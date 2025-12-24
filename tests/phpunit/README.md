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

This uses the optimized test configuration with:
- `--processes=auto`: Runs tests in parallel across all CPU cores for 2-4x speedup
- `--enforce-time-limit --default-time-limit 13000`: Ensures tests complete within reasonable time
- `--coverage-clover coverage.xml`: Generates code coverage reports

These optimizations reduce CI execution time and billable minutes by 50-80%.

To run the tests on Toolforge, first

    webservice --backend=kubernetes php8.4 shell

then install phpunit and then test:

    php ../phpunit-9.phar --bootstrap [etc]

Use Ctrl-D to escape from Toolforge.
