## Tests for Citation Bot classes

To run the tests for Parameter.php (for example), first check that PHP is installed and that the
php directory is added to your system `PATH` environment variable.
Then navigate to the root directory in which you have checked out the citation bot code, 
i.e. the folder containing setup.php. 

Then, run the following command from the command line :

    phpunit --bootstrap ./includes/setup.php tests/phpunit/gadgetapiTest.php

## Running the Full Test Suite

The recommended way to run all tests is:

    composer run test

This uses ParaTest for parallel test execution with:
- `--processes=auto`: Automatically detects and uses all available CPU cores
- `--runner=WrapperRunner`: Uses the wrapper runner for PHPUnit 12 compatibility
- `--coverage-clover coverage.xml`: Generates code coverage reports for CI

**Why ParaTest?**
- PHPUnit 12 removed native parallel execution support (`--processes` flag)
- ParaTest provides robust parallel testing for PHPUnit 12+
- Achieves 2-4x speedup by distributing tests across CPU cores
- Reduces CI execution time and billable minutes by 50-80%

**Note**: Tests must be stateless and thread-safe for parallel execution. This test suite uses read-only environment variables and avoids shared state, making it safe for parallelization.

To run the tests on Toolforge, first

    webservice --backend=kubernetes php8.4 shell

then install phpunit and then test:

    php ../phpunit-9.phar --bootstrap [etc]

Use Ctrl-D to escape from Toolforge.
