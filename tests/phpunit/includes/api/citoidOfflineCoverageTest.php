<?php

declare(strict_types=1);

/*
 * Deterministic Citoid/Zotero edge-case coverage.
 * No network access, rate-limit configuration, or credentials are required.
 */

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\Group;

#[Group('citoid-regression')]
final class citoidOfflineCoverageTest extends testBaseClass {
    /**
     * Exercise the complete malformed-field guard before metadata reaches a
     * citation template. Invalid data must not silently become citation text.
     */
    public function testMalformedZoteroFieldsDoNotAlterCitation(): void {
        foreach ([
            'title', 'publicationTitle', 'bookTitle', 'subject', 'caseName',
            'nameOfAct', 'DOI', 'extra', 'date', 'ISBN', 'issue', 'pages',
            'itemType', 'publisher', 'volume', 'series', 'rights',
            'university', 'thesisType',
        ] as $field) {
            $citation = '{{cite web|id=}}';
            $template = $this->make_citation($citation);
            $item = (object) [
                'title' => 'Offline validation',
                'itemType' => 'webpage',
            ];
            $item->{$field} = ['not', 'a scalar'];
            Zotero::process_zotero_response(
                json_encode([$item], JSON_THROW_ON_ERROR),
                $template,
                'https://example.test/offline',
                0
            );
            $this->assertSame($citation, $template->parsed_text(), 'Rejected field: ' . $field);
        }
    }

    public function testNestedMalformedZoteroFieldsDoNotAlterCitation(): void {
        $overrides = [
            ['identifiers' => ['unexpected']],
            ['identifiers' => (object) ['doi' => ['not scalar']]],
            ['author' => 'not an array'],
            ['author' => ['not an array entry']],
            ['author' => [['a' => (object) ['not' => 'scalar']]]],
            ['creators' => 'not an array'],
            ['creators' => ['not an object entry']],
            ['creators' => [(object) ['creatorType' => ['bad']]]],
            ['creators' => [(object) ['firstName' => ['bad']]]],
            ['creators' => [(object) ['lastName' => ['bad']]]],
        ];
        foreach ($overrides as $index => $fields) {
            $citation = '{{cite web|id=}}';
            $template = $this->make_citation($citation);
            $item = (object) array_merge([
                'title' => 'Offline validation',
                'itemType' => 'webpage',
            ], $fields);
            Zotero::process_zotero_response(
                json_encode([$item], JSON_THROW_ON_ERROR),
                $template,
                'https://example.test/offline',
                0
            );
            $this->assertSame($citation, $template->parsed_text(), 'Rejected nested payload: ' . $index);
        }
    }

    /** A scalar response is not a usable Citoid citation item. */
    public function testNonItemResponsesDoNotAlterCitation(): void {
        foreach ([
            'Internal Server Error',
            'Remote page not found',
            'No items returned from any translator',
            'An error occurred during translation. Please check translation with the Zotero client.',
            '<html><title>502 Bad Gateway</title></html>',
            '<html>503 Service Temporarily Unavailable</html>',
            '<html><title>Wikimedia Error</title></html>',
            'null', 'true', 'false', '123', '[]', '{}',
            json_encode(['type' => 'about:blank', 'title' => 'Bad gateway'], JSON_THROW_ON_ERROR),
        ] as $response) {
            $citation = '{{cite web|id=}}';
            $template = $this->make_citation($citation);
            Zotero::process_zotero_response($response, $template, 'https://example.test/offline', 0, true);
            $this->assertSame($citation, $template->parsed_text(), 'Rejected response: ' . $response);
        }
    }

    /**
     * A JSTOR stable identifier is an identity check, not just a title hint.
     * Citation metadata for a different item must remain untouched.
     */
    public function testJstorIdentityAndTitleChecksRejectMismatchedMetadata(): void {
        $url = 'https://www.jstor.org/stable/12345678';
        $payloads = [
            ['title' => 'Study of offline citations', 'itemType' => 'journalArticle', 'url' => 'https://www.jstor.org/stable/87654321'],
            ['title' => 'Different article', 'itemType' => 'journalArticle', 'url' => $url],
            ['title' => 'Study of offline citations', 'itemType' => 'journalArticle', 'url' => ['bad']],
            ['title' => 'Study of offline citations', 'type' => 'article', 'url' => $url],
            ['title' => 'Study of offline citations', 'itemType' => '', 'url' => $url],
        ];
        foreach ($payloads as $payload) {
            $citation = '{{cite journal|title=Study of offline citations|url=' . $url . '}}';
            $template = $this->make_citation($citation);
            Zotero::process_zotero_response(
                json_encode([$payload], JSON_THROW_ON_ERROR), $template, $url, 0, true, false, true
            );
            $this->assertSame($citation, $template->parsed_text());
        }
    }

    /**
     * Metadata in `extra` is parsed locally and need not make an HTTP request.
     * Exercise independent extraction paths using inert citation identifiers.
     */
    public function testExtraMetadataExtractionWithoutLiveServices(): void {
        $payloads = [
            ['extra' => 'PMID: 12345678 PMCID: PMC7654321', 'field' => 'pmid', 'value' => '12345678'],
            ['extra' => 'PMID: 12345678, 12345678', 'field' => 'pmid', 'value' => '12345678'],
            ['extra' => 'OCLC: ocm12345678', 'field' => 'oclc', 'value' => '12345678'],
            ['extra' => 'OCLC: ocn12345678', 'field' => 'oclc', 'value' => '12345678'],
            ['extra' => 'Open Library ID: OL1234567M', 'field' => 'ol', 'value' => '1234567M'],
        ];
        foreach ($payloads as $payload) {
            $template = $this->make_citation('{{cite web|id=}}');
            Zotero::process_zotero_response(
                json_encode([(object) [
                    'title' => 'Offline identifier extraction',
                    'itemType' => 'webpage',
                    'extra' => $payload['extra'],
                ]], JSON_THROW_ON_ERROR),
                $template,
                'https://example.test/offline',
                0, false, true, true
            );
            $this->assertSame($payload['value'], $template->get2($payload['field']));
        }
    }

    /** Test title rejection without relying on an external Zotero server. */
    public function testUnusableTitlesCannotCreateMetadata(): void {
        foreach (['Not Found', 'Newstream', '404', '/404', '%%%%%%', '�' . str_repeat('�', 8)] as $title) {
            $citation = '{{cite web|id=}}';
            $template = $this->make_citation($citation);
            Zotero::process_zotero_response(
                json_encode([['title' => $title, 'itemType' => 'webpage']], JSON_THROW_ON_ERROR),
                $template,
                'https://example.test/offline',
                0, false, true, true
            );
            $this->assertSame($citation, $template->parsed_text());
        }
    }

    /** Verify response canonicalization without copying tainted raw bytes to a citation. */
    public function testCitoidRawResponseHandlingOfInvalidUtf8(): void {
        $trim = new ReflectionMethod(Zotero::class, 'trim_raw_response');
        $excerpt = new ReflectionMethod(Zotero::class, 'raw_response_excerpt');
        $this->assertSame('article', $trim->invoke(null, " \tarticle \n"));
        $this->assertSame('article', $excerpt->invoke(null, 'article more', 7));
        $binary = "\xff\x80A\xfe";
        $this->assertSame($binary, $excerpt->invoke(null, $binary, 4));
        $this->assertSame("\xff\x80A", $excerpt->invoke(null, $binary, 3));
    }

    /** A JSTOR DOI alias and its bare stable identifier must identify the same item. */
    public function testJstorStableIdCanonicalization(): void {
        $identifier = new ReflectionMethod(Zotero::class, 'jstor_stable_id');
        foreach ([
            'https://www.jstor.org/stable/123456' => '123456',
            'https://jstor.org/stable/10.2307/123456' => '123456',
            'https://www.jstor.org/stable/10.2307%2F123456' => '123456',
            'https://jstor.org/stable/123456?seq=1' => '123456',
        ] as $url => $expected) {
            $this->assertSame($expected, $identifier->invoke(null, $url));
        }
        foreach (['https://example.test/stable/123456', 'https://jstor.org/article/123456'] as $url) {
            $this->assertNull($identifier->invoke(null, $url));
        }
    }

    /**
     * Scalars supplied by Citoid must be cast consistently. This guard is
     * invoked directly to avoid any unnecessary template or network activity.
     */
    public function testScalarNormalizationAndCreatorFieldCasting(): void {
        $normalizer = new ReflectionMethod(Zotero::class, 'normalize_zotero_result');
        $item = (object) [
            'title' => 42,
            'issue' => 3,
            'volume' => 7,
            'rights' => true,
            'identifiers' => (object) ['doi' => 123],
            'author' => [[0 => 123, 1 => 'Example']],
            'creators' => [(object) ['creatorType' => 99, 'firstName' => true, 'lastName' => 456]],
        ];
        $this->assertTrue($normalizer->invoke(null, $item, 'https://example.test/offline'));
        $this->assertSame('42', $item->title);
        $this->assertSame('3', $item->issue);
        $this->assertSame('7', $item->volume);
        $this->assertSame('1', $item->rights);
        $this->assertSame('123', $item->identifiers->doi);
        $this->assertSame('123', $item->author[0][0]);
        $this->assertSame('99', $item->creators[0]->creatorType);
        $this->assertSame('1', $item->creators[0]->firstName);
        $this->assertSame('456', $item->creators[0]->lastName);
    }
}
