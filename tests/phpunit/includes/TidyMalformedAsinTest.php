<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

/**
 * An ASIN that is already in a citation is cleaned up in tidy(), the same way
 * malformed arXiv, bibcode and PMC values already are.  CS1 flags anything that
 * is not ten upper-case letters or digits with "Check |asin= value".
 */
final class TidyMalformedAsinTest extends testBaseClass {

    public function testTooShortAsinIsRemoved(): void {
        $template = $this->make_citation('{{cite web|url=https://example.com|title=X|asin=12345}}');
        $template->tidy_parameter('asin');
        $this->assertNull($template->get2('asin'));
        $this->assertNull($template->get2('isbn'));
    }

    public function testTooLongAsinIsRemoved(): void {
        $template = $this->make_citation('{{cite web|url=https://example.com|title=X|asin=B0002TV0K8X}}');
        $template->tidy_parameter('asin');
        $this->assertNull($template->get2('asin'));
    }

    public function testJunkAsinIsRemoved(): void {
        $template = $this->make_citation('{{cite web|url=https://example.com|title=X|asin=xxxxxx}}');
        $template->tidy_parameter('asin');
        $this->assertNull($template->get2('asin'));
    }

    public function testMalformedAsinIsRemovedEvenWhenIsbnIsPresent(): void {
        // The ISBN is the useful identifier; a broken ASIN next to it only triggers a maintenance category
        $template = $this->make_citation('{{cite book|title=X|isbn=0226845494|asin=oops}}');
        $template->tidy_parameter('asin');
        $this->assertNull($template->get2('asin'));
        $this->assertSame('0226845494', $template->get2('isbn'));
    }

    public function testLowerCaseAsinIsUpperCased(): void {
        $template = $this->make_citation('{{cite book|title=X|asin=b0002tv0k8}}');
        $template->tidy_parameter('asin');
        $this->assertSame('B0002TV0K8', $template->get2('asin'));
    }

    public function testValidLetterAsinIsUntouched(): void {
        $template = $this->make_citation('{{cite book|title=X|asin=B0002TV0K8}}');
        $template->tidy_parameter('asin');
        $this->assertSame('B0002TV0K8', $template->get2('asin'));
    }

    public function testValidLetterAsinIsUntouchedWithIsbn(): void {
        $template = $this->make_citation('{{cite book|title=X|asin=B0002TV0K8|isbn=0226845494}}');
        $template->tidy_parameter('asin');
        $this->assertSame('B0002TV0K8', $template->get2('asin'));
        $this->assertSame('0226845494', $template->get2('isbn'));
    }

    public function testNumericAsinThatIsAnIsbnStillMovesToIsbn(): void {
        $template = $this->make_citation('{{cite book|title=X|asin=0671748750}}');
        $template->tidy_parameter('asin');
        $this->assertSame('0671748750', $template->get2('isbn'));
        $this->assertNull($template->get2('asin'));
    }

    public function testAsinStartingWith63IsNotTreatedAsIsbn(): void {
        $template = $this->make_citation('{{cite book|title=X|asin=6301234561}}');
        $template->tidy_parameter('asin');
        $this->assertSame('6301234561', $template->get2('asin'));
        $this->assertNull($template->get2('isbn'));
    }

    public function testHyphenatedIsbnInAsinIsLeftForAHuman(): void {
        // Not a valid ASIN, but it is somebody's real data; do not delete it when an ISBN already exists
        $template = $this->make_citation('{{cite book|title=X|isbn=0226845494|asin=0-671-74875-0}}');
        $template->tidy_parameter('asin');
        $this->assertSame('0-671-74875-0', $template->get2('asin'));
    }

    public function testEmptyAsinIsLeftAlone(): void {
        $template = $this->make_citation('{{cite book|title=X|asin=|isbn=}}');
        $template->tidy_parameter('asin');
        $this->assertSame('', $template->get2('asin'));
    }

    public function testAddIfNewRefusesMalformedAsin(): void {
        $template = $this->make_citation('{{cite book|title=X}}');
        $this->assertFalse($template->add_if_new('asin', 'ABC'));
        $this->assertFalse($template->add_if_new('asin', 'B0002TV0K8X'));
        $this->assertFalse($template->add_if_new('asin', '12345'));
        $this->assertNull($template->get2('asin'));
    }

    public function testAddIfNewStillAddsWellFormedAsin(): void {
        $template = $this->make_citation('{{cite book|title=X}}');
        $this->assertTrue($template->add_if_new('asin', 'B0002TV0K8'));
        $this->assertSame('B0002TV0K8', $template->get2('asin'));
    }

    public function testAmazonUrlWithShortNumericIdIsKept(): void {
        // Tidy removes malformed ASINs, so the URL must not be swapped for one
        $template = $this->prepare_citation('{{cite web|title=Some book|website=Amazon|url=https://www.amazon.com/dp/12345}}');
        $this->assertSame('https://www.amazon.com/dp/12345', $template->get2('url'));
        $this->assertNull($template->get2('asin'));
    }

    public function testAmazonUrlWithTenDigitIdStillBecomesIsbn(): void {
        $template = $this->prepare_citation('{{cite web|url=http://www.amazon.com/On-Origin-Phyla-James-Valentine/dp/0226845494|accessdate=2012-04-20}}');
        $this->assertSame('0226845494', $template->get2('isbn'));
        $this->assertNull($template->get2('asin'));
        $this->assertNull($template->get2('url'));
    }

    public function testWholeTidyRemovesMalformedAsin(): void {
        $template = $this->make_citation('{{cite web|url=https://example.com|title=X|website=Example|asin=12345}}');
        $template->tidy();
        $this->assertNull($template->get2('asin'));
    }
}
