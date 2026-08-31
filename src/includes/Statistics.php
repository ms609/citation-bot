<?php

declare(strict_types=1);

/**
 * Statistics helpers for User:Citation bot/statistics
 *
 * Pure functions – no network I/O – so they are easy to unit-test.
 * Network fetching lives in WikipediaBot::user_contribs_*.
 */

const STATISTICS_PAGE = 'User:Citation bot/statistics';
const STATISTICS_UNTAGGED_LABEL = 'Untagged';

const KNOWN_UCB_TYPES = [
    '#UCB_automated_tools',
    '#UCB_toolbar',
    '#UCB_template',
    '#UCB_webform',
    '#UCB_Headbomb',
    '#UCB_Smith609',
    '#UCB_arXiv',
    '#UCB_Other',
    '#UCB_Category',
    '#UCB_Gadget',
    '#UCB_CommandLine',
    '#UCB_webform_linked',
    '#UCB_Testing',
    '#UCB_Statistics',
];

/**
 * Extract the first #UCB_* token from an edit summary comment.
 * Returns the token (e.g. "#UCB_toolbar") or STATISTICS_UNTAGGED_LABEL
 * when no tag is present. Matching is case-sensitive (tags are canonical).
 */
function statistics_ucb_from_comment(string $comment): string {
    if (preg_match('~#UCB_[A-Za-z0-9_]+~', $comment, $m)) {
        return $m[0];
    }
    return STATISTICS_UNTAGGED_LABEL;
}

/**
 * Aggregate an array of contribs (each with at least ->comment)
 * into counts keyed by UCB type.
 *
 * @param array<object> $contribs
 * @return array<string,int> e.g. ["#UCB_toolbar"=>12, "Untagged"=>3]
 */
function statistics_aggregate(array $contribs): array {
    $counts = [];
    foreach ($contribs as $edit) {
        $comment = '';
        if (is_object($edit) && isset($edit->comment) && is_string($edit->comment)) {
            $comment = $edit->comment;
        } elseif (is_array($edit) && isset($edit['comment']) && is_string($edit['comment'])) {
            $comment = $edit['comment'];
        }
        $ucb = statistics_ucb_from_comment($comment);
        if (!isset($counts[$ucb])) {
            $counts[$ucb] = 0;
        }
        $counts[$ucb]++;
    }
    return $counts;
}

/**
 * Parse a decoded usercontribs API response into an array of edit
 * objects and an optional continue token.
 *
 * Expected shape: { query: { usercontribs: [...] }, continue: { uccontinue: "..." } }
 *
 * @return array{edits: array<object>, continue: string|null}|null null on malformed response
 */
function statistics_parse_contribs_response(mixed $response): ?array {
    if (!is_object($response) || !isset($response->query) || !is_object($response->query)) {
        return null;
    }
    if (!isset($response->query->usercontribs) || !is_array($response->query->usercontribs)) {
        return null;
    }
    /** @var array<object> $edits */
    $edits = [];
    foreach ($response->query->usercontribs as $edit) {
        if (is_object($edit)) {
            $edits[] = $edit;
        }
    }
    $continue = null;
    if (isset($response->continue) && is_object($response->continue)
        && isset($response->continue->uccontinue) && is_string($response->continue->uccontinue)
        && $response->continue->uccontinue !== '') {
        $continue = $response->continue->uccontinue;
    }
    return ['edits' => $edits, 'continue' => $continue];
}

/**
 * Generate wikitext for the statistics page.
 *
 * @param array<string,int> $counts from statistics_aggregate()
 * @param int $total total edits in window (sum of counts)
 * @param DateTimeImmutable $now reference time (UTC) – printed in header
 * @param int $window_hours window size for display (e.g. 24)
 * @return string wikitext
 */
function statistics_generate_wikitext(array $counts, int $total, DateTimeImmutable $now, int $window_hours = 24): string {
    $now_utc = $now->setTimezone(new DateTimeZone('UTC'));
    $stamp = $now_utc->format('Y-m-d H:i:s \U\T\C');
    // Sort counts descending for readability; keep untagged at end if tied? Just sort by count desc then name asc
    uksort($counts, static function (string $a, string $b) use ($counts): int {
        $ca = $counts[$a];
        $cb = $counts[$b];
        if ($ca !== $cb) {
            return $cb <=> $ca;
        }
        return strcmp($a, $b);
    });
    // Build wikitext – use the project's verbose, explicit style
    $out = '';
    $out .= "This page shows the number of edits made by [[User:Citation bot|Citation bot]] ";
    $out .= "in the last {$window_hours} hours (updated once per day).\n\n";
    $out .= "''Last updated: {$stamp}''\n\n";
    if ($total === 0) {
        $out .= "No edits were made in the last {$window_hours} hours.\n";
        return $out;
    }
    $out .= "Total edits in last {$window_hours} hours: '''{$total}'''\n\n";
    $out .= "{| class=\"wikitable sortable\"\n";
    $out .= "! UCB type !! Edits !! Percentage\n";
    foreach ($counts as $ucb => $num) {
        $pct = $total > 0 ? round(($num / $total) * 100, 1) : 0;
        // Format percentage: one decimal, but strip trailing .0 for neatness
        $pct_str = mb_rtrim(mb_rtrim(number_format($pct, 1, '.', ''), '0'), '.');
        $safe_ucb = str_replace('|', '&#124;', $ucb);
        $out .= "|-\n";
        $out .= "| <code>" . $safe_ucb . "</code> || {$num} || {$pct_str}%\n";
    }
    $out .= "|-\n";
    $out .= "! Total || {$total} || 100%\n";
    $out .= "|}\n\n";
    $out .= "<small>Breakdown by <code>#UCB</code> tag in edit summary. ";
    $out .= "\"" . STATISTICS_UNTAGGED_LABEL . "\" counts edits with no <code>#UCB_</code> tag ";
    $out .= "(typically test edits to [[User:Blocked Testing Account/writetest]]). ";
    $out .= "Data via <code>list=usercontribs</code> for user <code>Citation_bot</code>.</small>\n";
    return $out;
}
