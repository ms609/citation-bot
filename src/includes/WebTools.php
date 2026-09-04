<?php

declare(strict_types=1);

require_once __DIR__ . '/big_jobs.php';      // @codeCoverageIgnore
require_once __DIR__ . '/RequestRateLimit.php'; // @codeCoverageIgnore

/**
 * Users allowed to exceed the web page-count limit (MAX_PAGES).
 * Shared by the category and linked-pages entry points.
 */
const DEV_USERS = [
    'AManWithNoPlan',
    'Redalert2fan',
];

/**
 * Only on webpage
 */

/**
 * Run one page's work behind a Throwable boundary.
 *
 * A bad citation, malformed external response, or PHP engine error on one page
 * should not terminate a multi-page run.
 *
 * @param string $page_title
 * @param callable(): (bool|null) $operation
 * @return bool|null true when the page changed, false when it did not, null on failure
 */
function run_page_with_exception_boundary(string $page_title, callable $operation): ?bool {
    try {
        return $operation();
    } catch (Throwable $exception) {
        bot_debug_log(
            'Page processing failure for "' . $page_title . '": ' .
            $exception::class . ': ' . $exception->getMessage()
        );
        report_warning('Unexpected error while processing page "' . echoable($page_title) . '". Skipping this page.');
        return null;
    }
}

/**
 * Run a write once, then retry it up to $max_retries times.
 *
 * @param callable(): bool $operation
 */
function run_write_with_retries(callable $operation, int $max_retries): bool {
    $max_retries = max(0, $max_retries);
    for ($attempt = 0; $attempt <= $max_retries; ++$attempt) {
        if ($operation()) {
            return true;
        }
    }
    return false;
}

/**
 * Convert the legacy write API's two signals into the page runner's tri-state.
 *
 * A successful-but-skipped write (for example an edit conflict detected before
 * submission) is unchanged, while an exhausted retry sequence is a failure.
 */
function page_result_from_write(bool $write_succeeded, bool $write_skipped): ?bool {
    if (!$write_succeeded) {
        return null;
    }
    return !$write_skipped;
}

/**
 * Reject page-specific bad input without aborting an otherwise valid batch.
 *
 * @param array<mixed> $pages
 * @return array<string>
 */
function filter_runnable_page_titles(array $pages): array {
    $filtered = [];
    foreach ($pages as $page_title) {
        if (!is_string($page_title)) {
            report_warning('Skipping non-string page title.');
            continue;
        }
        if (!mb_check_encoding($page_title, 'UTF-8')) {
            report_warning('Skipping page title with invalid UTF-8.');
            continue;
        }
        if (mb_trim($page_title) === '') {
            continue;
        }
        if (mb_strlen($page_title, '8bit') > 255) {
            report_warning(
                'Skipping page name "' . echoable(mb_substr($page_title, 0, 80)) .
                '…" because it exceeds 255 bytes.'
            );
            continue;
        }
        $filtered[] = $page_title;
    }
    return array_values(array_unique($filtered, SORT_STRING));
}

/**
 * Bound the raw page-list string before explode() duplicates its memory.
 * N titles of at most 255 bytes plus N-1 separators fit in N*256 bytes.
 */
function page_batch_input_within_limit(string $pages, ?int $max_pages = null): bool {
    $effective_max = $max_pages ??
        (defined('MAX_PAGES_OVERRIDE') ? (int) MAX_PAGES_OVERRIDE : (int) MAX_PAGES);
    if ($effective_max < 1) {
        return false;
    }
    $max_bytes = $effective_max * 256;
    return mb_strlen($pages, '8bit') <= $max_bytes;
}

/**
 * Build the edit summary suffix for a category run.
 *
 * Scope wins over caller for human-launched runs: category runs are tagged
 * #UCB_Category (even when launched from the webform with edit=webform) so
 * they stay countable separately from single-page #UCB_webform runs in
 * statistics. Programmatic callers take priority: an explicit edit=template
 * or edit=automated_tools is tagged with its own token so those runs remain
 * attributable to the template or automated-tool caller. Any other caller
 * tag (toolbar, webform, etc.) is discarded for category scope, matching the
 * linked-pages precedent.
 */
function category_edit_summary_end(string $username, string $category, bool $has_override = false, bool $is_dev_run = false, ?string $edit = null): string {
    $edit_summary_end = "| Suggested by " . $username . " | [[Category:{$category}]] ";
    if ($edit === 'template') {
        $edit_summary_end .= "| #UCB_template ";
    } elseif ($edit === 'automated_tools') {
        $edit_summary_end .= "| #UCB_automated_tools ";
    } else {
        $edit_summary_end .= "| #UCB_Category ";
    }
    if ($has_override) {
        if ($is_dev_run) {
            $edit_summary_end .= "| Developer - max category limit override enabled ";
        } else {
            $edit_summary_end .= "| Whitelisted category ";
        }
    }
    return $edit_summary_end;
}

/**
 * Whether the given user may exceed the web page-count limit (MAX_PAGES)
 * on linked-pages runs, mirroring the category entry point's DEV_USERS
 * override. Note this is separate from the linked-pages User: restriction,
 * which uses its own wider allow-list.
 */
function linked_pages_should_override_limit(string $username): bool {
    return in_array($username, DEV_USERS, true);
}

/**
 * Build the edit summary suffix for a single/batch page run.
 *
 * Only generic caller tags are produced. Personal and unused tags
 * (Headbomb, Smith609, arXiv) are intentionally not produced and fall
 * through to #UCB_Other, since anyone could otherwise mint them via
 * ?edit= and fake personal attribution. They stay recognized in
 * KNOWN_UCB_TYPES so historic edits still classify.
 */
function process_page_edit_summary_end(string $username, bool $is_html_output, ?string $edit): string {
    if ($is_html_output) {
        $edit_summary_end = "| Suggested by " . $username . " ";
    } else {
        $edit_summary_end = ""; // Command line edits as the person
    }
    if (!empty($edit)) {
        if ($edit === 'automated_tools') {
            $edit_summary_end .= "| #UCB_automated_tools ";
        } elseif ($edit === 'toolbar') {
            $edit_summary_end .= "| #UCB_toolbar ";
        } elseif ($edit === 'template') {
            $edit_summary_end .= "| #UCB_template ";
        } elseif ($edit === 'webform') {
            $edit_summary_end .= "| #UCB_webform ";
        } else {
            $edit_summary_end .= "| #UCB_Other ";
        }
    } else {
        if ($is_html_output) {
            $edit_summary_end .= "| #UCB_webform ";
        } else {
            $edit_summary_end .= "| #UCB_CommandLine ";
        }
    }
    return $edit_summary_end;
}

/**
 * Map an ?edit= request-source value to the big-run gate activation type.
 *
 * Mirrors process_page_edit_summary_end's tag mapping so the token weight
 * matches the edit-summary tag: the default webform POST (no edit parameter)
 * is #UCB_webform, not #UCB_Other. Unknown non-empty values fall through to
 * #UCB_Other. 'testing' is deliberately NOT honored here: the edit parameter
 * is user-controlled, so granting it the testing exemption (which bypasses
 * the gate entirely) would let anyone mint an unlimited un-gated run, the
 * same reason process_page_edit_summary_end refuses deprecated tags.
 */
function big_run_type_from_edit(?string $edit): string {
    if ($edit === null || $edit === '') {
        return 'webform';
    }
    if (in_array($edit, ['webform', 'automated_tools', 'toolbar', 'template'], true)) {
        return $edit;
    }
    return 'other';
}

/**
 * Whether a run must pass through the big-run gate.
 *
 * Singles (≤ BIG_RUN_PAGE_THRESHOLD pages), trusted operators (DEV_USERS) and
 * testing runs are never gated.
 */
function big_run_gate_decision(int $page_count, string $run_type, string $username): bool {
    if ($page_count <= BIG_RUN_PAGE_THRESHOLD) {
        return false;
    }
    if (in_array($username, DEV_USERS, true)) {
        return false;
    }
    if ($run_type === 'testing') {
        return false;
    }
    return true;
}

/**
 * Humanize a wait in seconds, applying a 20% buffer so users do not retry too early.
 */
function big_run_humanize_wait(int $seconds): string {
    $buffered = max(1, (int) ceil($seconds * 1.2));
    if ($buffered < 60) {
        return (string) $buffered . ($buffered === 1 ? ' second' : ' seconds');
    }
    $minutes = max(1, (int) round($buffered / 60));
    return (string) $minutes . ($minutes === 1 ? ' minute' : ' minutes');
}

/**
 * Build the busy-page message for a deferred big run.
 */
function big_run_busy_page_message(string $reason, ?int $active_count, ?int $retry_after): string {
    if ($reason === 'tokens') {
        return 'Citation Bot\'s big-run quota is currently exhausted. Please try again in about ' .
            big_run_humanize_wait($retry_after ?? 0) . '.';
    }
    if ($reason === 'retry_later') {
        return 'Citation Bot could not check big-run availability right now. Please try again shortly.';
    }
    return 'Citation Bot is currently at capacity with other big runs (' .
        (string) ($active_count ?? 0) . ' in progress). Please try again shortly.';
}

/**
 * Render the busy page for a deferred big run (message + footer).
 */
function big_run_render_busy_page(string $reason, ?int $active_count, ?int $retry_after): void {
    report_warning(big_run_busy_page_message($reason, $active_count, $retry_after));
    bot_html_footer();
}

/**
 * Gate a big run: admit it (and register release) or render the busy page.
 *
 * Callers must only invoke this when HTML_OUTPUT is true (web requests), so the
 * authenticated username is always available.
 *
 * @param int $page_count Effective (post-filter) page count.
 * @param string $run_type Activation type from big_run_type_from_edit().
 * @param string $username Authenticated Wikipedia username.
 */
function gate_big_run(int $page_count, string $run_type, string $username, ?string $base_directory = null, ?float $now = null): void {
    if (!HTML_OUTPUT) {
        return;
    }
    if (!big_run_gate_decision($page_count, $run_type, $username)) {
        return;
    }

    $result = big_run_try_acquire($page_count, $run_type, $base_directory, $now);
    if ($result[0]) {
        $entry_id = $result[2];
        if ($entry_id !== null) {
            register_shutdown_function('big_run_release', $entry_id, $base_directory);
        }
        return;
    }

    big_run_render_busy_page((string) ($result[3] ?? 'big_full'), $result[4], $result[1]);
    exit(0); // @codeCoverageIgnore
}

/**
 * @codeCoverageIgnore
 * @param array<string> $pages_in_category
 */
function edit_a_list_of_pages(array $pages_in_category, WikipediaBot $api, string $edit_summary_end, string $run_type = 'other'): void {
    $final_edit_overview = "";
    $pages_in_category = filter_runnable_page_titles($pages_in_category);
    if (empty($pages_in_category)) {
        report_warning('No links to expand found');
        bot_html_footer();
        return;
    }
    $total = count($pages_in_category);
    $effective_max = defined('MAX_PAGES_OVERRIDE') ? MAX_PAGES_OVERRIDE : MAX_PAGES;
    if ($total > $effective_max) {
        report_warning('Number of links is huge. Cancelling run. Maximum size is ' . (string) $effective_max);
        bot_html_footer();
        return;
    }
    if (HTML_OUTPUT && $total > BIG_RUN_PAGE_THRESHOLD) {
        gate_big_run($total, $run_type, $api->get_the_user());
        // Only reached if the run was admitted; a deferred run exits above.
        report_warning('Reminder: the bot will edit these pages automatically. You are responsible for checking its edits — please review the changes it makes.');
    }
    big_jobs_check_overused($total);

    $page = new Page();
    $done = 0;
    $pages_changed = 0;   // Pages successfully processed where expand_text() returned true
    $pages_unchanged = 0; // Pages where no edit was made: no changes needed, blank, protected, redirect, etc.
    $pages_failed = 0;    // Pages skipped after an unexpected Throwable
    /** @var array<string> $pages_edit_conflict */
    $pages_edit_conflict = [];

    foreach ($pages_in_category as $page_title) {
        flush(); // Only call to flush in normal code, since calling flush breaks headers and sessions
        big_jobs_check_killed();
        $done++;
        $page_result = run_page_with_exception_boundary(
            $page_title,
            function () use (
                $page,
                $page_title,
                $api,
                $edit_summary_end,
                $total,
                $done,
                &$final_edit_overview,
                &$pages_edit_conflict
            ): ?bool {
                if (mb_strpos($page_title, 'Wikipedia:Requests') === false && $page->get_text_from($page_title) && $page->expand_text()) {
                    if (SAVETOFILES_MODE) {
                        // Sanitize file name by replacing characters that are not allowed on most file systems to underscores, and also replace path characters
                        // And add .md extension to avoid troubles with devices such as 'con' or 'aux'
                        $filename = preg_replace('~[\/\\:*?"<>|\s]~', '_', $page_title) . '.md';
                        report_phase("Saving to file " . echoable($filename));
                        $body = $page->parsed_text();
                        $bodylen = mb_strlen($body, '8bit'); // byte count, not character count
                        if (file_put_contents($filename, $body) === $bodylen) {
                            report_phase("Saved to file " . echoable($filename));
                        } else {
                            report_warning("Save to file failed.");
                        }
                        unset($body);
                    } else {
                        report_phase("Writing to " . echoable($page_title) . '... ');
                        if ($total === 1) {
                            $edit_sum = $edit_summary_end;
                        } else {
                            $edit_sum = $edit_summary_end . (string) $done . '/' . (string) $total . ' ';
                        }
                        $write_succeeded = run_write_with_retries(
                            static fn (): bool => $page->write($api, $edit_sum),
                            MAX_TRIES
                        );
                        $write_result = page_result_from_write($write_succeeded, $api->last_write_was_skipped());
                        if ($write_result === true) {
                            $last_rev = WikipediaBot::get_last_revision($page_title);
                            html_echo(
                            "\n  <a href=\"" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&amp;diff=prev&amp;oldid="
                            . $last_rev . "\">diff</a>" .
                            " | <a href=\"" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&amp;action=history\">history</a>",
                            "\n" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid=" . $last_rev . "\n");
                            $final_edit_overview .=
                                "\n [ <a href=\"" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&amp;diff=prev&amp;oldid="
                            . $last_rev . "\">diff</a>" .
                            " | <a href=\"" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&amp;action=history\">history</a> ] " . "<a href=\"" . WIKI_ROOT . "?title=" . urlencode($page_title) . "\">" . echoable($page_title) . "</a>";
                        } elseif ($write_result === false) {
                            report_warning("Write skipped because the page changed while Citation Bot was working.");
                            $final_edit_overview .= "\n Write skipped.           " . "<a href=\"" . WIKI_ROOT . "?title=" . urlencode($page_title) . "\">" . echoable($page_title) . "</a>";
                            $pages_edit_conflict[] = $page_title;
                        } else {
                            report_warning("Write failed.");
                            $final_edit_overview .= "\n Write failed.            " . "<a href=\"" . WIKI_ROOT . "?title=" . urlencode($page_title) . "\">" . echoable($page_title) . "</a>";
                        }
                        return $write_result;
                    }
                    // SAVETOFILES_MODE successfully produced (or attempted) the changed output.
                    return true;
                }

                report_phase($page->parsed_text() ? "No changes required. \n\n      # # # " : "Blank page. \n\n      # # # ");
                $final_edit_overview .= "\n No changes needed. " . "<a href=\"" . WIKI_ROOT . "?title=" . urlencode($page_title) . "\">" . echoable($page_title) . "</a>";
                return false;
            }
        );

        if ($page_result === true) {
            $pages_changed++;
        } elseif ($page_result === false) {
            $pages_unchanged++;
        } else {
            $pages_failed++;
            $final_edit_overview .= "\n Processing failed. " . "<a href=\"" . WIKI_ROOT . "?title=" . urlencode($page_title) . "\">" . echoable($page_title) . "</a>";
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
        echo "\n Done all ", (string) $total, " pages: ", (string) $pages_changed, " changed, ",
             (string) $pages_unchanged, " unchanged, ", (string) $pages_failed, " failed. \n  # # # \n", $final_edit_overview;
        if (!empty($pages_edit_conflict)) {
            $retry_count = (string) count($pages_edit_conflict);
            if (HTML_OUTPUT) {
                html_echo(
                    "\n\n---\nPages skipped due to edit conflict — please try again (" . $retry_count . "):\n",
                    "\n\n---\nPages skipped due to edit conflict — please try again (" . $retry_count . "):\n"
                );
                foreach ($pages_edit_conflict as $conflict_title) {
                    html_echo(
                        "\n* " . echoable($conflict_title),
                        "\n* " . $conflict_title
                    );
                }
                echo "\n";
            } else {
                echo "\n\n---\nPages skipped due to edit conflict — please try again (", $retry_count, "):\n";
                foreach ($pages_edit_conflict as $conflict_title) {
                    echo "  * ", $conflict_title, "\n";
                }
            }
        }
    } else {
        echo "\n Done with page.";
        if (!empty($pages_edit_conflict)) {
            if (HTML_OUTPUT) {
                html_echo(
                    "\n\nPage skipped due to edit conflict — please try again: " . echoable($pages_edit_conflict[0]),
                    "\n\nPage skipped due to edit conflict — please try again: " . $pages_edit_conflict[0]
                );
            } else {
                echo "\n\nPage skipped due to edit conflict — please try again: ", $pages_edit_conflict[0], "\n";
            }
        }
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
    '  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />', "\n",
    '  <title>Citation Bot: running</title>', "\n",
    '  <link rel="copyright" type="text/html" href="https://www.gnu.org/licenses/gpl-3.0" />', "\n",
    '  <link rel="stylesheet" type="text/css" href="assets/results.css" />', "\n",
    ' </head>', "\n",
    ' <body>', "\n",
    '  <a href="#main-content" class="skip-link">Skip to main content</a>', "\n",
    '  <header>', "\n",
    '   <p>Follow Citation bots progress below.</p>', "\n",
    '   <p>', "\n",
    '    <a href="https://en.wikipedia.org/wiki/User:Citation_bot/use" target="_blank" rel="noopener noreferrer" title="Using Citation Bot" aria-label="Using Citation Bot (opens new window)">How&nbsp;to&nbsp;Use&nbsp;/&nbsp;Tips&nbsp;and&nbsp;Tricks</a> |', "\n",
    '    <a href="https://en.wikipedia.org/wiki/User_talk:Citation_bot" title="Report bugs at Wikipedia" target="_blank" rel="noopener noreferrer" aria-label="Report bugs at Wikipedia (opens new window)">Report&nbsp;bugs</a> |', "\n",
    '    <a href="https://github.com/ms609/citation-bot" target="_blank" rel="noopener noreferrer" title="GitHub repository"  aria-label="GitHub repository (opens new window)">Source&nbsp;code</a>', "\n",
    '   </p>', "\n",
    '  </header>', "\n",
    '  <main id="main-content">', "\n",
    '   <h1 class="sr-only">Citation Bot progress</h1>', "\n",
    '  <pre id="botOutput" aria-label="Bot progress output">', "\n";
    if (ini_get('pcre.jit') === '0') {
        report_warning('PCRE JIT Disabled');
    }
}

/**
 * @codeCoverageIgnore
 */
function bot_html_footer(): void {
    if (HTML_OUTPUT) {
        echo '</pre></main><footer><a href="./" title="Use Citation Bot again" aria-label="Use Citation Bot again (return to main page)">Edit another page</a>?</footer></body></html>'; // @codeCoverageIgnore
    }
    echo "\n";
}
