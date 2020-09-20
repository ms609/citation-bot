<?php
@session_start();
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');
?>
<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Unlocker</title></head><body><pre>
<?php
ob_implicit_flush();
while (ob_get_level()) {
 ob_end_flush();
}

echo "Wait 6</p>\n";
sleep(2);

echo "Wait 5</p>\n";
sleep(2);

echo "Wait 4</p>\n";
sleep(2);

@shell_exec("/usr/bin/sync 2>&1 /dev/null")

echo "Wait 3</p>\n";
sleep(2);

echo "Wait 2</p>\n";
sleep(2);

echo "Wait 1</p>\n";
sleep(2);

echo "Script done</p>"
?>
</pre></body></html>
