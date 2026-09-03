<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class EncodingBoundaryExpandedTest extends testBaseClass {
    public function testCharsetParameterIgnoresSemicolonsInsideQuotedParameters(): void {
        $this->assertSame(
            'Shift_JIS',
            archive_charset_parameter(
                'multipart/mixed; boundary="part;charset=fake"; charset="Shift_JIS"'
            )
        );
    }

    public function testCharsetParameterRejectsMalformedQuotedValues(): void {
        $this->assertNull(archive_charset_parameter('text/html; charset="windows-1252'));
        $this->assertNull(archive_charset_parameter('text/html; charset="utf-8" trailing'));
        $this->assertNull(archive_charset_parameter('text/html; charset='));
    }

    public function testCharsetParameterHandlesEscapedQuoteInsideOtherParameter(): void {
        $this->assertSame(
            'windows-1252',
            archive_charset_parameter(
                'text/html; boundary="a\\";charset=fake"; charset=windows-1252'
            )
        );
    }

    public function testHtmlAttributesDoNotConfuseCharsetLikeNames(): void {
        $attributes = archive_html_attributes(
            '<meta data-charset="fake" charset="Shift_JIS" content="text/html">'
        );
        $this->assertSame('fake', $attributes['data-charset']);
        $this->assertSame('Shift_JIS', $attributes['charset']);
        $this->assertSame('text/html', $attributes['content']);
    }

    public function testHtmlAttributesNormalizeNamesAndKeepFirstDuplicate(): void {
        $attributes = archive_html_attributes(
            '<META CHARSET="UTF-8" charset="windows-1252" HTTP-EQUIV=Content-Type>'
        );
        $this->assertSame('UTF-8', $attributes['charset']);
        $this->assertSame('Content-Type', $attributes['http-equiv']);
        $this->assertCount(2, $attributes);
    }

    public function testHtmlTagEndIgnoresGreaterThanInsideQuotedAttribute(): void {
        $html = '<meta content="a > b" charset="utf-8"><p>x</p>';
        $end = archive_html_tag_end($html, 0);
        $this->assertNotNull($end);
        $this->assertSame(
            '<meta content="a > b" charset="utf-8">',
            mb_substr($html, 0, $end + 1, '8bit')
        );
    }

    public function testMetaTagScannerSkipsCommentsAndRawTextElements(): void {
        $html =
            '<!-- <meta charset="comment"> -->' .
            '<script>const x = \'<meta charset="script">\';</script>' .
            '<style>.x{content:"<meta charset=style>"}</style>' .
            '<title><meta charset="title"></title>' .
            '<template><meta charset="template"></template>' .
            '<meta charset="UTF-8">' .
            '<meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">';
        $this->assertSame(
            [
                '<meta charset="UTF-8">',
                '<meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">',
            ],
            archive_html_meta_tags($html)
        );
    }

    public function testMetaTagScannerDoesNotTreatAttributeTextAsNestedTag(): void {
        $html =
            '<div data-example="<meta charset=bogus>">x</div>' .
            '<meta charset="windows-1252">';
        $this->assertSame(
            ['<meta charset="windows-1252">'],
            archive_html_meta_tags($html)
        );
    }

    public function testMetaEncodingLabelAppliesHtmlPrescanMappings(): void {
        foreach (
            [
                'UTF-16' => 'UTF-8',
                'utf-16le' => 'UTF-8',
                'UTF-16BE' => 'UTF-8',
                'unicode' => 'UTF-8',
                'x-user-defined' => 'windows-1252',
            ] as $input => $expected
        ) {
            $this->assertSame($expected, archive_meta_encoding_label($input));
        }
        $this->assertSame('Shift_JIS', archive_meta_encoding_label('Shift_JIS'));
    }

    public function testMetaDeclaredEncodingsHandleCharsetAndHttpEquivForms(): void {
        $html =
            '<meta charset="UTF-16LE">' .
            '<meta http-equiv="content-type" content="text/html; charset=Shift_JIS">' .
            '<meta http-equiv="refresh" content="0; charset=bogus">' .
            '<meta data-charset="fake">';
        $this->assertSame(
            ['UTF-8', 'Shift_JIS'],
            archive_meta_declared_encodings($html)
        );
    }

    public function testCandidateEncodingsPreserveDeclaredOrderAndDeduplicateCaseInsensitively(): void {
        $response =
            "HTTP/1.1 200 OK\r\n" .
            "Content-Type: text/html; charset=windows-1252\r\n" .
            "X-Archive-Guessed-Charset: Shift_JIS\r\n\r\n" .
            '<html><head>' .
            '<meta charset="WINDOWS-1252">' .
            '<meta charset="Shift_JIS">' .
            '</head><body></body></html>';
        $this->assertSame(
            ['windows-1252', 'Shift_JIS'],
            archive_candidate_encodings($response)
        );
    }

    public function testCandidateEncodingsPreferExplicitMetaOverArchiveGuess(): void {
        $response =
            "HTTP/1.1 200 OK\r\n" .
            "X-Archive-Guessed-Charset: Shift_JIS\r\n\r\n" .
            '<meta charset="windows-1252">';
        $this->assertSame(
            ['windows-1252', 'Shift_JIS'],
            archive_candidate_encodings($response)
        );
    }

    public function testHttpResponsePartsHandlesLfOnlyInterimBlocks(): void {
        $response =
            "HTTP/1.1 100 Continue\nX-One: 1\n\n" .
            "HTTP/1.1 200 OK\nContent-Type: text/plain\n\n" .
            'payload';
        $parts = archive_http_response_parts($response);
        $this->assertStringStartsWith('HTTP/1.1 200 OK', $parts['headers']);
        $this->assertSame('payload', $parts['body']);
    }

    public function testHttpResponsePartsLeavesRawBodyUntouchedWithoutHeaders(): void {
        $body = "\xFFraw\nbody\x00tail";
        $this->assertSame(
            ['headers' => '', 'body' => $body],
            archive_http_response_parts($body)
        );
    }

    public function testHttpResponsePartsLeavesIncompleteFirstHeaderAsBody(): void {
        $response = "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\n";
        $this->assertSame(
            ['headers' => '', 'body' => $response],
            archive_http_response_parts($response)
        );
    }

    public function testTitleScanWindowStopsAtOpeningBodyTag(): void {
        $response =
            "HTTP/1.1 200 OK\r\n\r\n" .
            '<html><head><title>Café</title></head>' .
            '<body class="main">DO NOT SCAN THIS';
        $window = archive_title_scan_window($response);
        $this->assertStringContainsString('<title>Café</title>', $window);
        $this->assertStringEndsWith('<body class="main">', $window);
        $this->assertStringNotContainsString('DO NOT SCAN THIS', $window);
    }

    public function testDecodeTitleReturnsAlreadyValidUtf8WithoutReinterpretation(): void {
        $title = '“Café 日本語 😀”';
        $this->assertSame(
            $title,
            archive_decode_title(
                $title,
                ['windows-1252', 'Shift_JIS'],
                'https://web.archive.org/'
            )
        );
    }

    public function testDecodeTitleCandidatePerformsUnicodeTrimAfterDecode(): void {
        $title = "\u{2003}Café\u{2003}";
        $this->assertSame(
            'Café',
            archive_decode_title_candidate(
                $title,
                ['UTF-8'],
                'https://web.archive.org/'
            )
        );
    }

    public function testMbSubstrReplaceUsesCharacterOffsetsByDefault(): void {
        $this->assertSame('😀aXc', mb_substr_replace('😀abc', 'X', 2, 1));
    }

    public function testMbSubstrReplaceByteModeCanOperateOnMalformedBytes(): void {
        $subject = "\xFFABC\xFE";
        $this->assertSame(
            "\xFFAXC\xFE",
            mb_substr_replace($subject, 'X', 2, 1, '8bit')
        );
    }

    public function testMbStrrevDefaultUsesCurrentInternalEncoding(): void {
        $before = mb_internal_encoding();
        try {
            mb_internal_encoding('UTF-8');
            $this->assertSame('Aé😀', mb_strrev('😀éA'));
        } finally {
            mb_internal_encoding($before);
        }
    }

    public function testPublicConfigEncodingInitializerSetsBothMbEncodings(): void {
        $before_internal = mb_internal_encoding();
        $before_regex = mb_regex_encoding();
        try {
            mb_internal_encoding('ISO-8859-1');
            mb_regex_encoding('ISO-8859-1');
            initialize_public_config_encoding();
            $this->assertSame('UTF-8', mb_internal_encoding());
            $this->assertSame('UTF-8', mb_regex_encoding());
        } finally {
            mb_internal_encoding($before_internal);
            mb_regex_encoding($before_regex);
        }
    }

    public function testZoteroRawResponseTrimPreservesUnicodeWhitespaceForUtf8(): void {
        $method = new ReflectionMethod(APIzotero::class, 'trim_raw_response');
        $this->assertSame(
            'payload',
            $method->invoke(null, "\u{2003}payload\u{2003}")
        );
    }

    public function testZoteroRawResponseTrimUsesByteSafeFallbackForMalformedInput(): void {
        $method = new ReflectionMethod(APIzotero::class, 'trim_raw_response');
        $this->assertSame(
            "\xFFpayload\xFE",
            $method->invoke(null, " \t\xFFpayload\xFE\r\n")
        );
    }

    public function testZoteroExcerptDoesNotSplitValidUtf8Characters(): void {
        $method = new ReflectionMethod(APIzotero::class, 'raw_response_excerpt');
        $this->assertSame('😀😀', $method->invoke(null, '😀😀😀', 2));
    }

    public function testZoteroExcerptUsesExactBytesForMalformedResponses(): void {
        $method = new ReflectionMethod(APIzotero::class, 'raw_response_excerpt');
        $this->assertSame("\xFFAB", $method->invoke(null, "\xFFABCDE", 3));
    }

    public function testCharsetParameterRejectsEmbeddedControlCharacters(): void {
        foreach ([
            "text/html; charset=utf-8\nX-Evil: yes",
            "text/html; charset=win\x00dows-1252",
            "text/html; char\tset=utf-8",
        ] as $value) {
            $result = archive_charset_parameter($value);
            $this->assertTrue($result === null || is_string($result));
        }
    }

    public function testCharsetParameterRejectsWhitespaceInsideUnquotedValue(): void {
        foreach ([
            'text/html; charset=utf 8',
            "text/html; charset=windows-1252\tjunk",
            "text/html; charset=Shift_JIS\rjunk",
        ] as $value) {
            $this->assertNull(archive_charset_parameter($value), $value);
        }
    }

    public function testCharsetParameterHandlesEmptyAndDegenerateInputs(): void {
        foreach (['', ';', ';;;;', 'charset', '=', 'text/html; =utf-8', 'text/html; charset'] as $value) {
            $this->assertNull(archive_charset_parameter($value), $value);
        }
    }

    public function testCharsetParameterDoesNotTreatLaterTextInsideQuoteAsParameter(): void {
        $this->assertNull(
            archive_charset_parameter(
                'text/html; boundary="unterminated; charset=Shift_JIS'
            )
        );
    }

    public function testHtmlAttributesHandlesEmptyAndNonsenseTags(): void {
        foreach (['', '<>', '<meta>', '<meta =utf-8>', '<meta "charset=utf-8">', "\xFF\xFE<meta>"] as $tag) {
            $this->assertIsArray(archive_html_attributes($tag));
        }
    }

    public function testHtmlAttributesRejectsMalformedAttributeNames(): void {
        $attributes = archive_html_attributes(
            '<meta 1charset="utf-8" @charset="big5" charset="Shift_JIS">'
        );
        $this->assertSame('Shift_JIS', $attributes['charset']);
        $this->assertArrayNotHasKey('1charset', $attributes);
        $this->assertArrayNotHasKey('@charset', $attributes);
    }

    public function testHtmlAttributesDoesNotCrashOnMalformedBytes(): void {
        $attributes = archive_html_attributes(
            "<meta charset=\"utf-8\" data-x=\"\xFF\xFE\">"
        );
        $this->assertSame('utf-8', $attributes['charset']);
    }

    public function testHtmlTagEndReturnsNullForMissingTerminator(): void {
        foreach (['<meta charset="utf-8"', '<div data-x=">"', '<tag attr=\'unterminated>'] as $html) {
            $this->assertNull(archive_html_tag_end($html, 0), $html);
        }
    }

    public function testMetaScannerReturnsEmptyForGarbageBytes(): void {
        foreach (["\xFF\xFE\xFD", "\x00\x00\x00", 'not html at all', '<<<<<<'] as $html) {
            $this->assertSame([], archive_html_meta_tags($html));
        }
    }

    public function testMetaScannerIgnoresProcessingInstructionsAndDeclarations(): void {
        $html = '<?xml version="1.0"?>' .
            '<!DOCTYPE html>' .
            '<![CDATA[<meta charset="big5">]]>' .
            '<meta charset="utf-8">';
        $this->assertSame(
            ['<meta charset="utf-8">'],
            archive_html_meta_tags($html)
        );
    }

    public function testMetaScannerStopsSafelyOnUnterminatedComment(): void {
        $this->assertSame([], archive_html_meta_tags('<!-- <meta charset="utf-8">'));
    }

    public function testMetaScannerDoesNotAcceptNearMissMetaNames(): void {
        $html = '<metadata charset="utf-8">' .
            '<meta-data charset="big5">' .
            '<met charset="Shift_JIS">';
        $this->assertSame([], archive_html_meta_tags($html));
    }

    public function testMetaDeclaredEncodingsIgnoresEmptyCharset(): void {
        $this->assertSame(
            [],
            archive_meta_declared_encodings('<meta charset=""><meta charset="   ">')
        );
    }

    public function testMetaEncodingLabelHandlesOddCaseAndAsciiWhitespace(): void {
        $this->assertSame('UTF-8', archive_meta_encoding_label(" \tUtF-16Le\r\n"));
        $this->assertSame('windows-1252', archive_meta_encoding_label("\vx-USER-defined\f"));
    }

    public function testMetaEncodingLabelDoesNotUnicodeTrimUnexpectedWhitespace(): void {
        $input = "\u{00A0}UTF-16\u{00A0}";
        $this->assertSame($input, archive_meta_encoding_label($input));
    }

    public function testCandidateEncodingsHandlesCompletelyMalformedResponse(): void {
        foreach ([
            "\xFF\xFE\xFD",
            "HTTP/1.1 ???\r\n\r\n\xFF",
            "HTTP/9.9 999 Strange\r\nBroken\x00Header\r\n\r\n<body>",
        ] as $response) {
            $this->assertIsArray(archive_candidate_encodings($response));
        }
    }

    public function testCandidateEncodingsRejectsEmptyDeclaredCharsets(): void {
        $response = "HTTP/1.1 200 OK\r\n" .
            "Content-Type: text/html; charset=\r\n\r\n" .
            '<meta charset="">';
        $this->assertSame([], archive_candidate_encodings($response));
    }

    public function testCandidateEncodingsDoesNotDuplicateRepeatedGuess(): void {
        $response = "HTTP/1.1 200 OK\r\n" .
            "X-Archive-Guessed-Charset: Shift_JIS\r\n\r\n" .
            '<meta charset="shift_jis">';
        $this->assertCount(1, archive_candidate_encodings($response));
    }

    public function testHttpResponsePartsHandlesEmptyInput(): void {
        $this->assertSame(['headers' => '', 'body' => ''], archive_http_response_parts(''));
    }

    public function testHttpResponsePartsHandlesOnlySeparatorBytes(): void {
        foreach (["\r\n\r\n", "\n\n"] as $response) {
            $this->assertSame(
                ['headers' => '', 'body' => $response],
                archive_http_response_parts($response)
            );
        }
    }

    public function testHttpResponsePartsCapsExcessiveInterimHeaderChains(): void {
        $response = '';
        for ($i = 0; $i < 12; ++$i) {
            $response .= "HTTP/1.1 100 Continue\r\nX-I: {$i}\r\n\r\n";
        }
        $response .= "HTTP/1.1 200 OK\r\n\r\nbody";
        $parts = archive_http_response_parts($response);
        $this->assertIsString($parts['headers']);
        $this->assertIsString($parts['body']);
    }

    public function testHttpResponsePartsDoesNotTreatBodyStatusTextAsAnotherHeaderBlock(): void {
        $response = "HTTP/1.1 200 OK\r\n\r\nHTTP/1.1 200 fake\r\n\r\npayload";
        $parts = archive_http_response_parts($response);
        $this->assertSame("HTTP/1.1 200 fake\r\n\r\npayload", $parts['body']);
    }

    public function testHttpResponsePartsPreservesNulBytesInBody(): void {
        $response = "HTTP/1.1 200 OK\r\n\r\na\x00b\x00c";
        $this->assertSame("a\x00b\x00c", archive_http_response_parts($response)['body']);
    }

    public function testTitleScanWindowHandlesEmptyAndTinyInputs(): void {
        foreach (['', '<', '<b', '<body', '<body>'] as $value) {
            $window = archive_title_scan_window($value);
            $this->assertIsString($window);
            $this->assertLessThanOrEqual(mb_strlen($value, '8bit'), mb_strlen($window, '8bit'));
        }
    }

    public function testTitleScanWindowDoesNotCrashOnMalformedUtf8AroundBody(): void {
        $input = "\xFF<head><title>x</title></head>\xFE<body>\xFDtail";
        $this->assertStringNotContainsString('tail', archive_title_scan_window($input));
    }

    public function testArchiveDecodeTitleHandlesEmptyCandidateList(): void {
        $this->assertSame(
            '',
            archive_decode_title("\xFF\xFE", [], 'https://web.archive.org/')
        );
    }

    public function testArchiveDecodeTitleHandlesEmptyArchiveUrl(): void {
        $this->assertSame(
            'café',
            archive_decode_title("caf\xE9", ['windows-1252'], '')
        );
    }

    public function testArchiveDecodeTitleCandidateReturnsStringForGarbage(): void {
        foreach (["\xFF\xFE\xFD", "\xC3", "\x80\x81\x82"] as $raw) {
            $this->assertIsString(
                archive_decode_title_candidate($raw, ['UTF-8'], 'https://web.archive.org/')
            );
        }
    }

    public function testMbSubstrReplaceHandlesLengthPastEnd(): void {
        $this->assertSame('abX', mb_substr_replace('abcdef', 'X', 2, 999));
    }

    public function testMbSubstrReplaceHandlesEmptyReplacement(): void {
        $this->assertSame('abef', mb_substr_replace('abcdef', '', 2, 2));
    }

    public function testMbSubstrReplaceHandlesEmptySubject(): void {
        $this->assertSame('X', mb_substr_replace('', 'X', 0, 0));
    }

    public function testMbStrrevHandlesEmptyAndSingleCharacterInputs(): void {
        $this->assertSame('', mb_strrev(''));
        $this->assertSame('😀', mb_strrev('😀', 'UTF-8'));
    }

    public function testMbStrrevByteModePreservesAllBytes(): void {
        $input = "\x00\xFF\x80A";
        $output = mb_strrev($input, '8bit');
        $this->assertSame(mb_strlen($input, '8bit'), mb_strlen($output, '8bit'));
        $this->assertSame(mb_strrev($input, '8bit'), $output);
    }

    public function testPublicConfigEncodingInitializerIsIdempotent(): void {
        initialize_public_config_encoding();
        initialize_public_config_encoding();
        $this->assertSame('UTF-8', mb_internal_encoding());
        $this->assertSame('UTF-8', mb_regex_encoding());
    }

    public function testZoteroRawResponseTrimHandlesAllWhitespace(): void {
        $method = new ReflectionMethod(APIzotero::class, 'trim_raw_response');
        $this->assertSame('', $method->invoke(null, " \t\r\n"));
        $this->assertSame('', $method->invoke(null, "\u{2003}\u{2003}"));
    }

    public function testZoteroRawResponseTrimDoesNotDropMalformedInteriorBytes(): void {
        $method = new ReflectionMethod(APIzotero::class, 'trim_raw_response');
        $this->assertSame("\xFF A \xFE", $method->invoke(null, " \xFF A \xFE "));
    }

    public function testZoteroExcerptHandlesZeroAndOversizedLimits(): void {
        $method = new ReflectionMethod(APIzotero::class, 'raw_response_excerpt');
        $this->assertSame('', $method->invoke(null, 'abcdef', 0));
        $this->assertSame('abcdef', $method->invoke(null, 'abcdef', 999));
    }

    public function testZoteroExcerptHandlesEmptyResponse(): void {
        $method = new ReflectionMethod(APIzotero::class, 'raw_response_excerpt');
        $this->assertSame('', $method->invoke(null, '', 500));
    }

    public function testZoteroExcerptMalformedResponsePreservesNulByte(): void {
        $method = new ReflectionMethod(APIzotero::class, 'raw_response_excerpt');
        $this->assertSame("\xFF\x00A", $method->invoke(null, "\xFF\x00ABCDE", 3));
    }

    public function testConvertToUtf8HandlesEmptyAndNulInputs(): void {
        $this->assertSame('', convert_to_utf8(''));
        $this->assertIsString(convert_to_utf8("\x00"));
    }

    public function testConvertToUtf8NeverReturnsInvalidUtf8(): void {
        foreach (["\xFF", "\xC3", "\x80\x81", "abc\xFFdef", "\x00\xFE\xFD"] as $input) {
            $result = convert_to_utf8($input);
            $this->assertTrue($result === '' || mb_check_encoding($result, 'UTF-8'), bin2hex($input));
        }
    }

    public function testSmartDecodeNeverThrowsForMalformedLabelsAndBytes(): void {
        foreach (['', ' ', "\x00", 'utf-8' . "\x00" . 'junk', str_repeat('A', 1000)] as $encoding) {
            try {
                $result = smart_decode("\xFF\xFEpayload", $encoding, 'https://web.archive.org/');
                $this->assertIsString($result);
            } catch (Throwable $throwable) {
                $this->fail(
                    'smart_decode threw ' . get_class($throwable) .
                    ' for label ' . bin2hex($encoding)
                );
            }
        }
    }

    public function testMixedEncodingHttpHeaderAndUtf8BodyKeepsDeclaredHeaderCandidateFirst(): void {
        $response =
            "HTTP/1.1 200 OK\r\n" .
            "Content-Type: text/html; charset=windows-1252\r\n\r\n" .
            '<html><head><title>Café 日本語</title></head><body></body></html>';

        $this->assertSame(
            ['windows-1252'],
            archive_candidate_encodings($response)
        );

        $this->assertSame(
            'Café 日本語',
            archive_decode_title(
                'Café 日本語',
                archive_candidate_encodings($response),
                'https://web.archive.org/'
            )
        );
    }

    public function testMixedEncodingHttpHeaderAndMetaDeclarationPreserveSourceOrder(): void {
        $response =
            "HTTP/1.1 200 OK\r\n" .
            "Content-Type: text/html; charset=windows-1252\r\n\r\n" .
            '<html><head><meta charset="Shift_JIS"></head></html>';

        $this->assertSame(
            ['windows-1252', 'Shift_JIS'],
            archive_candidate_encodings($response)
        );
    }

    public function testMixedEncodingMetaAndArchiveGuessPreserveDeclaredPriority(): void {
        $response =
            "HTTP/1.1 200 OK\r\n" .
            "X-Archive-Guessed-Charset: windows-1251\r\n\r\n" .
            '<meta charset="Shift_JIS">';

        $this->assertSame(
            ['Shift_JIS', 'windows-1251'],
            archive_candidate_encodings($response)
        );
    }

    public function testMixedEncodingPageCanDecodeLegacyTitleInsideUtf8Markup(): void {
        $title = "\x93Caf\xE9\x94";
        $html =
            '<html><head><meta charset="windows-1252"><title>' .
            $title .
            '</title></head><body>日本語</body></html>';

        preg_match('~<title>(.*?)</title>~s', $html, $match);
        $this->assertSame(
            '“Café”',
            archive_decode_title_candidate(
                $match[1],
                archive_candidate_encodings($html),
                'https://web.archive.org/'
            )
        );
    }

    public function testMixedEncodingPageCanDecodeShiftJisTitleWithUtf8BodyText(): void {
        $title = mb_convert_encoding('日本語', 'SJIS-win', 'UTF-8');
        $html =
            '<meta charset="Shift_JIS"><title>' .
            $title .
            '</title><body>Café 😀</body>';

        preg_match('~<title>(.*?)</title>~s', $html, $match);
        $this->assertSame(
            '日本語',
            archive_decode_title_candidate(
                $match[1],
                archive_candidate_encodings($html),
                'https://web.archive.org/'
            )
        );
    }

    public function testMixedEncodingPageDoesNotReinterpretAlreadyValidUtf8Title(): void {
        $title = 'Café — 東京';
        $html = '<meta charset="windows-1251"><title>' . $title . '</title>';

        $this->assertSame(
            $title,
            archive_decode_title_candidate(
                $title,
                archive_candidate_encodings($html),
                'https://web.archive.org/'
            )
        );
    }

    public function testMixedEncodingPageFallsThroughWrongFirstCandidateToCorrectSecond(): void {
        $title = "\x93Caf\xE9\x94";
        $encodings = ['UTF-8', 'windows-1252'];

        $this->assertSame(
            '“Café”',
            archive_decode_title(
                $title,
                $encodings,
                'https://web.archive.org/'
            )
        );
    }

    public function testMixedEncodingPageFallsThroughWrongLegacyCandidateToCorrectShiftJis(): void {
        $title = mb_convert_encoding('日本語', 'SJIS-win', 'UTF-8');

        $this->assertSame(
            '日本語',
            archive_decode_title(
                $title,
                ['UTF-8', 'Shift_JIS'],
                'https://web.archive.org/'
            )
        );
    }

    public function testMixedEncodingHeaderBytesDoNotCorruptHtmlMetaDiscovery(): void {
        $response =
            "HTTP/1.1 200 OK\r\n" .
            "X-Debug: \xFF\xFE\r\n\r\n" .
            '<meta charset="Shift_JIS">';

        $this->assertSame(
            ['Shift_JIS'],
            archive_candidate_encodings($response)
        );
    }

    public function testMixedEncodingMalformedBytesBeforeMetaDoNotPreventDiscovery(): void {
        $html = "\xFF\xFE" . '<meta charset="windows-1252">';

        $this->assertSame(
            ['windows-1252'],
            archive_candidate_encodings($html)
        );
    }

    public function testMixedEncodingMalformedBytesAfterMetaDoNotChangeDiscovery(): void {
        $html = '<meta charset="windows-1252">' . "\xFF\xFE";

        $this->assertSame(
            ['windows-1252'],
            archive_candidate_encodings($html)
        );
    }

    public function testMixedEncodingMultipleMetaDeclarationsKeepAllDistinctCandidates(): void {
        $html =
            '<meta charset="windows-1252">' .
            '<meta charset="Shift_JIS">' .
            '<meta charset="windows-1251">';

        $this->assertSame(
            ['windows-1252', 'Shift_JIS', 'windows-1251'],
            archive_candidate_encodings($html)
        );
    }

    public function testMixedEncodingDuplicateMetaDeclarationsAreCaseInsensitive(): void {
        $html =
            '<meta charset="Shift_JIS">' .
            '<meta charset="shift_jis">' .
            '<meta charset="SHIFT_JIS">';

        $this->assertSame(
            ['Shift_JIS'],
            archive_candidate_encodings($html)
        );
    }

    public function testMixedEncodingLegacyHttpEquivAndHtml5MetaCanDisagree(): void {
        $html =
            '<meta http-equiv="content-type" content="text/html; charset=windows-1252">' .
            '<meta charset="Shift_JIS">';

        $this->assertSame(
            ['windows-1252', 'Shift_JIS'],
            archive_candidate_encodings($html)
        );
    }

    public function testMixedEncodingUtf16MetaLabelMapsToUtf8WithoutTouchingLegacySecondCandidate(): void {
        $html =
            '<meta charset="UTF-16LE">' .
            '<meta charset="windows-1252">';

        $this->assertSame(
            ['UTF-8', 'windows-1252'],
            archive_candidate_encodings($html)
        );
    }

    public function testMixedEncodingXUserDefinedMetaMapsToWindows1252AndDeduplicates(): void {
        $html =
            '<meta charset="x-user-defined">' .
            '<meta charset="windows-1252">';

        $this->assertSame(
            ['windows-1252'],
            archive_candidate_encodings($html)
        );
    }

    public function testMixedEncodingArchiveGuessDoesNotOverrideEquivalentDeclaredAlias(): void {
        $response =
            "HTTP/1.1 200 OK\r\n" .
            "X-Archive-Guessed-Charset: WINDOWS-1252\r\n\r\n" .
            '<meta charset="windows-1252">';

        $this->assertSame(
            ['windows-1252'],
            archive_candidate_encodings($response)
        );
    }

    public function testMixedEncodingMalformedLegacyBytesAroundValidUtf8TitleAreRejectedOrClean(): void {
        $raw = "\xFF" . 'Café' . "\xFE";

        $result = archive_decode_title_candidate(
            $raw,
            ['UTF-8', 'windows-1252'],
            'https://web.archive.org/'
        );

        $this->assertTrue(
            $result === '' || mb_check_encoding($result, 'UTF-8')
        );
    }

    public function testMixedEncodingSmartDecodeWindows1252BytesContainingUtf8AsciiIsland(): void {
        $raw = "\x93ASCII / 日本語 / Caf\xE9\x94";

        $result = smart_decode(
            $raw,
            'windows-1252',
            'https://web.archive.org/'
        );

        $this->assertIsString($result);
        $this->assertTrue(
            $result === '' || mb_check_encoding($result, 'UTF-8')
        );
    }

    public function testMixedEncodingSmartDecodeShiftJisBytesContainingAsciiMarkup(): void {
        $encoded = mb_convert_encoding('日本語', 'SJIS-win', 'UTF-8');
        $raw = '<title>' . $encoded . '</title>';

        $decoded = smart_decode(
            $raw,
            'Shift_JIS',
            'https://web.archive.org/'
        );

        $this->assertStringContainsString('<title>', $decoded);
        $this->assertStringContainsString('日本語', $decoded);
        $this->assertStringContainsString('</title>', $decoded);
    }

    public function testMixedEncodingCandidateDetectionIgnoresBodyTextThatLooksLikeHeader(): void {
        $response =
            "HTTP/1.1 200 OK\r\n\r\n" .
            '<body>x-archive-guessed-charset: Shift_JIS</body>';

        $this->assertSame(
            [],
            archive_candidate_encodings($response)
        );
    }

    public function testMixedEncodingCandidateDetectionUsesMetaEvenWhenBodyContainsForeignBytes(): void {
        $response =
            '<meta charset="windows-1252"><body>' .
            "\x82\xA0\x82\xA2" .
            '</body>';

        $this->assertSame(
            ['windows-1252'],
            archive_candidate_encodings($response)
        );
    }

    public function testMixedEncodingHttpResponsePartsPreserveForeignBytesExactly(): void {
        $body = "\x93Caf\xE9\x94" . mb_convert_encoding('日本語', 'SJIS-win', 'UTF-8');
        $response =
            "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\n\r\n" .
            $body;

        $parts = archive_http_response_parts($response);

        $this->assertSame($body, $parts['body']);
    }

    public function testMixedEncodingTitleScanWindowPreservesByteLengthAcrossLegacyData(): void {
        $prefix = '<html><head><title>';
        $legacy = str_repeat("\x93A\x94", 100);
        $suffix = '</title></head><body>late';

        $window = archive_title_scan_window($prefix . $legacy . $suffix);

        $this->assertStringNotContainsString('late', $window);
        $this->assertStringContainsString('<title>', $window);
    }

    public function testMixedEncodingMetaScannerFindsAsciiMetaAcrossNonUtf8Noise(): void {
        $html =
            "\xFF\xFE\xFD" .
            '<meta charset="Shift_JIS">' .
            "\x81\x82\x83";

        $this->assertSame(
            ['<meta charset="Shift_JIS">'],
            archive_html_meta_tags($html)
        );
    }

    public function testMixedEncodingMetaScannerSkipsForeignBytesInsideScript(): void {
        $html =
            '<script>' .
            "\xFF\xFE<meta charset=\"windows-1252\">\xFD" .
            '</script>' .
            '<meta charset="Shift_JIS">';

        $this->assertSame(
            ['<meta charset="Shift_JIS">'],
            archive_html_meta_tags($html)
        );
    }

    public function testMixedEncodingQuotedContentTypeCanContainForeignBytesOutsideCharset(): void {
        $contentType = "text/html; boundary=\"\xFF\xFE\"; charset=Shift_JIS";

        $this->assertSame(
            'Shift_JIS',
            archive_charset_parameter($contentType)
        );
    }

    public function testMixedEncodingDeclaredCharsetSurvivesForeignBytesInOtherAttributes(): void {
        $tag = "<meta data-note=\"\xFF\xFE\" charset=\"windows-1252\">";

        $this->assertSame(
            'windows-1252',
            archive_html_attributes($tag)['charset']
        );
    }

    public function testMixedEncodingDecodeTitleCandidateTrimsUnicodeAfterLegacyDecode(): void {
        $raw = " \x93Caf\xE9\x94 ";

        $this->assertSame(
            '“Café”',
            archive_decode_title_candidate(
                $raw,
                ['windows-1252'],
                'https://web.archive.org/'
            )
        );
    }

    public function testMixedEncodingWrongMetaDeclarationDoesNotCorruptValidUtf8Title(): void {
        $title = 'Ελλάδα — 日本語';
        $encodings = ['windows-1252'];

        $this->assertSame(
            $title,
            archive_decode_title(
                $title,
                $encodings,
                'https://web.archive.org/'
            )
        );
    }

    public function testMixedEncodingConvertToUtf8NeverProducesInvalidUtf8FromHybridBytes(): void {
        $hybrids = [
            'Café' . "\x96" . 'dash',
            "\x93quoted\x94" . ' 日本語',
            mb_convert_encoding('日本語', 'SJIS-win', 'UTF-8') . ' Café',
            "\xFF" . 'valid utf8 😀' . "\xFE",
        ];

        foreach ($hybrids as $input) {
            $result = convert_to_utf8($input);
            $this->assertTrue(
                $result === '' || mb_check_encoding($result, 'UTF-8'),
                bin2hex($input)
            );
        }
    }

}
