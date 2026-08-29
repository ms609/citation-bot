<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class PageConfigInteractionTest extends testBaseClass {
    public function testReusedPageResetsAllPerPageCitationConfiguration(): void {
        $page = new TestPage();
        $page->parse_text(
            '{{Use dmy dates}}' .
            '{{cs1 config|name-list-style=vanc|display-authors=etal}}' .
            '{{cite journal|title=First}}'
        );

        $this->assertSame(DateStyle::DATES_DMY, $page->get_date_style());
        $this->assertSame(VancStyle::NAME_LIST_STYLE_VANC, $page->get_name_list_style());
        $this->assertSame('etal', $page->get_page_display_authors());

        $page->parse_text('{{cite journal|title=Second}}');

        $this->assertSame(DateStyle::DATES_WHATEVER, $page->get_date_style());
        $this->assertSame(VancStyle::NAME_LIST_STYLE_DEFAULT, $page->get_name_list_style());
        $this->assertSame('', $page->get_page_display_authors());
    }
}
