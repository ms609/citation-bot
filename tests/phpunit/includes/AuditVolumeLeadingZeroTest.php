<?php
declare(strict_types=1);

/**
 * Leading zeroes are removed from a volume, and the result is saved.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditVolumeLeadingZeroTest extends testBaseClass {

    public function testLeadingZeroesAreStripped(): void {
        $template = $this->make_citation('{{cite journal|volume=007}}');
        $template->tidy_parameter('volume');
        $this->assertSame('7', $template->get2('volume'));
    }

    public function testSingleZeroIsKept(): void {
        $template = $this->make_citation('{{cite journal|volume=0}}');
        $template->tidy_parameter('volume');
        $this->assertSame('0', $template->get2('volume'));
    }

    public function testZeroFollowedByLetterIsKept(): void {
        $template = $this->make_citation('{{cite journal|volume=0A}}');
        $template->tidy_parameter('volume');
        $this->assertSame('0A', $template->get2('volume'));
    }
}
