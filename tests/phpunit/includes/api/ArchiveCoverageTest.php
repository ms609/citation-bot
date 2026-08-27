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
        // The function deliberately refuses conversion when its
        // detection passes disagree.
        $value = "\xA1\xA1";

        $this->assertSame(
            $value,
            convert_to_utf8_inside($value)
        );
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

    public function testFetchArchivePageRejectsEmptyUrlBeforeCurl(): void {
        $ch = curl_init();
        $this->assertSame('', fetch_archive_page($ch, ''));
    }

    public function testFetchArchivePageRejectsNonArchiveHostBeforeCurl(): void {
        $ch = curl_init();
        $this->assertSame('', fetch_archive_page($ch, 'https://example.com/archive'));
    }

    public function testArchiveTitleScanWindowIsBounded(): void {
        $prefix = '<!doctype html><html><head><title>Useful title</title></head>';
        $payload = $prefix . str_repeat('X', ARCHIVE_TITLE_SCAN_MAX_BYTES + 1024);

        $window = archive_title_scan_window($payload);

        $this->assertLessThanOrEqual(ARCHIVE_TITLE_SCAN_MAX_BYTES, mb_strlen($window, '8-bit'));
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
        $this->assertLessThan(mb_strlen($payload, '8-bit'), mb_strlen($window, '8-bit'));
    }

    public function testArchiveTitleScanWindowDoesNotIncludeLateBodyContent(): void {
        $payload = '<html><head><title>Early</title></head><body>' .
            str_repeat('X', 10000) . 'LATE_MARKER';

        $this->assertStringNotContainsString(
            'LATE_MARKER',
            archive_title_scan_window($payload)
        );
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
}
