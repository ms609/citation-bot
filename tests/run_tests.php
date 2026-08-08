<?php

declare(strict_types=1);

/*
 * Cross-platform test runner.
 *
 * Runs the PHPUnit test suite via paratest, then generates a JUnit
 * timing report, and finally exits with paratest's exit code.
 */

$paratest_command = PHP_BINARY . ' -d memory_limit=1G vendor/bin/paratest'
    . ' --processes=auto --runner=WrapperRunner --enforce-time-limit'
    . ' --default-time-limit=60000 --cache-directory=.phpunit.cache'
    . ' --coverage-clover=coverage.xml --log-junit=junit.xml --verbose';

// Send paratest's output to stderr, keeping stdout for the timing report
$process = proc_open(
    $paratest_command,
    [
        0 => ['file', 'php://stdin', 'r'],
        1 => ['file', 'php://stderr', 'w'],
        2 => ['file', 'php://stderr', 'w'],
    ],
    $pipes
);

if (!is_resource($process)) {
    fwrite(STDERR, "Failed to start paratest\n");
    exit(1);
}

$paratest_exit_code = proc_close($process);

// Generate the timing report on stdout; its exit code is not propagated
passthru(PHP_BINARY . ' -d memory_limit=1G tests/parse_junit.php', $junit_exit_code);
unset($junit_exit_code);

exit($paratest_exit_code);
