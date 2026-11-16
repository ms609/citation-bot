<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

/** No network access: test the Zotero response-to-template date path directly. */
final class ZoteroFutureDateTest extends testBaseClass {
    public function testFutureDatesAreIgnoredWithoutLosingOtherMetadata(): void {
        $nextMonth = strtotime('first day of next month');
        $this->assertNotFalse($nextMonth);
        $futureDates = [
            date('Y-m-d', strtotime('tomorrow')),
            date('j F Y', strtotime('tomorrow')),
            date('Y-m-d', strtotime('+4 days')),
            date('F Y', $nextMonth),
            date('Y-m', $nextMonth),
            (string) ((int) date('Y') + 1),
            ((int) date('Y') + 1) . '-01-01',
        ];
        foreach ($futureDates as $source) {
            foreach ([false, true] as $withAccessDate) {
                $template = $this->expand($source, $withAccessDate);
                $this->assertNull($template->get2('date'), $source);
                $this->assertSame('Offline future-date title', $template->get2('title'), $source);
            }
        }
    }

    public function testExistingNearTodayDateSafeguardIsPreserved(): void {
        // Template::add_if_new('date') already rejects dates within 14 days
        // of now. Zotero should not bypass that unrelated safeguard.
        foreach ([
            date('Y-m-d'),
            date('Y-m-d', strtotime('yesterday')),
            date('Y-m-d', strtotime('-7 days')),
        ] as $source) {
            $template = $this->expand($source, true);
            $this->assertNull($template->get2('date'), $source);
            $this->assertSame('Offline future-date title', $template->get2('title'), $source);
        }
    }

    public function testOlderPastDatesAndCurrentYearRemainUsable(): void {
        foreach ([
            date('Y-m-d', strtotime('-30 days')),
            date('F Y', strtotime('-3 months')),
            date('Y'),
        ] as $source) {
            $template = $this->expand($source, true);
            $this->assertNotNull($template->get2('date'), $source);
            $this->assertSame('Offline future-date title', $template->get2('title'), $source);
        }
    }

    public function testMissingAccessTimestampStillHonorsExistingEventDates(): void {
        $url = 'https://example.test/zotero-future-date';
        $response = json_encode([[
            'itemType' => 'webpage',
            'title' => 'Should not import',
            'date' => '2021-01-01',
        ]], JSON_THROW_ON_ERROR);
        foreach (['access-date', 'accessdate', 'archive-date', 'archivedate'] as $param) {
            $template = $this->make_citation('{{cite web|url=' . $url . '|' . $param . '=2020-01-01}}');
            Zotero::process_zotero_response($response, $template, $url, 0);
            $this->assertNull($template->get2('title'), $param);
            $this->assertNull($template->get2('date'), $param);
        }
    }

    public function testFutureZoteroDateDoesNotEraseExistingCitationDate(): void {
        $template = $this->expand(date('Y-m-d', strtotime('+4 days')), false, '2024');
        $this->assertSame('2024', $template->get2('date'));
        $this->assertSame('Offline future-date title', $template->get2('title'));
    }

    private function expand(string $source, bool $withAccessDate, ?string $existingDate = null): Template {
        $url = 'https://example.test/zotero-future-date';
        $citation = '{{cite web|url=' . $url;
        if ($withAccessDate) {
            $citation .= '|access-date=' . date('Y-m-d');
        }
        if ($existingDate !== null) {
            $citation .= '|date=' . $existingDate;
        }
        $template = $this->make_citation($citation . '}}');
        Zotero::process_zotero_response(
            json_encode([[
                'itemType' => 'webpage',
                'title' => 'Offline future-date title',
                'date' => $source,
            ]], JSON_THROW_ON_ERROR),
            $template,
            $url,
            $withAccessDate ? (int) strtotime('today') : 0
        );
        return $template;
    }
}
