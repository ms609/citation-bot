<?php
declare(strict_types=1);

/**
 * ISSN dash clean-up must never split a multi-byte character.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditIssnDashEncodingTest extends testBaseClass {

    #[DataProvider('dashProvider')]
    public function testUnicodeDashesBecomeAsciiHyphen(string $dash): void {
        $template = $this->make_citation('{{cite journal|issn=1234' . $dash . '5678}}');
        $template->tidy_parameter('issn');
        $this->assertSame('1234-5678', $template->get2('issn'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function dashProvider(): array {
        return [
            'hyphen-minus' => ['-'],
            'hyphen U+2010' => ["\u{2010}"],
            'non-breaking hyphen U+2011' => ["\u{2011}"],
            'figure dash U+2012' => ["\u{2012}"],
            'en dash U+2013' => ["\u{2013}"],
            'em dash U+2014' => ["\u{2014}"],
            'minus sign U+2212' => ["\u{2212}"],
            'spaced en dash' => [" \u{2013} "],
        ];
    }

    public function testOtherMultiByteCharactersStayValidUtf8(): void {
        $template = $this->make_citation("{{cite journal|issn=1234\u{2026}5678}}");
        $template->tidy_parameter('issn');
        $this->assertTrue(mb_check_encoding((string) $template->get2('issn'), 'UTF-8'));
    }
}
