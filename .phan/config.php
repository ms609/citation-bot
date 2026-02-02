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
        './vendor'
    ],
    'directory_list' => [
        './vendor/mediawiki', './vendor/phpunit', './src/includes/constants', './src/includes/api', './src/includes', './src', './tests'
    ],
    // Maximum array elements per level (default: 256)
    'ast_trim_max_elements_per_level' => 128,

    // Maximum total elements across all levels (default: 512)
    'ast_trim_max_total_elements' => 5096,

    // Maximum union set size (default: 1024)
    'max_union_type_set_size' => 2048,
];
