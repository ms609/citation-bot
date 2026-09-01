<?php

declare(strict_types=1);

/*
 * Cross-platform test runner.
 *
 * Runs the PHPUnit test suite via paratest, then generates a JUnit
 * timing report, and finally exits with paratest's exit code.
 *
 * Memory limit is auto-detected: 1G on CI (GITHUB_ACTIONS/CI set),
 * 2G locally to allow more headroom for parallel coverage runs.
 */

$memory_limit = (getenv('GITHUB_ACTIONS') || getenv('CI')) ? '1G' : '2G';
@ini_set('memory_limit', $memory_limit);

$paratest_command = PHP_BINARY . ' -d memory_limit=' . $memory_limit . ' vendor/bin/paratest'
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

$coverage_exit_code = 0;
if ($paratest_exit_code === 0) {
    passthru(
        PHP_BINARY . ' -d memory_limit=' . $memory_limit . ' tests/check_coverage.php coverage.xml',
        $coverage_exit_code
    );
}

// Generate the timing report on stdout; its exit code is not propagated
passthru(PHP_BINARY . ' -d memory_limit=' . $memory_limit . ' tests/parse_junit.php', $junit_exit_code);
unset($junit_exit_code);

exit($paratest_exit_code !== 0 ? $paratest_exit_code : $coverage_exit_code);
