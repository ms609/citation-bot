<?php

declare(strict_types=1);

@header('Access-Control-Allow-Origin: https://citations.toolforge.org');

/** @psalm-suppress MissingFile */
require_once __DIR__ . '/env.php';

const LOCK_DIR = 'git_pull.lock';

clearstatcache(true, LOCK_DIR);

$deployPassword = @getenv('DEPLOY_PASSWORD');

if ($deployPassword !== false && !hash_equals($_GET['password'] ?? '', (string) $deployPassword)) {
    $git_hub = 'Incorrect password. Please add ?password=YOUR_PASSWORD to the URL. You can set the password in your env.php file (DEPLOY_PASSWORD).';
} elseif (@mkdir(LOCK_DIR, 0700)) {
    /** @psalm-suppress ForbiddenCode */
    $git_hub = htmlspecialchars((string) shell_exec("(/usr/bin/git fetch  --all; /usr/bin/git reset --hard origin/master)  2>&1"), ENT_QUOTES); // phpcs:ignore
    rmdir(LOCK_DIR);
    if ($deployPassword === false) {
        $git_hub = $git_hub . '\nWarning: No DEPLOY_PASSWORD was required.';
    }
} else {
    $git_hub = "Please try again - lock file found";
}
echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><meta name="viewport" content="width=device-width, initial-scale=1.0" /><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><title>Git Pull</title></head><body><main><pre>', $git_hub, '</pre></main></body></html>';
flush(); // paranoid about disk I/O
