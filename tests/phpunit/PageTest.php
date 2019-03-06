<?php

/*
 * Tests for Page.php, called from expandFns.php.
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class PageTest extends testBaseClass {

  public function testPageChangeSummary() {
      $expanded = $this->expand_citation('{{cite journal|url=https://academic.oup.com/zoolinnean/advance-article-abstract/doi/10.1093/zoolinnean/zly047/5049994|doi=10.1093/zoolinnean/zly047|title=Phylogeny and biogeography of the endemic Hemidactylus geckos of the Indian subregion suggest multiple dispersals from Peninsular India to Sri Lanka|journal=Zoological Journal of the Linnean Society|year=2018|last1=Lajmi|first1=Aparna|last2=Bansal|first2=Rohini|last3=Giri|first3=Varad|last4=Karanth|first4=Praveen}}');;
      $this->assertNull($expanded->get('url'));
  }

}
