##Tests for Citation Bot classes

To run the tests for Parameter.php (for example), first check that PHP is installed and that the
php directory is added to your system `PATH` environment variable.
Then navigate to the root directory in which you have checked out the citation bot code, 
i.e. the folder containing setup.php. 

Then, run the following command from the command line :

    phpunit --bootstrap ./setup.php tests/phpunit/ParameterTest.php

To run the tests on Toolforge, first

    webservice --backend=kubernetes php7.4 shell

then install phpunit and then test:

    php ../phpunit-5.phar --bootstrap [etc]

Use Ctrl-D to escape from Toolforge.
