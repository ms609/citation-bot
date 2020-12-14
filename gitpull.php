<?php
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');
?>
<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Git Pull</title><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /></head><body><main><pre>
<?php
ob_implicit_flush();
if (mkdir('git_pull.lock', 0700)) {
  // Fetch only updates .git, so it is very safe. That is the first half of pull. So do it as own command
  /** @psalm-suppress ForbiddenCode */
  echo htmlspecialchars((string) shell_exec("/usr/bin/git fetch 2>&1 ; /usr/bin/git pull 2>&1"));
  rmdir('git_pull.lock') ;
} else {
  echo 'lock file exists -- aborting ';
}
?>
</pre></main></body></html>
