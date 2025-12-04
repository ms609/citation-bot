<?php

declare(strict_types=1);

header("Access-Control-Allow-Origin: null");

/** @psalm-suppress MissingFile */
require_once 'env.php';

ob_implicit_flush(true);
flush();
clearstatcache(true);

if (($_GET['password'] ?? '') !== (string) @getenv('DEPLOY_PASSWORD') ) {
    http_response_code(403);
    $git_hub = 'Incorrect password. Please add ?password=YOUR_PASSWORD to the URL. You can set the password in your .env file (DEPLOY_PASSWORD).';
} elseif (@mkdir('git_pull.lock', 0700)) {
    /** @psalm-suppress ForbiddenCode */
    $git_hub = htmlspecialchars((string) shell_exec("(/usr/bin/git fetch  --all; /usr/bin/git reset --hard origin/master)  2>&1"), ENT_QUOTES);
    rmdir('git_pull.lock');
} else {
    sleep(1);
    $git_hub = "Please try again";
}
echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Git Pull</title></head><body><main><pre>', $git_hub, '</pre></main></body></html>';
