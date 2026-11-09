<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 3) . '/src/includes/constants/translations.php';

/**
 * Coverage for #6143 edit summaries without enabling unapproved wikis.
 * The setup.php allowlist must remain unchanged in this PR.
 */
final class AdditionalWikiEditSummaryTranslationTest extends PHPUnit\Framework\TestCase {
    /** @return array<string, array{string}> */
    public static function languages(): array {
        $data = [];
        foreach (self::newLanguages() as $wiki) {
            $data[$wiki] = [$wiki];
        }
        return $data;
    }

    /** @return list<string> */
    private static function newLanguages(): array {
        return ['zh', 'uk', 'ms', 'th', 'he', 'hi', 'te', 'hu', 'et', 'hy', 'az', 'ne', 'ml', 'pa'];
    }

    public function test_all_requested_languages_have_translations(): void {
        foreach (self::newLanguages() as $wiki) {
            $this->assertArrayHasKey($wiki, EDIT_SUMMARY_TRANSLATIONS);
        }
        $this->assertCount(18, EDIT_SUMMARY_TRANSLATIONS);
    }

    #[DataProvider('languages')]
    public function test_key_sets_match_existing_translation_maps(string $wiki): void {
        $map = EDIT_SUMMARY_TRANSLATIONS[$wiki];
        $base_keys = array_keys(MK_TRANS);
        $extra_keys = ['authors ', 'editors ', 'Formatted [[WP:ENDASH|dashes]]. '];
        $this->assertSame(array_merge($base_keys, $extra_keys), array_keys($map), $wiki);
        $this->assertEqualsCanonicalizing(array_keys(RU_TRANS), $base_keys, $wiki);
        $this->assertEqualsCanonicalizing(array_keys(SR_TRANS), $base_keys, $wiki);
        $this->assertEqualsCanonicalizing(array_keys(VI_TRANS), $base_keys, $wiki);
        foreach ($map as $english => $translated) {
            $this->assertNotSame('', trim($translated), "$wiki: empty $english");
            $this->assertSame(str_ends_with($english, ' '), str_ends_with($translated, ' '), "$wiki: trailing space for $english");
        }
    }

    #[DataProvider('languages')]
    public function test_edit_summary_translation_preserves_tokens_and_links(string $wiki): void {
        $input = 'Altered doi. Added title. Removed access-date with no URL. '
            . 'Removed unsupported issue parameter from cite book. '
            . 'Rebuilt citation from DOI after removing cookieAbsent link. '
            . '| [[:en:WP:UCB|Use this bot]]. [[:en:WP:DBUG|Report bugs]]. '
            . '| Suggested by Editor | [[Category:Science]]';
        $translated = strtr($input, EDIT_SUMMARY_TRANSLATIONS[$wiki]);
        $this->assertNotSame($input, $translated, $wiki);
        foreach (['doi', 'title', 'access-date', 'issue', 'cite book', 'DOI', 'cookieAbsent'] as $token) {
            $this->assertStringContainsString($token, $translated, "$wiki: missing $token");
        }
        $this->assertStringContainsString('[[:en:WP:UCB|', $translated);
        $this->assertStringContainsString('[[:en:WP:DBUG|', $translated);
        $this->assertStringContainsString('[[Category:Science]]', $translated);
        $this->assertSame(2, substr_count($translated, ']].'));
    }

    #[DataProvider('languages')]
    public function test_plural_attribution_and_dash_labels_are_localized(string $wiki): void {
        $input = 'Alter: doi, title. Add: authors 1-2. editors 1-3. '
            . 'Formatted [[WP:ENDASH|dashes]]. ';
        $translated = strtr($input, EDIT_SUMMARY_TRANSLATIONS[$wiki]);
        $this->assertStringNotContainsString('Alter:', $translated, $wiki);
        $this->assertStringNotContainsString('Add:', $translated, $wiki);
        $this->assertStringNotContainsString('authors 1-2', $translated, $wiki);
        $this->assertStringNotContainsString('editors 1-3', $translated, $wiki);
        $this->assertStringNotContainsString('Formatted ', $translated, $wiki);
        $this->assertStringContainsString('[[:en:WP:ENDASH|', $translated, $wiki);
        $this->assertStringContainsString(']]', $translated, $wiki);
        $this->assertTrue(str_ends_with($translated, ' '), $wiki);
    }

    public function test_unapproved_wiki_has_no_translation_map(): void {
        $this->assertArrayNotHasKey('fa', EDIT_SUMMARY_TRANSLATIONS);
        $this->assertArrayNotHasKey('sq', EDIT_SUMMARY_TRANSLATIONS);
        $this->assertArrayNotHasKey('id', EDIT_SUMMARY_TRANSLATIONS);
    }

    public function test_longer_formatted_dashes_key_has_priority(): void {
        $map = EDIT_SUMMARY_TRANSLATIONS['zh'];
        $source = 'Formatted [[WP:ENDASH|dashes]]. ';
        $this->assertSame($map[$source], strtr($source, $map));
        $this->assertStringNotContainsString('[[WP:ENDASH|', strtr($source, $map));
    }

    public function test_preexisting_languages_remain_registered(): void {
        foreach (['mk', 'ru', 'sr', 'vi'] as $wiki) {
            $this->assertArrayHasKey($wiki, EDIT_SUMMARY_TRANSLATIONS);
        }
    }
}
