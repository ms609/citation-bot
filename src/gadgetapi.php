<?php

declare(strict_types=1);

// https://en.wikipedia.org/wiki/MediaWiki:Gadget-citations.js
set_time_limit(120);

require_once __DIR__ . '/includes/GadgetApi.php';
require_once __DIR__ . '/includes/RequestRateLimit.php';

$gadget_initial_ob_level = ob_get_level();
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

    $retry_after = request_rate_limit_consume_layered(
        'gadgetapi',
        GADGET_API_RATE_LIMIT_CAPACITY,
        GADGET_API_RATE_LIMIT_REFILL_PER_SECOND,
        GADGET_API_CLIENT_RATE_LIMIT_CAPACITY,
        GADGET_API_CLIENT_RATE_LIMIT_REFILL_PER_SECOND,
        is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : null
    );
    if ($retry_after !== null) {
        @header('Retry-After: ' . $retry_after);
        @header('Cache-Control: no-store');
        throw new GadgetApiRequestException('rate_limited', 429);
    }
    unset($retry_after);
    unset($_GET, $_POST, $_REQUEST); // Memory minimize

    //Expand text from postvars
    $page = new Page();
    ob_start(); // Capture stray output from parsing/expansion so it cannot corrupt the JSON response
    $page->parse_text($originalText);
    $page->expand_text();
    ob_end_clean();
    $newText = $page->parsed_text();
    if ($newText === "") {
        throw new RuntimeException('Parsed text unexpectedly empty');
    }

    //Modify edit summary to identify bot-assisted edits
    if ($newText !== $originalText) {
        if ($editSummary !== '') {
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

    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (GadgetApiRequestException $exception) {
    // Discard every buffer opened by this request; leave caller/server buffers intact.
    while (ob_get_level() > $gadget_initial_ob_level) {
        if (!@ob_end_clean()) {
            break;
        }
    }

    gadget_api_error($exception->error_name, $exception->http_status);
} catch (Throwable $exception) {
    // Discard every buffer opened by this request; leave caller/server buffers intact.
    while (ob_get_level() > $gadget_initial_ob_level) {
        if (!@ob_end_clean()) {
            break;
        }
    }
    if (function_exists('bot_debug_log')) {
        bot_debug_log('gadgetapi failure: ' . $exception::class . ': ' . $exception->getMessage());
    } else {
        error_log('gadgetapi failure: ' . $exception::class . ': ' . $exception->getMessage());
    }
    gadget_api_error('internal_error', 500);
}
