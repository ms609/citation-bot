<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class ArticleCaseExceptionsTest extends testBaseClass {
    #[DataProvider('articleCases')]
    public function testArticleTitlesKeepSpecialCapitalization(string $input, string $expected): void {
        $this->assertSame($expected, wikify_external_text($input));
    }

    /** @return array<string, array{string, string}> */
    public static function articleCases(): array {
        return [
            'pH and mTOR' => ['pH-dependent mTOR signalling', 'pH-dependent mTOR signalling'],
            'bacterial name' => ['An E. coli study', 'An E. coli study'],
            'units and speed' => ['Walking at 60 mph and 4 km/h', 'Walking at 60 mph and 4 km/h'],
            'Latin abbreviation' => ['Smith et al. respond', 'Smith et al. respond'],
            'iOS' => ['iOS apps and macOS tools', 'iOS apps and macOS tools'],
            'nucleic acids' => ['A cDNA and mRNA study', 'A cDNA and mRNA study'],
            'genes' => ['An mTORC1 and NF-κB analysis', 'An mTORC1 and NF-κB analysis'],
            'tech' => ['Using GitHub and PyTorch', 'Using GitHub and PyTorch'],
            'not a substring' => ['A triumph of science', 'A triumph of science'],
        ];
    }

    public function testJournalStillUsesItsOwnCapitalization(): void {
        $this->assertSame('Molecular and Cellular Biology', wikify_external_text('Molecular and cellular biology', use_title_case: true));
    }
}
