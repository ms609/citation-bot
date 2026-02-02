<?php

declare(strict_types=1);

/**
 * This configuration will be read and overlaid on top of the
 * default configuration. Command-line arguments will be applied
 * after this file is read.
 */
return [
    /**
     * Excluded from static analysis, but whose class and method information are used
     */
    'exclude_analysis_directory_list' => [
        './vendor'
    ],
    'directory_list' => [
        './vendor/mediawiki', './vendor/phpunit', './src/includes/constants', './src/includes/api', './src/includes', './src', './tests'
    ],
    
    /**
     * This uses a 150MB more, and is probably not needed, but this gets 100% testing
     * This should not really be needed, since our arrays are consistent, but we do not want to assume that
     */
    'ast_trim_max_elements_per_level' => 500000,
 
    /**
     * Set these to effectivelly infinite, since defaults result in false positives as data is lost
     */
    'ast_trim_max_total_elements' => 5000000,
    'max_union_type_set_size' => 5000000,
];
