<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class CitationInternationalCasingTest extends testBaseClass {
    public function testCaseExpansionsInJournalAndTitleKeepTheirOrder(): void {
        $template = $this->make_citation('{{cite journal|journal=ﬁnancial ﬂow}}');
        $template->tidy_parameter('journal');
        $this->assertSame('Financial Flow', $template->get('journal'));
        $this->assertTrue($template->add_if_new('title', 'acta physica ß'));
        $this->assertSame('Acta physica ẞ', $template->get('title'));
        $this->assertSame('Acta Physica A', title_capitalization('acta Physica A', false));
    }

    public function testFrenchElisionsRecognizeLettersBeyondLatinOne(): void {
        $this->assertSame("Étude d'Œuvres et l'Œuvre", title_capitalization("Étude D'Œuvres et L'Œuvre", true));
    }

    public function testGermanPeriodicalPhrasePreservesPartialLink(): void {
        $template = $this->make_citation('{{cite journal|title=Example|journal=MAGAZIN FÜR [[Forschung]]}}');
        $template->tidy_parameter('journal');
        $this->assertStringContainsString('[[Forschung]]', $template->get('journal'));
    }
}
