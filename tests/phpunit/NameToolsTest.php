<?php
declare(strict_types=1);

/*
 * Tests for NameTools.php.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class NameToolsTest extends testBaseClass {

    protected function setUp(): void {
        if (BAD_PAGE_API !== '') {
            $this->markTestSkipped();
        }
        $this->getTestResultObject()->setTimeoutForSmallTests(60);
        $this->getTestResultObject()->setTimeoutForMediumTests(120);
        $this->getTestResultObject()->setTimeoutForLargeTests(180);
    }

    public function testFillCache(): void {
        $this->fill_cache();
        $this->assertTrue(true);
    }

    public function testFormatMultipleAuthors1(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $authors = 'M.A. Smith, Smith M.A., Smith MA., Martin A. Smith, MA Smith, Martin Smith'; // unparsable gibberish formatted in many ways--basically exists to check for code changes
        $result=format_multiple_authors($authors);
        $this->assertSame('M. A. Smith, Smith M. A.; Smith, M. A.; Martin A. Smith, M.A. Smith', $result);
    }
    public function testFormatMultipleAuthors2(): void { // Semi-colon
        $authors = 'M.A. Smith; M.A. Smith';
        $result=format_multiple_authors($authors);
        $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
    }
    public function testFormatMultipleAuthors3(): void { // Spaces
        $authors = 'M.A. Smith  M.A. Smith';
        $result=format_multiple_authors($authors);
        $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
    }
    public function testFormatMultipleAuthors4(): void { // Commas
        $authors = 'M.A. Smith,  M.A. Smith';
        $result=format_multiple_authors($authors);
        $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
    }
    public function testFormatMultipleAuthors5(): void { // Commas, no space
        $authors = 'M.A. Smith,M.A. Smith';
        $result=format_multiple_authors($authors);
        $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
    }
    public function testFormatMultipleAuthors6(): void { // & symbol
        $authors = 'M.A. Smith & M.A. Smith';
        $result=format_multiple_authors($authors);
        $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
    }
    public function testFormatMultipleAuthors7(): void { // The word "and"
        $authors = 'M.A. Smith and M.A. Smith';
        $result=format_multiple_authors($authors);
        $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
    }
    public function testFormatMultipleAuthors8(): void { // extra commas
        $authors = ' ,,,, ,,, , , , , M.A. Smith, ,,, ,, , M.A. Smith,,,,, ,,, , , ';
        $result=format_multiple_authors($authors);
        $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
    }
    public function testFormatMultipleAuthors9(): void { // goofy data
        $authors = ',a,';
        $result=format_multiple_authors($authors);
        $this->assertSame('A.', $result);
    }
    public function testFormatMultipleAuthors10(): void { 
        $authors = 'a';
        $result=format_multiple_authors($authors);
        $this->assertSame('A.', $result);
    }
    public function testFormatMultipleAuthors11(): void { 
        $authors = ',a';
        $result=format_multiple_authors($authors);
        $this->assertSame('A.', $result);
    }
    public function testFormatMultipleAuthors12(): void { 
        $authors = 'a,';
        $result=format_multiple_authors($authors);
        $this->assertSame('A.', $result);
    }

    public function testFormatAuthor1(): void {
        $author = "Conway Morris S.C.";
        $result=format_author($author);
        $this->assertSame('Conway Morris, S. C.', $result); // Was c, Conway Morris S
    }
    public function testFormatAuthor2(): void {
        $author = "M.A. Smith";
        $result=format_author($author);
        $this->assertSame('Smith, M. A', $result);
    }
    public function testFormatAuthor3(): void {
        $author = "Smith M.A.";
        $result=format_author($author);
        $this->assertSame('Smith, M. A.', $result); // Was a, Smith M
    }
    public function testFormatAuthor4(): void {
        $author = "Smith MA.";
        $result=format_author($author);
        $this->assertSame('Smith, M. A.', $result);
    }
    public function testFormatAuthor5(): void {
        $author = "Martin A. Smith";
        $result=format_author($author);
        $this->assertSame('Smith, Martin A', $result);
    }
    public function testFormatAuthor6(): void {
        $author = "MA Smith";
        $result=format_author($author);
        $this->assertSame('Smith, M. A.', $result);
    }
    public function testFormatAuthor7(): void {
        $author = "Martin Smith";
        $result=format_author($author);
        $this->assertSame('Smith, Martin', $result);
    }
    public function testFormatAuthor8(): void {
        $author = "Conway Morris S.C..";
        $result=format_author($author);
        $this->assertSame('Conway Morris, S. C.', $result); //Was c, Conway Morris S
    }
    public function testFormatAuthor9(): void {
        $author = "Smith MA";
        $result=format_author($author);
        $this->assertSame('Smith, M. A.', $result);
    }
    public function testFormatAuthor10(): void {
        $author = "A B C D E F G H";
        $result=format_author($author);
        $this->assertSame('A. B. C. D. E. F. G. H.', $result);
    }
    public function testFormatAuthor11(): void {
        $author = "A. B. C. D. E. F. G. H.";
        $result=format_author($author);
        $this->assertSame('A. B. C. D. E. F. G. H.', $result);
    }
    public function testFormatAuthor12(): void {
        $author = "A.B.C.D.E.F.G.H.";
        $result=format_author($author);
        $this->assertSame('A. B. C. D. E. F. G. H.', $result);
    }
    public function testFormatAuthor13(): void {
        $author = "Smith"; // No first
        $result=format_author($author);
        $this->assertSame('Smith', $result);
    }
    public function testFormatAuthor14(): void {
        $author = "Smith.";  // No first, but with a period oddly
        $result=format_author($author);
        $this->assertSame('Smith', $result);
    }
    public function testFormatAuthor15(): void {
        $author = "S.SmithGuy.X.";  // Totally made up, but we should not eat parts of it - code used to
        $result=format_author($author);
        $this->assertSame('S. Smithguy X.', $result);
    }

    public function testFormatAuthor16(): void {
        $author = "abxxxc xyzd. d. dddss."; // Too man dots, special code
        $result=format_author($author);
        $this->assertSame('Abxxxc Xyzd D. Dddss', $result);
    }

    public function testJunior1(): void {
        $text = ""; // Empty string should work
        $result = junior_test($text);
        $this->assertSame("", $result[0]);
        $this->assertSame("", $result[1]);
    }
    public function testJunior2(): void {
        $text = "Smith";
        $result = junior_test($text);
        $this->assertSame("Smith", $result[0]);
        $this->assertSame("", $result[1]);
    }
    public function testJunior3(): void {
        $text = "Smith Jr.";
        $result = junior_test($text);
        $this->assertSame("Smith", $result[0]);
        $this->assertSame(" Jr.", $result[1]);
    }
    public function testJunior4(): void {
        $text = "Smith Jr";
        $result = junior_test($text);
        $this->assertSame("Smith", $result[0]);
        $this->assertSame(" Jr", $result[1]);
    }
    public function testJunior5(): void {
        $text = "Smith, Jr.";
        $result = junior_test($text);
        $this->assertSame("Smith", $result[0]);
        $this->assertSame(" Jr.", $result[1]);
    }
    public function testJunior6(): void {
        $text = "Smith, Jr";
        $result = junior_test($text);
        $this->assertSame("Smith", $result[0]);
        $this->assertSame(" Jr", $result[1]);
    }
    public function testJunior7(): void {
        $text = "Ewing JR"; // My name is J.R. Ewing, but you can call me J.R.
        $result = junior_test($text);
        $this->assertSame("Ewing JR", $result[0]);
        $this->assertSame("", $result[1]);
    }

    // Random extra code coverage tests
    public function testFormat1(): void {
        $this->assertSame('& a. Johnson', format_surname('& A. Johnson'));
    }
    public function testFormat2(): void {
        $this->assertSame('Johnson; Smith', format_surname('Johnson; Smith'));
    }
    public function testFormat3a(): void {
        $this->assertSame('', format_author(''));
    }
    public function testFormat3b(): void {
        $this->assertSame('', format_multiple_authors(''));
    }
    public function testFormat4(): void {
        $this->assertSame('John, Bob; Kim, Billy', format_multiple_authors('John,Bob,Kim,Billy'));
    }
    public function testFormat5(): void {
        $this->assertSame('Johnson, A. B. C. D. E. F. G', format_author('A. B. C. D. E. F. G. Johnson'));
    }

    public function testCleanUpLastNames1(): void {
        $this->assertSame('B A.', clean_up_last_names('B A.'));
    }
    public function testCleanUpLastNames2(): void {
        $this->assertSame('A.', clean_up_last_names('A.'));
    }
}
