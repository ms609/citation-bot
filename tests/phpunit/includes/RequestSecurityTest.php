<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once __DIR__ . '/../../../src/includes/request_security.php';
require_once __DIR__ . '/../../../src/includes/PublicConfig.php';

final class RequestSecurityTest extends PHPUnit\Framework\TestCase {
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

    public function testCsrfTokenInitializationCreatesAStoredRandomToken(): void {
        $session = [];
        $token = ensure_session_csrf_token($session);

        $this->assertMatchesRegularExpression('~^[a-f0-9]{64}$~D', $token);
        $this->assertSame($token, $session['csrf_token']);
    }

    public function testCsrfTokenInitializationPreservesAnExistingToken(): void {
        $session = ['csrf_token' => 'existing-token'];

        $this->assertSame('existing-token', ensure_session_csrf_token($session));
        $this->assertSame('existing-token', $session['csrf_token']);
    }

    public function testCsrfValidationRequiresPost(): void {
        $this->assertFalse(request_has_valid_post_csrf(['REQUEST_METHOD' => 'GET'], ['csrf_token' => 'token'], ['csrf_token' => 'token']));
    }

    public function testCsrfValidationRequiresStoredAndMatchingToken(): void {
        $server = ['REQUEST_METHOD' => 'POST'];
        $this->assertFalse(request_has_valid_post_csrf($server, [], []));
        $this->assertFalse(request_has_valid_post_csrf($server, ['csrf_token' => 'wrong'], ['csrf_token' => 'token']));
        $this->assertTrue(request_has_valid_post_csrf($server, ['csrf_token' => 'token'], ['csrf_token' => 'token']));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConfirmationFormEscapesUntrustedFields(): void {
        try {
            uopz_redefine('HTML_OUTPUT', true);
            $form = post_confirmation_form('process_page.php', ['page' => '"><script>'], 'a&b', 'Continue');
            $this->assertStringContainsString('method="post"', $form);
            $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $form);
            $this->assertStringContainsString('a&amp;b', $form);
            $this->assertStringNotContainsString('<script>', $form);
        finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', false);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConfirmationFormShowsRequestedGetDetails(): void {
        try {
            uopz_redefine('HTML_OUTPUT', true);
            $fields = [
                'page' => 'Example page',
                'wiki_base' => 'en',
                'pcre' => '0',
                'slow' => '1',
            ];
            $form = post_confirmation_form('process_page.php', $fields, 'token', 'Process page');
            $this->assertStringContainsString('Requested action: <strong>Process page</strong>', $form);
            $this->assertStringContainsString('<dt>Page</dt><dd>Example page</dd>', $form);
            $this->assertStringContainsString('<dt>Wiki</dt><dd>en</dd>', $form);
            $this->assertStringContainsString('<dt>PCRE option</dt><dd>0</dd>', $form);
            $this->assertStringContainsString('<dt>Thorough mode</dt><dd>enabled</dd>', $form);
            $this->assertStringContainsString('No changes have been made. Confirm to continue.', $form);
        finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', false);
        }
    }

    public function testProcessPageOauthReturnBecomesConfirmedPost(): void {
        $query = [
            'page' => 'Example page',
            'edit' => 'toolbar',
            'wiki_base' => 'simple',
            'pcre' => '0',
            'slow' => '1',
        ];
        $returned_query = $this->query_after_oauth_return('process_page.php', $query);
        $this->assertSame($query, $returned_query);
        $fields = process_page_confirmation_fields($query['page'], $returned_query);
        $this->assertSame($query, $fields);

        $this->assert_confirmation_posts_fields('process_page.php', $fields, 'Process page');
    }

    public function testCategoryOauthReturnBecomesConfirmedPost(): void {
        $query = [
            'cat' => 'CS1 errors: DOI',
            'wiki_base' => 'en',
            'pcre' => '1',
            'slow' => '1',
        ];
        $returned_query = $this->query_after_oauth_return('category.php', $query);
        $this->assertSame($query, $returned_query);
        $fields = category_confirmation_fields($query['cat'], $returned_query);
        $this->assertSame([
            'cat' => 'CS1 errors: DOI',
            'extended_limit' => '1',
            'wiki_base' => 'en',
            'pcre' => '1',
            'slow' => '1',
        ], $fields);

        $this->assert_confirmation_posts_fields('category.php', $fields, 'Process category');
    }

    /**
     * @param string $script
     * @param array<string, string> $query
     * @return array<string, string>
     */
    private function query_after_oauth_return(string $script, array $query): array {
        $return_path = '/' . $script . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        $authentication_url = oauth_authentication_url($return_path);
        $path_after_authentication = $this->return_path_from_url($authentication_url);
        $this->assertSame($return_path, $path_after_authentication);

        $callback_url = oauth_callback_url($path_after_authentication);
        $path_after_callback = $this->return_path_from_url($callback_url);
        $this->assertSame($return_path, $path_after_callback);

        $query_string = parse_url($path_after_callback, PHP_URL_QUERY);
        $this->assertIsString($query_string);
        parse_str($query_string, $returned_query);
        foreach ($returned_query as $value) {
            $this->assertIsString($value);
        }
        /** @var array<string, string> $returned_query */
        return $returned_query;
    }

    private function return_path_from_url(string $url): string {
        $query_string = parse_url($url, PHP_URL_QUERY);
        $this->assertIsString($query_string);
        parse_str($query_string, $query);
        $return_path = $query['return'] ?? null;
        $this->assertIsString($return_path);
        return $return_path;
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    /**
     * @param string $action
     * @param array<string, string> $fields
     * @param string $button_text
     */
    private function assert_confirmation_posts_fields(string $action, array $fields, string $button_text): void {
        try {
            uopz_redefine('HTML_OUTPUT', true);
            $session = ['csrf_token' => 'known-token'];
            $form = post_confirmation_form($action, $fields, $session['csrf_token'], $button_text);
            $document = new DOMDocument();
            $this->assertTrue(@$document->loadHTML($form));
            $xpath = new DOMXPath($document);
            $forms = $xpath->query('//form[@method="post"]');
            $this->assertNotFalse($forms);
            $this->assertCount(1, $forms);
            $posted_form = $forms->item(0);
            $this->assertInstanceOf(DOMElement::class, $posted_form);
            '@phan-var DOMElement $posted_form';
            $this->assertSame($action, $posted_form->getAttribute('action'));

            $inputs = $xpath->query('//form[@method="post"]//input[@type="hidden"]');
            $this->assertNotFalse($inputs);

            $post = [];
            foreach ($inputs as $input) {
                $this->assertInstanceOf(DOMElement::class, $input);
                $post[$input->getAttribute('name')] = $input->getAttribute('value');
            }

            $this->assertSame(['csrf_token' => 'known-token'] + $fields, $post);
            $this->assertTrue(request_has_valid_post_csrf(['REQUEST_METHOD' => 'POST'], $post, $session));
        finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_redefine('HTML_OUTPUT', false);
        }
    }
}
