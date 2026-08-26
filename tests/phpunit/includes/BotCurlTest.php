<?php
declare(strict_types=1);

/*
 * Tests for bot_curl.php
 */

require_once __DIR__ . '/../../testBaseClass.php';

final class BotCurlTest extends testBaseClass {

    public function testCurlLimitPageSizeZeroBytes(): void {
        new TestPage(); // Fill page name with test name for debugging
        $ch = curl_init();
        $this->assertNotFalse($ch);
        $this->assertSame(0, curl_limit_page_size($ch, 0, 0, 0, 0));
    }

    public function testCurlLimitPageSizeSmallPayload(): void {
        $ch = curl_init();
        $this->assertNotFalse($ch);
        $this->assertSame(0, curl_limit_page_size($ch, 0, 1000, 0, 0));
    }

    public function testCurlLimitPageSizeAtExactLimit(): void {
        // Limit is 128 MB = 134217728 bytes; at exactly the limit it should still return 0
        $ch = curl_init();
        $this->assertNotFalse($ch);
        $this->assertSame(0, curl_limit_page_size($ch, 0, 134217728, 0, 0));
    }

    public function testCurlLimitPageSizeOneByteOverLimit(): void {
        $ch = curl_init();
        $this->assertNotFalse($ch);
        $this->assertSame(1, curl_limit_page_size($ch, 0, 134217729, 0, 0));
    }

    public function testCurlLimitPageSizeLargePayload(): void {
        $ch = curl_init();
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

        file_put_contents($filename, 'local curl fixture');

        $ch = bot_curl_init(
            1.0,
            [CURLOPT_URL => 'file://' . $filename]
        );

        try {
            $this->assertSame(
                '',
                bot_curl_exec($ch)
            );

            curl_setopt_array($ch, [
                CURLOPT_PROTOCOLS => CURLPROTO_FILE,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_FILE,
            ]);
            $this->assertSame(
                'local curl fixture',
                bot_curl_exec($ch)
            );
        } finally {
            @unlink($filename);
        }
    }

    public function testCallerCannotReenableFileProtocol(): void {
        new TestPage(); // Fill page name with test name for debugging
        $filename = tempnam(sys_get_temp_dir(), 'citation-bot-curl-');
        $this->assertNotFalse($filename);

        file_put_contents($filename, 'local curl fixture');

        $ch = bot_curl_init(
            1.0,
            [
                CURLOPT_URL => 'file://' . $filename,
                CURLOPT_PROTOCOLS => CURLPROTO_ALL,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_ALL,
            ]
        );

        try {
            $this->assertSame('', bot_curl_exec($ch));
        } finally {
            @unlink($filename);
        }
    }

    public function testPublicCurlDestinationAccepted(): void {
        $ch = curl_init();
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
        $ch = curl_init();
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
}
