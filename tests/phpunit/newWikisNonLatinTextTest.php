<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once dirname(__DIR__, 2) . '/src/includes/constants/translations.php';

/**
 * Text in the native script of each #6143 wiki must pass through page parsing,
 * citation preparation/tidying and edit-summary generation unchanged, including
 * right-to-left marks (he) and zero-width joiners (ml).
 *
 * Each case boots setup.php for one wiki in its own process, since WIKI_BASE is
 * fixed at bootstrap time. A wiki that is not yet in SUPPORTED_WIKIS is skipped,
 * so the case starts running when that wiki is enabled.
 *
 * Deliberately does NOT extend testBaseClass, which would load setup.php for 'en'.
 */
final class newWikisNonLatinTextTest extends PHPUnit\Framework\TestCase {

    /** @return array<string, array{string, string, string, string, string, string}> wiki, script, title, journal, last, first */
    public static function nativeText(): array {
        return [
            'zh' => ['zh', 'Han', '蛋白质结构的预测', '自然科学', '王', '小明'],
            'uk' => ['uk', 'Cyrillic', 'Дослідження структури білків', 'Журнал біохімії', 'Шевченко', 'Олена'],
            'ms' => ['ms', 'Latin', 'Kajian struktur protein', 'Jurnal Biokimia', 'Abdul Rahman', 'Aishah'],
            'th' => ['th', 'Thai', 'การศึกษาโครงสร้างโปรตีน', 'วารสารชีวเคมี', 'สมชาย', 'สมหญิง'],
            'he' => ['he', 'Hebrew', "מחקר\u{200F} על מבנה חלבונים", 'כתב עת לביוכימיה', 'כהן', 'דוד'],
            'hi' => ['hi', 'Devanagari', 'प्रोटीन संरचना का अध्ययन', 'जैव रसायन पत्रिका', 'शर्मा', 'अनिता'],
            'te' => ['te', 'Telugu', 'ప్రోటీన్ నిర్మాణ అధ్యయనం', 'జీవరసాయన పత్రిక', 'రెడ్డి', 'లక్ష్మి'],
            'hu' => ['hu', 'Latin', 'Fehérjeszerkezetek vizsgálata', 'Biokémiai Szemle', 'Kovács', 'Zoltán'],
            'et' => ['et', 'Latin', 'Valgu struktuuri uurimine', 'Biokeemia Ajakiri', 'Tamm', 'Jüri'],
            'hy' => ['hy', 'Armenian', 'Սպիտակուցի կառուցվածքի ուսումնասիրություն', 'Կենսաքիմիայի հանդես', 'Պետրոսյան', 'Անի'],
            'az' => ['az', 'Latin', 'Zülal quruluşunun öyrənilməsi', 'Biokimya Jurnalı', 'Məmmədov', 'Əli'],
            'ne' => ['ne', 'Devanagari', 'प्रोटिन संरचनाको अध्ययन', 'जैवरसायन पत्रिका', 'श्रेष्ठ', 'सुनिता'],
            'ml' => ['ml', 'Malayalam', "പ്രോട്ടീന്\u{200D} ഘടനയുടെ പഠനം", 'ജൈവരസതന്ത്ര ജേണൽ', 'നായർ', 'അനിൽ'],
            'pa' => ['pa', 'Gurmukhi', 'ਪ੍ਰੋਟੀਨ ਬਣਤਰ ਦਾ ਅਧਿਐਨ', 'ਜੈਵ ਰਸਾਇਣ ਰਸਾਲਾ', 'ਸਿੰਘ', 'ਹਰਪ੍ਰੀਤ'],
        ];
    }

    private static function boot(string $wiki): void {
        putenv('PUBLIC_BASE_URL=https://citations.toolforge.org');
        putenv('ALLOWED_HOSTS=citations.toolforge.org');
        putenv('ALLOWED_ORIGINS=https://citations.toolforge.org');
        $_SERVER['HTTP_HOST'] = 'citations.toolforge.org';
        $_GET['wiki_base'] = $wiki;
        require dirname(__DIR__, 2) . '/src/includes/setup.php';
    }

    #[DataProvider('nativeText')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_native_script_survives_parsing_and_tidying(string $wiki, string $script, string $title, string $journal, string $last, string $first): void {
        if (!in_array($wiki, SUPPORTED_WIKIS, true)) {
            $this->markTestSkipped("$wiki is not enabled yet");
        }
        self::boot($wiki);

        foreach ([$title, $journal, $last, $first] as $field) {
            $this->assertMatchesRegularExpression('~\p{' . $script . '}~u', $field, 'fixture must contain the wiki script');
            $this->assertTrue(mb_check_encoding($field, 'UTF-8'));
        }

        $text = 'Intro ' . $title . ' {{Cite journal|last=' . $last . '|first=' . $first . '|title=' . $title . '|journal=' . $journal . '|year=2024}} outro.';
        $page = new Page();
        $page->parse_text($text);
        $this->assertSame($text, $page->parsed_text());
        $this->assertCount(1, $page->extract_object('Template'));

        $template = new Template();
        $template->parse_text('{{Cite journal|last=' . $last . '|first=' . $first . '|title=' . $title . '|journal=' . $journal . '|year=2024}}');
        $template->prepare();
        $template->tidy();
        $this->assertSame($title, $template->get('title'));
        $this->assertSame($journal, $template->get('journal'));
        $this->assertSame($last, $template->get('last'));
        $this->assertSame($first, $template->get('first'));
        $this->assertTrue(mb_check_encoding($template->parsed_text(), 'UTF-8'));
    }

    #[DataProvider('nativeText')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_edit_summary_is_translated_and_keeps_names(string $wiki, string $script, string $title, string $journal, string $last, string $first): void {
        if (!in_array($wiki, SUPPORTED_WIKIS, true)) {
            $this->markTestSkipped("$wiki is not enabled yet");
        }
        self::boot($wiki);

        $category = 'Added references';
        $summary = (new Page())->edit_summary(category_edit_summary_end($last, $category, true));

        $this->assertTrue(mb_check_encoding($summary, 'UTF-8'));
        $this->assertStringContainsString($last, $summary, 'user name must not be rewritten');
        $this->assertStringContainsString($category, $summary, 'category name must not be rewritten');
        $this->assertStringContainsString('#UCB_Category', $summary);
        $this->assertStringContainsString('[[:en:WP:UCB|', $summary, 'link targets must be preserved');
        $this->assertStringContainsString('[[:en:WP:DBUG|', $summary, 'link targets must be preserved');
        foreach (['Suggested by', 'Misc citation tidying', 'Use this bot', 'Report bugs'] as $english_phrase) {
            $this->assertStringNotContainsString($english_phrase, $summary, 'fixed phrase must be translated');
        }
        if ($script !== 'Latin') {
            $this->assertMatchesRegularExpression('~\p{' . $script . '}~u', $summary);
        }
    }
}
