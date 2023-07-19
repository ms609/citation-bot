<?php
declare(strict_types=1);

function big_jobs_name() : string {
  $version = "_1"; // So we can reset everyone, and we are 100% sure we do not get just the directory name
  $user = (string) @$_SESSION['citation_bot_user_id']; // Sometimes is not set - no idea how
  $user = base64_encode($user); // Sanitize - will now just be a-zA-Z0-9/+ and padded with = and surrounded by quotes because of PHP
  $user = str_replace(["'", "=", '"', "/"], ["", "", "", "_"], $user); // Sanitize more
  return "./user_locks/" . $user . $version; 
}

/** @param resource $lock_file **/
function big_jobs_we_died($lock_file) : void {
  @clearstatcache(TRUE);
  @fclose($lock_file);
  @unlink(big_jobs_name());
  flush();
}

function big_jobs_check_overused(int $page_count) : void {
 static $lock_file; // Force file handle to stay open
 if (!HTML_OUTPUT) return;
 if ($page_count < 50) return; // Used to be BIG_RUN constant
 clearstatcache(TRUE);
 $fn = big_jobs_name();
 if (file_exists($fn) && (filemtime($fn) > (time()-3600))) { // More than an hour
    @unlink($fn);
    flush();
 }
 if (file_exists($fn)) {
   echo '</pre><div style="text-align:center"><h1>Run blocked by your existing big run.</h1></div><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
   exit();
 } 
 $lock_file = fopen($fn, 'w+');
 if ($lock_file === FALSE) {
   echo '</pre><div style="text-align:center"><h1>Unable to obtain large run lock.</h1></div><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
   exit();
 } else {
   fflush($lock_file);
   flush();
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
   flush();
   exit(); // Shutdown will close and delete lockfile
 }
 $fn = big_jobs_name();
 if (file_exists($fn)) {
   touch($fn);
   flush();
 }
}

function big_jobs_kill() : bool {
 clearstatcache(TRUE);
 if (!file_exists(big_jobs_name())) return FALSE;
 touch(big_jobs_name() . '_kill_job');
 flush();
 return TRUE;
}
