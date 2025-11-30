<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';

final class CrashTest extends testBaseClass {

    public function testBadPage(): void {  // Use this when using PHPUnit to debug pages that crash the bot - There are better ways to debug it, but this allows debugging using GitHub's actions
        $page = new TestPage();
        $bad_page = BAD_PAGE_API;
        $bad_page = str_replace(' ', '_', $bad_page);
        if ($bad_page !== "") {
            Zotero::create_ch_zotero();
            WikipediaBot::make_ch();
            $page->get_text_from($bad_page);
            AdsAbsControl::big_back_on();
            AdsAbsControl::small_back_on();
            Zotero::unblock_zotero();
            $page->expand_text();
            AdsAbsControl::small_give_up();
            AdsAbsControl::big_give_up();
            Zotero::block_zotero();
            $this->assertTrue(false); // help prevent us from git committing with a website included
        }
        $this->assertTrue(true);
    }
}
