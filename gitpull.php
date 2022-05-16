<?php
set_time_limit(120);

require_once 'html_headers.php';

ob_implicit_flush();
echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Git Pull</title></head><body><main><pre>';

if (mkdir('git_pull.lock', 0700)) {
  // Fetch only updates .git, so it is very safe. That is the first half of pull. So do it as own command
  /** @psalm-suppress ForbiddenCode */
  echo htmlspecialchars((string) shell_exec("(/usr/bin/git fetch ; /usr/bin/git pull)  2>&1"), ENT_QUOTES);
  echo htmlspecialchars((string) shell_exec("(/usr/bin/git fetch  --all; /usr/bin/git reset --hard origin/master)  2>&1"), ENT_QUOTES);
  rmdir('git_pull.lock') ;
} else {
  echo 'lock file exists -- aborting ';
}
echo '</pre></main></body></html>';
?>
