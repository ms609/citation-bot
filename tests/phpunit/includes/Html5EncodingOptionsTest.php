<?php

declare(strict_types=1);

final class Html5EncodingOptionsTest extends PHPUnit\Framework\TestCase {
    /** @var array<string> */
    private const array HTML_ENCODING_FUNCTIONS = [
        'htmlspecialchars',
        'htmlspecialchars_decode',
        'htmlentities',
        'html_entity_decode',
    ];

    public function testAllHtmlEncodingAndDecodingCallsSpecifyHtml5(): void {
        $source_root = realpath(__DIR__ . '/../../../src');
        $this->assertNotFalse($source_root);

        $violations = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $source_root,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (
                !$file instanceof SplFileInfo ||
                !$file->isFile() ||
                $file->getExtension() !== 'php'
            ) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            $this->assertNotFalse($contents);

            foreach (self::htmlFunctionCalls($contents) as $call) {
                if (self::callSpecifiesHtml5($call['arguments'])) {
                    continue;
                }

                $relative_path = substr(
                    $file->getPathname(),
                    strlen($source_root) + 1
                );
                $violations[] = sprintf(
                    '%s:%d %s() must specify ENT_HTML5 in its flags argument',
                    $relative_path,
                    $call['line'],
                    $call['function']
                );
            }
        }

        $this->assertSame(
            [],
            $violations,
            "HTML encoding/decoding calls without ENT_HTML5:\n"
                . implode("\n", $violations)
        );
    }

    /**
     * @return array<int, array{
     *     function: string,
     *     line: int,
     *     arguments: array<int, array<int, array|string>>
     * }>
     */
    private static function htmlFunctionCalls(string $source): array {
        $tokens = token_get_all($source);
        $calls = [];

        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }

            $function = self::htmlFunctionName($token);
            if ($function === null) {
                continue;
            }

            $previous = self::previousSignificantToken($tokens, $index);
            if (
                $previous === T_FUNCTION ||
                $previous === T_OBJECT_OPERATOR ||
                $previous === T_DOUBLE_COLON
            ) {
                continue;
            }

            $open_paren_index = self::nextSignificantTokenIndex(
                $tokens,
                $index + 1
            );
            if (
                $open_paren_index === null ||
                $tokens[$open_paren_index] !== '('
            ) {
                continue;
            }

            $arguments = self::functionArguments($tokens, $open_paren_index);
            if ($arguments === null) {
                continue;
            }

            $calls[] = [
                'function' => $function,
                'line' => $token[2],
                'arguments' => $arguments,
            ];
        }

        return $calls;
    }

    /**
     * @param array{int, string, int} $token
     */
    private static function htmlFunctionName(array $token): ?string {
        $supported_token_types = [T_STRING];
        if (defined('T_NAME_FULLY_QUALIFIED')) {
            $supported_token_types[] = T_NAME_FULLY_QUALIFIED;
        }
        if (defined('T_NAME_QUALIFIED')) {
            $supported_token_types[] = T_NAME_QUALIFIED;
        }

        if (!in_array($token[0], $supported_token_types, true)) {
            return null;
        }

        $name = mb_strtolower(ltrim($token[1], '\\'));
        if (str_contains($name, '\\')) {
            $parts = explode('\\', $name);
            $name = (string) end($parts);
        }

        return in_array($name, self::HTML_ENCODING_FUNCTIONS, true)
            ? $name
            : null;
    }

    /**
     * @param array<int, array|string> $tokens
     */
    private static function previousSignificantToken(
        array $tokens,
        int $index
    ): int|string|null {
        for ($i = $index - 1; $i >= 0; --$i) {
            $token = $tokens[$i];
            if (
                is_array($token) &&
                in_array(
                    $token[0],
                    [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT],
                    true
                )
            ) {
                continue;
            }
            return is_array($token) ? $token[0] : $token;
        }
        return null;
    }

    /**
     * @param array<int, array|string> $tokens
     */
    private static function nextSignificantTokenIndex(
        array $tokens,
        int $start
    ): ?int {
        $count = count($tokens);
        for ($i = $start; $i < $count; ++$i) {
            $token = $tokens[$i];
            if (
                is_array($token) &&
                in_array(
                    $token[0],
                    [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT],
                    true
                )
            ) {
                continue;
            }
            return $i;
        }
        return null;
    }

    /**
     * Parse the top-level arguments of a function call.
     *
     * @param array<int, array|string> $tokens
     * @return array<int, array<int, array|string>>|null
     */
    private static function functionArguments(
        array $tokens,
        int $open_paren_index
    ): ?array {
        $arguments = [[]];
        $paren_depth = 1;
        $bracket_depth = 0;
        $brace_depth = 0;
        $count = count($tokens);

        for ($i = $open_paren_index + 1; $i < $count; ++$i) {
            $token = $tokens[$i];

            if (is_string($token)) {
                if ($token === '(') {
                    ++$paren_depth;
                } elseif ($token === ')') {
                    --$paren_depth;
                    if ($paren_depth === 0) {
                        return $arguments;
                    }
                } elseif ($token === '[') {
                    ++$bracket_depth;
                } elseif ($token === ']') {
                    --$bracket_depth;
                } elseif ($token === '{') {
                    ++$brace_depth;
                } elseif ($token === '}') {
                    --$brace_depth;
                } elseif (
                    $token === ',' &&
                    $paren_depth === 1 &&
                    $bracket_depth === 0 &&
                    $brace_depth === 0
                ) {
                    $arguments[] = [];
                    continue;
                }
            }

            $arguments[array_key_last($arguments)][] = $token;
        }

        return null;
    }

    /**
     * ENT_HTML5 is the flags value for all four monitored routines.
     * Accept it in any argument after the data/string argument so named
     * arguments remain valid even if they are supplied out of declaration
     * order.
     *
     * @param array<int, array<int, array|string>> $arguments
     */
    private static function callSpecifiesHtml5(array $arguments): bool {
        foreach (array_slice($arguments, 1) as $argument) {
            foreach ($argument as $token) {
                if (
                    is_array($token) &&
                    $token[0] === T_STRING &&
                    mb_strtoupper($token[1]) === 'ENT_HTML5'
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
