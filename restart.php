<!DOCTYPE html><html><head><title>Restart Bot</title></head><body><pre>
<?php
if (password_verify($_REQUEST['p'], '$2y$10$UOmZtkKs1X17vE/mmbfVgOiy0ZAkXnxa9UxFO97cFX8ioiJZpZ96S')) {
  echo shell_exec ("webservice restart 2>&1");
}
?>
PHP done </pre></body></html>

