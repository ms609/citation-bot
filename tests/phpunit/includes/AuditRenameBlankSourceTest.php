<?php
declare(strict_types=1);

/**
 * Renaming an empty parameter onto a populated one must not delete the
 * populated value.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditRenameBlankSourceTest extends testBaseClass {

    public function testBlankSourceDoesNotDestroyDestination(): void {
        $template = $this->make_citation('{{cite journal|journal=Nature|work=}}');
        $template->rename('work', 'journal');
        $this->assertSame('Nature', $template->get2('journal'));
        $this->assertNull($template->get2('work'));
    }

    public function testPopulatedSourceStillReplacesDestination(): void {
        $template = $this->make_citation('{{cite journal|journal=Old|work=New}}');
        $template->rename('work', 'journal');
        $this->assertSame('New', $template->get2('journal'));
        $this->assertNull($template->get2('work'));
    }

    public function testBlankSourceOntoBlankDestinationStillRenames(): void {
        $template = $this->make_citation('{{cite journal|work=}}');
        $template->rename('work', 'journal');
        $this->assertNull($template->get2('work'));
        $this->assertSame('', $template->get2('journal'));
    }
}
