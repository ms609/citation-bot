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
        $this->assertEqualsCanonicalizing(
            SUPPORTED_WIKIS,
            [...ENGLISH_WIKI, ...array_keys(EDIT_SUMMARY_TRANSLATIONS)],
            'Each supported wiki must have exactly one edit-summary language configuration.'
        );
    }
}
