<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

final class JstorTest extends testBaseClass {
    public function testJstor1(): void {
        $text = "{{cite journal|url=https://jstor.org/stable/832414?seq=1234}}";
        $template = $this->make_citation($text);
        expand_by_jstor($template);
        $this->assertSame('832414', $template->get2('jstor'));
    }

    public function testJstor2(): void {
        $text = "{{cite journal|jstor=832414?seq=1234}}";
        $template = $this->make_citation($text);
        expand_by_jstor($template);
        $this->assertNull($template->get2('url'));
    }

    public function testJstor3(): void {
        $text = "{{cite journal|jstor=123 123}}";
        $template = $this->make_citation($text);
        expand_by_jstor($template);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testJstor4(): void {
        $text = "{{cite journal|jstor=i832414}}";
        $template = $this->make_citation($text);
        expand_by_jstor($template);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testJstor5(): void {
        $text = "{{cite journal|jstor=4059223|title=This is not the right title}}";
        $template = $this->make_citation($text);
        expand_by_jstor($template);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testJstorStableUrlFragmentIsIgnored(): void {
        $template = $this->make_citation('{{cite journal|url=https://www.jstor.org/stable/4059223#metadata_info_tab_contents}}');
        expand_by_jstor($template);
        $this->assertSame('4059223', $template->get2('jstor'));
    }

    public function testJstorCitoidRejectsProblemJson(): void {
        foreach (['{"type":"about:blank","title":"Bad Request"}', '[{"title":"Bad Request"}]'] as $response) {
            $template = $this->make_citation('{{cite journal|jstor=4059223}}');
            Zotero::process_zotero_response($response, $template, 'https://www.jstor.org/stable/4059223', 0, true, true, true);
            $this->assertNull($template->get2('title'));
        }
    }

    public function testJstorCitoidRejectsJournalAsArticleTitle(): void {
        $template = $this->make_citation('{{cite journal|jstor=4059223|title=Example Journal}}');
        $response = '[{"itemType":"journalArticle","title":"Different Article","publicationTitle":"Example Journal","volume":"12","creators":[{"creatorType":"author","firstName":"Jane","lastName":"Smith"}]}]';
        Zotero::process_zotero_response($response, $template, 'https://www.jstor.org/stable/4059223', 0, true, true, true);
        $this->assertSame('Example Journal', $template->get2('title'));
        $this->assertNull($template->get2('last1'));
        $this->assertNull($template->get2('volume'));
    }

    public function testJstorCitoidIdentityGuard(): void {
        foreach (['https://www.jstor.org/stable/99999', 'https://www.jstor.org/stable/4059223', 'https://www.jstor.org/stable/10.2307/4059223', 'https://publisher.example/article'] as $returned_url) {
            $template = $this->make_citation('{{cite journal|jstor=4059223}}');
            $response = json_encode(['itemType' => 'journalArticle', 'url' => $returned_url, 'title' => 'Verified Article', 'volume' => '12']);
            Zotero::process_zotero_response($response, $template, 'https://www.jstor.org/stable/4059223', 0, true, true, true);
            $this->assertSame($returned_url === 'https://www.jstor.org/stable/99999' ? null : 'Verified Article', $template->get2('title'));
        }
    }

    public function testJstorCitoidDoiWithoutResolverRequest(): void {
        foreach (['10.0001/example.valid', 'not-a-doi', '10.0001/a|injected=1', '10.0001/a{bad}'] as $doi) {
            $template = $this->make_citation('{{cite journal|jstor=4059223}}');
            $response = json_encode(['itemType' => 'journalArticle', 'title' => 'Verified Article', 'DOI' => $doi]);
            Zotero::process_zotero_response($response, $template, 'https://www.jstor.org/stable/4059223', 0, true, true, true);
            $this->assertSame(doi_valid($doi) && preg_match('~[|{}<>]~', $doi) === 0 ? $doi : null, $template->get2('doi'));
            $this->assertSame('Verified Article', $template->get2('title'));
        }
    }

    public function testJstorCitoidRejectsMalformedIdentityField(): void {
        $template = $this->make_citation('{{cite journal|jstor=4059223}}');
        $response = '[{"itemType":"journalArticle","url":{"unexpected":"object"},"title":"A Title"}]';
        Zotero::process_zotero_response($response, $template, 'https://www.jstor.org/stable/4059223', 0, true, true, true);
        $this->assertNull($template->get2('title'));
    }

    public function testJstorBookAndChapterMustBothAgree(): void {
        $template = $this->make_citation('{{cite book|jstor=4059223|title=Example Book|chapter=Original Chapter}}');
        $response = '[{"itemType":"bookSection","title":"Different Chapter","bookTitle":"Example Book","publisher":"Wrong Publisher"}]';
        Zotero::process_zotero_response($response, $template, 'https://www.jstor.org/stable/4059223', 0, true, true, true);
        $this->assertSame('Original Chapter', $template->get2('chapter'));
        $this->assertNull($template->get2('publisher'));
    }

    public function testJstorCitoidCreatorRoles(): void {
        $template = $this->make_citation('{{cite book|jstor=resrep24545}}');
        $response = json_encode(['itemType' => 'book', 'title' => 'Example Book', 'creators' => [
            ['creatorType' => 'contributor', 'firstName' => 'Jane', 'lastName' => 'Brown'],
            ['creatorType' => 'bookAuthor', 'firstName' => 'John', 'lastName' => 'Taylor'],
            ['creatorType' => 'editor', 'firstName' => 'Alice', 'lastName' => 'Jones'],
            ['creatorType' => 'author', 'firstName' => 'Mary', 'lastName' => 'Smith'],
        ]]);
        Zotero::process_zotero_response($response, $template, 'https://www.jstor.org/stable/resrep24545', 0, true, true, true);
        $this->assertSame('Smith', $template->get2('last1'));
        $this->assertNull($template->get2('last2'));
        $this->assertStringContainsString('Jones', (string) $template->get2('editor1'));
    }

    public function testNonJstorContributorBehaviorIsPreserved(): void {
        $template = $this->make_citation('{{cite journal}}');
        $response = json_encode(['itemType' => 'journalArticle', 'title' => 'Example Article', 'creators' => [
            ['creatorType' => 'contributor', 'firstName' => 'Jane', 'lastName' => 'Brown'],
        ]]);
        Zotero::process_zotero_response($response, $template, 'https://example.org/article', 0);
        $this->assertSame('Brown', $template->get2('last1'));

        $jstor = $this->make_citation('{{cite journal|jstor=4059223}}');
        Zotero::process_zotero_response($response, $jstor, 'https://www.jstor.org/stable/4059223', 0, true, true, true);
        $this->assertNull($jstor->get2('last1'));
    }

    public function testJstorTranslationDoesNotPreventValidEnrichment(): void {
        $template = $this->make_citation('{{cite journal|jstor=4059223|title=Le titre original|trans-title=The Original Title}}');
        $response = '[{"itemType":"journalArticle","title":"Le titre original","publicationTitle":"Example Journal"}]';
        Zotero::process_zotero_response($response, $template, 'https://www.jstor.org/stable/4059223', 0, true, true, true);
        $this->assertSame('Example Journal', $template->get2('journal'));
        $this->assertSame('The Original Title', $template->get2('trans-title'));
    }

    public function testJstorTranslationOnlyDoesNotAuthorizeMismatchedMetadata(): void {
        $template = $this->make_citation('{{cite journal|jstor=4059223|trans-title=An Unrelated Translation}}');
        $response = '[{"itemType":"journalArticle","title":"Different Original Article","publicationTitle":"Example Journal"}]';
        Zotero::process_zotero_response($response, $template, 'https://www.jstor.org/stable/4059223', 0, true, true, true);
        $this->assertNull($template->get2('journal'));
        $this->assertSame('An Unrelated Translation', $template->get2('trans-title'));
    }

    public function testJstorZoteroTitleGuardRejectsMismatchedTitle(): void {
        $template = $this->make_citation(
            '{{cite journal|jstor=4059223|title=This is not the right title}}'
        );
        $response = <<<'JSON'
[
  {
    "itemType": "journalArticle",
    "title": "The Actual Article Title",
    "publicationTitle": "Example Journal",
    "date": "2020",
    "volume": "12",
    "issue": "3",
    "creators": [
      {
        "creatorType": "author",
        "firstName": "Alice",
        "lastName": "Smith"
      }
    ]
  }
]
JSON;

        Zotero::process_zotero_response(
            $response,
            $template,
            'https://www.jstor.org/stable/4059223',
            0,
            true
        );

        $this->assertSame('This is not the right title', $template->get2('title'));
        $this->assertNull($template->get2('journal'));
        $this->assertNull($template->get2('last1'));
    }

    public function testJstorZoteroTitleGuardAcceptsMatchingTitle(): void {
        $template = $this->make_citation(
            '{{cite journal|jstor=4059223|title=The Actual Article Title}}'
        );
        $response = <<<'JSON'
[
  {
    "itemType": "journalArticle",
    "title": "The Actual Article Title",
    "publicationTitle": "Example Journal",
    "date": "2020",
    "volume": "12",
    "issue": "3",
    "creators": [
      {
        "creatorType": "author",
        "firstName": "Alice",
        "lastName": "Smith"
      }
    ]
  }
]
JSON;

        Zotero::process_zotero_response(
            $response,
            $template,
            'https://www.jstor.org/stable/4059223',
            0,
            true
        );

        $this->assertSame('The Actual Article Title', $template->get2('title'));
        $this->assertSame('Example Journal', $template->get2('journal'));
        $this->assertSame('Smith', $template->get2('last1'));
        $this->assertSame('Alice', $template->get2('first1'));
        $this->assertSame('12', $template->get2('volume'));
        $this->assertSame('3', $template->get2('issue'));
    }

    public function testJstorZoteroReplacesKnownPlaceholderTitle(): void {
        $response = <<<'JSON'
[{"itemType":"journalArticle","title":"Verified Article Title","publicationTitle":"Example Journal","date":"2020","creators":[{"creatorType":"author","firstName":"Alice","lastName":"Smith"}]}]
JSON;
        foreach (['[No title found]', 'JSTOR'] as $placeholder) {
            $template = $this->make_citation('{{cite journal|jstor=4059223|title=' . $placeholder . '}}');
            Zotero::process_zotero_response(
                $response,
                $template,
                'https://www.jstor.org/stable/4059223',
                0,
                true,
                true,
                true
            );
            $this->assertSame('Verified Article Title', $template->get2('title'));
            $this->assertSame('Example Journal', $template->get2('journal'));
            $this->assertSame('Smith', $template->get2('last1'));
        }
    }

    public function testJstorZoteroBookSectionJson(): void {
        $template = $this->make_citation('{{cite book|jstor=j.ctt6wp6td.10}}');
        $response = <<<'JSON'
[
  {
    "itemType": "bookSection",
    "title": "Chapter Heading",
    "bookTitle": "Example Book",
    "date": "2019",
    "publisher": "Example Press",
    "creators": [
      {"creatorType":"author","firstName":"Example","lastName":"Book"},
      {"creatorType":"author","firstName":"Alice","lastName":"Verstraete"}
    ]
  }
]
JSON;
        Zotero::process_zotero_response(
            $response,
            $template,
            'https://www.jstor.org/stable/j.ctt6wp6td.10',
            0,
            true,
            true,
            true
        );
        $this->assertSame('Example Book', $template->get2('title'));
        $this->assertSame('Chapter Heading', $template->get2('chapter'));
        $this->assertSame('Verstraete', $template->get2('last1'));
        $this->assertSame('Alice', $template->get2('first1'));
        $this->assertNull($template->get2('last2'));
    }

    public function testJstorZoteroBookCreators(): void {
        $template = $this->make_citation('{{cite book|jstor=resrep24545}}');
        $response = <<<'JSON'
[{"itemType":"book","title":"Sample Monograph","creators":[{"creatorType":"author","firstName":"Jane","lastName":"Smith"}]}]
JSON;
        Zotero::process_zotero_response(
            $response,
            $template,
            'https://www.jstor.org/stable/resrep24545',
            0,
            true,
            true,
            true
        );
        $this->assertSame('Sample Monograph', $template->get2('title'));
        $this->assertSame('Smith', $template->get2('last1'));
        $this->assertSame('Jane', $template->get2('first1'));
    }

    public function testJstorZoteroBookRejectsMismatchedTitleAndAuthor(): void {
        $template = $this->make_citation('{{cite book|jstor=j.ctt6wp6td.10|title=Unrelated Title}}');
        $response = <<<'JSON'
[{"itemType":"bookSection","title":"Chapter Heading","bookTitle":"Example Book","creators":[{"creatorType":"author","firstName":"Alice","lastName":"Verstraete"}]}]
JSON;
        Zotero::process_zotero_response(
            $response,
            $template,
            'https://www.jstor.org/stable/j.ctt6wp6td.10',
            0,
            true,
            true,
            true
        );
        $this->assertSame('Unrelated Title', $template->get2('title'));
        $this->assertNull($template->get2('chapter'));
        $this->assertNull($template->get2('last1'));
    }

    public function testJstorZoteroBookCreatorsNotTrustedOnOtherSites(): void {
        $template = $this->make_citation('{{cite book|title=Sample Monograph}}');
        $response = <<<'JSON'
[{"itemType":"book","title":"Sample Monograph","creators":[{"creatorType":"author","firstName":"Jane","lastName":"Smith"}]}]
JSON;
        Zotero::process_zotero_response(
            $response,
            $template,
            'https://example.org/stable/resrep24545',
            0,
            true,
            true,
            true
        );
        $this->assertSame('Sample Monograph', $template->get2('title'));
        $this->assertNull($template->get2('last1'));
    }

    public function testJstorZoteroPlaceholderWithExistingAuthors(): void {
        $template = $this->make_citation('{{cite journal|jstor=3073767|title=[No title found]|author2=BAD|last1=Duh|first1=Dum}}');
        $response = <<<'JSON'
[{"itemType":"journalArticle","title":"Are Helionitronium Trications Stable?","publicationTitle":"Proceedings of the National Academy of Sciences of the United States of America","creators":[{"creatorType":"author","firstName":"Wolfgang","lastName":"Eisfeld"}]}]
JSON;
        Zotero::process_zotero_response(
            $response,
            $template,
            'https://www.jstor.org/stable/3073767',
            0,
            true,
            true,
            true
        );
        $this->assertSame('Are Helionitronium Trications Stable?', $template->get2('title'));
        $this->assertSame('Duh', $template->get2('last1'));
        $this->assertSame('Proceedings of the National Academy of Sciences of the United States of America', $template->get2('journal'));
    }

    public function testJstorGoofyRIS(): void {
        $this->require_live_jstor_ris();
        $text = "{{cite book| jstor=resrep24545| title=Safeguarding Digital Democracy Digital Innovation and Democracy Initiative Roadmap}}";
        $prepared = $this->process_citation($text);
        $this->assertSame('Kornbluh', $prepared->get2('last1'));
    }

    public function testJstorReportWithoutSuppliedTitle(): void {
        $this->require_live_jstor_ris();
        $text = "{{Cite book |jstor=resrep26423 }}";
        $prepared = $this->process_citation($text);

        $this->assertSame(
            'The War Comes Home: The Evolution of Domestic Terrorism in the United States',
            $prepared->get2('title')
        );
        $this->assertNull($prepared->get2('chapter'));
    }

    public function testJstorExpansion1(): void {
        $text = "{{Cite web | www.jstor.org/stable/pdfplus/1701972.pdf?&acceptTC=true|website=i found this online}}";
        $prepared = $this->prepare_citation($text);
        $this->assertSame('cite journal', $prepared->wikiname());
        $this->assertSame('1701972', $prepared->get2('jstor'));
        $this->assertNotNull($prepared->get2('website'));
    }

    public function testJstorExpansion2(): void {
        $text = "{{Cite journal | url=http://www.jstor.org/stable/10.2307/40237667|jstor=}}";
        $prepared = $this->prepare_citation($text);
        $this->assertSame('40237667', $prepared->get2('jstor'));
        $this->assertNull($prepared->get2('doi'));
        $this->assertSame(2, mb_substr_count($prepared->parsed_text(), 'jstor'));  // Verify that we do not have both jstor= and jstor=40237667.   Formerly testOverwriteBlanks()
    }

    public function testJstorExpansion4(): void {
        $this->require_live_jstor_ris();
        $text = '{{cite web | via = UTF8 characters from JSTOR | url = https://www.jstor.org/stable/27695659}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('Mórdha', $expanded->get2('last1'));
    }

    public function testJstorExpansion5(): void {
        $text = '{{cite journal | url = https://www-jstor-org.school.edu/stable/10.7249/mg1078a.10?seq=1#metadata_info_tab_contents }}';
        $expanded = $this->process_citation($text);
        $this->assertSame('10.7249/mg1078a.10', $expanded->get2('jstor'));
    }

    public function testRISJstorExpansion(): void {
        $this->require_live_jstor_ris();
        $text = "<ref name='jstor'>{{jstor|3073767}}</ref>"; // Check Page expansion too
        $page = $this->process_page($text);
        $expanded = $this->reference_to_template($page->parsed_text());
        $this->assertSame('Are Helionitronium Trications Stable?', $expanded->get2('title'));
        $this->assertSame('99', $expanded->get2('volume'));
        $this->assertSame('24', $expanded->get2('issue'));
        $this->assertSame('Francisco', $expanded->get2('last2'));
        $this->assertSame('Eisfeld', $expanded->get2('last1'));
        $this->assertSame('Proceedings of the National Academy of Sciences of the United States of America', $expanded->get2('journal'));
        $this->assertSame('15303–15307', $expanded->get2('pages'));
        // JSTOR gives up these, but we do not add since we get journal title and URL is simply jstor stable
        $this->assertNull($expanded->get2('publisher'));
        $this->assertNull($expanded->get2('issn'));
        $this->assertNull($expanded->get2('url'));
    }

    public function testDrop10_2307(): void {
        $text = "{{Cite journal | jstor=10.2307/40237667}}";  // This should get cleaned up in tidy
        $prepared = $this->prepare_citation($text);
        $this->assertSame('40237667', $prepared->get2('jstor'));
    }

    public function testExpansionJstorBook(): void {
        $this->require_live_jstor_ris();
        $text = '{{Cite journal|url=https://www.jstor.org/stable/j.ctt6wp6td.10}}';
        $expanded = $this->process_citation($text);
        if ($expanded->get2('last1') === null) {
            $this->markTestSkipped('Live JSTOR chapter metadata has no usable author; offline creator mapping is tested separately');
        }
        $this->assertSame('Verstraete', $expanded->get2('last1'));
    }

}
