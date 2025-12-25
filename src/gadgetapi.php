<?php

declare(strict_types=1);

// https://en.wikipedia.org/wiki/MediaWiki:Gadget-citations.js
set_time_limit(120);

try {
    @header('Access-Control-Allow-Origin: *'); // Needed for gadget to work right
    @header('Content-Type: text/json');

    // Force fast mode for gadget to prevent timeouts
    // The gadget is designed for quick, in-browser citation expansion
    // Slow mode operations (bibcode searches, URL expansions) can exceed the 120s timeout
    unset($_GET['slow'], $_POST['slow'], $_REQUEST['slow']);

    //Set up tool requirements
    require_once __DIR__ . '/includes/setup.php';

    if (!is_string(@$_POST['text']) || !is_string(@$_POST['summary'])) {
        throw new Exception('not a string');    // @codeCoverageIgnore
    }
    $originalText = $_POST['text'];
    $editSummary = $_POST['summary'];
    unset($_GET, $_POST, $_REQUEST); // Memory minimize

    if (mb_strlen($originalText) < 6) {
        throw new Exception('tiny page');  // @codeCoverageIgnore
    } elseif (mb_strlen($originalText) > 90000) { // will probably time-out otherwise, see https://en.wikipedia.org/wiki/Special:LongPages
        throw new Exception('bogus huge page');    // @codeCoverageIgnore
    } elseif (mb_strlen($editSummary) > 1000) { // see https://en.wikipedia.org/wiki/Help:Edit_summary#The_500-character_limit
        throw new Exception('bogus summary');  // @codeCoverageIgnore
    }

    //Expand text from postvars
    $page = new Page();
    ob_start(); // For some reason this is needed sometimes
    $page->parse_text($originalText);
    $page->expand_text();
    ob_end_clean();
    $newText = $page->parsed_text();
    if ($newText === "") {
        throw new Exception('text lost');    // @codeCoverageIgnore
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

    echo (string) @json_encode($result);
} catch (Throwable) { // @codeCoverageIgnore
    @ob_end_clean(); // @codeCoverageIgnore
    @ob_end_clean(); // @codeCoverageIgnore
    @ob_end_clean(); // @codeCoverageIgnore
    // Above is paranoid panic code.    So paranoid that we even empty buffers two extra times
}
