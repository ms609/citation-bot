<?php

declare(strict_types=1);

set_time_limit(120);
ob_implicit_flush(true);
require_once 'html_headers.php';
echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Git Pull</title></head><body><main><pre>';

clearstatcache();
if (@mkdir('git_pull.lock', 0700)) {
    /** @psalm-suppress ForbiddenCode */
    echo htmlspecialchars((string) shell_exec("(/usr/bin/git fetch  --all; /usr/bin/git reset --hard origin/master)  2>&1"), ENT_QUOTES);
    rmdir('git_pull.lock');
} else {
    sleep(2);
    echo 'try again';
}
echo '</pre></main></body></html>';
?>
