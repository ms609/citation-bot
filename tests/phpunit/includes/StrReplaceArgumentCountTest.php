<?php

declare(strict_types=1);

/**
 * PHP's str_replace() silently pads a shorter $replace array with empty
 * strings when it has fewer elements than $search: e.g.
 *   str_replace(['a', 'b', 'c'], ['x'], $s)
 * replaces 'a' with 'x', but 'b' and 'c' with '' -- silently, with no
 * warning. That is almost never what the author intended; it is easy to
 * add a search term and forget to add its replacement.
 *
 * This test statically scans the source for str_replace() calls where both
 * the search and replace arguments are literal arrays, and fails if their
 * element counts differ, so that every replacement must be given
 * explicitly instead of relying on the implicit "" default.
 *
 * Calls where either argument is not a literal array (a variable, a
 * function call, a single scalar broadcast across the whole search array,
 * etc.) cannot be checked statically and are skipped.
 */
final class StrReplaceArgumentCountTest extends PHPUnit\Framework\TestCase {
    /** @return array<int, string> */
    private static function sourceFiles(): array {
        $root = realpath(__DIR__ . '/../../../src');
        if ($root === false) {
            throw new RuntimeException('Could not locate src directory');
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        return $files;
    }

    private static function relativePath(string $path): string {
        $root = realpath(__DIR__ . '/../../../');
        if ($root === false) {
            return $path;
        }
        return str_replace('\\', '/', mb_substr($path, mb_strlen($root) + 1));
    }

    /** @param array<int, array|string> $tokens */
    private static function nextSignificantIndex(array $tokens, int $start): ?int {
        for ($i = $start, $count = count($tokens); $i < $count; ++$i) {
            $token = $tokens[$i];
            if (
                is_array($token) &&
                in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                continue;
            }
            return $i;
        }
        return null;
    }

    /** @param array<int, array|string> $tokens */
    private static function previousSignificantIndex(array $tokens, int $start, int $floor = 0): ?int {
        for ($i = $start; $i >= $floor; --$i) {
            $token = $tokens[$i];
            if (
                is_array($token) &&
                in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                continue;
            }
            return $i;
        }
        return null;
    }

    private static function isOpeningBracket(mixed $token): bool {
        if ($token === '(' || $token === '[' || $token === '{') {
            return true;
        }
        // "{$expr}" / "${expr}" string-interpolation braces: tokenized as
        // T_CURLY_OPEN / T_DOLLAR_OPEN_CURLY_BRACES, closed by a plain '}'.
        return is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true);
    }

    private static function isClosingBracket(mixed $token): bool {
        return $token === ')' || $token === ']' || $token === '}';
    }

    /**
     * Finds the index of the token that closes the bracket opened at $openIndex.
     * @param array<int, array|string> $tokens
     */
    private static function matchingCloserIndex(array $tokens, int $openIndex): ?int {
        $depth = 0;
        for ($i = $openIndex, $count = count($tokens); $i < $count; ++$i) {
            $token = $tokens[$i];
            if (self::isOpeningBracket($token)) {
                ++$depth;
            } elseif (self::isClosingBracket($token)) {
                --$depth;
                if ($depth === 0) {
                    return $i;
                }
            }
        }
        return null;
    }

    /**
     * Splits the tokens strictly between $openIndex and $closeIndex on
     * top-level commas (commas inside nested brackets don't count).
     * @param array<int, array|string> $tokens
     * @return array<int, array{0:int,1:int}> list of [start, end] (inclusive) index ranges
     */
    private static function splitTopLevelArguments(array $tokens, int $openIndex, int $closeIndex): array {
        $args = [];
        $depth = 0;
        $segmentStart = $openIndex + 1;
        for ($i = $openIndex + 1; $i < $closeIndex; ++$i) {
            $token = $tokens[$i];
            if (self::isOpeningBracket($token)) {
                ++$depth;
            } elseif (self::isClosingBracket($token)) {
                --$depth;
            } elseif ($token === ',' && $depth === 0) {
                $args[] = [$segmentStart, $i - 1];
                $segmentStart = $i + 1;
            }
        }
        if ($segmentStart <= $closeIndex - 1) {
            $args[] = [$segmentStart, $closeIndex - 1];
        }
        return $args;
    }

    /**
     * If the token range [$start, $end] is exactly one array literal
     * (short `[...]` or long `array(...)` syntax), returns its element
     * count. Returns null when the argument isn't a literal array (a
     * variable, a function call, a scalar, ...), since those can't be
     * checked statically.
     * @param array<int, array|string> $tokens
     */
    private static function literalArrayElementCount(array $tokens, int $start, int $end): ?int {
        $firstIndex = self::nextSignificantIndex($tokens, $start);
        if ($firstIndex === null || $firstIndex > $end) {
            return null;
        }
        $lastIndex = self::previousSignificantIndex($tokens, $end, $start);
        if ($lastIndex === null) {
            return null;
        }

        $first = $tokens[$firstIndex];
        if ($first === '[') {
            $openIndex = $firstIndex;
        } elseif (is_array($first) && $first[0] === T_ARRAY) {
            $next = self::nextSignificantIndex($tokens, $firstIndex + 1);
            if ($next === null || $next > $end || $tokens[$next] !== '(') {
                return null;
            }
            $openIndex = $next;
        } else {
            return null; // Not a literal array; e.g. a variable or function call.
        }

        $closeIndex = self::matchingCloserIndex($tokens, $openIndex);
        if ($closeIndex === null || $closeIndex !== $lastIndex) {
            // The bracket doesn't span the whole argument, e.g. `['a'] + $x`.
            return null;
        }

        $count = 0;
        foreach (self::splitTopLevelArguments($tokens, $openIndex, $closeIndex) as [$elementStart, $elementEnd]) {
            $sig = self::nextSignificantIndex($tokens, $elementStart);
            if ($sig !== null && $sig <= $elementEnd) {
                ++$count; // Ignores a trailing comma, which yields an empty segment.
            }
        }
        return $count;
    }

    public function testSearchAndReplaceArraysHaveMatchingLengths(): void {
        $violations = [];

        foreach (self::sourceFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);
            $tokens = token_get_all($source);

            foreach ($tokens as $index => $token) {
                if (
                    !is_array($token) ||
                    $token[0] !== T_STRING ||
                    mb_strtolower($token[1]) !== 'str_replace'
                ) {
                    continue;
                }

                $previous = self::previousSignificantIndex($tokens, $index - 1);
                if ($previous !== null && is_array($tokens[$previous]) && in_array(
                    $tokens[$previous][0],
                    [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR],
                    true
                )) {
                    continue; // A method/constant named str_replace, not the built-in function.
                }

                $open = self::nextSignificantIndex($tokens, $index + 1);
                if ($open === null || $tokens[$open] !== '(') {
                    continue;
                }
                $close = self::matchingCloserIndex($tokens, $open);
                if ($close === null) {
                    continue;
                }

                $arguments = self::splitTopLevelArguments($tokens, $open, $close);
                if (count($arguments) < 2) {
                    continue; // str_replace() requires at least $search and $replace.
                }

                [$searchStart, $searchEnd] = $arguments[0];
                [$replaceStart, $replaceEnd] = $arguments[1];
                $searchCount = self::literalArrayElementCount($tokens, $searchStart, $searchEnd);
                $replaceCount = self::literalArrayElementCount($tokens, $replaceStart, $replaceEnd);

                if ($searchCount === null || $replaceCount === null) {
                    continue; // Not two literal arrays; cannot be checked statically.
                }

                if ($searchCount !== $replaceCount) {
                    $violations[] = sprintf(
                        '%s:%d str_replace() has %d search term(s) but only %d replacement(s); '
                            . 'PHP would silently fill the missing replacement(s) with "" -- '
                            . 'supply one explicit replacement per search term instead.',
                        self::relativePath($file),
                        $token[2],
                        $searchCount,
                        $replaceCount
                    );
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }
}
