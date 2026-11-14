<?php
declare(strict_types=1);

/*
 * Tests for bot_curl.php
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class BotCurlTest extends testBaseClass {

    public function testCurlLimitPageSizeZeroBytes(): void {
        new TestPage(); // Fill page name with test name for debugging
        $ch = bot_curl_init(1, [], 1 * 1024 * 1024);
        $this->assertNotFalse($ch);
        $this->assertSame(0, curl_limit_page_size($ch, 0, 0, 0, 0));
    }

    public function testCurlLimitPageSizeSmallPayload(): void {
        $ch = bot_curl_init(1, [], 1 * 1024 * 1024);
        $this->assertNotFalse($ch);
        $this->assertSame(0, curl_limit_page_size($ch, 0, 1000, 0, 0));
    }

    public function testCurlLimitPageSizeAtExactLimit(): void {
        $this->expectException(InvalidArgumentException::class);
        $ch = bot_curl_init(1, [], 256 * 1024 * 1024); // Too big
        $this->assertFalse($ch);
    }

    public function testCurlLimitPageSizeOneByteOverLimit(): void {
        $ch = bot_curl_init(1, [], 1000);
        $this->assertNotFalse($ch);
        $this->assertSame(1, curl_limit_page_size($ch, 0, 1001, 0, 0));
    }

    public function testCurlLimitPageSizeLargePayload(): void {
        $ch = bot_curl_init(1, [], 1024);
        $this->assertNotFalse($ch);
        $this->assertSame(1, curl_limit_page_size($ch, 0, 500000000, 0, 0));
    }

    public function testBotCurlInitReturnsCurlHandle(): void {
        $ch = bot_curl_init(1.0, [], 1 * 1024 * 1024);
        $this->assertInstanceOf(CurlHandle::class, $ch);
    }

    public function testBotCurlInitWithUrl(): void {
        $ch = bot_curl_init(1.0, [CURLOPT_URL => 'http://example.com'], 1 * 1024 * 1024);
        $this->assertInstanceOf(CurlHandle::class, $ch);
    }

    public function testBotCurlInitWithHalfTimeScale(): void {
        $ch = bot_curl_init(0.5, [], 1 * 1024 * 1024);
        $this->assertInstanceOf(CurlHandle::class, $ch);
    }

    public function testBotCurlInitWithZeroTimeScale(): void {
        $ch = bot_curl_init(0.0, [], 1 * 1024 * 1024);
        $this->assertInstanceOf(CurlHandle::class, $ch);
    }

    public function testBotCurlExecReadsLocalFile(): void {
        new TestPage(); // Fill page name with test name for debugging
        $filename = tempnam(sys_get_temp_dir(), 'citation-bot-curl-');
        $this->assertNotFalse($filename);
        flush();
        clearstatcache(true, $filename);
        file_put_contents($filename, 'local curl fixture', FILE_APPEND);
        flush();
        clearstatcache(true, $filename);

        $ch = bot_curl_init(1.0, [CURLOPT_URL => 'file://' . $filename], 1 * 1024 * 1024);
        $out = bot_curl_exec($ch);
        $this->assertSame('', $out);

        $ch = bot_curl_init(
            1.0,
            [
                CURLOPT_URL => 'file://' . $filename,
                CURLOPT_PROTOCOLS => CURLPROTO_ALL,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_ALL,
            ], 1 * 1024 * 1024);
        $out = bot_curl_exec($ch);
        $this->assertSame('', $out);

        $ch = bot_curl_init(1.0, [CURLOPT_URL => 'file://' . $filename], 1 * 1024 * 1024);
        curl_setopt_array($ch, [
            CURLOPT_PROTOCOLS => CURLPROTO_ALL,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_ALL,
        ]);
        $out = bot_curl_exec($ch);
        $this->assertSame('', $out); /** The IP address validation blocks this now */

        @unlink($filename);
    }

    public function testPublicCurlDestinationAccepted(): void {
        $ch = bot_curl_init(1, [], 1 * 1024 * 1024);
        $this->assertNotFalse($ch);

        $this->assertSame(
            CURL_PREREQFUNC_OK,
            bot_curl_check_destination(
                $ch,
                '8.8.8.8',
                '192.0.2.1',
                443,
                12345
            )
        );
    }

    public function testLoopbackCurlDestinationRejected(): void {
        $ch = bot_curl_init(1, [], 1 * 1024 * 1024);
        $this->assertNotFalse($ch);

        $this->assertSame(
            CURL_PREREQFUNC_ABORT,
            bot_curl_check_destination(
                $ch,
                '127.0.0.1',
                '127.0.0.1',
                80,
                12345
            )
        );
    }

    public function testPrivateCurlDestinationRejected(): void {
        $this->assertFalse(bot_curl_ip_is_public('10.0.0.1'));
        $this->assertFalse(bot_curl_ip_is_public('172.16.0.1'));
        $this->assertFalse(bot_curl_ip_is_public('192.168.0.1'));
    }

    public function testLinkLocalCurlDestinationRejected(): void {
        $this->assertFalse(bot_curl_ip_is_public('169.254.169.254'));
        $this->assertFalse(bot_curl_ip_is_public('fe80::1'));
    }

    public function testIpv6LoopbackCurlDestinationRejected(): void {
        $this->assertFalse(bot_curl_ip_is_public('::1'));
    }

    public function testIpv4MappedIpv6LoopbackRejected(): void {
        $this->assertFalse(bot_curl_ip_is_public('::ffff:127.0.0.1'));
    }

    public function testIpv6LocalUseTranslationPrefixRejected(): void {
        $this->assertFalse(bot_curl_ip_is_public('64:ff9b:1::'));
        $this->assertFalse(bot_curl_ip_is_public('64:ff9b:1::1'));
        $this->assertFalse(bot_curl_ip_is_public('64:ff9b:1:ffff:ffff:ffff:ffff:ffff'));
    }

    public function testIpv6WellKnownNat64PrefixClassifiesEmbeddedIpv4(): void {
        $this->assertFalse(bot_curl_ip_is_public('64:ff9b::7f00:1'));   // 127.0.0.1
        $this->assertFalse(bot_curl_ip_is_public('64:ff9b::a00:1'));    // 10.0.0.1
        $this->assertFalse(bot_curl_ip_is_public('64:ff9b::a9fe:a9fe')); // 169.254.169.254
        $this->assertFalse(bot_curl_ip_is_public('64:ff9b::c000:201')); // 192.0.2.1
        $this->assertFalse(bot_curl_ip_is_public('64:ff9b::e000:1'));   // 224.0.0.1
        $this->assertTrue(bot_curl_ip_is_public('64:ff9b::808:808'));   // 8.8.8.8
    }

    public function testEvenMoreRejectedIP1(): void {
        $this->assertFalse(bot_curl_ip_is_public('0.0.0.0'));
    }

    public function testEvenMoreRejectedIP2(): void {
        $this->assertFalse(bot_curl_ip_is_public('100.64.0.1'));    // CGNAT
    }

    public function testEvenMoreRejectedIP3(): void {
        $this->assertFalse(bot_curl_ip_is_public('192.0.2.1'));     // documentation
    }

    public function testEvenMoreRejectedIP4(): void {
        $this->assertFalse(bot_curl_ip_is_public('224.0.0.1'));     // multicast
    }

    public function testEvenMoreRejectedIP5(): void {
        $this->assertFalse(bot_curl_ip_is_public('255.255.255.255'));
    }

    public function testEvenMoreRejectedIP6(): void {
        $this->assertFalse(bot_curl_ip_is_public('fc00::1'));       // IPv6 ULA
    }

    public function testEvenMoreRejectedIP7(): void {
        $this->assertFalse(bot_curl_ip_is_public('ff02::1'));       // IPv6 multicast
    }

    public function testCurlLimitPageSizeUsesPerHandleLimit(): void {
        $ch = bot_curl_init(1.0, [], 1024);
        $this->assertNotFalse($ch);

        $this->assertSame(0, curl_limit_page_size($ch, 0, 1024, 0, 0));
        $this->assertSame(1, curl_limit_page_size($ch, 0, 1025, 0, 0));
        $this->assertSame(1024, bot_curl_get_max_response_bytes($ch));
    }

    public function testCurlResponseLimitRejectsInvalidValues(): void {
        $this->expectException(InvalidArgumentException::class);
        $ch = bot_curl_init(1.0, [], -1);
        $this->assertFalse($ch);
    }

    public function testMandatoryProtocolsExcludeFtpEverywhere(): void {
        $this->assertSame(
            CURLPROTO_HTTP | CURLPROTO_HTTPS,
            BOT_CURL_ALLOWED_PROTOCOLS_USE
        );
        $this->assertSame(0, BOT_CURL_ALLOWED_PROTOCOLS_USE & CURLPROTO_FTP);
        $this->assertSame(
            CURLPROTO_HTTP | CURLPROTO_HTTPS,
            BOT_CURL_ALLOWED_PROTOCOLS_END
        );
        $this->assertSame(0, BOT_CURL_ALLOWED_PROTOCOLS_END & CURLPROTO_FTP);
    }

    public function testSecurityOptionsCanBeAppliedToNormalHandle(): void {
        $ch = bot_curl_init(1.0, [], 1 * 1024 * 1024);
        $this->assertNotFalse($ch);
        bot_curl_apply_security_options($ch);
        $this->addToAssertionCount(1);
    }

    public function testLegacyTlsPolicyRequiresExplicitProbeHandle(): void {
        $strict = bot_curl_init(1.0, [], 1 * 1024 * 1024);
        $legacy = bot_curl_init_legacy_tls_probe(1.0, [], 1 * 1024 * 1024);

        $this->assertFalse(bot_curl_is_legacy_tls_probe($strict));
        $this->assertTrue(bot_curl_is_legacy_tls_probe($legacy));

        // Both policies must remain applicable without executing a request.
        bot_curl_apply_security_options($strict);
        bot_curl_apply_security_options($legacy);
        $this->addToAssertionCount(2);
    }

    public function testLegacyFallbackOnlyClassifiesTlsCompatibilityErrors(): void {
        $this->assertTrue(
            /*
             * PHP exposes libcurl error 60 under this legacy constant name;
             * CURLE_PEER_FAILED_VERIFICATION is not defined by every PHP
             * build used by the project.
             */
            bot_curl_is_tls_compatibility_error(CURLE_SSL_CACERT)
        );
        $this->assertTrue(
            bot_curl_is_tls_compatibility_error(CURLE_SSL_CONNECT_ERROR)
        );
        $this->assertTrue(
            bot_curl_is_tls_compatibility_error(CURLE_SSL_CIPHER)
        );

        // A generic connection failure must not weaken TLS on retry.
        $this->assertFalse(
            bot_curl_is_tls_compatibility_error(CURLE_COULDNT_CONNECT)
        );
    }

    public function testBotCurlExecPreservesTransportFailureMetadata(): void {
        new TestPage();
        $ch = bot_curl_init(1.0, [
            CURLOPT_URL => 'file:///definitely-not-allowed-by-citation-bot',
        ], 1 * 1024 * 1024);

        $this->assertSame('', bot_curl_exec($ch));
        $transfer = bot_curl_last_transfer($ch);

        $this->assertFalse($transfer['ok']);
        $this->assertGreaterThan(0, $transfer['errno']);
        $this->assertNotSame('', $transfer['error']);
    }

    public function testOversizeCurlLogRedactsQueryAndUserInfo(): void {
        $message = bot_curl_size_limit_message(
            1024,
            4096,
            32,
            'src/includes/api/APIdoi.php:123 lookup_doi',
            'https://alice:password@api.example.org/path/10.1234?api_key=secret&doi=10.1234#token'
        );
        $this->assertStringContainsString('limit of 1024 bytes', $message);
        $this->assertStringContainsString('advertised=4096, downloaded=32', $message);
        $this->assertStringContainsString('APIdoi.php:123 lookup_doi', $message);
        $this->assertStringContainsString(
            'https://api.example.org/path/10.1234?api_key=REDACTED&doi=REDACTED',
            $message
        );
        $this->assertStringNotContainsString('password', $message);
        $this->assertStringNotContainsString('secret', $message);
        $this->assertStringNotContainsString('#token', $message);
    }

    public function testOversizeCurlLogHandlesMissingUrlAndLongQuery(): void {
        $this->assertSame('(unavailable)', bot_curl_loggable_url(''));
        $this->assertSame('(non-HTTP URL)', bot_curl_loggable_url('file:///tmp/private'));
        $this->assertSame('(non-HTTP URL)', bot_curl_loggable_url('mailto:user@example.org'));
        $this->assertSame('(non-HTTP URL)', bot_curl_loggable_url('ftp://example.org/private'));
        $this->assertSame('(unavailable)', bot_curl_loggable_url('https:/missing-host'));
        $url = 'https://example.org/' . str_repeat('x', 2000) .
            '?foo=some-value&bar=more';
        $this->assertLessThanOrEqual(1024, mb_strlen(bot_curl_loggable_url($url), '8bit'));
        $this->assertStringNotContainsString('some-value', bot_curl_loggable_url($url));
    }

    public function testCurlLimitRecordsFirstOversizeProgressEvent(): void {
        $ch = bot_curl_init(1.0, [], 1000);
        $this->assertSame(0, curl_limit_page_size($ch, 0, 0, 0, 0));
        // Content-Length alone must not abort a small transfer.
        $this->assertSame(0, curl_limit_page_size($ch, 1001, 1000, 0, 0));
        // Unknown (chunked) totals are bounded by downloaded bytes.
        $this->assertSame(1, curl_limit_page_size($ch, 0, 1001, 0, 0));
        $context = bot_curl_size_limit_log_context()[$ch];
        $this->assertSame(['advertised' => 0, 'downloaded' => 1001], $context['oversize']);
        // Extra progress updates do not overwrite the original size event.
        $this->assertSame(1, curl_limit_page_size($ch, 0, 1500, 0, 0));
        $this->assertSame($context, bot_curl_size_limit_log_context()[$ch]);
    }

    public function testCurlCallerIsAttributedForDirectAndWrappedExec(): void {
        new TestPage();
        $ch = bot_curl_init(1.0, [
            CURLOPT_URL => 'file:///not-allowed-by-citation-bot',
        ], 1024);

        bot_curl_exec_withFalse($ch);
        $direct = bot_curl_size_limit_log_context()[$ch];
        $this->assertStringContainsString('BotCurlTest.php:', $direct['caller']);
        $this->assertStringContainsString(__FUNCTION__, $direct['caller']);

        bot_curl_size_limit_log_context()[$ch] = [
            'caller' => 'old transfer',
            'oversize' => ['advertised' => 1000, 'downloaded' => 2000],
        ];
        bot_curl_exec($ch);
        $wrapped = bot_curl_size_limit_log_context()[$ch];
        $this->assertStringContainsString('BotCurlTest.php:', $wrapped['caller']);
        $this->assertStringContainsString(__FUNCTION__, $wrapped['caller']);
        $this->assertNull($wrapped['oversize']);
    }

    public function testGateAwareProgressCallbackPreservesResponseLimit(): void {
        $ch = bot_curl_init(1.0, [], 1000);
        $this->assertNotFalse($ch);
        $this->assertSame(
            0,
            bot_curl_progress_with_gate_heartbeat($ch, 0, 1000, 0, 0)
        );
        $this->assertSame(
            1,
            bot_curl_progress_with_gate_heartbeat($ch, 0, 1001, 0, 0)
        );
    }
}
