<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

/**
 * A page is in CS2 mode when {{cs1 config|mode=cs2}} says so, or when all of its (several) existing
 * citation templates are already Citation Style 2.  New bare references on such a page become
 * {{citation}} and not {{cite web}}.
 */
final class PageCs2ModeTest extends testBaseClass {

    private function cs2_mode_of(string $text): bool {
        $page = new TestPage();
        $page->parse_text($text);
        $method = new ReflectionMethod(Page::class, 'set_cs2_mode');
        $method->invoke($page);
        $property = new ReflectionProperty(Page::class, 'cs2_mode');
        return (bool) $property->getValue($page);
    }

    /** @return string five different {{citation}} templates */
    private function five_citations(): string {
        return '{{citation|title=A|year=2000}}{{citation|title=B|year=2001}}{{Citation |title=C|year=2002}}' .
               '{{citation|title=D|year=2003}}{{ citation|title=E|year=2004}}';
    }

    public function testExplicitConfigurationStillWorks(): void {
        $this->assertTrue($this->cs2_mode_of('{{cs1 config|mode=cs2}}'));
        $this->assertTrue($this->cs2_mode_of('{{cs1 config|Mode=cs2}}'));
        $this->assertFalse($this->cs2_mode_of('{{cs1 config|mode=cs1}}{{cite web|title=A}}'));
    }

    public function testPageWithoutCitationsIsNotCs2(): void {
        $this->assertFalse($this->cs2_mode_of('Just text<ref>http://example.com/a</ref>'));
    }

    public function testManyCitationTemplatesAreCs2(): void {
        $this->assertTrue($this->cs2_mode_of($this->five_citations()));
    }

    public function testFewCitationTemplatesAreNotEnoughToInferCs2(): void {
        $this->assertFalse($this->cs2_mode_of('{{citation|title=A}}{{citation|title=B}}{{citation|title=C}}{{citation|title=D}}'));
    }

    public function testOneCs1TemplateStopsTheInference(): void {
        $this->assertFalse($this->cs2_mode_of($this->five_citations() . '{{cite web|url=http://example.com/a|title=F}}'));
        $this->assertFalse($this->cs2_mode_of('{{cite journal|title=F}}' . $this->five_citations()));
    }

    public function testCiteTemplatesWithModeCs2CountAsCs2(): void {
        $text = '{{cite web|title=A|mode=cs2}}{{cite book|title=B|mode = CS2}}{{cite journal|title=C|mode=cs2}}' .
                '{{citation|title=D}}{{cite news|title=E|mode=cs2}}';
        $this->assertTrue($this->cs2_mode_of($text));
    }

    public function testCitationTemplateWithModeCs1CountsAsCs1(): void {
        $text = $this->five_citations() . '{{citation|title=F|mode=cs1}}';
        $this->assertFalse($this->cs2_mode_of($text));
    }

    public function testNeededTagsAreNotCitations(): void {
        $text = $this->five_citations() . 'Claim{{Citation needed|date=May 2024}}Other{{cite needed|date=May 2024}}';
        $this->assertTrue($this->cs2_mode_of($text));
    }

    public function testNestedTemplatesInsideCitationAreHandled(): void {
        $text = '{{citation|title={{lang|fr|A}}|year=2000}}{{citation|title={{lang|fr|B}}|year=2001}}' .
                '{{citation|title=C|year=2002}}{{citation|title=D|year=2003|quote={{tq|x}}}}{{citation|title=E|year=2004}}';
        $this->assertTrue($this->cs2_mode_of($text));
    }

    #[DataProvider('pageStyleCases')]
    public function testBareReferencesUseTheActivePageStyle(string $prefix, string $reference, bool $cs2): void {
        $this->with_jstor_citoid_fixture('962034', function () use ($prefix, $reference, $cs2): void {
            $page = $this->process_page($prefix . $reference);
            $output = $page->parsed_text();
            $this->assertStringContainsString($prefix, $output);
            $this->assertMatchesRegularExpression('~<ref>(\{\{[^{}]+\}\})</ref>$~', $output);
            $new_reference = mb_substr($output, mb_strlen($prefix));
            $this->assertStringContainsString('jstor=962034', $new_reference);
            $this->assertSame($cs2, preg_match('~<ref>\{\{citation\s*\||\|\s*mode\s*=\s*cs2\b~', $new_reference) === 1);
        });
    }

    /** @return array<string, array{string, string, bool}> */
    public static function pageStyleCases(): array {
        $url = '<ref>https://doi.org/10.2307/962034</ref>';
        $citations = '{{citation|title=A}}{{citation|title=B}}{{citation|title=C}}{{citation|title=D}}{{citation|title=E}}';
        return [
            'active CS2' => ['{{cs1 config|mode=cs2}}', $url, true],
            'commented config' => ['<!-- {{cs1 config|mode=cs2}} -->', $url, false],
            'nowiki config' => ['<nowiki>{{cs1 config|mode=cs2}}</nowiki>', $url, false],
            'nowiki citation does not change inference' => [$citations . '<nowiki>{{cite web|title=F}}</nowiki>', $url, true],
            'active CS1 overrides inference' => ['{{cs1 config|mode=cs1}}' . $citations, $url, false],
            'plain DOI' => [$citations, '<ref>10.2307/962034</ref>', true],
            'identifier template' => [$citations, '<ref>{{doi|10.2307/962034}}</ref>', true],
            'DOI with surrounding text' => [$citations, '<ref>Jarman, [https://doi.org/10.2307/962034 Violin Concerto], 1983.</ref>', true],
        ];
    }

    public function testNestedParametersDoNotSetTheOuterCitationMode(): void {
        $text = $this->five_citations() . '{{cite web|title={{example|mode=cs2}}}}';
        $this->assertFalse($this->cs2_mode_of($text));
    }

    public function testUnclosedTemplatesDoNotCountTowardCs2Inference(): void {
        $this->assertFalse($this->cs2_mode_of(str_repeat('{{citation|title=Unclosed ', 4096)));
    }

}
