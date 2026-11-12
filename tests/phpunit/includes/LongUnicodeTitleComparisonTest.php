<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class LongUnicodeTitleComparisonTest extends testBaseClass {
    public function testLongTitleToleranceCountsCharacters(): void {
        $title = str_repeat('日本古典文学研究', 35);
        $this->assertTrue(titles_are_similar($title, str_repeat('日本古典文学研究', 34) . '日本古典文学研号'));
        $this->assertFalse(titles_are_similar($title, str_repeat('日本古典文学研究', 34) . '日夲占與丈斈硏姤'));
        $large = str_repeat('研究', 2300);
        $this->assertTrue(titles_are_similar($large, $large));
        $this->assertFalse(titles_are_similar($large, str_repeat('研究', 2299) . '研攷'));
    }

    public function testBandedDistanceAgreesWithExactDistanceAtBoundary(): void {
        foreach ([['東京大学', '東京大学院'], ['東京大学', '東京大學'], ['東京', ''], ['猫犬鳥馬', '犬鳥馬猫']] as [$left, $right]) {
            foreach ([0, 1, 2, 3] as $cutoff) {
                $this->assertSame(min(unicode_levenshtein($left, $right), $cutoff + 1), unicode_levenshtein($left, $right, $cutoff));
            }
        }
    }
}
