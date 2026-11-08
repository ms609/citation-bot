<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php'; // Provides the public URL environment and bootstrap

/**
 * Network-free edge cases for the OAuth resume form helpers and the
 * confirmation-field builders in request_security.php.
 */
final class RequestSecurityEdgeCaseTest extends PHPUnit\Framework\TestCase {
    private string|false $saved_public_base_url;

    #[\Override]
    protected function setUp(): void {
        $this->saved_public_base_url = getenv('PUBLIC_BASE_URL');
        putenv('PUBLIC_BASE_URL=https://public.example/tools');
    }

    #[\Override]
    protected function tearDown(): void {
        if ($this->saved_public_base_url === false) {
            putenv('PUBLIC_BASE_URL');
        } else {
            putenv('PUBLIC_BASE_URL=' . $this->saved_public_base_url);
        }
    }

    /** @return array<string, mixed> */
    private function pendingSession(string $nonce, int $expires, bool $authorized): array {
        $session = [
            'citation_bot_oauth_form' => [
                'nonce' => $nonce,
                'expires' => $expires,
                'action' => 'process_page.php',
                'fields' => ['page' => 'Example'],
            ],
        ];
        if ($authorized) {
            $session['access_key'] = 'key';
            $session['access_secret'] = 'secret';
        }
        return $session;
    }

    public function testExpiredOauthFormIsDiscardedAndRejected(): void {
        $session = $this->pendingSession('abcdef', time() - 1, true);
        $this->assertNull(take_oauth_form($session, 'abcdef'));
        $this->assertArrayNotHasKey('citation_bot_oauth_form', $session);
    }

    public function testOauthFormWithMismatchedNonceIsRejectedButKept(): void {
        $session = $this->pendingSession('abcdef', time() + 600, true);
        $this->assertNull(take_oauth_form($session, 'abcdeg'));
        $this->assertArrayHasKey('citation_bot_oauth_form', $session);
    }

    public function testOauthFormWithoutPendingRequestIsRejected(): void {
        $session = ['access_key' => 'key', 'access_secret' => 'secret'];
        $this->assertNull(take_oauth_form($session, 'abcdef'));
        $this->assertArrayNotHasKey('citation_bot_oauth_form', $session);
    }

    public function testUnauthorizedSessionDoesNotConsumeThePendingForm(): void {
        $session = $this->pendingSession('abcdef', time() + 600, false);
        $this->assertNull(take_oauth_form($session, 'abcdef'));
        $this->assertArrayHasKey('citation_bot_oauth_form', $session);

        $session['access_key'] = 'key';
        $this->assertNull(take_oauth_form($session, 'abcdef'), 'The secret is also required');
        $this->assertArrayHasKey('citation_bot_oauth_form', $session);
    }

    public function testValidOauthFormIsReturnedOnceAndConsumed(): void {
        $session = $this->pendingSession('abcdef', time() + 600, true);
        $pending = take_oauth_form($session, 'abcdef');
        $this->assertIsArray($pending);
        $this->assertSame('process_page.php', $pending['action']);
        $this->assertSame(['page' => 'Example'], $pending['fields']);
        $this->assertArrayNotHasKey('citation_bot_oauth_form', $session);
        $this->assertNull(take_oauth_form($session, 'abcdef'), 'A form can only be resumed once');
    }

    public function testSavedOauthFormUsesBasePathAndRandomNonce(): void {
        $session = [];
        $first = save_oauth_form($session, 'category.php', ['cat' => 'Things']);
        $this->assertMatchesRegularExpression('~^/tools/resume\.php\?request=[0-9a-f]{32}$~D', $first);
        $this->assertSame('category.php', $session['citation_bot_oauth_form']['action']);
        $this->assertGreaterThan(time(), $session['citation_bot_oauth_form']['expires']);

        $second = save_oauth_form($session, 'category.php', ['cat' => 'Things']);
        $this->assertNotSame($first, $second, 'Every saved form gets a fresh nonce');
    }

    public function testOauthFormRequestRejectsUnknownActions(): void {
        $this->assertNull(oauth_form_request('evil.php', 'Example', []));
        $this->assertNull(oauth_form_request('', 'Example', []));
        $this->assertNull(oauth_form_request('PROCESS_PAGE.PHP', 'Example', []));
        $this->assertNull(oauth_form_request('../process_page.php', 'Example', []));
    }

    public function testOauthFormRequestBoundsTheTargetInBytes(): void {
        $limit = str_repeat('a', 12800);
        $request = oauth_form_request('process_page.php', $limit, []);
        if ($request === null) {
            $this->fail('Expected a form request at the byte limit');
        }
        $this->assertSame($limit, $request['fields']['page']);

        $this->assertNull(oauth_form_request('process_page.php', $limit . 'a', []));
        // Multibyte characters count by bytes, not characters: 6401 x 2 bytes = 12802
        $this->assertNull(oauth_form_request('process_page.php', str_repeat("\u{00e9}", 6401), []));
        $this->assertIsArray(oauth_form_request('process_page.php', str_repeat("\u{00e9}", 6400), []));
    }

    public function testOauthFormRequestMapsEachActionToItsOwnField(): void {
        $expected = [
            'process_page.php' => 'page',
            'category.php' => 'cat',
            'linked_pages.php' => 'linkpage',
        ];
        foreach ($expected as $action => $field) {
            $request = oauth_form_request($action, 'Target', []);
            $this->assertIsArray($request, $action);
            $this->assertSame($action, $request['action']);
            $this->assertSame([$field => 'Target'], $request['fields'], $action);
        }
    }

    public function testOauthFormRequestOnlyCarriesBoundedStringOptions(): void {
        $request = oauth_form_request('process_page.php', 'Target', [
            'edit' => 'yes',
            'wiki_base' => str_repeat('w', 255),
            'pcre' => str_repeat('p', 256), // too long: dropped
            'extended_limit' => ['not', 'a', 'string'], // wrong type: dropped
            'ignore_block' => '1',
            'csrf_token' => 'must-never-be-carried',
            'api_key' => 'must-never-be-carried',
            'slow' => '', // presence alone enables it
        ]);
        $this->assertIsArray($request);
        $this->assertSame(
            [
                'page' => 'Target',
                'edit' => 'yes',
                'wiki_base' => str_repeat('w', 255),
                'ignore_block' => '1',
                'slow' => '1',
            ],
            $request['fields']
        );
    }

    public function testConfirmationFieldsIgnoreNonStringOptions(): void {
        $query = [
            'edit' => ['array'],
            'wiki_base' => 'https://en.wikipedia.org',
            'pcre' => 5,
            'slow' => '0',
        ];
        $this->assertSame(
            ['page' => 'Example', 'wiki_base' => 'https://en.wikipedia.org', 'slow' => '1'],
            process_page_confirmation_fields('Example', $query)
        );
        $this->assertSame(
            ['cat' => 'Things', 'extended_limit' => '1', 'wiki_base' => 'https://en.wikipedia.org', 'slow' => '1'],
            category_confirmation_fields('Things', $query)
        );
        $this->assertSame(['page' => 'Plain'], process_page_confirmation_fields('Plain', []));
        $this->assertSame(
            ['cat' => 'Plain', 'extended_limit' => '1'],
            category_confirmation_fields('Plain', [])
        );
    }

    public function testConfirmationFormWithoutDisplayableFieldsOmitsTheSummary(): void {
        $html = post_confirmation_form('process_page.php', ['unlisted' => 'x'], 'token', 'Go');
        $this->assertStringNotContainsString('request-summary', $html);
        $this->assertStringContainsString('name="unlisted" value="x"', $html);
        $this->assertStringContainsString('name="csrf_token" value="token"', $html);
        $this->assertStringContainsString('<button type="submit">Go</button>', $html);
    }

    public function testConfirmationFormShowsSlowAsEnabledWhateverItsValue(): void {
        $html = post_confirmation_form('process_page.php', ['slow' => '0', 'page' => 'Example page'], 't', 'Go');
        $this->assertStringContainsString('<dt>Thorough mode</dt><dd>enabled</dd>', $html);
        $this->assertStringContainsString('<dt>Page</dt><dd>Example page</dd>', $html);
        $this->assertStringNotContainsString('<dd>0</dd>', $html, 'The raw slow value is never displayed');
    }

    public function testCsrfValidationRejectsNonStringAndEmptyTokens(): void {
        $post_server = ['REQUEST_METHOD' => 'POST'];
        $this->assertFalse(request_has_valid_post_csrf($post_server, ['csrf_token' => ['x']], ['csrf_token' => 'x']));
        $this->assertFalse(request_has_valid_post_csrf($post_server, ['csrf_token' => 'x'], ['csrf_token' => ['x']]));
        $this->assertFalse(request_has_valid_post_csrf($post_server, ['csrf_token' => ''], ['csrf_token' => '']));
        $this->assertFalse(request_has_valid_post_csrf($post_server, [], ['csrf_token' => 'x']));
        $this->assertFalse(request_has_valid_post_csrf($post_server, ['csrf_token' => 'x'], []));
        $this->assertFalse(request_has_valid_post_csrf(['REQUEST_METHOD' => 'post'], ['csrf_token' => 'x'], ['csrf_token' => 'x']));
        $this->assertTrue(request_has_valid_post_csrf($post_server, ['csrf_token' => 'x'], ['csrf_token' => 'x']));
    }
}
