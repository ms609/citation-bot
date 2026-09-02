<?php

declare(strict_types=1);

final class StrictComparisonTest extends PHPUnit\Framework\TestCase {

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

    public function testSourceDoesNotUseLooseEqualityOperators(): void {
        $violations = [];

        foreach (self::sourceFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);

            foreach (token_get_all($source) as $token) {
                if (!is_array($token) || !in_array($token[0], [T_IS_EQUAL, T_IS_NOT_EQUAL], true)) {
                    continue;
                }
                $violations[] = sprintf(
                    '%s:%d use ===/!== instead of %s',
                    self::relativePath($file),
                    $token[2],
                    $token[1]
                );
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }
}
