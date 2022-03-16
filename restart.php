<?php
set_time_limit(120);
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');
@header('Content-type: text/html; charset=utf-8');

echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Restart Bot</title></head><body><main><pre>';

ob_implicit_flush();
if (password_verify($_REQUEST['p'], '$2y$10$UOmZtkKs1X17vE/mmbfVgOiy0ZAkXnxa9UxFO97cFX8ioiJZpZ96S')) {
  /** @psalm-suppress ForbiddenCode */
  echo htmlspecialchars((string) shell_exec("webservice restart 2>&1"), ENT_QUOTES);
}

echo 'PHP done </pre></main></body></html>';

?>
