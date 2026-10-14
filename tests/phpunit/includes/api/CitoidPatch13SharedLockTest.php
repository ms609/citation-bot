<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

final class CitoidPatch13SharedLockTest extends testBaseClass {
    /** @var array<string, string|false> */
    private array $saved_environment = [];
    /** @var array<string> */
    private array $directories = [];

    private function assignEnvironment(string $name, string $value): void {
        if (!array_key_exists($name, $this->saved_environment)) {
            $this->saved_environment[$name] = getenv($name, true);
        }
        $this->assertTrue(putenv($name . '=' . $value));
    }

    #[\Override]
    protected function tearDown(): void {
        foreach ($this->saved_environment as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }
        foreach ($this->directories as $directory) {
            foreach (['rate.state', 'rate.lock', 'rate.namespace'] as $name) {
                @unlink($directory . '/' . $name);
            }
            @rmdir($directory . '/rate.lock');
            @rmdir($directory);
        }
        parent::tearDown();
    }

    public function testConfiguredNamespaceRefusesMissingOrNonRegularMutex(): void {
        $directory = sys_get_temp_dir() . '/citoid-patch13-' . bin2hex(random_bytes(12));
        $this->directories[] = $directory;
        $this->assertTrue(mkdir($directory, 0700));
        $id = bin2hex(random_bytes(16));
        $this->assertSame(39, file_put_contents($directory . '/rate.namespace', 'CBNS1|' . $id . "\n"));
        $this->assertTrue(chmod($directory . '/rate.namespace', 0600));
        $this->assignEnvironment('CITOID_RATE_LIMIT_DIR', $directory);
        $this->assignEnvironment('CITOID_RATE_LIMIT_NAMESPACE_ID', $id);
        $this->assignEnvironment('CITOID_ALLOW_LOCAL_FALLBACK', '1');
        $method = new ReflectionMethod(Zotero::class, 'throttle_citoid_requests');

        $reason = null;
        $this->assertFalse($method->invokeArgs(null, [null, 0.0, &$reason]));
        $this->assertSame('lock-unavailable', $reason);
        $this->assertFileDoesNotExist($directory . '/rate.lock');
        $this->assertFileDoesNotExist($directory . '/rate.state');

        $this->assertTrue(mkdir($directory . '/rate.lock', 0700));
        $reason = null;
        $this->assertFalse($method->invokeArgs(null, [null, 0.0, &$reason]));
        $this->assertSame('lock-unavailable', $reason);
        $this->assertTrue(rmdir($directory . '/rate.lock'));

        $this->assertSame(0, file_put_contents($directory . '/rate.lock', ''));
        $this->assertTrue(chmod($directory . '/rate.lock', 0600));
        $reason = null;
        $this->assertTrue($method->invokeArgs(null, [null, 0.0, &$reason]));
        $this->assertNull($reason);
        $this->assertFileExists($directory . '/rate.state');
    }
}
