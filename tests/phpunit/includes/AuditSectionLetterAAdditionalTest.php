<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditSectionLetterAAdditionalTest extends testBaseClass {
    #[DataProvider('capitalLabels')]
    public function testExplicitCapitalLabelsRemainCapitalized(string $input, string $expected): void {
        $this->assertSame($expected, title_capitalization($input, true));
    }

    public static function capitalLabels(): array {
        return [
            'section label after colon' => ['Methods: Section A Results', 'Methods: Section A Results'],
            'part label after dash' => ['Methods - Part A Results', 'Methods - Part A Results'],
            'series label before comma' => ['Series A, Methods', 'Series A, Methods'],
            'section label with unicode preceding text' => ['Études Section A Methods', 'Études Section A Methods'],
            'multiple capital labels' => ['Section A and Part A Methods', 'Section A and Part A Methods'],
            'capital label at end' => ['Acta Crystallographica Section A', 'Acta Crystallographica Section A'],
        ];
    }

    #[DataProvider('articles')]
    public function testIndefiniteArticlesRemainLowercase(string $input, string $expected): void {
        $this->assertSame($expected, title_capitalization($input, true));
    }

    public static function articles(): array {
        return [
            'article after ordinary word' => ['A Tale of A City', 'A Tale of a City'],
            'part article in a phrase' => ['We Took Part A Few Times', 'We Took Part a Few Times'],
            'article after colon' => ['Methods: A New Approach', 'Methods: a New Approach'],
            'lowercase section label remains lowercase' => ['Section a Methods', 'Section a Methods'],
            'lowercase part label at end' => ['Part a', 'Part a'],
        ];
    }
}
