<?php
declare(strict_types=1);

/*
 * Tests for pages that crash the bot
 */

require_once __DIR__ . '/../testBaseClass.php';

final class CrashTest extends testBaseClass {

    protected function setUp(): void {
        if (BAD_PAGE_API === '') {
            $this->markTestSkipped();
        }
        parent::setUp();
    }

    public function testBadPage2(): void {  // Use this when debugging pages that crash the bot
        $page = new TestPage();
        $bad_page = BAD_PAGE_API;
        $bad_page = str_replace(' ', '_', $bad_page);
        $page->get_text_from($bad_page);
        // Turn everything on
        AdsAbsControl::big_back_on();
        AdsAbsControl::small_back_on();
        Zotero::unblock_zotero();
        $page->expand_text();
        $this->assertTrue(false); // help prevent us from git committing with a website included
    }
}
