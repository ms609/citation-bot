<?php

/*
 * Tests for DOITools.php.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class doiToolsTest extends testBaseClass {
  public function testFormatMultipleAuthors1() {
    $authors = 'M.A. Smith, Smith M.A., Smith MA., Martin A. Smith, MA Smith, Martin Smith'; // unparsable gibberish formatted in many ways--basically exists to check for code changes
    $result=format_multiple_authors($authors,FALSE);
    $this->assertSame('M. A. Smith, Smith M. A.; Smith, M. A.; Martin A. Smith, M.A. Smith', $result);
  }
  public function testFormatMultipleAuthors2() {  // Semi-colon
    $authors = 'M.A. Smith; M.A. Smith';
    $result=format_multiple_authors($authors,FALSE);
    $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
  }
  public function testFormatMultipleAuthors3() { // Spaces
    $authors = 'M.A. Smith  M.A. Smith';
    $result=format_multiple_authors($authors,FALSE);
    $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
  }
  public function testFormatMultipleAuthors4() { // Commas
    $authors = 'M.A. Smith,  M.A. Smith';
    $result=format_multiple_authors($authors,FALSE);
    $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
  }
  public function testFormatMultipleAuthors5() { // Commas, no space
    $authors = 'M.A. Smith,M.A. Smith';
    $result=format_multiple_authors($authors,FALSE);
    $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
  }
  public function testFormatMultipleAuthors6() { // & symbol
    $authors = 'M.A. Smith & M.A. Smith';
    $result=format_multiple_authors($authors,FALSE);
    $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
  }
  public function testFormatMultipleAuthors7() { // The word "and"
    $authors = 'M.A. Smith and M.A. Smith';
    $result=format_multiple_authors($authors,FALSE);
    $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
  }
  public function testFormatMultipleAuthors8() { // extra commas
    $authors = ' ,,,, ,,, , , , , M.A. Smith, ,,, ,, , M.A. Smith,,,,, ,,, , , ';
    $result=format_multiple_authors($authors,FALSE);
    $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
  }
    
  public function testFormatAuthor1() {  
    $author = "Conway Morris S.C.";
    $result=format_author($author);
    $this->assertSame('Conway Morris, S. C.', $result); // Was c, Conway Morris S 
  }
  public function testFormatAuthor2() {  
    $author = "M.A. Smith";
    $result=format_author($author);
    $this->assertSame('Smith, M. A', $result);
  }
  public function testFormatAuthor3() {  
    $author = "Smith M.A.";
    $result=format_author($author);
    $this->assertSame('Smith, M. A.', $result); // Was a, Smith M
  }
  public function testFormatAuthor4() {  
    $author = "Smith MA.";
    $result=format_author($author);
    $this->assertSame('Smith, M. A.', $result);
  }
  public function testFormatAuthor5() {  
    $author = "Martin A. Smith";
    $result=format_author($author);
    $this->assertSame('Smith, Martin A', $result);
  }
  public function testFormatAuthor6() {  
    $author = "MA Smith";
    $result=format_author($author);
    $this->assertSame('Smith, M. A.', $result);
  }
  public function testFormatAuthor7() {  
    $author = "Martin Smith";
    $result=format_author($author);
    $this->assertSame('Smith, Martin', $result);
  }
  public function testFormatAuthor8() {  
    $author = "Conway Morris S.C..";
    $result=format_author($author);
    $this->assertSame('Conway Morris, S. C.', $result); //Was c, Conway Morris S
  }
  public function testFormatAuthor9() {  
    $author = "Smith MA";
    $result=format_author($author);
    $this->assertSame('Smith, M. A.', $result);
  }
  public function testFormatAuthor10() {  
    $author = "A B C D E F G H";
    $result=format_author($author);
    $this->assertSame('A. B. C. D. E. F. G. H.', $result);
  }
  public function testFormatAuthor11() {  
    $author = "A. B. C. D. E. F. G. H.";
    $result=format_author($author);
    $this->assertSame('A. B. C. D. E. F. G. H.', $result);
  }
  public function testFormatAuthor12() {  
    $author = "A.B.C.D.E.F.G.H.";
    $result=format_author($author);
    $this->assertSame('A. B. C. D. E. F. G. H.', $result);
  }
  public function testFormatAuthor13() {  
    $author = "Smith"; // No first
    $result=format_author($author);
    $this->assertSame('Smith', $result);
  }
  public function testFormatAuthor14() {  
    $author = "Smith.";  // No first, but with a period oddly
    $result=format_author($author);
    $this->assertSame('Smith', $result);
  }
  public function testFormatAuthor15() {  
     $author = "S.SmithGuy.X.";  // Totally made up, but we should not eat parts of it - code used to
     $result=format_author($author);
     $this->assertSame('S. Smithguy X.', $result);
   }

   public function testJunior() {
       $text = ""; // Empty string should work
       $result = junior_test($text);
       $this->assertSame("", $result[0]);
       $this->assertSame(FALSE, $result[1]);
       $text = "Smith";
       $result = junior_test($text);
       $this->assertSame("Smith", $result[0]);
       $this->assertSame(FALSE, $result[1]);
       $text = "Smith Jr.";
       $result = junior_test($text);
       $this->assertSame("Smith", $result[0]);
       $this->assertSame(" Jr.", $result[1]);
       $text = "Smith Jr";
       $result = junior_test($text);
       $this->assertSame("Smith", $result[0]);
       $this->assertSame(" Jr", $result[1]);
       $text = "Smith, Jr.";
       $result = junior_test($text);
       $this->assertSame("Smith", $result[0]);
       $this->assertSame(" Jr.", $result[1]);
       $text = "Smith, Jr";
       $result = junior_test($text);
       $this->assertSame("Smith", $result[0]);
       $this->assertSame(" Jr", $result[1]);
       $text = "Ewing JR"; // My name is J.R. Ewing, but you can call me J.R.
       $result = junior_test($text);
       $this->assertSame("Ewing JR", $result[0]);
       $this->assertSame(FALSE, $result[1]);
  }
  
  public function testEditSummary() {  // Not a great test. Mostly just verifies no crashes in code
    $page = new TestPage();
    $text = "{{Cite journal|pmid=9858586}}";
    $page->parse_text($text);
    $page->expand_text();
    $this->assertNotNull($page->edit_summary());
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
  
  public function testFormat() { // Random extra code coverage tests
    $this->assertSame('& a. Johnson', format_surname('& A. Johnson'));
    $this->assertSame('Johnson; Smith', format_surname('Johnson; Smith'));
    $this->assertSame(FALSE, format_author(''));
    $this->assertSame(FALSE, format_multiple_authors(''));
    $this->assertSame('John, Bob; Kim, Billy', format_multiple_authors('John,Bob,Kim,Billy'));
    $this->assertSame('Johnson, A. B. C. D. E. F. G', format_author('A. B. C. D. E. F. G. Johnson'));
    $this->assertSame(['John','Bob','Kim','Billy'], format_multiple_authors('John;Bob;Kim;Billy', TRUE));
  }
}
