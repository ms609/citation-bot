<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class TaxonCitationConversionTest extends testBaseClass {
    #[DataProvider('supportedUrls')]
    public function testWrapsCanonicalTaxonRecord(string $url, string $name, string $id, ?string $database): void {
        $template = $this->make_citation("{{Cite web|url=" . $url . "|title=''Rumex'' L.}}");

        $this->assertTrue($template->convert_taxon_url());
        $this->assertSame($name, $template->wikiname());
        $this->assertSame($id, $template->get('id'));
        $this->assertSame($database, $template->get2('1'));
        $this->assertSame($url, $template->get('url'));
        $this->assertSame("''Rumex'' L.", $template->get('title'));
        $output = $template->parsed_text();
        $this->assertFalse($template->convert_taxon_url());
        $this->assertSame($output, $template->parsed_text());
    }

    /** @return array<string, array{string, string, string, ?string}> */
    public static function supportedUrls(): array {
        return [
            'GBIF species' => ['https://www.gbif.org/species/2888942', 'cite gbif', '2888942', null],
            'GBIF genus record without www' => ['https://gbif.org/species/2888942', 'cite gbif', '2888942', null],
            'GBIF uppercase host and trailing slash' => ['http://WWW.GBIF.ORG/species/12345/', 'cite gbif', '12345', null],
            'POWO record' => [
                'https://powo.science.kew.org/taxon/urn:lsid:ipni.org:names:30003057-2', 'cite taxon', '30003057-2', 'POWO',
            ],
            'POWO record with trailing slash' => [
                'http://powo.science.kew.org/taxon/urn:lsid:ipni.org:names:77178919-1/', 'cite taxon', '77178919-1', 'POWO',
            ],
            'IPNI record' => ['https://www.ipni.org/n/30149252-2', 'cite taxon', '30149252-2', 'IPNI'],
            'IPNI record without www' => ['https://ipni.org/n/30003057-2/', 'cite taxon', '30003057-2', 'IPNI'],
        ];
    }

    #[DataProvider('pageCitations')]
    public function testPageConversionPreservesCitationFields(string $url, string $wrapper): void {
        $fields = [
            'url' => $url,
            'title' => "''Example taxon''",
            'website' => 'Taxonomic database',
            'publisher' => 'Example institution',
            'access-date' => '19 March 2019',
            'archive-url' => 'https://web.archive.org/web/20210101000000/' . $url,
            'archive-date' => '1 January 2021',
            'url-status' => 'live',
            'mode' => 'cs2',
            'ref' => 'example-taxon',
            'quote' => 'A taxonomic note',
        ];
        $input = '{{cite web';
        foreach ($fields as $name => $value) {
            $input .= '|' . $name . '=' . $value;
        }
        $input .= '}}';
        $result = $this->process_page($input)->parsed_text();
        $template = $this->make_citation($result);

        $this->assertSame($wrapper, $template->wikiname());
        foreach ($fields as $name => $value) {
            $this->assertSame($value, $template->get($name), $name);
        }
        $this->assertSame($result, $this->process_page($result)->parsed_text());
    }

    /** @return array<string, array{string, string}> */
    public static function pageCitations(): array {
        return [
            'GBIF' => ['https://www.gbif.org/species/2888942', 'cite gbif'],
            'BioRef via POWO' => ['https://powo.science.kew.org/taxon/urn:lsid:ipni.org:names:30003057-2', 'cite taxon'],
            'BioRef via IPNI' => ['https://www.ipni.org/n/30149252-2', 'cite taxon'],
        ];
    }

    #[DataProvider('pageCitations')]
    public function testKeepsOriginalTitleMarkup(string $url, string $wrapper): void {
        $title = "''Rumex'' L.:";
        $template = $this->make_citation('{{cite web|url=' . $url . '|title=' . $title . '}}');

        $this->assertTrue($template->convert_taxon_url());
        $this->assertSame($wrapper, $template->wikiname());
        $this->assertSame($title, $template->get('title'));
    }

    #[DataProvider('pageCitations')]
    public function testWikipediaRendersConvertedCitation(string $url, string $wrapper): void {
        if (getenv('CITATION_BOT_TEST_WIKI_RENDER') !== '1') {
            $this->markTestSkipped('Set CITATION_BOT_TEST_WIKI_RENDER=1 to run the live Wikipedia parser check');
        }
        $archive = 'https://web.archive.org/web/20210101000000/' . $url;
        $input = '{{cite web|url=' . $url . '|title=Rumex specimen|ref=taxon-regression' .
            '|archive-url=' . $archive . '|archive-date=1 January 2021|url-status=live|quote=Taxonomic note}}';
        $output = $this->process_page($input)->parsed_text();
        $this->assertSame($wrapper, $this->make_citation($output)->wikiname());

        $query = http_build_query([
            'action' => 'parse', 'format' => 'json', 'formatversion' => 2,
            'title' => 'Template:Sandbox', 'contentmodel' => 'wikitext', 'prop' => 'text', 'text' => $output,
        ], '', '&', PHP_QUERY_RFC3986);
        $ch = bot_curl_init(0.75, [
            CURLOPT_URL => 'https://en.wikipedia.org/w/api.php?' . $query,
            CURLOPT_FAILONERROR => true,
        ], 512 * 1024);
        $body = bot_curl_exec($ch);
        $this->assertNotSame('', $body, 'Wikipedia parser request failed: ' . curl_error($ch));
        $response = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        $html = $response['parse']['text'] ?? null;
        $this->assertIsString($html);
        $this->assertDoesNotMatchRegularExpression('~class="(?:[^"]* )?(?:error|cs1-visible-error)(?: [^"]*)?"~', $html);
        $this->assertStringContainsString('id="taxon-regression"', $html);
        $this->assertStringContainsString('href="' . $url . '"', $html);
        $this->assertStringContainsString('href="' . $archive . '"', $html);
        $this->assertStringContainsString('Rumex specimen', $html);
        $this->assertStringContainsString('Taxonomic note', $html);
    }

    #[DataProvider('pageCitations')]
    public function testMapsAliasesThatWouldConflictWithWrapperDefaults(string $url, string $wrapper): void {
        $input = '{{cite web|url=' . $url . '|title=Rumex|work=Local catalogue|lang=fr}}';
        $result = $this->process_page($input)->parsed_text();
        $template = $this->make_citation($result);

        $this->assertSame($wrapper, $template->wikiname());
        $this->assertSame('Local catalogue', $template->get('website'));
        $this->assertSame('fr', $template->get('language'));
        $this->assertNull($template->get2('work'));
        $this->assertNull($template->get2('lang'));
        $this->assertSame($result, $this->process_page($result)->parsed_text());
    }

    #[DataProvider('unsupportedCitations')]
    public function testDeclinesUnmappableCitation(string $citation): void {
        $template = $this->make_citation($citation);
        $before = $template->parsed_text();

        $this->assertFalse($template->convert_taxon_url());
        $this->assertSame($before, $template->parsed_text());
    }

    /** @return array<string, array{string}> */
    public static function unsupportedCitations(): array {
        $prefix = '{{cite web|title=Rumex|url=';
        $gbif = 'https://www.gbif.org/species/2888942';
        return [
            'GBIF occurrence' => [$prefix . 'https://www.gbif.org/occurrence/2888942}}'],
            'GBIF dataset' => [$prefix . 'https://www.gbif.org/dataset/2888942}}'],
            'GBIF search' => [$prefix . 'https://www.gbif.org/species/search?q=Rumex}}'],
            'GBIF subpage' => [$prefix . $gbif . '/references}}'],
            'noncanonical query' => [$prefix . $gbif . '?filter=example}}'],
            'noncanonical fragment' => [$prefix . $gbif . '#data}}'],
            'zero identifier' => [$prefix . 'https://www.gbif.org/species/0}}'],
            'wrong host' => [$prefix . 'https://www.gbif.org.example.com/species/2888942}}'],
            'userinfo' => [$prefix . 'https://www.gbif.org@example.com/species/2888942}}'],
            'different scheme' => [$prefix . 'ftp://www.gbif.org/species/2888942}}'],
            'POWO search' => [$prefix . 'https://powo.science.kew.org/results?q=Rumex}}'],
            'unsupported POWO identifier' => [$prefix . 'https://powo.science.kew.org/taxon/something}}'],
            'IPNI author record' => [$prefix . 'https://www.ipni.org/a/12345-1}}'],
            'IPNI malformed identifier' => [$prefix . 'https://www.ipni.org/n/12345}}'],
            'missing title' => ['{{cite web|url=' . $gbif . '}}'],
            'comment-only title' => ['{{cite web|url=' . $gbif . '|title=<!-- pending -->}}'],
            'known error title with an archive' => ['{{cite web|url=' . $gbif . '|title=Page not found' .
                '|archive-url=https://web.archive.org/web/20210101000000/' . $gbif . '|archive-date=1 January 2021}}'],
            'known interstitial title' => ['{{cite web|url=' . $gbif . '|title=Validate User}}'],
            'known challenge title' => [$prefix . $gbif . '|title=Checking your browser}}'],
            'already has an identifier' => [$prefix . $gbif . '|id=unrelated}}'],
            'duplicate URL' => [$prefix . $gbif . '|url=https://example.com/another-record}}'],
            'duplicate website aliases' => [$prefix . $gbif . '|website=One|work=Two}}'],
            'duplicate language aliases' => [$prefix . $gbif . '|language=en|lang=fr}}'],
            'unsupported field' => [$prefix . $gbif . '|custom=keep this}}'],
            'template wrapper control word' => [$prefix . $gbif . '|quote=unset}}'],
            'existing specialized citation' => ['{{Cite GBIF|url=' . $gbif . '|title=Rumex|id=2888942}}'],
            'different source template' => ['{{cite journal|url=' . $gbif . '|title=Rumex}}'],
        ];
    }
}
