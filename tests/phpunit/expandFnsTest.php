<?php

/*
 * Current tests that are failing.
 */

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
    $this->assertEquals('Molecular and Cellular Biology', title_capitalization(title_case('Molecular and cellular biology')));
    $this->assertEquals('z/Journal', title_capitalization(title_case('z/Journal')));
    $this->assertEquals('The Journal of Journals', title_capitalization('The Journal Of Journals')); // The, not the
  }

  public function testDoiRegExp() {
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/full')[1]);
    $this->assertEquals('10.1111/j.1475-4983.2012.01203.x', extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract')[1]);
    $this->assertEquals('10.1016/j.physletb.2010.03.064', extract_doi(' 10.1016%2Fj.physletb.2010.03.064')[1]);
  }
     
  public function testMathInTitle() {
      // This MML code comes from a real CroffRef search.  The <math> is the correct final code.
      $text_math = 'Spectroscopic analysis of the candidate <math><mrow>β</mrow></math> Cephei star <math><mrow>σ</mrow></math> Cas: Atmospheric characterization and line-profile variability';
      $text_mml  = 'Spectroscopic analysis of the candidate <mml:math altimg="si37.gif" overflow="scroll" xmlns:xocs="http://www.elsevier.com/xml/xocs/dtd" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.elsevier.com/xml/ja/dtd" xmlns:ja="http://www.elsevier.com/xml/ja/dtd" xmlns:mml="http://www.w3.org/1998/Math/MathML" xmlns:tb="http://www.elsevier.com/xml/common/table/dtd" xmlns:sb="http://www.elsevier.com/xml/common/struct-bib/dtd" xmlns:ce="http://www.elsevier.com/xml/common/dtd" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:cals="http://www.elsevier.com/xml/common/cals/dtd"><mml:mrow><mml:mi>β</mml:mi></mml:mrow></mml:math> Cephei star <mml:math altimg="si38.gif" overflow="scroll" xmlns:xocs="http://www.elsevier.com/xml/xocs/dtd" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.elsevier.com/xml/ja/dtd" xmlns:ja="http://www.elsevier.com/xml/ja/dtd" xmlns:mml="http://www.w3.org/1998/Math/MathML" xmlns:tb="http://www.elsevier.com/xml/common/table/dtd" xmlns:sb="http://www.elsevier.com/xml/common/struct-bib/dtd" xmlns:ce="http://www.elsevier.com/xml/common/dtd" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:cals="http://www.elsevier.com/xml/common/cals/dtd"><mml:mrow><mml:mi>σ</mml:mi></mml:mrow></mml:math> Cas: Atmospheric characterization and line-profile variability';
      $this->assertEquals($text_math,sanitize_string($text_math));      // Should not change
      $this->assertEquals($text_math,wikify_external_text($text_math)); // Should not changeg
      $this->assertEquals($text_math,wikify_external_text($text_mml));  // The most important test: mml converstion to <math>
  }

}
