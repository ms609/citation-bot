<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class ProtectedExtensionTagsTest extends testBaseClass {
    #[DataProvider('protectedMarkup')]
    public function testExtensionContentSurvivesWhileNeighborCitationIsTidied(string $protected): void {
        $page = new TestPage();
        $page->parse_text($protected . '{{cite journal|pages=44-55}}');
        $this->assertTrue($page->expand_text());

        $output = $page->parsed_text();
        $this->assertStringContainsString($protected, $output);
        $this->assertSame(1, mb_substr_count($output, 'pages=44-55'));
        $this->assertSame(1, mb_substr_count($output, 'pages=44–55'));
    }

    /** @return array<string, array{string}> */
    public static function protectedMarkup(): array {
        return [
            'deprecated source tag' => ['<source lang="wikitext">{{cite journal|pages=44-55}}</source>'],
            'mixed case source with attrs' => ['<SOURCE lang="wikitext" line="3">{{cite journal|pages=44-55}}</SOURCE>'],
            'chemical equation tag' => ['<ce display="inline">{{cite journal|pages=44-55}}</ce>'],
            'chem tag attributes' => ['<chem data-mode="x">{{cite journal|pages=44-55}}</chem>'],
            'math unfamiliar attributes' => ['<math id="eq-1" data-mode="new">{{cite journal|pages=44-55}}</math>'],
        ];
    }

    public function testNoBotsInsideProtectedSourceIsNotExecuted(): void {
        $protected = '<source lang="wikitext">{{nobots}}{{cite journal|pages=44-55}}</source>';
        $page = new TestPage();
        $page->parse_text($protected . '{{cite journal|pages=44-55}}');
        $this->assertTrue($page->expand_text());
        $this->assertStringContainsString($protected, $page->parsed_text());
        $this->assertSame(1, mb_substr_count($page->parsed_text(), 'pages=44–55'));
    }

    public function testOpeningTagsHaveCorrectBoundaries(): void {
        $this->assertSame(0, preg_match(SourceCode::REGEXP[0], '<sourceful>{{cite web}}</sourceful>'));
        $this->assertSame(0, preg_match(Chemistry::REGEXP[0], '<center>{{cite web}}</center>'));
        $this->assertSame(0, preg_match(Mathematics::REGEXP[0], '<mathjax>{{cite web}}</mathjax>'));
        $this->assertSame(1, preg_match(Mathematics::REGEXP[0], '<math new="x" chem class="y">{{cite web}}</math>'));
        $this->assertSame(1, preg_match(Chemistry::REGEXP[0], '<ce style="inline">{{cite web}}</ce>'));
    }
}
