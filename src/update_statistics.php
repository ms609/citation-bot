<?php

declare(strict_types=1);

if (file_exists(dirname(__DIR__) . '/env.php')) {
    /** @psalm-suppress MissingFile */
    include_once dirname(__DIR__) . '/env.php';
}
require_once __DIR__ . '/includes/setup.php';

set_time_limit(120);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(0);
}

// CLI options
$dry_run = false;
$window_hours = 24;
$stats_user = 'Citation_bot';
$stats_page = STATISTICS_PAGE;

foreach ($argv ?? [] as $arg) {
    if ($arg === '--dry-run' || $arg === '--dry_run') {
        $dry_run = true;
    } elseif (str_starts_with($arg, '--hours=')) {
        $val = parse_decimal_integer(mb_substr($arg, 8));
        if ($val !== null && $val >= 1 && $val <= 168) {
            $window_hours = $val;
        }
    } elseif (str_starts_with($arg, '--user=')) {
        $val = mb_trim(mb_substr($arg, 7));
        if ($val !== '') {
            $stats_user = $val;
        }
    } elseif (str_starts_with($arg, '--page=')) {
        $val = mb_trim(mb_substr($arg, 7));
        if ($val !== '') {
            $stats_page = $val;
        }
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "Usage: php src/update_statistics.php [--dry-run] [--hours=N] [--user=NAME] [--page=Title]\n";
        echo "  --dry-run   Do not write to wiki, just print wikitext and counts\n";
        echo "  --hours=N   Window in hours (default 24, max 168)\n";
        echo "  --user=NAME User to query (default Citation_bot)\n";
        echo "  --page=T    Statistics page title (default 'User:Citation bot/statistics')\n";
        exit(0);
    }
}

$api = new WikipediaBot();

if (!HTML_OUTPUT) {
    echo "Fetching contribs for {$stats_user} in last {$window_hours}h...\n";
}

$edits = WikipediaBot::fetch_user_contribs($stats_user, $window_hours);
if ($edits === null) {
    report_warning('Could not fetch a complete statistics window.');
    exit(1);
}
$total = count($edits);
$counts = statistics_aggregate($edits);
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$wikitext = statistics_generate_wikitext($counts, $total, $now, $window_hours);

if (!HTML_OUTPUT) {
    echo "Total edits in window: {$total}\n";
    foreach ($counts as $ucb => $num) {
        $pct = $total > 0 ? round(($num / $total) * 100, 1) : 0;
        echo "  {$ucb}: {$num} ({$pct}%)\n";
    }
    echo "\n--- Wikitext preview (first 2000 chars) ---\n";
    echo mb_substr($wikitext, 0, 2000), "\n";
    echo "--- end preview ---\n";
}

if ($dry_run) {
    echo "\nDry-run: not writing to {$stats_page}\n";
    exit(0);
}

// Fetch current page text to avoid no-op writes and to get base timestamp
$current = WikipediaBot::get_a_page($stats_page);
if (mb_trim($current) === mb_trim($wikitext)) {
    echo "Statistics page unchanged – no write needed.\n";
    exit(0);
}

$summary = "Update statistics: {$total} edits in last 24 hours";
if ($window_hours !== 24) {
    $summary = "Update statistics: {$total} edits in last {$window_hours} hours";
}
$summary .= " | #UCB_Statistics";

$parsed = page_details_from_api_response(WikipediaBot::read_details($stats_page));
if ($parsed === null) {
    report_warning('Unable to read statistics page metadata.');
    exit(1);
}
[$details, $read_at] = $parsed;
if (isset($details->missing)) {
    $ok = $api->write_statistics_page($stats_page, $wikitext, $summary, true);
} elseif (($details->lastrevid ?? 0) > 0 && $read_at !== '') {
    $ok = $api->write_page($stats_page, $wikitext, $summary, $details->lastrevid, $read_at);
    if ($ok && $api->last_write_was_skipped()) {
        report_warning('Statistics page changed before it could be updated.');
        exit(1);
    }
} else {
    report_warning('Unable to determine the current statistics page revision.');
    exit(1);
}

if ($ok) {
    echo "Successfully updated {$stats_page}\n";
    exit(0);
}
report_warning('Failed to update ' . echoable($stats_page));
exit(1);
