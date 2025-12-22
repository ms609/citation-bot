<?php

declare(strict_types=1);

function getS2CID(string $url): string {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(0.5, HEADER_S2);
    }
    $url = 'https://api.semanticscholar.org/graph/v1/paper/URL:' . urlencode(urldecode($url)) . '?fields=corpusId';
    curl_setopt($ch, CURLOPT_URL, $url);
    $response = bot_curl_exec($ch);
    if (!$response) {
        report_warning("No response from semanticscholar.");    // @codeCoverageIgnore
        return '';                                              // @codeCoverageIgnore
    }
    $json = @json_decode($response);
    unset($response);
    if (!$json) {
        report_warning("Bad response from semanticscholar.");    // @codeCoverageIgnore
        return '';                                              // @codeCoverageIgnore
    }
    if (!isset($json->corpusId)) {
        report_warning("No corpusId found from semanticscholar for " . echoable($url)); // @codeCoverageIgnore
        return '';                                                      // @codeCoverageIgnore
    }
    if (is_array($json->corpusId) || is_object($json->corpusId)) {
        report_warning("Bad data from semanticscholar.");    // @codeCoverageIgnore
        return '';                                          // @codeCoverageIgnore
    }
    return (string) $json->corpusId;
}

function ConvertS2CID_DOI(string $s2cid): string {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(0.5, HEADER_S2);
    }
    /** @psalm-taint-escape ssrf */
    $url = 'https://api.semanticscholar.org/graph/v1/paper/CorpusID:' . urlencode($s2cid) . '?fields=externalIds';
    curl_setopt($ch, CURLOPT_URL, $url);
    $response = bot_curl_exec($ch);
    if (!$response) {
        report_warning("No response from semanticscholar.");  // @codeCoverageIgnore
        return '';                                            // @codeCoverageIgnore
    }
    $json = @json_decode($response);
    unset($response);
    if (!$json) {
        report_warning("Bad response from semanticscholar."); // @codeCoverageIgnore
        return '';                                            // @codeCoverageIgnore
    }
    if (!isset($json->externalIds->DOI)) {
        return '';                                         // @codeCoverageIgnore
    }
    $doi = $json->externalIds->DOI;
    if (is_array($doi) || is_object($doi)) {
        report_warning("Bad data from semanticscholar."); // @codeCoverageIgnore
        return '';                                        // @codeCoverageIgnore
    }
    $doi = (string) $doi;
    if (doi_works($doi)) {
        return $doi;
    } else {
        report_info("non-functional doi found from semanticscholar: " . echoable_doi($doi));// @codeCoverageIgnore
        return '';                                                    // @codeCoverageIgnore
    }
}

/** https://api.semanticscholar.org/graph/v1/swagger.json */
function get_semanticscholar_license(string $s2cid): ?bool {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(0.5, HEADER_S2);
    }
    $url = 'https://api.semanticscholar.org/graph/v1/paper/CorpusID:' . urlencode($s2cid) . '?fields=isOpenAccess';
    curl_setopt($ch, CURLOPT_URL, $url);
    $response = bot_curl_exec($ch);
    if ($response === '') {
        return null; // @codeCoverageIgnore
    }
    if (mb_stripos($response, 'Paper not found') !== false) {
        return false; // @codeCoverageIgnore
    }
    $oa = @json_decode($response);
    if ($oa === false) {
        return null; // @codeCoverageIgnore
    }
    if (isset($oa->isOpenAccess) && $oa->isOpenAccess) {
        return true;
    }
    return false;
}

function get_doi_from_semanticscholar(Template $template): void {
    set_time_limit(120);
    if ($template->has('doi')) {
        return;
    }
    if ($template->blank(['s2cid', 'S2CID'])) {
        return;
    }
    if ($template->has('s2cid') && $template->has('S2CID')) {
        return;
    }
    report_action("Checking semanticscholar database for doi. ");
    $doi = ConvertS2CID_DOI($template->get('s2cid') . $template->get('S2CID'));
    if ($doi) {
        report_inline(" Successful!");
        $template->add_if_new('doi', $doi);
    }
    return;
}

function get_semanticscholar_url(Template $template, string $doi): void {
    static $ch = null;
    if ($ch === null) {
        $ch = bot_curl_init(0.5, HEADER_S2);
    }
    set_time_limit(120);
    if ($template->has('pmc') || ($template->has('doi') && $template->get('doi-access') === 'free') || ($template->has('jstor') && $template->get('jstor-access') === 'free')) {
        return;
    } // do not add url if have OA already. Do indlude preprints in list
    if ($template->has('s2cid') || $template->has('S2CID')) {
        return;
    }
    $url = 'https://api.semanticscholar.org/v1/paper/' . doi_encode(urldecode($doi));
    curl_setopt($ch, CURLOPT_URL, $url);
    $response = bot_curl_exec($ch);
    if ($response) {
        $oa = @json_decode($response);
        unset($response);
        if ($oa !== false && isset($oa->url) && isset($oa->is_publisher_licensed) && $oa->is_publisher_licensed && isset($oa->openAccessPdf) && $oa->openAccessPdf) {
            $url = $oa->url;
            unset($oa);
            $template->get_identifiers_from_url($url);
        }
    }
}
