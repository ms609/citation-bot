<?php
declare(strict_types=1);

/**
 * The British spelling of the parameter converts cite web just like the American one.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditEncyclopaediaSpellingTest extends testBaseClass {

    public function testAmericanSpellingConverts(): void {
        $template = $this->make_citation('{{cite web|url=http://example.com/entry|title=An entry|encyclopedia=A Big Encyclopedia}}');
        $template->tidy_parameter('encyclopedia');
        $this->assertSame('cite encyclopedia', $template->wikiname());
    }

    public function testBritishSpellingConverts(): void {
        $template = $this->make_citation('{{cite web|url=http://example.com/entry|title=An entry|encyclopaedia=A Big Encyclopaedia}}');
        $template->tidy_parameter('encyclopaedia');
        $this->assertSame('cite encyclopedia', $template->wikiname());
    }
}
