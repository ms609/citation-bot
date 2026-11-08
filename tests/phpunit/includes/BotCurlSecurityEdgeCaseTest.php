<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

/**
 * Network-free edge cases for the destination filtering and mandatory option
 * hardening in bot_curl.php. No request is ever sent.
 */
final class BotCurlSecurityEdgeCaseTest extends PHPUnit\Framework\TestCase {

    /** @return array<string, array{string, bool}> */
    public static function addressProvider(): array {
        return [
            'not an address' => ['not-an-ip', false],
            'empty string' => ['', false],
            'octet out of range' => ['999.999.999.999', false],
            'public ipv4' => ['8.8.8.8', true],
            'loopback' => ['127.0.0.1', false],
            'private 10/8' => ['10.1.2.3', false],
            'private 192.168/16' => ['192.168.1.1', false],
            'link local' => ['169.254.1.1', false],
            'carrier grade nat' => ['100.64.0.1', false],
            'ipv4 multicast low' => ['224.0.0.1', false],
            'ipv4 multicast high' => ['239.255.255.255', false],
            'limited broadcast' => ['255.255.255.255', false],
            'unspecified ipv4' => ['0.0.0.0', false],
            'ipv6 loopback' => ['::1', false],
            'ipv6 unspecified' => ['::', false],
            'ipv6 link local' => ['fe80::1', false],
            'ipv6 unique local' => ['fc00::1', false],
            'ipv6 multicast' => ['ff02::1', false],
            'ipv6 documentation' => ['2001:db8::1', false],
            'public ipv6' => ['2606:4700:4700::1111', true],
            'mapped loopback' => ['::ffff:127.0.0.1', false],
            'mapped loopback in hex' => ['::ffff:7f00:1', false],
            'mapped private' => ['::ffff:10.0.0.1', false],
            'mapped public' => ['::ffff:8.8.8.8', true],
            'nat64 public' => ['64:ff9b::808:808', true],
            'nat64 loopback' => ['64:ff9b::7f00:1', false],
            'nat64 private' => ['64:ff9b::a00:1', false],
            'nat64 multicast' => ['64:ff9b::e000:1', false],
            'local use translation prefix' => ['64:ff9b:1::1', false],
            'local use translation prefix upper end' => ['64:ff9b:1:ffff::1', false],
        ];
    }

    #[DataProvider('addressProvider')]
    public function testDestinationAddressClassification(string $address, bool $expected): void {
        $this->assertSame($expected, bot_curl_ip_is_public($address));
    }

    private function unconfiguredHandle(): CurlHandle {
        // phpcs:ignore Generic.PHP.ForbiddenFunctions.FoundWithAlternative -- These tests need a handle without bot defaults.
        $handle = curl_init();
        if ($handle === false) {
            $this->fail('Could not initialize a cURL test handle');
        }
        return $handle;
    }

    public function testFailureToApplySecurityOptionsIsFatal(): void {
        if (!function_exists('uopz_set_return') || !function_exists('uopz_unset_return')) {
            $this->markTestSkipped('uopz is required to simulate a cURL option failure');
        }
        $handle = $this->unconfiguredHandle();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('curl_setopt_array', false);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unable to apply mandatory cURL security options.');
            bot_curl_apply_security_options($handle);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('curl_setopt_array');
        }
    }

    public function testNormalHandlesGetStrictTlsVerification(): void {
        $handle = bot_curl_init(1.0, [], BOT_CURL_DEFAULT_MAX_RESPONSE_BYTES);
        $this->assertFalse(bot_curl_is_legacy_tls_probe($handle));
    }

    public function testTransferResultDefaultsToNotRun(): void {
        $handle = bot_curl_init(1.0, [], BOT_CURL_DEFAULT_MAX_RESPONSE_BYTES);
        $result = bot_curl_last_transfer($handle);
        $this->assertFalse($result['ok']);
        $this->assertSame(0, $result['errno']);
        $this->assertSame(0, $result['http_code']);
        $this->assertSame('cURL transfer has not run', $result['error']);
    }

    public function testTlsFallbackIsOnlyOfferedForTlsFailures(): void {
        $this->assertTrue(bot_curl_is_tls_compatibility_error(CURLE_SSL_CONNECT_ERROR));
        $this->assertTrue(bot_curl_is_tls_compatibility_error(CURLE_SSL_CIPHER));
        $this->assertTrue(bot_curl_is_tls_compatibility_error(CURLE_SSL_CACERT));
        $this->assertFalse(bot_curl_is_tls_compatibility_error(CURLE_OK));
        $this->assertFalse(bot_curl_is_tls_compatibility_error(CURLE_COULDNT_RESOLVE_HOST));
        $this->assertFalse(bot_curl_is_tls_compatibility_error(CURLE_COULDNT_CONNECT));
        $this->assertFalse(bot_curl_is_tls_compatibility_error(CURLE_OPERATION_TIMEDOUT));
        $this->assertFalse(bot_curl_is_tls_compatibility_error(CURLE_HTTP_RETURNED_ERROR));
    }

    public function testResponseLimitDefaultsWhenNoneWasSet(): void {
        $handle = $this->unconfiguredHandle();
        $this->assertSame(BOT_CURL_DEFAULT_MAX_RESPONSE_BYTES, bot_curl_get_max_response_bytes($handle));
    }
}
