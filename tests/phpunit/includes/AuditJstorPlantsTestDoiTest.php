<?php
declare(strict_types=1);

/**
 * A JSTOR Global Plants test-prefix DOI is replaced by its stable URL.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditJstorPlantsTestDoiTest extends testBaseClass {

    public function testPlantsDoiBecomesUrl(): void {
        $template = $this->make_citation('{{cite journal|title=Holotype of Persoonia terminalis|doi=10.5555/al.ap.specimen.nsw225972}}');
        $template->tidy_parameter('doi');
        $this->assertNull($template->get2('doi'));
        $this->assertSame('https://plants.jstor.org/stable/10.5555/al.ap.specimen.nsw225972', $template->get2('url'));
    }

    public function testOtherTestPrefixDoiIsJustRemoved(): void {
        $template = $this->make_citation('{{cite journal|title=A title|doi=10.5555/12345678}}');
        $template->tidy_parameter('doi');
        $this->assertNull($template->get2('doi'));
        $this->assertNull($template->get2('url'));
    }
}
