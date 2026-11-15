<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/src/includes/RegularFileIdentity.php';

final class RegularFileIdentityTest extends \PHPUnit\Framework\TestCase {
    public function testMatchingRegularFileIsAccepted(): void {
        $path = tempnam(sys_get_temp_dir(), 'citation-bot-identity-');
        $this->assertNotFalse($path);
        $handle = fopen($path, 'rb');
        $this->assertNotFalse($handle);
        try {
            clearstatcache(true, $path);
            $this->assertTrue(citation_bot_regular_file_identity_matches(fstat($handle), lstat($path)));
        } finally {
            fclose($handle);
            unlink($path);
        }
    }

    public function testReplacedPathIsRejectedWithOldDescriptorStillOpen(): void {
        $path = tempnam(sys_get_temp_dir(), 'citation-bot-identity-');
        $this->assertNotFalse($path);
        $handle = fopen($path, 'rb');
        $this->assertNotFalse($handle);
        try {
            $this->assertTrue(unlink($path));
            $this->assertNotFalse(file_put_contents($path, 'replacement'));
            clearstatcache(true, $path);
            $this->assertFalse(citation_bot_regular_file_identity_matches(fstat($handle), lstat($path)));
        } finally {
            fclose($handle);
            @unlink($path);
        }
    }

    public function testSymbolicLinkPathIsRejected(): void {
        if (!function_exists('symlink') || PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Symlink support required');
        }
        $target = tempnam(sys_get_temp_dir(), 'citation-bot-identity-');
        $this->assertNotFalse($target);
        $link = $target . '.link';
        $handle = fopen($target, 'rb');
        $this->assertNotFalse($handle);
        try {
            if (!@symlink($target, $link)) {
                $this->markTestSkipped('Filesystem cannot create symbolic links');
            }
            clearstatcache(true, $link);
            $this->assertFalse(citation_bot_regular_file_identity_matches(fstat($handle), lstat($link)));
        } finally {
            fclose($handle);
            @unlink($link);
            @unlink($target);
        }
    }

    public function testNonRegularFileTypesAndInvalidStatsAreRejected(): void {
        $file = ['mode' => 0100600, 'dev' => 17, 'ino' => 42];
        $this->assertTrue(citation_bot_regular_file_identity_matches($file, $file));
        $this->assertFalse(citation_bot_regular_file_identity_matches(false, $file));
        $this->assertFalse(citation_bot_regular_file_identity_matches($file, false));
        $this->assertFalse(citation_bot_regular_file_identity_matches([], $file));
        $this->assertFalse(citation_bot_regular_file_identity_matches($file, ['mode' => 0100600]));
        $this->assertFalse(citation_bot_regular_file_identity_matches($file, [...$file, 'ino' => 43]));
        $this->assertFalse(citation_bot_regular_file_identity_matches($file, [...$file, 'dev' => 18]));
        foreach ([0040000, 0120000, 0010000, 0140000, 0060000] as $non_regular_mode) {
            $non_regular = [...$file, 'mode' => $non_regular_mode | 0600];
            $this->assertFalse(citation_bot_regular_file_identity_matches($file, $non_regular));
            $this->assertFalse(citation_bot_regular_file_identity_matches($non_regular, $file));
        }
    }

    public function testActualDirectoryIsRejected(): void {
        $stat = lstat(sys_get_temp_dir());
        $this->assertNotFalse($stat);
        $this->assertFalse(citation_bot_regular_file_identity_matches($stat, $stat));
    }

    public function testHardLinkIdentityDoesNotReplaceCallerLinkCountPolicy(): void {
        $path = tempnam(sys_get_temp_dir(), 'citation-bot-identity-');
        $this->assertNotFalse($path);
        $link = $path . '.hardlink';
        try {
            if (!@link($path, $link)) {
                $this->markTestSkipped('Filesystem cannot create hard links');
            }
            $handle = fopen($path, 'rb');
            $this->assertNotFalse($handle);
            try {
                clearstatcache(true, $link);
                $this->assertTrue(citation_bot_regular_file_identity_matches(fstat($handle), lstat($link)));
                $this->assertGreaterThan(1, fstat($handle)['nlink']);
            } finally {
                fclose($handle);
            }
        } finally {
            @unlink($link);
            @unlink($path);
        }
    }
}
