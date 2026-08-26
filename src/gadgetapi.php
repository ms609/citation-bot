<?php

declare(strict_types=1);

// https://en.wikipedia.org/wiki/MediaWiki:Gadget-citations.js
set_time_limit(120);

require_once __DIR__ . '/includes/GadgetApi.php';

try {
    //Set up tool requirements
    require_once __DIR__ . '/includes/setup.php';

    $origin = allowed_cors_origin(is_string($_SERVER['HTTP_ORIGIN'] ?? null) ? $_SERVER['HTTP_ORIGIN'] : null);
    if ($origin === null) {
        throw new GadgetApiRequestException('invalid_origin', 403);
    }

    send_configured_cors_header($origin);
    @header('Content-Type: application/json; charset=utf-8');
    unset($origin);

    [$originalText, $editSummary] = gadget_api_validate_request($_POST);
    unset($_GET, $_POST, $_REQUEST); // Memory minimize

    //Expand text from postvars
    $page = new Page();
    ob_start(); // For some reason this is needed sometimes
    $page->parse_text($originalText);
    $page->expand_text();
    ob_end_clean();
    $newText = $page->parsed_text();
    if ($newText === "") {
        throw new RuntimeException('Parsed text unexpectedly empty');
    }

    //Modify edit summary to identify bot-assisted edits
    if ($newText !== $originalText) {
        if ($editSummary) {
            $editSummary .= ' | '; // Add pipe if already something there.
        }
        $editSummary .= str_replace('Use this bot', 'Use this tool', $page->edit_summary()) . '| #UCB_Gadget ';
    }
    unset($originalText, $page);

    /**
     * @psalm-taint-escape html
     * @psalm-taint-escape has_quotes
     */
    $result = ['expandedtext' => $newText, 'editsummary' => $editSummary];

    unset($newText, $editSummary);
    ob_end_clean();

    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (GadgetApiRequestException $exception) {
    @ob_end_clean();
    @ob_end_clean();
    @ob_end_clean();
    // Above is paranoid panic code. So paranoid that we even empty buffers two extra times.

    gadget_api_error($exception->error_name, $exception->http_status);
} catch (Throwable $exception) {
    @ob_end_clean();
    @ob_end_clean();
    @ob_end_clean();
    // Above is paranoid panic code.    So paranoid that we even empty buffers two extra times
    if (function_exists('bot_debug_log')) {
        bot_debug_log('gadgetapi failure: ' . $exception::class . ': ' . $exception->getMessage());
    } else {
        error_log('gadgetapi failure: ' . $exception::class . ': ' . $exception->getMessage());
    }
    gadget_api_error('internal_error', 500);
}
