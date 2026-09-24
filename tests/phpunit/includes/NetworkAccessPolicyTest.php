<?php

declare(strict_types=1);

final class NetworkAccessPolicyTest extends PHPUnit\Framework\TestCase {

    /**
     * file_get_contents() can dereference URL wrappers as well as local paths.
     * Keep the small set of reviewed production uses explicit so a future
     * file_get_contents($url) cannot silently create a second network stack
     * outside the hardened cURL wrapper.
     *
     * The count prevents an additional call from hiding behind an already
     * approved path/argument pair.
     *
     * @var array<string, array<string, int>>
     */
    private const ALLOWED_LOCAL_FILE_GET_CONTENTS = [
        'src/includes/RequestRateLimit.php' => ['$state_path' => 1],
        // $pages is restricted immediately beforehand to page_list.txt/page_list2.txt.
        'src/process_page.php' => ['$pages' => 1],
    ];

    /** @return array<int, string> */
    private static function sourceFiles(): array {
        $root = realpath(dirname(__DIR__, 3) . '/src');
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
        $root = realpath(dirname(__DIR__, 3));
        if ($root === false) {
            return $path;
        }
        return str_replace('\\', '/', mb_substr($path, mb_strlen($root) + 1));
    }

    private static function tokenText(array|string $token): string {
        return is_array($token) ? $token[1] : $token;
    }

    private static function approvedLocalFileGetContentsCount(
        string $relative_path,
        string $first_argument
    ): int {
        return self::ALLOWED_LOCAL_FILE_GET_CONTENTS[$relative_path][
            mb_trim($first_argument)
        ] ?? 0;
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
        $approved_local_reads_seen = [];

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

                $relative_path = self::relativePath($file);
                $first_argument = mb_trim($arguments[0] ?? '');
                $expected_count = self::approvedLocalFileGetContentsCount(
                    $relative_path,
                    $first_argument
                );

                if ($expected_count === 0) {
                    $violations[] = sprintf(
                        '%s:%d file_get_contents() is not an approved local-only read; ' .
                        'use the hardened cURL wrapper for network access',
                        $relative_path,
                        $token[2]
                    );
                    continue;
                }

                $read_key = $relative_path . "\0" . $first_argument;
                $approved_local_reads_seen[$read_key] =
                    ($approved_local_reads_seen[$read_key] ?? 0) + 1;
                if ($approved_local_reads_seen[$read_key] > $expected_count) {
                    $violations[] = sprintf(
                        '%s:%d additional file_get_contents(%s) call is not approved',
                        $relative_path,
                        $token[2],
                        $first_argument
                    );
                }
            }
        }

        foreach (self::ALLOWED_LOCAL_FILE_GET_CONTENTS as $path => $arguments) {
            foreach ($arguments as $argument => $expected_count) {
                $read_key = $path . "\0" . $argument;
                $actual_count = $approved_local_reads_seen[$read_key] ?? 0;
                if ($actual_count !== $expected_count) {
                    $violations[] = sprintf(
                        '%s approved local file_get_contents(%s) count changed: expected %d, found %d',
                        $path,
                        $argument,
                        $expected_count,
                        $actual_count
                    );
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function testFileGetContentsAllowlistDoesNotApproveArbitraryDynamicUrls(): void {
        $this->assertSame(
            1,
            self::approvedLocalFileGetContentsCount(
                'src/process_page.php',
                '$pages'
            )
        );
        $this->assertSame(
            1,
            self::approvedLocalFileGetContentsCount(
                'src/includes/RequestRateLimit.php',
                '$state_path'
            )
        );
        $this->assertSame(
            0,
            self::approvedLocalFileGetContentsCount(
                'src/process_page.php',
                '$url'
            )
        );
        $this->assertSame(
            0,
            self::approvedLocalFileGetContentsCount(
                'src/example.php',
                '$url'
            )
        );
        $this->assertSame(
            0,
            self::approvedLocalFileGetContentsCount(
                'src/includes/RequestRateLimit.php',
                '"https://example.invalid/state.json"'
            )
        );
    }

    public function testRequireAndIncludePathsDoNotTraverseParentDirectories(): void {
        $repository_root = realpath(dirname(__DIR__, 3));
        $this->assertIsString($repository_root);
        if (!is_string($repository_root)) {
            throw new RuntimeException('Could not locate repository root');
        }

        $files = [];
        foreach (['src', 'tests'] as $relative_directory) {
            $root = $repository_root . DIRECTORY_SEPARATOR . $relative_directory;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (
                    $file instanceof SplFileInfo &&
                    $file->isFile() &&
                    $file->getExtension() === 'php'
                ) {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);

        $violations = [];
        $include_tokens = [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE];

        foreach ($files as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);
            $tokens = token_get_all($source);
            $count = count($tokens);

            foreach ($tokens as $index => $token) {
                if (!is_array($token) || !in_array($token[0], $include_tokens, true)) {
                    continue;
                }

                $statement = '';
                for ($i = $index + 1; $i < $count; ++$i) {
                    $part = $tokens[$i];
                    if ($part === ';') {
                        break;
                    }
                    if (
                        is_array($part) &&
                        in_array($part[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
                    ) {
                        continue;
                    }
                    $statement .= self::tokenText($part);
                }

                if (str_contains($statement, '..')) {
                    $violations[] = sprintf(
                        '%s:%d require/include path uses parent traversal; use dirname()',
                        self::relativePath($file),
                        $token[2]
                    );
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function testInsecureTlsOptionsAreConfinedToCurlWrapper(): void {
        $violations = [];

        foreach (self::sourceFiles() as $file) {
            if (self::relativePath($file) === 'src/includes/bot_curl.php') {
                continue;
            }

            $source = file_get_contents($file);
            $this->assertIsString($source);

            /*
             * Legacy TLS is a deliberate capability, not a caller option.
             * Keeping all of these settings inside bot_curl.php prevents
             * future network code from silently creating another insecure
             * HTTPS path.
             */
            $forbidden = [
                '~CURLOPT_SSL_VERIFYPEER\s*=>\s*false~',
                '~CURLOPT_SSL_VERIFYHOST\s*=>\s*0~',
                '~ALL:@SECLEVEL=0~',
            ];

            foreach ($forbidden as $pattern) {
                if (preg_match($pattern, $source) === 1) {
                    $violations[] = sprintf(
                        '%s contains legacy TLS configuration outside bot_curl.php',
                        self::relativePath($file)
                    );
                    break;
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }
}
