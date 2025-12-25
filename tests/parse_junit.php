<?php
/**
 * Parse JUnit XML report and display test timings in a readable format
 * Format: [✓] ClassName::testName  time
 */

$junitFile = __DIR__ . '/../junit.xml';

if (!file_exists($junitFile)) {
    echo "JUnit XML file not found: $junitFile\n";
    exit(1);
}

$xml = simplexml_load_file($junitFile);
if ($xml === false) {
    echo "Failed to parse JUnit XML file\n";
    exit(1);
}

$tests = [];

// Parse all test cases from all test suites
foreach ($xml->xpath('//testcase') as $testcase) {
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
usort($tests, function($a, $b) {
    return $b['time'] <=> $a['time'];
});

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
