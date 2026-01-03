<?php

declare(strict_types=1);

/**
 * Only on webpage
 */

/**
 * @codeCoverageIgnore
 * @param array<string> $pages_in_category
 */
function edit_a_list_of_pages(array $pages_in_category, WikipediaBot $api, string $edit_summary_end): void {
    $final_edit_overview = "";
    // Remove pages with blank as the name, if present
    $key = array_search("", $pages_in_category);
    if ($key !== false) {
        unset($pages_in_category[$key]);
    }
    if (empty($pages_in_category)) {
        report_warning('No links to expand found');
        bot_html_footer();
        return;
    }
    $total = count($pages_in_category);
    if ($total > MAX_PAGES) {
        report_warning('Number of links is huge. Cancelling run. Maximum size is ' . (string) MAX_PAGES);
        bot_html_footer();
        return;
    }
    big_jobs_check_overused($total);

    $page = new Page();
    $done = 0;

    foreach ($pages_in_category as $page_title) {
        flush(); // Only call to flush in normal code, since calling flush breaks headers and sessions
        big_jobs_check_killed();
        $done++;
        if (mb_strpos($page_title, 'Wikipedia:Requests') === false && $page->get_text_from($page_title) && $page->expand_text()) {
            if (SAVETOFILES_MODE) {
                // Sanitize file name by replacing characters that are not allowed on most file systems to underscores, and also replace path characters
                // And add .md extension to avoid troubles with devices such as 'con' or 'aux'
                $filename = preg_replace('~[\/\\:*?"<>|\s]~', '_', $page_title) . '.md';
                report_phase("Saving to file " . echoable($filename));
                $body = $page->parsed_text();
                $bodylen = mb_strlen($body);
                if (file_put_contents($filename, $body) === $bodylen) {
                    report_phase("Saved to file " . echoable($filename));
                } else {
                    report_warning("Save to file failed.");
                }
                unset($body);
            } else {
                report_phase("Writing to " . echoable($page_title) . '... ');
                $attempts = 0;
                if ($total === 1) {
                    $edit_sum = $edit_summary_end;
                } else {
                    $edit_sum = $edit_summary_end . (string) $done . '/' . (string) $total . ' ';
                }
                while (!$page->write($api, $edit_sum) && $attempts < MAX_TRIES) {
                    ++$attempts;
                }
                if ($attempts < MAX_TRIES) {
                    $last_rev = WikipediaBot::get_last_revision($page_title);
                    html_echo(
                    "\n  <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid="
                    . $last_rev . ">diff</a>" .
                    " | <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&action=history>history</a>",
                    "\n" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid=" . $last_rev . "\n");
                    $final_edit_overview .=
                        "\n [ <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid="
                    . $last_rev . ">diff</a>" .
                    " | <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&action=history>history</a> ] " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
                } else {
                    report_warning("Write failed.");
                    $final_edit_overview .= "\n Write failed.            " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
                }
            }
        } else {
            report_phase($page->parsed_text() ? "No changes required. \n\n      # # # " : "Blank page. \n\n      # # # ");
                $final_edit_overview .= "\n No changes needed. " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
        }
        echo "\n";
        check_memory_usage("After writing page");
        $page->parse_text("");  // Clear variables before doing GC
        gc_collect_cycles();        // This should do nothing
        memory_reset_peak_usage();
    }
    if ($total > 1) {
        if (!HTML_OUTPUT) {
            $final_edit_overview = '';
        }
        echo "\n Done all " . (string) $total . " pages. \n  # # # \n" . $final_edit_overview;
    } else {
        echo "\n Done with page.";
    }
    bot_html_footer();
}

/**
 * @codeCoverageIgnore
 */
function bot_html_header(): void {
    if (!HTML_OUTPUT) {
        echo "\n";
        return;
    }
    echo '<!DOCTYPE html><html lang="en" dir="ltr">', "\n",
    ' <head>', "\n",
    '  <meta name="viewport" content="width=device-width, initial-scale=1.0" />', "\n",
    '  <title>Citation Bot: running</title>', "\n",
    '  <link rel="copyright" type="text/html" href="https://www.gnu.org/licenses/gpl-3.0" />', "\n",
    '  <link rel="stylesheet" type="text/css" href="assets/results.css" />', "\n",
    ' </head>', "\n",
    ' <body>', "\n",
    '  <header>', "\n",
    '   <p>Follow Citation bots progress below.</p>', "\n",
    '   <p>', "\n",
    '    <a href="https://en.wikipedia.org/wiki/User:Citation_bot/use" target="_blank" rel="noopener noreferrer" title="Using Citation Bot" aria-label="Using Citation Bot (opens new window)">How&nbsp;to&nbsp;Use&nbsp;/&nbsp;Tips&nbsp;and&nbsp;Tricks</a> |', "\n",
    '    <a href="https://en.wikipedia.org/wiki/User_talk:Citation_bot" title="Report bugs at Wikipedia" target="_blank" rel="noopener noreferrer" aria-label="Report bugs at Wikipedia (opens new window)">Report&nbsp;bugs</a> |', "\n",
    '    <a href="https://github.com/ms609/citation-bot" target="_blank" rel="noopener noreferrer" title="GitHub repository"  aria-label="GitHub repository (opens new window)">Source&nbsp;code</a>', "\n",
    '   </p>', "\n",
    '  </header>', "\n",
    '  <pre id="botOutput">', "\n";
    if (ini_get('pcre.jit') === '0') {
        report_warning('PCRE JIT Disabled');
    }
}

/**
 * @codeCoverageIgnore
 */
function bot_html_footer(): void {
    if (HTML_OUTPUT) {
        echo '</pre><footer><a href="./" title="Use Citation Bot again" aria-label="Use Citation Bot again (return to main page)">Edit another page</a>?</footer></body></html>'; // @codeCoverageIgnore
    }
    echo "\n";
}
