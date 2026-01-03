<?php

declare(strict_types=1);

header("Access-Control-Allow-Origin: null");

/** @psalm-suppress MissingFile */
require_once __DIR__ . '/env.php';

const LOCK_DIR = 'git_pull.lock';

clearstatcache(true, LOCK_DIR);

if (($_GET['password'] ?? '') !== (string) @getenv('DEPLOY_PASSWORD') ) {
    $git_hub = 'Incorrect password. Please add ?password=YOUR_PASSWORD to the URL. You can set the password in your .env file (DEPLOY_PASSWORD).';
} elseif (@mkdir(LOCK_DIR, 0700)) {
    /** @psalm-suppress ForbiddenCode */
    $git_hub = htmlspecialchars((string) shell_exec("(/usr/bin/git fetch  --all; /usr/bin/git reset --hard origin/master)  2>&1"), ENT_QUOTES); // phpcs:ignore
    rmdir(LOCK_DIR);
} else {
    $git_hub = "Please try again - lock file found";
}
echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><meta name="viewport" content="width=device-width, initial-scale=1.0" /><title>Git Pull</title></head><body><main><pre>', $git_hub, '</pre></main></body></html>';
flush(); // paranoid about disk I/O
