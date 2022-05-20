<?php

declare(strict_types=1);

/**
 * This configuration will be read and overlaid on top of the
 * default configuration. Command-line arguments will be applied
 * after this file is read.
 */
return [
    // A directory list that defines files that will be excluded
    // from static analysis, but whose class and method
    // information should be included.
    //
    'exclude_analysis_directory_list' => [
        './vendor/', './tests/', './tests/phpunit/'
    ],
    'directory_list' => [
        './', './constants/', './vendor/', './tests/', './tests/phpunit/'
    ],
    'target_php_version' => '8.0',
    'minimum_target_php_version' => '8.0',
];

