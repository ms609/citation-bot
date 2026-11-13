<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/src/includes/constants/translations.php';

/**
 * Page::extract_object() looks up the template-parse failure text by WIKI_BASE and
 * stops the run if the wiki is missing. Guard against enabling a wiki without an entry.
 */
final class TemplateParseErrorsTest extends PHPUnit\Framework\TestCase {

    public function testEverySupportedWikiHasParseErrorText(): void {
        $this->assertSame([], array_values(array_diff(SUPPORTED_WIKIS, array_keys(TEMPLATE_PARSE_ERRORS))));
    }

    public function testEveryTranslatedWikiHasParseErrorText(): void {
        $this->assertSame([], array_values(array_diff(array_keys(EDIT_SUMMARY_TRANSLATIONS), array_keys(TEMPLATE_PARSE_ERRORS))));
    }

    public function testParseErrorTextIsAPairOfNonEmptyStrings(): void {
        foreach (TEMPLATE_PARSE_ERRORS as $wiki => $messages) {
            $this->assertCount(2, $messages, $wiki);
            foreach ($messages as $message) {
                $this->assertNotSame('', $message, $wiki);
                $this->assertTrue(mb_check_encoding($message, 'UTF-8'), $wiki);
            }
        }
    }

    public function testExistingWikisKeepTheirOriginalText(): void {
        $this->assertSame([ENG_ERR1, ENG_ERR2], TEMPLATE_PARSE_ERRORS['en']);
        $this->assertSame([ENG_ERR1, ENG_ERR2], TEMPLATE_PARSE_ERRORS['simple']);
        $this->assertSame([ENG_ERR1, ENG_ERR2], TEMPLATE_PARSE_ERRORS['mdwiki']);
        $this->assertSame([RU_ERR1, RU_ERR2], TEMPLATE_PARSE_ERRORS['ru']);
        $this->assertSame([MK_ERR1, MK_ERR2], TEMPLATE_PARSE_ERRORS['mk']);
        $this->assertSame([SR_ERR1, SR_ERR2], TEMPLATE_PARSE_ERRORS['sr']);
        $this->assertSame([VI_ERR1, VI_ERR2], TEMPLATE_PARSE_ERRORS['vi']);
    }
}
