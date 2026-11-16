<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class TemplateTitleJournalHelperTest extends testBaseClass {
    public function testSeriesCannotDuplicateJournalOrTitle(): void {
        $template = $this->make_citation('{{cite journal|title=Article|journal=Example Journal}}');
        $this->assertFalse($template->add_if_new('series', 'Example Journal'));
        $this->assertFalse($template->add_if_new('series', 'Article'));
        $this->assertFalse($template->has('series'));
    }

    public function testBookTitleIsMovedToTitleAndExistingTitleToChapter(): void {
        $template = $this->make_citation('{{cite book|title=Chapter name|book-title=Book name}}');
        $template->tidy_parameter('book-title');
        $this->assertSame('Book name', $template->get('title'));
        $this->assertSame('Chapter name', $template->get('chapter'));
        $this->assertFalse($template->has('book-title'));
    }

    public function testBookTitleFillsMissingTitle(): void {
        $template = $this->make_citation('{{cite book|book-title=Book name}}');
        $template->tidy_parameter('book-title');
        $this->assertSame('Book name', $template->get('title'));
        $this->assertFalse($template->has('book-title'));
    }
}
