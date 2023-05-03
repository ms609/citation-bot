<?php

function big_jobs_name() : string {
  $version = "_1"; // So we can reset everyone
  $user = $_SESSION['citation_bot_user_id'];
  return "./user_locks/" . str_replace(["'", "="], '', base64_encode($user) . $version);
}

/** @param resource $lock_file **/
function big_jobs_we_died($lock_file) : void {
  @clearstatcache(TRUE);
  @fclose($lock_file);
  @unlink(big_jobs_name());
}

function big_jobs_check_overused(int $page_count) : void {
 static $lock_file; // Force file handle to stay open
 if (!HTML_OUTPUT) return;
 clearstatcache(TRUE);
 if ($page_count < 50) return; // Used to be BIG_RUN constant
 $fn = big_jobs_name();
 if (file_exists($fn) && (filemtime($fn) > (time()-3600))) { // More than an hour
    unlink($fn);
 }
 $lock_file = fopen($fn, 'w+');
 if ($lock_file === FALSE) {
   echo '</pre><div style="text-align:center"><h1>Run blocked by your existing big run.</h1>' . str_replace('user_locks/','', $fn) . '</div><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
   exit();
 } else {
   define('BIG_JOB_MODE', 'YES');
   register_shutdown_function('big_jobs_we_died', $lock_file); // We now have a lock file that will magically go away when code dies/quits
 }
}

function big_jobs_check_killed() : void {
 if (!HTML_OUTPUT) return;
 if (!defined('BIG_JOB_MODE')) return;
 clearstatcache(TRUE);
 $fn = big_jobs_name() . '_kill_job';
 if (file_exists($fn)) {
   echo '</pre><div style="text-align:center"><h1>Run killed as requested.</h1></div><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
   @unlink($fn);
   @unlink(big_jobs_name());
   exit();
 }
 $fn = big_jobs_name();
 if (file_exists($fn)) {
   touch($fn);
 }
}

function big_jobs_exists() : bool {
 clearstatcache(TRUE);
 return file_exists(big_jobs_name());
}

function big_jobs_kill() : void {
 clearstatcache(TRUE);
 touch(big_jobs_name() . '_kill_job');
}
