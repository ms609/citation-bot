<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

/**
 * Zotero often reports only a year.  strtotime('2015') reads that as a time of
 * day (8:15pm today), so the page was judged to have changed since the access
 * date and its metadata was thrown away.
 */
final class ZoteroYearOnlyDateTest extends testBaseClass {

    private function expand_with_date(string $result_date, string $access_date): Template {
        $template = $this->make_citation('{{cite web|url=https://example.test/page|access-date=' . $access_date . '}}');
        $item = (object) [
            'title' => 'Offline year only title',
            'itemType' => 'webpage',
            'date' => $result_date,
        ];
        Zotero::process_zotero_response(
            json_encode([$item], JSON_THROW_ON_ERROR),
            $template,
            'https://example.test/page',
            (int) strtotime($access_date)
        );
        return $template;
    }

    public function testYearBeforeAccessDateDoesNotLookLikeAChange(): void {
        foreach (['2015', '2005', '2019', '1955'] as $year) {
            $template = $this->expand_with_date($year, '2020-01-05');
            $this->assertSame('Offline year only title', $template->get2('title'), 'Year ' . $year);
        }
    }

    public function testYearOfAccessDateDoesNotLookLikeAChange(): void {
        $template = $this->expand_with_date('2020', '2020-06-05');
        $this->assertSame('Offline year only title', $template->get2('title'));
    }

    public function testYearAfterAccessDateStillCountsAsChanged(): void {
        $template = $this->expand_with_date('2023', '2020-01-05');
        $this->assertNull($template->get2('title'));
    }

    public function testFullDateAfterAccessDateStillCountsAsChanged(): void {
        $template = $this->expand_with_date('2023-03-04', '2020-01-05');
        $this->assertNull($template->get2('title'));
    }

    public function testFullDateBeforeAccessDateIsStillFine(): void {
        $template = $this->expand_with_date('2015-03-04', '2020-01-05');
        $this->assertSame('Offline year only title', $template->get2('title'));
    }
}
