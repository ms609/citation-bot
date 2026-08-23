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
            'https://999.999.999.999',
            'https://public.example:0',
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

    public function testHostNormalizationHandlesIpv6AndRejectsMalformedAuthorities(): void {
        $this->assertSame('public.example:443', normalize_public_host('Public.Example:443'));
        $this->assertSame('[2001:db8::1]', normalize_public_host('[2001:DB8::1]'));
        $this->assertSame('[2001:db8::1]:8443', normalize_public_host('[2001:DB8::1]:8443'));

        foreach ([
            ' public.example',
            'public.example ',
            'public.example/path',
            'public.example:0',
            'public.example:65536',
            'public.example:invalid',
            '999.999.999.999',
            '.public.example',
            'public.example.',
            '-public.example',
            'public-.example',
            'public..example',
            '[2001:db8::invalid]',
            '[127.0.0.1]',
            '[2001:db8::1]:65536',
            str_repeat('a', 64) . '.example',
            str_repeat('a', 254),
        ] as $invalid) {
            $this->assertNull(normalize_public_host($invalid), 'Host should be rejected: ' . $invalid);
        }
    }

    public function testConfiguredHostsAreNormalizedAndDeduplicated(): void {
        putenv('ALLOWED_HOSTS=Public.Example, public.example, [2001:DB8::1]:8443');
        $this->assertSame(
            ['public.example', '[2001:db8::1]:8443'],
            configured_allowed_hosts()
        );
        $this->assertFalse(request_host_is_allowed(null));
        $this->assertFalse(request_host_is_allowed('bad host'));
    }

    public function testConfiguredHostsAreRequiredAndMustBeValid(): void {
        putenv('ALLOWED_HOSTS');
        try {
            configured_allowed_hosts();
            $this->fail('A missing ALLOWED_HOSTS value was accepted');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        putenv('ALLOWED_HOSTS=public.example, bad host');
        $this->expectException(RuntimeException::class);
        configured_allowed_hosts();
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

    public function testCorsOriginNormalizationHandlesPatternsAndRejectsUnsafeValues(): void {
        $this->assertSame('https://public.example:8443', normalize_cors_origin('HTTPS://Public.Example:8443'));
        $this->assertSame('https://*.wikipedia.org:8443', normalize_cors_origin_pattern('HTTPS://*.Wikipedia.ORG:8443'));

        foreach ([
            '',
            ' https://public.example',
            'https://user@public.example',
            'https://public.example/path',
            'https://public.example?debug=1',
            'https://public.example#fragment',
            'https://public.example;https://evil.example',
            'https://*.127.0.0.1',
            'https://*.-wikipedia.org',
        ] as $invalid) {
            $this->assertNull(normalize_cors_origin_pattern($invalid), 'Origin should be rejected: ' . $invalid);
        }
    }

    public function testConfiguredOriginsAreDeduplicatedAndWildcardPortsMustMatch(): void {
        putenv('ALLOWED_ORIGINS=https://Public.Example, https://public.example, https://*.wikipedia.org:8443');
        $this->assertSame(
            ['https://public.example', 'https://*.wikipedia.org:8443'],
            configured_allowed_origins()
        );
        $this->assertSame('https://en.wikipedia.org:8443', allowed_cors_origin('https://en.wikipedia.org:8443'));
        $this->assertNull(allowed_cors_origin('https://en.wikipedia.org'));
        $this->assertNull(allowed_cors_origin('https://wikipedia.org:8443'));
        $this->assertNull(allowed_cors_origin(null));
    }

    public function testConfiguredOriginsAreRequiredAndMustBeValid(): void {
        putenv('ALLOWED_ORIGINS');
        try {
            configured_allowed_origins();
            $this->fail('A missing ALLOWED_ORIGINS value was accepted');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        putenv('ALLOWED_ORIGINS=https://public.example, https://user@invalid.example');
        $this->expectException(RuntimeException::class);
        configured_allowed_origins();
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

    public function testPublicUrlHelpersRejectUnsafePaths(): void {
        putenv('PUBLIC_BASE_URL=https://public.example');
        $this->assertSame('/authenticate.php', public_url_path('/authenticate.php'));

        foreach (['relative', '//attacker.example', "/line\nbreak", '/back\\slash'] as $invalid) {
            foreach ([
                static fn (string $path): string => public_url($path),
                static fn (string $path): string => public_url_path($path),
            ] as $url_builder) {
                try {
                    $url_builder($invalid);
                    $this->fail('An unsafe public path was accepted: ' . $invalid);
                } catch (InvalidArgumentException) {
                    $this->addToAssertionCount(1);
                }
            }
        }
    }

    public function testOauthCallbackWithoutReturnPathUsesConfiguredEndpoint(): void {
        putenv('PUBLIC_BASE_URL=https://public.example/tools/');
        $this->assertSame('https://public.example/tools/authenticate.php', oauth_callback_url(null));
        $this->assertTrue(is_valid_local_return_path('/process_page.php?page=Example'));
        $this->assertFalse(is_valid_local_return_path(''));
        $this->assertFalse(is_valid_local_return_path(' process_page.php'));
        $this->assertFalse(is_valid_local_return_path('//attacker.example'));
        $this->assertFalse(is_valid_local_return_path("/line\nbreak"));
    }

    public function testInvalidPublicRequestConfigurationsReturnFalse(): void {
        putenv('PUBLIC_BASE_URL=https://public.example');
        putenv('ALLOWED_HOSTS=public.example');
        putenv('ALLOWED_ORIGINS');
        $this->assertFalse(public_request_configuration_is_valid('public.example'));

        putenv('ALLOWED_ORIGINS=https://public.example');
        $this->assertFalse(public_request_configuration_is_valid(null));
        $this->assertFalse(public_request_configuration_is_valid('bad host'));
    }
}
