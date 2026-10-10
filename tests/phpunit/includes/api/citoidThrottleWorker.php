<?php

declare(strict_types=1);

/* Independent CLI worker for zoteroTest's cross-process rate limiter test. */
if ($argc !== 3) {
    exit(2);
}

// Reuse the standard test bootstrap without inheriting the ParaTest worker.
require_once dirname(__DIR__, 3) . '/testBaseClass.php';

$throttle = new ReflectionMethod(Zotero::class, 'throttle_citoid_requests');
if (!$throttle->invoke(null, $argv[1], 5.0)) {
    exit(1);
}

$written = file_put_contents($argv[2], (string) microtime(true) . "\n", FILE_APPEND | LOCK_EX);
exit($written === false ? 1 : 0);
