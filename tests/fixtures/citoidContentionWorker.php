<?php

declare(strict_types=1);

// Independent PHP interpreter: do not fork a running PHPUnit/ParaTest worker.
if ($argc !== 5) {
    exit(2);
}
require_once dirname(__DIR__) . '/testBaseClass.php';

$directory = $argv[1];
$ready = $argv[2];
$start = $argv[3];
$timeout = $argv[4];
if (file_put_contents($ready, 'ready') === false) {
    exit(3);
}
$deadline = hrtime(true) + 15000000000;
while (!is_file($start)) {
    if (hrtime(true) >= $deadline) {
        exit(4);
    }
    usleep(10000);
    clearstatcache(true, $start);
}
$method = new ReflectionMethod(Zotero::class, 'throttle_citoid_requests');
$allowed = $method->invoke(null, $directory, (float) $timeout);
printf("%d %.6f\n", (int) $allowed, microtime(true));
exit(0);
