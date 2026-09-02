<?php

declare(strict_types=1);

final class EchoCommaTest extends PHPUnit\Framework\TestCase {

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

    public function testEchoDoesNotConcatenateTopLevelOperands(): void {
        $violations = [];

        foreach (self::sourceFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);
            $tokens = token_get_all($source);
            $count = count($tokens);

            for ($i = 0; $i < $count; ++$i) {
                $token = $tokens[$i];
                if (!is_array($token) || $token[0] !== T_ECHO) {
                    continue;
                }

                $paren = 0;
                $bracket = 0;
                $brace = 0;
                for ($j = $i + 1; $j < $count; ++$j) {
                    $candidate = $tokens[$j];
                    $text = is_array($candidate) ? $candidate[1] : $candidate;

                    if ($text === '(') {
                        ++$paren;
                    } elseif ($text === ')') {
                        --$paren;
                    } elseif ($text === '[') {
                        ++$bracket;
                    } elseif ($text === ']') {
                        --$bracket;
                    } elseif ($text === '{') {
                        ++$brace;
                    } elseif ($text === '}') {
                        --$brace;
                    } elseif ($text === ';' && $paren === 0 && $bracket === 0 && $brace === 0) {
                        break;
                    } elseif ($text === '.' && $paren === 0 && $bracket === 0 && $brace === 0) {
                        $violations[] = sprintf(
                            '%s:%d echo should use comma-separated operands instead of concatenation',
                            self::relativePath($file),
                            $token[2]
                        );
                        break;
                    }
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }
}
