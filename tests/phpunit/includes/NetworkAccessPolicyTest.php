<?php

declare(strict_types=1);

final class NetworkAccessPolicyTest extends PHPUnit\Framework\TestCase {

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

    private static function tokenText(array|string $token): string {
        return is_array($token) ? $token[1] : $token;
    }

    /** @param array<int, array|string> $tokens */
    private static function nextSignificantIndex(array $tokens, int $start): ?int {
        $count = count($tokens);
        for ($i = $start; $i < $count; ++$i) {
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

    /**
     * @param array<int, array|string> $tokens
     * @return array<int, string>|null
     */
    private static function callArguments(array $tokens, int $openParen): ?array {
        $arguments = [''];
        $paren = 1;
        $bracket = 0;
        $brace = 0;
        $count = count($tokens);

        for ($i = $openParen + 1; $i < $count; ++$i) {
            $token = $tokens[$i];
            $text = self::tokenText($token);

            if ($text === '(') {
                ++$paren;
            } elseif ($text === ')') {
                --$paren;
                if ($paren === 0) {
                    return $arguments;
                }
            } elseif ($text === '[') {
                ++$bracket;
            } elseif ($text === ']') {
                --$bracket;
            } elseif ($text === '{') {
                ++$brace;
            } elseif ($text === '}') {
                --$brace;
            } elseif ($text === ',' && $paren === 1 && $bracket === 0 && $brace === 0) {
                $arguments[] = '';
                continue;
            }

            $arguments[array_key_last($arguments)] .= $text;
        }

        return null;
    }

    public function testNetworkAccessUsesCurlWrapper(): void {
        $violations = [];

        foreach (self::sourceFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);
            $tokens = token_get_all($source);

            foreach ($tokens as $index => $token) {
                if (!is_array($token) || $token[0] !== T_STRING) {
                    continue;
                }

                $function = mb_strtolower($token[1]);
                if (!in_array($function, ['file_get_contents', 'get_headers'], true)) {
                    continue;
                }

                $open = self::nextSignificantIndex($tokens, $index + 1);
                if ($open === null || $tokens[$open] !== '(') {
                    continue;
                }
                $arguments = self::callArguments($tokens, $open);
                if ($arguments === null) {
                    continue;
                }

                if ($function === 'get_headers') {
                    $violations[] = sprintf(
                        '%s:%d get_headers() is forbidden; use bot_curl_init()/bot_curl_exec()',
                        self::relativePath($file),
                        $token[2]
                    );
                    continue;
                }

                $first = mb_strtolower($arguments[0] ?? '');
                $apiPath = str_contains(
                    str_replace('\\', '/', $file),
                    '/src/includes/api/'
                );
                $literalNetworkUrl =
                    str_contains($first, 'http://') ||
                    str_contains($first, 'https://') ||
                    str_contains($first, 'ftp://');

                if ($apiPath || $literalNetworkUrl) {
                    $violations[] = sprintf(
                        '%s:%d network file_get_contents() is forbidden; use cURL',
                        self::relativePath($file),
                        $token[2]
                    );
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }
}
