<?php

declare(strict_types=1);

if ($argc !== 3) {
    exit(2);
}
if (function_exists('posix_setrlimit') && defined('POSIX_RLIMIT_CORE')) {
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
if ($argv[2] === 'exit') {
    exit(0);
}
sleep(20);
exit(5);
