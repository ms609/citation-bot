<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class MetadataConflictTest extends testBaseClass {
    private const string DOI = '10.1000/coral-recovery';
    private const string PMID = '12345678';
    private const string TITLE = 'Modelling coral reef recovery';
    private const string JOURNAL = 'Journal of Marine Ecology';

    #[DataProvider('crossrefOpenUrlTitleCases')]
    public function testCrossrefChecksOpenUrlTitleBeforeImportingMetadata(string $openurl_title, bool $matches): void {
        $title = self::TITLE . ': Part 1';
        $template = $this->make_citation('{{cite journal|title=' . $title . '|doi=' . self::DOI . '}}');
        $original = $template->parsed_text();
        $record = new SimpleXMLElement('<query><doi>' . self::DOI . '</doi><article_title>' . $openurl_title .
            '</article_title><journal_title>' . self::JOURNAL . '</journal_title><year>2020</year>' .
            '<volume>40</volume><issue>3</issue><first_page>12</first_page><last_page>19</last_page></query>');
        $this->with_function_stubs([
            'doi_works' => true,
            'doi_active' => true,
            'query_crossref' => $record,
            'query_crossref_newapi' => (object) [
                'DOI' => self::DOI, 'type' => 'journal-article', 'title' => [$title],
                'container-title' => [self::JOURNAL], 'article-number' => 'e1234',
            ],
        ], function () use ($template, $original, $title, $matches): void {
            expand_by_doi($template);
            if (!$matches) {
                $this->assertSame($original, $template->parsed_text());
                return;
            }
            $this->assertSame($title, $template->get('title'));
            $this->assertSame(self::DOI, $template->get('doi'));
            $this->assertSame(self::JOURNAL, $template->get('journal'));
            $this->assertSame('40', $template->get('volume'));
            $this->assertSame('12–19', $template->get('pages'));
            $this->assertSame('e1234', $template->get('article-number'));
        });
    }

    /** @return array<string, array{string, bool}> */
    public static function crossrefOpenUrlTitleCases(): array {
        return [
            'same paper with different capitalization' => ['Modelling Coral Reef Recovery: Part 1', true],
            'different numbered part despite matching REST title' => [self::TITLE . ': Part 2', false],
            'another work despite matching REST title' => ['An inventory of moss species in Patagonia', false],
        ];
    }

    #[DataProvider('crossrefArticleNumberCases')]
    public function testCrossrefArticleNumbers(string $doi, string $article_number, string $existing, string $expected): void {
        $template = $this->make_citation('{{cite journal|title=' . self::TITLE . '|doi=' . $doi .
            '|page=14' . ($existing === '' ? '' : '|article-number=' . $existing) . '}}');
        $record = new SimpleXMLElement('<query><doi>' . $doi . '</doi><article_title>' . self::TITLE .
            '</article_title><journal_title>' . self::JOURNAL . '</journal_title><year>2001</year>' .
            '<volume>54</volume><issue>12</issue><first_page>14</first_page></query>');
        $this->with_function_stubs([
            'doi_works' => true,
            'doi_active' => true,
            'query_crossref' => $record,
            'query_crossref_newapi' => (object) [
                'DOI' => $doi, 'type' => 'journal-article', 'title' => [self::TITLE],
                'container-title' => [self::JOURNAL], 'article-number' => $article_number,
            ],
        ], function () use ($template, $expected): void {
            expand_by_doi($template, true);
            $this->assertSame($expected, $template->get('article-number'));
            $this->assertSame('14', $template->get('page'));
            $this->assertSame('54', $template->get('volume'));
            $this->assertSame('12', $template->get('issue'));
        });
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function crossrefArticleNumberCases(): array {
        $doi = '10.1063/1.1445529';
        $internal_id = '00000199cf61d656a39ffff966050000';
        return [
            'reported AIP identifier' => [$doi, $internal_id, '', ''],
            'uppercase identifier' => [$doi, mb_strtoupper($internal_id), '', ''],
            'surrounding whitespace' => [$doi, ' ' . $internal_id . ' ', '', ''],
            'hyphenated UUID' => [$doi, '00000199-cf61-d656-a39f-fff966050000', '', ''],
            'ordinary article number' => [$doi, '014001', '', '014001'],
            'electronic locator' => [$doi, 'e1234', '', 'e1234'],
            'alphanumeric locator' => [$doi, '65050P', '', '65050P'],
            'long numeric locator' => [$doi, '123456789012', '', '123456789012'],
            'existing article number' => [$doi, $internal_id, '014001', '014001'],
            'other publisher unchanged' => [self::DOI, $internal_id, '', $internal_id],
        ];
    }

    /**
     * @param bool $crossref_first
     * @param string $citation_fields
     * @param array{string, string, string, string} $expected Year, volume, issue, and pages
     */
    #[DataProvider('crossrefAndPubmedConflictCases')]
    public function testCrossrefAndPubmedPreservePopulatedCitationFields(
        bool $crossref_first,
        string $citation_fields,
        array $expected
    ): void {
        $template = $this->make_citation('{{cite journal|title=' . self::TITLE . '|doi=' . self::DOI .
            '|pmid=' . self::PMID . $citation_fields . '}}');
        $templates = [$template];
        $crossref = new SimpleXMLElement('<query><doi>' . self::DOI . '</doi><article_title>' . self::TITLE .
            '</article_title><journal_title>' . self::JOURNAL . '</journal_title><year>2020</year>' .
            '<volume>40</volume><issue>3</issue><first_page>12</first_page><last_page>19</last_page></query>');
        $pubmed = new SimpleXMLElement('<eSummaryResult><DocSum><Id>' . self::PMID . '</Id>' .
            '<Item Name="Title">' . self::TITLE . '</Item><Item Name="DOI">' . self::DOI . '</Item>' .
            '<Item Name="PubDate">2021</Item><Item Name="Volume">41</Item><Item Name="Issue">4</Item>' .
            '<Item Name="Pages">50-56</Item><Item Name="AuthorList" Type="List">' .
            '<Item Name="Author">Jones A</Item></Item></DocSum></eSummaryResult>');

        $this->with_function_stubs([
            'doi_works' => true,
            'doi_active' => true,
            'query_crossref' => $crossref,
            'query_crossref_newapi' => (object) ['DOI' => self::DOI, 'type' => 'journal-article', 'title' => [self::TITLE]],
            'get_entrez_xml' => $pubmed,
        ], function () use ($template, $templates, $crossref_first, $expected): void {
            if ($crossref_first) {
                expand_by_doi($template, true);
                query_pmid_api([self::PMID], $templates);
            } else {
                query_pmid_api([self::PMID], $templates);
                expand_by_doi($template, true);
            }

            $this->assertSame('cite journal', $template->wikiname());
            $this->assertSame(self::DOI, $template->get('doi'));
            $this->assertSame(self::PMID, $template->get('pmid'));
            $this->assertSame(self::TITLE, $template->get('title'));
            $this->assertSame(self::JOURNAL, $template->get('journal'), 'Journal supplied by Crossref');
            $this->assertSame('Jones', $template->get('last1'), 'Author supplied by PubMed');
            [$year, $volume, $issue, $pages] = $expected;
            $this->assertSame($year, $template->year());
            $this->assertSame($volume, $template->get('volume'));
            $this->assertSame($issue, $template->get('issue'));
            $this->assertSame($pages, $template->get('pages'));
            if ($year === '2010') {
                $this->assertSame('7 March 2010', $template->get('date'));
            }
        });
    }

    /** @return array<string, array{bool, string, array{string, string, string, string}}> */
    public static function crossrefAndPubmedConflictCases(): array {
        $editor_fields = '|date=7 March 2010|volume=99|issue=9|pages=61–67';
        return [
            'Crossref supplies the missing fields first' => [true, '', ['2020', '40', '3', '12–19']],
            'PubMed supplies the missing fields first' => [false, '', ['2021', '41', '4', '50–56']],
            'editor values before Crossref' => [true, $editor_fields, ['2010', '99', '9', '61–67']],
            'editor values before PubMed' => [false, $editor_fields, ['2010', '99', '9', '61–67']],
            'only the blank fields are filled' => [false, '|volume=99|pages=61–67', ['2021', '99', '4', '61–67']],
            'in-press locators can be filled' => [
                true, '|volume=in press|issue=in press|pages=in press', ['2020', '40', '3', '12–19'],
            ],
        ];
    }

    #[DataProvider('pubmedDoiReplacementCases')]
    public function testPubmedDoiCandidateRespectsExistingIdentifier(string $existing_fields, string $candidate, bool $works, string $expected): void {
        $template = $this->make_citation('{{cite journal|pmid=' . self::PMID . $existing_fields . '}}');
        $templates = [$template];
        $summary = new SimpleXMLElement('<eSummaryResult><DocSum><Id>' . self::PMID . '</Id>' .
            '<Item Name="ArticleIds" Type="List"><Item Name="doi">' . $candidate . '</Item></Item>' .
            '<Item Name="Volume">42</Item></DocSum></eSummaryResult>');
        $this->with_function_stubs([
            'get_entrez_xml' => $summary,
            'doi_works' => $works,
            'doi_active' => $works,
        ], function () use ($templates, $template, $expected): void {
            query_pmid_api([self::PMID], $templates);
            $this->assertSame($expected, $template->get('doi'));
            $this->assertSame(self::PMID, $template->get('pmid'));
            $this->assertSame('42', $template->get('volume'));
        });
    }

    /** @return array<string, array{string, string, bool, string}> */
    public static function pubmedDoiReplacementCases(): array {
        $published = '10.1000/published-work';
        $repository = '10.13140/RG.2.2.12345.67890';
        $existing = '|doi=' . self::DOI;
        $inactive = $existing . '|doi-broken-date=2020-01-01';
        return [
            'working candidate conflicts with ordinary DOI' => [$existing, $published, true, self::DOI],
            'equivalent DOI preserves citation spelling' => [$existing, mb_strtoupper(self::DOI), true, self::DOI],
            'working candidate replaces marked broken DOI' => [$inactive, $published, true, $published],
            'unavailable candidate cannot replace marked broken DOI' => [$inactive, $published, false, self::DOI],
            'publisher DOI replaces ResearchGate DOI' => ['|doi=' . $repository, $published, true, $published],
            'unavailable publisher DOI preserves ResearchGate DOI' => ['|doi=' . $repository, $published, false, $repository],
        ];
    }

    #[DataProvider('citationLocatorAliasCases')]
    public function testPubmedLocatorsRespectCitationAliases(string $field, string $value, string $remote_field, string $canonical): void {
        $template = $this->make_citation('{{cite journal|pmid=' . self::PMID . '|' . $field . '=' . $value . '}}');
        $templates = [$template];
        $summary = new SimpleXMLElement('<eSummaryResult><DocSum><Id>' . self::PMID . '</Id>' .
            '<Item Name="' . $remote_field . '">17</Item><Item Name="Title">' . self::TITLE . '</Item></DocSum></eSummaryResult>');
        $this->with_function_stubs(['get_entrez_xml' => $summary], function () use ($templates, $template, $field, $value, $canonical): void {
            query_pmid_api([self::PMID], $templates);
            $this->assertSame($value, $template->get($field));
            $this->assertNull($template->get2($canonical));
            $this->assertSame(self::TITLE, $template->get('title'));
        });
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function citationLocatorAliasCases(): array {
        return [
            'issue supplied as number' => ['number', '9', 'Issue', 'issue'],
            'single page supplied as page' => ['page', '61', 'Pages', 'pages'],
            'specific location supplied as at' => ['at', 'Table 3', 'Pages', 'pages'],
        ];
    }

    public function testPubmedBatchIgnoresAnUnrequestedRecordWithTheSameTitle(): void {
        $missing = $this->make_citation('{{cite journal|pmid=12345679|title=' . self::TITLE . '}}');
        $matched = $this->make_citation('{{cite journal|pmid=' . self::PMID . '}}');
        $templates = [2 => $missing, 5 => $matched];
        $original = $missing->parsed_text();
        $entrez = new SimpleXMLElement('<eSummaryResult>' .
            '<DocSum><Id>12345680</Id><Item Name="Title">' . self::TITLE . '</Item>' .
            '<Item Name="DOI">10.1000/unrequested-work</Item><Item Name="Volume">99</Item></DocSum>' .
            '<DocSum><Id>' . self::PMID . '</Id><Item Name="Title">' . self::TITLE . '</Item>' .
            '<Item Name="DOI">' . self::DOI . '</Item><Item Name="Volume">40</Item></DocSum></eSummaryResult>');
        $this->with_function_stubs([
            'doi_works' => true,
            'get_entrez_xml' => $entrez,
        ], function () use ($templates, $missing, $matched, $original): void {
            query_pmid_api([2 => '12345679', 5 => self::PMID], $templates);
            $this->assertSame($original, $missing->parsed_text());
            $this->assertSame(self::TITLE, $matched->get('title'));
            $this->assertSame(self::DOI, $matched->get('doi'));
            $this->assertSame('40', $matched->get('volume'));
        });
    }

    #[DataProvider('arxivVersionCases')]
    public function testArxivDoesNotSubstituteADifferentVersion(string $id, bool $matches): void {
        $template = $this->make_citation('{{cite arXiv|eprint=' . $id . '}}');
        $original = $template->parsed_text();
        $templates = [$template];
        $atom = '<feed><entry><id>https://arxiv.org/abs/2001.01234v2</id>' .
            '<title>Geometric bounds for random graphs</title><published>2020-01-10T00:00:00Z</published>' .
            '<author><name>Alex Jones</name></author><category term="math.CO"/></entry></feed>';
        $this->with_function_stubs(['bot_curl_exec' => $atom], function () use ($template, $templates, $id, $original, $matches): void {
            arxiv_api([$id], $templates);
            if (!$matches) {
                $this->assertSame($original, $template->parsed_text());
                return;
            }
            $this->assertSame($id, $template->get('eprint'));
            $this->assertSame('Geometric bounds for random graphs', $template->get('title'));
            $this->assertSame('Jones', $template->get('last1'));
            $this->assertSame('2020', $template->year());
        });
    }

    /** @return array<string, array{string, bool}> */
    public static function arxivVersionCases(): array {
        return [
            'version requested matches the response' => ['2001.01234v2', true],
            'unversioned request can use a versioned response' => ['2001.01234', true],
            'version requested differs from response' => ['2001.01234v1', false],
            'identifier requested differs from response' => ['2001.01235v2', false],
        ];
    }

    #[DataProvider('adsDoiAndYearCases')]
    public function testAdsRequiresAgreementWithExistingDoiAndYear(string $record_doi, string $record_year, bool $matches): void {
        $template = $this->make_citation('{{cite journal|title=' . self::TITLE . '|doi=' . self::DOI .
            '|date=2020|bibcode=tmp-metadata-conflict}}');
        $original = $template->parsed_text();
        $bibcode = '2020FakeJ..40...12J';
        $record = (object) [
            'title' => [self::TITLE], 'bibcode' => $bibcode, 'doi' => [$record_doi],
            'year' => $record_year, 'pub' => self::JOURNAL, 'volume' => '40',
        ];
        $this->with_function_stubs(['doi_works' => true, 'doi_active' => true], function () use ($template, $original, $record, $matches): void {
            $queries = [];
            expand_by_adsabs($template, static function (string $query) use ($record, &$queries): stdClass {
                $queries[] = urldecode($query);
                return (object) ['numFound' => 1, 'docs' => [$record]];
            });
            $this->assertSame(['identifier:"' . self::DOI . '"'], $queries);
            if (!$matches) {
                $this->assertSame($original, $template->parsed_text());
                return;
            }
            $this->assertSame(self::DOI, $template->get('doi'));
            $this->assertSame('2020', $template->year());
            $this->assertSame(self::JOURNAL, $template->get('journal'));
            $this->assertSame('40', $template->get('volume'));
        });
    }

    /** @return array<string, array{string, string, bool}> */
    public static function adsDoiAndYearCases(): array {
        return [
            'equivalent DOI with different case' => [mb_strtoupper(self::DOI), '2020', true],
            'different DOI with the same title and year' => ['10.1000/other-coral-study', '2020', false],
            'same DOI with conflicting publication year' => [self::DOI, '2019', false],
        ];
    }
}
