<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class MemoirsSeriesTest extends testBaseClass {
    private const string DOI = '10.1090/S0065-9266-09-00565-1';
    private const string TITLE = 'Generalized Noncrossing Partitions and Combinatorics of Coxeter Groups';
    private const string FULL_NAME = 'Memoirs of the American Mathematical Society';

    public function testDoiExpansionKeepsTheReportedSeriesCitation(): void {
        $template = $this->make_citation('{{citation|last=Armstrong|first=Drew|year=2009|title=' . self::TITLE .
            '|publisher=American Mathematical Society|series=Memoirs of the AMS|number=949|doi=' . self::DOI .
            '|arxiv=math/0611106}}');

        process_doi_json($template, self::DOI, [
            'type' => 'journal-article',
            'title' => [self::TITLE],
            'container-title' => [self::FULL_NAME],
            'volume' => '202',
        ]);

        $this->assertSame('citation', $template->wikiname());
        $this->assertSame('Memoirs of the AMS', $template->get('series'));
        $this->assertSame('949', $template->get('number'));
        $this->assertSame('202', $template->get('volume'));
        $this->assertSame(self::DOI, $template->get('doi'));
        $this->assertNull($template->get2('journal'));
    }

    #[DataProvider('mixedCitations')]
    public function testCleansUpAnExistingDuplicate(string $name, string $series, string $parameter, string $journal): void {
        $input = '{{' . $name . '|title=' . self::TITLE . '|series=' . $series . '|' . $parameter . '=' . $journal . '}}';
        $template = $this->make_citation($input);
        $template->tidy_parameter($parameter);
        $template->final_tidy();

        $this->assertSame($name, $template->wikiname());
        $this->assertSame($series, $template->get('series'));
        $this->assertSame(self::TITLE, $template->get('title'));
        $this->assertNull($template->get2($parameter));
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function mixedCitations(): array {
        return [
            'exact report' => ['citation', 'Memoirs of the AMS', 'journal', self::FULL_NAME],
            'book with a linked series' => ['cite book', '[[Memoirs of the American Mathematical Society|Memoirs of the AMS]]', 'journal', self::FULL_NAME],
            'abbreviated periodical metadata' => ['citation', self::FULL_NAME, 'periodical', 'Mem. Amer. Math. Soc.'],
            'uppercase abbreviation' => ['citation', 'Memoirs of the AMS', 'journal', 'MEM. AMER. MATH. SOC.'],
        ];
    }

    public function testJournalStyleWithoutSeriesStillUsesJournalMetadata(): void {
        $template = $this->make_citation('{{cite journal|title=' . self::TITLE . '}}');
        $this->assertTrue($template->add_if_new('journal', self::FULL_NAME));
        $this->assertSame('cite journal', $template->wikiname());
        $this->assertTrue(str_equivalent(self::FULL_NAME, $template->get('journal')));
        $this->assertNull($template->get2('series'));
    }

    public function testAnotherSocietyIsNotTreatedAsTheSameSeries(): void {
        $template = $this->make_citation('{{citation|title=Example|series=Memoirs of the American Meteorological Society}}');
        $this->assertTrue($template->add_if_new('journal', self::FULL_NAME));
        $this->assertTrue(str_equivalent(self::FULL_NAME, $template->get('journal')));
    }

    public function testExplicitJournalStyleKeepsItsJournal(): void {
        $template = $this->make_citation('{{cite journal|title=Example|journal=' . self::FULL_NAME . '}}');
        $template->tidy_parameter('journal');
        $this->assertSame('cite journal', $template->wikiname());
        $this->assertTrue(str_equivalent(self::FULL_NAME, $template->get('journal')));
    }
}
