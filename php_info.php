<?php
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');
@header('Content-type: text/html; charset=utf-8');
?>
<!DOCTYPE html><html lang="en" dir="ltr"><head><title>PHP Info</title></head><body><pre>
<?php
ob_implicit_flush();
if (password_verify($_REQUEST['p'], '$2y$10$UOmZtkKs1X17vE/mmbfVgOiy0ZAkXnxa9UxFO97cFX8ioiJZpZ96S')) {
  phpinfo(INFO_ALL);
}
?>
</pre></body></html>
