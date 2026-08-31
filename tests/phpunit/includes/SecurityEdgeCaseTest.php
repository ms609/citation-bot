<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/includes/PublicConfig.php';
require_once __DIR__ . '/../../../src/includes/request_security.php';
require_once __DIR__ . '/../../../src/includes/big_jobs.php';

final class SecurityEdgeCaseTest extends PHPUnit\Framework\TestCase {
    /** @var array<string, string|false> */
    private array $saved_environment = [];
    private bool $session_was_set;
    private string $saved_session;

    #[\Override]
    protected function setUp(): void {
        foreach (['PUBLIC_BASE_URL', 'ALLOWED_HOSTS', 'ALLOWED_ORIGINS'] as $name) {
            $this->saved_environment[$name] = getenv($name);
        }
        $this->session_was_set = isset($_SESSION);
        if ($this->session_was_set) {
            $this->saved_session = serialize($_SESSION);
        }

        putenv('PUBLIC_BASE_URL=https://public.example/tools');
        putenv('ALLOWED_HOSTS=public.example');
        putenv('ALLOWED_ORIGINS=https://public.example,https://*.example.org');
        $_SESSION = [];
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

        if ($this->session_was_set) {
            $_SESSION = unserialize($this->saved_session);
        } else {
            unset($_SESSION);
        }
    }

    public function testHostNormalizationRejectsHeaderAndAuthorityConfusion(): void {
        foreach ([
            "public.example\r\nX-Injected: yes",
            "public.example\n",
            "public.example\t",
            "public.example\x00evil.example",
            "public.example\x1fevil.example",
            "public.example\x7fevil.example",
            'user@public.example',
            'public.example,evil.example',
            'public.example#evil.example',
            'public.example?next=evil.example',
            'public.example\\evil.example',
        ] as $host) {
            $this->assertNull(normalize_public_host($host), 'Host should be rejected: ' . bin2hex($host));
        }
    }

    public function testHostNormalizationRejectsUnicodeAndIpv6ZoneIdentifiers(): void {
        foreach ([
            'paypał.example',
            'раypal.example',
            "public\u{FF0E}example",
            '[fe80::1%25eth0]',
            '[fe80::1%eth0]',
        ] as $host) {
            $this->assertNull(normalize_public_host($host), 'Ambiguous host should be rejected: ' . $host);
        }
    }

    public function testHostPortSecurityBoundariesAreExact(): void {
        $this->assertSame('public.example:1', normalize_public_host('public.example:1'));
        $this->assertSame('public.example:65535', normalize_public_host('public.example:65535'));
        $this->assertNull(normalize_public_host('public.example:0'));
        $this->assertNull(normalize_public_host('public.example:65536'));
    }

    public function testRequestHostAllowlistRejectsHeaderInjectionAndSuffixConfusion(): void {
        putenv('ALLOWED_HOSTS=public.example');

        foreach ([
            'public.example.evil.test',
            'evilpublic.example',
            "public.example\r\nHost: evil.test",
            'public.example@evil.test',
            'public.example:443',
        ] as $host) {
            $this->assertFalse(request_host_is_allowed($host), 'Request host should be rejected: ' . $host);
        }
    }

    public function testConfiguredHostAllowlistFailsClosedOnEmptyEntries(): void {
        foreach (['public.example,', ',public.example', 'public.example,,preview.example'] as $configured) {
            putenv('ALLOWED_HOSTS=' . $configured);
            try {
                configured_allowed_hosts();
                $this->fail('Malformed ALLOWED_HOSTS was accepted: ' . $configured);
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testCorsOriginRejectsOpaqueNetworkAndHeaderInjectionForms(): void {
        foreach ([
            'null',
            '//evil.example',
            'file://public.example',
            'data:text/html,hello',
            'https://public.example/',
            "https://public.example\r\nAccess-Control-Allow-Origin: https://evil.example",
            "https://public.example\x00.evil.example",
            'https://public.example@evil.example',
        ] as $origin) {
            $this->assertNull(normalize_cors_origin($origin), 'Origin should be rejected: ' . bin2hex($origin));
        }
    }

    public function testCorsWildcardRequiresRealSubdomainMatchingSchemeAndPort(): void {
        putenv('ALLOWED_ORIGINS=https://*.example.org:8443');

        $this->assertSame(
            'https://a.b.example.org:8443',
            allowed_cors_origin('https://a.b.example.org:8443')
        );
        foreach ([
            'https://example.org:8443',
            'https://evilexample.org:8443',
            'https://example.org.evil.test:8443',
            'http://a.example.org:8443',
            'https://a.example.org',
            'https://a.example.org:443',
        ] as $origin) {
            $this->assertNull(allowed_cors_origin($origin), 'Wildcard origin should not match: ' . $origin);
        }
    }

    public function testExactCorsOriginDoesNotAliasExplicitDefaultPort(): void {
        putenv('ALLOWED_ORIGINS=https://public.example');

        $this->assertSame('https://public.example', allowed_cors_origin('https://PUBLIC.EXAMPLE'));
        $this->assertNull(allowed_cors_origin('https://public.example:443'));
    }

    public function testConfiguredOriginAllowlistFailsClosedOnEmptyEntries(): void {
        foreach ([
            'https://public.example,',
            ',https://public.example',
            'https://public.example,,https://preview.example',
        ] as $configured) {
            putenv('ALLOWED_ORIGINS=' . $configured);
            try {
                configured_allowed_origins();
                $this->fail('Malformed ALLOWED_ORIGINS was accepted: ' . $configured);
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testPublicUrlHelpersRejectEveryRawControlCharacterClass(): void {
        foreach (["\x00", "\x09", "\x0a", "\x0d", "\x1f", "\x20", "\x7f"] as $control) {
            $path = '/safe' . $control . 'unsafe';
            foreach ([
                static fn (string $value): string => public_url($value),
                static fn (string $value): string => public_url_path($value),
            ] as $builder) {
                try {
                    $builder($path);
                    $this->fail('Control character was accepted in public path: ' . bin2hex($control));
                } catch (InvalidArgumentException) {
                    $this->addToAssertionCount(1);
                }
            }
        }
    }

    public function testOAuthReturnPathRejectsHeaderAndNetworkPathTricks(): void {
        foreach ([
            '//evil.example/callback',
            '///evil.example/callback',
            '/\\evil.example/callback',
            "/safe\r\nLocation: https://evil.example",
            "/safe\tvalue",
            '/safe path',
            'https://evil.example/callback',
        ] as $return_path) {
            $this->assertFalse(is_valid_local_return_path($return_path));
            try {
                oauth_authentication_url($return_path);
                $this->fail('Unsafe OAuth return path was accepted: ' . bin2hex($return_path));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testOAuthReturnPathQueryCannotEscapeOuterAuthenticationQuery(): void {
        $return_path = '/process_page.php?page=Example&return=https://evil.example/#fragment';
        $url = oauth_authentication_url($return_path);

        $this->assertStringStartsWith('https://public.example/tools/authenticate.php?', $url);
        $query = parse_url($url, PHP_URL_QUERY);
        $this->assertIsString($query);
        parse_str($query, $params);
        $this->assertSame(['return' => $return_path], $params);
        $this->assertSame('public.example', parse_url($url, PHP_URL_HOST));
    }

    public function testHttpsSessionOptionsKeepDefensiveCookieSettingsEnabled(): void {
        putenv('PUBLIC_BASE_URL=https://public.example/tools');

        $this->assertSame([
            'use_strict_mode' => true,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => true,
        ], public_session_start_options());
        $this->assertSame([
            'use_strict_mode' => true,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => true,
            'read_and_close' => true,
        ], public_session_start_options(true));
    }

    public function testHttpSessionOptionsDisableOnlySecureCookieFlag(): void {
        putenv('PUBLIC_BASE_URL=http://public.example/tools');

        $options = public_session_start_options();
        $this->assertTrue($options['use_strict_mode']);
        $this->assertTrue($options['cookie_httponly']);
        $this->assertSame('Lax', $options['cookie_samesite']);
        $this->assertFalse($options['cookie_secure']);
    }

    public function testCsrfComparisonIsExactCaseSensitiveAndBinarySafe(): void {
        $server = ['REQUEST_METHOD' => 'POST'];
        $session = ['csrf_token' => 'AbCd0123-token'];

        $this->assertTrue(request_has_valid_post_csrf($server, ['csrf_token' => 'AbCd0123-token'], $session));
        foreach ([
            'abcd0123-token',
            'AbCd0123-TOKEN',
            ' AbCd0123-token',
            'AbCd0123-token ',
            'AbCd0123-token-extra',
            "AbCd0123-token\x00",
            '',
        ] as $posted_token) {
            $this->assertFalse(
                request_has_valid_post_csrf($server, ['csrf_token' => $posted_token], $session),
                'Altered CSRF token should be rejected: ' . bin2hex($posted_token)
            );
        }
    }

    public function testCsrfValidationFailsClosedOnMethodVariants(): void {
        $post = ['csrf_token' => 'token'];
        $session = ['csrf_token' => 'token'];

        foreach (['', 'GET', 'PUT', 'PATCH', 'DELETE', 'post', 'POST '] as $method) {
            $this->assertFalse(
                request_has_valid_post_csrf(['REQUEST_METHOD' => $method], $post, $session),
                'Method should not be treated as POST: ' . bin2hex($method)
            );
        }
    }

    public function testConfirmationFieldBuildersStripSecuritySensitiveAndUnknownInput(): void {
        $query = [
            'edit' => 'toolbar',
            'wiki_base' => 'en',
            'pcre' => '1',
            'slow' => '0',
            'csrf_token' => 'attacker-controlled',
            'return' => '//evil.example',
            'redirect' => 'https://evil.example',
            'extended_limit' => '999999',
            'PHP_ADSABSAPIKEY' => 'secret',
            'unexpected' => '<script>alert(1)</script>',
        ];

        $this->assertSame([
            'page' => 'Example',
            'edit' => 'toolbar',
            'wiki_base' => 'en',
            'pcre' => '1',
            'slow' => '1',
        ], process_page_confirmation_fields('Example', $query));

        $this->assertSame([
            'cat' => 'Maintenance',
            'extended_limit' => '1',
            'edit' => 'toolbar',
            'wiki_base' => 'en',
            'pcre' => '1',
            'slow' => '1',
        ], category_confirmation_fields('Maintenance', $query));
    }

    public function testCategoryConfirmationCannotOverrideServerControlledExtendedLimit(): void {
        foreach (['0', '-1', '999999999', 'attacker'] as $value) {
            $fields = category_confirmation_fields('Maintenance', ['extended_limit' => $value]);
            $this->assertSame('1', $fields['extended_limit']);
        }
    }

    public function testPublicRequestConfigurationFailsClosedOnMalformedSecurityConfig(): void {
        putenv('PUBLIC_BASE_URL=https://public.example');
        putenv('ALLOWED_HOSTS=public.example');
        putenv('ALLOWED_ORIGINS=https://public.example');
        $this->assertTrue(public_request_configuration_is_valid('public.example'));

        putenv('ALLOWED_HOSTS=public.example,');
        $this->assertFalse(public_request_configuration_is_valid('public.example'));

        putenv('ALLOWED_HOSTS=public.example');
        putenv('ALLOWED_ORIGINS=https://public.example,');
        $this->assertFalse(public_request_configuration_is_valid('public.example'));

        putenv('ALLOWED_ORIGINS=https://public.example');
        $this->assertFalse(public_request_configuration_is_valid("public.example\r\nHost: evil.example"));
    }

    public function testBigJobLockNameCannotEscapeSharedMemoryDirectory(): void {
        $_SESSION['citation_bot_user_id'] = "../../../../etc/passwd\x00\xff\r\n";
        $path = big_jobs_name();

        $this->assertSame('/dev/shm', dirname($path));
        $this->assertMatchesRegularExpression('~\A[A-Za-z0-9+_]*_1\z~D', basename($path));
        $this->assertStringNotContainsString('..', basename($path));
        $this->assertStringNotContainsString('/', basename($path));
        $this->assertStringNotContainsString('\\', basename($path));
        $this->assertStringNotContainsString("\x00", basename($path));
    }
}
