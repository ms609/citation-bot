<?php
declare(strict_types=1);

/**
 * An archive URL that only lacks its scheme is repaired, not deleted.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditArchiveUrlSchemeTest extends testBaseClass {

    public function testSchemelessWaybackUrlIsRepaired(): void {
        $template = $this->make_citation('{{cite web|url=http://example.com/page|title=A title|archive-url=web.archive.org/web/20200102030405/http://example.com/page|archive-date=2020-01-02}}');
        $template->tidy_parameter('archive-url');
        $archive = $template->get2('archive-url');
        $this->assertNotNull($archive);
        $this->assertStringStartsWith('http', $archive);
        $this->assertStringContainsString('web.archive.org/web/20200102030405/http://example.com/page', $archive);
        $this->assertSame('2020-01-02', $template->get2('archive-date'));
    }

    public function testSaveCommandIsStillRemoved(): void {
        $template = $this->make_citation('{{cite web|url=http://example.com/page|title=A title|archive-url=https://web.archive.org/save/http://example.com/page}}');
        $template->tidy_parameter('archive-url');
        $this->assertNull($template->get2('archive-url'));
    }
}
