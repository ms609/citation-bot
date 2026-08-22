# Tests for Citation Bot classes

To run the tests for Parameter.php (for example), first check that PHP is installed.
Then navigate to the repository root (the directory containing `composer.json`).

Then, run the following command:

    php vendor/bin/phpunit tests/phpunit/includes/parameterTest.php

## Running the Full Test Suite

The recommended way to run all tests is:

    composer run test

This uses ParaTest for parallel test execution:

- `--processes=auto`: Automatically selects worker count based on CPU cores
- `--runner=WrapperRunner`: ParaTest's default wrapper runner
- `--coverage-clover coverage.xml`: Combined Clover coverage output
- `--log-junit=junit.xml`: Records per-test results and durations
- `tests/parse_junit.php`: Prints durations sorted slowest-first
- `--verbose`: Prints additional ParaTest worker/debug information

Parallel execution can reduce runtime, depending on CPU count, network-bound tests,
and external API limits.

**Prerequisites:** The Composer test script is cross-platform and runs on supported
PHP CLI environments, including Windows. It requires PCOV or Xdebug coverage support
and Composer dependencies installed. For a focused test during development, use:

    php vendor/bin/phpunit path/to/Test.php

To run the tests on Toolforge, first:

    webservice --backend=kubernetes php8.4 shell

Then install dependencies and run:

    composer install
    php vendor/bin/phpunit tests/phpunit/includes/parameterTest.php
