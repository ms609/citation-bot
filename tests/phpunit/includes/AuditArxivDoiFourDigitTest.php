<?php
declare(strict_types=1);

/**
 * arXiv DOIs for 2007-2014 identifiers (four digits after the dot) are converted like later ones.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditArxivDoiFourDigitTest extends testBaseClass {

    public function testFourDigitIdentifier(): void {
        $template = $this->make_citation('{{cite journal|doi=10.48550/arXiv.0704.0001|pmid=<!-- -->|pmc=<!-- -->}}');
        $template->tidy_parameter('doi');
        $this->assertNull($template->get2('doi'));
        $this->assertSame('0704.0001', $template->get2('eprint'));
    }

    public function testFiveDigitIdentifierStillWorks(): void {
        $template = $this->make_citation('{{cite journal|doi=10.48550/arXiv.1234.56789|pmid=<!-- -->|pmc=<!-- -->}}');
        $template->tidy_parameter('doi');
        $this->assertNull($template->get2('doi'));
        $this->assertSame('1234.56789', $template->get2('eprint'));
    }
}
