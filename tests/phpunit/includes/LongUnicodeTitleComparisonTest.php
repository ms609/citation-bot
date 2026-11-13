<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class LongUnicodeTitleComparisonTest extends testBaseClass {
    public function testJapaneseVariantsAndMixedScriptIdentity(): void {
        $this->assertTrue(titles_are_similar('コンピューター', 'コンピュータ'));
        $this->assertTrue(titles_are_similar('プリンターの研究', 'プリンタの研究'));
        $this->assertFalse(titles_are_similar('X型糖尿病', 'Y型糖尿病'));
        $this->assertFalse(titles_are_similar('X型糖尿病', '型糖尿病'));
        $this->assertFalse(titles_are_similar('ビルの研究', 'ビールの研究'));
    }

    public function testLongTitleToleranceCountsCharacters(): void {
        $title = str_repeat('тест data ', 40);
        $this->assertTrue(titles_are_similar($title, str_repeat('тест data ', 34) . str_repeat('тёст data ', 6)));
        $this->assertFalse(titles_are_similar($title, str_repeat('тест data ', 33) . str_repeat('тест date ', 7)));
        $large = str_repeat('на', 2300);
        $this->assertTrue(titles_are_similar($large, $large));
        $this->assertFalse(titles_are_similar($large, str_repeat('на', 2299) . 'но'));
    }

    public function testLongJapaneseTitlesRequireMatchingText(): void {
        $common = str_repeat('日本古典文学研究', 34);
        $title = $common . '日本古典文学研究';
        $this->assertTrue(titles_are_similar($title, $title));
        $this->assertFalse(titles_are_similar($title, $common . '日本古典文学研号'));
    }

    public function testBandedDistanceAgreesWithExactDistanceAtBoundary(): void {
        foreach ([['東京大学', '東京大学院'], ['東京大学', '東京大學'], ['東京', ''], ['猫犬鳥馬', '犬鳥馬猫']] as [$left, $right]) {
            foreach ([0, 1, 2, 3] as $cutoff) {
                $this->assertSame(min(unicode_levenshtein($left, $right), $cutoff + 1), unicode_levenshtein($left, $right, $cutoff));
            }
        }
    }
}
