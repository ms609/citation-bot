<?php

declare(strict_types=1);

const BORING_STUFF = ["boring", "removed", "added", "changed", "subsubitem", "subitem"];

require_once 'constants.php';   // @codeCoverageIgnore

function html_echo(string $text, string $alternate_text=''): void {
    if (!TRAVIS || defined("TRAVIS_PRINT")) {
        echo HTML_OUTPUT ? $text : $alternate_text; // @codeCoverageIgnore
    }
}

function user_notice(string $symbol, string $class, string $text): void {
    if (!TRAVIS || defined("TRAVIS_PRINT")) {
        // @codeCoverageIgnoreStart
        if (defined('BIG_JOB_MODE') && in_array($class, BORING_STUFF, true)) {
            echo '.'; // Echo something to keep the code alive, but not so much to overfill the cache
            return;
        }
        // These are split over three lines to avoid creating a single long string during error conditions - which could blow out the memory
        echo "\n ", (HTML_OUTPUT ? "<span class='{$class}'>" : ""), $symbol;
        if (defined('BIG_JOB_MODE') && strlen($text) > 1000) { // No one looks at this anyway - long ones are often URLs in zotero errors
            echo "HUGE amount of text NOT printed";
            bot_debug_log("HUGE amount of text NOT printed.  Here is a bit: " .  substr($text, 0, 500));
        } else {
            echo $text;
        }
        echo HTML_OUTPUT ? "</span>" : "";
        // @codeCoverageIgnoreEnd
    }
}

function report_phase(string $text): void {
    user_notice("\n>", "phase", $text);
}
function report_action(string $text): void {
    user_notice(">", "subitem", $text);
}
function report_info(string $text): void {
    user_notice("  >", "subsubitem", $text);
}
function report_inaction(string $text): void {
    user_notice("  .", "boring", $text);
}
function report_warning(string $text): void {
    user_notice("  !", "warning", $text);
}
function report_modification(string $text): void {
    user_notice("  ~", "changed", $text);
}
function report_add(string $text): void {
    user_notice("  +", "added", $text);
}
function report_forget(string $text): void {
    user_notice("  -", "removed", $text);
}

function report_inline(string $text): void {
    if ((!TRAVIS && defined('BIG_JOB_MODE')) || defined("TRAVIS_PRINT")) {
        echo " ", $text;   // @codeCoverageIgnore
    }
}
// call report_warning to give users a message before we die
function report_error(string $text): never {
    // @codeCoverageIgnoreStart
    if (TRAVIS) {
        trigger_error($text);  // Stop this test now
    } elseif (function_exists('bot_debug_log')) {
        bot_debug_log($text);  // Code logfile, if defined
        report_warning($text); // To the user
    } else {
        report_warning($text); // To the user
        trigger_error($text);  // System Logfile
    }
    exit;  // @codeCoverageIgnoreEnd
}

function report_minor_error(string $text): void {  // For things we want to error in tests, but continue on Wikipedia
    // @codeCoverageIgnoreStart
    if (!HTML_OUTPUT) { // command line and TRAVIS
        report_error($text);
    } else {
        bot_debug_log($text);
        report_warning($text);
    }
    // @codeCoverageIgnoreEnd
}

function quietly(callable $function, string $text): void { // Stuff suppressed when running on the command line
    if (HTML_OUTPUT || TRAVIS) {
        $function($text);
    }
}

// special flags to mark this function as making all untrustworthy input magically safe to output
function echoable(?string $string): string {
 /**
   * @psalm-taint-escape html
   * @psalm-taint-escape has_quotes
   */
    $string = (string) $string;
     return HTML_OUTPUT ? htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401) : $string;
}

function pubmed_link(string $identifier, string $pm): string {
    return HTML_OUTPUT
       ? '<a href="https://www.ncbi.nlm.nih.gov/pubmed/' . urlencode($pm) . '" target="_blank" aria-label="Open PMID in new window">' . strtoupper($identifier) . ' ' . echoable($pm) . "</a>"   // @codeCoverageIgnore
       : strtoupper($identifier) . ' ' . echoable($pm);
}

function bibcode_link(string $id): string {
    return HTML_OUTPUT
    ? '<a href="https://ui.adsabs.harvard.edu/abs/' . urlencode($id) . '" target="_blank" aria-label="Open bibcode in new window">' . echoable($id) . '</a>'   // @codeCoverageIgnore
    : echoable($id);
}

function doi_link(string $doi): string {
    return HTML_OUTPUT
    ? '<a href="https://dx.doi.org/' . doi_encode(urldecode($doi)) . '" target="_blank" aria-label="Open DOI in new window">' . echoable($doi) . '</a>'      // @codeCoverageIgnore
    : echoable($doi);
}

function jstor_link(string $id): string {
    return HTML_OUTPUT
    ? '<a href="https://www.jstor.org/citation/ris/' . urlencode($id) . '" target="_blank" aria-label="Open JSTOR in new window">JSTOR ' . echoable($id) . '</a>'    // @codeCoverageIgnore
    : "JSTOR " . echoable($id);
}

function wiki_link(string $page): string {
    return HTML_OUTPUT
    ? '<a href="' . WIKI_ROOT . '?title=' . urlencode(str_replace(' ', '_', $page)) . '" target="_blank" aria-label="Open wiki in new window">Wikipedia page: ' . echoable($page) . '</a>'    // @codeCoverageIgnore
    : "Wikipedia page : " . echoable($page);
}
