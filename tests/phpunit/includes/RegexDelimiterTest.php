<?php

declare(strict_types=1);

final class RegexDelimiterTest extends PHPUnit\Framework\TestCase {
    private const array REGEX_FUNCTIONS = [
        'preg_filter',
        'preg_grep',
        'preg_match',
        'preg_match_all',
        'preg_replace',
        'preg_replace_callback',
        'preg_replace_callback_array',
        'preg_split',
        'safe_preg_replace',
    ];

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

    public function testLiteralRegularExpressionsUseTildeDelimiter(): void {
        $violations = [];

        foreach (self::sourceFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);
            $tokens = token_get_all($source);

            foreach ($tokens as $index => $token) {
                if (
                    !is_array($token) ||
                    $token[0] !== T_STRING ||
                    !in_array(mb_strtolower($token[1]), self::REGEX_FUNCTIONS, true)
                ) {
                    continue;
                }

                $open = self::nextSignificantIndex($tokens, $index + 1);
                if ($open === null || $tokens[$open] !== '(') {
                    continue;
                }
                $patternIndex = self::nextSignificantIndex($tokens, $open + 1);
                if ($patternIndex === null) {
                    continue;
                }
                $pattern = $tokens[$patternIndex];
                if (!is_array($pattern) || $pattern[0] !== T_CONSTANT_ENCAPSED_STRING) {
                    continue; // Dynamic/constant patterns are checked where defined.
                }

                $literal = $pattern[1];
                if (mb_strlen($literal) < 3 || $literal[1] !== '~') {
                    $violations[] = sprintf(
                        '%s:%d %s() literal pattern must use ~ as its delimiter',
                        self::relativePath($file),
                        $token[2],
                        $token[1]
                    );
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }
}
