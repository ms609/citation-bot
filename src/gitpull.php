<?php

declare(strict_types=1);

// We minimize includes so this recovery endpoint still works if an application
// deployment breaks unrelated Citation Bot code.
//
// For automation:
// curl -X POST -H "X-Deploy-Token: ${DEPLOY_TOKEN}" "https://citations.toolforge.org/gitpull.php"
// For an iPhone/browser, visit gitpull.php and use Password AutoFill on the form.

/** @psalm-suppress MissingFile */
require_once dirname(__DIR__) . '/env.php';

require_once __DIR__ . '/includes/PublicConfig.php';

enforce_public_request_configuration(is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : null);
send_configured_cors_header(is_string($_SERVER['HTTP_ORIGIN'] ?? null) ? $_SERVER['HTTP_ORIGIN'] : null);
@header('Cache-Control: no-store');
@header('Referrer-Policy: no-referrer');
@header('X-Content-Type-Options: nosniff');
@header("Content-Security-Policy: default-src 'none'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

const LOCK_FILE = __DIR__ . '/git_pull.lock';
const GITPULL_CSRF_COOKIE = 'citation_bot_gitpull_csrf';
const GITPULL_CSRF_TTL = 600;
const GITPULL_FETCH_URL = 'https://github.com/ms609/citation-bot.git';
const GITPULL_ALLOWED_ORIGIN_URLS = [
    GITPULL_FETCH_URL,
    'https://github.com/ms609/citation-bot',
];

/**
 * Deployment credentials are machine-generated 256-bit values represented as
 * lowercase hexadecimal.  Requiring the complete format keeps online guessing
 * impractical without introducing an attacker-triggerable account lockout.
 */
function gitpull_valid_deploy_token(string $token): bool {
    return preg_match('~\A[a-f0-9]{64}\z~D', $token) === 1;
}

function gitpull_origin_is_expected(string $origin): bool {
    return in_array(
        mb_trim($origin),
        GITPULL_ALLOWED_ORIGIN_URLS,
        true
    );
}

/** @return resource|false */
function gitpull_open_lock() {
    clearstatcache(true, LOCK_FILE);
    if (is_link(LOCK_FILE) || is_dir(LOCK_FILE)) {
        return false;
    }

    $handle = @fopen(LOCK_FILE, 'c+');
    if ($handle === false) {
        return false;
    }

    clearstatcache(true, LOCK_FILE);
    $held_stat = @fstat($handle);
    $path_stat = @lstat(LOCK_FILE);
    if (
        !is_array($held_stat) ||
        !is_array($path_stat) ||
        (($held_stat['mode'] & 0170000) !== 0100000) ||
        (($path_stat['mode'] & 0170000) !== 0100000) ||
        $held_stat['dev'] !== $path_stat['dev'] ||
        $held_stat['ino'] !== $path_stat['ino']
    ) {
        @fclose($handle);
        return false;
    }

    if (function_exists('posix_geteuid')) {
        $owner = @fileowner(LOCK_FILE);
        if (!is_int($owner) || $owner !== posix_geteuid()) {
            @fclose($handle);
            return false;
        }
    }

    @chmod(LOCK_FILE, 0600);
    return $handle;
}

function gitpull_new_browser_nonce(): string {
    return bin2hex(random_bytes(32));
}

function gitpull_cookie_path(): string {
    return public_url_path('/gitpull.php');
}

function gitpull_set_browser_nonce(string $nonce): void {
    $scheme = parse_url(public_base_url(), PHP_URL_SCHEME);
    setcookie(GITPULL_CSRF_COOKIE, $nonce, [
        'expires' => time() + GITPULL_CSRF_TTL,
        'path' => gitpull_cookie_path(),
        'secure' => $scheme === 'https',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function gitpull_clear_browser_nonce(): void {
    $scheme = parse_url(public_base_url(), PHP_URL_SCHEME);
    setcookie(GITPULL_CSRF_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => gitpull_cookie_path(),
        'secure' => $scheme === 'https',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

/**
 * Render the deployment page and stop processing.
 */
function gitpull_page(string $message, bool $show_form, int $status, string $csrf_token = ''): never {
    http_response_code($status);
    @header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head>',
        '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
        '<meta charset="utf-8"><title>Git Pull</title></head><body><main>';

    echo '<p>', htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'), '</p>';

    if ($show_form) {
        $escaped_csrf = htmlspecialchars($csrf_token, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        echo '<form method="post" action="gitpull.php">',
            '<input name="csrf_token" type="hidden" value="', $escaped_csrf, '">',
            '<label for="deploy-user">Account</label> ',
            '<input id="deploy-user" name="username" type="text" value="deploy" ',
            'autocomplete="username" autocapitalize="none" spellcheck="false" readonly> ',
            '<label for="deploy-token">Deployment token</label> ',
            /*
             * current-password makes the long random token easy to store and
             * retrieve with iPhone Password AutoFill instead of typing it.
             */
            '<input id="deploy-token" name="deploy_token" type="password" ',
            'autocomplete="current-password" autocapitalize="none" spellcheck="false" ',
            'inputmode="text" enterkeyhint="go" minlength="64" maxlength="64" ',
            'pattern="[a-f0-9]{64}" required autofocus> ',
            '<button type="submit">Deploy</button>',
            '</form>';
    }

    echo '</main></body></html>';
    flush();
    exit(0);
}

function gitpull_browser_form(string $message, int $status): never {
    $nonce = gitpull_new_browser_nonce();
    gitpull_set_browser_nonce($nonce);
    gitpull_page($message, true, $status, $nonce);
}

/**
 * Build the deliberately small environment inherited by Git subprocesses.
 *
 * env.php populates the PHP process with OAuth credentials, API keys, and the
 * deployment token. Git does not need those values, so do not pass the parent
 * environment through wholesale.
 *
 * @return array<string, string>
 */
function gitpull_process_environment(): array {
    $environment = [
        'PATH' => '/usr/bin:/bin',
        'GIT_CONFIG_NOSYSTEM' => '1',
        'GIT_CONFIG_GLOBAL' => '/dev/null',
        'GIT_TERMINAL_PROMPT' => '0',
        'GIT_ASKPASS' => '/bin/false',
    ];

    foreach (['LANG', 'LC_ALL', 'LC_CTYPE', 'TMPDIR'] as $name) {
        $value = getenv($name);
        if (is_string($value) && $value !== '') {
            $environment[$name] = $value;
        }
    }

    return $environment;
}

/**
 * Run Git without a shell and without inheriting application credentials.
 *
 * @param array<int, string> $arguments
 * @return array{output: string, status: int}
 */
function gitpull_run_git(array $arguments): array {
    $command = [
        '/usr/bin/git',
        '-c',
        'credential.helper=',
        '-C',
        dirname(__DIR__),
        ...$arguments,
    ];
    $output_stream = tmpfile();
    if (!is_resource($output_stream)) {
        return ['output' => 'Unable to create Git command output stream.', 'status' => 1];
    }

    $pipes = [];

    /** @psalm-suppress ForbiddenCode */
    $process = proc_open( // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
        $command,
        [
            0 => ['file', '/dev/null', 'r'],
            1 => $output_stream,
            2 => $output_stream,
        ],
        $pipes,
        null,
        gitpull_process_environment()
    );

    if (!is_resource($process)) {
        fclose($output_stream);
        return ['output' => 'Unable to start Git command.', 'status' => 1];
    }

    $status = proc_close($process);
    rewind($output_stream);
    $output = stream_get_contents($output_stream);
    fclose($output_stream);

    return [
        'output' => is_string($output) ? $output : '',
        'status' => $status,
    ];
}

clearstatcache(true, LOCK_FILE);

$deployToken = (string) @getenv('DEPLOY_TOKEN');
if (!gitpull_valid_deploy_token($deployToken)) {
    gitpull_page('Error: DEPLOY_TOKEN is missing or invalid.', false, 503);
}

$requestMethod = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : '';

if ($requestMethod === 'GET') {
    if (!empty($_GET)) {
        // Never leave credentials or other flags in a bookmarked/browser URL.
        @header('Location: gitpull.php', true, 303);
        exit(0);
    }
    gitpull_browser_form('', 200);
}

if ($requestMethod !== 'POST') {
    @header('Allow: GET, POST');
    gitpull_page('Only GET and POST requests are supported.', false, 405);
}

if (!empty($_GET)) {
    gitpull_page('Deployment authorization failed.', false, 400);
}

$headerToken = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? null;
$browserSubmission = $headerToken === null;

if ($browserSubmission) {
    $tokenIn = $_POST['deploy_token'] ?? null;
    $csrfIn = $_POST['csrf_token'] ?? null;
    $usernameIn = $_POST['username'] ?? null;
    $csrfCookie = $_COOKIE[GITPULL_CSRF_COOKIE] ?? null;

    /*
     * Browser deployment requires a one-time same-site nonce in addition to
     * the deploy token.  This prevents another site from causing Safari to
     * submit an AutoFilled deployment credential.
     */
    $validBrowserNonce =
        is_string($csrfIn) &&
        is_string($csrfCookie) &&
        preg_match('~\A[a-f0-9]{64}\z~D', $csrfIn) === 1 &&
        hash_equals($csrfCookie, $csrfIn);

    gitpull_clear_browser_nonce();
    unset($_POST['deploy_token'], $_POST['csrf_token'], $_POST['username']);
    unset($_REQUEST['deploy_token'], $_REQUEST['csrf_token'], $_REQUEST['username']);
    unset($_COOKIE[GITPULL_CSRF_COOKIE]);

    if (!$validBrowserNonce || $usernameIn !== 'deploy') {
        gitpull_browser_form('Deployment authorization failed.', 403);
    }
} else {
    // Header authentication is intended for non-browser automation only.
    if (!empty($_POST) || !is_string($headerToken)) {
        gitpull_page('Deployment authorization failed.', false, 403);
    }
    $tokenIn = $headerToken;
    unset($_SERVER['HTTP_X_DEPLOY_TOKEN']);
}

$tokenMatches =
    is_string($tokenIn) &&
    gitpull_valid_deploy_token($tokenIn) &&
    hash_equals($deployToken, $tokenIn);
unset($deployToken, $tokenIn, $headerToken);

if (!$tokenMatches) {
    if ($browserSubmission) {
        gitpull_browser_form('Deployment authorization failed.', 403);
    }
    gitpull_page('Deployment authorization failed.', false, 403);
}

$lockHandle = gitpull_open_lock();
if ($lockHandle === false) {
    gitpull_page('Unable to open deployment lock.', false, 503);
}
if (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
    @fclose($lockHandle);
    gitpull_page('Please try again - deployment already in progress', false, 409);
}

$git_status = 200;
try {
    /*
     * Validate the configured fetch target before contacting the network.
     * `git remote get-url` also applies Git's url.*.insteadOf rewriting, so a
     * local rewrite away from the expected repository fails this check.
     */
    $origin = gitpull_run_git(['remote', 'get-url', '--all', 'origin']);
    if (
        $origin['status'] !== 0 ||
        !gitpull_origin_is_expected($origin['output'])
    ) {
        $git_status = 500;
        $git_hub = 'Deployment aborted: repository origin is not the expected Citation Bot repository.';
    } else {
        // gitpull_page() escapes output with htmlspecialchars, so keep raw here.
        // Fetch one branch from one canonical HTTPS URL. Do not contact other
        // configured remotes, tags, or submodules.
        $fetch = gitpull_run_git([
            'fetch',
            '--no-tags',
            '--no-recurse-submodules',
            GITPULL_FETCH_URL,
            '+refs/heads/master:refs/remotes/origin/master',
        ]);
        $git_hub = $fetch['output'];
        if ($fetch['status'] !== 0) {
            $git_status = 500;
            $git_hub =
                'git fetch of Citation Bot master failed with exit status ' .
                (string) $fetch['status'] .
                ".\n" .
                $git_hub;
        } else {
            $reset = gitpull_run_git(['reset', '--hard', 'origin/master']);
            if ($reset['status'] !== 0) {
                $git_status = 500;
                $git_hub .=
                    'git reset --hard origin/master failed with exit status ' .
                    (string) $reset['status'] .
                    ".\n";
            }
            $git_hub .= $reset['output'];
            unset($reset);
        }
        unset($fetch);
    }
    unset($origin);
} finally {
    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);
}

if ($browserSubmission) {
    gitpull_browser_form($git_hub, $git_status);
}

gitpull_page($git_hub, false, $git_status);
