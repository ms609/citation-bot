<?php

declare(strict_types=1);

/** Verify that rejected shared limiter writes never alter an existing snapshot. */
require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class RequestRateLimitEdgeCasesTest extends testBaseClass {
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

    public function testEmptyBaseDirectoryFailsClosedForEachAdmissionPhase(): void {
        $rejected = [false, 2, null, 'retry_later', 0];
        $this->assertSame($rejected, big_run_try_acquire(90, 'webform', '', 1234.0));
        $this->assertSame($rejected, big_run_try_acquire_discovery('', 1234.0));
        $this->assertSame($rejected, big_run_try_acquire_probe('', 1234.0));
        $this->assertSame($rejected, big_run_try_promote_probe_to_discovery('missing', '', 1234.0));
        $this->assertSame($rejected, big_run_try_mark_discovery_large('missing', '', 1234.0));
        $this->assertSame($rejected, big_run_try_promote('missing', 90, 'webform', '', 1234.0));
    }

    public function testEmptyBaseDirectoryDoesNotReleaseOrHeartbeat(): void {
        $this->assertSame('retry', big_run_heartbeat_status('missing', '', 1234.0));
        $this->assertFalse(big_run_heartbeat('missing', '', 1234.0));
        $before = big_run_last_state_invalid_reason();
        big_run_release('missing', '');
        $this->assertSame($before, big_run_last_state_invalid_reason());
    }

    public function testOperatorRecoveryRejectsEmptyDirectoryWithoutStateChanges(): void {
        $check = big_run_recovery_check('', 1234.0);
        $this->assertFalse($check['ok']);
        $this->assertSame('base_directory_empty', $check['reason']);
        $this->assertSame('', $check['state_path']);
        $reset = big_run_recovery_reset('', 1234.0);
        $this->assertFalse($reset['ok']);
        $this->assertSame('base_directory_empty', $reset['reason']);
        $this->assertNull($reset['backup_path']);
    }
}
