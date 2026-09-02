<?php

declare(strict_types=1);

const MINIMUM_STATEMENT_COVERAGE = 88.80;

$coverage_file = $argv[1] ?? 'coverage.xml';
if (!is_string($coverage_file) || !is_file($coverage_file)) {
    fwrite(STDERR, "Coverage file not found: {$coverage_file}\n");
    exit(1);
}

try {
    $xml = @simplexml_load_file(
        $coverage_file,
        SimpleXMLElement::class,
        LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
    );
} catch (Throwable $exception) {
    fwrite(STDERR, 'Unable to parse coverage XML: ' . $exception->getMessage() . "\n");
    exit(1);
}

if ($xml === false) {
    fwrite(STDERR, "Unable to parse coverage XML\n");
    exit(1);
}

$metrics = $xml->xpath('/coverage/project/metrics');
if (!is_array($metrics) || count($metrics) !== 1) {
    fwrite(STDERR, "Coverage XML does not contain project metrics\n");
    exit(1);
}

$attributes = $metrics[0]->attributes();
$statements = isset($attributes['statements']) ? (int) $attributes['statements'] : 0;
$covered = isset($attributes['coveredstatements']) ? (int) $attributes['coveredstatements'] : 0;

if ($statements <= 0 || $covered < 0 || $covered > $statements) {
    fwrite(STDERR, "Coverage XML contains invalid statement counts\n");
    exit(1);
}

$coverage = ($covered / $statements) * 100.0;
printf(
    "Statement coverage: %.2f%% (%d/%d), required minimum %.2f%%\n",
    $coverage,
    $covered,
    $statements,
    MINIMUM_STATEMENT_COVERAGE
);

if ($coverage + 0.00001 < MINIMUM_STATEMENT_COVERAGE) {
    fwrite(
        STDERR,
        sprintf(
            "Coverage regression: %.2f%% is below the %.2f%% floor\n",
            $coverage,
            MINIMUM_STATEMENT_COVERAGE
        )
    );
    exit(1);
}

exit(0);
