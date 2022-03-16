<?php
declare(strict_types=1);
set_time_limit(120);
@session_start();
@header('Content-type: text/html; charset=utf-8');
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');

if (@$_SESSION['big_and_busy'] !== 'BLOCK3') {
 @session_write_close();
 echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Killing the big job</title></head><body><main><pre>No exiting large job found</pre></main></body></html>';
 exit();
}

$_SESSION['kill_the_big_job'] = "YES";  
@session_write_close();
echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Killing the big job</title></head><body><main><pre>Existing large job flagged for stopping</pre></main></body></html>';
exit();
?>
