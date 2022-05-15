<?php
set_time_limit(120);
require_once 'html_headers.php';

ob_implicit_flush();
echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>PHP Info</title></head><body><pre>';

if (password_verify($_REQUEST['p'], '$2y$10$UOmZtkKs1X17vE/mmbfVgOiy0ZAkXnxa9UxFO97cFX8ioiJZpZ96S')) {
  unset($_REQUEST['p'], $_GET['p'], $_POST['p'], $_SERVER['HTTP_X_ORIGINAL_URI'], $_SERVER['REQUEST_URI'], $_SERVER['QUERY_STRING']); // Anything that contains password string
  phpinfo(INFO_ALL);
  /** @psalm-suppress ForbiddenCode */
  echo htmlspecialchars((string) shell_exec("(/bin/rm -f ../.mysql_history core cookiejar.txt composer.lock ../error.log ../service.log ../access.log ../.viminfo ../phpunit ../.description.swp '../u_verisimilus_yarrow (1).sql.gz ../core' ../dummy)  2>&1"), ENT_QUOTES);
  echo htmlspecialchars((string) shell_exec("(/bin/rm -rf ../.vim)  2>&1"), ENT_QUOTES);
  echo htmlspecialchars((string) shell_exec("(/bin/ls -lahtr . .. ../public_html_20180723 ../.subversion ../logs ../.composer ../cli ../www ../safe ../.cache ../.ssh)  2>&1"), ENT_QUOTES);
  echo htmlspecialchars((string) shell_exec("(/bin/cat ../.description)  2>&1"), ENT_QUOTES);
  echo htmlspecialchars((string) shell_exec("(/bin/cat ../.ssh/authorized_keys)  2>&1"), ENT_QUOTES);
  echo htmlspecialchars((string) shell_exec("(/bin/cat ../README.md)  2>&1"), ENT_QUOTES);
}

echo '</pre></body></html>';
?>
