<?php

/*
 * Current tests that are failing.
 */
error_reporting(E_ALL);
// backward compatibility
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

final class expandFnsTest extends PHPUnit\Framework\TestCase {

  protected function setUp() {
  }

  protected function tearDown() {
  }
  
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
    
  public function testDoiRegExp() {
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', 
                        extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/full')[1]);
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', 
                        extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract')[1]);
    $this->assertEquals('10.1016/j.physletb.2010.03.064', 
                        extract_doi(' 10.1016%2Fj.physletb.2010.03.064')[1]);
  }  
}
