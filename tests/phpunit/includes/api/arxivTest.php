<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

final class arxivTest extends testBaseClass {

    public function testArxivDateUpgradeSeesDate1(): void {
        $text = '{{Cite journal|date=September 2010|doi=10.1016/j.physletb.2010.08.018|arxiv=1006.4000}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('September 2010', $expanded->get2('date'));
        $this->assertNull($expanded->get2('year'));
    }

    public function testGetBadDoiFromArxiv(): void { // If this DOI starts working or arXiv removes it, then this test will fail and not cover code anymore
        $text = '{{citation |arxiv=astro-ph/9708005 |last1=Steeghs |first1=D. |last2=Harlaftis |first2=E. T. |last3=Horne |first3=Keith |title=Spiral structure in the accretion disc of the binary IP Pegasi |year=1997  |doi= |doi-broken-date= }}';
        sleep(2); // touchy test
        $prepared = $this->process_citation($text);
        if ($prepared->get2('doi') === null || $prepared->get2('doi') === '') {
            $this->markTestSkipped('arXiv API did not respond (rate limit or outage)');
        } else {
            $this->assertSame('10.1093/mnras/290.2.L28', $prepared->get2('doi'));
            $this->assertNotNull($prepared->get2('doi-broken-date')); // The DOI has to not work for this test to cover the code where a title and arxiv exist and a doi is found, but the doi does not add a title
        }
    }

    public function testArxivExpansion(): void {
        $text = "{{Cite web | http://uk.arxiv.org/abs/0806.0013}}"
                    . "{{Cite arxiv | eprint = 0806.0013 | class=forgetit|publisher=uk.arxiv}}"
                    . '{{Cite arxiv |arxiv=1609.01689 | title = Accelerating Nuclear Configuration Interaction Calculations through a Preconditioned Block Iterative Eigensolver|class=cs.NA | year = 2016| last1 = Shao| first1 = Meiyue | display-authors = etal}}'
                    . '{{cite arXiv|eprint=hep-th/0303241}}'; // tests line feeds
        sleep(4); // Doing several at once
        $expanded = $this->process_page($text);
        $templates = $expanded->extract_object('Template');
        if ($templates[0]->wikiname() !== 'cite journal') {
            $this->markTestSkipped('arXiv API did not respond (rate limit or outage)');
        }
        $this->assertSame('cite journal', $templates[0]->wikiname());
        $this->assertSame('0806.0013', $templates[0]->get2('arxiv'));
        $this->assertSame('cite journal', $templates[1]->wikiname());
        $this->assertSame('0806.0013', $templates[1]->get2('arxiv'));
        $this->assertNull($templates[1]->get2('class'));
        $this->assertNull($templates[1]->get2('eprint'));
        $this->assertNull($templates[1]->get2('publisher'));
        $this->assertSame('2016', $templates[2]->get2('year'));
        $this->assertSame('Pascual Jordan, his contributions to quantum mechanics and his legacy in contemporary local quantum physics', $templates[3]->get2('title'));
    }

    public function testArxivWithPmidConvertsToCiteJournal(): void {
        $text = '{{cite arxiv |last1=TestAuthor |first1=Test |title=Test title for pmid conversion |date=2023 |arxiv=9999.99999}}';
        $template = $this->make_citation($text);
        $template->prepare();
        $this->assertSame('cite arxiv', $template->wikiname());
        $this->assertTrue($template->add_if_new('pmid', '12345678'));
        $this->assertSame('cite journal', $template->wikiname());
        $this->assertSame('12345678', $template->get2('pmid'));
    }

    public function testArxivWithPmcConvertsToCiteJournal(): void {
        $text = '{{cite arxiv |last1=TestAuthor |first1=Test |title=Test title for pmc conversion |date=2023 |arxiv=9999.99998}}';
        $template = $this->make_citation($text);
        $template->prepare();
        $this->assertSame('cite arxiv', $template->wikiname());
        $this->assertTrue($template->add_if_new('pmc', '87654321'));
        $this->assertSame('cite journal', $template->wikiname());
        $this->assertSame('87654321', $template->get2('pmc'));
    }

    public function testArxivWithPmidStaysCiteJournalAfterFinalTidy(): void {
        $text = '{{cite arxiv |last1=TestAuthor |first1=Test |title=Test title for final_tidy pmid |date=2023 |arxiv=9999.99997}}';
        $template = $this->make_citation($text);
        $template->prepare();
        $this->assertTrue($template->add_if_new('pmid', '12345678'));
        $this->assertSame('cite journal', $template->wikiname());
        $template->final_tidy();
        $this->assertSame('cite journal', $template->wikiname());
        $this->assertSame('12345678', $template->get2('pmid'));
    }

    public function testArxivPublicationMetadataPreservesExistingMathTitle(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub arXiv and DOI metadata');
        }

        $math_title = 'The structure of isoperimetric bubbles on <math>\mathbb{R}^n</math> and <math>\mathbb{S}^n</math>';
        $citation = '{{citation|title=' . $math_title . '|arxiv=2205.09102}}';
        $atom = '<feed xmlns:arxiv="http://arxiv.org/schemas/atom"><entry>' .
            '<id>https://arxiv.org/abs/2205.09102</id><title>Isoperimetric bubbles</title>' .
            '<arxiv:doi>10.4310/ACTA.2025.v234.n1.a2</arxiv:doi></entry></feed>';

        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('bot_curl_exec', static function (CurlHandle $handle) use ($atom): string {
            $url = curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
            return is_string($url) && str_starts_with($url, 'https://export.arxiv.org/') ? $atom : '';
        }, true);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('curl_exec', false);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('sleep', 0);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('doi_works', true);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('doi_active', true);
        $published_title = 'The structure of isoperimetric bubbles on R^n and S^n';
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('query_crossref', new SimpleXMLElement(
            '<query><article_title>' . $published_title . '</article_title>' .
            '<journal_title>Acta Mathematica</journal_title><year>2025</year><volume>234</volume></query>'
        ));
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('query_crossref_newapi', (object) ['title' => [$published_title], 'type' => 'journal-article']);
        try {
            $expanded = $this->process_page($citation)->parsed_text();
            $this->assertStringContainsString('title=' . $math_title, $expanded);
            $this->assertMatchesRegularExpression('~journal\s*=\s*Acta Mathematica~', $expanded);
            $this->assertSame($expanded, $this->process_page($expanded)->parsed_text());
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('query_crossref_newapi');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('query_crossref');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('doi_active');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('doi_works');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('sleep');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('curl_exec');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('bot_curl_exec');
        }
    }

    public function testArxivDoesNotReplaceCitedConferenceVersion(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub arXiv and DOI metadata');
        }

        $paper = 'Orthogonal dissection into few rectangles';
        $proceedings = 'Proceedings of the 34th Canadian Conference on Computational Geometry';
        $doi = '10.1007/s00454-023-00614-w';
        $atom = '<feed xmlns:arxiv="http://arxiv.org/schemas/atom"><entry>' .
            '<id>https://arxiv.org/abs/2206.10675</id><title>' . $paper . '</title>' .
            '<arxiv:doi>' . $doi . '</arxiv:doi></entry></feed>';
        $published = new SimpleXMLElement(
            '<query><article_title>Orthogonal Dissection into Few Rectangles</article_title>' .
            '<journal_title>Discrete &amp; Computational Geometry</journal_title>' .
            '<year>2025</year><volume>73</volume></query>'
        );
        $stubs = [
            'bot_curl_exec' => static function (CurlHandle $handle) use ($atom): string {
                $url = curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
                return is_string($url) && str_starts_with($url, 'https://export.arxiv.org/') ? $atom : '';
            },
            'curl_exec' => false,
            'sleep' => 0,
            'doi_works' => true,
            'doi_active' => true,
            'query_crossref' => $published,
            'query_crossref_newapi' => (object) ['type' => 'journal-article'],
        ];
        foreach ($stubs as $name => $value) {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_set_return($name, $value, $value instanceof Closure);
        }
        try {
            foreach (['citation' => 'contribution', 'cite book' => 'chapter'] as $name => $parameter) {
                $conference = $this->process_citation(
                    '{{' . $name . '|last=Eppstein|first=David|arxiv=2206.10675|' .
                    $parameter . '=' . $paper . '|title=' . $proceedings . '|year=2022}}'
                );
                $this->assertSame($paper, $conference->get($parameter));
                $this->assertSame($proceedings, $conference->get('title'));
                $this->assertSame('2022', $conference->get('year'));
                $this->assertSame('2206.10675', $conference->get('arxiv'));
                $this->assertNull($conference->get2('doi'));
                $this->assertNull($conference->get2('journal'));
            }

            $preprint = $this->process_citation('{{cite arXiv|eprint=2206.10675}}');
            $this->assertSame($doi, $preprint->get('doi'));
            $this->assertSame('Discrete & Computational Geometry', $preprint->get('journal'));
        } finally {
            foreach (array_keys($stubs) as $name) {
                /** @psalm-suppress UnusedFunctionCall */
                uopz_unset_return($name);
            }
        }
    }

    public function testArxivEntriesAreMatchedByIdentifierNotResponseOrder(): void {
        $xml = simplexml_load_string(
            '<feed>' .
            '<entry><id>https://arxiv.org/abs/2222.00002v3</id><title>Second</title></entry>' .
            '<entry><id>https://arxiv.org/abs/1111.00001v1</id><title>First</title></entry>' .
            '</feed>'
        );
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);

        $entries = arxiv_entries_for_ids(
            $xml,
            ['1111.00001', 'missing.00000', '2222.00002v3', '2222.00002']
        );

        $this->assertSame('First', (string) $entries[0]?->title);
        $this->assertNull($entries[1]);
        $this->assertSame('Second', (string) $entries[2]?->title);
        $this->assertSame('Second', (string) $entries[3]?->title);
    }

    public function testArxivEntryMatcherIgnoresMalformedIds(): void {
        $xml = simplexml_load_string(
            '<feed>' .
            '<entry><id>not-an-arxiv-url</id><title>Bad</title></entry>' .
            '</feed>'
        );
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);

        $entries = arxiv_entries_for_ids($xml, ['not-an-arxiv-url']);
        $this->assertSame([null], $entries);
    }

    public function testArxivXmlParserRejectsMalformedResponses(): void {
        $this->assertNull(parse_arxiv_xml_response('not xml'));
        $this->assertNull(parse_arxiv_xml_response('<feed><entry>'));
        $this->assertNull(parse_arxiv_xml_response("<feed>\xFF</feed>"));
        $this->assertNull(parse_arxiv_xml_response('<html><body>upstream error</body></html>'));
    }

    public function testArxivXmlParserHandlesNamespacedFields(): void {
        $xml = parse_arxiv_xml_response(
            '<feed xmlns:arxiv="http://arxiv.org/schemas/atom">' .
            '<entry><arxiv:doi>10.1000/test</arxiv:doi></entry>' .
            '</feed>'
        );
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
        $this->assertSame('10.1000/test', (string) $xml->entry->arxivdoi);
    }

}
