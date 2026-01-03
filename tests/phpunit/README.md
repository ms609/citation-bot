# Tests for Citation Bot classes

To run the tests for Parameter.php (for example), first check that PHP is installed and that the php directory is added to your system `PATH` environment variable. Then navigate to the root directory in which you have checked out the citation bot code, i.e. the folder containing setup.php.

Then, run the following command from the command line :

    phpunit --bootstrap ./includes/setup.php tests/phpunit/gadgetapiTest.php

## Running the Full Test Suite

The recommended way to run all tests is:

    composer run test

This uses ParaTest for parallel test execution:

- `--processes=auto`: Automatically uses all available CPU cores
- `--runner=WrapperRunner`: PHPUnit 12 compatibility
- `--coverage-clover coverage.xml`: Code coverage reports
- `--verbose`: Shows individual test execution times in console output

ParaTest provides 2-4x speedup by distributing tests across multiple processes. Required because PHPUnit 12 removed native parallel execution support.

The verbose output displays timing information for each test, making it easy to identify slow tests and monitor performance.

To run the tests on Toolforge, first

    webservice --backend=kubernetes php8.4 shell

then install phpunit and then test:

    php ../phpunit-9.phar --bootstrap [etc]

Use Ctrl-D to escape from Toolforge.
