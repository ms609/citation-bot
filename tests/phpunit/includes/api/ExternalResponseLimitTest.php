<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';
use PHPUnit\Framework\Attributes\DataProvider;

final class ExternalResponseLimitTest extends testBaseClass {
    /**
     * @return array<string, array{string, string, int, int}>
     */
    public static function configuredLimitProvider(): array {
        return [
            'semantic scholar url' => ['APIS2.php', 'getS2CID', 4, 1],
            'semantic scholar doi' => ['APIS2.php', 'ConvertS2CID_DOI', 4, 1],
            'semantic scholar license' => ['APIS2.php', 'get_semanticscholar_license', 4, 1],
            'semantic scholar legacy' => ['APIS2.php', 'get_semanticscholar_url', 4, 1],
            'unpaywall' => ['APIunpaywall.php', 'get_unpaywall_url', 4, 1],
            'arxiv' => ['APIarXiv.php', 'arxiv_api', 32, 1],
            'pubmed' => ['APIPubMed.php', 'xml_post', 16, 1],
            'crossref xml' => ['APIdoi.php', 'query_crossref', 8, 1],
            'doi csl json' => ['APIdoi.php', 'expand_doi_with_dx', 8, 1],
            'crossref rest' => ['APIdoi.php', 'query_crossref_newapi', 4, 1],
            'crossref search' => ['APIdoi.php', 'get_doi_from_crossref', 8, 1],
            'biorxiv' => ['APIdoi.php', 'get_biorxiv_published_doi', 16, 1],
            'google books url' => ['APIgoogle.php', 'expand_by_google_books_inner', 8, 1],
            'google books feed' => ['APIgoogle.php', 'google_book_details', 8, 1],
            'zotero' => ['APIzotero.php', 'create_ch_zotero', 8, 1],
            'archives' => ['APIarchives.php', 'expand_templates_from_archives', 16, 1],
            'jstor' => ['APIjstor.php', 'expand_by_jstor', 4, 1],
            'elsevier pii' => ['APIpii.php', 'get_doi_from_pii', 2, 1],
            'adsabs' => ['APIBibCode.php', 'Bibcode_Response_Processing', 16, 1],
            'url comparison' => ['../URLtools.php', 'drop_urls_that_match_dois', 2, 2],
        ];
    }

    private function functionSource(string $file, string $function): string {
        $source = file_get_contents(__DIR__ . '/../../../../src/includes/api/' . $file);
        if ($file === '../URLtools.php') {
            $source = file_get_contents(__DIR__ . '/../../../../src/includes/URLtools.php');
        }
        $this->assertIsString($source);

        $pattern = '~\bfunction\s+' . preg_quote($function, '~') . '\s*\(~';
        $matched = preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE);
        $this->assertSame(1, $matched, 'Could not locate function ' . $function . ' in ' . $file);
        $start = $match[0][1];
        $after_signature = $start + mb_strlen($match[0][0], '8bit');

        $next_pattern = '~\n\s*(?:(?:public|protected|private)\s+)?(?:static\s+)?function\s+[A-Za-z_][A-Za-z0-9_]*\s*\(~';
        if (preg_match($next_pattern, $source, $next, PREG_OFFSET_CAPTURE, $after_signature) === 1) {
            return substr($source, $start, $next[0][1] - $start);
        }
        return substr($source, $start);
    }

    #[DataProvider('configuredLimitProvider')]
    public function testExternalClientFunctionsSetExplicitResponseLimits(
        string $file,
        string $function,
        int $megabytes,
        int $occurrences
    ): void {
        $source = $this->functionSource($file, $function);
        $this->assertSame(
            $occurrences,
            substr_count($source, 'bot_curl_set_max_response_bytes('),
            $function . ' should cap every cURL handle it owns'
        );
        $this->assertSame(
            $occurrences,
            substr_count($source, $megabytes . ' * 1024 * 1024'),
            $function . ' should use the expected response-size limit'
        );
        $this->assertStringContainsString(
            'bot_curl_set_max_response_bytes(',
            $source,
            $function . ' should configure a response-size limit'
        );
    }
}
