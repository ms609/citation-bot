<?php

declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class BigRunRecoveryEndpointTest extends PHPUnit\Framework\TestCase {
    private string $source;

    #[\Override]
    protected function setUp(): void {
        $source = file_get_contents(__DIR__ . '/../../../src/reset_big_run_state.php');
        $this->assertIsString($source);
        $this->source = $source;
    }

    public function testEndpointMirrorsGitpullAuthenticationBoundary(): void {
        $this->assertStringContainsString("require_once __DIR__ . '/env.php';", $this->source);
        $this->assertStringContainsString("require_once __DIR__ . '/includes/PublicConfig.php';", $this->source);
        $this->assertStringContainsString('enforce_public_request_configuration(', $this->source);
        $this->assertStringContainsString('send_configured_cors_header(', $this->source);
        $this->assertStringContainsString("getenv('DEPLOY_PASSWORD')", $this->source);
        $this->assertStringContainsString("'HTTP_X_DEPLOY_TOKEN'", $this->source);
        $this->assertStringContainsString('hash_equals($deployPassword, $password_in)', $this->source);
        $this->assertStringContainsString("if (\$requestMethod === 'GET')", $this->source);
        $this->assertStringContainsString("if (\$requestMethod !== 'POST')", $this->source);
        $this->assertStringContainsString("@header('Cache-Control: no-store')", $this->source);
        $this->assertStringContainsString("@header('Referrer-Policy: no-referrer')", $this->source);
        $this->assertStringContainsString("@header('X-Content-Type-Options: nosniff')", $this->source);
        $this->assertStringContainsString('Content-Security-Policy:', $this->source);
    }

    public function testEndpointNeverAcceptsPasswordOrActionFromQueryString(): void {
        $this->assertStringNotContainsString("\$_GET['password']", $this->source);
        $this->assertStringNotContainsString("\$_GET['action']", $this->source);
        $this->assertStringContainsString('if (!empty($_GET))', $this->source);
        $this->assertStringContainsString("Location: reset_big_run_state.php", $this->source);
    }

    public function testResetRequiresAuthenticatedPostAndExplicitConfirmation(): void {
        $auth = strpos($this->source, 'hash_equals($deployPassword, $password_in)');
        $confirmation = strpos($this->source, "if (\$confirm_reset !== 'yes')");
        $reset = strpos($this->source, '$result = big_run_recovery_reset();');

        $this->assertIsInt($auth);
        $this->assertIsInt($confirmation);
        $this->assertIsInt($reset);
        $this->assertLessThan($confirmation, $auth);
        $this->assertLessThan($reset, $confirmation);
        $this->assertStringContainsString(
            'no automatic recovery was attempted',
            $this->source
        );
    }
}
