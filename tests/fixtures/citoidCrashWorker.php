<?php

declare(strict_types=1);

if ($argc !== 3) {
    exit(2);
}
if (function_exists('posix_setrlimit') && defined('POSIX_RLIMIT_CORE')) {
    /** @psalm-suppress UnusedFunctionCall */
    posix_setrlimit(POSIX_RLIMIT_CORE, 0, 0);
}
$directory = $argv[1];
$lock = fopen($directory . '/rate.lock', 'c+');
if ($lock === false || !flock($lock, LOCK_EX)) {
    exit(3);
}
if (file_put_contents($directory . '/rate.pending.crash-fixture', 'CB1|incomplete') === false ||
    file_put_contents($directory . '/ready', 'ready') === false) {
    exit(4);
}
// The controller creates this file only after verifying that flock is held.
// Sending the signal from within the worker avoids CI sandboxes that block
// external kill(2) calls from their test-controller process.
$proceed = $directory . '/proceed';
$deadline = microtime(true) + 8.0;
while (!is_file($proceed)) {
    if (microtime(true) >= $deadline) {
        exit(5);
    }
    usleep(20000);
    clearstatcache(true, $proceed);
}
if ($argv[2] === 'exit') {
    exit(0);
}
$signals = ['term' => 15, 'kill' => 9, 'hup' => 1, 'segv' => 11];
if (!isset($signals[$argv[2]]) || !function_exists('posix_kill') ||
    !posix_kill(posix_getpid(), $signals[$argv[2]])) {
    exit(6);
}
exit(7); // All selected signals must terminate the worker.
