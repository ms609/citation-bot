<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class RisInteractionTest extends testBaseClass {
    public function testJournalRisPopulatesRelatedCitationFieldsTogether(): void {
        $template = $this->make_citation('{{cite journal}}');
        $data = implode("\n", [
            'TY - JOUR',
            'TI - Interaction testing in practice',
            'VL - 12',
            'IS - 3',
            'SP - 101',
            'EP - 109',
            'UR - https://example.invalid/article',
            'ER -',
        ]);

        expand_by_RIS($template, $data, false);

        $this->assertSame('Interaction testing in practice', $template->get2('title'));
        $this->assertSame('12', $template->get2('volume'));
        $this->assertSame('3', $template->get2('issue'));
        $this->assertSame('101–109', $template->get2('pages'));
        $this->assertNull($template->get2('url'));
    }

    public function testChapterRisRoutesContainerAndChapterFieldsCorrectly(): void {
        $template = $this->make_citation('{{cite book}}');
        $data = implode("\n", [
            'TY - CHAP',
            'TI - A chapter from the interaction layer',
            'T2 - The Container Book',
            'PB - Example Press',
            'SP - 7',
            'EP - 19',
            'ER -',
        ]);

        expand_by_RIS($template, $data, false);

        $this->assertSame('A chapter from the interaction layer', $template->get2('chapter'));
        $this->assertSame('The Container Book', $template->get2('title'));
        $this->assertSame('Example Press', $template->get2('publisher'));
        $this->assertSame('7–19', $template->get2('pages'));
    }

    public function testRisUrlCanBeEnabledWithoutChangingOtherRouting(): void {
        $template = $this->make_citation('{{cite journal}}');
        $data = implode("\n", [
            'TY - JOUR',
            'TI - URL interaction',
            'UR - https://example.invalid/article',
            'ER -',
        ]);

        expand_by_RIS($template, $data, true);

        $this->assertSame('URL interaction', $template->get2('title'));
        $this->assertSame('https://example.invalid/article', $template->get2('url'));
    }
}
