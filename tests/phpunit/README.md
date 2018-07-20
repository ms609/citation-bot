##Tests for Citation Bot classes

These tests are implemented in `../../.travis.yml`, and can be performed locally.

To run the tests for Parameter.php (for example), first check that PHP is installed and that the
php directory is added to your system `PATH` environment variable.
Then navigate to the root directory in which you have checked out the citation bot code, 
i.e. the folder containing expandFns.php. 
(If your working directory is elsewhere, glob in constants.php won't work.)
Then, run the following command from the command line :

`phpunit --bootstrap ./Parameter.php tests/phpunit/ParameterTest.php`
