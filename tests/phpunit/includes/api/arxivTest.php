<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class arxivTest extends testBaseClass {

    public function testArxivDateUpgradeSeesDate1(): void {
        $text = '{{Cite journal|date=September 2010|doi=10.1016/j.physletb.2010.08.018|arxiv=1006.4000}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('September 2010', $expanded->get2('date'));
        $this->assertNull($expanded->get2('year'));
    }

    public function testGetBadDoiFromArxiv(): void { // If this DOI starts working or arXiv removes it, then this test will fail and not cover code anymore
        $text = '{{citation |arxiv=astro-ph/9708005 |last1=Steeghs |first1=D. |last2=Harlaftis |first2=E. T. |last3=Horne |first3=Keith |title=Spiral structure in the accretion disc of the binary IP Pegasi |year=1997  |doi= |doi-broken-date= }}';
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

}
