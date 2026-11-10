<?php
declare(strict_types=1);

/**
 * CSL-JSON from doi.org carries the page range under "page".
 */

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

final class AuditDoiJsonPageKeyTest extends testBaseClass {

    public function testPageKeyIsRead(): void {
        $template = $this->make_citation('{{cite journal|title=A title}}');
        process_doi_json($template, '10.1000/page-key-test', [
            'type' => 'journal-article',
            'title' => ['A title'],
            'page' => '177-209',
        ]);
        $this->assertSame('177–209', $template->get2('pages'));
    }

    public function testLegacyPagesKeyIsStillRead(): void {
        $template = $this->make_citation('{{cite journal|title=A title}}');
        process_doi_json($template, '10.1000/pages-key-test', [
            'type' => 'journal-article',
            'title' => ['A title'],
            'pages' => '177-209',
        ]);
        $this->assertSame('177–209', $template->get2('pages'));
    }
}
