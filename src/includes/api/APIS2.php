<?php

declare(strict_types=1);

function parse_semanticscholar_corpus_response(string $response): ?string {
    $json = ExternalApiResponseGuard::decodeObject($response);
    if ($json === null || !isset($json->corpusId) || (!is_string($json->corpusId) && !is_int($json->corpusId))) {
        return null;
    }
    return (string) $json->corpusId;
}

function parse_semanticscholar_doi_response(string $response): ?string {
    $json = ExternalApiResponseGuard::decodeObject($response);
    if (
        $json === null ||
        !isset($json->externalIds) ||
        !is_object($json->externalIds) ||
        !isset($json->externalIds->DOI) ||
        !is_string($json->externalIds->DOI)
    ) {
        return null;
    }
    return (string) $json->externalIds->DOI;
}

function parse_semanticscholar_open_access_response(string $response): ?bool {
    $json = ExternalApiResponseGuard::decodeObject($response);
    if ($json === null || isset($json->error) || !isset($json->isOpenAccess)) {
        return null;
    }
    if (!is_bool($json->isOpenAccess)) {
        return null;
    }
    return $json->isOpenAccess;
}

function parse_semanticscholar_legacy_url_response(string $response): ?string {
    $json = ExternalApiResponseGuard::decodeObject($response);
    if (
        $json === null ||
        !isset($json->url) ||
        !is_string($json->url) ||
        $json->url === '' ||
        !isset($json->is_publisher_licensed) ||
        $json->is_publisher_licensed !== true ||
        !isset($json->openAccessPdf) ||
        !is_object($json->openAccessPdf)
    ) {
        return null;
    }
    return $json->url;
}

function semanticscholar_request(CurlHandle $ch, bool $warn_on_no_response = false): string {
    try {
        $response = bot_curl_exec_withFalse($ch);
    } catch (Throwable $e) {
        bot_debug_log('Semantic Scholar request failed: ' . $e::class . ': ' . $e->getMessage());
        report_warning("Semantic Scholar request failed; continuing without this metadata.");
        return '';
    }

    if ($response === false) {
        bot_debug_log('Semantic Scholar cURL error #' . curl_errno($ch) . ': ' . curl_error($ch));
        if ($warn_on_no_response) {
            report_warning("Semantic Scholar request failed; continuing without this metadata.");
        }
        return '';
    }
    if ($response === '' && $warn_on_no_response) {
        report_warning("No response from semanticscholar.");
    }
    return (string) $response;
}

/**
 * Build a new Semantic Scholar handle with the common transport policy.
 * Keep a separate lazy handle in each caller: handles are not shared across
 * the corpus ID, DOI, access-license, and legacy URL endpoints.
 */
function semanticscholar_new_handle(): CurlHandle {
    return bot_curl_init(0.5, HEADER_S2, 4 * 1024 * 1024);
}

/**
 * Set only this handle's URL; leave per-call warning policy to the caller.
 * The existing request helper distinguishes transport failures from valid
 * empty responses, so do not turn a failed curl_setopt into a new error path.
 */
function semanticscholar_request_url(CurlHandle $ch, string $url, bool $warn_on_no_response = false): string {
    curl_setopt($ch, CURLOPT_URL, $url);
    return semanticscholar_request($ch, $warn_on_no_response);
}

function getS2CID(string $url): string {
    static $ch = null;
    if ($ch === null) {
        $ch = semanticscholar_new_handle();
    }
    $url = 'https://api.semanticscholar.org/graph/v1/paper/URL:' . urlencode(urldecode($url)) . '?fields=corpusId';
    $response = semanticscholar_request_url($ch, $url, true);
    if ($response === '') {
        return '';
    }
    $corpus_id = parse_semanticscholar_corpus_response($response);
    unset($response);
    if ($corpus_id === null) {
        report_warning("Bad response from semanticscholar.");    // @codeCoverageIgnore
        return '';                                              // @codeCoverageIgnore
    }
    return $corpus_id;
}

function ConvertS2CID_DOI(string $s2cid): string {
    static $ch = null;
    if ($ch === null) {
        $ch = semanticscholar_new_handle();
    }
    /** @psalm-taint-escape ssrf */
    $url = 'https://api.semanticscholar.org/graph/v1/paper/CorpusID:' . urlencode($s2cid) . '?fields=externalIds';
    $response = semanticscholar_request_url($ch, $url, true);
    if ($response === '') {
        return '';
    }
    $doi = parse_semanticscholar_doi_response($response);
    unset($response);
    if ($doi === null) {
        report_warning("Bad response from semanticscholar."); // @codeCoverageIgnore
        return '';                                            // @codeCoverageIgnore
    }
    if (doi_works($doi)) {
        return $doi;
    } else {
        report_info("non-functional doi found from semanticscholar: " . echoable($doi));// @codeCoverageIgnore
        return '';                                                    // @codeCoverageIgnore
    }
}

/** https://api.semanticscholar.org/graph/v1/swagger.json */
function get_semanticscholar_license(string $s2cid): ?bool {
    static $ch = null;
    if ($ch === null) {
        $ch = semanticscholar_new_handle();
    }
    /** @psalm-taint-escape ssrf */
    $url = 'https://api.semanticscholar.org/graph/v1/paper/CorpusID:' . urlencode($s2cid) . '?fields=isOpenAccess';
    $response = semanticscholar_request_url($ch, $url);
    if ($response === '') {
        return null; // @codeCoverageIgnore
    }
    if (mb_stripos($response, 'Too Many Requests', 0, '8bit') !== false) {
        sleep(1);
        return null; // @codeCoverageIgnore
    }
    if (mb_stripos($response, 'Paper not found', 0, '8bit') !== false) {
        return false; // @codeCoverageIgnore
    }
    return parse_semanticscholar_open_access_response($response);
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
        $ch = semanticscholar_new_handle();
    }
    set_time_limit(120);
    if ($template->has('pmc') || ($template->has('doi') && $template->get('doi-access') === 'free') || ($template->has('jstor') && $template->get('jstor-access') === 'free')) {
        return;
    } // do not add url if have OA already. Do indlude preprints in list
    if ($template->has('s2cid') || $template->has('S2CID')) {
        return;
    }
    $url = 'https://api.semanticscholar.org/v1/paper/' . doi_encode(urldecode($doi));
    $response = semanticscholar_request_url($ch, $url);
    if ($response) {
        $open_access_url = parse_semanticscholar_legacy_url_response($response);
        unset($response);
        if ($open_access_url !== null) {
            $template->get_identifiers_from_url($open_access_url);
        }
    }
}
