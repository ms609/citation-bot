<?php

declare(strict_types=1);

final class ExternalApiBoundaryPolicyTest extends PHPUnit\Framework\TestCase {
    public function testExternalMetadataApisDoNotCallJsonDecodeDirectly(): void {
        $root = realpath(__DIR__ . '/../../../../src/includes/api');
        $this->assertNotFalse($root);
        $violations = [];

        foreach (glob($root . '/*.php') ?: [] as $file) {
            if (basename($file) === 'APIResponseGuard.php') {
                continue;
            }
            $source = file_get_contents($file);
            $this->assertIsString($source);

            foreach (token_get_all($source) as $token) {
                if (
                    is_array($token) &&
                    $token[0] === T_STRING &&
                    mb_strtolower($token[1]) === 'json_decode'
                ) {
                    $violations[] = sprintf(
                        '%s:%d raw json_decode() bypasses ExternalApiResponseGuard',
                        basename($file),
                        $token[2]
                    );
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }
}
