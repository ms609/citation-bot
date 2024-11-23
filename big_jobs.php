<?php

declare(strict_types=1);

// Paranoid - trying to be atomic without non-portable locks etc.

// "hard" as in "try hard" and ignore errors
function hard_touch(string $file): void {
    touch($file);
    @fclose(@fopen($file, 'a')); // Do something else to file
}

function hard_unlink(string $file): void {
    @unlink($file);
}

function big_jobs_name(): string { // NEVER save this string. Always use this function so that clearstatcache is called
    $version = "_1"; // So we can reset everyone, and we are 100% sure we do not get just the directory name
    $start = "/dev/shm/"; // Avoid .nfs*** files, and auto-delete when container dies
    $user = (string) @$_SESSION['citation_bot_user_id'];
    $user = base64_encode($user); // Sanitize - will now just be a-zA-Z0-9/+ and padded with = and surrounded by quotes because of PHP
    $user = str_replace(["'", "=", '"', "/"], ["", "", "", "_"], $user); // Sanitize more
    $file = $start . $user . $version;
    @clearstatcache();
    @clearstatcache(true, $start);
    @clearstatcache(true, $file);
    @clearstatcache(true, $file . '_kill_job');
    return $file;
}

/** @param resource $lock_file */
function big_jobs_we_died($lock_file): void {
    @fclose($lock_file);
    hard_unlink(big_jobs_name());
}

function big_jobs_check_overused(int $page_count): void {
    static $lock_file; // Force file handle to stay open
    if (!HTML_OUTPUT || $page_count < 50) { // MAX_SIZE_OF_BIG_JOB
        return;
    }
    $fn = big_jobs_name();
    if (file_exists($fn) && fileatime($fn) < (time()-3600)) { // More than an hour
        hard_unlink($fn);
    }
    if (file_exists($fn)) {
        echo '<div style="text-align:center"><h1>Run blocked by your existing big run.</h1></div>';
        bot_html_footer();
        exit;
    }
    $lock_file = fopen($fn, 'x+');
    if ($lock_file === false) {
        echo '<div style="text-align:center"><h1>Unable to obtain large run lock.</h1></div>';
        bot_html_footer();
        exit;
    }
    define('BIG_JOB_MODE', 'YES');
    register_shutdown_function('big_jobs_we_died', $lock_file); // We now have a lock file that will magically go away when code dies/quits
}

function big_jobs_check_killed(): void {
    if (!HTML_OUTPUT || !defined('BIG_JOB_MODE')) {
        return;
    }
    $lfile = big_jobs_name();
    $kfile = $lfile . '_kill_job';
    if (file_exists($kfile)) {
        hard_unlink($kfile);
        echo '<div style="text-align:center"><h1>Run killed as requested.</h1></div>';
        bot_html_footer();
        exit; // Shutdown will close and delete lockfile
    }
    if (file_exists($lfile)) {
        hard_touch($lfile);
    }
}

function big_jobs_kill(): bool {
    if (!file_exists(big_jobs_name())) {
        return false;
    }
    hard_touch(big_jobs_name() . '_kill_job');
    return true;
}
