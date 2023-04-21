<?php
declare(strict_types=1);
set_time_limit(120);
@session_start(['read_and_close' => TRUE]);

require_once 'html_headers.php';
require_once 'big_jobs.php';

ob_implicit_flush();

if (!$started) {
 echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Killing the big job</title></head><body><main><pre>Could not even access session</pre></main></body></html>';
} elseif (!big_jobs_exists()) {
 echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Killing the big job</title></head><body><main><pre>No exiting large job found</pre></main></body></html>';
} else {
 big_jobs_kill();
 echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Killing the big job</title></head><body><main><pre>Existing large job flagged for stopping</pre></main></body></html>';
}
?>
