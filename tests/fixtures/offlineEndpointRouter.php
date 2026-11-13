<?php

declare(strict_types=1);

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\Token;

$root = (string) getenv('CITATION_BOT_TEST_ROOT');
if (PHP_SAPI !== 'cli-server' || $root === '') {
    http_response_code(404);
    return;
}
$source = $root . '/src';
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$endpoint = is_string($path) && str_starts_with($path, '/tools/') ? mb_substr($path, 7) : '';
if ($endpoint === 'health') {
    echo 'endpoint fixture ready';
    return;
}

/** @psalm-suppress UnusedFunctionCall */
uopz_set_return('curl_exec', static function (): never {
    throw new LogicException('External HTTP is disabled in endpoint fixtures');
}, true);
/** @psalm-suppress UnusedFunctionCall */
uopz_set_return('proc_open', static function (): never {
    throw new LogicException('Child processes are disabled in endpoint fixtures');
}, true);

switch ($endpoint) {
    case 'seed-session':
        require $source . '/includes/PublicConfig.php';
        require $source . '/includes/request_security.php';
        session_start(public_session_start_options());
        $_SESSION['csrf_token'] = 'csrf-before-oauth';
        $_SESSION['request_key'] = 'request-key';
        $_SESSION['request_secret'] = 'request-secret';
        if (isset($_GET['authorized']) || isset($_GET['stale'])) {
            $_SESSION['access_key'] = 'fixture-key';
            $_SESSION['access_secret'] = 'fixture-secret';
            if (isset($_GET['authorized'])) {
                $_SESSION['citation_bot_user_id'] = 'Fixture user';
            }
        } else {
            unset($_SESSION['access_key'], $_SESSION['access_secret'], $_SESSION['citation_bot_user_id']);
        }
        $resume = save_oauth_form($_SESSION, 'process_page.php', ['page' => 'Example <page>']);
        if (isset($_GET['expired'])) {
            $_SESSION['citation_bot_oauth_form']['expires'] = time() - 1;
        }
        echo json_encode(['id' => session_id(), 'resume' => $resume], JSON_THROW_ON_ERROR);
        session_write_close();
        break;

    case 'authenticate.php':
        require $root . '/vendor/autoload.php';
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Client::class, 'complete', static function (Token $request, string $verifier): Token {
            if ($request->key !== 'request-key' || $verifier !== 'accepted') {
                return new Token('', '');
            }
            return new Token('fixture-key', 'fixture-secret');
        }, true);
        require $source . '/authenticate.php';
        break;

    case 'process_page.php':
    case 'category.php':
    case 'linked_pages.php':
        require $source . '/includes/setup.php';
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Client::class, 'identify', static function (): never {
            throw new RuntimeException('Fixture rejected stale credentials');
        }, true);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(WikipediaBot::class, 'is_valid_user', static function (string $username): bool {
            file_put_contents(request_rate_limit_base_directory() . '/user-check', $username . "\n", FILE_APPEND);
            return true;
        }, true);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(WikipediaBot::class, 'read_details', static function (string $title): stdClass {
            return WikipediaBot::decode_object_response($title === 'User:Fixture/Example'
                ? '{"query":{"pages":{"1":{"ns":2}}}}'
                : '{}');
        }, true);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(WikipediaBot::class, 'linked_pages_batch', static function (string $title, ?array $continuation): ?array {
            if ($continuation === null) {
                return ['links' => [['ns' => 0, 'title' => 'Example']], 'continue' => ['continue' => 'next'], 'scanned' => 1];
            }
            $state = file_get_contents(big_run_state_directory(request_rate_limit_base_directory()) . '/' . BIG_RUN_STATE_FILE);
            file_put_contents(request_rate_limit_base_directory() . '/discovery-state', (string) $state);
            return null;
        }, true);
        uopz_set_hook('bot_admission_buffer_flush', static function (): void {
            $path = big_run_state_directory(request_rate_limit_base_directory()) . '/' . BIG_RUN_STATE_FILE;
            if (is_file($path)) {
                file_put_contents(request_rate_limit_base_directory() . '/state-after-discovery', (string) file_get_contents($path));
            }
        });
        require $source . '/' . $endpoint;
        break;

    case 'gadgetapi.php':
    case 'generate_template.php':
        require $source . '/includes/setup.php';
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(Page::class, 'expand_text', static function (): never {
            throw new LogicException('Rejected request reached citation expansion');
        }, true);
        require $source . '/' . $endpoint;
        break;

    case 'resume.php':
    case 'gitpull.php':
    case 'reset_big_run_state.php':
        require $source . '/' . $endpoint;
        break;

    default:
        http_response_code(404);
}
