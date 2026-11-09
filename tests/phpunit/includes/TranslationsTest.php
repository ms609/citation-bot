<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/src/includes/constants/translations.php';

final class TranslationsTest extends PHPUnit\Framework\TestCase {

    public function testEditSummaryTranslationsUseTheSameEnglishKeys(): void {
        $englishKeys = array_keys(MK_TRANS);
        $this->assertNotEmpty($englishKeys);
        foreach (EDIT_SUMMARY_TRANSLATIONS as $wiki => $translations) {
            $this->assertEqualsCanonicalizing(
                $englishKeys,
                array_keys($translations),
                "English edit-summary keys differ for $wiki."
            );
        }
    }

    public function testEverySupportedWikiUsesEnglishOrHasEditSummaryTranslations(): void {
        foreach (SUPPORTED_WIKIS as $wiki) {
            $is_english = in_array($wiki, ENGLISH_WIKI, true);
            $has_translation = array_key_exists($wiki, EDIT_SUMMARY_TRANSLATIONS);
            $this->assertNotSame(
                $is_english,
                $has_translation,
                "$wiki must use either English or a translation map, but not both."
            );
        }
    }
}
