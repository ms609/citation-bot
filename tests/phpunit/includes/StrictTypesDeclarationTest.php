<?php

declare(strict_types=1);

final class StrictTypesDeclarationTest extends PHPUnit\Framework\TestCase {

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

    public function testEverySourcePhpFileDeclaresStrictTypes(): void {
        $violations = [];

        foreach (self::sourceFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);

            if (
                preg_match(
                    '~declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;~',
                    mb_substr($source, 0, 1024)
                ) !== 1
            ) {
                $violations[] = self::relativePath($file) . ' must declare(strict_types=1)';
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }
}
