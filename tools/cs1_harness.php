<?php

declare(strict_types=1);

/*
 * CS1 self-validation harness.
 *
 * Runs a matrix of citation templates through the bot's real expansion
 * machinery (fast and slow modes) and flags any expanded output that would
 * trigger a CS1 error, per the encodable rules from docs/CS1-error-audit.md.
 *
 * Usage (from the repository root):
 *   php tools/cs1_harness.php            # fast mode
 *   php tools/cs1_harness.php --slow     # slow mode
 *   php tools/cs1_harness.php --list     # print the matrix without running
 *
 * The harness needs no credentials: the matrix uses fabricated identifiers
 * and titles that external APIs cannot match, so the pass/fail result is
 * reproducible run-to-run when the upstream APIs respond normally.
 * Exit code is 1 if any "must-pass" case violates the CS1 rules, or if a
 * known-gap case unexpectedly resolves (an XPASS that must be converted).
 */

set_time_limit(0);

// ---------------------------------------------------------------------------
// Bootstrap (must come before any class use)
// ---------------------------------------------------------------------------

$slow_mode = in_array('--slow', $argv, true);
if (!defined('SLOW_MODE')) {
    define('SLOW_MODE', $slow_mode);
}

// Force CI semantics: report_error() triggers an error (see handler below)
// instead of silently exit(0)ing, so a mid-expansion problem becomes a
// per-case failure rather than killing the whole run.
putenv('CI=1');

// constants.php reads PUBLIC_BASE_URL at load time; provide the same
// fallbacks env.php.example would set in production.
if (!getenv('PUBLIC_BASE_URL')) {
    putenv('PUBLIC_BASE_URL=https://citations.toolforge.org');
}
if (!getenv('ALLOWED_HOSTS')) {
    putenv('ALLOWED_HOSTS=citations.toolforge.org');
}
if (!getenv('ALLOWED_ORIGINS')) {
    putenv('ALLOWED_ORIGINS=https://citations.toolforge.org');
}

// setup.php re-defines SLOW_MODE; our definition wins, and we swallow the
// redundant-redefinition warning it would otherwise emit.
set_error_handler(static function (int $severity, string $message): bool {
    return mb_strpos($message, 'already defined') !== false;
});
$old_error_level = error_reporting(E_ALL & ~E_WARNING);
if (file_exists(__DIR__ . '/../src/env.php')) {
    /** @psalm-suppress MissingFile */
    include_once __DIR__ . '/../src/env.php';
}
require_once __DIR__ . '/../src/includes/setup.php';
error_reporting($old_error_level);
restore_error_handler();

// Convert report_error()'s trigger_error() into a catchable exception.
// Errors suppressed with @ are honored (in PHP 8 the @ operator leaves
// error_reporting() set to the insuppressible levels only), matching how
// PHPUnit's own error handler behaves. Only the report_* trigger_error()
// severities (and fatal-ish errors) are escalated, so native E_WARNING,
// E_NOTICE, and E_DEPRECATED pass through and results do not vary with the
// PHP version or incidental codebase warnings.
set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    $insuppressible = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
    if ((error_reporting() & ~$insuppressible) === 0) {
        // Error was suppressed with @.
        return true;
    }
    if (($severity & (E_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR | E_USER_WARNING | E_USER_NOTICE)) !== 0) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return true;
});

// setup.php skips channel creation under CI; we need the handle ourselves.
Zotero::create_ch_zotero();

// ---------------------------------------------------------------------------
// CS1 rule data (mirrors the audit and the bot's own coupled-parameter logic)
// ---------------------------------------------------------------------------

const PERIODICAL_PARAMS = ['work', 'journal', 'newspaper', 'magazine', 'website', 'periodical', 'encyclopedia', 'encyclopaedia'];

const NON_PERIODICAL_TEMPLATES = [
    'cite book', 'cite thesis', 'cite arxiv', 'cite biorxiv', 'cite medrxiv',
    'cite document', 'cite citeseerx', 'cite ssrn', 'cite report', 'cite press release',
];

const ACCESS_PARAMS = [
    'url-access' => ['url', 'URL'],
    'chapter-url-access' => ['chapter-url', 'chapterurl'],
    'contribution-url-access' => ['contribution-url', 'contributionurl'],
    'article-url-access' => ['article-url', 'articleurl'],
    'entry-url-access' => ['entry-url', 'entryurl'],
    'section-url-access' => ['section-url', 'sectionurl'],
    'event-url-access' => ['event-url', 'eventurl'],
    'lay-url-access' => ['lay-url', 'layurl'],
    'transcript-url-access' => ['transcript-url', 'transcripturl'],
    'map-url-access' => ['map-url', 'mapurl'],
    'doi-access' => ['doi'],
];

// Alias groups for the url/archive/access-date family. The bot normally
// canonicalizes these aliases before output (so the checker usually sees the
// canonical names); the groups are defense-in-depth so the rules also hold if
// an alias form ever survives expansion.
const URL_PARAMS = ['url', 'URL'];
const CHAPTER_URL_PARAMS = ['chapter-url', 'chapterurl'];
const ARCHIVE_URL_PARAMS = ['archive-url', 'archiveurl'];
const ARCHIVE_DATE_PARAMS = ['archive-date', 'archivedate'];
const ACCESS_DATE_PARAMS = ['access-date', 'accessdate'];

const TRANS_PARAMS = [
    'trans-title' => ['title', 'script-title'],
    'trans-chapter' => ['chapter'],
    'trans-quote' => ['quote'],
    'trans-journal' => ['journal'],
    'trans-work' => ['work'],
    'trans-magazine' => ['magazine'],
    'trans-newspaper' => ['newspaper'],
    'trans-website' => ['website'],
    'trans-encyclopedia' => ['encyclopedia'],
    'trans-dictionary' => ['dictionary'],
    'trans-periodical' => ['periodical'],
    'trans-section' => ['section'],
];

const TEXT_PARAMS = ['title', 'chapter', 'series', 'journal', 'work', 'magazine', 'newspaper', 'website', 'publisher', 'edition', 'volume', 'issue', 'pages', 'quote', 'trans-title'];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function reset_bot_state(): void {
    Template::$all_templates = [];
    Template::$date_style = DateStyle::DATES_WHATEVER;
    Template::$name_list_style = VancStyle::NAME_LIST_STYLE_DEFAULT;
    Template::$page_display_authors = '';
    Zotero::block_zotero();
    AdsAbsControl::big_give_up();
    AdsAbsControl::small_give_up();
}

/** @return array<int, Template> */
function expand_citation(string $wikitext): array {
    reset_bot_state();
    $page = new Page();
    $page->parse_text($wikitext);
    $page->expand_text();
    $templates = [];
    foreach ($page->extract_object('Template') as $template) {
        $wikiname = $template->wikiname();
        if (mb_strpos($wikiname, 'cite ') === 0 || $wikiname === 'citation') {
            $templates[] = $template;
        }
    }
    return $templates;
}

function has_any(Template $template, array $names): bool {
    foreach ($names as $name) {
        if ($template->has($name)) {
            return true;
        }
    }
    return false;
}

function has_external_link(string $value): bool {
    return preg_match('~\[\s*https?://~i', $value) === 1;
}

// ---------------------------------------------------------------------------
// The CS1 checker
// ---------------------------------------------------------------------------

/** @return array<int, string> */
function check_citation(Template $template): array {
    $violations = [];
    $name = $template->wikiname();

    // R1: title (or script-title) present and non-empty.
    if (!$template->has('title') && !$template->has('script-title')) {
        $violations[] = 'title-empty: CS1 "Missing or empty |title="';
    }

    // R2: trans-<param> requires its base parameter.
    foreach (TRANS_PARAMS as $trans => $bases) {
        if ($template->has($trans) && !has_any($template, $bases)) {
            $violations[] = "orphaned $trans: CS1 \"|$trans= requires |" . $bases[0] . "=\"";
        }
    }

    // R3: <param>-access requires its base URL/DOI parameter (all alias forms).
    foreach (ACCESS_PARAMS as $access => $bases) {
        if ($template->has($access) && !has_any($template, $bases)) {
            $violations[] = "orphaned $access: CS1 \"|$access= requires |" . $bases[0] . "=\"";
        }
    }

    // R4: ISBN check-digit/prefix validation.
    if ($template->has('isbn') && !isbn_valid($template->get('isbn'))) {
        $violations[] = 'isbn-invalid: CS1 "Check |isbn= value"';
    }

    // R5: archive-url/archive-date coupling (alias spellings included).
    if (has_any($template, ARCHIVE_URL_PARAMS) && !has_any($template, ARCHIVE_DATE_PARAMS)) {
        $violations[] = 'archive-url-without-date: CS1 "|archive-url= requires |archive-date="';
    }
    if (has_any($template, ARCHIVE_DATE_PARAMS) && !has_any($template, ARCHIVE_URL_PARAMS)) {
        $violations[] = 'archive-date-without-url: CS1 "|archive-date= requires |archive-url="';
    }
    if (has_any($template, ARCHIVE_URL_PARAMS) && !has_any($template, URL_PARAMS)) {
        $violations[] = 'archive-url-without-url: CS1 "|archive-url= requires |url="';
    }

    // R6: access-date requires url or archive-url (alias spellings included).
    if (has_any($template, ACCESS_DATE_PARAMS) && !has_any($template, URL_PARAMS) && !has_any($template, ARCHIVE_URL_PARAMS)) {
        $violations[] = 'access-date-orphaned: CS1 "|access-date= requires |url="';
    }

    // R7: no bare external links in free-text parameters.
    foreach (TEXT_PARAMS as $param) {
        if ($template->has($param) && has_external_link($template->get($param))) {
            $violations[] = "external-link-in-$param: CS1 \"External link in |$param=\"";
        }
    }

    // R8: periodical parameters are ignored in templates without a periodical.
    if (in_array($name, NON_PERIODICAL_TEMPLATES, true)) {
        foreach (PERIODICAL_PARAMS as $param) {
            if ($template->has($param)) {
                $violations[] = "$param-in-$name: CS1 \"|$param= ignored\"";
            }
        }
    }

    // R9: cite web requires a url.
    if ($name === 'cite web' && !has_any($template, URL_PARAMS)) {
        $violations[] = 'cite-web-without-url: CS1 "Missing or empty |url="';
    }

    // R10: identifier templates require their identifier.
    if ($name === 'cite arxiv' && !$template->has('arxiv') && !$template->has('eprint')) {
        $violations[] = 'arxiv-required: CS1 "|arxiv= required"';
    }
    if ($name === 'cite biorxiv' && !$template->has('biorxiv')) {
        $violations[] = 'biorxiv-required: CS1 "|biorxiv= required"';
    }
    if ($name === 'cite medrxiv' && !$template->has('medrxiv')) {
        $violations[] = 'medrxiv-required: CS1 "|medrxiv= required"';
    }
    if ($name === 'cite ssrn' && !$template->has('ssrn')) {
        $violations[] = 'ssrn-required: CS1 "|ssrn= required"';
    }

    // R11: cite document requires a publisher.
    if ($name === 'cite document' && !$template->has('publisher')) {
        $violations[] = 'document-without-publisher: CS1 "Cite document requires |publisher="';
    }

    // R12: doi-broken-date requires doi.
    if ($template->has('doi-broken-date') && !$template->has('doi')) {
        $violations[] = 'doi-broken-date-orphaned: CS1 "|doi-broken-date= requires |doi="';
    }

    // R13: format parameters require their url base.
    if ($template->has('format') && !has_any($template, URL_PARAMS)) {
        $violations[] = 'format-orphaned: CS1 "|format= requires |url="';
    }
    if ($template->has('chapter-format') && !has_any($template, CHAPTER_URL_PARAMS)) {
        $violations[] = 'chapter-format-orphaned: CS1 "|chapter-format= requires |chapter-url="';
    }

    // R14: pmc-embargo-date requires pmc.
    if ($template->has('pmc-embargo-date') && !$template->has('pmc')) {
        $violations[] = 'pmc-embargo-date-orphaned: CS1 "|pmc-embargo-date= requires |pmc="';
    }

    // R15: url and title-link must not both be present.
    if (has_any($template, URL_PARAMS) && $template->has('title-link')) {
        $violations[] = 'url-title-link-conflict: CS1 "URL-wikilink conflict"';
    }

    // R16: identifier format checks (validators mirror CS1's structural rules).
    if (($template->has('arxiv') && !arxiv_id_valid($template->get('arxiv'))) ||
        ($template->has('eprint') && !arxiv_id_valid($template->get('eprint')))) {
        $violations[] = 'arxiv-malformed: CS1 "Check |arxiv= value"';
    }
    if ($template->has('pmid') && !pmid_valid($template->get('pmid'))) {
        $violations[] = 'pmid-malformed: CS1 "Check |pmid= value"';
    }
    if ($template->has('pmc') && !pmc_valid($template->get('pmc'))) {
        $violations[] = 'pmc-malformed: CS1 "Check |pmc= value"';
    }
    if ($template->has('bibcode') && !bibcode_valid($template->get('bibcode'))) {
        $violations[] = 'bibcode-malformed: CS1 "Check |bibcode= value"';
    }
    foreach (['biorxiv', 'medrxiv'] as $param) {
        if ($template->has($param)) {
            $test_value = $template->get($param);
            // Mirror the add_if_new gate: normalize the prefix before validating.
            if (mb_strpos($test_value, '10.1101/') !== 0 && mb_strpos($test_value, '10.64898/') !== 0) {
                $test_value = '10.1101/' . $test_value;
            }
            if (!rxiv_id_valid($test_value)) {
                $violations[] = 'rxiv-malformed: CS1 "Check |biorxiv=/|medrxiv= value"';
            }
        }
    }

    // R17: generic names trigger CS1 "Cite uses generic name".
    foreach (['last', 'first', 'author', 'last1', 'first1', 'author1', 'editor', 'editor1', 'editor-last', 'editor1-last', 'editor-first', 'editor1-first'] as $param) {
        if ($template->has($param) && is_generic_name($template->get($param))) {
            $violations[] = "generic-name-in-$param: CS1 \"Cite uses generic name\"";
        }
    }

    // R18: generic titles trigger CS1 "Cite uses generic title".
    if ($template->has('title') && is_generic_title($template->get('title'))) {
        $violations[] = 'generic-title: CS1 "Cite uses generic title"';
    }

    // R19: url must be structurally valid (CS1 "Check |url= value").
    if ($template->has('url') && !url_valid($template->get('url'))) {
        $violations[] = 'url-malformed: CS1 "Check |url= value"';
    }

    return $violations;
}

// ---------------------------------------------------------------------------
// The matrix
// ---------------------------------------------------------------------------

/**
 * Each case is [name, wikitext, expectation].
 * expectation 'pass' means the expanded output must satisfy every CS1 rule.
 * expectation 'gap'  means the current code is known to leave a CS1 error in
 *                    the output; reported (and tracked) but does not fail the run.
 *
 * @return array<int, array{0: string, 1: string, 2: string}>
 */
function build_matrix(): array {
    return [
        // --- cite web -> cite book, work=series routing (mandatory cases) ---
        ['Erxleben path: work=series lands in series=', '{{cite web |title=Harness paper alpha |work=Lecture Notes in Computer Science |date=2014 |isbn=978-0-19-852011-5}}', 'pass'],
        ['Najman path: title=book title kept, work=series -> series=', '{{cite web |title=Harness book title beta |work=Lecture notes in mathematics |date=2017 |isbn=978-0-19-852011-5}}', 'pass'],
        ['No-clobber: existing series= preserved', '{{cite web |title=Harness paper gamma |work=Lecture Notes in Computer Science |series=Special Series Name |date=2014 |isbn=978-0-19-852011-5}}', 'pass'],

        // --- orphaned *-access removal (merged Tier 1 fix) ---
        ['Orphaned url-access removed', '{{cite journal |title=X |journal=J |url-access=subscription}}', 'pass'],
        ['Orphaned chapter-url-access removed', '{{cite journal |title=X |journal=J |chapter-url-access=free}}', 'pass'],
        ['Orphaned contribution-url-access removed', '{{cite journal |title=X |journal=J |contribution-url-access=free}}', 'pass'],
        ['url-access kept when base uses uppercase URL alias', '{{cite web |URL=https://example.com |url-access=subscription |title=X}}', 'pass'],
        ['url-access kept when base uses un-hyphenated alias', '{{cite web |url=https://example.com |url-access=subscription |title=X}}', 'pass'],
        ['chapter-url-access kept with chapter-url base', '{{cite book |title=X |chapter-url=https://example.com |chapter-url-access=free |year=2020}}', 'pass'],
        ['uppercase URL base keeps access-date', '{{cite web |URL=https://example.com |access-date=2020-01-01 |title=X}}', 'pass'],
        ['uppercase URL base keeps archive-url', '{{cite web |URL=https://example.com |archive-url=https://web.archive.org/web/20200101000000/https://example.com |archive-date=2020-01-01 |title=X}}', 'pass'],
        ['uppercase URL base keeps format', '{{cite web |URL=https://example.com |format=PDF |title=X}}', 'pass'],
        ['un-hyphenated chapterurl base keeps chapter-format', '{{cite book |title=X |chapterurl=https://example.com |chapter-format=PDF |year=2020}}', 'pass'],

        // --- ISBN validation (merged Tier 1 fix) ---
        ['Valid ISBN-10 kept', '{{cite journal |title=X |journal=J |isbn=0-306-40615-2}}', 'pass'],
        ['Valid ISBN-13 kept', '{{cite journal |title=X |journal=J |isbn=978-0-306-40615-7}}', 'pass'],
        ['Bad ISBN-10 auto-repaired for post-2007 book', '{{cite journal |title=X |journal=J |date=2019 |isbn=0-306-40615-1}}', 'pass'],

        // --- coupled-parameter consistency (bot prevents these) ---
        ['doi-broken-date removed without doi', '{{cite journal |title=X |journal=J |doi-broken-date=2020-01-01}}', 'pass'],
        ['format removed without url', '{{cite journal |title=X |journal=J |format=PDF}}', 'pass'],
        ['pmc-embargo-date removed without pmc', '{{cite journal |title=X |journal=J |pmc-embargo-date=2020-01-01}}', 'pass'],

        // --- clean citations across template families ---
        ['Clean cite journal', '{{cite journal |title=Some paper |journal=Nature |year=2020 |volume=1 |issue=2 |pages=3}}', 'pass'],
        ['Clean cite book', '{{cite book |title=A Book |publisher=Pub |year=2020 |isbn=978-0-306-40615-7}}', 'pass'],
        ['Clean cite web', '{{cite web |url=https://example.com |title=A page |access-date=2020-01-01}}', 'pass'],
        ['Clean cite news with work= (periodical alias)', '{{cite news |url=https://example.com |title=Story |work=Reuters |date=2020}}', 'pass'],
        ['Clean cite magazine', '{{cite magazine |title=X |magazine=The New Yorker |date=2020}}', 'pass'],
        ['Clean cite encyclopedia', '{{cite encyclopedia |title=X |encyclopedia=Britannica |publisher=P |date=2020}}', 'pass'],
        ['Clean cite web with work= (periodical alias)', '{{cite web |url=https://example.com |title=X |work=SomeWebsite}}', 'pass'],
        ['Clean cite arxiv', '{{cite arxiv |arxiv=2401.99999 |title=X}}', 'pass'],
        ['Clean cite biorxiv', '{{cite biorxiv |biorxiv=10.1101/2020.01.01.000001 |title=X}}', 'pass'],
        ['Clean cite ssrn', '{{cite ssrn |ssrn=1234567 |title=X}}', 'pass'],

        // --- known gaps (documented current CS1 violations, tracked) ---
        ['GAP bad ISBN-13 check digit survives tidy', '{{cite journal |title=X |journal=J |isbn=978-0-306-40615-8}}', 'gap'],
        ['GAP invalid ISBN prefix survives tidy', '{{cite journal |title=X |journal=J |isbn=123-4567-890128}}', 'gap'],
        ['GAP bad ISBN-10 (pre-2007) survives tidy', '{{cite journal |title=X |journal=J |date=1999 |isbn=0-306-40615-1}}', 'gap'],
        ['GAP url-less cite web left as-is', '{{cite web |title=X}}', 'gap'],
        ['GAP empty title left as-is', '{{cite journal |journal=Nature}}', 'gap'],
        ['GAP orphaned trans-chapter left as-is', '{{cite journal |title=X |journal=J |trans-chapter=Y}}', 'gap'],
        ['GAP work= survives in cite book', '{{cite book |title=X |work=Some Series |publisher=P |year=2020}}', 'gap'],
        ['GAP malformed arxiv in input survives tidy', '{{cite journal |title=X |journal=J |arxiv=bogus}}', 'gap'],
        ['GAP malformed pmc in input survives tidy', '{{cite journal |title=X |journal=J |pmc=notnumeric}}', 'gap'],
        ['GAP malformed arxiv/eprint in input survives tidy', '{{cite journal |title=X |journal=J |eprint=XYZ}}', 'gap'],
        ['GAP malformed bibcode in input survives tidy', '{{cite journal |title=X |journal=J |bibcode=Z}}', 'gap'],
        ['GAP generic title in input survives tidy', '{{cite journal |title=No Title |journal=J}}', 'gap'],
        ['GAP generic name in input survives tidy', '{{cite journal |title=X |journal=J |last1=CNN}}', 'gap'],
        ['GAP malformed url in input survives tidy', '{{cite web |url=example.com |title=X}}', 'gap'],
    ];
}

// ---------------------------------------------------------------------------
// Runner
// ---------------------------------------------------------------------------

/** @return array{0: bool, 1: array<int, string>} */
function run_case(string $wikitext): array {
    try {
        ob_start();
        $templates = expand_citation($wikitext);
        ob_end_clean();
    } catch (Throwable $e) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $trace = array_slice(explode("\n", $e->getTraceAsString()), 0, 12);
        return [false, ["expansion error: " . $e->getMessage(), ...$trace]];
    }
    $violations = [];
    foreach ($templates as $template) {
        $violations = array_merge($violations, check_citation($template));
    }
    return [count($templates) > 0, array_values(array_unique($violations))];
}

$list_only = in_array('--list', $argv, true);
$matrix = build_matrix();

if ($list_only) {
    $mode = SLOW_MODE ? 'slow' : 'fast';
    echo "CS1 harness matrix (mode: $mode)\n";
    foreach ($matrix as $i => [$name, $wikitext, $expectation]) {
        echo sprintf("  %2d  %-7s %s\n", $i + 1, $expectation, $name);
    }
    exit(0);
}

$mode = SLOW_MODE ? 'slow' : 'fast';
echo "CS1 self-validation harness (mode: $mode)\n";
echo "--------------------------------------------------------------\n";

$passed = 0;
$gaps = 0;
$resolved = 0;
$failed = 0;
$failures = [];

foreach ($matrix as [$name, $wikitext, $expectation]) {
    [$produced_output, $violations] = run_case($wikitext);
    if (!$produced_output) {
        $failed++;
        $failures[] = [$name, $violations];
        echo "FAIL  $name (no citation output produced)\n";
        continue;
    }
    if ($expectation === 'pass') {
        if ($violations === []) {
            $passed++;
            echo "PASS  $name\n";
        } else {
            $failed++;
            $failures[] = [$name, $violations];
            echo "FAIL  $name\n";
            foreach ($violations as $violation) {
                echo "        - $violation\n";
            }
        }
    } else {
        $gaps++;
        if ($violations === []) {
            $resolved++;
            $status = 'RESOLVED';
        } else {
            $status = 'KNOWN-GAP';
        }
        echo "GAP   $name [$status]\n";
        foreach ($violations as $violation) {
            echo "        - $violation\n";
        }
    }
}

echo "--------------------------------------------------------------\n";
echo "Summary: $passed passed, $gaps gap cases, $resolved unexpectedly resolved, $failed failed\n";
if ($resolved > 0) {
    echo "\n$resolved gap case(s) unexpectedly resolved: convert them from 'gap' to 'pass' (or confirm the resolution is intentional).\n";
}
if ($failed > 0) {
    echo "\nFailed cases:\n";
    foreach ($failures as [$name, $violations]) {
        echo "  - $name\n";
        foreach ($violations as $violation) {
            echo "      $violation\n";
        }
    }
}

exit($failed > 0 || $resolved > 0 ? 1 : 0);
