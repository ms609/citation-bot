<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class PageProtectedRegionInteractionTest extends testBaseClass {
    public function testNowikiCitationIsRestoredWhileNeighborCitationIsTidied(): void {
        $input =
            '<nowiki>{{cite journal|pages=44-55}}</nowiki>' .
            '{{cite journal|pages=44-55}}';

        $page = new TestPage();
        $page->parse_text($input);
        $this->assertTrue($page->expand_text());

        $output = $page->parsed_text();
        $this->assertStringContainsString(
            '<nowiki>{{cite journal|pages=44-55}}</nowiki>',
            $output
        );
        $this->assertSame(1, mb_substr_count($output, 'pages=44-55'));
        $this->assertSame(1, mb_substr_count($output, 'pages=44–55'));
    }

    public function testDuplicateProtectedRegionsSurvivePlaceholderRoundTrip(): void {
        $protected = '<nowiki>{{cite journal|pages=10-20}}</nowiki>';
        $input = $protected . $protected . '{{cite journal|pages=10-20}}';

        $page = new TestPage();
        $page->parse_text($input);
        $this->assertTrue($page->expand_text());

        $output = $page->parsed_text();
        $this->assertSame(2, mb_substr_count($output, $protected));
        $this->assertSame(1, mb_substr_count($output, 'pages=10–20'));
    }

    public function testNoBotsRestoresOriginalAfterEarlyObjectExtraction(): void {
        $input =
            '{{nobots}}' .
            '<!-- {{cite journal|pages=1-2}} -->' .
            '<nowiki>{{cite journal|pages=3-4}}</nowiki>' .
            '{{cite journal|pages=5-6}}';

        $page = new TestPage();
        $page->parse_text($input);

        $this->assertFalse($page->expand_text());
        $this->assertSame($input, $page->parsed_text());
    }
}
