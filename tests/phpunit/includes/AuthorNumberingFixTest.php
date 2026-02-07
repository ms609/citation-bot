<?php
declare(strict_types=1);

/*
 * Tests for author numbering compaction fix
 * Ensures author-numbered parameters are contiguous after filtering
 */

require_once __DIR__ . '/../../testBaseClass.php';
final class AuthorNumberingFixTest extends testBaseClass {

    /**
     * Test case 1: Template with only last2/first2 (missing last1/first1)
     * Should compact to last1/first1
     */
    public function testAuthorNumberingGap_OnlyLast2(): void {
        $text = '{{Cite web|last2=Magid |first2=Jacob |url=https://www.timesofisrael.com/in-1st-entire-arab-league-condemns-oct-7-urges-hamas-to-disarm-at-2-state-solution-confab/|title=In 1st, entire Arab League condemns Oct. 7, urges Hamas to disarm, at 2-state confab|work=The Times of Israel |date=July 30, 2025|via=www.timesofisrael.com}}';
        $expanded = $this->process_citation($text);
        
        // After processing, last2/first2 should become last1/first1
        $this->assertSame('Magid', $expanded->get2('last1'));
        $this->assertSame('Jacob', $expanded->get2('first1'));
        // last2/first2 should not exist anymore
        $this->assertNull($expanded->get2('last2'));
        $this->assertNull($expanded->get2('first2'));
    }

    /**
     * Test case 2: Template with last1/first1 and last3/first3 (missing last2/first2)
     * Should compact to last1/first1 and last2/first2
     */
    public function testAuthorNumberingGap_MissingLast2(): void {
        $text = '{{Cite news |last3=Chicago |first3=Julie Bosman From |last1=Aleaziz |first1=Hamed |date=September 8, 2025 |title=Trump Administration Says It Has Begun Immigration Crackdown in Chicago |work=The New York Times |url=https://www.nytimes.com/2025/09/08/us/chicago-immigration-crackdown-trump-administration.html |access-date=September 9, 2025 |language=en}}';
        $expanded = $this->process_citation($text);
        
        // After processing, authors should be numbered 1 and 2 (contiguously)
        $this->assertSame('Aleaziz', $expanded->get2('last1'));
        $this->assertSame('Hamed', $expanded->get2('first1'));
        $this->assertSame('Chicago', $expanded->get2('last2'));
        $this->assertSame('Julie Bosman From', $expanded->get2('first2'));
        // last3/first3 should not exist anymore
        $this->assertNull($expanded->get2('last3'));
        $this->assertNull($expanded->get2('first3'));
    }

    /**
     * Test case 3: Template with properly numbered authors (no gaps)
     * Should remain unchanged
     */
    public function testAuthorNumbering_AlreadyContiguous(): void {
        $text = '{{cite journal|last1=Smith|first1=John|last2=Doe|first2=Jane|title=Test}}';
        $expanded = $this->process_citation($text);
        
        // Authors should remain properly numbered
        $this->assertSame('Smith', $expanded->get2('last1'));
        $this->assertSame('John', $expanded->get2('first1'));
        $this->assertSame('Doe', $expanded->get2('last2'));
        $this->assertSame('Jane', $expanded->get2('first2'));
        $this->assertNull($expanded->get2('last3'));
    }

    /**
     * Test case 4: Multiple gaps in author numbering
     * Should compact all to be contiguous
     */
    public function testAuthorNumbering_MultipleGaps(): void {
        $text = '{{cite journal|last2=Second|first2=Author|last5=Fifth|first5=Author|last7=Seventh|first7=Author|title=Test}}';
        $expanded = $this->process_citation($text);
        
        // All authors should be renumbered to 1, 2, 3
        $this->assertSame('Second', $expanded->get2('last1'));
        $this->assertSame('Author', $expanded->get2('first1'));
        $this->assertSame('Fifth', $expanded->get2('last2'));
        $this->assertSame('Author', $expanded->get2('first2'));
        $this->assertSame('Seventh', $expanded->get2('last3'));
        $this->assertSame('Author', $expanded->get2('first3'));
        $this->assertNull($expanded->get2('last4'));
        $this->assertNull($expanded->get2('last5'));
    }

    /**
     * Test case 5: Author with both last/first and author parameter
     * Should keep them grouped together during compaction
     */
    public function testAuthorNumbering_MixedParameterTypes(): void {
        $text = '{{cite journal|author3=Third Author|last5=Fifth|first5=Author|title=Test}}';
        $expanded = $this->process_citation($text);
        
        // Should be compacted to author1 and last2/first2
        $this->assertSame('Third Author', $expanded->get2('author1'));
        $this->assertSame('Fifth', $expanded->get2('last2'));
        $this->assertSame('Author', $expanded->get2('first2'));
        $this->assertNull($expanded->get2('author3'));
        $this->assertNull($expanded->get2('last3'));
    }

    /**
     * Test case 6: High-numbered orphaned parameters should be removed
     * When compacting from high numbers, leftover parameters should be cleaned
     */
    public function testAuthorNumbering_RemoveOrphanedHighNumbers(): void {
        $text = '{{cite journal|last10=Tenth|first10=Author|last11=Eleventh|first11=Author|title=Test}}';
        $expanded = $this->process_citation($text);
        
        // Should be compacted to 1 and 2, with originals removed
        $this->assertSame('Tenth', $expanded->get2('last1'));
        $this->assertSame('Author', $expanded->get2('first1'));
        $this->assertSame('Eleventh', $expanded->get2('last2'));
        $this->assertSame('Author', $expanded->get2('first2'));
        $this->assertNull($expanded->get2('last10'));
        $this->assertNull($expanded->get2('last11'));
    }
}
