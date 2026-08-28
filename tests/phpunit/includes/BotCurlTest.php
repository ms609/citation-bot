<?php
declare(strict_types=1);

/*
 * Tests for bot_curl.php
 */

require_once __DIR__ . '/../../testBaseClass.php';

final class BotCurlTest extends testBaseClass {

    public function testCurlLimitPageSizeZeroBytes(): void {
        new TestPage(); // Fill page name with test name for debugging
        $ch = bot_curl_init(1, []);
        $this->assertNotFalse($ch);
        $this->assertSame(0, curl_limit_page_size($ch, 0, 0, 0, 0));
    }

    public function testCurlLimitPageSizeSmallPayload(): void {
        $ch = bot_curl_init(1, []);
        $this->assertNotFalse($ch);
        $this->assertSame(0, curl_limit_page_size($ch, 0, 1000, 0, 0));
    }

    public function testCurlLimitPageSizeAtExactLimit(): void {
        // Limit is 128 MB = 134217728 bytes; at exactly the limit it should still return 0
        $ch = bot_curl_init(1, []);
        $this->assertNotFalse($ch);
        $this->assertSame(0, curl_limit_page_size($ch, 0, 134217728, 0, 0));
    }

    public function testCurlLimitPageSizeOneByteOverLimit(): void {
        $ch = bot_curl_init(1, []);
        $this->assertNotFalse($ch);
        $this->assertSame(1, curl_limit_page_size($ch, 0, 134217729, 0, 0));
    }

    public function testCurlLimitPageSizeLargePayload(): void {
        $ch = bot_curl_init(1, []);
        $this->assertNotFalse($ch);
        $this->assertSame(1, curl_limit_page_size($ch, 0, 500000000, 0, 0));
    }

    public function testBotCurlInitReturnsCurlHandle(): void {
        $ch = bot_curl_init(1.0, []);
        $this->assertInstanceOf(CurlHandle::class, $ch);
    }

    public function testBotCurlInitWithUrl(): void {
        $ch = bot_curl_init(1.0, [CURLOPT_URL => 'http://example.com']);
        $this->assertInstanceOf(CurlHandle::class, $ch);
    }

    public function testBotCurlInitWithHalfTimeScale(): void {
        $ch = bot_curl_init(0.5, []);
        $this->assertInstanceOf(CurlHandle::class, $ch);
    }

    public function testBotCurlInitWithZeroTimeScale(): void {
        $ch = bot_curl_init(0.0, []);
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

        $ch = bot_curl_init(1.0, [CURLOPT_URL => 'file://' . $filename]);
        $out = bot_curl_exec($ch);
        $this->assertSame('', $out);

        $ch = bot_curl_init(
            1.0,
            [
                CURLOPT_URL => 'file://' . $filename,
                CURLOPT_PROTOCOLS => CURLPROTO_ALL,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_ALL,
            ]
        );
        $out = bot_curl_exec($ch);
        $this->assertSame('', $out);

        $ch = bot_curl_init(1.0, [CURLOPT_URL => 'file://' . $filename]);
        curl_setopt_array($ch, [
            CURLOPT_PROTOCOLS => CURLPROTO_ALL,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_ALL,
        ]);
        $out = bot_curl_exec($ch);
        $this->assertSame('', $out); /** The IP address validation blocks this now */

        @unlink($filename);
    }

    public function testPublicCurlDestinationAccepted(): void {
        $ch = bot_curl_init(1, []);
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
        $ch = bot_curl_init(1, []);
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
        $ch = bot_curl_init(1.0, []);
        $this->assertNotFalse($ch);
        bot_curl_set_max_response_bytes($ch, 1024);

        $this->assertSame(0, curl_limit_page_size($ch, 0, 1024, 0, 0));
        $this->assertSame(1, curl_limit_page_size($ch, 0, 1025, 0, 0));
        $this->assertSame(1024, bot_curl_get_max_response_bytes($ch));
    }

    public function testCurlResponseLimitRejectsInvalidValues(): void {
        $ch = bot_curl_init(1.0, []);
        $this->assertNotFalse($ch);
        $this->expectException(InvalidArgumentException::class);
        bot_curl_set_max_response_bytes($ch, 0);
    }

    public function testMandatoryProtocolsExcludeFtp(): void {
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
        $ch = bot_curl_init(1.0, []);
        $this->assertNotFalse($ch);
        bot_curl_apply_security_options($ch);
        $this->addToAssertionCount(1);
    }

    public function testBotCurlExecPreservesTransportFailureMetadata(): void {
        new TestPage();
        $ch = bot_curl_init(1.0, [
            CURLOPT_URL => 'file:///definitely-not-allowed-by-citation-bot',
        ]);

        $this->assertSame('', bot_curl_exec($ch));
        $transfer = bot_curl_last_transfer($ch);

        $this->assertFalse($transfer['ok']);
        $this->assertGreaterThan(0, $transfer['errno']);
        $this->assertNotSame('', $transfer['error']);
    }
}
