<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class ConservativeTaxonomyItalicsTest extends testBaseClass {
    #[DataProvider('taxonCases')]
    public function testTaxonRecognition(string $input, string $expected): void {
        $this->assertSame($expected, title_capitalization($input, true));
    }

    /** @return array<string, array{string, string}> */
    public static function taxonCases(): array {
        return [
            'genus-only from Peru' => ['Description of Barus sp. nov. from Peru', "Description of ''Barus'' sp. nov. from Peru"],
            'genus only at title start' => ['Barus sp. nov. from Peru', "''Barus'' sp. nov. from Peru"],
            'binomial' => ['Description of Barus exampleus sp. nov. from Peru', "Description of ''Barus exampleus'' sp. nov. from Peru"],
            'new genus' => ['Description of Barus gen. nov. from Peru', "Description of ''Barus'' gen. nov. from Peru"],
            'ordinary sentence' => ['Description of a new species from Peru', 'Description of a new species from Peru'],
            'already italicized' => ["Description of ''Barus'' sp. nov. from Peru", "Description of ''Barus'' sp. nov. from Peru"],
        ];
    }
}
