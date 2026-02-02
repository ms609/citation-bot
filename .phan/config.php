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
     * This will get almost all of our large arrays completely used - not the null hdl ones
     */
    'ast_trim_max_elements_per_level' => 10000,
 
    /**
     * Set these to effectivelly infinite, since defaults result in false positives
     */
    'ast_trim_max_total_elements' => 500000,
    'max_union_type_set_size' => 500000,
];
