<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class EncodingBoundaryTest extends testBaseClass {
    public function testDoiStopsAtUnicodeWhitespaceAndKeepsFollowingText(): void {
        uopz_set_return('doi_works', static fn (string $doi): bool => $doi === '10.1038/nature12373', true);
        try {
            foreach (["\u{00A0}", "\u{202F}", ' '] as $space) {
                $text = '10.1038/nature12373' . $space . '(2013)';
                $this->assertSame('10.1038/nature12373', extract_doi($text)[1]);
                $template = $this->make_citation('{{cite web|title=Example paper|' . $text . '}}');
                $template->prepare();
                $this->assertSame('10.1038/nature12373', $template->get('doi'));
                $this->assertStringContainsString('(2013)', $template->parsed_text());
            }
        } finally {
            uopz_unset_return('doi_works');
        }
    }

    public function testDoiPrefixRequiresAsciiDigitsEvenWithUnicodeMatching(): void {
        $this->assertSame(1, preg_match(REGEXP_DOI, '10.1038/nature12373'));
        $this->assertSame(0, preg_match(REGEXP_DOI, '10.١٠٣٨/nature12373'));
    }

    public function testPercentDecodedTemplateValuesStayValidUtf8(): void {
        uopz_set_return('doi_works', false);
        try {
            $this->assertSame('caf%E9', urldecode_valid_utf8('caf%E9'));
            $this->assertSame('café', urldecode_valid_utf8('caf%C3%A9'));
            $this->assertSame('1234/caf%E9', hdl_decode('1234/caf%E9'));
            $this->assertSame('10.1234/caf%E9', sanitize_doi('10.1234/caf%E9'));

            foreach ([
                'https://academic.oup.com/crawlprevention/governor?content=%2Fcaf%E9',
                'https://www.ancestry.com/cs/offers/join?url=https://example.org/caf%E9',
                'https://login.libproxy.example.org/login?url=https%3A%2F%2Fexample.org%2Fcaf%E9',
            ] as $url) {
                $template = $this->make_citation('{{cite web|title=Example article|url=' . $url . '}}');
                clean_existing_urls($template, 'url');
                $this->assertTrue(mb_check_encoding($template->parsed_text(), 'UTF-8'));
                $this->assertStringContainsString('caf%E9', $template->get('url'));
            }
        } finally {
            uopz_unset_return('doi_works');
        }
    }

    public function testTitleMatchingDoesNotEraseDifferentAccentedLetters(): void {
        $this->assertFalse(titles_are_similar('Section Å', 'Section Ø'));
        $this->assertFalse(titles_are_similar('Ø', 'Å'));
        $this->assertTrue(titles_are_similar('Café culture', 'Cafe culture'));

        $url = 'https://www.jstor.org/stable/1234567';
        $template = $this->make_citation('{{cite journal|title=Section Å|url=' . $url . '}}');
        $response = json_encode([[
            'itemType' => 'journalArticle',
            'url' => $url,
            'title' => 'Section Ø',
            'publicationTitle' => 'Journal of Other Research',
            'volume' => '42',
        ]], JSON_THROW_ON_ERROR);
        Zotero::process_zotero_response($response, $template, $url, 0, true, true, true);
        $this->assertSame('', $template->get('journal'));
        $this->assertSame('', $template->get('volume'));
    }

    public function testCitoidChecksScriptTitleBeforeEnriching(): void {
        $url = 'https://www.jstor.org/stable/1234567';
        foreach (['Different article', '東京大学の研究'] as $result_title) {
            $template = $this->make_citation('{{cite journal|script-title=ja:東京大学の研究|url=' . $url . '}}');
            $response = json_encode([[
                'itemType' => 'journalArticle',
                'url' => $url,
                'title' => $result_title,
                'publicationTitle' => 'Journal of Other Research',
                'volume' => '42',
            ]], JSON_THROW_ON_ERROR);
            Zotero::process_zotero_response($response, $template, $url, 0, true, true, true);
            $this->assertSame($result_title === '東京大学の研究' ? '42' : '', $template->get('volume'));
        }
    }

    public function testDifferentInternationalTitleLabelsBlockEnrichment(): void {
        foreach ([
            ['Annual report ٢٠٢٤', 'Annual report ٢٠٢٥'],
            ['東京研究報告２０２４年', '東京研究報告２０２５年'],
            ['दृष्टि अंक १', 'दृष्टि अंक २'],
            ['Hepatitis Α study', 'Hepatitis Β study'],
            ['Гепатит А у детей', 'Гепатит В у детей'],
            ['Part Ⅲ Methods', 'Part Ⅳ Methods'],
        ] as [$original, $candidate]) {
            $this->assertFalse(titles_are_similar($original, $candidate), $original);
        }
        $this->assertTrue(titles_are_similar('Part Ⅲ Methods', 'Part III Methods'));
        $this->assertFalse(titles_are_similar('Annual report ٢٠٢٤', 'Annual report 2024'));

        $url = 'https://www.jstor.org/stable/1234567';
        $template = $this->make_citation('{{cite journal|title=Annual report ٢٠٢٤|url=' . $url . '}}');
        Zotero::process_zotero_response(json_encode([[
            'itemType' => 'journalArticle',
            'title' => 'Annual report ٢٠٢٥',
            'publicationTitle' => 'Unrelated journal',
            'volume' => '42',
        ]], JSON_THROW_ON_ERROR), $template, $url, 0, true, true, true);
        $this->assertSame('', $template->get('volume'));
    }

    public function testInternationalPageRangeDoesNotDiscardSpecificPage(): void {
        foreach (['١٠–٢٠', '１２–２０', '№9', 'Ⅻ'] as $candidate) {
            $template = $this->make_citation('{{cite journal|title=Example article|journal=Example Journal|page=12}}');
            $this->assertFalse($template->add_if_new('pages', $candidate, 'crossref'), $candidate);
            $this->assertSame('12', $template->get('page'));
        }
        $template = $this->make_citation('{{cite journal|title=Example article|journal=Example Journal|page=12}}');
        $this->assertFalse($template->add_if_new('pages', '10–20', 'crossref'));
    }

    public function testDistinctInternationalUrlPathsSurviveBookConversion(): void {
        foreach ([
            ['https://example.org/Straße', 'https://example.org/STRASSE'],
            ['https://example.org/école', 'https://example.org/École'],
        ] as [$url, $chapter_url]) {
            $template = $this->make_citation('{{cite web|title=Example book|chapter=Chapter|publisher=Example|url=' .
                $url . '|chapter-url=' . $chapter_url . '}}');
            $template->change_name_to('cite book');
            $this->assertSame($url, $template->get('url'));
            $this->assertSame($chapter_url, $template->get('chapter-url'));
        }
        $this->assertTrue(citation_urls_equivalent('https://EXAMPLE.ORG/same', 'https://example.org/same'));
        $this->assertFalse(citation_urls_equivalent('https://example.org/Part1', 'https://example.org/part1'));
    }

    public function testCrossrefOpenUrlReceivesDecodedInternationalEntities(): void {
        $queries = [];
        uopz_set_return('bot_curl_exec', static function (CurlHandle $handle) use (&$queries): string {
            parse_str((string) parse_url((string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL), PHP_URL_QUERY), $query);
            if (isset($query['atitle'])) {
                $queries[] = [$query['atitle'], $query['title'] ?? ''];
            }
            return '';
        }, true);
        try {
            foreach (['Caf&eacute; culture', 'Caf&#233; culture'] as $title) {
                $template = $this->make_citation('{{cite journal|last=Smith|title=' . $title .
                    '|journal=Revue de g&eacute;ographie|year=2020|pages=12–15}}');
                $template->prepare();
                get_doi_from_crossref($template);
            }
        } finally {
            uopz_unset_return('bot_curl_exec');
        }
        $this->assertSame([
            ['Café culture', 'Revue de géographie'],
            ['Café culture', 'Revue de géographie'],
        ], $queries);
    }

    public function testArchiveDecodesUtf16AndIso2022BeforeSearchingTitle(): void {
        $archive_url = 'https://web.archive.org/web/20120101000000/https://example.org/a';
        foreach (['UTF-16LE', 'UTF-16BE', 'ISO-2022-JP', 'SJIS-win'] as $encoding) {
            $title = '日本語の研究';
            $body = '<!doctype html><html><head><title>' .
                mb_convert_encoding($title, $encoding, 'UTF-8') . '</title></head><body>Example</body></html>';
            if ($encoding === 'UTF-16LE' || $encoding === 'UTF-16BE') {
                $utf8_body = '<!doctype html><html><head><title>' . $title .
                    '</title></head><body>Example</body></html>';
                $body = ($encoding === 'UTF-16LE' ? "\xFF\xFE" : "\xFE\xFF") .
                    mb_convert_encoding($utf8_body, $encoding, 'UTF-8');
            }
            $response = "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=" . $encoding . "\r\n\r\n" . $body;
            uopz_set_return('fetch_archive_page', $response);
            try {
                $template = $this->make_citation('{{cite web|archive-url=' . $archive_url . '|title=Archived copy}}');
                $templates = [$template];
                expand_templates_from_archives($templates);
                $this->assertSame($title, $template->get('title'), $encoding);
            } finally {
                uopz_unset_return('fetch_archive_page');
            }
        }
    }

    public function testInternationalAuthorsAreNotMistakenForInitials(): void {
        $this->assertSame('王小明', format_author('王小明'));
        $this->assertSame('김민수', format_author('김민수'));
        $this->assertFalse(is_initials('明'));
        $this->assertSame('Élodie', format_surname("E\u{0301}lodie"));

        $template = $this->make_citation('{{cite journal|title=Example article}}');
        $template->validate_and_add('author1', "E\u{0301} Smith", '', '', false);
        $this->assertSame('É.', $template->get('first1'));

        $previous_style = Template::$name_list_style;
        try {
            $template = $this->make_citation('{{cite journal|title=Example article}}');
            $template->set('last1', 'García');
            $template->set('first1', "E\u{0301}lodie");
            Template::$name_list_style = VancStyle::NAME_LIST_STYLE_VANC;
            $this->assertStringContainsString('García É', $template->parsed_text());
        } finally {
            Template::$name_list_style = $previous_style;
        }
    }

    public function testPubmedPreservesHyphenatedInitialAuthor(): void {
        $this->assertSame(['Smith A-B', 'Smith', 'A-B'], pubmed_split_author_name('Smith A-B'));
        $document = new SimpleXMLElement('<eSummaryResult><DocSum><Id>123</Id>' .
            '<Item Name="AuthorList" Type="List"><Item Name="Author" Type="String">Smith A-B</Item>' .
            '<Item Name="Author" Type="String">García M</Item></Item></DocSum></eSummaryResult>');
        uopz_set_return('get_entrez_xml', $document);
        uopz_set_return('get_doi_from_crossref', null);
        try {
            $template = $this->make_citation('{{cite journal|title=Example article|pmid=123}}');
            $templates = [$template];
            query_pmid_api(['123'], $templates);
            $this->assertSame('Smith', $template->get('last1'));
            $this->assertSame('García', $template->get('last2'));
        } finally {
            uopz_unset_return('get_doi_from_crossref');
            uopz_unset_return('get_entrez_xml');
        }
    }

    public function testCitoidDeduplicatesCanonicallyEquivalentCreators(): void {
        $url = 'https://example.org/article';
        $template = $this->make_citation('{{cite journal|title=Example article|url=' . $url . '}}');
        $creators = [
            ['firstName' => 'José', 'lastName' => 'García', 'creatorType' => 'author'],
            ['firstName' => "Jose\u{0301}", 'lastName' => "Garci\u{0301}a", 'creatorType' => 'author'],
            ['firstName' => 'Sofía', 'lastName' => 'Lopez', 'creatorType' => 'author'],
        ];
        Zotero::process_zotero_response(json_encode([[
            'itemType' => 'journalArticle',
            'title' => 'Example article',
            'publicationTitle' => 'Example Journal',
            'creators' => $creators,
        ]], JSON_THROW_ON_ERROR), $template, $url, 0, false, true, true);
        $this->assertSame('García', $template->get('last1'));
        $this->assertSame('Lopez', $template->get('last2'));
        $this->assertSame('', $template->get('last3'));
    }

    public function testMbStrrevHonorsExplicitEncoding(): void {
        $this->assertSame('Aé', mb_strrev('éA', 'UTF-8'));
        $this->assertSame("A\xA9\xC3", mb_strrev('éA', '8bit'));
    }

    public function testMbSubstrReplaceAcceptsByteOffsets(): void {
        $subject = 'é ITS marker';
        $matched = preg_match('~ITS~', $subject, $match, PREG_OFFSET_CAPTURE);
        $this->assertSame(1, $matched);
        $this->assertSame(
            'é its marker',
            mb_substr_replace($subject, 'its', $match[0][1], 3, '8bit')
        );
    }

    public function testTitleCapitalizationPreservesCaseAfterMultibytePrefix(): void {
        $this->assertSame(
            'Café ITS is Faster',
            title_capitalization('CAFÉ ITS IS FASTER', true)
        );
        $this->assertSame(
            'Café DOS is Faster',
            title_capitalization('CAFÉ DOS IS FASTER', true)
        );
    }

    public function testArchiveTitleCandidateDecodesBeforeUnicodeOperations(): void {
        $this->assertSame(
            '“Café”',
            archive_decode_title_candidate(
                " \x93Caf\xE9\x94 ",
                ['windows-1252'],
                'https://web.archive.org/'
            )
        );

        $shift_jis = mb_convert_encoding(' 日本語 ', 'SJIS-win', 'UTF-8');
        $this->assertSame(
            '日本語',
            archive_decode_title_candidate(
                $shift_jis,
                ['Shift_JIS'],
                'https://web.archive.org/'
            )
        );
    }

    public function testArchiveConnectParsingToleratesNonUtf8ReasonBytes(): void {
        $response =
            "HTTP/1.1 200 \xFFConnection established\r\n\r\n" .
            "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\n\r\n" .
            '<html></html>';

        $parts = archive_http_response_parts($response);
        $this->assertStringStartsWith('HTTP/1.1 200 OK', $parts['headers']);
        $this->assertSame('<html></html>', $parts['body']);
    }

    public function testRawBoundaryCallSitesUseEightBitMode(): void {
        $root = dirname(__DIR__, 3) . '/src/';
        $cases = [
            'includes/api/APIS2.php' => [
                "mb_stripos(\$response, 'Too Many Requests', 0, '8bit')",
                "mb_stripos(\$response, 'Paper not found', 0, '8bit')",
            ],
            'includes/api/APIBibCode.php' => [
                "mb_stripos(\$body, 'down for maintenance', 0, '8bit')",
            ],
            'includes/api/APIzotero.php' => [
                "private static function trim_raw_response",
                "private static function raw_response_excerpt",
                "mb_substr(\$response, 0, \$limit, '8bit')",
            ],
            'process_page.php' => [
                "mb_check_encoding(\$pages, 'UTF-8')",
                "mb_strpos(\$pages, '|', 0, '8bit')",
            ],
            'generate_template.php' => [
                "mb_check_encoding(\$value, 'UTF-8')",
                "mb_strlen(\$value) < 3",
                "mb_strlen(\$value) > 100",
            ],
            'includes/RequestRateLimit.php' => [
                "mb_rtrim(\$base_directory, \"/\\\\\", '8bit')",
            ],
        ];

        foreach ($cases as $relative => $needles) {
            $source = file_get_contents($root . $relative);
            $this->assertIsString($source);
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $source, $relative);
            }
        }
    }

    public function testConvertToUtf8DoesNotReinterpretValidUtf8(): void {
        $value = 'Café © Registered ® 日本語';
        $this->assertSame($value, convert_to_utf8($value));
    }

    public function testArchiveCharsetParsingUsesAsciiWhitespaceOnly(): void {
        $this->assertSame(
            'windows-1252',
            archive_charset_parameter("text/html; charset=  windows-1252 \t")
        );
        $this->assertSame(
            "windows-1252\u{00A0}",
            archive_charset_parameter("text/html; charset=windows-1252\xC2\xA0")
        );
    }

    public function testArchiveResponsePartsHandlesMalformedBytesBeforeSeparators(): void {
        $response =
            "HTTP/1.1 100 Continue\r\nX-Debug: \xFF\r\n\r\n" .
            "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n\r\nbody\xFF";

        $parts = archive_http_response_parts($response);
        $this->assertStringStartsWith('HTTP/1.1 200 OK', $parts['headers']);
        $this->assertSame("body\xFF", $parts['body']);
    }

    public function testByteModeReplacementWorksAtMultipleMultibyteOffsets(): void {
        foreach (['é', '日本', '😀'] as $prefix) {
            $subject = $prefix . ' ITS suffix';
            preg_match('~ITS~', $subject, $match, PREG_OFFSET_CAPTURE);
            $this->assertSame(
                $prefix . ' its suffix',
                mb_substr_replace($subject, 'its', $match[0][1], 3, '8bit')
            );
        }
    }

    public function testWindows1252HeuristicUsesRawByteLiterals(): void {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/includes/api/APIarchives.php');
        $this->assertIsString($source);
        foreach (["\\xAE", "\\xA9", "\\x81", "\\x94", "\\x84"] as $literal) {
            $this->assertStringContainsString('"' . $literal . '"', $source);
        }
    }

    public function testPublicConfigEnforcementRemainsNoOpOnCli(): void {
        if (PHP_SAPI !== 'cli') {
            $this->markTestSkipped('CLI-specific behavior');
        }

        $before = mb_internal_encoding();
        try {
            mb_internal_encoding('ISO-8859-1');
            enforce_public_request_configuration(null);
            $this->assertSame('ISO-8859-1', mb_internal_encoding());
        } finally {
            mb_internal_encoding($before);
        }
    }
}
