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
- `--processes=2`: Limited to 2 processes to prevent API rate limit issues
- `--runner=WrapperRunner`: PHPUnit 12 compatibility
- `--coverage-clover coverage.xml`: Code coverage reports

ParaTest provides ~2x speedup. Process limit set to 2 prevents simultaneous external API calls that could trigger rate limiting. Required because PHPUnit 12 removed native parallel execution support.

To run the tests on Toolforge, first

    webservice --backend=kubernetes php8.4 shell

then install phpunit and then test:

    php ../phpunit-9.phar --bootstrap [etc]

Use Ctrl-D to escape from Toolforge.
