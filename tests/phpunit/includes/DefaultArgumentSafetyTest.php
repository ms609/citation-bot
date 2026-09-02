<?php

declare(strict_types=1);

final class DefaultArgumentSafetyTest extends PHPUnit\Framework\TestCase {
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

    public function testErrorHelpersDoNotDefaultToSuccessSemantics(): void {
        $violations = [];
        $functionPattern = '~function\\s+([A-Za-z_][A-Za-z0-9_]*)\\s*\\((.*?)\\)\\s*:\\s*([A-Za-z_\\\\][A-Za-z0-9_\\\\|?]*)~s';
        $defaultPattern = '~\\$([A-Za-z_][A-Za-z0-9_]*)\\s*=\\s*([0-9]+)~';

        foreach (self::sourceFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);

            if (preg_match_all($functionPattern, $source, $functions, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
                $this->fail('Could not scan ' . self::relativePath($file));
            }

            foreach ($functions as $function) {
                $name = $function[1][0];
                $parameters = $function[2][0];
                $returnType = mb_strtolower($function[3][0]);
                $isErrorHelper = $returnType === 'never' || preg_match('~(?:error|die|reject|fail|abort)~i', $name) === 1;
                if (!$isErrorHelper) {
                    continue;
                }

                if (preg_match_all($defaultPattern, $parameters, $defaults, PREG_SET_ORDER) === false) {
                    $this->fail('Could not scan defaults in ' . self::relativePath($file));
                }

                foreach ($defaults as $default) {
                    $parameterName = mb_strtolower($default[1]);
                    $value = (int) $default[2];
                    $unsafeExitCode = str_contains($parameterName, 'exit') && $value === 0;
                    $unsafeHttpStatus = str_contains($parameterName, 'status') && $value >= 200 && $value < 300;
                    if (!$unsafeExitCode && !$unsafeHttpStatus) {
                        continue;
                    }

                    $line = 1 + mb_substr_count(mb_substr($source, 0, $function[0][1]), "\n");
                    $violations[] = sprintf(
                        '%s:%d %s() must not default $%s to success value %d',
                        self::relativePath($file),
                        $line,
                        $name,
                        $default[1],
                        $value
                    );
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }
}
