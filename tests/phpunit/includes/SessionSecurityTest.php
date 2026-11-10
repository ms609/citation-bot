<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

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

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLandingPageFooterDisclosesTermsPrivacyAndSessionCookie(): void {
        putenv('PUBLIC_BASE_URL=https://citations.toolforge.org');
        putenv('ALLOWED_HOSTS=citations.toolforge.org');
        putenv('ALLOWED_ORIGINS=https://citations.toolforge.org');
        $_SERVER['HTTP_HOST'] = 'citations.toolforge.org';

        ob_start();
        require dirname(__DIR__, 3) . '/src/index.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString(
            'href="https://wikitech.wikimedia.org/wiki/Wikitech:Cloud_Services_End_User_Terms_of_use"',
            $html
        );
        $this->assertStringContainsString(
            'href="https://foundation.wikimedia.org/wiki/Policy:Privacy_policy"',
            $html
        );
        $this->assertStringContainsString('<code>CiteBot</code>', $html);
    }
}
