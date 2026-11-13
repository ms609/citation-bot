<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once dirname(__DIR__, 2) . '/src/includes/constants/translations.php';

/**
 * Wikis proposed in #6143 use English CS1 template names, so renaming a
 * template there must keep the English name (see templateRenameLanguageTest).
 *
 * Each case boots setup.php for one wiki in its own process, since WIKI_BASE is
 * fixed at bootstrap time. A wiki that is not yet in SUPPORTED_WIKIS is skipped,
 * so the case starts running when that wiki is enabled.
 *
 * Deliberately does NOT extend testBaseClass, which would load setup.php for 'en'.
 */
final class templateRenameNewWikisTest extends PHPUnit\Framework\TestCase {

    /** @return array<string, array{string}> */
    public static function newWikis(): array {
        $data = [];
        foreach (['zh', 'uk', 'ms', 'th', 'he', 'hi', 'te', 'hu', 'et', 'hy', 'az', 'ne', 'ml', 'pa'] as $wiki) {
            $data[$wiki] = [$wiki];
        }
        return $data;
    }

    #[DataProvider('newWikis')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_english_template_names_stay_english(string $wiki): void {
        if (!in_array($wiki, SUPPORTED_WIKIS, true)) {
            $this->markTestSkipped("$wiki is not enabled yet");
        }
        putenv('PUBLIC_BASE_URL=https://citations.toolforge.org');
        putenv('ALLOWED_HOSTS=citations.toolforge.org');
        putenv('ALLOWED_ORIGINS=https://citations.toolforge.org');
        $_SERVER['HTTP_HOST'] = 'citations.toolforge.org';
        $_GET['wiki_base'] = $wiki;
        require dirname(__DIR__, 2) . '/src/includes/setup.php';

        $this->assertSame($wiki, WIKI_BASE);
        $this->assertSame('https://' . $wiki . '.wikipedia.org/w/api.php', API_ROOT);
        $this->assertNotContains($wiki, ENGLISH_WIKI);

        $template = new Template();
        $template->parse_text('{{Cite web|url=https://www.tandfonline.com/action/cookieAbsent|title=Patriotic Chinese Triads and Secret Societies|last=Martin|first=Purbrick|journal=Asian Affairs|doi=10.1080/03068374.2019.1636515|url-status=live|access-date=2026-09-30}}');
        $this->assertSame('cite web', $template->wikiname());
        $this->assertTrue($template->should_be_processed());
        $template->change_name_to('cite journal');
        $this->assertStringStartsWith('{{Cite journal|', $template->parsed_text());
        $this->assertStringContainsString('doi=10.1080/03068374.2019.1636515', $template->parsed_text());
    }
}
