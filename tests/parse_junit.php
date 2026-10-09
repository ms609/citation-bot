<?php
declare(strict_types=1);
/**
 * Parse JUnit XML report and display test timings in a readable format
 * Format: [✓] ClassName::testName  time
 */

$junitFile = dirname(__DIR__) . '/junit.xml';

if (!file_exists($junitFile)) {
    echo "\nWarning: JUnit XML file not found: $junitFile\n";
    echo "Test timing report cannot be generated.\n";
    exit(0); // Don't fail the build if timing report can't be generated
}

// Never fetch DTDs or external entities when parsing a potentially partial
// report. A malformed report must not hide the original ParaTest failure.
$xml = @simplexml_load_file($junitFile, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
if (!$xml instanceof SimpleXMLElement) {
    echo "\nWarning: Failed to parse JUnit XML file\n";
    echo "Test timing report cannot be generated.\n";
    exit(0); // Don't replace the original test exit code with a report error.
}

$cases = $xml->xpath('//testcase');
if ($cases === false) {
    echo "\nWarning: Failed to read JUnit test cases\n";
    exit(0);
}

$tests = [];

// Parse all test cases from all test suites
foreach ($cases as $testcase) {
    $class = (string)$testcase['class'];
    $name = (string)$testcase['name'];
    $time = (float)$testcase['time'];

    // Extract just the class name without namespace
    $classParts = explode('\\', $class);
    $shortClass = end($classParts);

    // Check if test failed, was skipped, or had errors
    $status = '✓';
    if (isset($testcase->failure)) {
        $status = '✗';
    } elseif (isset($testcase->error)) {
        $status = '✗';
    } elseif (isset($testcase->skipped)) {
        $status = 'S';
    }

    $tests[] = [
        'status' => $status,
        'class' => $shortClass,
        'name' => $name,
        'time' => $time,
        'display' => sprintf('[%s] %s::%s', $status, $shortClass, $name)
    ];
}

// Sort by time descending to show slowest tests first
usort($tests, function ($a, $b) {
    return $b['time'] <=> $a['time'];
});

flush();
echo "\n";
echo "================================================================================\n";
echo "Test Execution Times (sorted by duration, slowest first)\n";
echo "================================================================================\n\n";

$totalTime = 0;
foreach ($tests as $test) {
    $totalTime += $test['time'];
    printf("%-70s %8.3fs\n", $test['display'], $test['time']);
}

echo "\n";
echo "================================================================================\n";
printf("Total: %d tests, %.3f seconds\n", count($tests), $totalTime);
echo "================================================================================\n";
flush();
