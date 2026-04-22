<?php
declare(strict_types=1);

/*
 * Tests for NameTools.php
 */

require_once __DIR__ . '/../../testBaseClass.php';

final class nameToolsTest extends testBaseClass {

    public function testFormatMultipleAuthors1(): void {
        new TestPage(); // Fill page name with test name for debugging
        $authors = 'M.A. Smith, Smith M.A., Smith MA., Martin A. Smith, MA Smith, Martin Smith'; // unparsable gibberish formatted in many ways--basically exists to check for code changes
        $result = format_multiple_authors($authors);
        $this->assertSame('M. A. Smith, Smith M. A.; Smith, M. A.; Martin A. Smith, M.A. Smith', $result);
    }

    public function testFormatMultipleAuthors2(): void { // Semi-colon
        $authors = 'M.A. Smith; M.A. Smith';
        $result = format_multiple_authors($authors);
        $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
    }

    public function testFormatMultipleAuthors3(): void { // Spaces
        $authors = 'M.A. Smith  M.A. Smith';
        $result = format_multiple_authors($authors);
        $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
    }

    public function testFormatMultipleAuthors4(): void { // Commas
        $authors = 'M.A. Smith,  M.A. Smith';
        $result = format_multiple_authors($authors);
        $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
    }

    public function testFormatMultipleAuthors5(): void { // Commas, no space
        $authors = 'M.A. Smith,M.A. Smith';
        $result = format_multiple_authors($authors);
        $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
    }

    public function testFormatMultipleAuthors6(): void { // & symbol
        $authors = 'M.A. Smith & M.A. Smith';
        $result = format_multiple_authors($authors);
        $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
    }

    public function testFormatMultipleAuthors7(): void { // The word "and"
        $authors = 'M.A. Smith and M.A. Smith';
        $result = format_multiple_authors($authors);
        $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
    }

    public function testFormatMultipleAuthors8(): void { // extra commas
        $authors = ' ,,,, ,,, , , , , M.A. Smith, ,,, ,, , M.A. Smith,,,,, ,,, , , ';
        $result = format_multiple_authors($authors);
        $this->assertSame('Smith, M. A.; Smith, M. A.', $result);
    }

    public function testFormatMultipleAuthors9(): void { // goofy data
        $authors = ',a,';
        $result = format_multiple_authors($authors);
        $this->assertSame('A.', $result);
    }

    public function testFormatMultipleAuthors10(): void {
        $authors = 'a';
        $result = format_multiple_authors($authors);
        $this->assertSame('A.', $result);
    }

    public function testFormatMultipleAuthors11(): void {
        $authors = ',a';
        $result = format_multiple_authors($authors);
        $this->assertSame('A.', $result);
    }

    public function testFormatMultipleAuthors12(): void {
        $authors = 'a,';
        $result = format_multiple_authors($authors);
        $this->assertSame('A.', $result);
    }

    public function testFormatAuthor1(): void {
        $author = "Conway Morris S.C.";
        $result = format_author($author);
        $this->assertSame('Conway Morris, S. C.', $result); // Was c, Conway Morris S
    }

    public function testFormatAuthor2(): void {
        $author = "M.A. Smith";
        $result = format_author($author);
        $this->assertSame('Smith, M. A', $result);
    }

    public function testFormatAuthor3(): void {
        $author = "Smith M.A.";
        $result = format_author($author);
        $this->assertSame('Smith, M. A.', $result); // Was a, Smith M
    }

    public function testFormatAuthor4(): void {
        $author = "Smith MA.";
        $result = format_author($author);
        $this->assertSame('Smith, M. A.', $result);
    }

    public function testFormatAuthor5(): void {
        $author = "Martin A. Smith";
        $result = format_author($author);
        $this->assertSame('Smith, Martin A', $result);
    }

    public function testFormatAuthor6(): void {
        $author = "MA Smith";
        $result = format_author($author);
        $this->assertSame('Smith, M. A.', $result);
    }

    public function testFormatAuthor7(): void {
        $author = "Martin Smith";
        $result = format_author($author);
        $this->assertSame('Smith, Martin', $result);
    }

    public function testFormatAuthor8(): void {
        $author = "Conway Morris S.C..";
        $result = format_author($author);
        $this->assertSame('Conway Morris, S. C.', $result); //Was c, Conway Morris S
    }

    public function testFormatAuthor9(): void {
        $author = "Smith MA";
        $result = format_author($author);
        $this->assertSame('Smith, M. A.', $result);
    }

    public function testFormatAuthor10(): void {
        $author = "A B C D E F G H";
        $result = format_author($author);
        $this->assertSame('A. B. C. D. E. F. G. H.', $result);
    }

    public function testFormatAuthor11(): void {
        $author = "A. B. C. D. E. F. G. H.";
        $result = format_author($author);
        $this->assertSame('A. B. C. D. E. F. G. H.', $result);
    }

    public function testFormatAuthor12(): void {
        $author = "A.B.C.D.E.F.G.H.";
        $result = format_author($author);
        $this->assertSame('A. B. C. D. E. F. G. H.', $result);
    }

    public function testFormatAuthor13(): void {
        $author = "Smith"; // No first
        $result = format_author($author);
        $this->assertSame('Smith', $result);
    }

    public function testFormatAuthor14(): void {
        $author = "Smith.";  // No first, but with a period oddly
        $result = format_author($author);
        $this->assertSame('Smith', $result);
    }

    public function testFormatAuthor15(): void {
        $author = "S.SmithGuy.X.";  // Totally made up, but we should not eat parts of it - code used to
        $result = format_author($author);
        $this->assertSame('S. Smithguy X.', $result);
    }

    public function testFormatAuthor16(): void {
        $author = "abxxxc xyzd. d. dddss."; // Too man dots, special code
        $result = format_author($author);
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

    /** Random extra code coverage tests */
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

    public function testMiscNameTests1(): void {
        $this->assertSame('', format_surname('-'));
    }

    public function testMiscNameTests2(): void {
        $this->assertSame('', format_surname(''));
    }

    public function testMiscNameTests3(): void {
        $this->assertSame('', format_forename('-'));
    }

    public function testMiscNameTests4(): void {
        $this->assertSame('', format_forename(''));
    }

    public function testMiscNameTests5(): void {
        $this->assertSame('', format_initials('    '));
    }

    public function testMiscNameTests62(): void {
        $this->assertFalse(is_initials('    '));
    }

    public function testMiscNameTests7(): void {
        $this->assertSame('Aa;xx', format_surname('AA;XX'));
    }

    public function testMiscNameTests8(): void {
        $this->assertSame('Aa; Xx', format_surname('AA; XX'));
    }

    public function testSplit(): void {
        $out = split_authors('Joe,Bob;Jim,Slim');
        $this->assertSame('Joe,Bob', $out[0]);
        $this->assertSame('Jim,Slim', $out[1]);
    }

    public function testTidyLastFirts(): void {
        $text = '{{cite document |last=Howlett |first=Felicity|last2=Fred}}';
        $template = $this->process_citation($text);
        $this->assertSame('{{cite document |last1=Howlett |first1=Felicity|last2=Fred}}', $template->parsed_text());
    }

    public function testAuthorIsHumanNormalName(): void {
        $this->assertTrue(author_is_human('John Smith'));
    }

    public function testAuthorIsHumanSingleWord(): void {
        $this->assertTrue(author_is_human('Smith'));
    }

    public function testAuthorIsHumanWithColon(): void {
        $this->assertFalse(author_is_human('Reuters: News Agency'));
    }

    public function testAuthorIsHumanTooManySpaces(): void {
        $this->assertFalse(author_is_human('A B C D E'));
    }

    public function testAuthorIsHumanTooLong(): void {
        $this->assertFalse(author_is_human('Abcdefghijklmnopqrstuvwxyz12345678'));
    }

    public function testAuthorIsHumanStartsWithThe(): void {
        $this->assertFalse(author_is_human('The Associated Press'));
    }

    public function testAuthorIsHumanThreeUppercaseChars(): void {
        $this->assertFalse(author_is_human('ABC News'));
    }

    public function testAuthorIsHumanInc(): void {
        $this->assertFalse(author_is_human('Company Inc'));
    }

    public function testAuthorIsHumanIncDot(): void {
        $this->assertFalse(author_is_human('Company Inc.'));
    }

    public function testAuthorIsHumanLLC(): void {
        $this->assertFalse(author_is_human('Company LLC'));
    }

    public function testAuthorIsHumanLLCDot(): void {
        $this->assertFalse(author_is_human('Company LLC.'));
    }

    public function testAuthorIsHumanBooks(): void {
        $this->assertFalse(author_is_human('Some Books'));
    }

    public function testAuthorIsHumanNyheter(): void {
        $this->assertFalse(author_is_human('Dagbladet Nyheter'));
    }

    public function testUnderTwoAuthorsSingleWord(): void {
        $this->assertTrue(under_two_authors('Smith'));
    }

    public function testUnderTwoAuthorsLastFirstFormat(): void {
        $this->assertTrue(under_two_authors('Smith, John'));
    }

    public function testUnderTwoAuthorsSemicolon(): void {
        $this->assertFalse(under_two_authors('Smith, John; Doe, Jane'));
    }

    public function testUnderTwoAuthorsMoreThanOneComma(): void {
        $this->assertFalse(under_two_authors('Smith, John, Doe'));
    }

    public function testUnderTwoAuthorsSpacesExceedCommas(): void {
        // "John Smith" has 1 space and 0 commas → spaces > commas → multiple authors
        $this->assertFalse(under_two_authors('John Smith'));
    }

    public function testIsBadAuthorPipe(): void {
        $this->assertTrue(is_bad_author('|'));
    }

    public function testIsBadAuthorPublished(): void {
        $this->assertTrue(is_bad_author('Published'));
    }

    public function testIsBadAuthorNormalName(): void {
        $this->assertFalse(is_bad_author('John Smith'));
    }

    public function testIsBadAuthorEmpty(): void {
        $this->assertFalse(is_bad_author(''));
    }

    public function testSplitAuthorOneComma(): void {
        $result = split_author('Smith, John');
        $this->assertSame(['Smith', ' John'], $result);
    }

    public function testSplitAuthorNoComma(): void {
        $result = split_author('John Smith');
        $this->assertSame([], $result);
    }

    public function testSplitAuthorMultipleCommas(): void {
        $result = split_author('Smith, John, Jr');
        $this->assertSame([], $result);
    }

    public function testCleanUpFullNamesAndSemicolon(): void {
        $this->assertSame('name1; name2', clean_up_full_names('name1 and; name2'));
    }

    public function testCleanUpFullNamesDoubleSpace(): void {
        $this->assertSame('name1 name2', clean_up_full_names('name1  name2'));
    }

    public function testCleanUpFullNamesPlusRemoved(): void {
        $this->assertSame('name1name2', clean_up_full_names('name1+name2'));
    }

    public function testCleanUpFullNamesStarRemoved(): void {
        $this->assertSame('name1name2', clean_up_full_names('name1*name2'));
    }

    public function testCleanUpFirstNamesSingleCharGetsDot(): void {
        $this->assertSame('J.', clean_up_first_names('J'));
    }

    public function testCleanUpFirstNamesTwoInitialsGetDots(): void {
        $this->assertSame('F. M.', clean_up_first_names('F M'));
    }

    public function testCleanUpFirstNamesWordEndingInInitial(): void {
        $this->assertSame('Fred M.', clean_up_first_names('Fred M'));
    }

    public function testCleanUpFirstNamesDoubleSpaceCollapsed(): void {
        $this->assertSame('Fred Smith', clean_up_first_names('Fred  Smith'));
    }

    public function testFormatSurname2UpperToTitleCase(): void {
        $this->assertSame('Smith', format_surname_2('SMITH'));
    }

    public function testFormatSurname2VonLowercased(): void {
        $this->assertSame('von Neumann', format_surname_2('VON NEUMANN'));
    }

    public function testFormatSurname2DeLaLowercased(): void {
        $this->assertSame('de la Cruz', format_surname_2('DE LA CRUZ'));
    }

    public function testFormatSurname2HyphenWithSpaces(): void {
        $this->assertSame('Smith-Jones', format_surname_2('SMITH - JONES'));
    }

    public function testFormatSurname2UndLowercased(): void {
        $this->assertSame('Strauss und Torney', format_surname_2('STRAUSS UND TORNEY'));
    }
}
