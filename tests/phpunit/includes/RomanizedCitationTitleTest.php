<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class RomanizedCitationTitleTest extends testBaseClass {
    public function testAccentedRomanizationCanAccompanyNativeTitle(): void {
        $template = $this->make_citation('{{cite web|script-title=ja:東京の歴史}}');
        $this->assertTrue($template->add_if_new('title', 'Tōkyō no rekishi'));
        $this->assertSame('Tōkyō no rekishi', $template->get('title'));
        $this->assertSame('ja:東京の歴史', $template->get('script-title'));

        $template = $this->make_citation('{{cite web|script-title=fr:Café historique}}');
        $this->assertFalse($template->add_if_new('title', 'Cafe historique'));
    }

    public function testCitoidRequiresExactNativeTitleWhenRomanizationDiffers(): void {
        $url = 'https://example.org/article';
        foreach (['東京の歴史' => '15 May 2020', '東京の地理' => ''] as $title => $date) {
            $template = $this->make_citation('{{cite web|title=Tōkyō no rekishi|script-title=ja-Jpan:東京の歴史|url=' . $url . '}}');
            Zotero::process_zotero_response(json_encode([[
                'itemType' => 'webpage', 'title' => $title, 'date' => '2020-05-15',
            ]], JSON_THROW_ON_ERROR), $template, $url, 0, true, true, true);
            $this->assertSame($date, $template->get('date'));
            $this->assertSame('Tōkyō no rekishi', $template->get('title'));
        }
    }
}
