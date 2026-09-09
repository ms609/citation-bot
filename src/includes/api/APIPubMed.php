<?php

declare(strict_types=1);

/**
 * @param array<string> $pmids
 * @param array<Template> &$templates
 */
function query_pmid_api (array $pmids, array &$templates): void {  // Pointer to save memory
    entrez_api($pmids, $templates, 'pubmed');
}

/**
 * @param array<string> $pmcs
 * @param array<Template> &$templates
 */
function query_pmc_api (array $pmcs, array &$templates): void {  // Pointer to save memory
    entrez_api($pmcs, $templates, 'pmc');
}

function pubmed_item_name(SimpleXMLElement $item): string {
    return (string) $item['Name'];
}

function pubmed_document_has_items(SimpleXMLElement $document): bool {
    return isset($document->Item) && count($document->Item) > 0;
}

function pubmed_document_title(SimpleXMLElement $document): ?string {
    if (!pubmed_document_has_items($document)) {
        return null;
    }
    foreach ($document->Item as $item) {
        if (pubmed_item_name($item) === 'Title') {
            $title = str_replace(["[", "]"], "", (string) $item);
            return $title === '' ? null : $title;
        }
    }
    return null;
}

/**
 * @param array<string> $ids
 * @param array<Template> &$templates
 */
function entrez_api(array $ids, array &$templates, string $db): void {    // Pointer to save memory
    set_time_limit(120);
    /* idx is also the index into the template array */
    foreach ($ids as $idx => $value) {
        if (!preg_match('~^\d+$~', $value) || $value === '1' || $value === '0') {
            unset($ids[$idx]);
        }
    }
    unset($idx, $value);
    if (!count($ids)) {
        return;
    }
    if ($db !== 'pubmed' && $db !== 'pmc') {
        report_error("Invalid Entrez type passed in: " . echoable($db));  // @codeCoverageIgnore
    }

    report_action("Using {$db} API to retrieve publication details: ");
    $xml = get_entrez_xml($db, implode(',', $ids));

    if ($xml === null) {
        report_warning("Error in PubMed search: No response from Entrez server");
        return;
    }

    // A few PMC do not have any data, just pictures of stuff
    if (isset($xml->DocSum) && count($xml->DocSum) > 0) {
        foreach ($xml->DocSum as $document) {
            if (!pubmed_document_has_items($document)) {
                continue;
            }
            report_info("Found match for {$db} identifier " . echoable((string) $document->Id));
            foreach ($ids as $template_key => $an_id) { // Cannot use array_search since that only returns first
                $an_id = (string) $an_id;
                if (!array_key_exists($template_key, $templates)) {
                    bot_debug_log('Key not found in entrez_api ' . (string) $template_key . ' ' . $an_id);
                    $an_id = '-3333';
                }
                if ($an_id === (string) $document->Id) {
                    $this_template = $templates[$template_key];
                    $this_template->record_api_usage('entrez', $db === 'pubmed' ? 'pmid' : 'pmc');

                    foreach ($document->Item as $item) {
                        if (preg_match("~10\.\d{4}/[^\s\"']*~", (string) $item, $match)) {
                            $this_template->add_if_new('doi', $match[0], 'entrez');
                        }
                        switch ($item["Name"]) {
                            case "Title":
                                $this_template->add_if_new('title', str_replace(["[", "]"], "", (string) $item), 'entrez'); // add_if_new will format the title
                                break;
                            case "PubDate":
                                if (preg_match("~(\d+)\s*(\w*)~", (string) $item, $match)) {
                                    $this_template->add_if_new('year', $match[1], 'entrez');
                                }
                                break;
                            case "FullJournalName":
                                $this_template->add_if_new('journal', mb_ucwords((string) $item), 'entrez'); // add_if_new will format the title
                                break;
                            case "Volume":
                                $this_template->add_if_new('volume', (string) $item, 'entrez');
                                break;
                            case "Issue":
                                $this_template->add_if_new('issue', (string) $item, 'entrez');
                                break;
                            case "Pages":
                                $this_template->add_if_new('pages', (string) $item, 'entrez');
                                break;
                            case "PmId":
                                $this_template->add_if_new('pmid', (string) $item, 'entrez');
                                break;
                            case "AuthorList":
                                $i = 0;
                                foreach ($item->Item as $subItem) {
                                    $item_name = pubmed_item_name($subItem);
                                    $subItem = (string) $subItem;
                                    if (preg_match('~^\d~', $subItem)) { // Author started with a number, skip all remaining authors.
                                        break;    // @codeCoverageIgnore
                                    } elseif ($item_name === "CollectiveName") { // Do not treat a study group as a person.
                                        break;    // @codeCoverageIgnore
                                    } elseif (mb_strlen($subItem) > 100) {
                                        break;    // @codeCoverageIgnore
                                    } elseif (author_is_human($subItem)) {
                                        $suffix_test = junior_test($subItem);
                                        $subItem = $suffix_test[0];
                                        $junior = $suffix_test[1];
                                        if (preg_match("~(.*) (\w+)$~", $subItem, $names)) {
                                            $first = mb_trim(preg_replace('~(?<=[A-Z])([A-Z])~', ". $1", $names[2]));
                                            if ($first !== '' && mb_substr($first, -1) !== '.' && (mb_strpos($first, '.') !== false || $junior !== '')) {
                                                $first .= '.';
                                            }
                                            $i++;
                                            $this_template->add_if_new("author{$i}", $names[1] . ',' . $first . $junior, 'entrez');
                                        }
                                    } else {
                                        // We probably have a committee or similar.    Just use 'author$i'.
                                        $i++;
                                        $this_template->add_if_new("author{$i}", $subItem, 'entrez');
                                    }
                                }
                                break;
                            case "LangList":
                            case 'ISSN':
                                break;
                            case "ArticleIds":
                                // Buffer the embargo date: add() tidies immediately, so adding
                                // it before pmc= would drop it as an orphan.  Flushed below
                                // once pmc= has had its chance.
                                $embargo_date = ''; // @codeCoverageIgnore
                                foreach ($item->Item as $subItem) {
                                    switch ($subItem["Name"]) {
                                        case "pubmed":
                                        case "pmid":
                                            if (preg_match("~\d+~", (string) $subItem, $match)) {
                                                $this_template->add_if_new("pmid", $match[0], 'entrez');
                                            }
                                            break;
                                        case "pmc":
                                            if (preg_match("~\d+~", (string) $subItem, $match)) {
                                                $this_template->add_if_new('pmc', $match[0], 'entrez');
                                            }
                                            break;
                                        case "pmcid":
                                            if (preg_match("~embargo-date: ?(\d{4})\/(\d{2})\/(\d{2})~", (string) $subItem, $match)) {
                                                $embargo_ts = mktime(0, 0, 0, (int) $match[2], (int) $match[3], (int) $match[1]); // @codeCoverageIgnore
                                                if ($embargo_ts !== false) {                                                 // @codeCoverageIgnore
                                                    $date_emb = date("F j, Y", $embargo_ts);                                  // @codeCoverageIgnore
                                                    // Buffer first-wins like sequential adds, but only dates
                                                    // the add gate would accept: a past date must not block
                                                    // a later future one.
                                                    if ($embargo_date === '' && $embargo_ts >= strtotime('now')) {            // @codeCoverageIgnore
                                                        $embargo_date = $date_emb;                                            // @codeCoverageIgnore
                                                    }                                                                         // @codeCoverageIgnore
                                                }                                                                              // @codeCoverageIgnore
                                            }
                                            break;
                                        case "doi":
                                        case "pii":
                                            if (preg_match("~10\.\d{4}/[^\s\"']*~", (string) $subItem, $match)) {
                                                $this_template->add_if_new('doi', $match[0], 'entrez');
                                            }
                                    }
                                }
                                if ($embargo_date !== '') { // @codeCoverageIgnore
                                    $this_template->add_if_new('pmc-embargo-date', $embargo_date, 'entrez'); // @codeCoverageIgnore
                                } // @codeCoverageIgnore
                                break;
                        }
                    }
                }
            }
        }
    }
    return;
}

function get_entrez_xml(string $type, string $query): ?SimpleXMLElement {
    $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/";
    $request = NLM_LOGIN;
    if ($type === "esearch_pubmed") {
        $url  .= "esearch.fcgi";
        $request .= "&db=pubmed&term=" . $query;
    } elseif ($type === "pubmed") {
        $url .= "esummary.fcgi";
        $request .= "&db=pubmed&id=" . $query;
    } elseif ($type === "pmc") {
        $url .= "esummary.fcgi";
        $request .= "&db=pmc&id=" . $query;
    } else {
        report_error("Invalid type passed to get_entrez_xml: " . echoable($type));  // @codeCoverageIgnore
    }
    return xml_post($url, $request);
}

function parse_entrez_xml_response(string $output): ?SimpleXMLElement {
    if ($output === '') {
        return null;
    }
    try {
        $xml = @simplexml_load_string(
            $output,
            SimpleXMLElement::class,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
    } catch (Throwable) {
        return null;
    }
    if ($xml === false || !in_array($xml->getName(), ['eSummaryResult', 'eSearchResult'], true)) {
        return null;
    }
    return $xml;
}

/**
 * Must use post in order to get DOIs with <, >, [, and ] in them and other problems
 * @param non-empty-string $url
 */
function xml_post(string $url, string $post): ?SimpleXMLElement {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.0, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/x-www-form-urlencoded", "Accept: application/xml"],
        ], 16 * 1024 * 1024);
    }
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POSTFIELDS => $post,
    ]);
    try {
        $output = bot_curl_exec($ch);
    } catch (Throwable $e) {
        bot_debug_log('Entrez request failed: ' . $e::class . ': ' . $e->getMessage());
        report_warning("Entrez request failed; continuing without PubMed metadata.");
        return null;
    }
    $xml = parse_entrez_xml_response($output);
    if ($xml === null) {
        sleep(1);
        return null;
    }
    return $xml;
}

function find_pmid(Template $template): void {
    set_time_limit(120);
    if (!$template->blank('pmid')) {
        return;
    }
    report_action("Searching PubMed... ");
    $results = query_pubmed($template);
    if ($results[1] === 1) {
        // Double check title if we did not use DOI
        if ($template->has('title') && !in_array('doi', $results[2], true)) {
            usleep(100000); // Wait 1/10 of a second since we just tried
            $xml = get_entrez_xml('pubmed', $results[0]);
            if ($xml === null || !isset($xml->DocSum) || count($xml->DocSum) !== 1) {
                report_inline("Unable to query PubMed.");
                return;
            }
            $new_title = pubmed_document_title($xml->DocSum[0]);
            if ($new_title === null) {
                report_inline("Unable to verify PubMed title.");
                return;
            }
            foreach (THINGS_THAT_ARE_TITLES as $possible) {
                if ($template->has($possible) && titles_are_similar($template->get($possible), $new_title)) {
                    $template->add_if_new('pmid', $results[0]);
                    return;
                }
            }
            // @codeCoverageIgnoreStart
            report_inline("Similar matching PubMed title not similar enough. Rejected: " . pubmed_link('pmid', $results[0]));
            return;
            // @codeCoverageIgnoreEnd
        }
        $template->add_if_new('pmid', $results[0]);
    } else {
        report_inline("Nothing found.");
    }
}

/** @return array{0: string, 1: int, 2: array<string>} */
function query_pubmed(Template $template): array {
    /*
    * Performs a search based on article data, using the DOI preferentially, and failing that, the rest of the article details.
    * Returns an array:
    * [0] => PMID of first matching result
    * [1] => total number of results
    */
    $doi = $template->get_without_comments_and_placeholders('doi');
    if ($doi) {
        if (doi_works($doi)) {
            $results = do_pumbed_query($template, ["doi"]);
            if ($results[1] !== 0) {
                return $results;
            } // If more than one, we are doomed
        }
    }
    // If we've got this far, the DOI was unproductive or there was no DOI.

    if ($template->has('journal') && $template->has('volume') && $template->page_range()) {
        $results = do_pumbed_query($template, ["journal", "volume", "issue", "page"]);
        if ($results[1] === 1) {
            return $results;
        }
    }
    $is_book = citationLooksLikeBook($template);
    if ($template->has('title') && $template->first_surname() && !$is_book) {
        $results = do_pumbed_query($template, ["title", "surname", "year", "volume"]);
        if ($results[1] === 1) {
            return $results;
        }
        if ($results[1] > 1) {
            $results = do_pumbed_query($template, ["title", "surname", "year", "volume", "issue"]);
            if ($results[1] === 1) {
                  return $results;
            }
        }
    }
    return ['', 0, []];
}

/**
 * @param Template $template
 * @param array<string> $terms
 * @return array{0: string, 1: int, 2: array<string>}
 */
function do_pumbed_query(Template $template, array $terms): array {
    set_time_limit(120);
    /* do_query
    *
    * Searches pubmed based on terms provided in an array.
    * Provide an array of wikipedia parameters which exist in $p, and this will construct a Pubmed search query and
    * return the results as array (first result, # of results)
    */
    $key_index = [
        'issue' => 'Issue',
        'journal' => 'Journal',
        'pmid' => 'PMID',
        'volume' => 'Volume',
    ];
    $query = '';
    foreach ($terms as $term) {
        $term = mb_strtolower($term);
        if ($term === "title") {
            $data = $template->get_without_comments_and_placeholders('title');
            if ($data) {
                $key = 'Title';
                $data = straighten_quotes($data, true);
                $data = str_replace([';', ',', ':', '.', '?', '!', '&', '/', '(', ')', '[', ']', '{', '}', '"', "'", '|', '\\'], [' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' '], $data);
                $data = strip_diacritics($data);
                $data_array = explode(" ", $data);
                foreach ($data_array as $val) {
                    if (!in_array(mb_strtolower($val), SHORT_STRING, true) && mb_strlen($val) > 3) {
                        // Small words are NOT indexed
                        $query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($val)) . "[{$key}])";
                    }
                }
            }
        } elseif ($term === "page") {
            $pages = $template->page_range();
            if ($pages) {
                  $val = $pages[1];
                  $key = 'Pagination';
                  $query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($val)) . "[{$key}])";
            }
        } elseif ($term === "surname") {
            $val = $template->first_surname();
            if ($val) {
                $key = 'Author';
                $query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($val)) . "[{$key}])";
            }
        } elseif ($term === "year") {
            $key = 'Publication Date';
            $val = $template->year();
            if ($val) {
                $query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($val)) . "[{$key}])";
            }
        } elseif ($term === "doi") {
            $key = 'AID';
            $val = $template->get_without_comments_and_placeholders($term);
            if ($val) {
                  $query .= " AND (" . "\"" . str_replace(["%E2%80%93", ';'], ["-", '%3B'], $val) . "\"" . "[{$key}])"; // PubMed does not like escaped /s in DOIs, but other characters seem problematic.
            }
        } else {
            $key = $key_index[$term]; // Will crash if bad data is passed
            $val = $template->get_without_comments_and_placeholders($term);
            if ($val) {
                if (preg_match(REGEXP_PLAIN_WIKILINK, $val, $matches)) {
                    $val = $matches[1]; // @codeCoverageIgnore
                } elseif (preg_match(REGEXP_PIPED_WIKILINK, $val, $matches)) {
                    $val = $matches[2]; // @codeCoverageIgnore
                }
                $val = strip_diacritics($val);
                $val = straighten_quotes($val, true);
                $query .= " AND (" . str_replace("%E2%80%93", "-", urlencode($val)) . "[{$key}])";
            }
        }
    }
    $query = mb_substr($query, 5); // Chop off initial " AND "
    usleep(20000); // Wait 1/50 of a second since we probably just tried
    $xml = get_entrez_xml('esearch_pubmed', $query);
    // @codeCoverageIgnoreStart
    if ($xml === null) {
        sleep(1);
        report_inline("no results.");
        return ['', 0, []];
    }
    if (isset($xml->ErrorList)) {
        // Could look at $xml->ErrorList->PhraseNotFound for list of what was not found
        report_inline('no results.');
        return ['', 0, []];
    }
    // @codeCoverageIgnoreEnd

    if (isset($xml->IdList->Id[0]) && isset($xml->Count)) {
        return [(string) $xml->IdList->Id[0], (int) (string) $xml->Count, $terms]; // first results; number of results
    } else {
        return ['', 0, []];
    }
}
