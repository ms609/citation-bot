
<?php
set_time_limit(240);
require_once 'html_headers.php';

ob_implicit_flush();
echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Key Update</title></head><body><pre>';

if (isset($_REQUEST['p']) && password_verify($_REQUEST['p'], '$2y$10$UOmZtkKs1X17vE/mmbfVgOiy0ZAkXnxa9UxFO97cFX8ioiJZpZ96S')) {
  $secret = $_REQUEST['s'];
  $token = $_REQUEST['t'];
  unset ($_GET, $_POST, $_REQUEST);
  $env = file_get_contents('env.php');
  $env = preg_replace("~PHP_WP_OAUTH_CONSUMER=[^\']+~", "PHP_WP_OAUTH_CONSUMER=". $token, $env);
  $env = preg_replace("~PHP_WP_OAUTH_SECRET=[^\']+~", "PHP_WP_OAUTH_SECRET=" . $token, $env);
  $file = tempnam(".", "holder");
  file_put_contents($file, $env);
  echo "\n\n" . htmlspecialchars((string) shell_exec("(/bin/mv " . $file . " ../new_env.php )  2>&1"), ENT_QUOTES);
  set_time_limit(240);
  echo "\n  \n" . htmlspecialchars((string) shell_exec("(/bin/cat ../new_env.php )  2>&1"), ENT_QUOTES);
  echo "\n \n" . htmlspecialchars((string) shell_exec("(/bin/rm " . $file . " )  2>&1"), ENT_QUOTES);
}

echo '</pre></body></html>';
