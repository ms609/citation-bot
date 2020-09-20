<?php
declare(strict_types=1);
@session_start();
error_reporting(E_ALL^E_NOTICE);
@header( 'Content-type: text/html; charset=utf-8' );
@header("Content-Encoding: None", TRUE);
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');
@header_remove("Transfer-Encoding");
flush();
?>
<!DOCTYPE html><html lang="en" dir="ltr"><head><title>DEBUG CACHE</title></head><body><main>X</main><div>Y</div><pre>
<?php
ob_implicit_flush();
while (ob_get_level()) {
 ob_end_flush();
}
echo("\n" . str_pad("", 80096) . "\n");
flush();
echo "Wait 6</p>\n";
sleep(2);
flush();
echo "Wait 5</p>\n";
sleep(2);
flush();
echo "Wait 4</p>\n";
sleep(2);
flush();
/** @psalm-suppress ForbiddenCode */
@shell_exec("/usr/bin/sync 2>&1 /dev/null");
flush();
echo "</pre><pre>Wait 3</p>\n";
sleep(2);
flush();
echo "Wait 2</p>\n";
sleep(2);
flush();
echo "Wait 1</p>\n";
sleep(2);
flush();
echo "Script done</p>";
?>
</pre></body></html>
