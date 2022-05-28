<?php
declare(strict_types=1);
set_time_limit(120);
$started = @session_start();

require_once 'html_headers.php';

if (!$started) {
 echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Killing the big job</title></head><body><main><pre>Could not even access session</pre></main></body></html>';
} elseif ((string) @$_SESSION['big_and_busy'] !== 'BLOCK3') {
 echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Killing the big job</title></head><body><main><pre>No exiting large job found</pre></main></body></html>';
} else {
 $_SESSION['kill_the_big_job'] = "YES";
 echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Killing the big job</title></head><body><main><pre>Existing large job flagged for stopping</pre></main></body></html>';
}
?>
