<?php

declare(strict_types=1);

/** Google Books Atom metadata branches tested with fixed, local XML fixtures. */
require_once dirname(__DIR__, 3) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class GoogleBooksCoveragePatch12Test extends testBaseClass {
    /** @return array<string, array{string}> */
    public static function unusableGoogleDateProvider(): array {
        return [
            'historical ISBN with dubious pre-1900 year' => ['1750'],
            'book feed without a year' => ['unknown'],
            'Google Books known placeholder date' => ['101-01-01'],
        ];
    }

    #[DataProvider('unusableGoogleDateProvider')]
    public function testUnusableDatesDoNotOverrideExistingCitation(string $date): void {
        $template = $this->make_citation('{{cite book|title=Existing work|date=2001}}');
        $xml = '<entry xmlns:dc="http://purl.org/dc/elements/1.1/">' .
            '<title>Imported work</title>' .
            '<dc:identifier>ISBN 978-0-393-30700-9</dc:identifier>' .
            '<dc:date>' . $date . '</dc:date>' .
            '</entry>';
        apply_google_book_details($template, $xml);
        $this->assertSame('Existing work', $template->get2('title'));
        $this->assertSame('2001', $template->get2('date'));
    }

    public function testFutureGoogleBooksDateIsIgnored(): void {
        $template = $this->make_citation('{{cite book|title=Known article}}');
        $future = (string) ((int) date('Y') + 5);
        $xml = '<entry xmlns:dc="http://purl.org/dc/elements/1.1/">' .
            '<title>Unverified publication date</title>' .
            '<dc:date>' . $future . '</dc:date>' .
            '</entry>';
        apply_google_book_details($template, $xml);
        $this->assertSame('Known article', $template->get2('title'));
        $this->assertNull($template->get2('date'));
    }

    /** @return array<string, array{string}> */
    public static function unwantedGoogleCreatorProvider(): array {
        return [
            'bulk publisher without author' => ['Gale Group'],
            'date mistakenly in author field' => ['A Contributor 2018'],
            'territory mistaken for author' => ['Springfield (Illinois)'],
        ];
    }

    #[DataProvider('unwantedGoogleCreatorProvider')]
    public function testUnusableCreatorIsNotAdded(string $creator): void {
        $template = $this->make_citation('{{cite book|title=Existing work}}');
        $xml = '<entry xmlns:dc="http://purl.org/dc/elements/1.1/">' .
            '<title>Imported work</title><dc:creator>' . $creator . '</dc:creator>' .
            '<dc:date>2010</dc:date></entry>';
        apply_google_book_details($template, $xml);
        $this->assertNull($template->get2('author1'));
        $this->assertNull($template->get2('last1'));
        $this->assertSame('Existing work', $template->get2('title'));
    }
}
