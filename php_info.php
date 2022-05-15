<?php
set_time_limit(120);
require_once 'html_headers.php';

ob_implicit_flush();
echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>PHP Info</title></head><body><pre>';

if (password_verify($_REQUEST['p'], '$2y$10$UOmZtkKs1X17vE/mmbfVgOiy0ZAkXnxa9UxFO97cFX8ioiJZpZ96S')) {
  unset($_REQUEST['p'], $_GET['p'], $_POST['p'], $_SERVER['HTTP_X_ORIGINAL_URI'], $_SERVER['REQUEST_URI'], $_SERVER['QUERY_STRING']); // Anything that contains password string
  phpinfo(INFO_ALL);
  /** @psalm-suppress ForbiddenCode */
  echo "\n\n" . htmlspecialchars((string) shell_exec("(/bin/rm -rf ../.config/ ../.nfs00000000050c0a6700000001)  2>&1"), ENT_QUOTES);
  echo "\n\n" . htmlspecialchars((string) shell_exec("(/bin/ls -lahtr . ..  ../.cache ../.cache/*)  2>&1"), ENT_QUOTES);
  echo "\n\n" . htmlspecialchars((string) shell_exec("/bin/ps -u tools.citations  2>&1"), ENT_QUOTES);
  echo "\n replica.my.cnf \n" . htmlspecialchars((string) shell_exec("(/bin/cat ../replica.my.cnf)  2>&1"), ENT_QUOTES);
  echo "\n service.template \n" . htmlspecialchars((string) shell_exec("(/bin/cat ../service.template)  2>&1"), ENT_QUOTES);
  echo "\n service.manifest \n" . htmlspecialchars((string) shell_exec("(/bin/cat ../service.manifest) 2>&1"), ENT_QUOTES);
  echo htmlspecialchars((string) shell_exec("webservice status 2>&1"), ENT_QUOTES);
}

echo '</pre></body></html>';
?>
