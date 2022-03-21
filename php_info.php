<?php
set_time_limit(120);
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');
@header('Content-type: text/html; charset=utf-8');

ob_implicit_flush();
echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>PHP Info</title></head><body><pre>';

if (password_verify($_REQUEST['p'], '$2y$10$UOmZtkKs1X17vE/mmbfVgOiy0ZAkXnxa9UxFO97cFX8ioiJZpZ96S')) {
  phpinfo(INFO_ALL);
}

echo '</pre></body></html>';
?>
