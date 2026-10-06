<?php

declare(strict_types=1);

// This file is loaded only by the Playwright development server. Production
// Citation Bot requests never include it. A shutdown function is used instead
// of auto_append_file because PHP does not run auto_append_file after exit().
if (!function_exists('xdebug_start_code_coverage')) {
    return;
}

$repository_root = realpath(dirname(__DIR__, 2));
$source_root = is_string($repository_root)
    ? realpath($repository_root . DIRECTORY_SEPARATOR . 'src')
    : false;
if (!is_string($repository_root) || !is_string($source_root)) {
    return;
}

if (
    function_exists('xdebug_set_filter') &&
    defined('XDEBUG_FILTER_CODE_COVERAGE') &&
    defined('XDEBUG_PATH_INCLUDE')
) {
    $coverage_filter = constant('XDEBUG_FILTER_CODE_COVERAGE');
    $path_include = constant('XDEBUG_PATH_INCLUDE');
    if (is_int($coverage_filter) && is_int($path_include)) {
        xdebug_set_filter(
            $coverage_filter,
            $path_include,
            [$source_root . DIRECTORY_SEPARATOR]
        );
    }
}

$coverage_flags = defined('XDEBUG_CC_UNUSED') ? XDEBUG_CC_UNUSED : 0;
xdebug_start_code_coverage($coverage_flags);

register_shutdown_function(static function () use ($repository_root, $source_root): void {
    if (!function_exists('xdebug_get_code_coverage') || !function_exists('xdebug_stop_code_coverage')) {
        return;
    }

    $coverage = xdebug_get_code_coverage();
    xdebug_stop_code_coverage();

    $filtered = [];
    $source_prefix = $source_root . DIRECTORY_SEPARATOR;
    foreach ($coverage as $filename => $lines) {
        $real_filename = realpath($filename);
        if (!is_string($real_filename) || !str_starts_with($real_filename, $source_prefix)) {
            continue;
        }
        $relative = str_replace(
            DIRECTORY_SEPARATOR,
            '/',
            mb_substr($real_filename, mb_strlen($repository_root) + 1)
        );
        $filtered[$relative] = $lines;
    }

    $directory = getenv('UI_PHP_COVERAGE_DIR');
    if (!is_string($directory) || $directory === '') {
        $directory = 'coverage/php/raw';
    }
    if ($directory[0] !== DIRECTORY_SEPARATOR) {
        $directory = $repository_root . DIRECTORY_SEPARATOR . $directory;
    }
    if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
        return;
    }

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (!is_string($request_uri)) {
        $request_uri = '';
    }
    $request_path = parse_url($request_uri, PHP_URL_PATH);
    if (!is_string($request_path)) {
        $request_path = '';
    }

    $payload = json_encode(
        [
            // Never persist a query string in uploaded coverage artifacts.
            'path' => $request_path,
            'files' => $filtered,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );
    if (!is_string($payload)) {
        return;
    }

    try {
        $suffix = bin2hex(random_bytes(8));
    } catch (Throwable) {
        $suffix = str_replace('.', '-', uniqid('', true));
    }
    $process_id = getmypid();
    $process_label = is_int($process_id) ? (string) $process_id : 'unknown';
    $path = $directory . DIRECTORY_SEPARATOR . $process_label . '-' . $suffix . '.json';
    @file_put_contents($path, $payload, LOCK_EX);
});
