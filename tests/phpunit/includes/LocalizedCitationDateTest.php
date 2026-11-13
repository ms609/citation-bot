<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class LocalizedCitationDateTest extends testBaseClass {
    public function testSplitLocalizedDateUsesValidatedCalendarDay(): void {
        foreach ([
            ['février', '29', '29 February 2024'],
            ["Ma\u{0308}rz", '3', '3 March 2024'],
            ['février', '30', ''],
            ['inconnuë', '3', ''],
        ] as [$month, $day, $expected]) {
            $template = $this->make_citation('{{cite web|title=Example|month=' . $month . '|day=' . $day . '|year=2024}}');
            $template->tidy_parameter('month');
            $this->assertSame($expected, $template->get('date'));
            if ($expected === '') {
                $this->assertSame($day, $template->get('day'));
            }
        }
    }
}
