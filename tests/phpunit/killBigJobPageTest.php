<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Exercises the real kill_big_job.php entry point without loading setup.php,
 * reproducing the cold web-request environment where HTML_OUTPUT and CI are
 * never defined. Deliberately does NOT extend testBaseClass, which would
 * define those constants and hide the failure this guards against.
 */
final class killBigJobPageTest extends PHPUnit\Framework\TestCase {

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLoggedInGetRequestRendersConfirmationForm(): void {
        putenv('PUBLIC_BASE_URL=https://citations.toolforge.org');
        putenv('ALLOWED_HOSTS=citations.toolforge.org');
        putenv('ALLOWED_ORIGINS=https://citations.toolforge.org');
        $_SERVER['HTTP_HOST'] = 'citations.toolforge.org';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_ORIGIN'], $_GET, $_POST, $_REQUEST);

        session_id('kill-big-job-page-test-session');
        session_start();
        $_SESSION['citation_bot_user_id'] = 12345;
        session_write_close();

        try {
            ob_start();
            require __DIR__ . '/../../src/kill_big_job.php';
            $body = (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            $this->fail('kill_big_job.php fatals on a logged-in GET request: ' . $e->getMessage());
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $this->assertStringContainsString('<title>Killing the big job</title>', $body);
        $this->assertStringContainsString('Stop large job', $body);
        $this->assertStringContainsString('method="post"', $body);
        $this->assertStringContainsString('name="csrf_token"', $body);
        $this->assertStringContainsString('</body></html>', $body);
        $this->assertStringNotContainsString('You are not logged in', $body);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConfirmedPostReportsMissingJobWhenNoLockFileExists(): void {
        putenv('PUBLIC_BASE_URL=https://citations.toolforge.org');
        putenv('ALLOWED_HOSTS=citations.toolforge.org');
        putenv('ALLOWED_ORIGINS=https://citations.toolforge.org');
        $_SERVER['HTTP_HOST'] = 'citations.toolforge.org';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['HTTP_ORIGIN'], $_GET, $_REQUEST);
        $_POST = ['csrf_token' => 'kill-big-job-page-test-token'];

        session_id('kill-big-job-page-test-session');
        session_start();
        $_SESSION['citation_bot_user_id'] = 12345;
        $_SESSION['csrf_token'] = 'kill-big-job-page-test-token';
        session_write_close();

        ob_start();
        require __DIR__ . '/../../src/kill_big_job.php';
        $body = (string) ob_get_clean();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $this->assertStringContainsString('<title>Killing the big job</title>', $body);
        $this->assertStringContainsString('No existing large job found', $body);
        $this->assertStringContainsString('</body></html>', $body);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLoggedInGetRequestRendersConfirmationForm(): void {
        putenv('PUBLIC_BASE_URL=https://citations.toolforge.org');
        putenv('ALLOWED_HOSTS=citations.toolforge.org');
        putenv('ALLOWED_ORIGINS=https://citations.toolforge.org');
        $_SERVER['HTTP_HOST'] = 'citations.toolforge.org';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_ORIGIN'], $_GET, $_POST, $_REQUEST);

        session_id('kill-big-job-page-test-session');
        session_start();
        $_SESSION['citation_bot_user_id'] = 12345;
        session_write_close();

        try {
            ob_start();
            require __DIR__ . '/../../src/kill_big_job.php';
            $body = (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            $this->fail('kill_big_job.php fatals on a logged-in GET request: ' . $e->getMessage());
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $this->assertStringContainsString('<title>Killing the big job</title>', $body);
        $this->assertStringContainsString('Stop large job', $body);
        $this->assertStringContainsString('method="post"', $body);
        $this->assertStringContainsString('name="csrf_token"', $body);
        $this->assertStringContainsString('</body></html>', $body);
        $this->assertStringNotContainsString('You are not logged in', $body);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConfirmedPostReportsMissingJobWhenNoLockFileExists(): void {
        putenv('PUBLIC_BASE_URL=https://citations.toolforge.org');
        putenv('ALLOWED_HOSTS=citations.toolforge.org');
        putenv('ALLOWED_ORIGINS=https://citations.toolforge.org');
        $_SERVER['HTTP_HOST'] = 'citations.toolforge.org';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['HTTP_ORIGIN'], $_GET, $_REQUEST);
        $_POST = ['csrf_token' => 'kill-big-job-page-test-token'];

        session_id('kill-big-job-page-test-session');
        session_start();
        $_SESSION['citation_bot_user_id'] = 12345;
        $_SESSION['csrf_token'] = 'kill-big-job-page-test-token';
        session_write_close();

        ob_start();
        require __DIR__ . '/../../src/kill_big_job.php';
        $body = (string) ob_get_clean();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $this->assertStringContainsString('<title>Killing the big job</title>', $body);
        $this->assertStringContainsString('No existing large job found', $body);
        $this->assertStringContainsString('</body></html>', $body);
    }
}
