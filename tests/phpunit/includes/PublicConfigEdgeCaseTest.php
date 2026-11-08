<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 3) . '/src/includes/PublicConfig.php';

/**
 * Network-free edge cases for the public URL, host, origin and redirect
 * canonicalization helpers.
 */
final class PublicConfigEdgeCaseTest extends PHPUnit\Framework\TestCase {
    /** @var array<string, string|false> */
    private array $saved_environment = [];

    #[\Override]
    protected function setUp(): void {
        foreach (['PUBLIC_BASE_URL', 'ALLOWED_HOSTS', 'ALLOWED_ORIGINS'] as $name) {
            $this->saved_environment[$name] = getenv($name);
        }
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
    }

    // ---- normalize_public_host -------------------------------------------------

    /** @return array<string, array{string, string|null}> */
    public static function hostProvider(): array {
        $label63 = str_repeat('a', 63);
        return [
            'lowercased' => ['Example.ORG', 'example.org'],
            'port kept' => ['example.org:8080', 'example.org:8080'],
            'lowest port' => ['example.org:1', 'example.org:1'],
            'highest port' => ['example.org:65535', 'example.org:65535'],
            'port zero' => ['example.org:0', null],
            'port too large' => ['example.org:65536', null],
            'five digit port overflow' => ['example.org:99999', null],
            'six digit port' => ['example.org:123456', null],
            'empty port' => ['example.org:', null],
            'ipv4' => ['1.2.3.4', '1.2.3.4'],
            'ipv4 with port' => ['1.2.3.4:80', '1.2.3.4:80'],
            'ipv4 octet too large' => ['999.1.1.1', null],
            'ipv4 too few octets' => ['1.2.3', null],
            'ipv6' => ['[::1]', '[::1]'],
            'ipv6 lowercased with port' => ['[::FFFF:1.2.3.4]:8443', '[::ffff:1.2.3.4]:8443'],
            'ipv6 missing bracket' => ['[::1', null],
            'ipv6 not hex' => ['[zz::1]', null],
            'ipv4 inside brackets' => ['[1.2.3.4]', null],
            'ipv6 port zero' => ['[::1]:0', null],
            'leading dot' => ['.example.org', null],
            'trailing dot' => ['example.org.', null],
            'empty label' => ['a..example.org', null],
            'label starts with hyphen' => ['-bad.example', null],
            'label ends with hyphen' => ['bad-.example', null],
            'underscore' => ['bad_host.example', null],
            'label of 63 characters' => [$label63 . '.example', $label63 . '.example'],
            'label of 64 characters' => [$label63 . 'a.example', null],
            'exactly 253 characters' => [$label63 . '.' . $label63 . '.' . $label63 . '.' . str_repeat('a', 61), $label63 . '.' . $label63 . '.' . $label63 . '.' . str_repeat('a', 61)],
            'over 253 characters' => [$label63 . '.' . $label63 . '.' . $label63 . '.' . str_repeat('a', 63), null],
            'empty' => ['', null],
            'leading space' => [' example.org', null],
            'trailing newline' => ["example.org\n", null],
            'inner space' => ['exa mple.org', null],
            'path' => ['example.org/path', null],
            'userinfo' => ['user@example.org', null],
            'query' => ['example.org?x', null],
            'fragment' => ['example.org#x', null],
            'comma list' => ['a.example,b.example', null],
            'backslash' => ['example.org\\evil', null],
            'delete control' => ["example\x7f.org", null],
            'non ascii' => ["b\u{00fc}cher.example", null],
        ];
    }

    #[DataProvider('hostProvider')]
    public function testNormalizePublicHost(string $input, ?string $expected): void {
        $this->assertSame($expected, normalize_public_host($input));
    }

    // ---- normalize_cors_origin / pattern ---------------------------------------

    /** @return array<string, array{string, string|null}> */
    public static function originProvider(): array {
        return [
            'lowercased' => ['HTTPS://Example.ORG', 'https://example.org'],
            'port kept' => ['http://example.org:8080', 'http://example.org:8080'],
            'ipv6 origin' => ['https://[::1]:8443', 'https://[::1]:8443'],
            'empty' => ['', null],
            'leading space' => [' https://example.org', null],
            'trailing slash is a path' => ['https://example.org/', null],
            'path' => ['https://example.org/app', null],
            'userinfo' => ['https://user@example.org', null],
            'password' => ['https://user:pw@example.org', null],
            'query' => ['https://example.org?x=1', null],
            'fragment' => ['https://example.org#frag', null],
            'unsupported scheme' => ['ftp://example.org', null],
            'no scheme' => ['example.org', null],
            'scheme relative' => ['//example.org', null],
            'invalid host character' => ['https://bad_host.example', null],
            'invalid label' => ['https://-bad.example', null],
            'port out of range' => ['https://example.org:99999', null],
            'port zero' => ['https://example.org:0', null],
            'comma separated pair' => ['https://example.org,https://evil.example', null],
            'semicolon' => ['https://example.org;evil', null],
            'backslash' => ['https://example.org\\evil', null],
            'space inside' => ['https://exa mple.org', null],
        ];
    }

    #[DataProvider('originProvider')]
    public function testNormalizeCorsOrigin(string $input, ?string $expected): void {
        $this->assertSame($expected, normalize_cors_origin($input));
    }

    /** @return array<string, array{string, string|null}> */
    public static function originPatternProvider(): array {
        return [
            'wildcard' => ['https://*.example.org', 'https://*.example.org'],
            'wildcard normalized' => ['HTTP://*.Example.ORG:8080', 'http://*.example.org:8080'],
            'plain origin passes through' => ['https://Example.org', 'https://example.org'],
            'wildcard ipv4 rejected' => ['https://*.127.0.0.1', null],
            'wildcard with path rejected' => ['https://*.example.org/path', null],
            'wildcard without host rejected' => ['https://*.', null],
            'wildcard with bad label rejected' => ['https://*.-bad.example', null],
            'wildcard with port zero rejected' => ['https://*.example.org:0', null],
            'bare wildcard rejected' => ['https://*', null],
            'wildcard in the middle rejected' => ['https://a.*.example.org', null],
            'garbage' => ['not an origin', null],
        ];
    }

    #[DataProvider('originPatternProvider')]
    public function testNormalizeCorsOriginPattern(string $input, ?string $expected): void {
        $this->assertSame($expected, normalize_cors_origin_pattern($input));
    }

    // ---- configured allowlists -------------------------------------------------

    public function testConfiguredAllowedHostsAreNormalizedAndDeduplicated(): void {
        putenv('ALLOWED_HOSTS=A.example, b.example:8080 ,a.EXAMPLE');
        $this->assertSame(['a.example', 'b.example:8080'], configured_allowed_hosts());
    }

    public function testConfiguredAllowedHostsRejectMissingBlankAndInvalidValues(): void {
        putenv('ALLOWED_HOSTS');
        $this->assertThrowsRuntime(static fn (): array => configured_allowed_hosts(), 'ALLOWED_HOSTS is missing');
        putenv('ALLOWED_HOSTS=');
        $this->assertThrowsRuntime(static fn (): array => configured_allowed_hosts(), 'ALLOWED_HOSTS is missing');
        putenv('ALLOWED_HOSTS=   ');
        $this->assertThrowsRuntime(static fn (): array => configured_allowed_hosts(), 'ALLOWED_HOSTS is missing');
        putenv('ALLOWED_HOSTS=good.example,bad_host');
        $this->assertThrowsRuntime(static fn (): array => configured_allowed_hosts(), 'ALLOWED_HOSTS contains an invalid host');
        putenv('ALLOWED_HOSTS=good.example,,other.example');
        $this->assertThrowsRuntime(static fn (): array => configured_allowed_hosts(), 'ALLOWED_HOSTS contains an invalid host');
    }

    public function testConfiguredAllowedOriginsAreNormalizedAndDeduplicated(): void {
        putenv('ALLOWED_ORIGINS=https://a.example, https://*.b.example , HTTPS://A.example');
        $this->assertSame(['https://a.example', 'https://*.b.example'], configured_allowed_origins());
    }

    public function testConfiguredAllowedOriginsRejectMissingBlankAndInvalidValues(): void {
        putenv('ALLOWED_ORIGINS');
        $this->assertThrowsRuntime(static fn (): array => configured_allowed_origins(), 'ALLOWED_ORIGINS is missing');
        putenv('ALLOWED_ORIGINS=');
        $this->assertThrowsRuntime(static fn (): array => configured_allowed_origins(), 'ALLOWED_ORIGINS is missing');
        putenv('ALLOWED_ORIGINS=https://a.example,not a url');
        $this->assertThrowsRuntime(static fn (): array => configured_allowed_origins(), 'ALLOWED_ORIGINS contains an invalid origin');
        putenv('ALLOWED_ORIGINS=https://a.example/with-path');
        $this->assertThrowsRuntime(static fn (): array => configured_allowed_origins(), 'ALLOWED_ORIGINS contains an invalid origin');
    }

    /** @param callable(): mixed $call */
    private function assertThrowsRuntime(callable $call, string $message): void {
        try {
            $call();
            $this->fail('Expected a RuntimeException: ' . $message);
        } catch (RuntimeException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }

    public function testRequestHostAllowance(): void {
        putenv('ALLOWED_HOSTS=a.example,b.example:8080');
        $this->assertTrue(request_host_is_allowed('a.example'));
        $this->assertTrue(request_host_is_allowed('A.Example'));
        $this->assertTrue(request_host_is_allowed('b.example:8080'));
        $this->assertFalse(request_host_is_allowed('a.example:8080'), 'Ports are significant');
        $this->assertFalse(request_host_is_allowed('b.example'), 'Ports are significant');
        $this->assertFalse(request_host_is_allowed('evil.example'));
        $this->assertFalse(request_host_is_allowed('a.example/evil'));
        $this->assertFalse(request_host_is_allowed(''));
        $this->assertFalse(request_host_is_allowed(null));
    }

    // ---- allowed_cors_origin ---------------------------------------------------

    /** @return array<string, array{string|null, string|null}> */
    public static function corsDecisionProvider(): array {
        return [
            'null origin' => [null, null],
            'invalid origin' => ['https://bad_host.example', null],
            'origin with path' => ['https://sub.wild.example/path', null],
            'wildcard subdomain' => ['https://sub.wild.example', 'https://sub.wild.example'],
            'wildcard deep subdomain' => ['https://a.b.wild.example', 'https://a.b.wild.example'],
            'wildcard case folded' => ['HTTPS://SUB.WILD.EXAMPLE', 'https://sub.wild.example'],
            'wildcard does not match apex' => ['https://wild.example', null],
            'wildcard needs a dot boundary' => ['https://evilwild.example', null],
            'wildcard suffix lookalike' => ['https://sub.wild.example.evil.test', null],
            'wildcard scheme mismatch' => ['http://sub.wild.example', null],
            'wildcard port mismatch' => ['https://sub.wild.example:8443', null],
            'wildcard with port matches' => ['https://sub.port.example:8443', 'https://sub.port.example:8443'],
            'wildcard with port needs port' => ['https://sub.port.example', null],
            'exact origin' => ['http://exact.example:8080', 'http://exact.example:8080'],
            'exact origin needs port' => ['http://exact.example', null],
            'exact origin scheme' => ['https://exact.example:8080', null],
            'unlisted' => ['https://evil.example', null],
        ];
    }

    #[DataProvider('corsDecisionProvider')]
    public function testAllowedCorsOrigin(?string $origin, ?string $expected): void {
        putenv('ALLOWED_ORIGINS=https://*.wild.example,http://exact.example:8080,https://*.port.example:8443');
        $this->assertSame($expected, allowed_cors_origin($origin));
    }

    public function testSendConfiguredCorsHeaderIgnoresInvalidAndNullOrigins(): void {
        putenv('ALLOWED_ORIGINS=https://allowed.example');
        // None of these may raise or throw, whatever the SAPI does with headers.
        send_configured_cors_header(null);
        send_configured_cors_header('not an origin');
        send_configured_cors_header('https://evil.example');
        send_configured_cors_header('https://allowed.example');
        $this->addToAssertionCount(1);
    }

    // ---- public base URL -------------------------------------------------------

    /** @return array<string, array{string}> */
    public static function badBaseUrlProvider(): array {
        return [
            'leading space' => [' https://example.org'],
            'trailing space' => ['https://example.org '],
            'inner space' => ['https://example.org/a b'],
            'backslash' => ['https://example.org\\x'],
            'no scheme' => ['example.org'],
            'scheme relative' => ['//example.org'],
            'unsupported scheme' => ['ftp://example.org'],
            'userinfo' => ['https://user@example.org'],
            'password' => ['https://user:pw@example.org'],
            'query' => ['https://example.org?x=1'],
            'fragment' => ['https://example.org#top'],
            'invalid host' => ['https://bad_host.example'],
            'no host' => ['https:///path'],
            'port zero' => ['https://example.org:0'],
        ];
    }

    #[DataProvider('badBaseUrlProvider')]
    public function testPublicBaseUrlRejectsBadValues(string $value): void {
        putenv('PUBLIC_BASE_URL=' . $value);
        $this->expectException(RuntimeException::class);
        public_base_url();
    }

    public function testPublicBaseUrlRejectsMissingAndEmptyValues(): void {
        putenv('PUBLIC_BASE_URL');
        $this->assertThrowsRuntime(static fn (): string => public_base_url(), 'PUBLIC_BASE_URL is missing or invalid');
        putenv('PUBLIC_BASE_URL=');
        $this->assertThrowsRuntime(static fn (): string => public_base_url(), 'PUBLIC_BASE_URL is missing or invalid');
    }

    public function testPublicBaseUrlAcceptsIpv6AndTrimsTrailingSlashes(): void {
        putenv('PUBLIC_BASE_URL=https://[::1]:8443/app//');
        $this->assertSame('https://[::1]:8443/app', public_base_url());
        $this->assertSame('[::1]:8443', public_base_host());
        putenv('PUBLIC_BASE_URL=http://localhost:8081');
        $this->assertSame('http://localhost:8081', public_base_url());
        $this->assertSame('localhost:8081', public_base_host());
    }

    /** @return array<string, array{string}> */
    public static function badLocalPathProvider(): array {
        return [
            'relative' => ['relative/path'],
            'scheme relative' => ['//evil.example/x'],
            'space' => ['/with space'],
            'newline' => ["/with\nnewline"],
            'backslash' => ['/with\\backslash'],
            'delete character' => ["/with\x7fdelete"],
            'empty' => [''],
        ];
    }

    #[DataProvider('badLocalPathProvider')]
    public function testPublicUrlAndPathRejectNonLocalPaths(string $path): void {
        putenv('PUBLIC_BASE_URL=https://public.example/tools');
        try {
            public_url($path);
            $this->fail('public_url() accepted ' . $path);
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        try {
            public_url_path($path);
            $this->fail('public_url_path() accepted ' . $path);
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testPublicUrlKeepsQueryStringsAndBasePath(): void {
        putenv('PUBLIC_BASE_URL=https://public.example/tools');
        $this->assertSame('https://public.example/tools/x?y=1#z', public_url('/x?y=1#z'));
        $this->assertSame('/tools/x?y=1#z', public_url_path('/x?y=1#z'));
        putenv('PUBLIC_BASE_URL=https://public.example');
        $this->assertSame('/x', public_url_path('/x'));
    }

    // ---- redirects and OAuth URLs ----------------------------------------------

    /** @return array<string, array{string, bool}> */
    public static function redirectProvider(): array {
        $oauth = 'https://meta.wikimedia.org/w/index.php?title=Special:OAuth/authorize&oauth_token=';
        return [
            'local path' => ['/local/page?x=1', true],
            'scheme relative' => ['//evil.example', false],
            'leading space' => [' /local', false],
            'trailing newline' => ["/local\n", false],
            'backslash' => ['/local\\x', false],
            'empty' => ['', false],
            'under the base url' => ['https://public.example/tools/page', true],
            'base url itself' => ['https://public.example/tools', false],
            'base url lookalike' => ['https://public.example/toolsX/page', false],
            'other host' => ['https://evil.example/tools/page', false],
            'base url with trailing space' => ['https://public.example/tools/page ', false],
            'base url with control character' => ["https://public.example/tools/pa\x01ge", false],
            'oauth authorize' => [$oauth . 'abc123', true],
            'oauth authorize without token' => [$oauth, false],
            'oauth authorize with space' => [$oauth . 'abc 123', false],
            'oauth authorize with backslash' => [$oauth . 'abc\\123', false],
            'oauth authorize with trailing newline' => [$oauth . "abc\n", false],
            'oauth lookalike host' => ['https://meta.wikimedia.org.evil.example/w/index.php?title=Special:OAuth/authorize&oauth_token=abc', false],
        ];
    }

    #[DataProvider('redirectProvider')]
    public function testIsSafeRedirectTarget(string $target, bool $expected): void {
        putenv('PUBLIC_BASE_URL=https://public.example/tools');
        $this->assertSame($expected, is_safe_redirect_target($target));
    }

    public function testRedirectTargetsFailClosedWhenTheBaseUrlIsBroken(): void {
        putenv('PUBLIC_BASE_URL=');
        $this->assertTrue(is_safe_redirect_target('/local'), 'Local paths need no configuration');
        $this->assertFalse(is_safe_redirect_target('https://public.example/tools/page'));
    }

    public function testLocalReturnPathValidation(): void {
        $this->assertTrue(is_valid_local_return_path('/'));
        $this->assertTrue(is_valid_local_return_path('/a/b?c=d#e'));
        $this->assertFalse(is_valid_local_return_path(''));
        $this->assertFalse(is_valid_local_return_path('a'));
        $this->assertFalse(is_valid_local_return_path('//a'));
        $this->assertFalse(is_valid_local_return_path('/a b'));
        $this->assertFalse(is_valid_local_return_path('/a\\b'));
        $this->assertFalse(is_valid_local_return_path("/a\x00b"));
        $this->assertFalse(is_valid_local_return_path('/a '));
    }

    public function testOauthUrlsEncodeTheReturnPath(): void {
        putenv('PUBLIC_BASE_URL=https://public.example/tools');
        $this->assertSame(
            'https://public.example/tools/authenticate.php?return=%2Fx%3Fy%3D1%26z%3D2',
            oauth_authentication_url('/x?y=1&z=2')
        );
        $this->assertSame('https://public.example/tools/authenticate.php', oauth_callback_url(null));
        $this->assertSame(
            'https://public.example/tools/authenticate.php?return=%2Fback',
            oauth_callback_url('/back')
        );
        $this->expectException(InvalidArgumentException::class);
        oauth_authentication_url('//evil.example');
    }

    // ---- request configuration and sessions ------------------------------------

    public function testPublicRequestConfigurationValidity(): void {
        putenv('PUBLIC_BASE_URL=https://public.example');
        putenv('ALLOWED_HOSTS=public.example');
        putenv('ALLOWED_ORIGINS=https://public.example');
        $this->assertTrue(public_request_configuration_is_valid('public.example'));
        $this->assertFalse(public_request_configuration_is_valid('other.example'));
        $this->assertFalse(public_request_configuration_is_valid(null));

        putenv('ALLOWED_HOSTS=other.example');
        $this->assertFalse(public_request_configuration_is_valid('other.example'), 'The base host must also be allowed');

        putenv('ALLOWED_HOSTS=public.example');
        putenv('ALLOWED_ORIGINS=not an origin');
        $this->assertFalse(public_request_configuration_is_valid('public.example'));

        putenv('ALLOWED_ORIGINS=https://public.example');
        putenv('PUBLIC_BASE_URL=ftp://public.example');
        $this->assertFalse(public_request_configuration_is_valid('public.example'));
    }

    public function testSessionOptionsFollowTheBaseUrlScheme(): void {
        putenv('PUBLIC_BASE_URL=https://public.example');
        $this->assertSame(
            ['use_strict_mode' => true, 'cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => true],
            public_session_start_options()
        );
        $this->assertSame(
            ['use_strict_mode' => true, 'cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => true, 'read_and_close' => true],
            public_session_start_options(true)
        );
        putenv('PUBLIC_BASE_URL=HTTP://localhost:8081');
        $this->assertFalse(public_session_start_options()['cookie_secure']);
    }

    public function testCommandLineRequestsSkipHostEnforcement(): void {
        putenv('PUBLIC_BASE_URL=');
        putenv('ALLOWED_HOSTS=');
        ob_start();
        enforce_public_request_configuration('evil.example');
        $output = (string) ob_get_clean();
        $this->assertSame('', $output);
        initialize_public_config_encoding();
        $this->assertSame('UTF-8', mb_internal_encoding());
    }
}
