<?php
declare(strict_types=1);

/*
 * Tests for bot_curl.php
 */

require_once __DIR__ . '/../../testBaseClass.php';

final class BotCurlTest extends testBaseClass {

    // ======================== curl_limit_page_size() ========================

    public function testCurlLimitPageSizeZeroBytes(): void {
        new TestPage();
        $ch = curl_init();
        $this->assertNotFalse($ch);
        $this->assertSame(0, curl_limit_page_size($ch, 0, 0, 0, 0));
        curl_close($ch);
    }

    public function testCurlLimitPageSizeSmallPayload(): void {
        new TestPage();
        $ch = curl_init();
        $this->assertNotFalse($ch);
        $this->assertSame(0, curl_limit_page_size($ch, 0, 1000, 0, 0));
        curl_close($ch);
    }

    public function testCurlLimitPageSizeAtExactLimit(): void {
        new TestPage();
        // Limit is 128 MB = 134217728 bytes; at exactly the limit it should still return 0
        $ch = curl_init();
        $this->assertNotFalse($ch);
        $this->assertSame(0, curl_limit_page_size($ch, 0, 134217728, 0, 0));
        curl_close($ch);
    }

    public function testCurlLimitPageSizeOneByteOverLimit(): void {
        new TestPage();
        $ch = curl_init();
        $this->assertNotFalse($ch);
        $this->assertSame(1, curl_limit_page_size($ch, 0, 134217729, 0, 0));
        curl_close($ch);
    }

    public function testCurlLimitPageSizeLargePayload(): void {
        new TestPage();
        $ch = curl_init();
        $this->assertNotFalse($ch);
        $this->assertSame(1, curl_limit_page_size($ch, 0, 500000000, 0, 0));
        curl_close($ch);
    }

    // ======================== bot_curl_init() ========================

    public function testBotCurlInitReturnsCurlHandle(): void {
        new TestPage();
        $ch = bot_curl_init(1.0, []);
        $this->assertInstanceOf(CurlHandle::class, $ch);
        curl_close($ch);
    }

    public function testBotCurlInitWithUrl(): void {
        new TestPage();
        $ch = bot_curl_init(1.0, [CURLOPT_URL => 'http://example.com']);
        $this->assertInstanceOf(CurlHandle::class, $ch);
        curl_close($ch);
    }

    public function testBotCurlInitWithHalfTimeScale(): void {
        new TestPage();
        $ch = bot_curl_init(0.5, []);
        $this->assertInstanceOf(CurlHandle::class, $ch);
        curl_close($ch);
    }

    public function testBotCurlInitWithZeroTimeScale(): void {
        new TestPage();
        $ch = bot_curl_init(0.0, []);
        $this->assertInstanceOf(CurlHandle::class, $ch);
        curl_close($ch);
    }
}
