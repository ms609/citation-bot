<?php

declare(strict_types=1);

// https://en.wikipedia.org/wiki/MediaWiki:Gadget-citations.js
set_time_limit(120);

final class GadgetApiRequestException extends RuntimeException {
    public function __construct(
        public readonly string $error_name,
        public readonly int $http_status
    ) {
        parent::__construct($error_name);
    }
}

function gadget_api_error(string $error_name, int $http_status): void {
    http_response_code($http_status);
    @header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        ['error' => $error_name],
        JSON_THROW_ON_ERROR
    );
}

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

    if (!is_string(@$_POST['text']) || !is_string(@$_POST['summary'])) {
        throw new GadgetApiRequestException('invalid_parameters', 400);
    }
    $originalText = (string) $_POST['text'];
    $editSummary = (string) $_POST['summary'];
    unset($_GET, $_POST, $_REQUEST); // Memory minimize

    if (!mb_check_encoding($originalText, 'UTF-8') || !mb_check_encoding($editSummary, 'UTF-8')) {
        throw new GadgetApiRequestException('invalid_utf8', 400);
    }

    if (mb_strlen($originalText) < 6) {
        throw new GadgetApiRequestException('page_too_small', 400);
    } elseif (mb_strlen($originalText) > 150000) { // will probably time-out otherwise, see https://en.wikipedia.org/wiki/Special:LongPages
        throw new GadgetApiRequestException('page_too_large', 400);
    } elseif (mb_strlen($editSummary) > 1000) { // see https://en.wikipedia.org/wiki/Help:Edit_summary#The_500-character_limit
        throw new GadgetApiRequestException('summary_too_large', 400);
    }

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
