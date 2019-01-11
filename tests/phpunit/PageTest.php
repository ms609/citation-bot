<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testUrlReferences() {
      $page = $this->process_page("URL reference test 1 <ref name='bob'>http://doi.org/10.1007/s12668-011-0022-5< / ref>\n Second reference: \n<ref >  [https://www.ncbi.nlm.nih.gov/pmc/articles/PMC3705692/] </ref> URL reference test 1");
      $this->assertEquals("URL reference test 1 <ref name='bob'>{{Cite journal |doi = 10.1007/s12668-011-0022-5|title = Reoccurring Patterns in Hierarchical Protein Materials and Music: The Power of Analogies|journal = Bionanoscience|volume = 1|issue = 4|pages = 153–161|year = 2011|last1 = Giesa|first1 = Tristan|last2 = Spivak|first2 = David I.|last3 = Buehler|first3 = Markus J.|arxiv = 1111.5297}}< / ref>\n Second reference: \n<ref >{{Cite journal |pmc = 3705692|year = 2013|last1 = Mahajan|first1 = P. T.|title = Indian religious concepts on sexuality and marriage|journal = Indian Journal of Psychiatry|volume = 55|issue = Suppl 2|pages = S256–S262|last2 = Pimple|first2 = P.|last3 = Palsetia|first3 = D.|last4 = Dave|first4 = N.|last5 = De Sousa|first5 = A.|pmid = 23858264|doi = 10.4103/0019-5545.105547}}</ref> URL reference test 1", $page->parsed_text());
      $page = $this->process_page(" text <ref name='dog' > 10.1063/1.2263373 </ref>");
      $this->assertTrue((boolean) strpos($page->parsed_text(), 'title'));
      $page = $this->process_page(" text <ref name='dog' >[http://doi.org/10.1007/s12668-011-0022-5 http://doi.org/10.1007/s12668-011-0022-5]</ref>");
      $this->assertTrue((boolean) strpos($page->parsed_text(), 'title'));
  }
}
