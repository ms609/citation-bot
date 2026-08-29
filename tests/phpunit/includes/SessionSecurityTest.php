<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class SessionSecurityTest extends testBaseClass {
    public function testHttpsSessionOptionsAreSecureAndHttpOnly(): void {
        $old = getenv('PUBLIC_BASE_URL');
        try {
            putenv('PUBLIC_BASE_URL=https://citation.example.test');
            $options = public_session_start_options();

            $this->assertTrue($options['use_strict_mode']);
            $this->assertTrue($options['cookie_httponly']);
            $this->assertTrue($options['cookie_secure']);
            $this->assertSame('Lax', $options['cookie_samesite']);
            $this->assertArrayNotHasKey('read_and_close', $options);
        } finally {
            $old === false ? putenv('PUBLIC_BASE_URL') : putenv('PUBLIC_BASE_URL=' . $old);
        }
    }

    public function testReadOnlySessionOptionPreservesCookiePolicy(): void {
        $old = getenv('PUBLIC_BASE_URL');
        try {
            putenv('PUBLIC_BASE_URL=http://localhost');
            $options = public_session_start_options(true);

            $this->assertTrue($options['use_strict_mode']);
            $this->assertTrue($options['cookie_httponly']);
            $this->assertFalse($options['cookie_secure']);
            $this->assertSame('Lax', $options['cookie_samesite']);
            $this->assertTrue($options['read_and_close']);
        } finally {
            $old === false ? putenv('PUBLIC_BASE_URL') : putenv('PUBLIC_BASE_URL=' . $old);
        }
    }
}
