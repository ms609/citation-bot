<?php

static $big_jobs_lock_file;

function big_jobs_name() : string {
  $user = $_SESSION['citation_bot_user_id'];
  return "user_locks/" . base64_encode($user);
}

function big_jobs_check_overused(int $page_count) : void {
 if (!HTML_OUTPUT) return;
 if (!WikipediaBot::NonStandardMode()) return; // TOTO - enable for everyone
 if ($page_count < 50) return; // Used to be BIG_RUN constant
 $fn = big_jobs_name();
 $lock_file = fopen($fn, 'w+');
 if ($lock_file === FALSE) {
   echo '</pre><div style="text-align:center"><h1>Run blocked by your existing big run.</h1>' . str_replace('user_locks/','', $fn) . '</div><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
   exit();
 } else {
   $big_jobs_lock_file = $lock_file; // Force file handle to stay open
   unlink ($fn);  // We now have a lock file that will magically go away when code dies/quits
 }
}

function big_jobs_check_killed() : void {
 if (!HTML_OUTPUT) return;
 $fn = big_jobs_name() . '_kill_job';
 if (file_exists($fn)) {
   echo '</pre><div style="text-align:center"><h1>Run killed as requested.</h1></div><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
   unlink($fn);
   exit();
 }
}

function big_jobs_exists() : bool {
 if (!HTML_OUTPUT) return FALSE;
 $fn = big_jobs_name();
 $file = fopen($fn, 'w+');
 if ($file === FALSE) {
   return TRUE;
 } else {
   unlink($fn);
   fclose($file);
   return FALSE;
 }
}

function big_jobs_kill() : void {
 $fn = big_jobs_name() . '_kill_job';
 $file = fopen($fn, 'w+');
 fclose($file);
}
