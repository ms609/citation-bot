<?php

/*
 * Tests for NameTools.php.
 */

require_once(__DIR__ . '/../testBaseClass.php');

final class doiToolsTest extends testBaseClass {
  public function testFormatMultipleAuthors1() {
    $authors = 'M.A. Smith, Smith M.A., Smith MA., Martin A. Smith, MA Smith, Martin Smith'; // unparsable gibberish formatted in many ways--basically exists to check for code changes
    $result=format_multiple_authors($authors);
    $this->assertSame('M. A. Smith, Smith M. A.; Smith, M. A.; Martin A. Smith, M.A. Smith', $result);
  }
  public function testFormatMultipleAuthors2() {  // Semi-colon
    $authors = 'M.A. Smith; M.A. Smith';
    $result=format_multiple_authors($authors);
    $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
  }
  public function testFormatMultipleAuthors3() { // Spaces
    $authors = 'M.A. Smith  M.A. Smith';
    $result=format_multiple_authors($authors);
    $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
  }
  public function testFormatMultipleAuthors4() { // Commas
    $authors = 'M.A. Smith,  M.A. Smith';
    $result=format_multiple_authors($authors);
    $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
  }
  public function testFormatMultipleAuthors5() { // Commas, no space
    $authors = 'M.A. Smith,M.A. Smith';
    $result=format_multiple_authors($authors);
    $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
  }
  public function testFormatMultipleAuthors6() { // & symbol
    $authors = 'M.A. Smith & M.A. Smith';
    $result=format_multiple_authors($authors);
    $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
  }
  public function testFormatMultipleAuthors7() { // The word "and"
    $authors = 'M.A. Smith and M.A. Smith';
    $result=format_multiple_authors($authors);
    $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
  }
  public function testFormatMultipleAuthors8() { // extra commas
    $authors = ' ,,,, ,,, , , , , M.A. Smith, ,,, ,, , M.A. Smith,,,,, ,,, , , ';
    $result=format_multiple_authors($authors);
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

  public function testFormatAuthor16() {  
     $author = "abxxxc xyzd. d. dddss."; // Too man dots, special code
     $result=format_author($author);
     $this->assertSame('Abxxxc Xyzd D. Dddss', $result);
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

  public function testFormat() { // Random extra code coverage tests
    $this->assertSame('& a. Johnson', format_surname('& A. Johnson'));
    $this->assertSame('Johnson; Smith', format_surname('Johnson; Smith'));
    $this->assertSame('', format_author(''));
    $this->assertSame('', format_multiple_authors(''));
    $this->assertSame('John, Bob; Kim, Billy', format_multiple_authors('John,Bob,Kim,Billy'));
    $this->assertSame('Johnson, A. B. C. D. E. F. G', format_author('A. B. C. D. E. F. G. Johnson'));
  }
}
