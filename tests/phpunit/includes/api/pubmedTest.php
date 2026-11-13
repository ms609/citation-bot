<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class pubmedTest extends testBaseClass {
    public function testTitleFallbackRejectsDifferentWorkWithShortChineseWords(): void {
        $template = $this->make_citation('{{cite journal|title=肺癌 治疗 指南|last=Smith|year=2019}}');
        $queries = [];
        $this->assertTrue(uopz_set_return('get_doi_from_crossref', null));
        $this->assertTrue(uopz_set_return('get_entrez_xml', static function (string $type, string $query) use (&$queries): SimpleXMLElement {
            $queries[] = [$type, urldecode($query)];
            return new SimpleXMLElement($type === 'esearch_pubmed'
                ? '<eSearchResult><Count>1</Count><IdList><Id>12345</Id></IdList></eSearchResult>'
                : '<eSummaryResult><DocSum><Id>12345</Id><Item Name="Title">胃癌 治疗 指南</Item></DocSum></eSummaryResult>');
        }, true));
        try {
            find_pmid($template);
        } finally {
            $this->assertTrue(uopz_unset_return('get_entrez_xml'));
            $this->assertTrue(uopz_unset_return('get_doi_from_crossref'));
        }
        $this->assertSame([
            ['esearch_pubmed', '("肺癌 治疗 指南"[Title]) AND (Smith[Author]) AND (2019[Publication Date])'],
            ['pubmed', '12345'],
        ], $queries);
        $this->assertNull($template->get2('pmid'));
    }

    public function testTitleFallbackSkipsSearchWithoutUsefulTitleWords(): void {
        $template = $this->make_citation('{{cite journal|title=The and ...|last=Smith|year=2019}}');
        $this->assertTrue(uopz_set_return('get_entrez_xml', static function (): never {
            throw new RuntimeException('A title search must include a title term');
        }, true));
        try {
            $this->assertSame(['', 0, []], query_pubmed($template));
        } finally {
            $this->assertTrue(uopz_unset_return('get_entrez_xml'));
        }
    }

    #[DataProvider('pmidExpansionResponses')]
    public function testPmidExpansion(?string $response, string $expected_template, ?string $expected_journal): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Entrez responses');
        }
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('get_entrez_xml', $response === null ? null : new SimpleXMLElement($response));
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('get_doi_from_crossref', null);
        try {
            $expanded = $this->prepare_citation('{{Cite web | http://www.ncbi.nlm.nih.gov/pubmed/1941451?dopt=AbstractPlus}}');
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('get_doi_from_crossref');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('get_entrez_xml');
        }

        $this->assertSame('1941451', $expanded->get2('pmid'));
        $this->assertSame($expected_template, $expanded->wikiname());
        $this->assertSame($expected_journal, $expanded->get2('journal'));
        if ($expected_journal === null) {
            $this->assertNotNull($expanded->get2('url'));
        }
    }

    /** @return array<string, array{?string, string, ?string}> */
    public static function pmidExpansionResponses(): array {
        $response = <<<'XML'
<eSummaryResult>
  <DocSum>
    <Id>1941451</Id>
    <Item Name="Title">Example Article</Item>
    <Item Name="FullJournalName">Example Journal</Item>
  </DocSum>
</eSummaryResult>
XML;
        return [
            'identifier only' => [null, 'cite web', null],
            'journal metadata returned' => [$response, 'cite journal', 'Example Journal'],
        ];
    }

    #[DataProvider('cellBiologyIssueCitations')]
    public function testEntrezDoesNotAddAnnualReviewDefaultIssue(string $fields, ?string $expected_issue): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Entrez responses');
        }
        // Entrez returns Issue before FullJournalName for PMID 39356808.
        $response = <<<'XML'
<eSummaryResult>
  <DocSum>
    <Id>39356808</Id>
    <Item Name="Title">Lipid Droplets Big and Small: Basic Mechanisms That Make Them All</Item>
    <Item Name="Volume">40</Item>
    <Item Name="Issue">1</Item>
    <Item Name="Pages">143-168</Item>
    <Item Name="FullJournalName">Annual review of cell and developmental biology</Item>
  </DocSum>
</eSummaryResult>
XML;
        $template = $this->make_citation('{{cite journal|pmid=39356808' . $fields . '}}');
        $templates = [$template];
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('get_entrez_xml', new SimpleXMLElement($response));
        try {
            query_pmid_api(['39356808'], $templates);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('get_entrez_xml');
        }

        $this->assertSame($expected_issue, $template->get2('issue'));
        $this->assertSame('40', $template->get2('volume'));
        $this->assertSame('143–168', $template->get2('pages'));
    }

    /** @return array<string, array{string, ?string}> */
    public static function cellBiologyIssueCitations(): array {
        return [
            'reported citation with blank issue' => [
                '|journal=Annual Review of Cell and Developmental Biology|doi=10.1146/annurev-cellbio-012624-031419|issue=',
                '',
            ],
            'PMID-only citation' => ['', null],
            'editor-supplied issue' => ['|issue=1', '1'],
        ];
    }

    public function testGetPMIDwitNoDOIorJournal(): void {  // Also has evil colon in the name.   Use wikilinks for code coverage reason
        $this->sleep_pubmed();
        sleep(9);
        $text = '{{cite journal|title=ISiCLE: A Quantum Chemistry Pipeline for Establishing in Silico Collision Cross Section Libraries|volume=[[91]]|issue=[[7|7]]|pages=4346|year=2019|last1=Colby}}';
        $template = $this->make_citation($text);
        find_pmid($template);
        if ($template->get2('pmid') === null) {
            sleep(14);
            find_pmid($template);
        }
        if ($template->get2('pmid') === null) {
            $this->markTestSkipped('PubMed API did not respond (rate limit or outage)');
        }
        $this->assertSame('30741529', $template->get2('pmid'));
    }

    public function testPmidIsZero(): void {
        $this->sleep_pubmed();
        $text = '{{cite journal|pmc=2676591}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('pmid'));
    }

    public function testPMCExpansion1(): void {
        $this->sleep_pubmed();
        $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pmc/articles/PMC154623/}}";
        $expanded = $this->prepare_citation($text);
        $this->assertSame('cite journal', $expanded->wikiname());
        $this->assertSame('154623', $expanded->get2('pmc'));
        $this->assertNull($expanded->get2('url'));
    }

    public function testPMCExpansion2(): void {
        // PMC ID extraction from PDF URL. Network-dependent URL/template-type outcome covered by testPMCExpansion3.
        $this->sleep_pubmed();
        sleep(4);
        $text = "{{Cite web | url = https://www.ncbi.nlm.nih.gov/pmc/articles/PMC2491514/pdf/annrcse01476-0076.pdf}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('2491514', $expanded->get2('pmc'));
    }

    public function testPMCExpansion3(): void {
        // Non-existent PMC: NCBI returns 404, early-return fires → PDF URL kept, rename skipped.
        // If NCBI returns 200 instead (e.g. a soft-404 page), the URL is dropped and the test is skipped.
        $this->sleep_pubmed();
        $this->sleep_pubmed(); // super picky test
        $text = "{{Cite web | url = https://www.ncbi.nlm.nih.gov/pmc/articles/PMC99999999/pdf/nonexistent.pdf}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('99999999', $expanded->get2('pmc'));
        if ($expanded->get2('url') === null) {
            $this->markTestSkipped('NCBI did not return 404 for non-existent PMC (API behavior changed or unavailable)');
        }
        $this->assertSame('cite web', $expanded->wikiname());
        $this->assertSame('https://www.ncbi.nlm.nih.gov/pmc/articles/PMC99999999/pdf/nonexistent.pdf', $expanded->get2('url'));
    }

    public function testPMC2PMID(): void {
        $this->sleep_pubmed();
        $text = '{{cite journal|pmc=58796}}';
        $expanded = $this->process_citation($text);
        if ($expanded->get('pmid') === "") {
            sleep(run_type_mods(-1, 5, 3, 2, 2));
            $expanded = $this->process_citation($text);
        }
        if ($expanded->get2('pmid') === null) {
            $this->markTestSkipped('PubMed API did not respond (rate limit or outage)');
        }
        $this->assertSame('11573006', $expanded->get2('pmid'));
    }

    public function testDoi2PMID(): void {
        $this->sleep_pubmed();
        $this->sleep_pubmed(); // picky
        $text = "{{cite journal|doi=10.1073/pnas.171325998}}";
        $expanded = $this->process_citation($text);
        if ($expanded->get2('pmid') === null) {
            $this->markTestSkipped('PubMed API did not respond (rate limit or outage)');
        }
        if ($expanded->get2('pmc') === null) {
            sleep(run_type_mods(-1, 5, 3, 2, 2));
            $expanded = $this->process_citation($text);
        }
        if ($expanded->get2('pmc') === null) {
            $this->markTestSkipped('PubMed PMC API did not respond (rate limit or outage)');
        }
        $this->assertSame('11573006', $expanded->get2('pmid'));
        $this->assertSame('58796', $expanded->get2('pmc'));
    }

    public function testZootaxaDOIPageRejection(): void {
        // Test that bot rejects DOI suffix as page number for Zootaxa articles
        // This is the exact case from the bug report
        $text = '{{Cite journal|journal=Zootaxa|volume=4963|issue=1|pages=1–10|doi=10.11646/zootaxa.4963.1.1}}';
        $template = $this->make_citation($text);

        // Try to add the bad page data (DOI suffix) that PMID would return
        $result = $template->add_if_new('pages', 'zootaxa.4963.1.1', 'entrez');

        // Should reject the DOI suffix and keep the correct pages
        $this->assertFalse($result);
        $this->assertSame('1–10', $template->get2('pages'));
    }

    public function testPhytotaxaDOIPageRejection(): void {
        // Test that bot also rejects DOI suffix as page number for Phytotaxa articles
        $text = '{{Cite journal|journal=Phytotaxa|volume=260|issue=2|pages=123–130|doi=10.11646/phytotaxa.260.2.3}}';
        $template = $this->make_citation($text);

        // Try to add the bad page data (DOI suffix) that PMID might return
        $result = $template->add_if_new('pages', 'phytotaxa.260.2.3', 'entrez');

        // Should reject the DOI suffix and keep the correct pages
        $this->assertFalse($result);
        $this->assertSame('123–130', $template->get2('pages'));
    }

    public function testAuthorOrdinalSuffixStoredInFirstName(): void {
        // Regression: ordinal suffix (e.g. '3rd') must land on the first-name side of the comma.
        $template = $this->make_citation('{{cite journal}}');
        $template->add_if_new('author1', 'Jacob,P 3rd', 'entrez');
        $this->assertSame('Jacob', $template->get2('last1'));
        $this->assertSame('P 3rd', $template->get2('first1'));
    }

    public function testAuthorOrdinalSuffixWithPeriodOnInitial(): void {
        // Period is appended to initial when suffix is present: "P. 3rd" not "P 3rd".
        $template = $this->make_citation('{{cite journal}}');
        $template->add_if_new('author1', 'Jacob,P. 3rd', 'entrez');
        $this->assertSame('Jacob', $template->get2('last1'));
        $this->assertSame('P. 3rd', $template->get2('first1'));
    }

    public function testEntrezXmlParserRejectsMalformedResponses(): void {
        $this->assertNull(parse_entrez_xml_response(''));
        $this->assertNull(parse_entrez_xml_response('not xml'));
        $this->assertNull(parse_entrez_xml_response('<eSummaryResult><DocSum>'));
        $this->assertNull(parse_entrez_xml_response("<eSummaryResult>\xFF</eSummaryResult>"));
    }

    public function testEntrezXmlParserAcceptsExpectedResponse(): void {
        $xml = parse_entrez_xml_response(
            '<eSummaryResult><DocSum><Id>12345</Id></DocSum></eSummaryResult>'
        );
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
        $this->assertSame('12345', (string) $xml->DocSum->Id);
    }

    public function testPubmedDocumentTitleRejectsMissingExpectedShape(): void {
        $this->assertNull(pubmed_document_title(new SimpleXMLElement('<DocSum><Id>12345</Id></DocSum>')));
        $this->assertNull(pubmed_document_title(
            new SimpleXMLElement('<DocSum><Item Name="PubDate">2026</Item></DocSum>')
        ));
    }

    public function testPubmedDocumentTitleExtractsTitle(): void {
        $document = new SimpleXMLElement(
            '<DocSum><Item Name="Title">[Expected title]</Item></DocSum>'
        );
        $this->assertSame('Expected title', pubmed_document_title($document));
    }

    #[DataProvider('pubmedSummaryProvider')]
    public function testFindPmidVerifiesBibliographicMatch(?string $summary, ?string $expected_pmid): void {
        $template = $this->make_citation('{{cite journal|title=Modelling Coral Reef Recovery' .
            '|journal=Journal of Marine Ecology|volume=40|issue=2|pages=12–19}}');
        $search = '<eSearchResult><Count>1</Count><IdList><Id>12345678</Id></IdList></eSearchResult>';
        $requests = $this->findPmidWithEntrezResponses($template, [$search, $summary, $summary]);

        $this->assertSame('esearch_pubmed', $requests[0][0]);
        $this->assertStringContainsString('(12[Pagination])', $requests[0][1]);
        $this->assertSame(['pubmed', '12345678'], $requests[1]);
        $this->assertSame($expected_pmid, $template->get2('pmid'));
        if ($expected_pmid === null) {
            $this->assertCount(2, $requests);
        }
    }

    /** @return array<string, array{?string, ?string}> */
    public static function pubmedSummaryProvider(): array {
        return [
            'unavailable summary' => [null, null],
            'no summary records' => ['<eSummaryResult/>', null],
            'multiple summary records' => [
                '<eSummaryResult><DocSum><Item Name="Title">Modelling Coral Reef Recovery</Item></DocSum>' .
                '<DocSum><Item Name="Title">Another Article</Item></DocSum></eSummaryResult>',
                null,
            ],
            'missing title' => ['<eSummaryResult><DocSum><Item Name="PubDate">2025</Item></DocSum></eSummaryResult>', null],
            'different title' => [
                '<eSummaryResult><DocSum><Item Name="Title">Rare Mosses of Patagonia</Item></DocSum></eSummaryResult>',
                null,
            ],
            'matching title' => [
                '<eSummaryResult><DocSum><Id>12345678</Id>' .
                '<Item Name="Title">[Modelling Coral Reef Recovery]</Item></DocSum></eSummaryResult>',
                '12345678',
            ],
        ];
    }

    #[DataProvider('pubmedIssueSearchProvider')]
    public function testFindPmidRetriesAmbiguousTitleSearchWithIssue(int $refined_count, ?string $expected_pmid): void {
        $template = $this->make_citation('{{cite journal|title=Modelling Coral Reef Recovery' .
            '|last=Jones|year=2025|volume=40|issue=2}}');
        $ambiguous = '<eSearchResult><Count>2</Count><IdList><Id>87654321</Id><Id>12345678</Id></IdList></eSearchResult>';
        $refined = '<eSearchResult><Count>' . $refined_count . '</Count><IdList><Id>12345678</Id></IdList></eSearchResult>';
        $summary = '<eSummaryResult><DocSum><Id>12345678</Id>' .
            '<Item Name="Title">Modelling Coral Reef Recovery</Item></DocSum></eSummaryResult>';
        $requests = $this->findPmidWithEntrezResponses($template, [$ambiguous, $refined, $summary, $summary]);

        $this->assertSame('esearch_pubmed', $requests[0][0]);
        $this->assertStringContainsString('(Jones[Author])', $requests[0][1]);
        $this->assertStringNotContainsString('[Issue]', $requests[0][1]);
        $this->assertSame(['esearch_pubmed', $requests[0][1] . ' AND (2[Issue])'], $requests[1]);
        $this->assertSame($expected_pmid, $template->get2('pmid'));
        if ($expected_pmid === null) {
            $this->assertCount(2, $requests);
        } else {
            $this->assertSame(['pubmed', '12345678'], $requests[2]);
        }
    }

    /** @return array<string, array{int, ?string}> */
    public static function pubmedIssueSearchProvider(): array {
        return [
            'unique after adding the issue' => [1, '12345678'],
            'still ambiguous' => [2, null],
        ];
    }

    public function testFindPmidRejectsInvalidSearchCounts(): void {
        foreach (['1.5', '-1'] as $count) {
            $template = $this->make_citation('{{cite journal|title=Modelling Coral Reef Recovery|last=Jones}}');
            $search = '<eSearchResult><Count>' . $count . '</Count><IdList><Id>12345678</Id></IdList></eSearchResult>';
            $requests = $this->findPmidWithEntrezResponses($template, [$search]);

            $this->assertCount(1, $requests);
            $this->assertSame('esearch_pubmed', $requests[0][0]);
            $this->assertNull($template->get2('pmid'));
        }
    }

    public function testEntrezTransportFailureLeavesCitationUnchanged(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Entrez transport');
        }
        $text = '{{cite journal|pmid=12345678}}';
        $template = $this->make_citation($text);
        $templates = [$template];
        $requests = [];
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('bot_curl_exec', static function (CurlHandle $handle) use (&$requests): never {
            $requests[] = (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
            throw new RuntimeException('Simulated Entrez transport failure');
        }, true);
        try {
            query_pmid_api(['12345678'], $templates);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('bot_curl_exec');
        }
        $this->assertSame(['https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi'], $requests);
        $this->assertSame($text, $template->parsed_text());
    }

    #[DataProvider('entrezDoiItemSources')]
    public function testEntrezExtractsDoiOnlyFromIdentifierItems(string $items_xml, ?string $expected_doi): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Entrez responses');
        }
        $response = '<eSummaryResult><DocSum><Id>12345678</Id>' . $items_xml . '</DocSum></eSummaryResult>';
        $template = $this->make_citation('{{cite journal}}');
        $templates = [$template];
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('get_entrez_xml', new SimpleXMLElement($response));
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('doi_works', true);
        try {
            query_pmid_api(['12345678'], $templates);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('doi_works');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('get_entrez_xml');
        }

        $this->assertSame($expected_doi, $template->get2('doi'));
    }

    /** @return array<string, array{string, ?string}> */
    public static function entrezDoiItemSources(): array {
        return [
            'top-level DOI with five-digit registrant' => [
                '<Item Name="Title">A study of 10.1234/not-affiliated</Item>' .
                '<Item Name="DOI">10.12345/real-doi</Item>',
                '10.12345/real-doi',
            ],
            'ArticleIds doi with five-digit registrant' => [
                '<Item Name="ArticleIds" Type="List"><Item Name="doi">10.12345/from-articleids</Item></Item>',
                '10.12345/from-articleids',
            ],
            'ELocationID doi' => [
                '<Item Name="ELocationID" Type="String">doi: 10.12345/from-elocation</Item>',
                '10.12345/from-elocation',
            ],
            'title-shaped doi is ignored' => [
                '<Item Name="Title">A study of 10.1234/not-affiliated</Item>',
                null,
            ],
        ];
    }

    #[DataProvider('doiFormBodyProvider')]
    public function testDoPubMedQueryEncodesDoiForFormBody(string $doi, string $expected_query): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Entrez responses');
        }
        $template = $this->make_citation('{{cite journal|doi=' . $doi . '}}');
        $queries = [];
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('get_entrez_xml', static function (string $type, string $query) use (&$queries): ?SimpleXMLElement {
            $queries[] = [$type, $query];
            return null;
        }, true);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('sleep', 0);
        try {
            do_pumbed_query($template, ['doi']);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('sleep');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('get_entrez_xml');
        }

        $this->assertSame([['esearch_pubmed', $expected_query]], $queries);
    }

    /** @return array<string, array{string, string}> */
    public static function doiFormBodyProvider(): array {
        return [
            'raw plus and ampersand' => ['10.1000/a&b+c', '("10.1000/a%26b%2Bc"[AID])'],
            'already percent-encoded plus' => ['10.1000/a%2Bb', '("10.1000/a%2Bb"[AID])'],
            'already percent-encoded hash' => ['10.1000/a%23b', '("10.1000/a%23b"[AID])'],
        ];
    }

    /**
     * @param Template $template
     * @param list<?string> $responses
     * @return list<array{string, string}>
     */
    private function findPmidWithEntrezResponses(Template $template, array $responses): array {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Entrez responses');
        }
        $requests = [];
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('get_entrez_xml', static function (string $type, string $query) use (&$requests, $responses): ?SimpleXMLElement {
            $response = $responses[count($requests)] ?? null;
            $requests[] = [$type, $query];
            return $response === null ? null : new SimpleXMLElement($response);
        }, true);
        // Adding a verified PMID can also trigger DOI discovery.
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('get_doi_from_crossref', null);
        try {
            find_pmid($template);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('get_doi_from_crossref');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('get_entrez_xml');
        }
        return $requests;
    }
}
