<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/includes/RequestRateLimit.php';

final class RequestRateLimitTest extends PHPUnit\Framework\TestCase {
    private string $base_directory;

    #[\Override]
    protected function setUp(): void {
        $this->base_directory =
            sys_get_temp_dir() .
            DIRECTORY_SEPARATOR .
            'citation-bot-rate-limit-test-' .
            bin2hex(random_bytes(8));

        $this->assertTrue(mkdir($this->base_directory, 0700, true));
    }

    #[\Override]
    protected function tearDown(): void {
        $state_directory =
            $this->base_directory .
            DIRECTORY_SEPARATOR .
            REQUEST_RATE_LIMIT_STATE_DIRECTORY;

        if (is_dir($state_directory)) {
            foreach (glob($state_directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($state_directory);
        }
        @rmdir($this->base_directory);
    }

    public function testCapacityIsAllowedThenNextRequestIsLimited(): void {
        $this->assertNull(
            request_rate_limit_consume('capacity-test', 2, 1.0, $this->base_directory, 100.0)
        );
        $this->assertNull(
            request_rate_limit_consume('capacity-test', 2, 1.0, $this->base_directory, 100.0)
        );
        $this->assertSame(
            1,
            request_rate_limit_consume('capacity-test', 2, 1.0, $this->base_directory, 100.0)
        );
    }

    public function testTokensRefillOverTime(): void {
        $this->assertNull(
            request_rate_limit_consume('refill-test', 1, 0.25, $this->base_directory, 100.0)
        );
        $this->assertSame(
            3,
            request_rate_limit_consume('refill-test', 1, 0.25, $this->base_directory, 101.0)
        );
        $this->assertNull(
            request_rate_limit_consume('refill-test', 1, 0.25, $this->base_directory, 104.0)
        );
    }

    public function testLockContentionRejectsImmediately(): void {
        $this->assertNull(
            request_rate_limit_consume('lock-test', 2, 1.0, $this->base_directory, 100.0)
        );

        $state_path =
            $this->base_directory .
            DIRECTORY_SEPARATOR .
            REQUEST_RATE_LIMIT_STATE_DIRECTORY .
            DIRECTORY_SEPARATOR .
            'lock-test.json';

        $handle = fopen($state_path, 'r+');
        $this->assertIsResource($handle);

        try {
            $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));
            $started = microtime(true);
            $this->assertSame(
                1,
                request_rate_limit_consume('lock-test', 2, 1.0, $this->base_directory, 100.0)
            );
            $this->assertLessThan(0.5, microtime(true) - $started);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function testCorruptStateRecoversToFreshBucket(): void {
        $state_directory =
            $this->base_directory .
            DIRECTORY_SEPARATOR .
            REQUEST_RATE_LIMIT_STATE_DIRECTORY;
        $this->assertTrue(mkdir($state_directory, 0700, true));

        $state_path = $state_directory . DIRECTORY_SEPARATOR . 'corrupt-test.json';
        $this->assertNotFalse(file_put_contents($state_path, '{not-json'));

        $this->assertNull(
            request_rate_limit_consume('corrupt-test', 1, 1.0, $this->base_directory, 100.0)
        );
        $this->assertSame(
            1,
            request_rate_limit_consume('corrupt-test', 1, 1.0, $this->base_directory, 100.0)
        );
    }

    public function testInvalidConfigurationIsRejected(): void {
        $this->expectException(InvalidArgumentException::class);
        request_rate_limit_consume('../bad', 1, 1.0, $this->base_directory, 100.0);
    }
}
