<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class WhosWhoConversionTest extends testBaseClass {
    private const string DOI = '10.1093/ww/9780199540884.013.U192476';
    private const string TITLE = 'Wyatt, Sir William Henry';
    private const string CROSSREF_TITLE = 'Wyatt, Sir William Henry, (1823–6 Jan. 1898), JP, DL';
    private const string PUBLISHER_URL = 'https://www.ukwhoswho.com/view/10.1093/ww/9780199540891.001.0001/ww-9780199540884-e-192476';

    /** @param array<string, mixed> $overrides */
    private static function crossrefRecord(array $overrides = []): object {
        return (object) array_replace([
            'DOI' => self::DOI,
            'type' => 'reference-entry',
            'title' => [self::TITLE],
            'container-title' => ['Who Was Who'],
        ], $overrides);
    }

    #[DataProvider('supportedCitations')]
    public function testConvertsVerifiedDoi(string $doi, string $name, string $container, ?string $type): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Crossref metadata');
        }
        $record = self::crossrefRecord(['DOI' => mb_strtolower($doi), 'container-title' => [$container]]);
        $template = $this->make_citation('{{' . $name . '|doi=' . $doi . '}}');
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('query_crossref_newapi', $record);
        try {
            $template->convert_whos_who_doi();
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('query_crossref_newapi');
        }

        $this->assertSame("who's who", $template->wikiname());
        $this->assertSame('U192476', $template->get('id'));
        $this->assertSame($doi, $template->get('doi'));
        $this->assertSame(self::TITLE, $template->get('title'));
        $this->assertSame($type, $template->get2('type'));
        $this->assertSame($name === 'citation' ? 'cs2' : null, $template->get2('mode'));
    }

    /** @return array<string, array{string, string, string, ?string}> */
    public static function supportedCitations(): array {
        return [
            'DOI in cite web' => [self::DOI, 'cite web', 'Who Was Who', 'was'],
            'lowercase DOI in CS2' => [mb_strtolower(self::DOI), 'citation', 'Who Was Who', 'was'],
            'DOI with numeric suffix' => ['10.1093/ww/9780199540884.013.192476', 'cite journal', "Who's Who", null],
            'typographic apostrophe' => [self::DOI, 'cite encyclopedia', 'Who’s Who', null],
            'British template spelling' => [self::DOI, 'cite encyclopaedia', 'Who Was Who', 'was'],
        ];
    }

    public function testPageConversionPreservesFieldsAndIsIdempotent(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Crossref metadata');
        }
        $record = self::crossrefRecord(['title' => ['Wyatt, Sir William Henry &amp; family']]);
        $response = json_encode(['status' => 'ok', 'message-type' => 'work', 'message' => $record], JSON_THROW_ON_ERROR);
        $requests = [];
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('bot_curl_exec', static function (CurlHandle $handle) use ($response, &$requests): string {
            $requests[] = (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
            return $response;
        }, true);
        $input = '{{cite web|doi=' . self::DOI . '|url=https://doi.org/' . self::DOI .
            '|website=Who Was Who|date=2020|access-date=21 April 2021|ref=wyatt|mode=cs2|quote=Example}}';
        try {
            $output = $this->process_page($input)->parsed_text();
            $this->assertSame($output, $this->process_page($output)->parsed_text());
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('bot_curl_exec');
        }

        $this->assertSame(['https://api.crossref.org/v1/works/' . doi_encode(self::DOI) . '?mailto=' . CROSSREFUSERNAME], $requests);
        $template = $this->make_citation($output);
        $this->assertSame("who's who", $template->wikiname());
        $this->assertSame('Wyatt, Sir William Henry & family', $template->get('title'));
        $this->assertSame('2020', $template->get('date'));
        $this->assertSame('21 April 2021', $template->get('access-date'));
        $this->assertSame('wyatt', $template->get('ref'));
        $this->assertSame('cs2', $template->get('mode'));
        $this->assertSame('Example', $template->get('quote'));
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('website'));
    }

    #[DataProvider('bareDoiReferences')]
    public function testPageConvertsBareDoiInNamedReference(string $reference): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Crossref metadata');
        }
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('query_crossref_newapi', self::crossrefRecord());
        try {
            $output = $this->process_page('<ref name="wyatt">' . $reference . '</ref>')->parsed_text();
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('query_crossref_newapi');
        }
        $this->assertStringStartsWith('<ref name="wyatt">{{Who\'s Who', $output);
        $this->assertMatchesRegularExpression('~\|\s*id\s*=\s*U192476~', $output);
        $this->assertStringEndsWith('}}</ref>', $output);
    }

    /** @return array<string, array{string}> */
    public static function bareDoiReferences(): array {
        return [
            'bare identifier' => [self::DOI],
            'bare DOI URL' => ['https://doi.org/' . self::DOI],
            'bracketed legacy DOI URL' => ['[https://dx.doi.org/' . self::DOI . ']'],
            'citation with only a DOI URL' => ['{{cite web|url=https://doi.org/' . self::DOI . '}}'],
        ];
    }

    public function testKeepsExistingTitleAndStyleWithCanonicalProfileUrl(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Crossref metadata');
        }
        $input = '{{citation|doi=' . self::DOI . '|title=WYATT, Sir William Henry|mode=cs1' .
            '|url=https://www.ukwhoswho.com/view/article/oupww/whoswho/U192476|accessdate=2021-04-21}}';
        $template = $this->make_citation($input);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('query_crossref_newapi', self::crossrefRecord());
        try {
            $template->convert_whos_who_doi();
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('query_crossref_newapi');
        }
        $this->assertSame("who's who", $template->wikiname());
        $this->assertSame('WYATT, Sir William Henry', $template->get('title'));
        $this->assertSame('cs1', $template->get('mode'));
        $this->assertSame('2021-04-21', $template->get('accessdate'));
        $this->assertNull($template->get2('url'));
    }

    public function testKeepsTheCurrentPublisherLinkAndShortTitle(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Crossref metadata');
        }
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('query_crossref_newapi', self::crossrefRecord(['title' => [self::CROSSREF_TITLE]]));
        $input = '{{citation|doi=' . self::DOI . '|title=' . self::TITLE . '|url=' . self::PUBLISHER_URL .
            '|ref={{sfnref|Wyatt|2020}}|access-date=21 April 2021}}';
        try {
            $output = $this->process_page($input)->parsed_text();
            $this->assertSame($output, $this->process_page($output)->parsed_text());
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('query_crossref_newapi');
        }
        $template = $this->make_citation($output);
        $this->assertSame("who's who", $template->wikiname());
        $this->assertSame(self::TITLE, $template->get('title'));
        $this->assertSame(self::PUBLISHER_URL, $template->get('entry-url'));
        $this->assertNull($template->get2('url'));
        $this->assertSame('21 April 2021', $template->get('access-date'));
        $this->assertSame('cs2', $template->get('mode'));
        $this->assertStringContainsString('{{sfnref|Wyatt|2020}}', $output);
    }

    #[DataProvider('crossrefOutcomes')]
    public function testSharesCrossrefResultWithinPage(bool $available): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Crossref metadata');
        }
        $calls = 0;
        $record = $available ? self::crossrefRecord() : new stdClass();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('query_crossref_newapi', static function () use ($record, &$calls): object {
            $calls++;
            return $record;
        }, true);
        $first = $this->make_citation('{{cite web|doi=' . self::DOI . '}}');
        $second = $this->make_citation('{{cite web|doi=' . mb_strtolower(self::DOI) . '}}');
        $records = [];
        try {
            $first->convert_whos_who_doi($records);
            $second->convert_whos_who_doi($records);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('query_crossref_newapi');
        }
        $this->assertSame(1, $calls);
        $expected = $available ? "who's who" : 'cite web';
        $this->assertSame($expected, $first->wikiname());
        $this->assertSame($expected, $second->wikiname());
    }

    /** @return array<string, array{bool}> */
    public static function crossrefOutcomes(): array {
        return ['verified record' => [true], 'empty failure response' => [false]];
    }

    public function testCrossrefFailureLeavesCitationIntact(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Crossref metadata');
        }
        $input = '{{cite web|doi=' . self::DOI . '}}';
        $template = $this->make_citation($input);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('query_crossref_newapi', static function (): never {
            throw new RuntimeException('Simulated Crossref failure');
        }, true);
        try {
            $template->convert_whos_who_doi();
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('query_crossref_newapi');
        }
        $this->assertSame($input, $template->parsed_text());
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('unverifiedRecords')]
    public function testDeclinesUnverifiedMetadata(array $overrides, string $fields): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Crossref metadata');
        }
        $template = $this->make_citation('{{cite web|doi=' . self::DOI . $fields . '}}');
        $before = $template->parsed_text();
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('query_crossref_newapi', self::crossrefRecord($overrides));
        try {
            $template->convert_whos_who_doi();
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('query_crossref_newapi');
        }
        $this->assertSame($before, $template->parsed_text());
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function unverifiedRecords(): array {
        return [
            'missing DOI' => [['DOI' => null], ''],
            'different DOI' => [['DOI' => '10.1093/ww/9780199540884.013.U9'], ''],
            'wrong type' => [['type' => 'journal-article'], ''],
            'wrong container' => [['container-title' => ['Oxford Dictionary of National Biography']], ''],
            'ambiguous container' => [['container-title' => ["Who's Who", 'Who Was Who']], ''],
            'missing title' => [['title' => []], ''],
            'multiple titles' => [['title' => [self::TITLE, 'Another person']], ''],
            'title with template syntax' => [['title' => ['{{Example}}']], ''],
            'title with encoded parameter separator' => [['title' => ['Wyatt &#124; issue=2']], ''],
            'conflicting existing title' => [[], '|title=An unrelated article about marine life'],
            'conflicting existing container' => [[], '|website=Another Encyclopedia'],
            'comment-only title' => [[], '|title=<!-- Editor will fill this -->'],
        ];
    }

    #[DataProvider('unsupportedCitations')]
    public function testDeclinesUnsafeInputBeforeLookup(string $input): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub Crossref metadata');
        }
        $calls = 0;
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('query_crossref_newapi', static function () use (&$calls): object {
            $calls++;
            return self::crossrefRecord();
        }, true);
        try {
            $template = $this->make_citation($input);
            $before = $template->parsed_text();
            $template->convert_whos_who_doi();
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('query_crossref_newapi');
        }
        $this->assertSame(0, $calls);
        $this->assertSame($before, $template->parsed_text());
    }

    /** @return array<string, array{string}> */
    public static function unsupportedCitations(): array {
        $citation = '{{cite web|doi=' . self::DOI;
        return [
            'unrelated Oxford DOI' => ['{{cite web|doi=10.1093/ref:odnb/1}}'],
            'extra DOI path' => [$citation . '/extra}}'],
            'unsupported template' => ['{{cite book|doi=' . self::DOI . '}}'],
            'different URL' => [$citation . '|url=https://example.org/review}}'],
            'different resolver DOI' => [$citation . '|url=https://doi.org/10.1093/ww/9780199540884.013.U9}}'],
            'counterfeit resolver host' => ['{{cite web|url=https://doi.org.example.com/' . self::DOI . '}}'],
            'different profile ID' => [$citation . '|url=https://www.ukwhoswho.com/view/article/oupww/whoswho/U9}}'],
            'different modern profile ID' => [$citation . '|url=' . str_replace('e-192476', 'e-9', self::PUBLISHER_URL) . '}}'],
            'lookalike modern publisher host' => [$citation . '|url=' . str_replace('.com/', '.com.example.org/', self::PUBLISHER_URL) . '}}'],
            'archive metadata' => [$citation . '|archive-url=https://web.archive.org/example}}'],
            'unknown parameter' => [$citation . '|custom=value}}'],
            'duplicate DOI' => [$citation . '|doi=' . self::DOI . '}}'],
            'explicit ID' => [$citation . '|id=another-id}}'],
        ];
    }
}
