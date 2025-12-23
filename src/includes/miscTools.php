<?php

declare(strict_types=1);

const GROUP_F1  = ['first', 'initials', 'forename', 'contributor-first', 'contributor-given', 'author-first', 'forename', 'initials', 'given', 'author-given'];
const GROUP_L1  = ['last', 'surname', 'author', 'contributor-last', 'contributor-surname', 'contributor', 'author-last', 'author-surname'];
const GROUP1  = ['author', 'authors', 'last', 'first', 'vauthors', 'surname'];
const GROUP2  = ['others', 'display-editors', 'displayeditors', 'display-authors', 'displayauthors', 'author-link', 'veditors', 'coauthors', 'coauthor', 'author-mask', 'contributor-last', 'contributor-link', 'contributor-mask', 'contributor-surname', 'contributor', 'display-contributors', 'display-interviewers', 'display-subjects', 'display-translators', 'editor-first', 'editor-given', 'editor-last', 'editor-link', 'editor-mask', 'editor-surname', 'editor', 'interviewer-first', 'interviewer-given', 'interviewer-last', 'interviewer-link', 'interviewer-mask', 'interviewer-surname', 'interviewer', 'inventor-first', 'inventor-given', 'inventor-last', 'inventor-link', 'inventor-surname', 'inventor', 'inventorlink', 'translator-first', 'translator-given', 'translator-last', 'translator-link', 'translator-mask', 'translator-surname', 'translator', 'veditor', ];
const GROUP3  = ['title', 'title-link', 'titlelink'];
const GROUP4  = ['chapter'];
const GROUP5  = ['journal', 'work', 'newspaper', 'website', 'magazine', 'periodical', 'encyclopedia', 'encyclopaedia', 'book-title'];
const GROUP6  = ['series'];
const GROUP7  = ['year', 'date'];
const GROUP8  = ['volume'];
const GROUP9  = ['issue', 'number'];
const GROUP10 = ['page', 'pages', 'at', 'p', 'pp'];
const GROUP11 = ['article-number'];
const GROUP12 = ['location', 'publisher', 'edition', 'agency'];
const GROUP13 = ['doi'];
const GROUP14 = ['doi-broken-date'];
const GROUP15 = ['doi-access'];
// GROUP16 does not exist
const GROUP17 = ['jstor'];
const GROUP18 = ['pmid'];
const GROUP19 = ['pmc'];
const GROUP20 = ['pmc-embargo-date'];
const GROUP21 = ['arxiv', 'eprint', 'class'];
const GROUP22 = ['bibcode', 'bibcode-access'];
const GROUP23 = ['hdl', 'hdl-access'];
const GROUP24 = ['isbn', 'biorxiv', 'citeseerx', 'jfm', 'zbl', 'mr', 'osti', 'ssrn', 'rfc'];
const GROUP25 = ['lccn', 'issn', 'ol', 'oclc', 'asin', 's2cid', 's2cid-access'];
const GROUP26 = ['url'];
const GROUP27 = ['chapter-url', 'article-url', 'chapterurl', 'conference-url', 'conferenceurl', 'contribution-url', 'contributionurl', 'entry-url', 'event-url', 'eventurl', 'lay-url', 'layurl', 'map-url', 'mapurl', 'section-url', 'sectionurl', 'transcript-url', 'transcripturl', 'URL'];
const GROUP28 = ['archive-url', 'archiveurl', 'accessdate', 'access-date', 'chapter-url-access', 'entry-url-access'];
const GROUP29 = ['archive-date', 'archivedate'];
const GROUP30 = ['id', 'type', 'via'];

function check_memory_usage(string $where): void {
    $mem_used = (int) (memory_get_usage() / 1048576);
    if ($mem_used > 24) {
        bot_debug_log("Memory Usage is up to " . (string) $mem_used . "MB in " . $where);
    }
    $mem_used = (int) (memory_get_peak_usage() / 1048576);
    if ($mem_used > 128) {
        bot_debug_log("Peak memory Usage is up to " . (string) $mem_used . "MB in " . $where); // @codeCoverageIgnore
    }
}

function string_is_book_series(string $str): bool {
    $simple = mb_trim(str_replace(['-', '.', '   ', '  ', '[[', ']]'], [' ', ' ', ' ', ' ', ' ', ' '], mb_strtolower($str)));
    $simple = mb_trim(str_replace(['    ', '   ', '  '], [' ', ' ', ' '], $simple));
    return in_array($simple, JOURNAL_IS_BOOK_SERIES, true);
}

/**
 * This code is recursive. It goes through a long list of parameters to find its place in the list.
 *
 * @param string $parameter
 * @param array<string> $list
 * @return array<string> A big list of parameters. This can return over a thousand parameters.
 */
function prior_parameters(string $parameter, array $list = []): array {
    // If no parameter is provided, use the first one in the list provided.
    if ($parameter === '') {
        $parameter = $list[0];
    }

    // Add $parameter at the beginning of the $list.
    array_unshift($list, $parameter);

    // Handle parameters with numbers in them, e.g. author1
    $parameterContainsANumber = preg_match('/(\D+)(\d+)/', $parameter, $match);
    $parameterIsNotS2cid = mb_stripos($parameter, 's2cid') === false;
    if ($parameterContainsANumber && $parameterIsNotS2cid) {
        $before = (string) ((int) $match[2] - 1);
        $number = $match[2];
        $base = $match[1];
        unset($match);
        if (in_array($base, GROUP_F1, true)) {
            return [
                'last' . $number,
                'surname' . $number,
                'author' . $before,
                'contributor-last' . $before,
                'contributor-surname' . $before,
                'contributor' . $before,
                'contributor' . $before . '-surname',
                'contributor' . $before . '-last'
            ];
        } elseif (in_array($base, GROUP_L1, true)) {
            return [
                'first' . $before,
                'forename' . $before,
                'initials' . $before,
                'author' . $before,
                'contributor-given' . $before,
                'contributor-first' . $before,
                'contributor' . $before . '-given',
                'contributor' . $before . '-first'
            ];
        } else {
            // Always add new authors at the very end of existing ones, even ones with bigger numbers.
            return array_merge(
                FLATTENED_AUTHOR_PARAMETERS,
                [
                    $base . $before,
                    $base . $before . '-last',
                    $base . $before . '-first',
                    $base . '-last' . $before,
                    $base . '-first' . $before,
                    $base . $before . '-surname',
                    $base . $before . '-given',
                    $base . '-surname' . $before,
                    $base . '-given' . $before,
                ]
            );
        }
    }

    // Handle parameters with no numbers in them, e.g. author. This section uses recursion.
    if (in_array($parameter, GROUP1, true)) {
        return $list;
    } elseif (in_array($parameter, GROUP2, true)) {
        return prior_parameters('', array_merge(FLATTENED_AUTHOR_PARAMETERS, $list));
    } elseif (in_array($parameter, GROUP3, true)) {
        return prior_parameters('', array_merge(GROUP2, $list));
    } elseif (in_array($parameter, GROUP4, true)) {
        return prior_parameters('', array_merge(GROUP3, $list));
    } elseif (in_array($parameter, GROUP5, true)) {
        return prior_parameters('', array_merge(GROUP4, $list));
    } elseif (in_array($parameter, GROUP6, true)) {
        return prior_parameters('', array_merge(GROUP5, $list));
    } elseif (in_array($parameter, GROUP7, true)) {
        return prior_parameters('', array_merge(GROUP6, $list));
    } elseif (in_array($parameter, GROUP8, true)) {
        return prior_parameters('', array_merge(GROUP7, $list));
    } elseif (in_array($parameter, GROUP9, true)) {
        return prior_parameters('', array_merge(GROUP8, $list));
    } elseif (in_array($parameter, GROUP10, true)) {
        return prior_parameters('', array_merge(GROUP9, $list));
    } elseif (in_array($parameter, GROUP11, true)) {
        return prior_parameters('', array_merge(GROUP10, $list));
    } elseif (in_array($parameter, GROUP12, true)) {
        return prior_parameters('', array_merge(GROUP11, $list));
    } elseif (in_array($parameter, GROUP13, true)) {
        return prior_parameters('', array_merge(GROUP12, $list));
    } elseif (in_array($parameter, GROUP14, true)) {
        return prior_parameters('', array_merge(GROUP13, $list));
    } elseif (in_array($parameter, GROUP15, true)) {
        return prior_parameters('', array_merge(GROUP14, $list));
    } elseif (in_array($parameter, GROUP17, true)) { // There is no GROUP 16
        return prior_parameters('', array_merge(GROUP15, $list));
    } elseif (in_array($parameter, GROUP18, true)) {
        return prior_parameters('', array_merge(GROUP17, $list));
    } elseif (in_array($parameter, GROUP19, true)) {
        return prior_parameters('', array_merge(GROUP18, $list));
    } elseif (in_array($parameter, GROUP20, true)) {
        return prior_parameters('', array_merge(GROUP19, $list));
    } elseif (in_array($parameter, GROUP21, true)) {
        return prior_parameters('', array_merge(GROUP20, $list));
    } elseif (in_array($parameter, GROUP22, true)) {
        return prior_parameters('', array_merge(GROUP21, $list));
    } elseif (in_array($parameter, GROUP23, true)) {
        return prior_parameters('', array_merge(GROUP22, $list));
    } elseif (in_array($parameter, GROUP24, true)) {
        return prior_parameters('', array_merge(GROUP23, $list));
    } elseif (in_array($parameter, GROUP25, true)) {
        return prior_parameters('', array_merge(GROUP24, $list));
    } elseif (in_array($parameter, GROUP26, true)) {
        return prior_parameters('', array_merge(GROUP25, $list));
    } elseif (in_array($parameter, GROUP27, true)) {
        return prior_parameters('', array_merge(GROUP26, $list));
    } elseif (in_array($parameter, GROUP28, true)) {
        return prior_parameters('', array_merge(GROUP27, $list));
    } elseif (in_array($parameter, GROUP29, true)) {
        return prior_parameters('', array_merge(GROUP28, $list));
    } elseif (in_array($parameter, GROUP30, true)) {
        return prior_parameters('', array_merge(GROUP29, $list));
    } else {
        bot_debug_log("prior_parameters missed: " . $parameter);
        if (TRAVIS && $parameter !== 'not-a-param' && $parameter !== 's2cid1') {
            return [];  // errors in test suite that were not expected
        }
        return $list;
    }
}

/** @return array<string> */
function equivalent_parameters(string $par): array {
    switch ($par) {
        case 'author':
        case 'authors':
        case 'author1':
        case 'last1':
            return FLATTENED_AUTHOR_PARAMETERS;
        case 'pmid':
        case 'pmc':
            return ['pmc', 'pmid'];
        case 'page_range':
        case 'start_page':
        case 'end_page': // From doi_crossref
        case 'pages':
        case 'page':
            return ['page_range', 'pages', 'page', 'end_page', 'start_page'];
        default:
            return [$par];
    }
}

function throttle(): void {
    static $last_write_time = 0;
    static $phase = 0;
    $cycles = 20;    // Check every this many writes
    $min_interval = 2 * $cycles;    // How many seconds we want per-write on average
    if ($last_write_time === 0) {
        $last_write_time = time();
    }

    $mem_max = (string) @ini_get('memory_limit');
    if (preg_match('~^(\d+)M$~', $mem_max, $matches)) {
        $mem_max = (int) (0.3 * @intval($matches[1])); // Memory limit is set super high just to avoid crash
        unset($matches);
        $mem_used = (int) (memory_get_usage() / 1048576);
        if (($mem_max !== 0) && ($mem_used > $mem_max)) {    // Clear every buffer we have
                HandleCache::free_memory();                                                 // @codeCoverageIgnoreStart
                $mem_used1 = (string) (int) (memory_get_usage() / 1048576);
                AdsAbsControl::free_memory();
                $mem_used2 = (string) (int) (memory_get_usage() / 1048576);
                $mem_used0 = (string) $mem_used;
            bot_debug_log("Cleared memory: " . $mem_used2 . ' : ' . $mem_used1 . ' : ' . $mem_used0);
        }                                                                                                                // @codeCoverageIgnoreEnd
    } else {
        bot_debug_log("Memory Limit should end in M, but got: " . echoable($mem_max));  // @codeCoverageIgnore
    }
    $phase += 1;
    if ($phase < $cycles) {
        return;
    } else {
        $phase = 0;
    }

    $time_since_last_write = time() - $last_write_time;
    if ($time_since_last_write < 0) {
        $time_since_last_write = 0; // Super paranoid, this would be a freeze point
    }
    if ($time_since_last_write < $min_interval) {
        $time_to_pause = (int) floor($min_interval - $time_since_last_write); // @codeCoverageIgnore
        report_info("Throttling: waiting " . $time_to_pause . " seconds..."); // @codeCoverageIgnore
        sleep($time_to_pause);                                                // @codeCoverageIgnore
    }
    $last_write_time = time();
}

function should_url2chapter(Template $template, bool $force): bool {
    if ($template->has('chapterurl')) {
        return false;
    }
    if ($template->has('chapter-url')) {
        return false;
    }
    if ($template->has('trans-chapter')) {
        return false;
    }
    if ($template->blank('chapter')) {
        return false;
    }
    if (mb_strpos($template->get('chapter'), '[') !== false) {
        return false;
    }
    $url = $template->get('url');
    $url = str_ireplace('%2F', '/', $url);
    if (mb_stripos($url, 'google') && !mb_strpos($template->get('url'), 'pg=')) {
        return false;
    } // Do not move books without page numbers
    if (mb_stripos($url, 'archive.org/details/isbn')) {
        return false;
    }
    if (mb_stripos($url, 'page_id=0')) {
        return false;
    }
    if (mb_stripos($url, 'page=0')) {
        return false;
    }
    if (mb_substr($url, -2) === '_0') {
        return false;
    }
    if (preg_match('~archive\.org/details/[^/]+$~', $url)) {
        return false;
    }
    if (preg_match('~archive\.org/details/.+/page/n(\d+)~', $url, $matches)) {
        if ((int) $matches[1] < 16) {
            return false;
        } // Assume early in the book - title page, etc
    }
    if (mb_stripos($url, 'PA1') && !preg_match('~PA1[0-9]~i', $url)) {
        return false;
    }
    if (mb_stripos($url, 'PA0')) {
        return false;
    }
    if (mb_stripos($url, 'PP1') && !preg_match('~PP1[0-9]~i', $url)) {
        return false;
    }
    if (mb_stripos($url, 'PP0')) {
        return false;
    }
    if ($template->get_without_comments_and_placeholders('chapter') === '') {
        return false;
    }
    if (mb_stripos($url, 'archive.org')) {
        if (mb_strpos($url, 'chapter')) {
            return true;
        }
        if (mb_strpos($url, 'page')) {
            if (preg_match('~page/?[01]?$~i', $url)) {
                return false;
            }
            return true;
        }
        return false;
    }
    if (mb_stripos($url, 'wp-content')) {
        // Private websites are hard to judge
        if (mb_stripos($url, 'chapter') || mb_stripos($url, 'section')) {
            return true;
        }
        if (mb_stripos($url, 'pages') && !preg_match('~[^\d]1[-–]~u', $url)) {
            return true;
        }
        return false;
    }
    if (mb_strpos($url, 'link.springer.com/chapter/10.')) {
        return true;
    }
    if (preg_match('~10\.1007\/97[89]-?[0-9]{1,5}\-?[0-9]+\-?[0-9]+\-?[0-9]\_\d{1,3}~', $url)) {
        return true;
    }
    if (preg_match('~10\.1057\/97[89]-?[0-9]{1,5}\-?[0-9]+\-?[0-9]+\-?[0-9]\_\d{1,3}~', $url)) {
        return true;
    }
    if ($force) {
        return true;
    }
    // Only do a few select website unless we just converted to cite book from cite journal
    if (mb_strpos($url, 'archive.org')) {
        return true;
    }
    if (mb_strpos($url, 'google.com')) {
        return true;
    }
    if (mb_strpos($url, 'www.sciencedirect.com/science/article')) {
        return true;
    }
    return false;
}

function handleConferencePretendingToBeAJournal(Template $template, string $rawtext): void {
    $the_chapter = '';
    $the_issue = '';
    $the_journal = '';
    $the_page = '';
    $the_pages = '';
    $the_title = '';
    $the_volume = '';
    $this_array = [$template];
    $move_and_forget = function (string $para) use($template): void
    {
        // Try to keep parameters in the same order
        $para2 = str_replace('CITATION_BOT_PLACEHOLDER_', '', $para);
        if ($template->has($para2)) {
            $template->set($para, $template->get($para2));
            $template->rename($para, $para2);
        } else {
            $template->forget($para); // This can happen when there is less than ideal data, such as {{cite journal|jstor=3073767|pages=null|page=null|volume=n/a|issue=0|title=[No title found]|coauthors=Duh|last1=Duh|first1=Dum|first=Hello|last=By|author=Yup|author1=Nope|year=2002
        }
    };

    if (
        mb_stripos($rawtext, 'citation_bot_placeholder_comment') === false &&
        mb_stripos($rawtext, 'graph drawing') === false &&
        mb_stripos($rawtext, 'Lecture Notes in Computer Science') === false &&
        mb_stripos($rawtext, 'LNCS ') === false &&
        mb_stripos($rawtext, ' LNCS') === false && (
            !$template->blank(['pmc', 'pmid', 'doi', 'jstor']) || (
                mb_stripos($template->get('journal') . $template->get('title'), 'arxiv') !== false && !$template->blank(ARXIV_ALIASES)
            )
        )
    ) {
        // Have some good data
        $the_title = $template->get('title');
        $the_journal = str_replace(['[', ']'], '', $template->get('journal'));
        $the_chapter = $template->get('chapter');
        $the_volume = $template->get('volume');
        $the_issue = $template->get('issue');
        $the_page = $template->get('page');
        $the_pages = $template->get('pages');
        if ($template->get2('chapter') === null) {
            $no_start_chapter = true;
        } else {
            $no_start_chapter = false;
        }
        if ($template->get2('journal') === null) {
            $no_start_journal = true;
        } else {
            $no_start_journal = false;
        }
        $initial_author_params_save = $template->initial_author_params();
        $bad_data = false;
        if (mb_stripos($the_journal, 'Advances in Cryptology') === 0 && mb_stripos($the_title, 'Advances in Cryptology') === 0) {
            $the_journal = '';
            $template->forget('journal');
            $bad_data = true;
        }
        $ieee_insanity = false;
        if (
            conference_doi($template->get('doi')) &&
            in_array($template->wikiname(), ['cite journal', 'cite web'], true) &&
            ($template->has('isbn') ||
            (mb_stripos($the_title, 'proceedings') !== false && mb_stripos($the_journal, 'proceedings') !== false) ||
            (mb_stripos($the_title, 'proc. ') !== false && mb_stripos($the_journal, 'proc. ') !== false) ||
            (mb_stripos($the_title, 'Conference') !== false && mb_stripos($the_journal, 'Conference') !== false) ||
            (mb_stripos($the_title, 'Colloquium') !== false && mb_stripos($the_journal, 'Colloquium') !== false) ||
            (mb_stripos($the_title, 'Symposium') !== false && mb_stripos($the_journal, 'Symposium') !== false) ||
            (mb_stripos($the_title, 'Extended Abstracts') !== false && mb_stripos($the_journal, 'Extended Abstracts') !== false) ||
            (mb_stripos($the_title, 'Meeting on ') !== false && mb_stripos($the_journal, 'Meeting on ') !== false))
        ) {
            // IEEE/ACM/etc "book"
            $data_to_check = $the_title . $the_journal . $the_chapter . $template->get('series');
            if (mb_stripos($data_to_check, 'IEEE Standard for') !== false && $template->blank('journal')) {
                // Do nothing
            } elseif (mb_stripos($data_to_check, 'SIGCOMM Computer Communication Review') !== false) {
                // Actual journal with ISBN
                // Do nothing
            } elseif (
                mb_stripos($data_to_check, 'Symposium') === false &&
                mb_stripos($data_to_check, 'Conference') === false &&
                mb_stripos($data_to_check, 'Proceedings') === false &&
                mb_stripos($data_to_check, 'Proc. ') === false &&
                mb_stripos($data_to_check, 'Workshop') === false &&
                mb_stripos($data_to_check, 'Symp. On ') === false &&
                mb_stripos($data_to_check, 'Meeting on ') === false &&
                mb_stripos($data_to_check, 'Colloquium') === false &&
                mb_stripos($data_to_check, 'Extended Abstracts') === false &&
                mb_stripos($the_journal, 'Visual Languages and Human-Centric Computing') === false &&
                mb_stripos($the_journal, 'Active and Passive Microwave Remote Sensing for') === false
            ) {
                // Looks like conference done, but does not claim so
                if ($the_journal !== '') {
                    $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
                    $the_journal = '';
                }
                if ($the_title !== '') {
                    $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                    $the_title = '';
                }
                if ($the_chapter !== '') {
                    $template->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
                    $the_chapter = '';
                }
                $bad_data = true;
            } elseif (
                mb_stripos($the_journal, 'Symposium') !== false ||
                mb_stripos($the_journal, 'Conference') !== false ||
                mb_stripos($the_journal, 'Proceedings') !== false ||
                mb_stripos($the_journal, 'Proc. ') !== false ||
                mb_stripos($the_journal, 'Workshop') !== false ||
                mb_stripos($the_journal, 'Symp. On ') !== false ||
                mb_stripos($the_journal, 'Meeting on ') !== false ||
                mb_stripos($the_journal, 'Colloquium') !== false ||
                mb_stripos($the_journal, 'Extended Abstracts') !== false ||
                mb_stripos($the_journal, 'Active and Passive Microwave Remote Sensing for') !== false ||
                mb_stripos($the_journal, 'Visual Languages and Human-Centric Computing') !== false
            ) {
                $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
                $ieee_insanity = true;
                $the_journal = '';
                $bad_data = true;
                if ($the_title !== '') {
                    $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                    $the_title = '';
                }
                if ($the_chapter !== '') {
                    $template->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
                    $the_chapter = '';
                }
            }
        }
        if (
            mb_stripos($the_journal, 'Advances in Cryptology') === 0 ||
            mb_stripos($the_journal, 'IEEE Symposium') !== false ||
            mb_stripos($the_journal, 'IEEE Conference') !== false ||
            mb_stripos($the_journal, 'IEEE International Conference') !== false ||
            mb_stripos($the_journal, 'ACM International Symposium') !== false ||
            mb_stripos($the_journal, 'ACM Symposium') !== false ||
            mb_stripos($the_journal, 'Extended Abstracts') !== false ||
            mb_stripos($the_journal, 'IEEE International Symposium') !== false ||
            mb_stripos($the_journal, 'Symposium on Theoretical Aspects') !== false ||
            mb_stripos($the_journal, 'Lecture Notes in Computer Science') !== false ||
            mb_stripos($the_journal, 'International Conference on ') !== false ||
            mb_stripos($the_journal, 'ACM International Conference') !== false ||
            mb_stripos($the_journal, 'Proceedings of SPIE') !== false ||
            mb_stripos($the_journal, 'Proceedings of the SPIE') !== false ||
            mb_stripos($the_journal, 'SPIE Proc') !== false ||
            mb_stripos($the_journal, 'Proceedings of the Society of ') !== false ||
            (mb_stripos($the_journal, 'Proceedings of ') !== false && mb_stripos($the_journal, 'Conference') !== false) ||
            (mb_stripos($the_journal, 'Proc. ') !== false && mb_stripos($the_journal, 'Conference') !== false) ||
            (mb_stripos($the_journal, 'International') !== false && mb_stripos($the_journal, 'Conference') !== false) ||
            (mb_stripos($the_journal, 'International') !== false && mb_stripos($the_journal, 'Meeting') !== false) ||
            (mb_stripos($the_journal, 'International') !== false && mb_stripos($the_journal, 'Colloquium') !== false) ||
            (mb_stripos($the_journal, 'International') !== false && mb_stripos($the_journal, 'Symposium') !== false) ||
            mb_stripos($the_journal, 'SIGGRAPH') !== false ||
            mb_stripos($the_journal, 'Design Automation Conference') !== false
        ) {
            $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
            $the_journal = '';
            $bad_data = true;
            if ($the_title !== '') {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $the_title = '';
            }
            if ($the_chapter !== '') {
                $template->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
                $the_chapter = '';
            }
        }
        if ($template->is_book_series('series') && $the_journal !== "") {
            $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
            $the_journal = '';
            $bad_data = true;
            if ($the_title !== '') {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $the_title = '';
            }
            if ($the_chapter !== '') {
                $template->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
                $the_chapter = '';
            }
        } elseif ($template->is_book_series('series') && $the_chapter === '' && $the_title !== '' && $template->has('doi')) {
            $bad_data = true;
            $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
            $the_title = '';
        }

        if ($the_pages === '_' || $the_pages === '0' || $the_pages === 'null' || $the_pages === 'n/a' || $the_pages === 'online' || $the_pages === 'Online' || $the_pages === 'Forthcoming' || $the_pages === 'forthcoming') {
            $template->rename('pages', 'CITATION_BOT_PLACEHOLDER_pages');
            $the_pages = '';
            $bad_data = true;
        }
        if ($the_page === '_' || $the_page === '0' || $the_page === 'null' || $the_page === 'n/a' || $the_page === 'online' || $the_page === 'Online' || $the_page === 'Forthcoming' || $the_page === 'forthcoming') {
            $template->rename('page', 'CITATION_BOT_PLACEHOLDER_page');
            $the_page = '';
            $bad_data = true;
        }
        if (
            $the_volume === '_' ||
            $the_volume === '0' ||
            $the_volume === 'null' ||
            $the_volume === 'n/a' ||
            $the_volume === 'Online edition' ||
            $the_volume === 'online' ||
            $the_volume === 'Online' ||
            $the_volume === 'in press' ||
            $the_volume === 'In press' ||
            $the_volume === 'ahead-of-print' ||
            $the_volume === 'Forthcoming' ||
            $the_volume === 'forthcoming'
        ) {
            $template->rename('volume', 'CITATION_BOT_PLACEHOLDER_volume');
            $the_volume = '';
            $bad_data = true;
        }
        if (
            $the_issue === '_' ||
            $the_issue === '0' ||
            $the_issue === 'null' ||
            $the_issue === 'ja' ||
            $the_issue === 'n/a' ||
            $the_issue === 'Online edition' ||
            $the_issue === 'online' ||
            $the_issue === 'Online' ||
            $the_issue === 'in press' ||
            $the_issue === 'In press' ||
            $the_issue === 'ahead-of-print' ||
            $the_issue === 'Forthcoming' ||
            $the_issue === 'forthcoming'
        ) {
            $template->rename('issue', 'CITATION_BOT_PLACEHOLDER_issue');
            $the_issue = '';
            $bad_data = true;
        }
        if (mb_strlen($the_title) > 15 && mb_strpos($the_title, ' ') !== false && mb_strtoupper($the_title) === $the_title && mb_strpos($the_title, 'CITATION') === false && mb_check_encoding($the_title, 'ASCII')) {
            $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
            $the_title = '';
            $bad_data = true;
        }
        if (mb_stripos($the_title, 'SpringerLink') !== false) {
            $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
            $the_title = '';
            $bad_data = true;
        }
        if (
            $the_title === '_' ||
            $the_title === 'null' ||
            $the_title === '[No title found]' ||
            $the_title === 'Archived copy' ||
            $the_title === 'JSTOR' ||
            $the_title === 'ShieldSquare Captcha' ||
            $the_title === 'Shibboleth Authentication Request' ||
            $the_title === 'Pubmed' ||
            $the_title === 'usurped title' ||
            $the_title === 'Pubmed Central' ||
            $the_title === 'Optica Publishing Group' ||
            $the_title === 'BioOne' ||
            $the_title === 'IEEE Xplore' ||
            $the_title === 'ScienceDirect' ||
            $the_title === 'Science Direct' ||
            $the_title === 'Validate User'
        ) {
            // title=none is often because title is "reviewed work....
            $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
            $the_title = '';
            $bad_data = true;
        }
        if (mb_strlen($the_journal) > 15 && mb_strpos($the_journal, ' ') !== false && mb_strtoupper($the_journal) === $the_journal && mb_strpos($the_journal, 'CITATION') === false && mb_check_encoding($the_journal, 'ASCII')) {
            $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
            $the_journal = '';
            $bad_data = true;
        }
        if (mb_strlen($the_chapter) > 15 && mb_strpos($the_chapter, ' ') !== false && mb_strtoupper($the_chapter) === $the_chapter && mb_strpos($the_chapter, 'CITATION') === false && mb_check_encoding($the_chapter, 'ASCII')) {
            $template->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
            $the_chapter = '';
            $bad_data = true;
        }
        if (str_i_same($the_journal, 'Biochimica et Biophysica Acta') || str_i_same($the_journal, '[[Biochimica et Biophysica Acta]]')) {
            // Only part of the journal name
            $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
            $the_journal = '';
            $bad_data = true;
        }
        if (
            str_i_same($the_journal, 'JSTOR') ||
            $the_journal === '_' ||
            str_i_same($the_journal, 'BioOne') ||
            str_i_same($the_journal, 'IEEE Xplore') ||
            str_i_same($the_journal, 'PubMed') ||
            str_i_same($the_journal, 'PubMed Central') ||
            str_i_same($the_journal, 'ScienceDirect') ||
            str_i_same($the_journal, 'Science Direct')
        ) {
            $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
            $the_journal = '';
            $bad_data = true;
        }
        if ((mb_stripos($the_journal, 'arXiv:') === 0 || $the_journal === 'arXiv') && !$template->blank(ARXIV_ALIASES)) {
            $template->forget('journal');
            $the_journal = '';
            $bad_data = true;
            if ($template->wikiname() === 'cite journal') {
                $template->change_name_to('cite arxiv');
            }
        }
        if (mb_stripos($the_journal, 'arXiv') !== false) {
            $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
            $the_journal = '';
            $bad_data = true;
        }
        if (mb_stripos($the_journal, 'ScienceDirect') !== false) {
            $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
            $the_journal = '';
            $bad_data = true;
        }
        if ($the_chapter === '_') {
            $template->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
            $the_chapter = '';
            $bad_data = true;
        }
        if ($the_title !== '' && mb_stripos(str_replace('CITATION_BOT_PLACEHOLDER_TEMPLATE', '', $the_title), 'CITATION') === false) {
            // Templates are generally {{!}} and such
            if (str_i_same($the_title, $the_journal) && str_i_same($the_title, $the_chapter)) {
                // Journal === Title === Chapter INSANE!  Never actually seen
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
                $template->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
                $the_title = '';
                $the_journal = '';
                $the_chapter = '';
                $bad_data = true;
            } elseif (str_i_same($the_title, $the_journal)) {
                // Journal === Title
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $template->rename('journal', 'CITATION_BOT_PLACEHOLDER_journal');
                $the_title = '';
                $the_journal = '';
                $bad_data = true;
            } elseif (str_i_same($the_title, $the_chapter)) {
                // Chapter === Title
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $template->rename('chapter', 'CITATION_BOT_PLACEHOLDER_chapter');
                $the_title = '';
                $the_chapter = '';
                $bad_data = true;
            } elseif (mb_substr($the_title, -9, 9) === ' on JSTOR') {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title'); // Ends in 'on jstor'
                $the_title = '';
                $bad_data = true;
            } elseif (mb_substr($the_title, -20, 20) === 'IEEE Xplore Document') {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $the_title = '';
                $bad_data = true;
            } elseif (mb_substr($the_title, 0, 12) === 'IEEE Xplore ') {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $the_title = '';
                $bad_data = true;
            } elseif (mb_substr($the_title, -12) === ' IEEE Xplore') {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $the_title = '';
                $bad_data = true;
            } elseif (preg_match('~.+(?: Volume| Vol\.| V. | Number| No\.| Num\.| Issue ).*\d+.*page.*\d+~i', $the_title)) {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $the_title = '';
                $bad_data = true;
            } elseif (preg_match('~^\[No title found\]$~i', $the_title)) {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $the_title = '';
                $bad_data = true;
            } elseif (mb_stripos($the_title, 'arXiv') !== false) {
                $template->rename('title', 'CITATION_BOT_PLACEHOLDER_title');
                $the_title = '';
                $bad_data = true;
            }
        }
        if ($template->has('coauthors')) {
            if ($template->has('first')) {
                $template->rename('first', 'CITATION_BOT_PLACEHOLDER_first');
            }
            if ($template->has('last')) {
                $template->rename('last', 'CITATION_BOT_PLACEHOLDER_last');
            }
            if ($template->has('first1')) {
                $template->rename('first1', 'CITATION_BOT_PLACEHOLDER_first1');
            }
            if ($template->has('last1')) {
                $template->rename('last1', 'CITATION_BOT_PLACEHOLDER_last1');
            }
            if ($template->has('author1')) {
                $template->rename('author1', 'CITATION_BOT_PLACEHOLDER_author1');
            }
            if ($template->has('author')) {
                $template->rename('author', 'CITATION_BOT_PLACEHOLDER_author');
            }
            $template->rename('coauthors', 'CITATION_BOT_PLACEHOLDER_coauthors');
            if ($template->blank(FLATTENED_AUTHOR_PARAMETERS)) {
                $template->initial_author_params_set([]);
                $bad_data = true;
            } else {
                if ($template->has('CITATION_BOT_PLACEHOLDER_first')) {
                    $template->rename('CITATION_BOT_PLACEHOLDER_first', 'first');
                }
                if ($template->has('CITATION_BOT_PLACEHOLDER_last')) {
                    $template->rename('CITATION_BOT_PLACEHOLDER_last', 'last');
                }
                if ($template->has('CITATION_BOT_PLACEHOLDER_first1')) {
                    $template->rename('CITATION_BOT_PLACEHOLDER_first1', 'first1');
                }
                if ($template->has('CITATION_BOT_PLACEHOLDER_last1')) {
                    $template->rename('CITATION_BOT_PLACEHOLDER_last1', 'last1');
                }
                if ($template->has('CITATION_BOT_PLACEHOLDER_author1')) {
                    $template->rename('CITATION_BOT_PLACEHOLDER_author1', 'author1');
                }
                if ($template->has('CITATION_BOT_PLACEHOLDER_author')) {
                    $template->rename('CITATION_BOT_PLACEHOLDER_author', 'author');
                }
                $template->rename('CITATION_BOT_PLACEHOLDER_coauthors', 'coauthors');
            }
        }
        if ($bad_data) {
            if ($template->has('year') && $template->blank(['isbn', 'lccn', 'oclc'])) {
                // Often the pre-print year
                $template->rename('year', 'CITATION_BOT_PLACEHOLDER_year');
            }
            if ($template->has('doi')) {
                expand_by_doi($template);
            }
            if ($template->has('pmid')) {
                query_pmid_api([$template->get('pmid')], $this_array);
            }
            if ($template->has('pmc')) {
                query_pmc_api([$template->get('pmc')], $this_array);
            }
            if ($template->has('jstor')) {
                expand_by_jstor($template);
            }
            if ($template->blank(['pmid', 'pmc', 'jstor']) && ($template->has('eprint') || $template->has('arxiv'))) {
                expand_arxiv_templates($this_array);
            }
            if ($ieee_insanity && $template->has('chapter') && $template->has('title')) {
                $template->forget('CITATION_BOT_PLACEHOLDER_journal');
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_journal')) {
                if ($template->has('journal') && $template->get('journal') !== $template->get('CITATION_BOT_PLACEHOLDER_journal') && '[[' . $template->get('journal') . ']]' !== $template->get('CITATION_BOT_PLACEHOLDER_journal')) {
                    $move_and_forget('CITATION_BOT_PLACEHOLDER_journal');
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_journal', 'journal');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_title')) {
                if ($template->has('title')) {
                    $newer = str_replace([".", ",", ":", ";", "?", "!", " ", "-", "'", '"'], '', mb_strtolower($template->get('title')));
                    $older = str_replace([".", ",", ":", ";", "?", "!", " ", "-", "'", '"'], '', mb_strtolower($template->get('CITATION_BOT_PLACEHOLDER_title')));
                    if ($newer !== $older && mb_strpos($older, $newer) === 0) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_title', 'title'); // New title lost sub-title
                    } elseif (str_replace(" ", '', $template->get('title')) === str_replace([" ", "'"], '', $template->get('CITATION_BOT_PLACEHOLDER_title'))) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_title', 'title'); // New title lost italics
                    } elseif ($template->get('title') === $template->get('CITATION_BOT_PLACEHOLDER_title')) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_title', 'title');
                    } else {
                        $move_and_forget('CITATION_BOT_PLACEHOLDER_title');
                    }
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_title', 'title');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_chapter')) {
                if ($template->has('chapter')) {
                    $newer = str_replace([".", ",", ":", ";", "?", "!", " ", "-", "'", '"'], '', mb_strtolower($template->get('chapter')));
                    $older = str_replace([".", ",", ":", ";", "?", "!", " ", "-", "'", '"'], '', mb_strtolower($template->get('CITATION_BOT_PLACEHOLDER_chapter')));
                    if ($newer !== $older && mb_strpos($older, $newer) === 0) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_chapter', 'chapter'); // New chapter lost sub-chapter
                    } elseif (str_replace(" ", '', $template->get('chapter')) === str_replace([" ", "'"], '', $template->get('CITATION_BOT_PLACEHOLDER_chapter'))) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_chapter', 'chapter'); // New chapter lost italics
                    } elseif ($template->get('chapter') === $template->get('CITATION_BOT_PLACEHOLDER_chapter')) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_chapter', 'chapter');
                    } else {
                        $move_and_forget('CITATION_BOT_PLACEHOLDER_chapter');
                    }
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_chapter', 'chapter');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_issue')) {
                if ($template->has('issue') && $template->get('issue') !== $template->get('CITATION_BOT_PLACEHOLDER_issue')) {
                    $move_and_forget('CITATION_BOT_PLACEHOLDER_issue');
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_issue', 'issue');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_volume')) {
                if ($template->has('volume') && $template->get('volume') !== $template->get('CITATION_BOT_PLACEHOLDER_volume')) {
                    $move_and_forget('CITATION_BOT_PLACEHOLDER_volume');
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_volume', 'volume');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_page')) {
                if (($template->has('page') || $template->has('pages')) && $template->get('page') . $template->get('pages') !== $template->get('CITATION_BOT_PLACEHOLDER_page')) {
                    $move_and_forget('CITATION_BOT_PLACEHOLDER_page');
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_page', 'page');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_pages')) {
                if (($template->has('page') || $template->has('pages')) && $template->get('page') . $template->get('pages') !== $template->get('CITATION_BOT_PLACEHOLDER_pages')) {
                    $move_and_forget('CITATION_BOT_PLACEHOLDER_pages');
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_pages', 'pages');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_year')) {
                if ($template->has('year') && $template->get('year') !== $template->get('CITATION_BOT_PLACEHOLDER_year')) {
                    $move_and_forget('CITATION_BOT_PLACEHOLDER_year');
                } elseif ($template->has('date') && $template->get('date') !== $template->get('CITATION_BOT_PLACEHOLDER_year')) {
                    $move_and_forget('CITATION_BOT_PLACEHOLDER_year');
                } elseif ($template->has('date') && $template->get('date') === $template->get('CITATION_BOT_PLACEHOLDER_year')) {
                    $template->forget('date');
                    $template->rename('CITATION_BOT_PLACEHOLDER_year', 'year');
                } else {
                    $template->rename('CITATION_BOT_PLACEHOLDER_year', 'year');
                }
            }
            if ($template->has('CITATION_BOT_PLACEHOLDER_coauthors')) {
                if ($template->has('last1') || $template->has('author1')) {
                    $template->forget('CITATION_BOT_PLACEHOLDER_first');
                    $template->forget('CITATION_BOT_PLACEHOLDER_last');
                    $template->forget('CITATION_BOT_PLACEHOLDER_first1');
                    $template->forget('CITATION_BOT_PLACEHOLDER_last1');
                    $template->forget('CITATION_BOT_PLACEHOLDER_author1');
                    $template->forget('CITATION_BOT_PLACEHOLDER_author');
                    $template->forget('CITATION_BOT_PLACEHOLDER_coauthors');
                } else {
                    $template->initial_author_params_set($initial_author_params_save);
                    if ($template->has('CITATION_BOT_PLACEHOLDER_first')) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_first', 'first');
                    }
                    if ($template->has('CITATION_BOT_PLACEHOLDER_last')) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_last', 'last');
                    }
                    if ($template->has('CITATION_BOT_PLACEHOLDER_first1')) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_first1', 'first1');
                    }
                    if ($template->has('CITATION_BOT_PLACEHOLDER_last1')) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_last1', 'last1');
                    }
                    if ($template->has('CITATION_BOT_PLACEHOLDER_author1')) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_author1', 'author1');
                    }
                    if ($template->has('CITATION_BOT_PLACEHOLDER_author')) {
                        $template->rename('CITATION_BOT_PLACEHOLDER_author', 'author');
                    }
                    $template->rename('CITATION_BOT_PLACEHOLDER_coauthors', 'coauthors');
                }
            }
        }
        if ($no_start_chapter && $template->blank('chapter')) {
            $template->forget('chapter');
        }
        if ($no_start_journal && $template->blank('journal')) {
            $template->forget('journal');
        }
    }
    if ($the_chapter === 'a' && $the_issue === 'b' && $the_journal === 'c' && $the_page === 'd' && $the_pages === 'e' && $the_title === 'f' && $the_volume === 'g') {
        report_info('static analysis is happy');
        // We set many of these variables to "", and then never use them again.
        // We do this it means that over time we can safely expnand this function.
        // But this makes static analysis unhappy.
    }
}

function clean_cite_odnb(Template $template): void {
    if ($template->has('url')) {
        while (preg_match('~^(https?://www\.oxforddnb\.com/.+)(?:\;jsession|\?rskey|\#)~', $template->get('url'), $matches)) {
            $template->set('url', $matches[1]);
        }
    }
    if ($template->has('doi')) {
        $doi = $template->get('doi');
        if (doi_works($doi) === false) {
            if (preg_match("~^10\.1093/(?:\S+odnb-9780198614128-e-|ref:odnb|odnb/9780198614128\.013\.|odnb/)(\d+)$~", $doi, $matches)) {
                $try1 = '10.1093/ref:odnb/' . $matches[1];
                $try3 = '10.1093/odnb/9780198614128.013.' . $matches[1];
                if (doi_works($try1)) {
                    $template->set('doi', $try1);
                } elseif (doi_works($try3)) {
                    $template->set('doi', $try3);
                }
            }
        }
    }
    if ($template->has('id')) {
        $doi = $template->get('doi');
        $try1 = '10.1093/ref:odnb/' . $template->get('id');
        $try3 = '10.1093/odnb/9780198614128.013.' . $template->get('id');
        if (doi_works($try1) !== false) {
            // Template does this
        } elseif (doi_works($try3)) {
            if ($doi === '') {
                $template->rename('id', 'doi', $try3);
            } elseif ($doi === $try3) {
                $template->forget('id');
            } elseif (doi_works($doi)) {
                $template->forget('id');
            } else {
                $template->forget('doi');
                $template->rename('id', 'doi', $try3);
            }
        }
    }
    if ($template->has('doi')) {
        $works = doi_works($template->get('doi'));
        if ($works === false) {
            $template->add_if_new('doi-broken-date', date('Y-m-d'));
        } elseif ($works === true) {
            $template->forget('doi-broken-date');
        }
    }
}
