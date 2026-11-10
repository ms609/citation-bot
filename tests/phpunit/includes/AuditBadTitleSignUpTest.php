<?php
declare(strict_types=1);

/**
 * BAD_TITLES is compared with a trimmed, lower-cased title, so an entry with
 * edge whitespace can never match.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditBadTitleSignUpTest extends testBaseClass {

    public function testSignUpIsABadTitle(): void {
        $this->assertContains('sign up', BAD_TITLES);
    }

    public function testNoEntryHasEdgeWhitespace(): void {
        foreach (BAD_TITLES as $title) {
            $this->assertSame(mb_trim($title), $title);
        }
    }

    public function testSignUpTitleIsNotAdded(): void {
        $template = $this->make_citation('{{cite web|url=http://example.com/page}}');
        $this->assertFalse($template->add_if_new('title', 'Sign up'));
        $this->assertNull($template->get2('title'));
    }
}
