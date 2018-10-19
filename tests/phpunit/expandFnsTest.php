<?php

/*
 * Current tests that are failing.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class expandFnsTest extends testBaseClass {

  public function testCapitalization() {
    $this->assertEquals('Molecular and Cellular Biology', 
                        title_capitalization(title_case('Molecular and cellular biology'), TRUE));
    $this->assertEquals('z/Journal', 
                        title_capitalization(title_case('z/Journal'), TRUE));
    $this->assertEquals('The Journal of Journals', // The, not the
                        title_capitalization('The Journal Of Journals', TRUE));
    $this->assertEquals('A Journal of Chemistry A',
                        title_capitalization('A Journal of Chemistry A', TRUE));
    $this->assertEquals('A Journal of Chemistry E',
                        title_capitalization('A Journal of Chemistry E', TRUE));                      
    $this->assertEquals('This a Journal', 
                        title_capitalization('THIS A JOURNAL', TRUE));
  }
  
  public function testFrenchCapitalization() {
    $this->assertEquals("L'Aerotecnica",
                        title_capitalization(title_case("L'Aerotecnica"), TRUE));
    $this->assertEquals("Phénomènes d'Évaporation d'Hydrologie",
                        title_capitalization(title_case("Phénomènes d'Évaporation d’hydrologie"), TRUE));
    $this->assertEquals("D'Hydrologie Phénomènes d'Évaporation d'Hydrologie l'Aerotecnica",
                        title_capitalization("D'Hydrologie Phénomènes d&#x2019;Évaporation d&#8217;Hydrologie l&rsquo;Aerotecnica", TRUE));
  }
    
  public function testExtractDoi() {
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', 
                        extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/full')[1]);
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', 
                        extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract')[1]);
    $this->assertEquals('10.1016/j.physletb.2010.03.064', 
                        extract_doi(' 10.1016%2Fj.physletb.2010.03.064')[1]);
    $this->assertEquals('10.1093/acref/9780199204632.001.0001', 
                        extract_doi('http://www.oxfordreference.com/view/10.1093/acref/9780199204632.001.0001/acref-9780199204632-e-4022')[1]);
    $this->assertEquals('10.1038/nature11111', 
                        extract_doi('http://www.oxfordreference.com/view/10.1038/nature11111/figures#display.aspx?quest=solve&problem=punctuation')[1]);
  }
  
  public function testTidyDate() {
    $this->assertEquals('2014', tidy_date('maanantai 14. heinäkuuta 2014'));
    $this->assertEquals('2012-03-20', tidy_date('2012年4月20日 星期五'));
    $this->assertEquals('2011-05-10', tidy_date('2011-05-10T06:34:00-0400'));
    $this->assertEquals('2014-07-01', tidy_date('2014-07-01T23:50:00Z, 2014-07-01'));
    $this->assertEquals('', tidy_date('۱۳۸۶/۱۰/۰۴ - ۱۱:۳۰'));
  }
}
