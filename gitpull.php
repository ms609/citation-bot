<?php

declare(strict_types=1);

header("Access-Control-Allow-Origin: null");

/** @psalm-suppress MissingFile */
require_once 'env.php';

if (($_GET['password'] ?? '') !== (string) @getenv('DEPLOY_PASSWORD') ) {
    http_response_code(403);
    exit('Incorrect password. Please add ?password=YOUR_PASSWORD to the URL. You can set the password in your .env file (DEPLOY_PASSWORD).');
}

ob_implicit_flush(true);
flush();

clearstatcache(true);
if (@mkdir('git_pull.lock', 0700)) {
    sleep(1); // paranoid
    /** @psalm-suppress ForbiddenCode */
    $git_hub = htmlspecialchars((string) shell_exec("(/usr/bin/git fetch  --all; /usr/bin/git reset --hard origin/master)  2>&1"), ENT_QUOTES);
    sleep(2);
    rmdir('git_pull.lock');
} else {
    sleep(3);
    $git_hub = "Please try again";
}
echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Git Pull</title></head><body><main><pre>', $git_hub, '</pre></main></body></html>';
