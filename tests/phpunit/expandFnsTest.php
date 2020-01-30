<?php

/*
 * Current tests that are failing.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class expandFnsTest extends testBaseClass {

  public function testCapitalization() {
    $this->assertSame('Molecular and Cellular Biology', 
                        title_capitalization(title_case('Molecular and cellular biology'), TRUE));
    $this->assertSame('z/Journal', 
                        title_capitalization(title_case('z/Journal'), TRUE));
    $this->assertSame('The Journal of Journals', // The, not the
                        title_capitalization('The Journal Of Journals', TRUE));
    $this->assertSame('A Journal of Chemistry A',
                        title_capitalization('A Journal of Chemistry A', TRUE));
    $this->assertSame('A Journal of Chemistry E',
                        title_capitalization('A Journal of Chemistry E', TRUE));                      
    $this->assertSame('This a Journal', 
                        title_capitalization('THIS A JOURNAL', TRUE));
    $this->assertSame('This a Journal', 
                        title_capitalization('THIS A JOURNAL', TRUE));
    $this->assertSame("THIS 'A' JOURNAL mittEilUngen", 
                        title_capitalization("THIS `A` JOURNAL mittEilUngen", TRUE));
    $this->assertSame('[Johsnon And me]', title_capitalization('[Johsnon And me]', TRUE)); // Do not touch links
  }
  
  public function testFrenchCapitalization() {
    $this->assertSame("L'Aerotecnica",
                        title_capitalization(title_case("L'Aerotecnica"), TRUE));
    $this->assertSame("Phénomènes d'Évaporation d'Hydrologie",
                        title_capitalization(title_case("Phénomènes d'Évaporation d’hydrologie"), TRUE));
    $this->assertSame("D'Hydrologie Phénomènes d'Évaporation d'Hydrologie l'Aerotecnica",
                        title_capitalization("D'Hydrologie Phénomènes d&#x2019;Évaporation d&#8217;Hydrologie l&rsquo;Aerotecnica", TRUE));
  }
  
  public function testITS() {
    $this->assertSame(                     "Keep case of its Its and ITS",
                        title_capitalization("Keep case of its Its and ITS", TRUE));
    $this->assertSame(                     "ITS Keep case of its Its and ITS",
                        title_capitalization("ITS Keep case of its Its and ITS", TRUE));
  }
    
  public function testExtractDoi() {
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', 
                        extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/full')[1]);
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', 
                        extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract')[1]);
    $this->assertSame('10.1016/j.physletb.2010.03.064', 
                        extract_doi(' 10.1016%2Fj.physletb.2010.03.064')[1]);
    $this->assertSame('10.1093/acref/9780199204632.001.0001', 
                        extract_doi('http://www.oxfordreference.com/view/10.1093/acref/9780199204632.001.0001/acref-9780199204632-e-4022')[1]);
    $this->assertSame('10.1038/nature11111', 
                        extract_doi('http://www.oxfordreference.com/view/10.1038/nature11111/figures#display.aspx?quest=solve&problem=punctuation')[1]);
  }
  
  public function testSanitizeDoi() {
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', sanitize_doi('10.1111/j.1475-4983.2012.01203.x'));
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', sanitize_doi('10.1111/j.1475-4983.2012.01203.x.')); // extra dot
    $this->assertSame('10.1111/j.1475-4983.2012.01203.x', sanitize_doi('10.1111/j.1475-4983.2012.01203.'));  // Missing x after dot
    $this->assertSame('143242342342', sanitize_doi('143242342342.')); // Rubbish with trailing dot, just remove it 
  }
  
  public function testTidyDate() {
    $this->assertSame('2014', tidy_date('maanantai 14. heinäkuuta 2014'));
    $this->assertSame('2012-04-20', tidy_date('2012年4月20日 星期五'));
    $this->assertSame('2011-05-10', tidy_date('2011-05-10T06:34:00-0400'));
    $this->assertSame('July 2014', tidy_date('2014-07-01T23:50:00Z, 2014-07-01'));
    $this->assertSame('', tidy_date('۱۳۸۶/۱۰/۰۴ - ۱۱:۳۰'));
    $this->assertSame('2014-01-24', tidy_date('01/24/2014 16:01:06'));
    $this->assertSame('2011-11-30', tidy_date('30/11/2011 12:52:08'));
    $this->assertSame('2011'      , tidy_date('05/11/2011 12:52:08'));
    $this->assertSame('2011-11-11', tidy_date('11/11/2011 12:52:08'));
    $this->assertSame('2018-10-21', tidy_date('Date published (2018-10-21'));
    $this->assertSame('2008-04-29', tidy_date('07:30 , 04.29.08'));
    $this->assertSame('', tidy_date('-0001-11-30T00:00:00+00:00'));
    $this->assertSame('', tidy_date('22/22/2010'));  // That is not valid date code
    $this->assertSame('', tidy_date('The date is 88 but not three')); // Not a date, but has some numbers
    $this->assertSame('2016-10-03', tidy_date('3 October, 2016')); // evil comma
    $this->assertSame('22 October 1999 – 22 September 2000', tidy_date('1999-10-22 - 2000-09-22'));
    $this->assertSame('22 October – 22 September 1999', tidy_date('1999-10-22 - 1999-09-22'));
  }
  
  public function testRemoveComments() {
    $this->assertSame('ABC', remove_comments('A<!-- -->B# # # CITATION_BOT_PLACEHOLDER_COMMENT 33 # # #C'));
  }

  public function testJstorInDo() {
    $template = $this->prepare_citation('{{cite journal|jstor=}}');
    $doi = '10.2307/3241423?junk'; // test 10.2307 code and ? code
    check_doi_for_jstor($doi, $template);
    $this->assertSame('3241423',$template->get('jstor'));
  }

  public function test_titles_are_dissimilar_LONG() {
    $big1 = "asdfgtrewxcvbnjy67rreffdsffdsgfbdfni goreinagoidfhgaodusfhaoleghwc89foxyehoif2faewaeifhajeowhf;oaiwehfa;ociboes;";
    $big1 = $big1 . $big1 .$big1 .$big1 .$big1 ;
    $big2 = $big1 . "X"; // stuff...X
    $big1 = $big1 . "Y"; // stuff...Y
    $big3 = $big1 . $big1 ; // stuff...Xstuff...X
    $this->assertTrue(titles_are_similar($big1, $big2));
    $this->assertTrue(titles_are_dissimilar($big1, $big3));
  }
  
  public function test_titles_are_similar_ticks() {
    $this->assertSame('ejscriptgammaramshg', strip_diacritics('ɞɟɡɣɤɥɠ'));
    $this->assertTrue(titles_are_similar('ɞɟɡɣɤɥɠ', 'ejscriptgammaramshg'));
  }

  public function testArrowAreQuotes() {
    $text = "This » That";
    $this->assertSame($text,straighten_quotes($text));
    $text = "X«Y»Z";
    $this->assertSame('X"Y"Z',straighten_quotes($text));
    $text = "This › That";
    $this->assertSame($text,straighten_quotes($text));
    $text = "X‹Y›Z";
    $this->assertSame("X'Y'Z",straighten_quotes($text));
  }

  public function testMathInTitle() {
    // This MML code comes from a real CrossRef search of DOI 10.1016/j.newast.2009.05.001
    // $text_math is the correct final output
    $text_math = 'Spectroscopic analysis of the candidate <math><mrow>ß</mrow></math> Cephei star <math><mrow>s</mrow></math> Cas: Atmospheric characterization and line-profile variability';
    $text_mml  = 'Spectroscopic analysis of the candidate <mml:math altimg="si37.gif" overflow="scroll" xmlns:xocs="http://www.elsevier.com/xml/xocs/dtd" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.elsevier.com/xml/ja/dtd" xmlns:ja="http://www.elsevier.com/xml/ja/dtd" xmlns:mml="http://www.w3.org/1998/Math/MathML" xmlns:tb="http://www.elsevier.com/xml/common/table/dtd" xmlns:sb="http://www.elsevier.com/xml/common/struct-bib/dtd" xmlns:ce="http://www.elsevier.com/xml/common/dtd" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:cals="http://www.elsevier.com/xml/common/cals/dtd"><mml:mrow><mml:mi>ß</mml:mi></mml:mrow></mml:math> Cephei star <mml:math altimg="si38.gif" overflow="scroll" xmlns:xocs="http://www.elsevier.com/xml/xocs/dtd" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.elsevier.com/xml/ja/dtd" xmlns:ja="http://www.elsevier.com/xml/ja/dtd" xmlns:mml="http://www.w3.org/1998/Math/MathML" xmlns:tb="http://www.elsevier.com/xml/common/table/dtd" xmlns:sb="http://www.elsevier.com/xml/common/struct-bib/dtd" xmlns:ce="http://www.elsevier.com/xml/common/dtd" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:cals="http://www.elsevier.com/xml/common/cals/dtd"><mml:mrow><mml:mi>s</mml:mi></mml:mrow></mml:math> Cas: Atmospheric characterization and line-profile variability';
    $this->assertSame($text_math,sanitize_string($text_math));      // Should not change
    $this->assertSame($text_math,wikify_external_text($text_math)); // Should not change
    $this->assertSame($text_math,wikify_external_text($text_mml));  // The most important test: mml converstion to <math>
  }
  
  public function testBrackets() {
    $this->assertSame("ABC",remove_brackets('{}{}{A[][][][][]B()(){}[]][][[][C][][][[()()'));
  }
  
  // The X prevents first character caps
  public function testCapitalization_lots_more() { // Double check that constants are in order when we sort - paranoid
    $this->assertSame('X BJPsych', title_capitalization(title_case('X Bjpsych'), TRUE));
    $this->assertSame('X delle', title_capitalization(title_case('X delle'), TRUE));
    $this->assertSame('X IEEE', title_capitalization(title_case('X Ieee'), TRUE));
    $this->assertSame('X NASA', title_capitalization(title_case('X Nasa'), TRUE));
    $this->assertSame('X over', title_capitalization(title_case('X Over'), TRUE));
    $this->assertSame('X und', title_capitalization(title_case('X Und'), TRUE));
    $this->assertSame('X within', title_capitalization(title_case('X Within'), TRUE));
    $this->assertSame('X AAPS', title_capitalization(title_case('X Aaps'), TRUE));
    $this->assertSame('X BJOG', title_capitalization(title_case('X Bjog'), TRUE));
  }
  public function testCapitalization_lots_more2() {
    $this->assertSame('X e-Neuroforum', title_capitalization(title_case('X E-Neuroforum'), TRUE));
    $this->assertSame('X eGEMs', title_capitalization(title_case('X Egems'), TRUE));
    $this->assertSame('X eNeuro', title_capitalization(title_case('X Eneuro'), TRUE));
    $this->assertSame('X eVolo', title_capitalization(title_case('X EVolo'), TRUE));
    $this->assertSame('X HannahArendt.net', title_capitalization(title_case('X hannaharendt.net'), TRUE));
    $this->assertSame('X iJournal', title_capitalization(title_case('X IJournal'), TRUE));
    $this->assertSame('X JABS : Journal of Applied Biological Sciences', title_capitalization(title_case('X Jabs : Journal of Applied Biological Sciences'), TRUE));
  }
  public function testCapitalization_lots_more3() {
    $this->assertSame('X La Trobe', title_capitalization(title_case('X La Trobe'), TRUE));
    $this->assertSame('X MERIP', title_capitalization(title_case('X Merip'), TRUE));
    $this->assertSame('X mSystems', title_capitalization(title_case('X MSystems'), TRUE));
    $this->assertSame('X PhytoKeys', title_capitalization(title_case('X Phytokeys'), TRUE));
    $this->assertSame('X PNAS', title_capitalization(title_case('X Pnas'), TRUE));
  }
  public function testCapitalization_lots_more4() {
    $this->assertSame('X Srp Arh Celok Lek', title_capitalization(title_case('X SRP Arh Celok Lek'), TRUE));
    $this->assertSame('X Time Out London', title_capitalization(title_case('X Time out London'), TRUE));
    $this->assertSame('X z/Journal', title_capitalization(title_case('X Z/journal'), TRUE));
    $this->assertSame('X ZooKeys', title_capitalization(title_case('X zookeys'), TRUE));
  }
}
