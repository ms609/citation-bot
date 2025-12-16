<?php

declare(strict_types=1);

header("Access-Control-Allow-Origin: null"); // Humans only

set_time_limit(120);
session_start(['read_and_close' => true]);

require_once __DIR__ . '/includes/big_jobs.php';

ob_implicit_flush(true);

if (!isset($_SESSION['citation_bot_user_id'])) {
    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Killing the big job</title></head><body><main><pre>You are not logged in</pre></main></body></html>';
} elseif (!big_jobs_kill()) {
    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Killing the big job</title></head><body><main><pre>No exiting large job found</pre></main></body></html>';
} else {
    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Killing the big job</title></head><body><main><pre>Existing large job flagged for stopping</pre></main></body></html>';
}
