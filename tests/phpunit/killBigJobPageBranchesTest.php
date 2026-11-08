<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Exercises the remaining branches of the real kill_big_job.php entry point:
 * logged-out visitors, unsupported methods, bad CSRF tokens, and a confirmed
 * kill of an existing large job.  Like killBigJobPageTest, this deliberately
 * does NOT extend testBaseClass, so that CI and HTML_OUTPUT stay undefined
 * until the page itself defines them.  No network access is used.
 */
final class killBigJobPageBranchesTest extends PHPUnit\Framework\TestCase {

    private function prepareRequest(string $method, ?int $user_id, ?string $session_token): string {
        putenv('PUBLIC_BASE_URL=https://citations.toolforge.org');
        putenv('ALLOWED_HOSTS=citations.toolforge.org');
        putenv('ALLOWED_ORIGINS=https://citations.toolforge.org');
        $_SERVER['HTTP_HOST'] = 'citations.toolforge.org';
        $_SERVER['REQUEST_METHOD'] = $method;
        unset($_SERVER['HTTP_ORIGIN'], $_GET, $_REQUEST);
        $_POST = [];

        $session_id = 'kill-big-job-branches-' . bin2hex(random_bytes(8));
        session_id($session_id);
        session_start();
        if ($user_id !== null) {
            $_SESSION['citation_bot_user_id'] = $user_id;
        }
        if ($session_token !== null) {
            $_SESSION['csrf_token'] = $session_token;
        }
        session_write_close();
        return $session_id;
    }

    private function runPage(): string {
        ob_start();
        try {
            require dirname(__DIR__, 2) . '/src/kill_big_job.php';
            $body = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        return $body;
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLoggedOutVisitorIsToldToLogIn(): void {
        $this->prepareRequest('GET', null, null);

        $body = $this->runPage();

        $this->assertStringContainsString('<title>Killing the big job</title>', $body);
        $this->assertStringContainsString('You are not logged in', $body);
        $this->assertStringNotContainsString('Stop large job', $body);
        $this->assertStringNotContainsString('method="post"', $body);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLoggedOutPostIsStillToldToLogInBeforeAnyCsrfCheck(): void {
        $this->prepareRequest('POST', null, 'some-token');
        $_POST = ['csrf_token' => 'some-token'];

        $body = $this->runPage();

        $this->assertStringContainsString('You are not logged in', $body);
        $this->assertStringNotContainsString('Invalid CSRF token', $body);
        $this->assertStringNotContainsString('flagged for stopping', $body);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testUnsupportedMethodGetsAMethodNotAllowedPage(): void {
        $this->prepareRequest('PUT', 4242, 'some-token');

        $body = $this->runPage();

        $this->assertStringContainsString('Only GET and POST requests are supported', $body);
        $this->assertStringNotContainsString('Stop large job', $body);
        $this->assertStringNotContainsString('No existing large job found', $body);
        $this->assertSame(405, http_response_code());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMissingRequestMethodIsTreatedAsUnsupported(): void {
        $this->prepareRequest('GET', 4242, null);
        unset($_SERVER['REQUEST_METHOD']);

        $body = $this->runPage();

        $this->assertStringContainsString('Only GET and POST requests are supported', $body);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPostWithWrongCsrfTokenIsForbidden(): void {
        $this->prepareRequest('POST', 4242, 'the-real-token');
        $_POST = ['csrf_token' => 'a-forged-token'];

        $body = $this->runPage();

        $this->assertStringContainsString('Invalid CSRF token', $body);
        $this->assertStringNotContainsString('flagged for stopping', $body);
        $this->assertStringNotContainsString('No existing large job found', $body);
        $this->assertSame(403, http_response_code());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPostWithoutAnyCsrfTokenIsForbidden(): void {
        $this->prepareRequest('POST', 4242, 'the-real-token');
        $_POST = [];

        $body = $this->runPage();

        $this->assertStringContainsString('Invalid CSRF token', $body);
        $this->assertSame(403, http_response_code());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPostWithNonStringCsrfTokenIsForbidden(): void {
        $this->prepareRequest('POST', 4242, 'the-real-token');
        $_POST = ['csrf_token' => ['the-real-token']];

        $body = $this->runPage();

        $this->assertStringContainsString('Invalid CSRF token', $body);
        $this->assertSame(403, http_response_code());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConfirmedPostFlagsAnExistingLargeJobForStopping(): void {
        $user_id = random_int(1000000, 9999999);
        $token = 'kill-big-job-branches-token';
        $this->prepareRequest('POST', $user_id, $token);
        $_POST = ['csrf_token' => $token];

        require_once dirname(__DIR__, 2) . '/src/includes/big_jobs.php';
        $_SESSION = ['citation_bot_user_id' => $user_id];
        $lock_name = big_jobs_name();
        $kill_name = $lock_name . '_kill_job';
        $guard_name = big_jobs_guard_name();
        @unlink($kill_name);
        hard_touch($lock_name);
        $this->assertFileExists($lock_name);
        $this->assertFileDoesNotExist($kill_name);

        try {
            $body = $this->runPage();
            clearstatcache(true, $kill_name);

            $this->assertStringContainsString('Existing large job flagged for stopping', $body);
            $this->assertStringNotContainsString('No existing large job found', $body);
            $this->assertFileExists($kill_name);
            $this->assertFileExists($lock_name, 'Flagging a job must not remove its lease');
        } finally {
            @unlink($kill_name);
            @unlink($lock_name);
            @unlink($guard_name);
        }
    }
}
