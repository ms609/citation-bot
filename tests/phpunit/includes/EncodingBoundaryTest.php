<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class EncodingBoundaryTest extends testBaseClass {
    public function testDoiStopsAtUnicodeWhitespaceAndKeepsFollowingText(): void {
        $this->assertTrue(uopz_set_return('doi_works', static fn (string $doi): bool => $doi === '10.1038/nature12373', true));
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
            $this->assertTrue(uopz_unset_return('doi_works'));
        }
    }

    public function testDoiPrefixRequiresAsciiDigitsEvenWithUnicodeMatching(): void {
        $this->assertSame(1, preg_match(REGEXP_DOI, '10.1038/nature12373'));
        $this->assertSame(0, preg_match(REGEXP_DOI, '10.١٠٣٨/nature12373'));
    }

    public function testPercentDecodedTemplateValuesStayValidUtf8(): void {
        $this->assertTrue(uopz_set_return('doi_works', false));
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
            $this->assertTrue(uopz_unset_return('doi_works'));
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
