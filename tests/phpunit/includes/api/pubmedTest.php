<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class pubmedTest extends testBaseClass {
    public function testPmidExpansion(): void {
        $this->sleep_pubmed();
        $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pubmed/1941451?dopt=AbstractPlus}}";
        $expanded = $this->prepare_citation($text);
        $this->assertSame('cite journal', $expanded->wikiname());
        $this->assertSame('1941451', $expanded->get2('pmid'));
    }

    public function testGetPMIDwitNoDOIorJournal(): void {  // Also has evil colon in the name.   Use wikilinks for code coverage reason
        $this->sleep_pubmed();
        $text = '{{cite journal|title=ISiCLE: A Quantum Chemistry Pipeline for Establishing in Silico Collision Cross Section Libraries|volume=[[91]]|issue=[[7|7]]|pages=4346|year=2019|last1=Colby}}';
        $template = $this->make_citation($text);
        find_pmid($template);
        $this->assertSame('30741529', $template->get2('pmid'));
    }

    public function testPmidIsZero(): void {
        $this->sleep_pubmed();
        $text = '{{cite journal|pmc=2676591}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('pmid'));
    }

    public function testPMCExpansion1(): void {
        $this->sleep_pubmed();
        $text = "{{Cite web | http://www.ncbi.nlm.nih.gov/pmc/articles/PMC154623/}}";
        $expanded = $this->prepare_citation($text);
        $this->assertSame('cite journal', $expanded->wikiname());
        $this->assertSame('154623', $expanded->get2('pmc'));
        $this->assertNull($expanded->get2('url'));
    }

    public function testPMCExpansion2(): void {
        $this->sleep_pubmed();
        $text = "{{Cite web | url = https://www.ncbi.nlm.nih.gov/pmc/articles/PMC2491514/pdf/annrcse01476-0076.pdf}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('cite web', $expanded->wikiname());
        $this->assertSame('https://www.ncbi.nlm.nih.gov/pmc/articles/PMC2491514/pdf/annrcse01476-0076.pdf', $expanded->get2('url'));
        $this->assertSame('2491514', $expanded->get2('pmc'));
    }

    public function testPMC2PMID(): void {
        $this->sleep_pubmed();
        $text = '{{cite journal|pmc=58796}}';
        $expanded = $this->process_citation($text);
        if ($expanded->get('pmid') === "") {
            sleep(2);
            $expanded = $this->process_citation($text);
        }
        $this->assertSame('11573006', $expanded->get2('pmid'));
    }

    public function testDoi2PMID(): void {
        $this->sleep_pubmed();
        $text = "{{cite journal|doi=10.1073/pnas.171325998}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('11573006', $expanded->get2('pmid'));
        $this->assertSame('58796', $expanded->get2('pmc'));
    }

    public function testZootaxaDOIPageRejection(): void {
        // Test that bot rejects DOI suffix as page number for Zootaxa articles
        // This is the exact case from the bug report
        $text = '{{Cite journal|journal=Zootaxa|volume=4963|issue=1|pages=1–10|doi=10.11646/zootaxa.4963.1.1}}';
        $template = $this->make_citation($text);
        
        // Try to add the bad page data (DOI suffix) that PMID would return
        $result = $template->add_if_new('pages', 'zootaxa.4963.1.1', 'entrez');
        
        // Should reject the DOI suffix and keep the correct pages
        $this->assertFalse($result);
        $this->assertSame('1–10', $template->get2('pages'));
    }

    public function testPhytotaxaDOIPageRejection(): void {
        // Test that bot also rejects DOI suffix as page number for Phytotaxa articles
        $text = '{{Cite journal|journal=Phytotaxa|volume=260|issue=2|pages=123–130|doi=10.11646/phytotaxa.260.2.3}}';
        $template = $this->make_citation($text);
        
        // Try to add the bad page data (DOI suffix) that PMID might return
        $result = $template->add_if_new('pages', 'phytotaxa.260.2.3', 'entrez');
        
        // Should reject the DOI suffix and keep the correct pages
        $this->assertFalse($result);
        $this->assertSame('123–130', $template->get2('pages'));
    }
}
