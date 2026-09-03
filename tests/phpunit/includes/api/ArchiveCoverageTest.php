<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class ArchiveCoverageTest extends testBaseClass {

    /**
     * Verify that every host in the production allow-list is actually accepted.
     *
     * @return iterable<string, array{string}>
     */
    public static function archiveHostProvider(): iterable {
        foreach (ARCHIVE_FETCH_HOSTS as $host) {
            yield $host => [$host];
        }
    }

    #[DataProvider('archiveHostProvider')]
    public function testEveryConfiguredArchiveHostIsAllowed(string $host): void {
        $this->assertTrue(
            archive_url_is_allowed('https://' . $host . '/archive/path')
        );
    }

    #[DataProvider('validArchiveUrlProvider')]
    public function testArchiveUrlAdditionalValidForms(string $url): void {
        $this->assertTrue(archive_url_is_allowed($url));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validArchiveUrlProvider(): array {
        return [
            'uppercase scheme and host' => [
                'HTTPS://WEB.ARCHIVE.ORG/archive',
            ],
            'http explicit default port' => [
                'http://web.archive.org:80/archive',
            ],
            'https explicit default port' => [
                'https://web.archive.org:443/archive',
            ],
            'query string allowed' => [
                'https://web.archive.org/archive?foo=bar',
            ],
            'fragment allowed' => [
                'https://web.archive.org/archive#section',
            ],
            'query and fragment allowed' => [
                'https://web.archive.org/archive?foo=bar#section',
            ],
        ];
    }

    #[DataProvider('invalidArchiveUrlProvider')]
    public function testArchiveUrlAdditionalInvalidForms(string $url): void {
        $this->assertFalse(
            archive_url_is_allowed($url),
            'URL should be rejected: ' . $url
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidArchiveUrlProvider(): array {
        return [
            'empty' => [''],
            'leading whitespace' => [
                ' https://web.archive.org/archive',
            ],
            'trailing whitespace' => [
                'https://web.archive.org/archive ',
            ],
            'embedded newline' => [
                "https://web.archive.org/archive\nfoo",
            ],
            'embedded tab' => [
                "https://web.archive.org/archive\tfoo",
            ],
            'backslash' => [
                'https://web.archive.org/archive\\foo',
            ],
            'missing scheme' => [
                'web.archive.org/archive',
            ],
            'scheme but missing host' => [
                'https:/archive',
            ],
            'ftp scheme' => [
                'ftp://web.archive.org/archive',
            ],
            'file scheme' => [
                'file://web.archive.org/archive',
            ],
            'username' => [
                'https://user@web.archive.org/archive',
            ],
            'username and password' => [
                'https://user:password@web.archive.org/archive',
            ],
            'http with https port' => [
                'http://web.archive.org:443/archive',
            ],
            'https with http port' => [
                'https://web.archive.org:80/archive',
            ],
            'high nonstandard port' => [
                'https://web.archive.org:65535/archive',
            ],
        ];
    }

    #[DataProvider('throttleDelayProvider')]
    public function testArchiveThrottleDelayBoundaries(
        float $now,
        float $last,
        float $minimumInterval,
        int $expected
    ): void {
        $this->assertSame(
            $expected,
            archive_throttle_delay(
                $now,
                $last,
                $minimumInterval
            )
        );
    }

    /**
     * @return array<string, array{float, float, float, int}>
     */
    public static function throttleDelayProvider(): array {
        return [
            'negative last time' => [
                100.0,
                -1.0,
                1.0,
                0,
            ],
            'zero interval' => [
                100.0,
                99.0,
                0.0,
                0,
            ],
            'negative interval' => [
                100.0,
                99.0,
                -1.0,
                0,
            ],
            'exactly elapsed' => [
                101.0,
                100.0,
                1.0,
                0,
            ],
            'quarter second remains' => [
                100.75,
                100.0,
                1.0,
                250000,
            ],
            'half second remains' => [
                100.5,
                100.0,
                1.0,
                500000,
            ],
            'custom two second interval' => [
                100.25,
                100.0,
                2.0,
                1750000,
            ],
            'future last timestamp is capped' => [
                100.0,
                101.0,
                1.0,
                1000000,
            ],
            'future last with custom interval capped' => [
                100.0,
                101.0,
                0.5,
                500000,
            ],
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function saneEncodingProvider(): iterable {
        foreach (SANE_ENCODE as $encoding) {
            yield $encoding => [$encoding];
        }
    }

    #[DataProvider('saneEncodingProvider')]
    public function testSaneEncodingsAreNotWorthTrying(string $encoding): void {
        $this->assertFalse(
            is_encoding_reasonable($encoding)
        );
    }

    #[DataProvider('additionalEncodingProvider')]
    public function testEncodingReasonableness(
        string $encoding,
        bool $expected
    ): void {
        $this->assertSame(
            $expected,
            is_encoding_reasonable($encoding)
        );
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function additionalEncodingProvider(): array {
        return [
            'UTF-8 uppercase is sane' => [
                'UTF-8',
                false,
            ],
            'Windows uppercase is sane' => [
                'WINDOWS-1252',
                false,
            ],
            'Latin1 uppercase is sane' => [
                'LATIN1',
                false,
            ],
            'shift jis worth trying' => [
                'shift_jis',
                true,
            ],
            'big5 worth trying' => [
                'big5',
                true,
            ],
            'koi8 worth trying' => [
                'koi8-r',
                true,
            ],
            'unknown encoding worth trying' => [
                'citation-bot-made-up-encoding',
                true,
            ],
        ];
    }

    public function testSmartDecodeEmptyTitleReturnsEmpty(): void {
        $this->assertSame(
            '',
            smart_decode(
                '',
                'UTF-8',
                'https://web.archive.org/'
            )
        );
    }

    #[DataProvider('utf8AliasProvider')]
    public function testSmartDecodeNormalizesUtf8Aliases(
        string $encoding
    ): void {
        $this->assertSame(
            'Archive title',
            smart_decode(
                'Archive title',
                $encoding,
                'https://web.archive.org/'
            )
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function utf8AliasProvider(): array {
        return [
            'compound utf8 header' => [
                'UTF-8; charset=UTF-8',
            ],
            'en utf8' => [
                'en-utf-8',
            ],
            'utf8' => [
                'utf8',
            ],
            'windows utf8' => [
                'windows-utf-8',
            ],
            'mysql utf8 collation name' => [
                'utf8_unicode_ci',
            ],
        ];
    }

    #[DataProvider('insaneEncodingProvider')]
    public function testSmartDecodeRejectsKnownBadEncodings(
        string $encoding
    ): void {
        $this->assertSame(
            '',
            smart_decode(
                'Archive title',
                $encoding,
                'https://web.archive.org/'
            )
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function insaneEncodingProvider(): array {
        return [
            'utf8 sig' => ['utf-8-sig'],
            'user defined' => ['x-user-defined'],
        ];
    }

    public function testSmartDecodeNormalizesNumericIsoEncoding(): void {
        $this->assertSame(
            'café',
            smart_decode(
                "caf\xE9",
                '8859-1',
                'https://web.archive.org/'
            )
        );
    }

    public function testSmartDecodeNormalizesIsoCase(): void {
        $this->assertSame(
            'café',
            smart_decode(
                "caf\xE9",
                'ISO-8859-1',
                'https://web.archive.org/'
            )
        );
    }

    public function testSmartDecodeUsesTryEncodingList(): void {
        // windows-1250 is in TRY_ENCODE and therefore takes
        // the iconv path.
        $this->assertSame(
            'café',
            smart_decode(
                "caf\xE9",
                'windows-1250',
                'https://web.archive.org/'
            )
        );
    }

    public function testSmartDecodeShiftJisAlias(): void {
        $encoded = mb_convert_encoding(
            '日本語',
            'SJIS-win',
            'UTF-8'
        );

        $this->assertSame(
            '日本語',
            smart_decode(
                $encoded,
                'Shift_JIS',
                'https://web.archive.org/'
            )
        );
    }

    public function testSmartDecodeShiftJisAliasIsCaseInsensitive(): void {
        $encoded = mb_convert_encoding(
            '日本語',
            'SJIS-win',
            'UTF-8'
        );

        $this->assertSame(
            '日本語',
            smart_decode(
                $encoded,
                'shift_jis',
                'https://web.archive.org/'
            )
        );
    }

    public function testSmartDecodeRejectsInvalidUtf8ForUtf8Label(): void {
        $this->assertSame(
            '',
            smart_decode(
                "caf\xE9",
                'UTF-8',
                'https://web.archive.org/'
            )
        );
    }

    public function testSmartDecodeUsesWindows1252ForHtmlLatin1Aliases(): void {
        foreach (['ISO-8859-1', '8859-1', 'latin1', 'us-ascii', 'cp1252'] as $encoding) {
            $this->assertSame(
                'A€B',
                smart_decode(
                    "A\x80B",
                    $encoding,
                    'https://web.archive.org/'
                ),
                $encoding
            );
        }
    }

    public function testSmartDecodeCoversAdditionalHtmlLatin1Aliases(): void {
        foreach ([
            'iso8859-1',
            'iso_8859-1',
            'iso_8859-1:1987',
            'latin-1',
            'l1',
            'IBM819',
            'csISOLatin1',
            'x-cp1252',
        ] as $encoding) {
            $this->assertSame(
                'A€B',
                smart_decode(
                    "A\x80B",
                    $encoding,
                    'https://web.archive.org/'
                ),
                $encoding
            );
        }
    }

    public function testSmartDecodeAcceptsCaseInsensitiveCp932Alias(): void {
        $encoded = mb_convert_encoding('日本語', 'SJIS-win', 'UTF-8');
        $this->assertSame('日本語', smart_decode($encoded, 'CP932', 'https://web.archive.org/'));
    }

    public function testSmartDecodeRejectsMalformedKnownSourceEncoding(): void {
        $this->assertSame(
            '',
            smart_decode(
                "\x82",
                'Shift_JIS',
                'https://web.archive.org/'
            )
        );
    }

    public function testSmartDecodeRejectsUnsupportedWebEncodingLabels(): void {
        foreach (['UTF-7', 'unicode', 'none'] as $encoding) {
            $this->assertSame(
                '',
                smart_decode(
                    'Archive title',
                    $encoding,
                    'https://web.archive.org/'
                ),
                $encoding
            );
        }
    }

    public function testSmartDecodeXSjisAlias(): void {
        $encoded = mb_convert_encoding(
            '日本語',
            'SJIS-win',
            'UTF-8'
        );

        $this->assertSame(
            '日本語',
            smart_decode(
                $encoded,
                'x-sjis',
                'https://web.archive.org/'
            )
        );
    }

    public function testSmartDecodeSjisAlias(): void {
        $encoded = mb_convert_encoding(
            '日本語',
            'SJIS-win',
            'UTF-8'
        );

        $this->assertSame(
            '日本語',
            smart_decode(
                $encoded,
                'SJIS',
                'https://web.archive.org/'
            )
        );
    }

    public function testSmartDecodeBig5Alias(): void {
        $encoded = mb_convert_encoding(
            '中文',
            'BIG-5',
            'UTF-8'
        );

        $this->assertSame(
            '中文',
            smart_decode(
                $encoded,
                'big5',
                'https://web.archive.org/'
            )
        );
    }

    public function testSmartDecodeUnknownEncodingReturnsEmpty(): void {
        $this->assertSame(
            '',
            smart_decode(
                'Archive title',
                'citation-bot-not-a-real-encoding',
                'https://web.archive.org/'
            )
        );
    }

    public function testConvertToUtf8InsideLeavesUtf8Alone(): void {
        $value = 'Déjà vu — 日本語';

        $this->assertSame(
            $value,
            convert_to_utf8_inside($value)
        );
    }

    public function testConvertToUtf8InsideLeavesWindows1252Alone(): void {
        $value = "\x93Quoted text\x94";

        $this->assertSame(
            $value,
            convert_to_utf8_inside($value)
        );
    }

    public function testConvertToUtf8InsideRejectsAmbiguousAsianEncoding(): void {
        // A1 A1 is valid in multiple East Asian encodings.
        // The inner detector deliberately refuses conversion when its
        // detection passes disagree, and the public wrapper rejects it.
        $value = "\xA1\xA1";

        $this->assertSame(
            $value,
            convert_to_utf8_inside($value)
        );
        $this->assertSame('', convert_to_utf8($value));
    }

    #[DataProvider('utf8RepairProvider')]
    public function testConvertToUtf8SpecialRepairs(
        string $input,
        string $expected
    ): void {
        $this->assertSame(
            $expected,
            convert_to_utf8($input)
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function utf8RepairProvider(): array {
        return [
            'plain ascii unchanged' => [
                'Archive title',
                'Archive title',
            ],
            'unlabelled Windows-1252 accent rejected' => [
                "caf\xE9",
                '',
            ],
            'unlabelled Windows-1252 dash rejected' => [
                "A\x96B",
                '',
            ],
            'Livelong quotes repaired' => [
                ' �Livelong� ',
                ' "Livelong" ',
            ],
            'Uniao repaired' => [
                'Uni�o',
                'União',
            ],
            'Independencia repaired' => [
                'Independ�ncia',
                'Independência',
            ],
            'Ekstrom repaired' => [
                'Folke Ekstr�m',
                'Folke Ekström',
            ],
        ];
    }

    public function testArchiveCandidateEncodingsFindsHtml5MetaCharset(): void {
        $html = '<html><head><meta charset="Shift_JIS"></head></html>';

        $this->assertSame(
            ['Shift_JIS'],
            archive_candidate_encodings($html)
        );
    }

    public function testArchiveCandidateEncodingsFindsLegacyMetaCharset(): void {
        $html =
            "<meta http-equiv='content-type' " .
            "content='text/html; charset=big5'>";

        $this->assertSame(
            ['big5'],
            archive_candidate_encodings($html)
        );
    }

    public function testArchiveCandidateEncodingsFindsHttpCharset(): void {
        $html =
            "HTTP/1.1 200 OK\r\n" .
            "Content-Type: text/html; charset=windows-1251\r\n\r\n" .
            '<html><head><title>Example</title></head></html>';

        $this->assertSame(
            ['windows-1251'],
            archive_candidate_encodings($html)
        );
    }

    public function testArchiveCandidateEncodingsKeepsExplicitDefaultCharset(): void {
        $html = '<html><head><meta charset="windows-1252"></head></html>';

        $this->assertSame(
            ['windows-1252'],
            archive_candidate_encodings($html)
        );
    }

    public function testArchiveCandidateEncodingsPreferDeclarationsOverGuess(): void {
        $html =
            "HTTP/1.1 200 OK\r\n" .
            "x-archive-guessed-charset: Shift_JIS\r\n\r\n" .
            '<meta charset="windows-1252">';

        $this->assertSame(
            ['windows-1252', 'Shift_JIS'],
            archive_candidate_encodings($html)
        );
    }

    public function testArchiveCandidateEncodingsDeduplicatesCaseInsensitively(): void {
        $html =
            "HTTP/1.1 200 OK\r\n" .
            "x-archive-guessed-charset: Shift_JIS\r\n\r\n" .
            '<meta charset="shift_jis">';

        $this->assertSame(
            ['shift_jis'],
            archive_candidate_encodings($html)
        );
    }

    public function testArchiveCandidateEncodingsSupportsDeclaredIso88597(): void {
        $html = '<meta charset="iso-8859-7">';
        $encoded = mb_convert_encoding('Ελλάδα', 'ISO-8859-7', 'UTF-8');
        $encodings = archive_candidate_encodings($html);

        $this->assertSame(['iso-8859-7'], $encodings);
        $this->assertSame(
            'Ελλάδα',
            smart_decode(
                $encoded,
                $encodings[0],
                'https://web.archive.org/'
            )
        );
    }

    public function testArchiveCharsetParameterRequiresExactName(): void {
        $this->assertSame(
            'windows-1251',
            archive_charset_parameter('text/html; charset = " windows-1251 "')
        );
        $this->assertNull(archive_charset_parameter('text/html; x-charset=Shift_JIS'));
        $this->assertNull(archive_charset_parameter('text/html; foocharset=big5'));
        $this->assertNull(archive_charset_parameter('text/html; charset='));
        $this->assertNull(archive_charset_parameter(
            'text/html; boundary="foo;charset=Shift_JIS"'
        ));
        $this->assertSame(
            'windows-1252',
            archive_charset_parameter(
                'text/html; charset=windows-1252; charset=big5'
            )
        );
    }

    public function testArchiveHtmlAttributesKeepsFirstDuplicateAttribute(): void {
        $attributes = archive_html_attributes(
            '<meta charset="utf-8" charset="shift_jis" content="first" content="second">'
        );

        $this->assertSame('utf-8', $attributes['charset']);
        $this->assertSame('first', $attributes['content']);
    }

    public function testArchiveCandidateEncodingsSupportsUnquotedLegacyContentType(): void {
        $this->assertSame(
            ['big5'],
            archive_candidate_encodings(
                '<meta http-equiv=content-type content=text/html;charset=big5>'
            )
        );
    }

    public function testArchiveCandidateEncodingsRejectsNonCharsetParameters(): void {
        foreach ([
            "HTTP/1.1 200 OK\r\nContent-Type: text/html; x-charset=Shift_JIS\r\n\r\n",
            '<meta http-equiv="content-type" content="text/html; x-charset=Shift_JIS">',
        ] as $html) {
            $this->assertSame([], archive_candidate_encodings($html), $html);
        }
    }

    public function testArchiveCandidateEncodingsOrdersHttpThenMetaThenGuess(): void {
        $html =
            "HTTP/1.1 200 OK\r\n" .
            "Content-Type: text/html; charset=windows-1251\r\n" .
            "x-archive-guessed-charset: Shift_JIS\r\n\r\n" .
            '<meta charset="big5">';

        $this->assertSame(
            ['windows-1251', 'big5', 'Shift_JIS'],
            archive_candidate_encodings($html)
        );
    }

    public function testArchiveCandidateEncodingsSupportsLfHttp2Headers(): void {
        $html =
            "HTTP/2 200\n" .
            "Content-Type: text/html; charset=windows-1251\n\n" .
            '<meta charset="big5">';

        $this->assertSame(
            ['windows-1251', 'big5'],
            archive_candidate_encodings($html)
        );
    }

    public function testArchiveCandidateEncodingsHandlesAttributeCaseAndOrder(): void {
        foreach ([
            "<META CHARSET = 'Shift_JIS'>" => ['Shift_JIS'],
            '<meta content="text/html;charset=windows-1251" http-equiv="CONTENT-TYPE">' => ['windows-1251'],
        ] as $html => $expected) {
            $this->assertSame($expected, archive_candidate_encodings($html), $html);
        }
    }

    public function testArchiveCandidateEncodingsRejectsCharsetLookalikes(): void {
        foreach ([
            '<meta data-charset="Shift_JIS">',
            '<meta name="charset" content="windows-1251">',
            '<meta name="description" content="foo charset=windows-1251">',
            '<!-- <meta charset="big5"> -->',
            '<script>const fake = \'<meta charset="big5">\';</script>',
            '<title>x-archive-guessed-charset: Shift_JIS</title>',
        ] as $html) {
            $this->assertSame([], archive_candidate_encodings($html), $html);
        }
    }

    public function testArchiveCandidateEncodingsSupportsUnquotedCharset(): void {
        $this->assertSame(
            ['big5'],
            archive_candidate_encodings('<meta charset=big5/>')
        );
    }

    public function testArchiveGuessMustBeInHttpHeaders(): void {
        $this->assertSame(
            [],
            archive_candidate_encodings(
                '<html><head><script>' .
                '"x-archive-guessed-charset: Shift_JIS"' .
                '</script></head></html>'
            )
        );
    }

    public function testArchiveDecodeTitleKeepsAlreadyValidUtf8(): void {
        $title = 'Café — 東京';

        foreach (['windows-1252', 'ISO-8859-1', 'Shift_JIS', 'windows-1251'] as $encoding) {
            $this->assertSame(
                $title,
                archive_decode_title(
                    $title,
                    [$encoding],
                    'https://web.archive.org/'
                ),
                $encoding
            );
        }
    }

    public function testArchiveDecodeTitleUsesDeclaredLegacyEncoding(): void {
        $this->assertSame(
            'café',
            archive_decode_title(
                "caf\xE9",
                ['iso-8859-1'],
                'https://web.archive.org/'
            )
        );
    }

    public function testArchiveDecodeTitleFallsBackAcrossCandidates(): void {
        $this->assertSame(
            'café',
            archive_decode_title(
                "caf\xE9",
                ['UTF-8', 'windows-1252'],
                'https://web.archive.org/'
            )
        );
    }

    public function testArchiveDecodeTitlePreservesAmbiguousValidUtf8(): void {
        // C3 80 is valid UTF-8 for À but is also decodable as Windows-1251.
        // The conservative UTF-8-first rule must not reinterpret it.
        $title = "\xC3\x80";
        $this->assertTrue(mb_check_encoding($title, 'UTF-8'));
        $this->assertSame(
            'À',
            archive_decode_title(
                $title,
                ['windows-1251'],
                'https://web.archive.org/'
            )
        );
    }

    public function testArchiveDecodeTitleRejectsUnresolvableInvalidBytes(): void {
        $this->assertSame(
            '',
            archive_decode_title(
                "\xA1\xA1",
                ['UTF-8'],
                'https://web.archive.org/'
            )
        );
    }

    public function testArchiveHttpHeaderBlockHandlesChainedInterimResponses(): void {
        $response =
            "HTTP/1.1 103 Early Hints\r\nLink: </style.css>; rel=preload\r\n\r\n" .
            "HTTP/1.1 100 Continue\r\n\r\n" .
            "HTTP/3 200\nContent-Type: text/html; charset=windows-1251\n\n" .
            '<html></html>';

        $headers = archive_http_header_block($response);
        $this->assertStringStartsWith('HTTP/3 200', $headers);
        $this->assertStringContainsString('charset=windows-1251', $headers);
    }

    public function testArchiveHttpHeaderBlockAcceptsHttp10WithoutReasonPhrase(): void {
        $response =
            "HTTP/1.0 200\r\n" .
            "Content-Type: text/html; charset=big5\r\n\r\n" .
            '<html></html>';

        $this->assertSame(
            "HTTP/1.0 200\r\nContent-Type: text/html; charset=big5",
            archive_http_header_block($response)
        );
    }

    public function testArchiveCharsetParameterIgnoresSingleQuotedSemicolonText(): void {
        $contentType =
            "multipart/mixed; boundary='part;charset=Shift_JIS'; charset=windows-1252";

        $this->assertSame(
            'windows-1252',
            archive_charset_parameter($contentType)
        );
    }

    public function testArchiveMetaSupportsFormFeedSeparatorsAndSelfClosingTag(): void {
        $html = "<meta\fcharset='Shift_JIS'/>";
        $this->assertSame(
            ['Shift_JIS'],
            archive_meta_declared_encodings($html)
        );
    }

    public function testArchiveCandidateEncodingsDeduplicatesAcrossAllSources(): void {
        $response =
            "HTTP/1.1 200 OK\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "x-archive-guessed-charset: Shift_JIS\r\n\r\n" .
            '<meta charset="utf-8">';

        $this->assertSame(
            ['UTF-8', 'Shift_JIS'],
            archive_candidate_encodings($response)
        );
    }

    public function testSmartDecodeTrimsAsciiWhitespaceAroundWebLabel(): void {
        $this->assertSame(
            'A€B',
            smart_decode(
                "A\x80B",
                "\t ANSI_X3.4-1968 \r\n",
                'https://web.archive.org/'
            )
        );
    }

    public function testSmartDecodeBig5HyphenAliasIsCaseInsensitive(): void {
        $encoded = mb_convert_encoding('中文', 'BIG-5', 'UTF-8');
        $this->assertSame(
            '中文',
            smart_decode($encoded, 'BIG-5', 'https://web.archive.org/')
        );
    }

    public function testFetchArchivePageRejectsEmptyUrlBeforeCurl(): void {
        $ch = bot_curl_init(1, [], 1 * 1024 * 1024);
        $this->assertSame('', fetch_archive_page($ch, ''));
    }

    public function testFetchArchivePageRejectsNonArchiveHostBeforeCurl(): void {
        $ch = bot_curl_init(1, [], 1 * 1024 * 1024);
        $this->assertSame('', fetch_archive_page($ch, 'https://example.com/archive'));
    }

    public function testArchiveTitleScanWindowIsBounded(): void {
        $prefix = '<!doctype html><html><head><title>Useful title</title></head>';
        $payload = $prefix . str_repeat('X', ARCHIVE_TITLE_SCAN_MAX_BYTES + 1024);

        $window = archive_title_scan_window($payload);

        $this->assertLessThanOrEqual(ARCHIVE_TITLE_SCAN_MAX_BYTES, mb_strlen($window, '8bit'));
        $this->assertStringContainsString('Useful title', $window);
    }

    public function testArchiveTitleScanWindowStopsAfterOpeningBodyTag(): void {
        $payload =
            "HTTP/1.1 200 OK\r\n" .
            "x-archive-guessed-charset: UTF-8\r\n\r\n" .
            '<!doctype html><html><head><title>Useful title</title></head>' .
            '<body class="example">' .
            str_repeat('X', 100000);

        $window = archive_title_scan_window($payload);

        $this->assertStringEndsWith('<body class="example">', $window);
        $this->assertLessThan(mb_strlen($payload, '8bit'), mb_strlen($window, '8bit'));
    }

    public function testArchiveTitleScanWindowDoesNotIncludeLateBodyContent(): void {
        $payload = '<html><head><title>Early</title></head><body>' .
            str_repeat('X', 10000) . 'LATE_MARKER';

        $this->assertStringNotContainsString(
            'LATE_MARKER',
            archive_title_scan_window($payload)
        );
    }

    public function testArchiveTitleScanWindowCapsMultibyteInputByBytes(): void {
        $payload = str_repeat('é', ARCHIVE_TITLE_SCAN_MAX_BYTES);
        $this->assertGreaterThan(ARCHIVE_TITLE_SCAN_MAX_BYTES, mb_strlen($payload, '8bit'));

        $window = archive_title_scan_window($payload);

        $this->assertSame(ARCHIVE_TITLE_SCAN_MAX_BYTES, mb_strlen($window, '8bit'));
    }

    #[DataProvider('placeholderArchiveTitleProvider')]
    public function testScriptTitleRemovesPlaceholderArchiveTitle(
        string $title
    ): void {
        $template = $this->make_citation(
            '{{cite web'
            . '|title=' . $title
            . '|script-title=ja:保存されたページ'
            . '|archive-url=https://web.archive.org/example.pdf'
            . '}}'
        );

        $templates = [$template];

        // script-title prevents any actual archive request.
        expand_templates_from_archives($templates);

        $this->assertNull($template->get2('title'));
        $this->assertNotNull($template->get2('script-title'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function placeholderArchiveTitleProvider(): array {
        return [
            'archived copy' => ['Archived copy'],
            'archive copy' => ['Archive copy'],
            'usurped title' => ['Usurped title'],
        ];
    }

    public function testScriptTitleDoesNotRemoveRealTitle(): void {
        $template = $this->make_citation(
            '{{cite web'
            . '|title=A Real Article Title'
            . '|script-title=ja:本当のタイトル'
            . '|archive-url=https://web.archive.org/example.pdf'
            . '}}'
        );

        $templates = [$template];
        expand_templates_from_archives($templates);

        $this->assertSame(
            'A Real Article Title',
            $template->get2('title')
        );
    }

    #[DataProvider('archiveExpansionGuardProvider')]
    public function testArchiveExpansionGuardConditionsDoNotAlterTemplate(
        string $text
    ): void {
        $template = $this->make_citation($text);
        $before = $template->parsed_text();

        $templates = [$template];
        expand_templates_from_archives($templates);

        $this->assertSame(
            $before,
            $template->parsed_text()
        );
    }

    /**
     * All URLs use .pdf where appropriate, ensuring these tests remain
     * offline even if an earlier guard regresses.
     *
     * @return array<string, array{string}>
     */
    public static function archiveExpansionGuardProvider(): array {
        return [
            'chapter prevents expansion' => [
                '{{cite web'
                . '|chapter=Existing chapter'
                . '|archive-url=https://web.archive.org/example.pdf'
                . '}}',
            ],
            'series prevents expansion' => [
                '{{cite web'
                . '|series=Existing series'
                . '|archive-url=https://web.archive.org/example.pdf'
                . '}}',
            ],
            'script title prevents expansion' => [
                '{{cite web'
                . '|script-title=ja:既存のタイトル'
                . '|archive-url=https://web.archive.org/example.pdf'
                . '}}',
            ],
            'good existing title prevents replacement' => [
                '{{cite web'
                . '|title=Existing useful title'
                . '|archive-url=https://web.archive.org/example.pdf'
                . '}}',
            ],
            'work without website prevents expansion' => [
                '{{cite web'
                . '|work=Existing work'
                . '|archive-url=https://web.archive.org/example.pdf'
                . '}}',
            ],
            'pdf archive is not fetched' => [
                '{{cite web'
                . '|archive-url=https://web.archive.org/example.pdf'
                . '}}',
            ],
            'disallowed archive service is not fetched' => [
                '{{cite web'
                . '|archive-url=https://example.com/archive'
                . '}}',
            ],
        ];
    }

    public function testWebsiteAllowsWorkAliasPastWorkGuard(): void {
        $template = $this->make_citation(
            '{{cite web'
            . '|work=Existing work'
            . '|website=Existing website'
            . '|archive-url=https://web.archive.org/example.pdf'
            . '}}'
        );

        $before = $template->parsed_text();
        $templates = [$template];

        expand_templates_from_archives($templates);

        // The work/website guard is passed, but PDF detection prevents
        // the network request.
        $this->assertSame(
            $before,
            $template->parsed_text()
        );
    }

    public function testQuestionMarkDamagedTitlePassesReplacementTest(): void {
        $title = str_repeat('?', 11);

        $template = $this->make_citation(
            '{{cite web'
            . '|title=' . $title
            . '|archive-url=https://web.archive.org/example.pdf'
            . '}}'
        );

        $templates = [$template];
        expand_templates_from_archives($templates);

        // The damaged-title condition is true, but PDF protection
        // prevents a network call.
        $this->assertSame(
            $title,
            $template->get2('title')
        );
    }

    public function testSmartDecodeInvalidEncodingNeverThrows(): void {
        $this->assertSame(
            '',
            smart_decode(
                'Archive title',
                'definitely-not-an-encoding',
                'https://web.archive.org/'
            )
        );
    }

    public function testEncodingReasonablenessIgnoresCase(): void {
        $this->assertSame(
            is_encoding_reasonable('windows-1252'),
            is_encoding_reasonable('WINDOWS-1252')
        );
    }

    public function testArchiveHttpHeaderBlockUsesFinalInterimResponse(): void {
        $response =
            "HTTP/1.1 100 Continue\r\n" .
            "Content-Type: text/plain; charset=big5\r\n\r\n" .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Type: text/html; charset=windows-1251\r\n\r\n" .
            '<html></html>';

        $headers = archive_http_header_block($response);
        $this->assertStringStartsWith('HTTP/1.1 200 OK', $headers);
        $this->assertStringContainsString('charset=windows-1251', $headers);
        $this->assertStringNotContainsString('charset=big5', $headers);
    }

    public function testArchiveHttpHeaderBlockHandlesProxyConnectThenHttp2(): void {
        $response =
            "HTTP/1.1 200 Connection established\r\n\r\n" .
            "HTTP/2 200\nContent-Type: text/html; charset=big5\n\n" .
            '<html></html>';

        $this->assertSame(
            "HTTP/2 200\nContent-Type: text/html; charset=big5",
            archive_http_header_block($response)
        );
    }

    public function testArchiveHttpHeaderBlockRejectsIncompleteResponse(): void {
        $this->assertSame(
            '',
            archive_http_header_block(
                "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=big5"
            )
        );
    }

    public function testArchiveHttpHeaderBlockDoesNotParseStatusLikeBody(): void {
        $response =
            "HTTP/1.1 200 OK\r\n" .
            "Content-Type: text/html; charset=windows-1251\r\n\r\n" .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Type: text/plain; charset=big5\r\n\r\n";

        $headers = archive_http_header_block($response);
        $this->assertStringContainsString('charset=windows-1251', $headers);
        $this->assertStringNotContainsString('charset=big5', $headers);
    }

    public function testArchiveCandidateEncodingsUsesFinalHeaderBlock(): void {
        $response =
            "HTTP/1.1 100 Continue\r\n" .
            "Content-Type: text/plain; charset=Shift_JIS\r\n\r\n" .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Type: text/html; charset=windows-1251\r\n\r\n" .
            '<meta charset="big5">';

        $this->assertSame(
            ['windows-1251', 'big5'],
            archive_candidate_encodings($response)
        );
    }

    public function testArchiveMetaIgnoresUnterminatedComment(): void {
        $this->assertSame(
            [],
            archive_meta_declared_encodings('<!-- <meta charset="big5">')
        );
    }

    public function testArchiveMetaIgnoresUnterminatedRawTextElements(): void {
        foreach (['script', 'style', 'template', 'noscript'] as $tag) {
            $this->assertSame(
                [],
                archive_meta_declared_encodings(
                    '<' . $tag . '>fake <meta charset="big5">'
                ),
                $tag
            );
        }
    }

    public function testArchiveMetaHandlesGreaterThanInsideQuotedAttribute(): void {
        $html =
            '<meta content="text/html; note=a > b; charset=windows-1251" ' .
            'http-equiv="content-type">';

        $this->assertSame(
            ['windows-1251'],
            archive_meta_declared_encodings($html)
        );
    }

    public function testArchiveCharsetParameterIgnoresEscapedQuotedSemicolon(): void {
        $contentType = 'text/html; boundary="foo\\";charset=Shift_JIS"';
        $this->assertNull(archive_charset_parameter($contentType));
    }

    public function testArchiveCharsetParameterDecodesQuotedPairs(): void {
        $this->assertSame(
            'windows-1252',
            archive_charset_parameter('text/html; charset="windows\\-1252"')
        );
    }

    public function testArchiveCharsetParameterRejectsMalformedValues(): void {
        foreach ([
            'text/html; charset="windows-1252',
            'text/html; charset=windows-1252 extra',
            'text/html; charset="windows-1252" trailing',
        ] as $contentType) {
            $this->assertNull(
                archive_charset_parameter($contentType),
                $contentType
            );
        }
    }

    public function testArchiveHtmlAttributesFirstDuplicateIsCaseInsensitive(): void {
        $attributes = archive_html_attributes(
            '<meta CHARSET="utf-8" charset="shift_jis">'
        );

        $this->assertSame('utf-8', $attributes['charset']);
    }

    public function testArchiveMetaCharsetAttributeWinsOverLegacyPragma(): void {
        $html =
            '<meta charset="utf-8" http-equiv="content-type" ' .
            'content="text/html; charset=big5">';

        $this->assertSame(['utf-8'], archive_meta_declared_encodings($html));
    }

    public function testArchiveMetaEmptyCharsetFallsBackToLegacyPragma(): void {
        $html =
            '<meta charset="" http-equiv="content-type" ' .
            'content="text/html; charset=big5">';

        $this->assertSame(['big5'], archive_meta_declared_encodings($html));
    }

    public function testSmartDecodeCoversRemainingWindows1252WebAliases(): void {
        foreach (['ansi_x3.4-1968', 'iso-ir-100', 'iso88591'] as $encoding) {
            $this->assertSame(
                'A€B',
                smart_decode("A\x80B", $encoding, 'https://web.archive.org/'),
                $encoding
            );
        }
    }

    public function testSmartDecodeCoversUtf8WebAliases(): void {
        $title = 'Café 東京';
        foreach ([
            'unicode-1-1-utf-8',
            'unicode11utf8',
            'unicode20utf8',
            'x-unicode20utf8',
        ] as $encoding) {
            $this->assertSame(
                $title,
                smart_decode($title, $encoding, 'https://web.archive.org/'),
                $encoding
            );
        }
    }

    public function testSmartDecodeUtf8AliasesRejectMalformedUtf8(): void {
        foreach (['unicode11utf8', 'x-unicode20utf8'] as $encoding) {
            $this->assertSame(
                '',
                smart_decode("caf\xE9", $encoding, 'https://web.archive.org/'),
                $encoding
            );
        }
    }

    public function testSmartDecodeCoversShiftJisWebAliases(): void {
        $encoded = mb_convert_encoding('日本語', 'SJIS-win', 'UTF-8');
        foreach (['csshiftjis', 'ms932', 'ms_kanji'] as $encoding) {
            $this->assertSame(
                '日本語',
                smart_decode($encoded, $encoding, 'https://web.archive.org/'),
                $encoding
            );
        }
    }

    public function testArchiveDecodeTitlePreservesAsciiForLegacyDeclarations(): void {
        foreach (['windows-1251', 'Shift_JIS', 'big5'] as $encoding) {
            $this->assertSame(
                'Plain ASCII title',
                archive_decode_title(
                    'Plain ASCII title',
                    [$encoding],
                    'https://web.archive.org/'
                ),
                $encoding
            );
        }
    }
}
