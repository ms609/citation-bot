<?php

declare(strict_types=1);

/**
 * @param array<Template> &$templates
 */
function expand_arxiv_templates (array &$templates): void {    // Pointer to save memory
    $ids = [];
    $arxiv_templates = [];
    foreach ($templates as $this_template) {
        if ($this_template->wikiname() === 'cite arxiv') {
            $this_template->rename('arxiv', 'eprint');
        } else {
            $this_template->rename('eprint', 'arxiv');
        }
        $eprint = str_ireplace("arXiv:", "", $this_template->get('eprint') . $this_template->get('arxiv'));
        if ($eprint && mb_stripos($eprint, 'CITATION_BOT') === false) {
            $ids[] = $eprint;
            $arxiv_templates[] = $this_template;
        }
    }
    arxiv_api($ids, $arxiv_templates);
}

function parse_arxiv_xml_response(string $response): ?SimpleXMLElement {
    $normalized = preg_replace("~(</?)(\w+):([^>]*>)~", "$1$2$3", $response);
    if ($normalized === null) {
        return null;
    }
    try {
        $xml = @simplexml_load_string(
            $normalized,
            SimpleXMLElement::class,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
    } catch (Throwable) {
        return null;
    }
    if ($xml === false || $xml->getName() !== 'feed') {
        return null;
    }
    return $xml;
}

/**
 * Return arXiv entries in the same order as the requested identifiers.
 * Missing or malformed entries are represented as null.
 *
 * @param SimpleXMLElement $xml
 * @param array<string> $ids
 * @return array<SimpleXMLElement|null>
 */
function arxiv_entries_for_ids(SimpleXMLElement $xml, array $ids): array {
    $entry_map = [];
    foreach ($xml->entry as $entry) {
        $raw_id = (string) $entry->id;
        if (!preg_match('~^https?://arxiv\.org/abs/(.+)$~', $raw_id, $match)) {
            continue;
        }
        $versioned_id = $match[1];
        $unversioned_id = preg_replace('~v\d+$~', '', $versioned_id);
        if ($unversioned_id === null || $unversioned_id === '') {
            continue;
        }
        $entry_map[$versioned_id] = $entry;
        $entry_map[$unversioned_id] = $entry;
    }

    $sorted = [];
    foreach ($ids as $id) {
        $sorted[] = $entry_map[$id] ?? null;
    }
    return $sorted;
}

/**
 * @param array<string> $ids
 * @param array<Template> &$templates
 */
function arxiv_api(array $ids, array &$templates): void {  // Pointer to save memory
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(1.0, [], 32 * 1024 * 1024);
    }
    set_time_limit(120);
    if (count($ids) === 0 || empty($templates)) {
        return;
    }
    report_action("Getting data from arXiv API");
    /** @psalm-taint-escape ssrf */
    $request = "https://export.arxiv.org/api/query?start=0&max_results=2000&id_list=" . implode(',', $ids);
    curl_setopt($ch, CURLOPT_URL, $request);
    $response = bot_curl_exec($ch);
    if ($response) {
        $xml = parse_arxiv_xml_response($response);
        unset($response);
    } else {
        report_warning("No response from arXiv.");        // @codeCoverageIgnore
        return;                                      // @codeCoverageIgnore
    }
    if ($xml === null) {
        report_warning("No valid response from arXiv.");        // @codeCoverageIgnore
        return;                                  // @codeCoverageIgnore
    }
    if ((string) $xml->entry->title === "Error") {
        $the_error = (string) $xml->entry->summary;
        if (mb_stripos($the_error, 'incorrect id format for') !== false) {
            report_warning("arXiv search failed: " . echoable($the_error));
        } else {
            report_minor_error("arXiv search failed - please report the error: " . echoable($the_error));    // @codeCoverageIgnore
        }
        return;
    }

    // Arxiv currently does not order the data received according to id_list. This is causing CitationBot to mix up
    // which Arxiv ID is associated with which citation. As a result, we first perform a sorting pass to make sure we
    // order the arxiv data based on our id_list so that we have a 1 to 1 ordering of both.
    $id_list = array_values($ids);
    $template_list = array_values($templates);
    $sorted_arxiv_data = arxiv_entries_for_ids($xml, $id_list);

    foreach ($sorted_arxiv_data as $index => $entry) {
        if ($entry === null || !isset($template_list[$index])) {
            continue;
        }
        $this_template = $template_list[$index];
        $i = 0;
        report_info("Found match for arXiv " . echoable($id_list[$index]));
        if ($this_template->add_if_new("doi", (string) @$entry->arxivdoi, 'arxiv')) {
            if ($this_template->blank(['journal', 'volume', 'issue']) && $this_template->has('title')) {
                // Move outdated/bad arXiv title out of the way
                $the_arxiv_title = $this_template->get('title');
                $the_arxiv_contribution = $this_template->get('contribution');
                if ($the_arxiv_contribution !== '') {
                    $this_template->set('contribution', '');
                }
                $this_template->set('title', '');
                expand_by_doi($this_template);
                if ($this_template->blank('title')) {
                    $this_template->set('title', $the_arxiv_title);
                    if ($the_arxiv_contribution !== '') {
                        $this_template->set('contribution', $the_arxiv_contribution);
                    }
                } else {
                    if ($the_arxiv_contribution !== '' && $this_template->blank('contribution')) {
                        $this_template->forget('contribution');
                    }
                }
                unset($the_arxiv_title);
                unset($the_arxiv_contribution);
            } else {
                expand_by_doi($this_template);
            }
        }
        foreach ($entry->author as $auth) {
            $i++;
            $name = (string) $auth->name;
            if (preg_match("~(.+\.)(.+?)$~", $name, $names) || preg_match('~^\s*(\S+) (\S+)\s*$~', $name, $names)) {
                $this_template->add_if_new("last{$i}", $names[2], 'arxiv');
                $this_template->add_if_new("first{$i}", $names[1], 'arxiv');
            } else {
                $this_template->add_if_new("author{$i}", $name, 'arxiv');
            }
            if ($this_template->blank(["last{$i}", "first{$i}", "author{$i}"])) {
                $i--;    // Deal with authors that are empty or just a colon as in https://export.arxiv.org/api/query?start=0&max_results=2000&id_list=2112.04678
            }
        }
        $the_title = (string) $entry->title;
        // arXiv fixes these when it sees them
        while (preg_match('~\$\^{(\d+)}\$~', $the_title, $match)) {
            $the_title = str_replace($match[0], '<sup>' . $match[1] . '</sup>', $the_title); // @codeCoverageIgnore
        }
        while (preg_match('~\$_(\d+)\$~', $the_title, $match)) {
            $the_title = str_replace($match[0], '<sub>' . $match[1] . '</sub>', $the_title); // @codeCoverageIgnore
        }
        while (preg_match('~\\ce{([^}{^ ]+)}~', $the_title, $match)) {    // arXiv fixes these when it sees them
            $the_title = str_replace($match[0], ' ' . $match[1] . ' ', $the_title);    // @codeCoverageIgnore
            $the_title = str_replace('  ', ' ', $the_title);                          // @codeCoverageIgnore
        }
        $this_template->add_if_new("title", $the_title, 'arxiv'); // Formatted by add_if_new
        $this_template->add_if_new("class", (string) $entry->category["term"], 'arxiv');
        $int_time = strtotime((string) $entry->published);
        if ($int_time) {
            $this_template->add_if_new("year", date("Y", $int_time), 'arxiv');
        }

        if ($this_template->has('publisher')) {
            if (mb_stripos($this_template->get('publisher'), 'arxiv') !== false) {
                $this_template->forget('publisher');
            }
        }
    }
    if (count($template_list) !== count($id_list)) {
        bot_debug_log('arxiv_api received mismatched identifier/template counts');
    }
    return;
}
