<?php

declare(strict_types=1);

final class CurlBooleanOptionTest extends PHPUnit\Framework\TestCase {
    private const array BOOLEAN_OPTIONS = [
        'CURLOPT_AUTOREFERER',
        'CURLOPT_COOKIESESSION',
        'CURLOPT_FAILONERROR',
        'CURLOPT_FILETIME',
        'CURLOPT_FOLLOWLOCATION',
        'CURLOPT_FORBID_REUSE',
        'CURLOPT_FRESH_CONNECT',
        'CURLOPT_HEADER',
        'CURLOPT_HTTPGET',
        'CURLOPT_NOBODY',
        'CURLOPT_NOPROGRESS',
        'CURLOPT_NOSIGNAL',
        'CURLOPT_POST',
        'CURLOPT_RETURNTRANSFER',
        'CURLOPT_SSL_VERIFYPEER',
        'CURLOPT_TCP_NODELAY',
        'CURLOPT_UPLOAD',
        'CURLOPT_VERBOSE',
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

    public function testCurlBooleanOptionsUseBooleanLiterals(): void {
        $violations = [];

        foreach (self::sourceFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);
            $tokens = token_get_all($source);

            foreach ($tokens as $index => $token) {
                if (
                    !is_array($token) ||
                    $token[0] !== T_STRING ||
                    !in_array($token[1], self::BOOLEAN_OPTIONS, true)
                ) {
                    continue;
                }

                $next = self::nextSignificantIndex($tokens, $index + 1);
                if ($next === null) {
                    continue;
                }

                // Array form: CURLOPT_FOLLOWLOCATION => 1
                if (is_array($tokens[$next]) && $tokens[$next][0] === T_DOUBLE_ARROW) {
                    $valueIndex = self::nextSignificantIndex($tokens, $next + 1);
                // curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1)
                } elseif ($tokens[$next] === ',') {
                    $valueIndex = self::nextSignificantIndex($tokens, $next + 1);
                } else {
                    continue;
                }

                if ($valueIndex === null) {
                    continue;
                }
                $value = $tokens[$valueIndex];
                if (is_array($value) && $value[0] === T_LNUMBER && in_array($value[1], ['0', '1'], true)) {
                    $violations[] = sprintf(
                        '%s:%d %s must use true/false, not %s',
                        self::relativePath($file),
                        $token[2],
                        $token[1],
                        $value[1]
                    );
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }
}
