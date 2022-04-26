<?php
declare(strict_types=1);

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../testBaseClass.php';
 
final class TemplateTest extends testBaseClass {

  public function testRemoveURLthatRedirects() : void { // This URL is a redirect -- tests code that does that
    $text = '{{cite journal|doi=10.1021/acs.analchem.8b04567|url=https://shortdoi.org/gf7sqt|pmid=30741529|pmc=6526953|title=ISiCLE: A Quantum Chemistry Pipeline for Establishing in Silico Collision Cross Section Libraries|journal=Analytical Chemistry|volume=91|issue=7|pages=4346–4356|year=2019|last1=Colby|first1=Sean M.|last2=Thomas|first2=Dennis G.|last3=Nuñez|first3=Jamie R.|last4=Baxter|first4=Douglas J.|last5=Glaesemann|first5=Kurt R.|last6=Brown|first6=Joseph M.|last7=Pirrung|first7=Meg A.|last8=Govind|first8=Niranjan|last9=Teeguarden|first9=Justin G.|last10=Metz|first10=Thomas O.|last11=Renslow|first11=Ryan S.}}';
    $template = $this->make_citation($text);
    $tmp_array = [$template];
    Zotero::drop_urls_that_match_dois($tmp_array);
    $this->assertNull($template->get2('url'));
  }
  
}
