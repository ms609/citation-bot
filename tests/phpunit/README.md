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

This uses ParaTest for parallel test execution:
- `--processes=auto`: Runs tests across all CPU cores
- `--runner=WrapperRunner`: PHPUnit 12 compatibility
- `--do-not-cache-result`: Disables result caching (avoids cache configuration requirement)
- `--coverage-clover coverage.xml`: Code coverage reports

ParaTest provides 2-4x speedup and 50-80% reduction in CI execution time. Required because PHPUnit 12 removed native parallel execution support.

To run the tests on Toolforge, first

    webservice --backend=kubernetes php8.4 shell

then install phpunit and then test:

    php ../phpunit-9.phar --bootstrap [etc]

Use Ctrl-D to escape from Toolforge.
