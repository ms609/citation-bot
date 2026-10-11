<?php

declare(strict_types=1);

/*
 * Inject a real POSIX short write into the production Citoid state writer.
 * Invoked only by citoidRateLimitRegressionTest; never issues an HTTP request.
 */

if ($argc !== 2 || PHP_OS_FAMILY !== 'Linux' ||
    !function_exists('posix_setrlimit') || !defined('POSIX_RLIMIT_FSIZE') ||
    !function_exists('pcntl_signal')) {
    exit(2);
}

require_once dirname(__DIR__) . '/testBaseClass.php';

// Ignore SIGXFSZ so the write reports an error instead of terminating the
// interpreter. A limit smaller than the CB1 record forces a partial write.
if (!pcntl_signal(SIGXFSZ, SIG_IGN) ||
    !posix_setrlimit(POSIX_RLIMIT_FSIZE, 16, 16)) {
    exit(3);
}

$throttle = new ReflectionMethod(Zotero::class, 'throttle_citoid_requests');
if ($throttle->invoke(null, $argv[1], 2.0)) {
    exit(4); // A partial write must never authorize a Citoid request.
}
exit(0);
