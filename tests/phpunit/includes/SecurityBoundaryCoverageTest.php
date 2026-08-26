<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../src/includes/PublicConfig.php';
require_once __DIR__ . '/../../../src/includes/request_security.php';

final class SecurityBoundaryCoverageTest extends PHPUnit\Framework\TestCase {
    private string|false $saved_public_base_url;
    private string|false $saved_allowed_origins;

    #[\Override]
    protected function setUp(): void {
        $this->saved_public_base_url = getenv('PUBLIC_BASE_URL');
        $this->saved_allowed_origins = getenv('ALLOWED_ORIGINS');
    }

    #[\Override]
    protected function tearDown(): void {
        if ($this->saved_public_base_url === false) {
            putenv('PUBLIC_BASE_URL');
        } else {
            putenv('PUBLIC_BASE_URL=' . $this->saved_public_base_url);
        }

        if ($this->saved_allowed_origins === false) {
            putenv('ALLOWED_ORIGINS');
        } else {
            putenv('ALLOWED_ORIGINS=' . $this->saved_allowed_origins);
        }
    }

    public function testPublicBaseUrlRejectsMissingEnvironment(): void {
        putenv('PUBLIC_BASE_URL');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PUBLIC_BASE_URL is missing or invalid');
        public_base_url();
    }

    public function testHostNormalizationAcceptsValidIpv4WithPort(): void {
        $this->assertSame('192.0.2.10:443', normalize_public_host('192.0.2.10:443'));
    }

    public function testCorsHeaderHelperTraversesMissingRejectedAndAllowedOrigins(): void {
        putenv('ALLOWED_ORIGINS=https://public.example,https://*.wikipedia.org');

        send_configured_cors_header(null);
        send_configured_cors_header('not-an-origin');

        $this->assertNull(allowed_cors_origin('https://evil.example'));
        send_configured_cors_header('https://evil.example');

        $this->assertSame(
            'https://en.wikipedia.org',
            allowed_cors_origin('https://en.wikipedia.org')
        );
        send_configured_cors_header('https://en.wikipedia.org');
    }

    public function testCsrfTokenInitializationHandlesNullAndInvalidStoredValues(): void {
        $session = null;
        $token = ensure_session_csrf_token($session);

        $this->assertIsArray($session);
        $this->assertMatchesRegularExpression('~^[a-f0-9]{64}$~D', $token);
        $this->assertSame($token, $session['csrf_token']);

        foreach ([null, '', 0, []] as $invalid_token) {
            $session = ['csrf_token' => $invalid_token];
            $token = ensure_session_csrf_token($session);

            $this->assertMatchesRegularExpression('~^[a-f0-9]{64}$~D', $token);
            $this->assertSame($token, $session['csrf_token']);
        }
    }

    public function testCsrfValidationRejectsMissingAndNonStringTokens(): void {
        $this->assertFalse(request_has_valid_post_csrf(
            [],
            ['csrf_token' => 'token'],
            ['csrf_token' => 'token']
        ));
        $this->assertFalse(request_has_valid_post_csrf(
            ['REQUEST_METHOD' => 'POST'],
            ['csrf_token' => ['token']],
            ['csrf_token' => 'token']
        ));
        $this->assertFalse(request_has_valid_post_csrf(
            ['REQUEST_METHOD' => 'POST'],
            ['csrf_token' => 'token'],
            ['csrf_token' => 123]
        ));
        $this->assertFalse(request_has_valid_post_csrf(
            ['REQUEST_METHOD' => 'POST'],
            ['csrf_token' => 'token'],
            ['csrf_token' => '']
        ));
    }

    public function testConfirmationFieldBuildersFilterNonStringOptions(): void {
        $this->assertSame(
            ['page' => 'Example', 'slow' => '1'],
            process_page_confirmation_fields('Example', [
                'edit' => ['toolbar'],
                'wiki_base' => false,
                'pcre' => null,
                'slow' => false,
            ])
        );

        $this->assertSame(
            ['cat' => 'Maintenance', 'extended_limit' => '1'],
            category_confirmation_fields('Maintenance', [
                'wiki_base' => 123,
                'pcre' => null,
                'slow' => null,
            ])
        );
    }

    public function testConfirmationFormEscapesUnknownFieldsAndOmitsEmptySummary(): void {
        $form = post_confirmation_form(
            'process_page.php?x="&y=<',
            [
                'extended_limit' => '1',
                'weird"name' => '<value>&',
            ],
            'token"&<',
            '<Continue & go>'
        );

        $this->assertStringContainsString(
            'action="process_page.php?x=&quot;&amp;y=&lt;"',
            $form
        );
        $this->assertStringContainsString('name="weird&quot;name"', $form);
        $this->assertStringContainsString('value="&lt;value&gt;&amp;"', $form);
        $this->assertStringContainsString('value="token&quot;&amp;&lt;"', $form);
        $this->assertStringContainsString('&lt;Continue &amp; go&gt;', $form);
        $this->assertStringNotContainsString('<dl class="request-summary">', $form);
    }
}
