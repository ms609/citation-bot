<?php

declare(strict_types=1);

// Per-user >=50-page runs use a permanent guard inode to serialize lease
// acquisition, stale takeover, heartbeat, kill requests, and shutdown cleanup.

const BIG_JOBS_STALE_SECONDS = 3600;
const BIG_JOBS_HEARTBEAT_INTERVAL_SECONDS = 15;
const BIG_JOBS_FALLBACK_STATE_DIRECTORY = 'citation-bot-big-jobs';

/**
 * Prefer a private directory under Linux shared memory. Platforms without
 * writable /dev/shm (for example macOS) use the same private subdirectory
 * beneath the system temporary directory.
 */
function big_jobs_state_directory(): string {
    $shared_memory = '/dev/shm';
    if (is_dir($shared_memory) && is_writable($shared_memory)) {
        $base_directory = $shared_memory;
    } else {
        $base_directory = sys_get_temp_dir();
    }

    $directory =
        mb_rtrim($base_directory, "/\\", '8bit') .
        DIRECTORY_SEPARATOR .
        BIG_JOBS_FALLBACK_STATE_DIRECTORY;

    if (is_link($directory)) {
        throw new RuntimeException('Large-job state directory must not be a symlink.');
    }

    if (
        !is_dir($directory) &&
        !@mkdir($directory, 0700, true) &&
        !is_dir($directory)
    ) {
        throw new RuntimeException('Unable to create private large-job state directory.');
    }

    clearstatcache(true, $directory);
    $directory_stat = @lstat($directory);
    if (
        is_link($directory) ||
        !is_dir($directory) ||
        !is_writable($directory) ||
        !is_array($directory_stat) ||
        (($directory_stat['mode'] & 0170000) !== 0040000)
    ) {
        throw new RuntimeException('Large-job state directory is unsafe.');
    }

    if (function_exists('posix_geteuid')) {
        $owner = @fileowner($directory);
        if (!is_int($owner) || $owner !== posix_geteuid()) {
            throw new RuntimeException('Large-job state directory has an unexpected owner.');
        }
    }

    @chmod($directory, 0700);
    return $directory;
}

/** "hard" as in "try hard" and ignore errors */
function hard_touch(string $file): void {
    @touch($file);
    $handle = @fopen($file, 'a'); // Do something else to file
    if ($handle !== false) {
        @fclose($handle);
    }
}

function big_jobs_name(): string { // NEVER save this string. Always use this function so that clearstatcache is called
    $version = "_1"; // So we can reset everyone, and we are 100% sure we do not get just the directory name
    $start = big_jobs_state_directory();
    $user = (string) @$_SESSION['citation_bot_user_id'];
    $user = base64_encode($user); // Sanitize - will now just be a-zA-Z0-9/+ and padded with = and surrounded by quotes because of PHP
    $user = str_replace(["'", "=", '"', "/"], ["", "", "", "_"], $user); // Sanitize more
    $file = $start . DIRECTORY_SEPARATOR . $user . $version;
    @clearstatcache();
    @clearstatcache(true, $start);
    @clearstatcache(true, $file);
    @clearstatcache(true, $file . '_guard');
    @clearstatcache(true, $file . '_kill_job');
    return $file;
}

function big_jobs_guard_name(): string {
    return big_jobs_name() . '_guard';
}

/** @return resource|false */
function big_jobs_open_guard() {
    $guard_name = big_jobs_guard_name();
    clearstatcache(true, $guard_name);
    if (is_link($guard_name)) {
        return false;
    }

    $guard = @fopen($guard_name, 'c+');
    if ($guard === false) {
        return false;
    }

    clearstatcache(true, $guard_name);
    $held_stat = @fstat($guard);
    $path_stat = @lstat($guard_name);
    if (
        !is_array($held_stat) ||
        !is_array($path_stat) ||
        (($held_stat['mode'] & 0170000) !== 0100000) ||
        (($path_stat['mode'] & 0170000) !== 0100000) ||
        $held_stat['dev'] !== $path_stat['dev'] ||
        $held_stat['ino'] !== $path_stat['ino']
    ) {
        @fclose($guard);
        return false;
    }

    if (function_exists('posix_geteuid')) {
        $owner = @fileowner($guard_name);
        if (!is_int($owner) || $owner !== posix_geteuid()) {
            @fclose($guard);
            return false;
        }
    }

    @chmod($guard_name, 0600);
    return $guard;
}

/** @param resource $lock_file */
function big_jobs_owns_path($lock_file, string $path): bool {
    $held_stat = @fstat($lock_file);
    $current_stat = @lstat($path);
    return is_array($held_stat) &&
        is_array($current_stat) &&
        (($held_stat['mode'] & 0170000) === 0100000) &&
        (($current_stat['mode'] & 0170000) === 0100000) &&
        $held_stat['dev'] === $current_stat['dev'] &&
        $held_stat['ino'] === $current_stat['ino'];
}

/** @return resource|null */
function big_jobs_current_lock_file() {
    $lock_file = $GLOBALS['citation_bot_big_jobs_lock_file'] ?? null;
    return is_resource($lock_file) ? $lock_file : null;
}

/** Render a terminal large-job message without leaving buffered output behind. */
function big_jobs_stop_page(string $message): never {
    echo '<div style="text-align:center"><h1>',
        htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'),
        '</h1></div>';
    bot_html_footer();
    if (function_exists('bot_admission_buffer_flush')) {
        bot_admission_buffer_flush();
    }
    exit(0);
}

/** @param resource $lock_file */
function big_jobs_we_died($lock_file): void {
    $path = big_jobs_name();
    $guard = big_jobs_open_guard();
    if ($guard !== false) {
        if (@flock($guard, LOCK_EX)) {
            if (big_jobs_owns_path($lock_file, $path)) {
                @unlink($path);
            }
            @flock($guard, LOCK_UN);
        }
        @fclose($guard);
    }

    if (($GLOBALS['citation_bot_big_jobs_lock_file'] ?? null) === $lock_file) {
        unset($GLOBALS['citation_bot_big_jobs_lock_file']);
    }
    @fclose($lock_file);
}

/**
 * Acquire the user's >=50-page lease as soon as incremental discovery proves
 * that the request is large. A permanent per-user guard serializes stale
 * takeover so two contenders cannot both replace one expired lease.
 */
function big_jobs_acquire_large_run(): void {
    if (!HTML_OUTPUT || defined('BIG_JOB_MODE')) {
        return;
    }

    $fn = big_jobs_name();
    $guard = big_jobs_open_guard();
    if ($guard === false || !@flock($guard, LOCK_EX)) {
        if ($guard !== false) {
            @fclose($guard);
        }
        big_jobs_stop_page('Unable to obtain large run guard.');
    }

    $lock_file = false;
    $stop_message = null;
    try {
        clearstatcache(true, $fn);
        $last_seen = @filemtime($fn);
        if (is_int($last_seen) && $last_seen < (time() - BIG_JOBS_STALE_SECONDS)) {
            @unlink($fn);
            clearstatcache(true, $fn);
        }
        if (file_exists($fn)) {
            $stop_message = 'Run blocked by your existing big run.';
        } else {
            $lock_file = @fopen($fn, 'x+');
            if ($lock_file === false) {
                $stop_message = 'Unable to obtain large run lock.';
            } else {
                @chmod($fn, 0600);
                @unlink($fn . '_kill_job');
            }
        }
    } finally {
        @flock($guard, LOCK_UN);
        @fclose($guard);
    }

    if ($stop_message !== null || $lock_file === false) {
        big_jobs_stop_page($stop_message ?? 'Unable to obtain large run lock.');
    }

    $GLOBALS['citation_bot_big_jobs_lock_file'] = $lock_file;
    define('BIG_JOB_MODE', 'YES');
    register_shutdown_function('big_jobs_we_died', $lock_file);
}

/** Stop a stale process that no longer owns the pathname representing its lease. */
function big_jobs_stop_after_ownership_loss(): never {
    big_jobs_stop_page('Large run lock ownership was lost. Stopping this run.');
}

/**
 * Check the user's kill flag and refresh the active large-run lease.
 *
 * Ownership is verified against the request's still-open lease inode while the
 * permanent guard is held. A resumed stale process therefore cannot refresh a
 * newer request's replacement lease.
 */
function big_jobs_heartbeat(?int $now = null): bool {
    if (!HTML_OUTPUT || !defined('BIG_JOB_MODE')) {
        return true;
    }

    $lock_file = big_jobs_current_lock_file();
    if ($lock_file === null) {
        big_jobs_stop_after_ownership_loss();
    }

    $lfile = big_jobs_name();
    $kfile = $lfile . '_kill_job';
    $guard = big_jobs_open_guard();
    if ($guard === false || !@flock($guard, LOCK_EX)) {
        if ($guard !== false) {
            @fclose($guard);
        }
        return false;
    }

    $killed = false;
    $lost_ownership = false;
    $touched = false;
    try {
        clearstatcache(true, $lfile);
        if (!big_jobs_owns_path($lock_file, $lfile)) {
            $lost_ownership = true;
        } elseif (file_exists($kfile)) {
            @unlink($kfile);
            $killed = true;
        } else {
            $now ??= time();
            if ($now >= 0 && @touch($lfile, $now, $now)) {
                $touched = true;
            }
        }
    } finally {
        @flock($guard, LOCK_UN);
        @fclose($guard);
    }

    if ($lost_ownership) {
        big_jobs_stop_after_ownership_loss();
    }
    if ($killed) {
        big_jobs_stop_page('Run killed as requested.');
    }
    return $touched;
}

/** Rate-limit filesystem heartbeat work performed by common network hooks. */
function big_jobs_maybe_heartbeat(?int $now = null): void {
    static $last_heartbeat = null;

    if (!HTML_OUTPUT || !defined('BIG_JOB_MODE')) {
        return;
    }

    $now ??= time();
    if (
        is_int($last_heartbeat) &&
        $now >= $last_heartbeat &&
        ($now - $last_heartbeat) < BIG_JOBS_HEARTBEAT_INTERVAL_SECONDS
    ) {
        return;
    }

    if (big_jobs_heartbeat($now)) {
        $last_heartbeat = $now;
    }
}

function big_jobs_check_overused(int $page_count): void {
    static $reported = false;

    if (!HTML_OUTPUT || $page_count < 50) {
        return;
    }

    big_jobs_acquire_large_run();
    if (!$reported) {
        $reported = true;
        report_warning(
            "Large job mode: running " . $page_count .
            " pages — detailed per-parameter output is suppressed to conserve memory."
        );
    }
}

function big_jobs_check_killed(): void {
    if (!HTML_OUTPUT || !defined('BIG_JOB_MODE')) {
        return;
    }
    big_jobs_heartbeat();
}

function big_jobs_kill(): bool {
    $lfile = big_jobs_name();
    $guard = big_jobs_open_guard();
    if ($guard === false || !@flock($guard, LOCK_EX)) {
        if ($guard !== false) {
            @fclose($guard);
        }
        return false;
    }

    try {
        clearstatcache(true, $lfile);
        if (!file_exists($lfile)) {
            return false;
        }
        hard_touch($lfile . '_kill_job');
        return true;
    } finally {
        @flock($guard, LOCK_UN);
        @fclose($guard);
    }
}
