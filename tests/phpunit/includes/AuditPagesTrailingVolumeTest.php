<?php
declare(strict_types=1);

/**
 * A volume written after the page range is moved to volume, not thrown away.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditPagesTrailingVolumeTest extends testBaseClass {

    public function testTrailingVolumeIsMovedWhenVolumeIsBlank(): void {
        $template = $this->make_citation('{{cite book|title=A title|pages=123–130, vol. 5}}');
        $template->tidy_parameter('pages');
        $this->assertSame('123–130', $template->get2('pages'));
        $this->assertSame('5', $template->get2('volume'));
    }

    public function testExistingVolumeIsNotOverwritten(): void {
        $template = $this->make_citation('{{cite book|title=A title|volume=7|pages=123–130, vol. 5}}');
        $template->tidy_parameter('pages');
        $this->assertSame('123–130', $template->get2('pages'));
        $this->assertSame('7', $template->get2('volume'));
    }

    public function testPlainPagesAreUntouched(): void {
        $template = $this->make_citation('{{cite book|title=A title|pages=123–130}}');
        $template->tidy_parameter('pages');
        $this->assertSame('123–130', $template->get2('pages'));
        $this->assertNull($template->get2('volume'));
    }
}
