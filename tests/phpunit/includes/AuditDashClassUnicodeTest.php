<?php
declare(strict_types=1);

/**
 * A dash inside a character class needs the /u flag, otherwise the class
 * matches single bytes and can split a neighbouring multi-byte character.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditDashClassUnicodeTest extends testBaseClass {

    public function testPagePrefixWithEnDashIsStripped(): void {
        $template = $this->make_citation('{{cite journal|pages=pp. 12–15}}');
        $template->tidy_parameter('pages');
        $this->assertSame('12–15', $template->get2('pages'));
    }

    public function testDashPatternsInSourceAreUnicodeAware(): void {
        $root = dirname(__DIR__, 3) . '/src/includes/';
        $doi = (string) file_get_contents($root . 'api/APIdoi.php');
        $template = (string) file_get_contents($root . 'Template.php');
        $this->assertSame(2, mb_substr_count($doi, '(.................+)$~iu'));
        $this->assertStringContainsString("~^pp?\\. ([0-9\\-–\\s\\,]+)$~u", $template);
    }
}
