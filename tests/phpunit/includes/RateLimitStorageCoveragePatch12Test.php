<?php

declare(strict_types=1);

/** Verify that rejected shared limiter writes never alter an existing snapshot. */
require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class RateLimitStorageCoveragePatch12Test extends testBaseClass {
    public function testNonfiniteStateCannotReplaceSnapshot(): void {
        $handle = tmpfile();
        $this->assertIsResource($handle);
        try {
            $original = '{"tokens":2.0,"updated":100.0}';
            $this->assertSame(mb_strlen($original, '8bit'), fwrite($handle, $original));
            $this->assertFalse(request_rate_limit_store_state($handle, INF, 100.0));
            $this->assertFalse(request_rate_limit_store_state($handle, 2.0, INF));
            $this->assertTrue(rewind($handle));
            $this->assertSame($original, stream_get_contents($handle));
        } finally {
            fclose($handle);
        }
    }

    public function testReadOnlyStateHandleCannotBeOverwritten(): void {
        $path = tempnam(sys_get_temp_dir(), 'citation-rate-p12-');
        $this->assertIsString($path);
        $original = '{"tokens":2.0,"updated":100.0}';
        try {
            $this->assertSame(mb_strlen($original, '8bit'), file_put_contents($path, $original));
            $handle = fopen($path, 'rb');
            $this->assertIsResource($handle);
            try {
                $this->assertFalse(request_rate_limit_store_state($handle, 1.0, 200.0));
            } finally {
                fclose($handle);
            }
            $this->assertSame($original, file_get_contents($path));
        } finally {
            unlink($path);
        }
    }
}
