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
}
