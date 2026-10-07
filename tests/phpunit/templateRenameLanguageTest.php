<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Guards template-name localization in change_name_to() on non-English wikis.
 *
 * Regression test: on sr.wikipedia (which uses English template names such as
 * {{Cite web}}), renaming to cite journal must keep the English name. The old
 * code matched the current name against the English VALUES of every language
 * map with no break, so the last map (Vietnamese) always won and produced
 * {{Chú thích tập san học thuật}} on Serbian pages.
 *
 * Deliberately does NOT extend testBaseClass: each test runs in a separate
 * process and loads setup.php itself with the wanted wiki_base, since
 * WIKI_BASE is fixed at bootstrap time.
 */
final class templateRenameLanguageTest extends PHPUnit\Framework\TestCase {

    private static function boot_sr_wiki(): void {
        putenv('PUBLIC_BASE_URL=https://citations.toolforge.org');
        putenv('ALLOWED_HOSTS=citations.toolforge.org');
        putenv('ALLOWED_ORIGINS=https://citations.toolforge.org');
        $_SERVER['HTTP_HOST'] = 'citations.toolforge.org';
        $_GET['wiki_base'] = 'sr';
        require dirname(__DIR__, 2) . '/src/includes/setup.php';
    }

    private static function boot_vi_wiki(): void {
        putenv('PUBLIC_BASE_URL=https://citations.toolforge.org');
        putenv('ALLOWED_HOSTS=citations.toolforge.org');
        putenv('ALLOWED_ORIGINS=https://citations.toolforge.org');
        $_SERVER['HTTP_HOST'] = 'citations.toolforge.org';
        $_GET['wiki_base'] = 'vi';
        require dirname(__DIR__, 2) . '/src/includes/setup.php';
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_english_name_stays_english_on_sr_wiki(): void {
        self::boot_sr_wiki();
        $template = new Template();
        $template->parse_text('{{Cite web|url=https://www.tandfonline.com/action/cookieAbsent|title=Patriotic Chinese Triads and Secret Societies|last=Martin|first=Purbrick|website=www.tandfonline.com|doi=10.1080/03068374.2019.1636515|url-status=live|access-date=2026-09-30}}');
        $template->change_name_to('cite journal');
        $this->assertStringStartsWith('{{Cite journal|', $template->parsed_text());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_vietnamese_name_maps_within_vietnamese_on_vi_wiki(): void {
        self::boot_vi_wiki();
        $template = new Template();
        $template->parse_text('{{Chú thích web|url=https://www.tandfonline.com/action/cookieAbsent|title=Patriotic Chinese Triads and Secret Societies|last=Martin|first=Purbrick|website=www.tandfonline.com|doi=10.1080/03068374.2019.1636515|url-status=live|access-date=2026-09-30}}');
        $template->change_name_to('cite journal');
        $this->assertStringStartsWith('{{Chú thích tập san học thuật|', $template->parsed_text());
    }
}
