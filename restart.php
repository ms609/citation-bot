<?php
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');
?>
<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Restart Bot</title></head><body><pre>
<?php
ob_implicit_flush();
if (password_verify($_REQUEST['p'], '$2y$10$UOmZtkKs1X17vE/mmbfVgOiy0ZAkXnxa9UxFO97cFX8ioiJZpZ96S')) {
  /** @psalm-suppress ForbiddenCode */
  echo htmlspecialchars((string) shell_exec("webservice restart 2>&1"));
}
?>
PHP done </pre></body></html>

