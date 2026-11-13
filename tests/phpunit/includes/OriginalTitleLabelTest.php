<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class OriginalTitleLabelTest extends testBaseClass {
    public function testTitleComparisonsUseOriginalSectionAndChapterLabels(): void {
        $this->assertFalse(titles_are_similar('Introduction to Medicine Section Å', 'Introduction to Medicine Section A'));
        $this->assertTrue(titles_are_similar('Introduction to Medicine Section &Aring;', "Introduction to Medicine Section A\u{030A}"));
        $this->assertFalse(titles_are_similar('Ιστορία της Ιατρικής μέρος Α', 'Ιστορία της Ιατρικής μέρος Β'));
        $this->assertFalse(titles_are_similar('日本の古典文学第一章物語', '日本の古典文学第二章物語'));
        $this->assertTrue(titles_are_similar('Introduction to Medicine Part IV', 'Introduction to Medicine Part 4'));
    }
}
