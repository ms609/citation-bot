<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class ChangeNameToCiteBookTest extends testBaseClass {

    public function testJournalContainerBecomesBookTitle(): void {
        // A conference paper first formatted as cite journal: the journal= is really the proceedings
        $text = '{{cite journal|title=Paper Title|journal=FM 2014: Formal Methods|isbn=978-3-319-06409-3}}';
        $template = $this->make_citation($text);
        $template->change_name_to('cite book');
        $this->assertSame('{{cite book|chapter=Paper Title|title=FM 2014: Formal Methods|isbn=978-3-319-06409-3}}', $template->parsed_text());
        $this->assertNull($template->get2('journal'));
    }

    public function testBookSeriesJournalIsNotMadeIntoTitle(): void {
        // Book series are routed into series= by the journal handling, never into title=
        $text = '{{cite journal|title=Paper Title|journal=Lecture Notes in Computer Science}}';
        $template = $this->make_citation($text);
        $template->change_name_to('cite book');
        $this->assertSame('Paper Title', $template->get2('title'));
        $this->assertNull($template->get2('chapter'));
    }

    public function testExistingChapterBlocksJournalRemap(): void {
        // Remapping would have to overwrite the chapter, so leave everything for a human
        $text = '{{cite journal|title=T|journal=J|chapter=C}}';
        $template = $this->make_citation($text);
        $template->change_name_to('cite book');
        $this->assertSame('cite book', $template->wikiname());
        $this->assertSame('T', $template->get2('title'));
        $this->assertSame('C', $template->get2('chapter'));
        $this->assertSame('J', $template->get2('journal'));
    }

    public function testJournalSameAsTitleIsDropped(): void {
        $text = '{{cite journal|title=Same Name|journal=Same Name|isbn=978-3-319-06409-3}}';
        $template = $this->make_citation($text);
        $template->change_name_to('cite book');
        $this->assertSame('{{cite book|title=Same Name|isbn=978-3-319-06409-3}}', $template->parsed_text());
    }

    public function testExistingCiteBookIsUntouched(): void {
        // No conversion happens, so nothing may be moved around
        $text = '{{cite book|title=T|journal=J}}';
        $template = $this->make_citation($text);
        $template->change_name_to('cite book');
        $this->assertSame($text, $template->parsed_text());
    }
}
