<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/includes/PublicConfig.php';

final class PublicConfigTest extends PHPUnit\Framework\TestCase {
    /** @var array<string, string|false> */
    private array $saved_environment = [];
    private string|false $saved_http_host;

    #[\Override]
    protected function setUp(): void {
        foreach (['PUBLIC_BASE_URL', 'ALLOWED_HOSTS', 'ALLOWED_ORIGINS'] as $name) {
            $this->saved_environment[$name] = getenv($name);
        }
        $this->saved_http_host = is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : false;
    }

    #[\Override]
    protected function tearDown(): void {
        foreach ($this->saved_environment as $name => $value) {
            if ($value === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $value);
            }
        }
        if ($this->saved_http_host === false) {
            unset($_SERVER['HTTP_HOST']);
        } else {
            $_SERVER['HTTP_HOST'] = $this->saved_http_host;
        }
    }

    public function testPublicBaseUrlIsNormalized(): void {
        putenv('PUBLIC_BASE_URL=https://Public.Example:8443/tools/');
        $this->assertSame('https://public.example:8443/tools', public_base_url());
        $this->assertSame('public.example:8443', public_base_host());
        $this->assertSame('https://public.example:8443/tools/authenticate.php', public_url('/authenticate.php'));
        $this->assertSame('/tools/authenticate.php', public_url_path('/authenticate.php'));
    }

    public function testPublicBaseUrlRejectsUnusableValues(): void {
        foreach ([
            '',
            'public.example',
            'javascript://public.example',
            'https://user@public.example',
            'https://public.example/?debug=1',
            'https://public.example/#fragment',
            "https://public.example\r\n.invalid",
        ] as $invalid) {
            putenv('PUBLIC_BASE_URL=' . $invalid);
            try {
                public_base_url();
                $this->fail('Invalid PUBLIC_BASE_URL was accepted: ' . $invalid);
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testHostsAreExactAndIncludePorts(): void {
        putenv('ALLOWED_HOSTS=public.example, localhost:8081');
        $this->assertTrue(request_host_is_allowed('PUBLIC.EXAMPLE'));
        $this->assertTrue(request_host_is_allowed('localhost:8081'));
        $this->assertFalse(request_host_is_allowed('localhost'));
        $this->assertFalse(request_host_is_allowed('public.example.evil.test'));
        $this->assertFalse(request_host_is_allowed('public.example,evil.test'));
        $this->assertFalse(request_host_is_allowed('public.example/path'));
    }

    public function testBaseHostMustAlsoBeAllowed(): void {
        putenv('PUBLIC_BASE_URL=https://public.example');
        putenv('ALLOWED_HOSTS=public.example,preview.example');
        putenv('ALLOWED_ORIGINS=https://public.example');
        $this->assertTrue(public_request_configuration_is_valid('preview.example'));

        putenv('ALLOWED_HOSTS=preview.example');
        $this->assertFalse(public_request_configuration_is_valid('preview.example'));
    }

    public function testCorsOriginsUseExactOrLeftMostWildcardMatches(): void {
        putenv('ALLOWED_ORIGINS=https://public.example,https://mdwiki.org,https://*.wikipedia.org');
        $this->assertSame('https://public.example', allowed_cors_origin('https://PUBLIC.EXAMPLE'));
        $this->assertSame('https://en.wikipedia.org', allowed_cors_origin('https://en.wikipedia.org'));
        $this->assertSame('https://zh-min-nan.wikipedia.org', allowed_cors_origin('https://zh-min-nan.wikipedia.org'));
        $this->assertNull(allowed_cors_origin('https://wikipedia.org'));
        $this->assertNull(allowed_cors_origin('https://en.wikipedia.org.evil.test'));
        $this->assertNull(allowed_cors_origin('http://en.wikipedia.org'));
        $this->assertNull(allowed_cors_origin('https://en.wikipedia.org/path'));
        $this->assertNull(allowed_cors_origin('https://en.wikipedia.org,https://evil.test'));
    }

    public function testOAuthCallbackNeverUsesTheRequestHost(): void {
        putenv('PUBLIC_BASE_URL=https://public.example/tools');
        $_SERVER['HTTP_HOST'] = 'attacker.example';

        $this->assertSame(
            'https://public.example/tools/authenticate.php?return=%2Fprocess_page.php%3Fpage%3DExample',
            oauth_callback_url('/process_page.php?page=Example')
        );
    }

    public function testOAuthCallbackRejectsExternalReturnPath(): void {
        putenv('PUBLIC_BASE_URL=https://public.example');
        $this->expectException(InvalidArgumentException::class);
        oauth_callback_url('//attacker.example/callback');
    }
}
