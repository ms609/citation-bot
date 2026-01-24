<?php

declare(strict_types=1);

const BAD_DOIS_FROM_CROSSREF = ['10.1355/9789812306319'];
const NO_CHAPTER_ADD = ['citation', 'cite web', 'cite news', 'cite magazine', 'cite press release', 'cite podcast', 'cite newsgroup', 'cite journal'];

/**
 * @param array<string> $_ids
 * @param array<Template> &$templates
 */
function query_doi_api(array $_ids, array &$templates): void { // $id not used yet  // Pointer to save memory
    foreach ($templates as $template) {
        expand_by_doi($template);
    }
    return;
}

function expand_by_doi(Template $template, bool $force = false): void {
    set_time_limit(120);
    $template->verify_doi();  // Sometimes CrossRef has Data even when DOI is broken, so try CrossRef anyway even when return is false
    $doi = $template->get_without_comments_and_placeholders('doi');
    if ($doi === $template->last_searched_doi) {
        return;
    }
    $template->last_searched_doi = $doi;
    if (preg_match(REGEXP_DOI_ISSN_ONLY, $doi)) {
        return;
    }
    if (isset(BAD_DOI_ARRAY[$doi])) { // Really bad ones that do not really exist at all
        return;
    }
    if ($doi && preg_match('~^10\.2307/(\d+)$~', $doi)) {
        $template->add_if_new('jstor', mb_substr($doi, 8));
    }
    if ($doi && ($force || $template->incomplete())) {
        $crossRef = query_crossref($doi);
        if ($crossRef) {
            if (in_array(mb_strtolower((string) @$crossRef->article_title), BAD_ACCEPTED_MANUSCRIPT_TITLES, true)) {
                return;
            }
            if ($template->has('title') && mb_trim((string) @$crossRef->article_title) && $template->get('title') !== 'none') { // Verify title of DOI matches existing data somewhat
                $bad_data = true;
                $new = (string) $crossRef->article_title;
                if (preg_match('~^(.................+)[\.\?]\s+([IVX]+)\.\s.+$~i', $new, $matches)) {
                    $new = $matches[1];
                    $new_roman = $matches[2];
                } elseif (preg_match('~^([IVX]+)\.[\s\-\—]*(.................+)$~i', $new, $matches)) {
                    $new = $matches[2];
                    $new_roman = $matches[1];
                } else {
                    $new_roman = false;
                }
                foreach (THINGS_THAT_ARE_TITLES as $possible) {
                    if ($template->has($possible)) {
                        $old = $template->get($possible);
                        if (preg_match('~^(.................+)[\.\?]\s+([IVX]+)\.\s.+$~i', $old, $matches)) {
                            $old = $matches[1];
                            $old_roman = $matches[2];
                        } elseif (preg_match('~^([IVX]+)\.[\s\-\—]*(.................+)$~i', $old, $matches)) {
                            $old = $matches[2];
                            $old_roman = $matches[1];
                        } else {
                            $old_roman = false;
                        }
                        if (titles_are_similar($old, $new)) {
                            if ($old_roman && $new_roman) {
                                if ($old_roman === $new_roman) { // If they got roman numeral truncated, then must match
                                    $bad_data = false;
                                    break;
                                }
                            } else {
                                $bad_data = false;
                                break;
                            }
                        }
                    }
                }
                if (isset($crossRef->series_title)) {
                    foreach (THINGS_THAT_ARE_TITLES as $possible) { // Series === series could easily be false positive
                        if ($template->has($possible) && titles_are_similar(preg_replace("~# # # CITATION_BOT_PLACEHOLDER_TEMPLATE \d+ # # #~i", "�", $template->get($possible)), (string) $crossRef->series_title)) {
                            $bad_data = false;
                            break;
                        }
                    }
                }
                if ($bad_data) {
                    report_warning("CrossRef title did not match existing title: doi:" . doi_link($doi));
                    if (isset($crossRef->series_title)) {
                        report_info("Possible new title: " . str_replace("\n", "", echoable((string) $crossRef->series_title)));
                    }
                    if (isset($crossRef->article_title)) {
                        report_info("Possible new title: " . echoable((string) $crossRef->article_title));
                    }
                    foreach (THINGS_THAT_ARE_TITLES as $possible) {
                        if ($template->has($possible)) {
                            report_info("Existing old title: " . echoable(preg_replace("~# # # CITATION_BOT_PLACEHOLDER_TEMPLATE \d+ # # #~i", "�", $template->get($possible))));
                        }
                    }
                    return;
                }
            }
            report_action("Querying CrossRef: doi:" . doi_link($doi));

            if ((string) @$crossRef->volume_title === 'Professional Paper') {
                unset($crossRef->volume_title);
            }
            if ((string) @$crossRef->series_title === 'Professional Paper') {
                unset($crossRef->series_title);
            }
            if ($template->has('book-title')) {
                unset($crossRef->volume_title);
            }

            $crossRefNewAPI = query_crossref_newapi($doi);
            if (isset($crossRefNewAPI->title[0]) && !isset($crossRefNewAPI->title[1])) {
                $new_title = (string) $crossRefNewAPI->title[0];
                if (conference_doi($doi) && isset($crossRefNewAPI->subtitle[0]) && mb_strlen((string) $crossRefNewAPI->subtitle[0]) > 4) {
                    $new_title .= ": " . (string) $crossRefNewAPI->subtitle[0];
                }
                $new_title = str_ireplace(['<i>', '</i>', '</i> :', '  '], [' <i>', '</i> ', '</i>:', ' '], $new_title);
            } else {
                $new_title = '';
            }
            // Check if this is a book chapter based on DOI type from the new API
            $doi_type = isset($crossRefNewAPI->type) ? (string) $crossRefNewAPI->type : '';
            $is_book_chapter = ($doi_type === 'book-chapter' || $doi_type === 'chapter' || $doi_type === 'book-section');
            if ($crossRef->volume_title && ($template->blank(WORK_ALIASES) || $template->wikiname() === 'cite book' || ($is_book_chapter && !in_array($template->wikiname(), NO_CHAPTER_ADD)))) {
                if (mb_strtolower($template->get('title')) === mb_strtolower((string) $crossRef->article_title)) {
                    $template->rename('title', 'chapter');
                } else {
                    if ($new_title !== '' && $crossRef->article_title) {
                        $template->add_if_new('chapter', $new_title, 'crossref');
                    } else {
                        $template->add_if_new('chapter', restore_italics((string) $crossRef->article_title), 'crossref');
                    }
                }
                $template->add_if_new('title', restore_italics((string) $crossRef->volume_title), 'crossref'); // add_if_new will wikify title and sanitize the string
            } else {
                if ($new_title !== '' && $crossRef->article_title) {
                    $template->add_if_new('title', $new_title, 'crossref');
                } else {
                    $template->add_if_new('title', restore_italics((string) $crossRef->article_title), 'crossref');
                }
            }
            $template->add_if_new('series', (string) $crossRef->series_title, 'crossref'); // add_if_new will format the title for a series?
            if (mb_strpos($doi, '10.7817/jameroriesoci') === false || (string) $crossRef->year !== '2021') { // 10.7817/jameroriesoci "re-published" everything in 2021
                $template->add_if_new("year", (string) $crossRef->year, 'crossref');
            }
            if ($template->blank(['editor', 'editor1', 'editor-last', 'editor1-last', 'editor-last1']) // If editors present, authors may not be desired
                    && $crossRef->contributors->contributor
                ) {
                $au_i = 0;
                $ed_i = 0;
                // Check to see whether a single author is already set
                // This might be, for example, a collaboration
                $existing_author = $template->first_author();
                $add_authors = $existing_author === '' || author_is_human($existing_author);

                foreach ($crossRef->contributors->contributor as $author) {
                    if (mb_strtoupper((string) $author->surname) === '&NA;') {
                        break; // No Author, leave loop now!  Have only seen upper-case in the wild
                    }
                    if ((string) $author["contributor_role"] === 'editor') {
                        ++$ed_i;
                        $ed_i_str = (string) $ed_i;
                        if ($ed_i < 31 && !isset($crossRef->journal_title)) {
                            $template->add_if_new("editor-last" . $ed_i_str, format_surname((string) $author->surname), 'crossref');
                            $template->add_if_new("editor-first" . $ed_i_str, format_forename((string) $author->given_name), 'crossref');
                        }
                    } elseif ((string) $author['contributor_role'] === 'author' && $add_authors) {
                        ++$au_i;
                        $au_i_str = (string) $au_i;
                        if ((string) $author->surname === 'Editor' && (string) $author->given_name === 'The') {
                            $template->add_if_new("author" . $au_i_str, 'The Editor', 'crossref');
                        } else {
                            $template->add_if_new("last" . $au_i_str, format_surname((string) $author->surname), 'crossref');
                            $template->add_if_new("first" . $au_i_str, format_forename((string) $author->given_name), 'crossref');
                        }
                    }
                }
            }
            $template->add_if_new('isbn', (string) $crossRef->isbn, 'crossref');
            $template->add_if_new('journal', (string) $crossRef->journal_title); // add_if_new will format the title
            if ((int) $crossRef->volume > 0) {
                $template->add_if_new('volume', (string) $crossRef->volume, 'crossref');
            }
            if (((mb_strpos((string) $crossRef->issue, '-') > 0 || (int) $crossRef->issue > 1))) {
                // "1" may refer to a journal without issue numbers,
                //  e.g. 10.1146/annurev.fl.23.010191.001111, as well as a genuine issue 1.    Best ignore.
                $template->add_if_new('issue', (string) $crossRef->issue, 'crossref');
            }
            if ($template->blank("page")) {
                if ($crossRef->last_page && (strcmp((string) $crossRef->first_page, (string) $crossRef->last_page) !== 0)) {
                    if (mb_strpos((string) $crossRef->first_page . (string) $crossRef->last_page, '-') === false) { // Very rarely get stuff like volume/issue/year added to pages
                        $template->add_if_new("pages", $crossRef->first_page . "-" . $crossRef->last_page, 'crossref'); //replaced by an endash later in script
                    }
                } else {
                    if (mb_strpos((string) $crossRef->first_page, '-') === false) { // Very rarely get stuff like volume/issue/year added to pages
                        $template->add_if_new("pages", (string) $crossRef->first_page, 'crossref');
                    }
                }
            }
            if (isset($crossRefNewAPI->{"article-number"})) {
                $template->add_if_new("article-number", (string) $crossRefNewAPI->{"article-number"});
            }
        } else {
            report_info("No CrossRef record found for doi '" . echoable($doi) . "'");
            expand_doi_with_dx($template, $doi);
        }
    }
    return;
}

function query_crossref(string $doi): ?object {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.0, []);
    }
    if (mb_strpos($doi, '10.2307') === 0) {
        return null; // jstor API is better
    }
    set_time_limit(120);
    $url = "https://www.crossref.org/openurl/?pid=" . CROSSREFUSERNAME . "&id=doi:" . doi_encode($doi) . "&noredirect=TRUE";
    curl_setopt($ch, CURLOPT_URL, $url);
    for ($i = 0; $i < 2; $i++) {
        $raw_xml = bot_curl_exec($ch);
        if (!$raw_xml) {
            sleep(1);                // @codeCoverageIgnore
            continue;                // @codeCoverageIgnore
            // Keep trying...
        }
        $raw_xml = preg_replace(
            '~(\<year media_type=\"online\"\>\d{4}\<\/year\>\<year media_type=\"print\"\>)~',
                    '<year media_type="print">',
                    $raw_xml);
        $xml = @simplexml_load_string($raw_xml);
        unset($raw_xml);
        if (is_object($xml) && isset($xml->query_result->body->query)) {
            $result = $xml->query_result->body->query;
            if ((string) @$result["status"] === "resolved") {
                if (mb_stripos($doi, '10.1515/crll') === 0) {
                    $volume = intval(mb_trim((string) @$result->volume));
                    if ($volume > 1820) {
                        if (isset($result->issue)) {
                            /** @psalm-suppress UndefinedPropertyAssignment */
                            $result->volume = $result->issue;
                            unset($result->issue);
                        } else {
                            unset($result->volume);
                        }
                    }
                }
                if (mb_stripos($doi, '10.3897/ab.') === 0) {
                    unset($result->volume);
                    unset($result->page);
                    unset($result->issue);
                }
                return $result;
            } else {
                return null;
            }
        } else {
            sleep(1);                // @codeCoverageIgnore
            // Keep trying...
        }
    }
    report_warning("Error loading CrossRef file from DOI " . echoable($doi) . "!");      // @codeCoverageIgnore
    return null;                                                                        // @codeCoverageIgnore
}

function expand_doi_with_dx(Template $template, string $doi): void {
    // See https://crosscite.org/docs.html for discussion of API we are using -- not all agencies resolve the same way
    // https://api.crossref.org/works/$doi can be used to find out the agency
    // https://www.doi.org/registration_agencies.html  https://www.doi.org/RA_Coverage.html List of all ten doi granting agencies - many do not do journals
    // Examples of DOI usage  https://www.doi.org/demos.html
    // This basically does this:
    // curl -LH "Accept: application/vnd.citationstyles.csl+json" https://dx.doi.org/10.5524/100077
    // Data Quality is CrossRef > DX.doi.org > Zotero
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.5, // can take a long time when nothing to be found
        [CURLOPT_HTTPHEADER => ["Accept: application/vnd.citationstyles.csl+json"]]);
    }
    if (mb_strpos($doi, '10.2307') === 0 || // jstor API is better
        mb_strpos($doi, '10.24436') === 0 || // They have horrible meta-data
        mb_strpos($doi, '10.5284/1028203') === 0) { // database
        return;
    }
    set_time_limit(120);
    /** @psalm-taint-escape ssrf */
    $doi = mb_trim($doi);
    if (!$doi) {
        return;
    }
    curl_setopt($ch, CURLOPT_URL, 'https://doi.org/' . $doi);
    report_action("Querying dx.doi.org: doi:" . doi_link($doi));
    try {
        $data = bot_curl_exec($ch);
    } catch (Exception) {          // @codeCoverageIgnoreStart
        $template->mark_inactive_doi();
        return;
    }                      // @codeCoverageIgnoreEnd
    if ($data === "" || mb_stripos($data, 'DOI Not Found') !== false || mb_stripos($data, 'DOI prefix') !== false) {
        $template->mark_inactive_doi();
        return;
    }
    $json = @json_decode($data, true);
    unset($data);
    if ($json === false || $json === null) {
        return;
    }
    process_doi_json($template, $doi, $json);
}

/**
 * @param Template $template
 * @param string $doi
 * @param array<string|int|array<string|int|array<string|int|array<string|int|array<string|int>>>>> $json
 */
function process_doi_json(Template $template, string $doi, array $json): void {
    /** @param array|string|int|null $data */
    $try_to_add_it = static function (string $name, $data) use($template): void {
        if ($template->has($name)) {
            return; // Not worth updating based upon DX
        }
        if (is_null($data)) {
            return;
        }
        while (is_array($data)) {
            if (!isset($data['0']) || isset($data['1'])) {
                return;
            }
            $data = $data['0'];
        }
        $data = (string) $data;
        if ($data === '') {
            return;
        }
        if ($data === 'Array') {
            return;
        }
        if ($data === 'Unknown') {
            return;
        }
        if (str_ends_with(mb_strtolower($data), '.pdf')) {
            return;
        }
        if (mb_strpos($name, 'author') !== false) { // Remove dates from names from 10.11501/ dois
            if (preg_match('~^(.+), \d{3,4}\-\d{3,4}$~', $data, $matched)) {
                $data = $matched[1];
            }
            if (preg_match('~^(.+), \d{3,4}\-$~', $data, $matched)) {
                $data = $matched[1];
            }
            if (preg_match('~^(.+), \-\d{3,4}$~', $data, $matched)) {
                $data = $matched[1];
            }
        }
        $template->add_if_new($name, $data, 'dx');
        return;
    };
    // BE WARNED:  this code uses the "@$var" method.
    // If the variable is not set, then PHP just passes null, then that is interpreted as a empty string
    if ($template->blank(['date', 'year'])) {
        $try_to_add_it('year', @$json['issued']['date-parts']['0']['0']);
        $try_to_add_it('year', @$json['created']['date-parts']['0']['0']);
        $try_to_add_it('year', @$json['published-print']['date-parts']['0']['0']);
    }
    $try_to_add_it('issue', @$json['issue']);
    $try_to_add_it('pages', @$json['pages']);
    $try_to_add_it('page', @$json['pages']);
    $try_to_add_it('volume', @$json['volume']);
    $try_to_add_it('isbn', @$json['ISBN']['0']);
    $try_to_add_it('isbn', @$json['isbn-type']['value']);
    $try_to_add_it('isbn', @$json['isbn-type']['0']['value']);
    if (isset($json['author'])) {
        $i = 0;
        foreach ($json['author'] as $auth) {
            $i += 1;
            $full_name = mb_strtolower(mb_trim((string) @$auth['given'] . ' ' . (string) @$auth['family'] . (string) @$auth['literal']));
            if (in_array($full_name, BAD_AUTHORS, true)) {
                break;
            }
            if (((string) @$auth['family'] === '') && ((string) @$auth['given'] !== '')) {
                $try_to_add_it('author' . (string) $i, @$auth['given']); // First name without last name.  Probably an organization or chinese/korean/japanese name
            } else {
                $try_to_add_it('last' . (string) $i, @$auth['family']);
                $try_to_add_it('first' . (string) $i, @$auth['given']);
                $try_to_add_it('author' . (string) $i, @$auth['literal']);
            }
        }
    }
    if (isset($json['editor']) && $template->wikiname() !== 'cite journal') {
        $i = 0;
        foreach ($json['editor'] as $auth) {
            $i += 1;
            $full_name = mb_strtolower(mb_trim((string) @$auth['given'] . ' ' . (string) @$auth['family'] . (string) @$auth['literal']));
            if (in_array($full_name, BAD_AUTHORS, true)) {
                break;
            }
            if (((string) @$auth['family'] === '') && ((string) @$auth['given'] !== '')) {
                $try_to_add_it('editor' . (string) $i, @$auth['given']); // First name without last name.  Probably an organization or chinese/korean/japanese name
            } else {
                $try_to_add_it('editor-last' . (string) $i, @$auth['family']);
                $try_to_add_it('editor-first' . (string) $i, @$auth['given']);
                $try_to_add_it('editor' . (string) $i, @$auth['literal']);
            }
        }
    }
    // Publisher hiding as journal name - defective data
    if (isset($json['container-title']) && isset($json['publisher']) && ($json['publisher'] === $json['container-title'])) {
        unset($json['container-title']);   // @codeCoverageIgnore
    }

    $type = (string) @$json['type'];
    if ($type === 'article-journal' ||
            $type === 'journal-article' ||
            $type === 'article' ||
            $type === 'proceedings-article' ||
            $type === 'conference-paper' ||
            $type === 'entry' ||
            ($type === '' && (isset($json['container-title']) || isset($json['issn']['0'])))) {
        $try_to_add_it('journal', @$json['container-title']);
        $try_to_add_it('title', @$json['title']);
        $try_to_add_it('issn', @$json['issn']); // Will not add if journal is set
    } elseif ($type === 'journal-issue') { // Very rare: Do not add "title": should be blank anyway.  Got this once from DOI:10.7592/fejf2015.62
        $try_to_add_it('journal', @$json['container-title']);  // @codeCoverageIgnore
        $try_to_add_it('issn', @$json['issn']);          // @codeCoverageIgnore
    } elseif ($type === 'journal') { // Very rare: Do not add "title": should be blank anyway.  Got this once from DOI:10.1007/13539.2190-6009 and DOI:10.14296/rih/issn.1749.8155
        $try_to_add_it('issn', @$json['issn']);          // @codeCoverageIgnore
    } elseif ($type === 'reference-entry' || $type === 'component') { // Very rare: reference-entry from 10.1002/14356007.a02_115.pub2, component from 10.3998/mpub.11422327.cmp.11
        $try_to_add_it('work', @$json['container-title']);    // @codeCoverageIgnore
        $try_to_add_it('title', @$json['title']);        // @codeCoverageIgnore
    } elseif ($type === 'monograph' || $type === 'book' || $type === 'edited-book') {
        $try_to_add_it('title', @$json['title']);
        $try_to_add_it('title', @$json['container-title']);// Usually not set, but just in case this instead of title is set
        $try_to_add_it('location', @$json['publisher-location']);
        $try_to_add_it('publisher', @$json['publisher']);
    } elseif ($type === 'reference-book') { // VERY rare
        $try_to_add_it('title', @$json['title']);          // @codeCoverageIgnore
        $try_to_add_it('title', @$json['container-title']);      // @codeCoverageIgnore
        $try_to_add_it('chapter', @$json['original-title']);    // @codeCoverageIgnore
        $try_to_add_it('location', @$json['publisher-location']); // @codeCoverageIgnore
        $try_to_add_it('publisher', @$json['publisher']);      // @codeCoverageIgnore
    } elseif ($type === 'chapter' || $type === 'book-chapter' || $type === 'book-section') {
        $try_to_add_it('title', @$json['container-title']);
        $try_to_add_it('chapter', @$json['title']);
        $try_to_add_it('location', @$json['publisher-location']);
        $try_to_add_it('publisher', @$json['publisher']);
    } elseif ($type === 'other') {
        if (isset($json['container-title'])) {
            $try_to_add_it('title', @$json['container-title']);
            $try_to_add_it('chapter', @$json['title']);
        } else {
            $try_to_add_it('title', @$json['title']);
        }
        $try_to_add_it('location', @$json['publisher-location']);
        $try_to_add_it('publisher', @$json['publisher']);
    } elseif ($type === 'dataset') {
        $try_to_add_it('type', 'Data Set');
        $try_to_add_it('title', @$json['title']);
        $try_to_add_it('location', @$json['publisher-location']);
        $try_to_add_it('publisher', @$json['publisher']);
        if (!isset($json['categories']['1']) &&
                (($template->wikiname() === 'cite book') || $template->blank(WORK_ALIASES))) { // No journal/magazine set and can convert to book
            $try_to_add_it('chapter', @$json['categories']['0']);  // Not really right, but there is no cite data set template
        }
    } elseif ($type === '' || $type === 'graphic' || $type === 'report' || $type === 'report-component') {  // Add what we can where we can
        $try_to_add_it('title', @$json['title']);
        $try_to_add_it('location', @$json['publisher-location']);
        $try_to_add_it('publisher', @$json['publisher']);
    } elseif ($type === 'thesis' || $type === 'dissertation' || $type === 'dissertation-thesis') {
        $template->change_name_to('cite thesis');
        $try_to_add_it('title', @$json['title']);
        $try_to_add_it('location', @$json['publisher-location']);
        $try_to_add_it('publisher', @$json['publisher']);
        if (mb_stripos(@$json['URL'], 'hdl.handle.net')) {
            $template->get_identifiers_from_url($json['URL']);
        }
    } elseif ($type === 'standard') {
        $try_to_add_it('title', @$json['title']);
        $try_to_add_it('location', @$json['publisher-location']);
        $try_to_add_it('publisher', @$json['standards-body']['name']);
        $try_to_add_it('publisher', @$json['publisher']);
        if (mb_stripos(@$json['URL'], 'hdl.handle.net')) {
            $template->get_identifiers_from_url($json['URL']);
        }
    } elseif ($type === 'posted-content' || $type === 'grant' || $type === 'song' || $type === 'motion_picture' || $type === 'patent' || $type === 'database') { // posted-content is from bioRxiv
        $try_to_add_it('title', @$json['title']);
    } else {
        $try_to_add_it('title', @$json['title']);                          // @codeCoverageIgnore
        if (!HTML_OUTPUT) {
            print_r($json);                              // @codeCoverageIgnore
        }
        report_minor_error('DOI returned unexpected data type ' . echoable($type) . ' for ' . doi_link($doi));    // @codeCoverageIgnore
    }
    return;
}

/**
 * @todo look at using instead https://doi.crossref.org/openurl/?pid=email@address.com&id=doi:10.1080/00222938700771131&redirect=no&format=unixref This API can get article numbers in addition to page numbers. Will need to use exist DX code, and add all the extra checks cross ref code has
 */
function query_crossref_newapi(string $doi): object {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.0,
            [CURLOPT_USERAGENT => BOT_CROSSREF_USER_AGENT]);
    }
    $url = "https://api.crossref.org/v1/works/" . doi_encode($doi) . "?mailto=" . CROSSREFUSERNAME;
    curl_setopt($ch, CURLOPT_URL, $url);
    $json = bot_curl_exec($ch);
    $json = @json_decode($json);

    if (is_object($json) && isset($json->message) && isset($json->status) && (string) $json->status === "ok") {
        $result = $json->message;
    } else {
        sleep(2);  // @codeCoverageIgnore
        return new stdClass(); // @codeCoverageIgnore
    }

    // A bunch of stuff we will never use - make dubug messages and memory smaller

    unset(  $json, $result->reference, $result->assertion, $result->{'reference-count'},
            $result->deposited, $result->link, $result->{'update-policy'}, $result->{'is-referenced-by-count'},
            $result->{'published-online'}, $result->member, $result->score, $result->prefix, $result->source,
            $result->abstract, $result->URL, $result->relation, $result->{'content-domain'},
            $result->{'short-container-title'}, $result->license,
            $result->indexed, $result->{'references-count'}, $result->resource,
            $result->subject, $result->language);

    return $result;
}

function get_doi_from_crossref(Template $template): void {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.0, [CURLOPT_USERAGENT => BOT_CROSSREF_USER_AGENT]);
    }
    set_time_limit(120);
    if ($template->has('doi')) {
        return;
    }
    report_action("Checking CrossRef database for doi. ");
    $page_range = $template->page_range();
    $data = [
        'title' => de_wikify($template->get('title')),
        'journal' => de_wikify($template->get('journal')),
        'author' => $template->first_surname(),
        'year' => (int) preg_replace("~([12]\d{3}).*~", "$1", $template->year()),
        'volume' => $template->get('volume'),
        'start_page' => (string) @$page_range[1],
        'end_page' => (string) @$page_range[2],
        'issn' => $template->get('issn'),
    ];

    if ($data['year'] < 1900 || $data['year'] > (int) date("Y") + 3) {
        $data['year'] = null;
    } else {
        $data['year'] = (string) $data['year'];
    }
    if ((int) $data['end_page'] < (int) $data['start_page']) {
        $data['end_page'] = null;
    }

    $novel_data = false;
    foreach ($data as $key => $value) {
        if ($value) {
            if (!$template->api_has_used('crossref', equivalent_parameters($key))) {
                $novel_data = true;
            }
            $template->record_api_usage('crossref', $key);
        }
    }

    if (!$novel_data) {
        return;
    }
    // They already allow some fuzziness in matches
    if (($data['journal'] || $data['issn']) && ($data['start_page'] || $data['author'])) {
        /** @psalm-taint-escape ssrf */
        $url =
        "https://www.crossref.org/openurl/?noredirect=TRUE&pid=" .
        CROSSREFUSERNAME .
        ($data['title'] ? "&atitle=" . urlencode($data['title']) : '') .
        ($data['author'] ? "&aulast=" . urlencode($data['author']) : '') .
        ($data['start_page'] ? "&spage=" . urlencode($data['start_page']) : '') .
        ($data['end_page'] ? "&epage=" . urlencode($data['end_page']) : '') .
        ($data['year'] ? "&date=" . urlencode($data['year']) : '') .
        ($data['volume'] ? "&volume=" . urlencode($data['volume']) : '') .
        ($data['issn'] ? "&issn=" . urlencode($data['issn']) : "&title=" . urlencode($data['journal'])) .
        "&mailto=" .
        CROSSREFUSERNAME; // do not encode crossref email
        curl_setopt($ch, CURLOPT_URL, $url);
        $xml = bot_curl_exec($ch);
        if (mb_strlen($xml) > 0) {
            $result = @simplexml_load_string($xml);
            unset($xml);
        } else {
            $result = false;
        }
        if ($result === false) {
            report_warning("Error loading simpleXML file from CrossRef."); // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        }
        if (!isset($result->query_result->body->query)) {
            report_warning("Unexpected simpleXML file from CrossRef."); // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        }
        $result = $result->query_result->body->query;
        if ((string) $result->attributes()->status === 'malformed') {
            report_minor_error("Cannot search CrossRef: " . echoable((string) $result->msg)); // @codeCoverageIgnore
        } elseif ((string) $result->attributes()->status === "resolved") {
            if (!isset($result->doi)) {
                return;
            }
            $doi = (string) $result->doi;
            if (in_array($doi, BAD_DOIS_FROM_CROSSREF, true)) {
                return;
            }
            report_inline(" Successful!");
            $template->add_if_new('doi', $doi);
            return;
        }
    }
    return;
}

/**
 * Check if bioRxiv/medRxiv preprint published via bioRxiv API.
 *
 * @param string $doi DOI (10.1101/* or 10.64898/*)
 * @return string|null Published DOI or null
 */
function get_biorxiv_published_doi(string $doi): ?string {
    if (mb_strpos($doi, '10.1101/') !== 0 && mb_strpos($doi, '10.64898/') !== 0) {
        return null;
    }

    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.0,
            [CURLOPT_USERAGENT => BOT_CROSSREF_USER_AGENT]);
    }

    $server = 'biorxiv';
    $url = "https://api.biorxiv.org/details/" . $server . "/" . $doi;
    curl_setopt($ch, CURLOPT_URL, $url);
    $json = bot_curl_exec($ch);
    $data = @json_decode($json);

    if (!is_object($data)) {
        return null;
    }

    if (isset($data->collection) && is_array($data->collection) && count($data->collection) > 0) {
        $article = $data->collection[0];
        if (isset($article->published_doi) && $article->published_doi !== '' && $article->published_doi !== null) {
            $published_doi = (string) $article->published_doi;
            $is_biorxiv_doi = (mb_strpos($published_doi, '10.1101/') === 0);
            $is_alt_biorxiv_doi = (mb_strpos($published_doi, '10.64898/') === 0);
            if (!$is_biorxiv_doi && !$is_alt_biorxiv_doi) {
                return $published_doi;
            }
        }
    }

    return null;
}
