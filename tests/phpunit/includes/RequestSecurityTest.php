<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/includes/request_security.php';

final class RequestSecurityTest extends PHPUnit\Framework\TestCase {

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

    public function testConfirmationFormEscapesUntrustedFields(): void {
        $form = post_confirmation_form('process_page.php', ['page' => '"><script>'], 'a&b', 'Continue');
        $this->assertStringContainsString('method="post"', $form);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $form);
        $this->assertStringContainsString('a&amp;b', $form);
        $this->assertStringNotContainsString('<script>', $form);
    }

    public function testConfirmationFormShowsRequestedGetDetails(): void {
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
    }
}
