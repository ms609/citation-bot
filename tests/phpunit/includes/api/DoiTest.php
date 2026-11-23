<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class DoiTest extends testBaseClass {
    public function testCrossrefContributorDecodesHtmlEntitiesBeforeNameFormatting(): void {
        $template = $this->make_citation('{{cite journal|title=Example|doi=10.5555/example}}');
        $record = new SimpleXMLElement(
            '<query><article_title>Example</article_title><journal_title>Example Journal</journal_title>' .
            '<contributors><contributor contributor_role="author"><surname>M&amp;amp;uuml;ller</surname>' .
            '<given_name>Fran&amp;amp;ccedil;ois</given_name></contributor>' .
            '<contributor contributor_role="author"><surname>Smith&amp;#60;ref&amp;#62;Test&amp;#60;/ref&amp;#62;</surname>' .
            '<given_name>Jane</given_name></contributor></contributors></query>'
        );
        $this->assertTrue(uopz_set_return('doi_works', true));
        $this->assertTrue(uopz_set_return('query_crossref', $record));
        $this->assertTrue(uopz_set_return('query_crossref_newapi', (object) []));
        try {
            expand_by_doi($template, true);
            $this->assertSame('Müller', $template->get('last1'));
            $this->assertSame('François', $template->get('first1'));
            $this->assertStringContainsString('&#60;', $template->get('last2'));
            $this->assertStringNotContainsString('<', $template->get('last2'));
            $this->assertStringNotContainsString('<Ref>', $template->parsed_text());
        } finally {
            $this->assertTrue(uopz_unset_return('query_crossref_newapi'));
            $this->assertTrue(uopz_unset_return('query_crossref'));
            $this->assertTrue(uopz_unset_return('doi_works'));
        }
    }

    public function testCrossrefStopsAfterTwoUnusableResponses(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Crossref');
        }
        $attempts = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('bot_curl_exec', static function () use (&$attempts): string {
            return ++$attempts === 1 ? '' : '<broken';
        }, true);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('sleep', 0);
        try {
            $this->assertNull(query_crossref('10.1000/coverage-fixture'));
            $this->assertSame(2, $attempts);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('sleep');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('bot_curl_exec');
        }
    }

    public function testJournalDoiDoesNotEnrichProceedingsCitation(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub DOI metadata');
        }

        $doi = '10.7155/jgaa.00318';
        $record = new SimpleXMLElement(
            '<query><article_title>Superpatterns and Universal Point Sets</article_title>' .
            '<journal_title>Journal of Graph Algorithms and Applications</journal_title>' .
            '<year>2014</year><volume>18</volume><issue>2</issue>' .
            '<first_page>177</first_page><last_page>209</last_page></query>'
        );
        $stubs = [
            'bot_curl_exec' => '',
            'curl_exec' => false,
            'sleep' => 0,
            'doi_works' => true,
            'doi_active' => true,
            'query_crossref' => $record,
            'query_crossref_newapi' => (object) ['type' => 'journal-article'],
        ];
        foreach ($stubs as $name => $value) {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_set_return($name, $value);
        }
        try {
            $proceedings = '[[International Symposium on Graph Drawing|Proc. 21st International Symposium on Graph Drawing (GD 2013)]]';
            $citation = $this->process_citation(
                '{{citation|contribution=Superpatterns and universal point sets|title=' . $proceedings .
                '|year=2013|doi=' . $doi . '|bibcode=2013arXiv1308.0403B|s2cid=6229641}}'
            );
            $this->assertSame('Superpatterns and universal point sets', $citation->get('contribution'));
            $this->assertSame($proceedings, $citation->get('title'));
            $this->assertSame('2013', $citation->get('year'));
            $this->assertSame($doi, $citation->get('doi'));
            foreach (['journal', 'volume', 'issue', 'pages'] as $parameter) {
                $this->assertNull($citation->get2($parameter), $parameter);
            }
        } finally {
            foreach (array_keys($stubs) as $name) {
                /** @psalm-suppress UnusedFunctionCall */
                uopz_unset_return($name);
            }
        }
    }

    public function testProceedingsCitationRejectsJournalDoiJson(): void {
        $doi = '10.7155/jgaa.00318';
        $citation = $this->make_citation(
            '{{cite book|chapter=Superpatterns and universal point sets|' .
            'title=Proceedings of the 21st International Symposium on Graph Drawing|year=2013|doi=' . $doi . '}}'
        );
        $original = $citation->parsed_text();
        process_doi_json($citation, $doi, [
            'type' => 'article-journal',
            'title' => 'Superpatterns and Universal Point Sets',
            'container-title' => 'Journal of Graph Algorithms and Applications',
            'volume' => '18',
            'issue' => '2',
            'pages' => '177-209',
        ]);
        $this->assertSame($original, $citation->parsed_text());

        if (function_exists('uopz_set_return')) {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_set_return('get_unpaywall_url', static function (): string {
                throw new LogicException('A DOI for a different version must not supply an open access URL');
            }, true);
            try {
                get_open_access_url($citation);
            } finally {
                /** @psalm-suppress UnusedFunctionCall */
                uopz_unset_return('get_unpaywall_url');
            }
        }

        process_bibcode_data($citation, (object) [
            'title' => ['Superpatterns and Universal Point Sets'],
            'pub' => 'Journal of Graph Algorithms and Applications',
            'volume' => '18',
            'page' => ['177-209'],
        ]);
        $this->assertSame($original, $citation->parsed_text());
    }

    public function testProceedingsCitationCanUseProceedingsDoiJson(): void {
        $doi = '10.1000/proceedings';
        foreach (['proceedings-article', 'article-journal'] as $type) {
            $citation = $this->make_citation(
                '{{cite book|chapter=Example paper|title=Proceedings of the Example Symposium|doi=' . $doi . '}}'
            );
            process_doi_json($citation, $doi, [
                'type' => $type,
                'container-title' => 'Proceedings of the Example Symposium',
                'pages' => '1-12',
            ]);
            $this->assertSame('1–12', $citation->get('pages'));
        }
    }

    public function testCrossRefNewApiParserAcceptsObjectMessage(): void {
        $result = parse_crossref_newapi_response(
            '{"status":"ok","message":{"title":["Expected title"]}}'
        );

        $this->assertNotNull($result);
        $this->assertSame(['Expected title'], $result->title);
    }

    public function testCrossRefNewApiParserRejectsNonObjectMessage(): void {
        foreach (['[]', 'null', '"metadata"', '42', 'true'] as $message) {
            $result = parse_crossref_newapi_response(
                '{"status":"ok","message":' . $message . '}'
            );
            $this->assertNull($result);
        }
    }

    public function testCrossRefNewApiParserRejectsInvalidEnvelope(): void {
        $this->assertNull(parse_crossref_newapi_response('not json'));
        $this->assertNull(parse_crossref_newapi_response('{"status":"error","message":{}}'));
        $this->assertNull(parse_crossref_newapi_response('{"status":[],"message":{}}'));
    }

    public function testCrossRefNewApiParserRejectsMalformedNestedFields(): void {
        foreach ([
            '{"status":"ok","message":{"title":{}}}',
            '{"status":"ok","message":{"title":[{}]}}',
            '{"status":"ok","message":{"subtitle":"bad"}}',
            '{"status":"ok","message":{"type":[]}}',
            '{"status":"ok","message":{"article-number":{}}}',
        ] as $response) {
            $this->assertNull(parse_crossref_newapi_response($response));
        }
    }

    public function testCrossRefNewApiParserNormalizesScalarTitleEntries(): void {
        $result = parse_crossref_newapi_response(
            '{"status":"ok","message":{"title":[123],"subtitle":[true],"type":"journal-article"}}'
        );
        $this->assertNotNull($result);
        $this->assertSame(['123'], $result->title);
        $this->assertSame(['1'], $result->subtitle);
    }

    public function testDoiJsonHelpersRejectObjectsAndBadPaths(): void {
        $this->assertNull(doi_json_scalar((object) ['bad' => 'shape']));
        $this->assertNull(doi_json_get(['issued' => 'bad'], ['issued', 'date-parts', 0, 0]));
        $this->assertNull(doi_json_get(['issn' => (object) ['bad' => true]], ['issn', 0]));
        $this->assertSame('2026', doi_json_scalar(2026));
        $this->assertSame(
            2026,
            doi_json_get(['issued' => ['date-parts' => [[2026]]]], ['issued', 'date-parts', 0, 0])
        );
    }

    public function testProcessDoiJsonIgnoresMalformedNestedValues(): void {
        $template = $this->make_citation('{{cite journal}}');
        process_doi_json($template, '10.1000/test', [
            'issued' => (object) ['date-parts' => [[2026]]],
            'ISBN' => (object) ['unexpected' => true],
            'issn' => (object) ['unexpected' => true],
            'author' => [
                ['given' => (object) ['unexpected' => true], 'family' => 'Valid'],
                'not-an-array',
            ],
            'URL' => [],
            'title' => (object) ['unexpected' => true],
            'type' => 'article-journal',
        ]);

        $this->assertNull($template->get2('title'));
        $this->assertNull($template->get2('year'));
    }

    /**
     * @param Template $template
     * @param array<string, mixed> $json
     */
    private function processDoiJsonOffline(Template $template, array $json): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to guard offline DOI metadata tests');
        }
        $requests = new ArrayObject();
        $this->assertTrue(uopz_set_return('curl_exec', static function () use ($requests): never {
            $requests->append(true);
            throw new LogicException('Unexpected network request while applying DOI JSON metadata');
        }, true));
        try {
            process_doi_json($template, '10.1000/offline-metadata', $json);
            $this->assertCount(0, $requests);
        } finally {
            $this->assertTrue(uopz_unset_return('curl_exec'));
        }
    }

    /**
     * @param string $citation
     * @param list<mixed> $editors
     * @param string $expected
     */
    #[DataProvider('doiJsonEditorsProvider')]
    public function testDoiJsonMetadataEditors(string $citation, array $editors, string $expected): void {
        $template = $this->make_citation($citation);
        $this->processDoiJsonOffline($template, ['type' => 'book', 'editor' => $editors]);
        $this->assertSame($expected, $template->parsed_text());
    }

    /** @return array<string, array{string, list<mixed>, string}> */
    public static function doiJsonEditorsProvider(): array {
        $person = ['given' => 'Jane', 'family' => 'Smith'];
        return [
            'given and family' => ['{{cite book}}', [$person], '{{cite book| editor-first1=Jane | editor-last1=Smith }}'],
            'given only' => ['{{cite book}}', [['given' => 'Prince']], '{{cite book| editor1=Prince }}'],
            'literal name' => ['{{cite book}}', [['literal' => 'Example Consortium']], '{{cite book| editor1=Example Consortium }}'],
            'malformed entries do not consume a number' => ['{{cite book}}', [null, 'invalid', $person], '{{cite book| editor-first1=Jane | editor-last1=Smith }}'],
            'invalid given field cannot become citation text' => ['{{cite book}}', [['given' => ['bad'], 'family' => 'Smith']], '{{cite book| editor-last1=Smith }}'],
            'rejected contributor stops the list' => ['{{cite book}}', [['literal' => 'author'], $person], '{{cite book}}'],
            'existing editor is preserved' => ['{{cite book|editor=Original Editor}}', [$person], '{{cite book|editor=Original Editor}}'],
            'journal does not acquire book editors' => ['{{cite journal|title=Original title}}', [$person], '{{cite journal|title=Original title}}'],
        ];
    }

    #[DataProvider('doiJsonAuthorDateSuffixesProvider')]
    public function testDoiJsonMetadataAuthorDateSuffixes(string $name, string $expected): void {
        $template = $this->make_citation('{{cite book}}');
        $this->processDoiJsonOffline($template, ['type' => 'book', 'author' => [['literal' => $name]]]);
        $this->assertSame($expected, $template->parsed_text());
    }

    /** @return array<string, array{string, string}> */
    public static function doiJsonAuthorDateSuffixesProvider(): array {
        $person = '{{cite book| last1=Smith | first1=Jane }}';
        return [
            'birth and death' => ['Smith, Jane, 1900-1980', $person],
            'birth only' => ['Smith, Jane, 1900-', $person],
            'death only' => ['Smith, Jane, -1980', $person],
            'digits in a corporate name' => ['3M', '{{cite book| author1=3M }}'],
        ];
    }

    #[DataProvider('doiJsonFieldValuesProvider')]
    public function testDoiJsonMetadataFieldValues(mixed $value, ?string $publisher): void {
        $citation = '{{cite book|title=Original title}}';
        $template = $this->make_citation($citation);
        $this->processDoiJsonOffline($template, [
            'type' => 'book', 'title' => 'Replacement title', 'publisher' => $value,
        ]);
        $this->assertSame('Original title', $template->get2('title'));
        $this->assertSame($publisher, $template->get2('publisher'));
        $this->assertSame(
            $publisher === null ? $citation : '{{cite book|title=Original title |publisher=Fixture Press }}',
            $template->parsed_text()
        );
    }

    /** @return array<string, array{mixed, string|null}> */
    public static function doiJsonFieldValuesProvider(): array {
        return [
            'scalar' => ['Fixture Press', 'Fixture Press'],
            'single-item array' => [['Fixture Press'], 'Fixture Press'],
            'nested single-item arrays' => [[[['Fixture Press']]], 'Fixture Press'],
            'null' => [null, null],
            'empty string' => ['', null],
            'empty array' => [[], null],
            'multiple values' => [['Fixture Press', 'Other Press'], null],
            'nested multiple values' => [[['Fixture Press', 'Other Press']], null],
            'associative array' => [['name' => 'Fixture Press'], null],
            'object' => [(object) ['name' => 'Fixture Press'], null],
            'false' => [false, null],
            'array placeholder' => ['Array', null],
            'unknown placeholder' => ['Unknown', null],
            'PDF filename' => ['metadata.PDF', null],
        ];
    }

    #[DataProvider('doiJsonWorkTypesProvider')]
    public function testDoiJsonMetadataWorkTypes(
        string $type,
        bool $has_container,
        string $expected_name,
        string $title,
        ?string $chapter
    ): void {
        $json = [
            'type' => $type, 'title' => 'Fixture Title',
            'publisher' => 'Fixture Press', 'publisher-location' => 'Paris',
        ];
        if ($has_container) {
            $json['container-title'] = 'Fixture Collection';
        }
        $template = $this->make_citation('{{cite book}}');
        $this->processDoiJsonOffline($template, $json);
        $this->assertSame($expected_name, $template->wikiname());
        $this->assertSame($title, $template->get2('title'));
        $this->assertSame($chapter, $template->get2('chapter'));
        $this->assertSame('Fixture Press', $template->get2('publisher'));
        $this->assertSame('Paris', $template->get2('location'));
        $this->assertSame(
            '{{' . $expected_name . '| title=' . $title . ' |' .
                ($chapter === null ? '' : ' chapter=' . $chapter . ' |') .
                ' publisher=Fixture Press | location=Paris }}',
            $template->parsed_text()
        );

        $original = '|title=Original title|chapter=Original chapter|publisher=Original press|location=Rome}}';
        $preserved = $this->make_citation('{{cite book' . $original);
        $this->processDoiJsonOffline($preserved, $json);
        $this->assertSame('{{cite book' . $original, $preserved->parsed_text());
    }

    /** @return iterable<string, array{string, bool, string, string, string|null}> */
    public static function doiJsonWorkTypesProvider(): iterable {
        foreach (['other', 'software'] as $type) {
            yield $type . ' with container' => [$type, true, 'cite book', 'Fixture Collection', 'Fixture Title'];
            yield $type . ' without container' => [$type, false, 'cite book', 'Fixture Title', null];
        }
        foreach (['', 'graphic', 'report', 'report-component', 'standard'] as $type) {
            yield 'standalone ' . $type => [$type, false, 'cite book', 'Fixture Title', null];
        }
        foreach (['thesis', 'dissertation', 'dissertation-thesis'] as $type) {
            yield $type => [$type, false, 'cite thesis', 'Fixture Title', null];
        }
    }

    public function testDoiJsonMetadataStandardPrefersStandardsBodyToPublisher(): void {
        $template = $this->make_citation('{{cite book}}');
        $this->processDoiJsonOffline($template, [
            'type' => 'standard', 'title' => 'Fixture Standard',
            'standards-body' => ['name' => 'Fixture Standards Body'], 'publisher' => 'Other Publisher',
        ]);
        $this->assertSame('{{cite book| title=Fixture Standard | publisher=Fixture Standards Body }}', $template->parsed_text());
    }

    /** @param array<string, mixed> $dates */
    #[DataProvider('doiJsonYearsProvider')]
    public function testDoiJsonMetadataYearPrecedence(array $dates, ?string $expected): void {
        $json = ['type' => 'book', ...$dates];
        $template = $this->make_citation('{{cite book}}');
        $this->processDoiJsonOffline($template, $json);
        $this->assertSame($expected, $template->get2('date'));
        $this->assertNull($template->get2('year'));
        $this->assertSame($expected === null ? '{{cite book}}' : '{{cite book| date=' . $expected . ' }}', $template->parsed_text());

        foreach (['{{cite book|date=1999-06-15}}', '{{cite book|year=1999}}'] as $citation) {
            $preserved = $this->make_citation($citation);
            $this->processDoiJsonOffline($preserved, $json);
            $this->assertSame($citation, $preserved->parsed_text());
        }
    }

    /** @return array<string, array{array<string, mixed>, string|null}> */
    public static function doiJsonYearsProvider(): array {
        $created = ['created' => ['date-parts' => [[2002]]]];
        $printed = ['published-print' => ['date-parts' => [[2003]]]];
        return [
            'issued wins' => [['issued' => ['date-parts' => [[2001]]], ...$created, ...$printed], '2001'],
            'created fallback' => [[...$created, ...$printed], '2002'],
            'invalid issued falls back' => [['issued' => ['date-parts' => [['Unknown']]], ...$created, ...$printed], '2002'],
            'zero issued falls back' => [['issued' => ['date-parts' => [[0]]], ...$created], '2002'],
            'print fallback' => [['issued' => null, 'created' => ['date-parts' => [[]]], ...$printed], '2003'],
            'unusable dates stay absent' => [['issued' => ['date-parts' => [['invalid']]], 'created' => null], null],
        ];
    }

    #[DataProvider('doiJsonHandleTypesProvider')]
    public function testDoiJsonMetadataHandleExtraction(string $type): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Handle resolution');
        }
        $handles = new ArrayObject();
        $this->assertTrue(uopz_set_return('hdl_works', static function (string $handle) use ($handles): string {
            $handles->append($handle);
            return 'https://example.test/fulltext';
        }, true));
        try {
            foreach ([
                'https://hdl.handle.net/2027.42/1234' => '2027.42/1234',
                'https://example.test/item' => null,
            ] as $url => $expected) {
                $template = $this->make_citation('{{cite book}}');
                $this->processDoiJsonOffline($template, ['type' => $type, 'URL' => $url]);
                $this->assertSame($expected, $template->get2('hdl'));
                $this->assertNull($template->get2('url'));
                $this->assertSame($type === 'thesis' ? 'cite thesis' : 'cite book', $template->wikiname());
            }
            $this->assertSame(['2027.42/1234'], $handles->getArrayCopy());
        } finally {
            $this->assertTrue(uopz_unset_return('hdl_works'));
        }
    }

    /** @return array<string, array{string}> */
    public static function doiJsonHandleTypesProvider(): array {
        return ['thesis' => ['thesis'], 'standard' => ['standard']];
    }

    public function testBiorxivParserRejectsMalformedNestedFields(): void {
        $status = null;
        $this->assertNull(parse_biorxiv_publication_response(
            '{"messages":[{"status":{}}],"collection":[{"published_doi":{}}]}',
            $status
        ));
        $this->assertNull($status);

        $this->assertSame(
            '10.1000/published',
            parse_biorxiv_publication_response(
                '{"messages":[{"status":"ok"}],"collection":[{"published_doi":"10.1000/published"}]}',
                $status
            )
        );
        $this->assertSame('ok', $status);
    }

    public function testBiorxivParserAcceptsUtf8Bom(): void {
        $status = null;
        $response =
            "\xEF\xBB\xBF" .
            '{"messages":[{"status":"ok"}],"collection":[{"published_doi":"10.1000/published"}]}';

        $this->assertSame(
            '10.1000/published',
            parse_biorxiv_publication_response($response, $status)
        );
        $this->assertSame('ok', $status);
    }

    public function testBiorxivParserAcceptsBomBeforeJsonWhitespace(): void {
        $status = null;
        $response =
            "\xEF\xBB\xBF \n" .
            '{"messages":[{"status":"ok"}],"collection":[{"published_doi":"10.1000/published"}]}';

        $this->assertSame(
            '10.1000/published',
            parse_biorxiv_publication_response($response, $status)
        );
        $this->assertSame('ok', $status);
    }

    public function testBiorxivParserRejectsInvalidUtf8(): void {
        $status = null;
        $response =
            '{"messages":[{"status":"ok"}],"collection":[{"published_doi":"10.1000/' .
            "\xFF" .
            '"}]}';

        $this->assertNull(
            parse_biorxiv_publication_response($response, $status)
        );
        $this->assertNull($status);
    }

    public function testBiorxivParserRejectsExcessiveJsonNesting(): void {
        $status = null;
        $response = str_repeat('{"x":', 140) . '1' . str_repeat('}', 140);

        $this->assertNull(
            parse_biorxiv_publication_response($response, $status)
        );
        $this->assertNull($status);
    }

    public function testBiorxivParserRejectsWrongTopLevelJsonShapes(): void {
        foreach (['[]', 'null', '"metadata"', '123', 'true'] as $response) {
            $status = 'stale';
            $this->assertNull(
                parse_biorxiv_publication_response($response, $status),
                $response
            );
            $this->assertNull($status);
        }
    }

    public function testBiorxivParserRejectsDoubleUtf8Bom(): void {
        $status = null;
        $response =
            "\xEF\xBB\xBF\xEF\xBB\xBF" .
            '{"messages":[{"status":"ok"}],"collection":[{"published_doi":"10.1000/published"}]}';

        $this->assertNull(parse_biorxiv_publication_response($response, $status));
        $this->assertNull($status);
    }

    public function testBiorxivParserRejectsBomAfterWhitespace(): void {
        $status = null;
        $response =
            " \xEF\xBB\xBF" .
            '{"messages":[{"status":"ok"}],"collection":[{"published_doi":"10.1000/published"}]}';

        $this->assertNull(parse_biorxiv_publication_response($response, $status));
        $this->assertNull($status);
    }

    public function testExpansion_doi_not_from_crossrefRG(): void {
        $text = '{{Cite journal| doi= 10.13140/RG.2.1.1002.9609|pmid=<!-- -->|pmc=<!-- -->}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('Lesson Study as a form of in-School Professional Development', $expanded->get2('title'));
        $this->assertSame('2015', $expanded->get2('date'));
        $this->assertSame('Aoibhinn Ni Shuilleabhain', $expanded->get2('author1'));
    }

    public function testExpansion_doi_not_from_crossrefJapanJournal(): void {
        $text = '{{cite journal|doi=10.11429/ppmsj1919.17.0_48|pmid=<!-- -->|pmc=<!-- -->}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('On the Interaction of Elementary Particles. I', $expanded->get2('title'));
        $this->assertSame('1935', $expanded->get2('date'));
        $this->assertSame('Proceedings of the Physico-Mathematical Society of Japan. 3rd Series', $expanded->get2('journal'));
        $this->assertSame('17', $expanded->get2('volume'));
        $this->assertSame('YUKAWA', $expanded->get2('last1'));
        $this->assertSame('Hideki', $expanded->get2('first1'));
    }

    /** See https://www.doi.org/demos.html  NOT ALL EXPAND AT THIS TIME */
    public function testExpansion_doi_not_from_crossrefBook(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.1017/CBO9780511983658');  // This is cross-ref doi, so for DX DOI expansion
        $this->assertSame('{{Cite book| last1=Luo | first1=Zhi-Quan | last2=Pang | first2=Jong-Shi | last3=Ralph | first3=Daniel | title=Mathematical Programs with Equilibrium Constraints | date=1996 | publisher=Cambridge University Press | isbn=978-0-521-57290-3 }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossrefBookChapter(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.1002/0470841559.ch1');  // This is cross-ref doi, so for DX DOI expansion
        $this->assertSame('{{Cite book| title=Internetworking LANs and WANs | chapter=Network Concepts | date=2001 | pages=1–30 | publisher=Wiley | isbn=978-0-471-97514-4 }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossrefDataCiteSubsets(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.1594/PANGAEA.726855');
        $this->assertSame('{{Cite journal| last1=Irino | first1=Tomohisa | last2=Tada | first2=Ryuji | title=Chemical and mineral compositions of sediments from ODP Site 127-797 | date=2009 }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossrefDataCiteEarthquake(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.1594/GFZ.GEOFON.gfz2009kciu');
        $this->assertSame('{{Cite journal| author1=Geofon Operator | title=GEOFON event gfz2009kciu (NW Balkan Region) | date=2009 | publisher=Deutsches GeoForschungsZentrum GFZ }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossrefDataCiteMappedVisualization(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.1594/PANGAEA.667386');
        $this->assertSame('{{Cite book| last1=Kraus | first1=Stefan | last2=del Valle | first2=Rodolfo | title=Geological map of Potter Peninsula (King George Island, South Shetland Islands, Antarctic Peninsula) | chapter=Impact of climate induced glacier melt on marine coastal systems, Antarctica (IMCOAST/IMCONet) | date=2008 | publisher=Pangaea }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossrefDataCitevideo(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.3207/2959859860');
        $this->assertSame('{{Cite journal| last1=Kirchhof | first1=Bernd | title=Silicone oil bubbles entrapped in the vitreous base during silicone oil removal | date=2009 }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossref_fISTIC_Journal(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.3866/PKU.WHXB201112303');
        $this->assertSame('{{Cite journal| last3=Ning | first3=MA | last4=Wei-Zhou | first4=WANG | last1=Yu | first1=ZHANG | title=Correlation between Bond-Length Change and Vibrational Frequency Shift in Hydrogen-Bonded Complexes Revisited | journal=Acta Physico-Chimica Sinica | date=2012 | volume=28 | issue=3 | pages=499–503 }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossref_fISTIC_Data(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.3972/water973.0145.db');
        $this->assertSame('{{Cite journal}}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossref_ISTIC_Thesis(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.7666/d.y351065');
        $this->assertSame('{{Cite journal}}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossref_JaLC_Journal(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub DOI content negotiation');
        }
        $expanded = $this->make_citation('{{Cite journal}}');
        $response = json_encode([
            'type' => 'article-journal',
            'title' => '大学におけるWebメールとターミナルサービスの研究',
            'container-title' => 'Society for Standardization Studies',
            'author' => [
                ['family' => '竹本', 'given' => '賢太郎'],
                ['family' => '川東', 'given' => '正美'],
                ['family' => '久保', 'given' => '信行'],
                ['family' => '左近', 'given' => '多喜男'],
            ],
            'issued' => ['date-parts' => [[2009]]],
            'volume' => '7',
            'page' => '11-20',
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('bot_curl_exec', $response);
        try {
            expand_doi_with_dx($expanded, '10.11467/isss2003.7.1_11');
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('bot_curl_exec');
        }
        $this->assertSame('{{Cite journal| last1=竹本 | first1=賢太郎 | last2=川東 | first2=正美 | last3=久保 | first3=信行 | last4=左近 | first4=多喜男 | title=大学におけるWebメールとターミナルサービスの研究 | journal=Society for Standardization Studies | date=2009 | volume=7 | pages=11–20 }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossref_JaLC_JournalChecksCiteJournal107875LeadingAuthor200(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.7875/leading.author.2.e008');
        $this->assertSame('{{Cite journal| last1=川崎 | first1=努 | title=植物における免疫誘導と病原微生物の感染戦略 | journal=領域融合レビュー | date=2013 | volume=2 }}', $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossref_mEDRA_Journal(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.1430/8105');
        if ($expanded->get2('title') === null) {
            $this->markTestSkipped('doi.org API did not respond (rate limit or outage)');
        }
        $this->assertSame("{{Cite journal| author1=Romano Prodi | title=L'Industria dopo l'euro | journal=L'Industria | date=2002 | issue=4 | pages=559–566 }}", $expanded->parsed_text());
    }

    public function testExpansion_doi_not_from_crossref_mEDRA_Monograph(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.1392/BC1.0');
        if ($expanded->get2('title') === null) {
            $this->markTestSkipped('doi.org API did not respond (rate limit or outage)');
        }
        $this->assertSame('{{Cite journal| last1=Attanasio | first1=Piero | title=The use of Doi in eContent value chain | date=2004 | publisher=mEDRA }}', $expanded->parsed_text());
    }

    /** http://doi.airiti.com/. They allow you to easily find the RA, but they seem to no longer do meta-data http://www.airitischolar.com/doi/WhichRA/index.jsp */
    public function testExpansion_doi_not_from_crossref_airiti_journal(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.6620/ZS.2018.57-30');
        if ($expanded->parsed_text() === '{{Cite journal}}') {
            $this->assertSame('{{Cite journal}}', $expanded->parsed_text());
        } else {
            $this->assertSame('{{Cite journal| author1=Jun Aoyama | author2=Sam Wouthuyzen | author3=Michael J. Miller | author4=Hagi Y. Sugeha | author5=Mari Kuroki | author6=Shun Watanabe | author7=Augy Syahailatua | author8=Fadly Y. Tantu | author9=Seishi Hagihara | author10=Triyanto | author11=Tsuguo Otake | author12=Katsumi Tsukamoto | title=Reproductive Ecology and Biodiversity of Freshwater Eels around Sulawesi Island Indonesia | journal=Zoological Studies | date=2018 | volume=無 | issue=57 }}', $expanded->parsed_text());
        }
    }

    /** http://www.eidr.org/ */
    public function testExpansion_doi_not_from_crossref_eidr_Black_Panther_Movie(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.5240/7B2F-ED76-31F6-8CFB-4DB9-M');
        if ($expanded->get2('title') === null) {
            $this->markTestSkipped('doi.org API did not respond (rate limit or outage)');
        }
        $this->assertSame('{{Cite journal| last1=Coogler | first1=Ryan | title=Black Panther | date=2018 }}', $expanded->parsed_text());
    }

    /** http://www.kisti.re.kr/eng/ */
    public function testExpansion_doi_not_from_crossref_kisti_journal(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        expand_doi_with_dx($expanded, '10.3743/KOSIM.2011.28.2.117');
        if ($expanded->parsed_text() === '{{Cite journal}}') {
            $this->assertSame('{{Cite journal}}', $expanded->parsed_text());
        } else {
            $this->assertSame('{{Cite journal| last1=Kim | first1=Byung-Kyu | last2=Kang | first2=Mu-Yeong | last3=Choi | first3=Seon-Heui | last4=Kim | first4=Soon-Young | last5=You | first5=Beom-Jong | last6=Shin | first6=Jae-Do | title=Citing Behavior of Korean Scientists on Foreign Journals in KSCD | journal=Journal of the Korean Society for Information Management | date=2011 | volume=28 | issue=2 | pages=117–133 }}', $expanded->parsed_text());
        }
    }

    /** https://publications.europa.eu/en/ */
    public function testExpansion_doi_not_from_crossref_europa_monograph(): void {
        $expanded = $this->make_citation('{{Cite journal}}');
        sleep(3);
        expand_doi_with_dx($expanded, '10.2788/14231');
        if ($expanded->has('author1')) {
            $this->assertSame('{{Cite journal| author1=European Commission. Joint Research Centre. Institute for Environment and Sustainability | last2=Vogt | first2=Jürgen | last3=Foisneau | first3=Stéphanie | title=European river and catchment database, version 2.0 (CCM2) : Analysis tools | date=2007 | publisher=Publications Office }}', $expanded->parsed_text());
        } else {
            $this->markTestSkipped('doi.org API did not respond (rate limit or outage)');
        }
    }

    public function testComplexCrossRef(): void {
        $text = '{{citation | title = Deciding the Winner of an Arbitrary Finite Poset Game is PSPACE-Complete| arxiv =<!-- -->| bibcode = <!-- --> | doi=10.1007/978-3-642-39206-1_42}}';
        $expanded = $this->process_citation($text);
        if ($expanded->get2('chapter') !== null) {
            $this->assertSame('Deciding the Winner of an Arbitrary Finite Poset Game is PSPACE-Complete', $expanded->get2('chapter'));
            $this->assertSame('Lecture Notes in Computer Science', $expanded->get2('series'));
            $this->assertSame('Automata, Languages, and Programming', $expanded->get2('title'));
        } else {
            $this->markTestSkipped('CrossRef API did not respond (rate limit or outage)');
        }
    }

    public function testThesisDOI(): void {
        $doi = '10.17077/etd.g638o927';
        $text = "{{cite journal|doi=$doi}}";
        $template = $this->make_citation($text);
        expand_doi_with_dx($template, $doi);
        $this->assertSame($doi, $template->get2('doi'));
        $this->assertSame("The caregiver's journey", $template->get2('title'));
        $this->assertSame('The University of Iowa', $template->get2('publisher'));
        $this->assertSame('2018', $template->get2('date'));
        $this->assertSame('Schumacher', $template->get2('last1'));
        $this->assertSame('Lisa Anne', $template->get2('first1'));
    }

    public function testCrossRefAddSeriesRemovesSeriesDoi1010631283310TitleTransientSemiMetallicLayerPmid(): void {
        $text = "{{Cite book | doi = 10.1063/1.2833100| title = A Transient Semi-Metallic Layer in Detonating Nitromethane|pmid=<!-- -->|pmc=<!-- -->}}";
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('series'));
    }

    public function testNonSpieProceedingsRetainBookReviewGuard(): void {
        $template = $this->make_citation(
            '{{cite book|doi=10.1063/1.2833100|title=A Transient Semi-Metallic Layer in Detonating Nitromethane}}'
        );
        $crossref = (object) [
            'type' => 'proceedings-article',
            'title' => ['A Transient Semi-Metallic Layer in Detonating Nitromethane'],
            'container-title' => ['AIP Conference Proceedings'],
        ];
        $this->assertTrue(isCrossRefReviewConfusion($template, $crossref, '10.1063/1.2833100'));
    }

    public function testCrossRefAddSeriesChecksDate2008Doi1010631283310TitleAIPConferenceProceedings(): void {
        // Kind of messed up, but "matches" enough to expand
        $text = "{{Cite book | doi = 10.1063/1.2833100| title = AIP Conference Proceedings|pmid=<!-- -->|pmc=<!-- -->}}";
        $template = $this->process_citation($text);
        $this->assertSame("2008", $template->get2('date'));
    }

    public function testCrossRefAddEditors(): void {
        $text = "{{Cite book | doi = 10.1117/12.135408|pmid=<!-- -->|pmc=<!-- -->}}";
        $template = $this->process_citation($text);
        $this->assertSame("Kopera", $template->get2('editor-last1'));
    }

    #[DataProvider('malformedSpieRestContainerTitles')]
    public function testSpieExpansionContinuesWithMalformedRestContainerTitle(mixed $container_title): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Crossref');
        }
        $record = new SimpleXMLElement(
            '<query><article_title>Fixture paper</article_title><volume>6505</volume><year>2007</year></query>'
        );
        $response = json_encode([
            'status' => 'ok',
            'message' => [
                'DOI' => '10.1117/12.703370',
                'type' => 'proceedings-article',
                'title' => ['Fixture paper'],
                'container-title' => $container_title,
            ],
        ], JSON_THROW_ON_ERROR);
        $stubs = [
            'doi_works' => true,
            'doi_active' => true,
            'query_crossref' => $record,
            'bot_curl_exec' => $response,
        ];
        foreach ($stubs as $name => $value) {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_set_return($name, $value);
        }
        try {
            $template = $this->make_citation('{{cite journal|doi=10.1117/12.703370}}');
            expand_by_doi($template, true);

            $this->assertSame('Fixture paper', $template->get('title'));
            $this->assertSame('6505', $template->get('volume'));
            $this->assertSame('', $template->get('series'));
        } finally {
            foreach (array_keys($stubs) as $name) {
                /** @psalm-suppress UnusedFunctionCall */
                uopz_unset_return($name);
            }
        }
    }

    /** @return array<string, array{mixed}> */
    public static function malformedSpieRestContainerTitles(): array {
        return [
            'object' => [new stdClass()],
            'scalar' => ['SPIE Proceedings'],
            'nested array' => [[['SPIE Proceedings']]],
            'missing title' => [null],
        ];
    }

    public function testSpieProceedingsSeriesFromCrossrefContainer(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Crossref');
        }
        $doi = '10.1117/12.703370';
        $title = 'Security, Steganography, and Watermarking of Multimedia Contents IX';
        $chapter = 'Digital imaging sensor identification (Further study)';
        $record = new SimpleXMLElement(
            '<query><article_title>' . $chapter . '</article_title>' .
            '<volume_title>' . $title . '</volume_title><volume>6505</volume><year>2007</year></query>'
        );
        $stubs = [
            'doi_works' => true,
            'doi_active' => true,
            'query_crossref' => $record,
            'query_crossref_newapi' => (object) [
                'DOI' => $doi,
                'type' => 'proceedings-article',
                'title' => [$chapter],
                'container-title' => ['SPIE Proceedings', $title],
            ],
        ];
        foreach ($stubs as $name => $value) {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_set_return($name, $value);
        }
        try {
            $template = $this->make_citation('{{cite journal|doi=' . $doi . '}}');
            expand_by_doi($template, true);
            $template->final_tidy();
            $this->assertSame('cite book', $template->wikiname());
            $this->assertSame($title, $template->get('title'));
            $this->assertSame($chapter, $template->get('chapter'));
            $this->assertSame('Proceedings of SPIE', $template->get('series'));
            $this->assertSame('6505', $template->get('volume'));
            // The local, sourced roster enriches editors even without REST.
            $this->assertSame('Delp III', $template->get2('editor-last1'));
            $this->assertSame('Edward J.', $template->get2('editor-first1'));
            $this->assertSame('Wong', $template->get2('editor-last2'));
            $this->assertSame('Ping Wah', $template->get2('editor-first2'));
        } finally {
            foreach (array_keys($stubs) as $name) {
                /** @psalm-suppress UnusedFunctionCall */
                uopz_unset_return($name);
            }
        }
    }

    /**
     * @param string $citation
     * @param bool $older_record
     * @param array<string, mixed> $rest_overrides
     * @param string $expected_series
     * @param string $expected_publisher
     * @param bool $expect_same_citation
     */
    #[DataProvider('spieProceedingsBookMetadataCases')]
    public function testSpieProceedingsBookMetadata(string $citation, bool $older_record, array $rest_overrides,
        string $expected_series, string $expected_publisher, bool $expect_same_citation): void {
        $warnings = [];
        $this->with_function_stubs([
            ...self::spieProceedingsStubs($older_record, $rest_overrides),
            'report_warning' => static function (string $message) use (&$warnings): void {
                $warnings[] = $message;
            },
        ], function () use ($citation, $expected_series, $expected_publisher, $expect_same_citation, &$warnings): void {
            $template = $this->make_citation($citation);
            if ($template->has('isbn')) {
                $this->assertFalse($template->incomplete());
            }
            $before = $template->parsed_text();
            $volume = $template->get('volume');
            $pages = $template->get('pages');
            $chapter_url = $template->get('chapter-url');
            $archive_url = $template->get('archive-url');
            $editor_last = $template->get('editor-last');
            $templates = [$template];
            query_doi_api([$template->get('doi')], $templates);
            $template->final_tidy();

            $this->assertSame($expected_series, $template->get('series'));
            $this->assertSame($expected_publisher, $template->get('publisher'));
            if ($volume === '17') {
                $this->assertStringContainsString('SPIE proceedings volume 133 conflicts with citation volume 17', implode("\n", $warnings));
            }
            if ($expect_same_citation) {
                $this->assertSame($before, $template->parsed_text());
            } else {
                $this->assertSame('cite book', $template->wikiname());
                $this->assertNotSame('', $template->get('chapter'));
            }
            if ($volume !== '') {
                $this->assertSame($volume, $template->get('volume'));
                $this->assertSame($pages, $template->get('pages'));
            }
            $this->assertSame($chapter_url, $template->get('chapter-url'));
            $this->assertSame($archive_url, $template->get('archive-url'));
            $this->assertSame($editor_last, $template->get('editor-last'));
        });
    }

    /**
     * @param bool $older_record
     * @param array<string, mixed> $rest_overrides
     * @param ?string $openurl_volume
     * @return array<string, mixed>
     */
    private static function spieProceedingsStubs(bool $older_record = false, array $rest_overrides = [], ?string $openurl_volume = null): array {
        if ($older_record) {
            $doi = '10.1117/12.956078';
            $volume_title = 'Optics in Missile Engineering';
            $paper_title = '&lt;title&gt;Scattering From Infrared Missile Domes&lt;/title&gt;';
            $volume = '0133';
            $year = '1978';
            $first_page = '71';
            $last_page = '77';
        } else {
            $doi = '10.1117/12.611774';
            $volume_title = 'UV/Optical/IR Space Telescopes: Innovative Technologies and Concepts II';
            $paper_title = 'Preliminary design of the Wide-Field Infrared Survey Explorer (WISE)';
            $volume = '5899';
            $year = '2005';
            $first_page = '58990R';
            $last_page = '';
        }
        // Both OpenURL records file the volume title in series_title.
        $record = new SimpleXMLElement('<query><doi>' . $doi . '</doi><series_title>' . $volume_title .
            '</series_title><volume_title>' . $volume_title . '</volume_title><article_title>' .
            htmlspecialchars($paper_title, ENT_XML1, 'UTF-8') . '</article_title><volume>' .
            htmlspecialchars($openurl_volume ?? $volume, ENT_XML1, 'UTF-8') .
            '</volume><year>' . $year . '</year><first_page>' . $first_page . '</first_page><last_page>' .
            $last_page . '</last_page></query>');
        $rest = (object) array_replace([
            'DOI' => $doi,
            'type' => 'proceedings-article',
            'title' => [$paper_title],
            'container-title' => ['SPIE Proceedings', $volume_title],
            'publisher' => 'SPIE',
            'volume' => $volume,
        ], $rest_overrides);
        return [
            'doi_works' => true,
            'doi_active' => true,
            'query_crossref' => $record,
            'query_crossref_newapi' => $rest,
        ];
    }

    /** @return array<string, array{string, bool, array<string, mixed>, string, string, bool}> */
    public static function spieProceedingsBookMetadataCases(): array {
        $wise = '{{cite book|title=UV/Optical/IR Space Telescopes: Innovative Technologies and Concepts II' .
            '|chapter=Preliminary design of the Wide-Field Infrared Survey Explorer (WISE)' .
            '|volume=5899|pages=262–273|date=10 August 2005|doi=10.1117/12.611774' .
            '|editor-last=MacEwen|editor-first=Howard A.' .
            '|chapter-url=http://wise.ssl.berkeley.edu/documents/WISESPIE_SanDiego05.pdf' .
            '|archive-url=https://web.archive.org/web/20090521083142/http://wise.ssl.berkeley.edu/documents/WISESPIE_SanDiego05.pdf' .
            '|archive-date=21 May 2009|url-status=live';
        $optics = '{{cite book|title=Optics in Missile Engineering|chapter=Scattering from Infrared Missile Domes' .
            '|year=1978|doi=10.1117/12.956078';
        return [
            'WISE book' => [$wise . '}}', false, [], 'Proceedings of SPIE', 'SPIE', false],
            'WISE DOI only' => ['{{cite journal|doi=10.1117/12.611774}}', false, [], 'Proceedings of SPIE', 'SPIE', false],
            'otherwise complete book' => [$wise . '|isbn=9780819458933|last1=Mainzer|last2=Eisenhardt|display-authors=1}}',
                false, [], 'Proceedings of SPIE', 'SPIE', false],
            '1978 proceedings with unpadded volume' => [$optics . '|volume=133|pages=71–77}}',
                true, [], 'Proceedings of SPIE', 'SPIE', false],
            '1978 book with journal locators' => [$optics . '|volume=17|page=647}}', true, [], '', '', true],
            '1978 journal with proceedings DOI' => [
                '{{cite journal|title=Scattering from infrared missile domes|journal=Opt. Eng.' .
                '|series=Optics in Missile Engineering|year=1978|volume=17|page=647|doi=10.1117/12.956078}}',
                true, [], 'Optics in Missile Engineering', '', true,
            ],
            'explicit metadata' => [$wise . '|series=SPIE Conference Proceedings|publisher=[[SPIE]]}}',
                false, [], 'SPIE Conference Proceedings', '[[SPIE]]', true],
            'comment-protected metadata' => [$wise . '|series=<!-- keep blank -->|publisher=<!-- keep blank -->}}',
                false, [], '<!-- keep blank -->', '<!-- keep blank -->', true],
            'mismatched REST DOI' => [$wise . '}}', false, ['DOI' => '10.1117/12.956078'], '', '', true],
            'missing REST DOI' => [$wise . '}}', false, ['DOI' => null], '', '', true],
            'wrong REST work type' => [$wise . '}}', false, ['type' => 'journal-article'], '', '', true],
            'malformed REST publisher' => [$wise . '}}', false, ['publisher' => ['SPIE']], 'Proceedings of SPIE', '', false],
            'conflicting book title' => [str_replace('title=UV/Optical/IR Space Telescopes: Innovative Technologies and Concepts II',
                'title=Unrelated Volume', $wise) . '}}', false, [], '', '', true],
        ];
    }

    private const string WISE_PAPER = 'Preliminary design of the Wide-field Infrared Survey Explorer (WISE)';
    private const string WISE_VOLUME = 'UV/Optical/IR Space Telescopes: Innovative Technologies and Concepts II';
    private const string WISE_JOURNAL = 'Uv/Optical/Ir Space Telescopes: Innovative Technologies and Concepts II';

    public function testSpieConvertsReportedWiseCitationAndPreservesItsFields(): void {
        $paper_url = 'http://wise.ssl.berkeley.edu/documents/WISESPIE_SanDiego05.pdf';
        $fields = [
            'last1' => 'Mainzer', 'first1' => 'Amanda K.', 'last2' => 'Eisenhardt', 'first2' => 'Peter',
            'last3' => 'Wright', 'first3' => 'Edward L.', 'last4' => 'Liu', 'first4' => 'Feng-Chuan',
            'last5' => 'Irace', 'first5' => 'William', 'last6' => 'Heinrichsen', 'first6' => 'Ingolf',
            'last7' => 'Cutri', 'first7' => 'Roc', 'last8' => 'Duval', 'first8' => 'Valerie',
            'editor-last' => 'MacEwen', 'editor-first' => 'Howard A.',
            'date' => '10 August 2005', 'volume' => '5899', 'pages' => '262–273',
            'arxiv' => 'astro-ph/0508246', 'bibcode' => '2005SPIE.5899..262M',
            'doi' => '10.1117/12.611774', 's2cid' => '118977552',
            'archive-url' => 'https://web.archive.org/web/20090521083142/' . $paper_url,
            'archive-date' => '21 May 2009', 'access-date' => '5 March 2010', 'url-status' => 'live',
        ];
        $citation = '{{cite journal|title=' . self::WISE_PAPER . '|journal=' . self::WISE_JOURNAL .
            '|series=Proceedings of the SPIE – ' . self::WISE_VOLUME . '|url=' . $paper_url;
        foreach ($fields as $field => $value) {
            $citation .= '|' . $field . '=' . $value;
        }
        $this->with_function_stubs([
            ...self::spieProceedingsStubs(),
            'bot_curl_exec' => '',
        ], function () use ($citation, $fields, $paper_url): void {
            $template = $this->process_citation($citation . '}}');
            $this->assertSame('cite book', $template->wikiname());
            $this->assertSame(self::WISE_PAPER, $template->get('chapter'));
            $this->assertSame(self::WISE_VOLUME, $template->get('title'));
            $this->assertSame('Proceedings of SPIE', $template->get('series'));
            $this->assertSame('SPIE', $template->get('publisher'));
            $this->assertSame($paper_url, $template->get('chapter-url'));
            $this->assertSame('', $template->get('url'));
            $this->assertSame('', $template->get('journal'));
            foreach ($fields as $field => $value) {
                $this->assertSame($value, $template->get($field), $field);
            }
            $this->assertSame($template->parsed_text(), $this->process_citation($template->parsed_text())->parsed_text());
        });
    }

    public function testSpieConvertsJournalWithEscapedCrossrefPaperTitle(): void {
        $citation = '{{cite journal|title=Scattering from infrared missile domes|journal=Optics in Missile Engineering' .
            '|series=Optics in Missile Engineering|volume=133|pages=71–77|year=1978|doi=10.1117/12.956078}}';
        $this->with_function_stubs(self::spieProceedingsStubs(true), function () use ($citation): void {
            $template = $this->make_citation($citation);
            expand_by_doi($template);
            $template->final_tidy();

            $this->assertSame('cite book', $template->wikiname());
            $this->assertSame('Scattering from infrared missile domes', $template->get('chapter'));
            $this->assertSame('Optics in Missile Engineering', $template->get('title'));
            $this->assertSame('133', $template->get('volume'));
            $this->assertSame('71–77', $template->get('pages'));
            $this->assertSame('Proceedings of SPIE', $template->get('series'));
            $this->assertSame('SPIE', $template->get('publisher'));
        });
    }

    public function testSpieConversionPreservesLinkedVolumeAndExistingPublisher(): void {
        $this->with_function_stubs(self::spieProceedingsStubs(), function (): void {
            $linked_volume = '[[Proceedings volume 5899|' . self::WISE_JOURNAL . ']]';
            $template = $this->make_citation('{{cite journal|title=' . self::WISE_PAPER . '|journal=' . $linked_volume .
                '|doi=10.1117/12.611774|series=SPIE Conference Proceedings|publisher=[[SPIE]]}}');
            expand_by_doi($template);
            $template->final_tidy();

            $this->assertSame('cite book', $template->wikiname());
            $this->assertSame($linked_volume, $template->get('title'));
            $this->assertSame('SPIE Conference Proceedings', $template->get('series'));
            $this->assertSame('[[SPIE]]', $template->get('publisher'));
        });
    }

    public function testSpieConversionImportsMissingBookFieldsWithoutJournalIssue(): void {
        $record = new SimpleXMLElement('<query><article_title>' . self::WISE_PAPER . '</article_title>' .
            '<volume_title>' . self::WISE_VOLUME . '</volume_title><volume>5899</volume><year>2005</year>' .
            '<journal_title>SPIE Proceedings</journal_title><issue>2</issue><first_page>58990R</first_page>' .
            '<contributors><contributor contributor_role="author"><surname>Mainzer</surname>' .
            '<given_name>Amanda K.</given_name></contributor></contributors></query>');
        $this->with_function_stubs([
            ...self::spieProceedingsStubs(),
            'query_crossref' => $record,
        ], function (): void {
            $template = $this->make_citation('{{cite journal|title=' . self::WISE_PAPER .
                '|journal=' . self::WISE_JOURNAL . '|doi=10.1117/12.611774}}');
            expand_by_doi($template);
            $template->final_tidy();

            $this->assertSame('cite book', $template->wikiname());
            $this->assertSame('5899', $template->get('volume'));
            $this->assertSame('2005', $template->get('date'));
            $this->assertSame('Mainzer', $template->get('last1'));
            $this->assertSame('', $template->get('journal'));
            $this->assertSame('', $template->get('issue'));
        });
    }

    #[DataProvider('spieCrossrefVolumeConflicts')]
    public function testSpieDeclinesExpansionWhenCrossrefVolumeNumbersConflict(string $citation, bool $complete = false): void {
        $warnings = [];
        $this->with_function_stubs([
            ...self::spieProceedingsStubs(false, [], '17'),
            'report_warning' => static function (string $message) use (&$warnings): void {
                $warnings[] = $message;
            },
        ], function () use ($citation, $complete, &$warnings): void {
            $template = $this->make_citation($citation);
            $this->assertSame(!$complete, $template->incomplete());
            $before = $template->parsed_text();
            expand_by_doi($template);
            $this->assertSame($before, $template->parsed_text());
            $this->assertStringContainsString('Crossref REST volume 5899 conflicts with OpenURL volume 17', implode("\n", $warnings));
        });
    }

    /** @return array<string, array{string, 1?: bool}> */
    public static function spieCrossrefVolumeConflicts(): array {
        $journal = '{{cite journal|title=' . self::WISE_PAPER . '|journal=' . self::WISE_JOURNAL . '|doi=10.1117/12.611774';
        $book = '{{cite book|chapter=' . self::WISE_PAPER . '|title=' . self::WISE_VOLUME . '|doi=10.1117/12.611774';
        return [
            'journal without a volume' => [$journal . '}}'],
            'journal with the REST volume' => [$journal . '|volume=5899}}'],
            'journal with the OpenURL volume' => [$journal . '|volume=17}}'],
            'book without a volume' => [$book . '}}'],
            'book eligible for series and publisher only' => [
                $book . '|volume=5899|pages=262–273|date=2005|isbn=9780819458933|last1=Mainzer|last2=Eisenhardt|display-authors=1}}',
                true,
            ],
            'DOI only' => ['{{cite journal|doi=10.1117/12.611774}}'],
        ];
    }

    #[DataProvider('spieCrossrefVolumeFallbacks')]
    public function testSpieConversionImportsVerifiedCrossrefVolume(mixed $rest_volume, string $openurl_volume, string $expected_volume): void {
        $this->with_function_stubs(self::spieProceedingsStubs(false, ['volume' => $rest_volume], $openurl_volume), function () use ($expected_volume): void {
            $template = $this->make_citation('{{cite journal|title=' . self::WISE_PAPER .
                '|journal=' . self::WISE_JOURNAL . '|doi=10.1117/12.611774}}');
            expand_by_doi($template);
            $template->final_tidy();

            $this->assertSame('cite book', $template->wikiname());
            $this->assertSame($expected_volume, $template->get('volume'));
            $this->assertSame('Proceedings of SPIE', $template->get('series'));
            $this->assertSame('SPIE', $template->get('publisher'));
        });
    }

    /** @return array<string, array{mixed, string, string}> */
    public static function spieCrossrefVolumeFallbacks(): array {
        return [
            'numeric agreement with padding and an integer REST value' => [5899, '05899', '5899'],
            'REST volume only' => ['5899', '', '5899'],
            'OpenURL volume only' => [null, '5899', '5899'],
            'malformed REST volume' => [['17'], '5899', '5899'],
            'boolean REST volume' => [true, '5899', '5899'],
            'zero REST volume' => ['0', '5899', '5899'],
            'negative REST volume' => ['-17', '5899', '5899'],
            'zero OpenURL volume' => ['5899', '0', '5899'],
            'non-numeric suffix in OpenURL volume' => ['5899', '17A', '5899'],
            'out-of-range OpenURL volume' => ['5899', str_repeat('9', 25), '5899'],
            'neither source supplies a usable volume' => [null, '17A', ''],
        ];
    }

    public function testSpiePageProcessingLeavesVolumeUnsetWhenCrossrefSourcesConflict(): void {
        $this->with_function_stubs([
            ...self::spieProceedingsStubs(false, [], '17'),
            'bot_curl_exec' => '',
        ], function (): void {
            $template = $this->process_citation('{{cite journal|title=' . self::WISE_PAPER . '|journal=' . self::WISE_JOURNAL .
                '|pages=262–273|doi=10.1117/12.611774}}');

            $this->assertSame('cite journal', $template->wikiname());
            $this->assertSame('', $template->get('volume'));
            $this->assertSame('', $template->get('series'));
            $this->assertSame('', $template->get('publisher'));
            $this->assertSame('262–273', $template->get('pages'));
        });
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, mixed> $rest
     */
    #[DataProvider('spieJournalConversionConflicts')]
    public function testSpieJournalConversionDeclinesConflictingEvidence(array $fields, array $rest = []): void {
        $citation = '{{cite journal';
        foreach (array_replace([
            'title' => self::WISE_PAPER, 'journal' => self::WISE_JOURNAL, 'volume' => '5899',
            'pages' => '262–273', 'date' => '10 August 2005', 'doi' => '10.1117/12.611774',
        ], $fields) as $field => $value) {
            $citation .= '|' . $field . '=' . $value;
        }
        $this->with_function_stubs(self::spieProceedingsStubs(false, $rest), function () use ($citation): void {
            $template = $this->make_citation($citation . '}}');
            $before = $template->parsed_text();
            expand_by_doi($template);

            $this->assertSame($before, $template->parsed_text());
        });
    }

    /** @return array<string, array{array<string, string>, 1?: array<string, mixed>}> */
    public static function spieJournalConversionConflicts(): array {
        return [
            'different paper' => [['title' => 'An unrelated paper', 'series' => self::WISE_VOLUME]],
            'different volume title' => [['journal' => 'An unrelated journal']],
            'different volume number' => [['volume' => '17']],
            'ambiguous volume' => [['volume' => '5899A']],
            'duplicate work alias' => [['work' => 'Another work']],
            'existing journal translation' => [['trans-journal' => 'Translated journal title']],
            'existing chapter alias' => [['contribution' => 'Existing contribution']],
            'protected chapter' => [['chapter' => '<!-- keep blank -->']],
            'competing chapter URL' => [['url' => 'https://example.org/paper.pdf', 'chapter-url' => 'https://example.org/other.pdf']],
            'paper title link' => [['title-link' => 'Wide-field Infrared Survey Explorer']],
            'journal-only issue' => [['issue' => '2']],
            'different composite series' => [['series' => 'Proceedings of the SPIE – Another Volume']],
            'different REST DOI' => [[], ['DOI' => '10.1117/12.956078']],
            'different REST volume title' => [[], ['container-title' => ['SPIE Proceedings', 'Another Volume']]],
            'missing REST container data' => [[], ['container-title' => null]],
            'malformed REST paper title' => [[], ['title' => [new stdClass()]]],
            'different REST paper title' => [[], ['title' => ['An unrelated paper']]],
        ];
    }

    public function testSpieJournalArticleKeepsJournalMetadata(): void {
        $doi = '10.1117/12.7972298';
        $title = 'Scattering from Infrared Missile Domes';
        $record = new SimpleXMLElement('<query><article_title>' . $title . '</article_title>' .
            '<journal_title>Optical Engineering</journal_title><volume>17</volume><year>1978</year>' .
            '<first_page>647</first_page><last_page>651</last_page></query>');
        $this->with_function_stubs([
            'doi_works' => true,
            'doi_active' => true,
            'query_crossref' => $record,
            'query_crossref_newapi' => (object) [
                'DOI' => $doi,
                'type' => 'journal-article',
                'title' => [$title],
                'container-title' => ['Optical Engineering'],
                'publisher' => 'SPIE-Intl Soc Optical Eng',
                'volume' => '17',
            ],
        ], function () use ($doi, $title): void {
            $template = $this->make_citation('{{cite journal|doi=' . $doi . '}}');
            expand_by_doi($template);
            $template->final_tidy();

            $this->assertSame('cite journal', $template->wikiname());
            $this->assertSame($title, $template->get('title'));
            $this->assertSame('Optical Engineering', $template->get('journal'));
            $this->assertSame('17', $template->get('volume'));
            $this->assertSame('647–651', $template->get('pages'));
            $this->assertSame('', $template->get('series'));
            $this->assertSame('', $template->get('publisher'));
        });
    }

    public function testSpieCrossrefVolumeTitleFallbackPreservesPaperCitation(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Crossref');
        }
        $doi = '10.1117/12.703370';
        $paperTitle = 'Digital imaging sensor identification (further study)';
        $volumeTitle = 'Security, Steganography, and Watermarking of Multimedia Contents IX';
        $record = new SimpleXMLElement(
            '<query><article_title>' . $paperTitle . '</article_title>' .
            '<volume_title>' . $volumeTitle . '</volume_title><volume>6505</volume><year>2007</year></query>'
        );
        $stubs = [
            'doi_works' => true,
            'doi_active' => true,
            'query_crossref' => $record,
            'query_crossref_newapi' => (object) [
                'type' => 'proceedings-article',
                'title' => [$paperTitle],
                'container-title' => ['SPIE Proceedings', $volumeTitle],
            ],
        ];
        foreach ($stubs as $name => $value) {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_set_return($name, $value);
        }
        try {
            // A generic existing book-title prevents the normal Crossref
            // volume-title rewrite, so enrichment must use the verified fallback.
            $template = $this->make_citation('{{cite book|doi=' . $doi .
                '|title=' . $paperTitle . '|book-title=SPIE Proceedings|volume=6505|year=2007}}');
            expand_by_doi($template, true);
            $this->assertSame($paperTitle, $template->get2('title'));
            $this->assertSame('Delp III', $template->get2('editor-last1'));
            $this->assertSame('Edward J.', $template->get2('editor-first1'));
            $this->assertSame('Wong', $template->get2('editor-last2'));
            $this->assertSame('Ping Wah', $template->get2('editor-first2'));
        } finally {
            foreach (array_keys($stubs) as $name) {
                /** @psalm-suppress UnusedFunctionCall */
                uopz_unset_return($name);
            }
        }
    }

    public function testBlankTypeFromDXChecksDate2015Doi1014989Doctor1925Pmid(): void {
        $text = "{{cite book| doi=10.14989/doctor.k19250 |pmid=<!-- -->|pmc=<!-- -->}}";
        $prepared = $this->process_citation($text);
        $this->assertSame('2015', $prepared->get2('date'));
    }

    public function testBlankTypeFromDXChecksLast1CollinsDoi1026099Aacp5268Pmid(): void {
        $text = "{{Cite journal|doi=10.26099/aacp-5268|pmid=<!-- -->|pmc=<!-- -->}}";
        $prepared = $this->process_citation($text);
        $this->assertSame('Collins', $prepared->get2('last1'));
    }

    public function testCrossRefAlternativeAPI(): void {
        $text = "{{cite journal| doi=10.1080/00222938700771131 |s2cid=<!-- --> |pmid=<!-- --> |pmc=<!-- --> |arxiv=<!-- --> |jstor=<!-- --> |bibcode=<!-- --> }}";
        $prepared = $this->process_citation($text);
        $this->assertSame("Life cycles of ''Phialella zappai'' n. Sp., ''Phialella fragilis'' and ''Phialella'' sp. (Cnidaria, Leptomedusae, Phialellidae) from central California", $prepared->get2('title'));
    }

    public function testCrossrefFallbackPreservesExistingBookCitation(): void {
        $text = "{{Cite book |date=2012-11-12 |title=The Analects of Confucius |url=http://dx.doi.org/10.4324/9780203715246 |doi=10.4324/9780203715246|isbn=9780203715246 |last1=Estate |first1=The Arthur Waley |pmid=<!-- -->|pmc=<!-- -->}}";
        $prepared = $this->process_citation($text);
        $this->assertSame($text, $prepared->parsed_text());
    }

    public function testCrossRefAlternativeAPIChecksLastGalbacsFirstPeterTitleTheTheoryOfNewClassicalMacPublisherSpringer(): void {
        $text = "{{cite book |last=Galbács |first=Peter |title=The Theory of New Classical Macroeconomics. A Positive Critique |location=Heidelberg/New York/Dordrecht/London |publisher=Springer |year=2015 |isbn= 978-3-319-17578-2|doi=10.1007/978-3-319-17578-2 |series=Contributions to Economics |pmid=<!-- -->|pmc=<!-- -->}}";
        $prepared = $this->process_citation($text);
        $this->assertSame($text, $prepared->parsed_text());
    }

    public function testCrossRefAlternativeAPIChecksUrlTaylorfrancisComBooksEdit1TitleMediaAndSuicideInternationDate20171031PublisherRoutledge(): void {
        $text = "{{Cite book |url=https://www.taylorfrancis.com/books/edit/10.4324/9781351295246/media-suicide-thomas-niederkrotenthaler-steven-stack |title=Media and Suicide: International Perspectives on Research, Theory, and Policy |date=2017-10-31 |publisher=Routledge |isbn=978-1-351-29524-6 |editor-last=Niederkrotenthaler |editor-first=Thomas |location=New York |doi=10.4324/9781351295246 |editor-last2=Stack |editor-first2=Steven|pmid=<!-- -->|pmc=<!-- -->}}";
        $prepared = $this->process_citation($text);
        $this->assertSame($text, $prepared->parsed_text());
    }

    public function testPoundDOI(): void {
        $text = "{{cite book |url=https://link.springer.com/chapter/10.1007%2F978-3-642-75924-6_15#page-1|pmid=<!-- -->|pmc=<!-- -->}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('10.1007/978-3-642-75924-6_15', $expanded->get2('doi'));
    }

    public function testPlusDOI(): void {
        $doi = "10.1002/1097-0142(19840201)53:3+<815::AID-CNCR2820531334>3.0.CO;2-U#page_scan_tab_contents=342342"; // Also check #page_scan_tab_contents stuff too
        $text = "{{cite journal|doi = $doi |pmid=<!-- -->|pmc=<!-- -->}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("10.1002/1097-0142(19840201)53:3+<815::AID-CNCR2820531334>3.0.CO;2-U", $expanded->get2('doi'));
    }

    public function testNewsDoiUrlStripsSessionId(): void {
        $text = "{{cite news|url=http://doi.org/10.1021/cen-v076n048.p024;jsessionid=222|pmid=<!-- -->|pmc=<!-- -->}}"; // Also check jsesssion removal
        $expanded = $this->process_citation($text);
        $this->assertSame('10.1021/cen-v076n048.p024', $expanded->get2('doi'));
    }

    public function testDoiExpansionChecksDoiCiteJournalPmidPmc(): void {
        $text = "{{Cite web | http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract|pmid=<!-- -->|pmc=<!-- -->}}";
        $prepared = $this->prepare_citation($text);
        $this->assertSame('cite journal', $prepared->wikiname());
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $prepared->get2('doi'));
    }

    public function testDoiExpansionChecksDoiCiteWebUrlOnlinelibraryWileyComDoi10(): void {
        $text = "{{Cite web | url = http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract}}";
        $expanded = $this->prepare_citation($text);
        $this->assertSame('cite web', $expanded->wikiname());
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $expanded->get2('doi'));
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testDoiExpansionKeepsUrlUrlLinkSpringerComArticle1010Doi101007BF002337Pmid(): void {
        // Recognize official DOI targets in URL with extra fragments - fall back to S2
        $text = '{{cite journal | url = https://link.springer.com/article/10.1007/BF00233701#page-1 | doi = 10.1007/BF00233701|pmid=<!-- -->|pmc=<!-- -->}}';
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testDoiExtractionPreservesUnverifiedSourceUrl(): void {
        // Replace this test with a real URL (if one exists)
        $text = "{{Cite web | url = http://fake.url/doi/10.1111/j.1475-4983.2012.01203.x/file.pdf}}"; // Fake URL, real DOI
        $expanded = $this->prepare_citation($text);
        $this->assertSame('cite web', $expanded->wikiname());
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $expanded->get2('doi'));
        // Do not drop PDF files, in case they are open access and the DOI points to a paywall
        $this->assertSame('http://fake.url/doi/10.1111/j.1475-4983.2012.01203.x/file.pdf', $expanded->get2('url'));
    }

    public function testOxfordMusicDoiConvertsCiteWebToDocument(): void {
        $text = '{{cite web |doi=10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last=Howlett |first=Felicity |publisher=Oxford University Press |date=2002|pmid=<!-- -->|pmc=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame('{{cite document |doi=10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last=Howlett |first=Felicity |publisher=Oxford University Press |date=2002|pmid=<!-- -->|pmc=<!-- -->}}', $template->parsed_text());
    }

    public function testOxfordMusicDoiConversionPreservesTemplateCase(): void {
        $text = '{{Cite web |doi=10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last=Howlett |first=Felicity |publisher=Oxford University Press |date=2002|pmid=<!-- -->|pmc=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame('{{Cite document |doi=10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last=Howlett |first=Felicity |publisher=Oxford University Press |date=2002|pmid=<!-- -->|pmc=<!-- -->}}', $template->parsed_text());
    }

    public function testDOI1093WW(): void {
        $text = '{{cite web |doi=10.1093/ww/9780199540891.001.0001/ww-9780199540884-e-221850|pmid=<!-- -->|pmc=<!-- -->}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/ww/9780199540884.013.U221850', $template->get2('doi'));
    }

    public function testBookChapterDOIFromJournal(): void {
        // Test that book chapter DOIs are properly expanded in a single pass
        // Previously required two passes: first to change template type, second to add chapter
        $text = '{{cite journal | doi = 10.1016/B978-0-12-801022-8.00014-3 }}';
        $template = $this->process_citation($text);
        // Should be converted to cite book
        $this->assertSame('cite book', $template->wikiname());
        // Should have chapter title added in single pass
        $chapter = $template->get2('chapter');
        $this->assertIsString($chapter);
        // @phan-suppress-next-line PhanTypeMismatchArgumentNullable - assertIsString guarantees non-null
        $this->assertStringContainsString('Adenosine', $chapter);
        // Should have book title
        $title = $template->get2('title');
        $this->assertIsString($title);
        // @phan-suppress-next-line PhanTypeMismatchArgumentNullable - assertIsString guarantees non-null
        $this->assertStringContainsString('Adenosine Receptors', $title);
        // Should have series
        $this->assertSame('International Review of Neurobiology', $template->get2('series'));
    }

    public function testNoHaveBadChapter(): void {
        $text = "{{Citation |last=Chuku |first=Gloria |title=Kenneth Dike: The Father of Modern African Historiography |date=2013 |work=The Igbo Intellectual Tradition: Creative Conflict in African and African Diasporic Thought |pages=137–164 |editor-last=Chuku |editor-first=Gloria |url=https://link.springer.com/chapter/10.1057/9781137311290_6 |access-date=2024-11-18 |place=New York |publisher=Palgrave Macmillan US |language=en |doi=10.1057/9781137311290_6 |isbn=978-1-137-31129-0|url-access=subscription }}";
        $template = $this->process_citation($text);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testConferenceCitationPreservesArticleTitleFromDoi(): void {
        $text = '{{citation
 | last1=Shugart | first1=Alan | last2=Tong | first2=Yang-hu
 | year=1966
 | title=IBM 2321 data cell drive
 | journal=Proceedings of the April 26–28, 1966, Spring Joint Computer Conference
 | publisher=Association for Computing Machinery (ACM)
 | place=New York City, New York
 | pages=335–345
 | doi=10.1145/1464182.1464223
 | isbn=978-1-4503-7892-5
 | url=http://portal.acm.org/citation.cfm?id=1464223
 | doi-access=free
}}';
        $template = $this->process_citation($text);
        $title = $template->get2('title');
        if ($title === null) {
            $this->markTestSkipped('CrossRef API did not respond (rate limit or outage)');
        }
        $this->assertSame('IBM 2321 data cell drive', $title);
        $this->assertNull($template->get2('chapter'));
    }

    public function testPreprintIdsUseCs1ParameterForms(): void {
        foreach ([
            ['biorxiv', '078733', '10.1101/078733'],
            ['biorxiv', '2020.02.29.123456v2', '10.1101/2020.02.29.123456v2'],
            ['medrxiv', '2020.02.29.12345678v2', '10.1101/2020.02.29.12345678v2'],
            ['medrxiv', '10.1101/19000380', '19000380'],
            ['medrxiv', '10.1101/2019.12.26.19014274', '10.1101/2019.12.26.19014274'],
        ] as [$server, $input, $expected]) {
            $template = $this->make_citation('{{cite ' . $server . '|title=Example}}');
            $this->assertTrue($template->add_if_new($server, $input));
            $this->assertSame($expected, $template->get($server));
        }
    }

    public function testGetBioRxivPublishedDoiChecksCalendarBeforeLookup(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub bioRxiv');
        }
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('bot_curl_exec', '{"messages":[{"status":"ok"}]}');
        try {
            $this->assertNull(get_biorxiv_published_doi('10.1101/2021.02.29.123456', 'biorxiv', $status));
            $this->assertNull($status);
            $this->assertNull(get_biorxiv_published_doi('10.64898/2024.02.29.12345678v2', 'medrxiv', $status));
            $this->assertSame('ok', $status);
            $this->assertNull(get_biorxiv_published_doi('10.1101/2019.12.26.19014274', 'medrxiv', $status));
            $this->assertSame('ok', $status);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('bot_curl_exec');
        }
    }

    public function testGetBioRxivPublishedDoi(): void {
        $result = get_biorxiv_published_doi('10.1234/invalid');
        $this->assertNull($result);

        $result = get_biorxiv_published_doi('10.1101/999999999');
        $this->assertNull($result);
    }

    public function testCrossrefOpenUrlIncludesZeroLocatorsWithoutAnAuthor(): void {
        $template = $this->make_citation('{{cite journal|title=Example article|journal=Example journal|volume=0|pages=0–2}}');
        $queries = [];
        $this->assertTrue(uopz_set_return('bot_curl_exec', static function (CurlHandle $handle) use (&$queries): string {
            $queries[] = curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
            return '<crossref_result><query_result><body><query status="unresolved"/></body></query_result></crossref_result>';
        }, true));
        try {
            get_doi_from_crossref($template);
        } finally {
            $this->assertTrue(uopz_unset_return('bot_curl_exec'));
        }
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('&spage=0&epage=2&volume=0&', implode('', $queries));
    }

    public function testCrossrefXmlParserRejectsMalformedResponses(): void {
        $this->assertNull(parse_crossref_xml_response(''));
        $this->assertNull(parse_crossref_xml_response('not xml'));
        $this->assertNull(parse_crossref_xml_response("<query_result>\xFF</query_result>"));
        $this->assertNull(parse_crossref_xml_response('<query_result><body>'));
    }

    public function testCrossrefXmlParserAcceptsExpectedShape(): void {
        $xml = parse_crossref_xml_response(
            '<crossref_result><query_result><body><query status="resolved"><doi>10.1000/test</doi></query></body></query_result></crossref_result>'
        );
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
        $this->assertSame('resolved', (string) $xml->query_result->body->query['status']);
        $this->assertSame('10.1000/test', (string) $xml->query_result->body->query->doi);
    }

    public function testCrossRefDoesNotReturnNatureReviewForBook(): void {
        $template = $this->make_citation('{{citation|editor=Turnbull, H. W.|title=The James Gregory Tercentenary Memorial Volume|publication-place=London|year=1939}}');
        $crossRefMsg = (object) [
            'title' => ['James Gregory Tercentenary Memorial Volume'],
            'container-title' => ['Nature'],
            'volume' => '144',
            'page' => '1062-1063',
            'issued' => (object) ['date-parts' => [[1939]]],
            'DOI' => '10.1038/1441062a0',
            'type' => 'journal-article',
        ];
        $this->assertTrue(isCrossRefReviewConfusion($template, $crossRefMsg), 'CrossRef Nature hit for book title should be flagged as review');
    }

    public function testCrossRefIssueMatchingVolumeIsSkippedForRstlBackfile(): void {
        $this->assertTrue(should_skip_crossref_issue_as_volume_match('10.1098/rstl.1873.0014', '', '163', '163'));
    }

    public function testCrossRefIssueMatchingVolumeIsSkippedForRsplBackfile(): void {
        $this->assertTrue(should_skip_crossref_issue_as_volume_match('10.1098/rspl.1850.0001', '', '6', '6'));
    }

    public function testCrossRefIssueMatchingVolumeIsKeptWhenRecordHasVolume(): void {
        $this->assertFalse(should_skip_crossref_issue_as_volume_match('10.1098/rstl.1867.0001', '157', '157', '157'));
    }

    public function testCrossRefIssueMatchingVolumeIsKeptOutsideFamily(): void {
        $this->assertFalse(should_skip_crossref_issue_as_volume_match('10.1002/andp.201800355', '', '5', '5'));
    }

    public function testCrossRefIssueMatchingVolumeIsKeptWithoutCitationVolume(): void {
        $this->assertFalse(should_skip_crossref_issue_as_volume_match('10.1098/rstl.1873.0014', '', '163', ''));
    }

    public function testCrossRefIssueMatchingVolumeIsKeptWhenDifferent(): void {
        $this->assertFalse(should_skip_crossref_issue_as_volume_match('10.1098/rstl.1873.0014', '', '163', '164'));
    }

    public function testCrossRefIssueMatchingVolumeIgnoresCaseAndWhitespace(): void {
        $this->assertTrue(should_skip_crossref_issue_as_volume_match('10.1098/RSTL.1873.0014', '', ' 163 ', '163'));
    }

    public function testDoiJsonSkipsRstlBackfileIssueMatchingVolume(): void {
        $template = $this->make_citation('{{cite journal|doi=10.1098/rstl.1873.0014|volume=163|title=XIII. Results}}');
        process_doi_json($template, '10.1098/rstl.1873.0014', [
            'type' => 'journal-article',
            'issue' => '163',
            'title' => ['XIII. Results'],
        ]);
        $this->assertNull($template->get2('issue'));
    }

    public function testDoiJsonKeepsIssueWhenRecordHasVolume(): void {
        $template = $this->make_citation('{{cite journal|doi=10.1098/rstl.1867.0001|volume=157|title=Test}}');
        process_doi_json($template, '10.1098/rstl.1867.0001', [
            'type' => 'journal-article',
            'issue' => '157',
            'volume' => '157',
            'title' => ['Test'],
        ]);
        $this->assertSame('157', $template->get2('issue'));
    }

    public function testDoiJsonKeepsIssueOutsideFamily(): void {
        $template = $this->make_citation('{{cite journal|doi=10.1002/andp.201800355|volume=5|title=Test}}');
        process_doi_json($template, '10.1002/andp.201800355', [
            'type' => 'journal-article',
            'issue' => '5',
            'title' => ['Test'],
        ]);
        $this->assertSame('5', $template->get2('issue'));
    }

    public function testDoiJsonSkipsArrayFormRstlBackfileIssue(): void {
        $template = $this->make_citation('{{cite journal|doi=10.1098/rstl.1873.0014|volume=163|title=XIII. Results}}');
        process_doi_json($template, '10.1098/rstl.1873.0014', [
            'type' => 'journal-article',
            'issue' => ['163'],
            'title' => ['XIII. Results'],
        ]);
        $this->assertNull($template->get2('issue'));
    }

    public function testDoiJsonDoesNotAddJournalNameAsAuthor(): void {
        // Some journals credit themselves as the author of editorials
        $template = $this->make_citation('{{cite journal|doi=10.1000/journal-as-author}}');
        process_doi_json($template, '10.1000/journal-as-author', [
            'type' => 'journal-article',
            'title' => ['An editorial'],
            'container-title' => ['The Lancet Child & Adolescent Health'],
            'author' => [['literal' => 'The Lancet Child & Adolescent Health']],
        ]);
        $this->assertNull($template->get2('author1'));
        $this->assertNull($template->get2('author'));
        $this->assertNull($template->get2('last1'));
        $this->assertNull($template->get2('first1'));
        $this->assertNull($template->get2('vauthors'));
    }

    public function testDoiJsonDoesNotAddJournalNameAsAuthorStringContainerTitle(): void {
        $template = $this->make_citation('{{cite journal|doi=10.1000/journal-as-author-string}}');
        process_doi_json($template, '10.1000/journal-as-author-string', [
            'type' => 'journal-article',
            'title' => ['An editorial'],
            'container-title' => 'Lancet Child and Adolescent Health', // string form; no "The"; "and" for "&"
            'author' => [['literal' => 'The Lancet Child & Adolescent Health']],
        ]);
        $this->assertNull($template->get2('author1'));
        $this->assertNull($template->get2('last1'));
    }

    public function testDoiJsonKeepsOtherAuthorsAndNumbersThemConsecutively(): void {
        $template = $this->make_citation('{{cite journal|doi=10.1000/journal-and-person}}');
        process_doi_json($template, '10.1000/journal-and-person', [
            'type' => 'journal-article',
            'title' => ['An editorial'],
            'container-title' => ['The Lancet Child & Adolescent Health'],
            'author' => [
                ['literal' => 'The Lancet Child & Adolescent Health'],
                ['given' => 'Jane', 'family' => 'Smith'],
            ],
        ]);
        $this->assertSame('Smith', $template->get2('last1'));
        $this->assertSame('Jane', $template->get2('first1'));
        $this->assertNull($template->get2('last2'));
    }

    #[DataProvider('doiDxUrlProvider')]
    public function testExpandDoiWithDxEncodesReservedCharactersInUrl(string $doi, string $expected_url): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub DOI content negotiation');
        }
        $template = $this->make_citation('{{cite journal|doi=' . $doi . '}}');
        $urls = [];
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('bot_curl_exec', static function ($handle) use (&$urls): string {
            $urls[] = (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
            return 'not json';
        }, true);
        try {
            expand_doi_with_dx($template, $doi);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('bot_curl_exec');
        }

        $this->assertSame([$expected_url], $urls);
    }

    /** @return array<string, array{string, string}> */
    public static function doiDxUrlProvider(): array {
        return [
            'hash' => ['10.1000/foo#bar', 'https://doi.org/10.1000/foo%23bar'],
            'already percent-encoded hash' => ['10.1000/foo%23bar', 'https://doi.org/10.1000/foo%23bar'],
            'query and ampersand' => ['10.1000/foo?bar&baz', 'https://doi.org/10.1000/foo%3Fbar%26baz'],
        ];
    }

    public function testDoiJsonStillAddsAuthorsWhenNoContainerTitle(): void {
        $template = $this->make_citation('{{cite journal|doi=10.1000/person-only}}');
        process_doi_json($template, '10.1000/person-only', [
            'type' => 'journal-article',
            'title' => ['A paper'],
            'author' => [['given' => 'Jane', 'family' => 'Smith']],
        ]);
        $this->assertSame('Smith', $template->get2('last1'));
        $this->assertSame('Jane', $template->get2('first1'));
    }
}
