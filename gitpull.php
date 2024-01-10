<?php
declare(strict_types=1);
set_time_limit(120);

ob_implicit_flush(TRUE);

require_once 'html_headers.php';

echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Git Pull</title></head><body><main>';
// phpinfo(INFO_ALL);
echo '<pre>';
clearstatcache();
if (@mkdir('git_pull.lock', 0700)) {
  // Fetch only updates .git, so it is very safe. That is the first half of pull. So do it as own command
  /** @psalm-suppress ForbiddenCode */
  echo htmlspecialchars((string) shell_exec("(/usr/bin/git fetch  --all; /usr/bin/git reset --hard origin/master)  2>&1"), ENT_QUOTES);
  rmdir('git_pull.lock');
  exit('</pre></main></body></html>');
} else {
  sleep(1);
  clearstatcache();
  if (!file_exists('git_pull.lock')) exit("try again</pre></main></body></html>");
  sleep(1);
  clearstatcache();
  if (!file_exists('git_pull.lock')) exit("try again</pre></main></body></html>");
  sleep(1);
  clearstatcache();
  if (!file_exists('git_pull.lock')) exit("try again</pre></main></body></html>");
  sleep(1);
  clearstatcache();
  if (!file_exists('git_pull.lock')) exit("try again</pre></main></body></html>");
  sleep(1);
  clearstatcache();
  if (!file_exists('git_pull.lock')) exit("try again</pre></main></body></html>");
  @rmdir('git_pull.lock');
  @unlink('git_pull.lock'); // Paranoid
  exit('try again</pre></main></body></html>');
}
?>
