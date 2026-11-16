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

    /** @return array<string, array{string, string, bool}> */
    public static function postscriptPageStyleCases(): array {
        $book = '{{cite book|title=Example|postscript=.}}';
        return [
            // Report: https://en.wikipedia.org/wiki/Special:Diff/1386028926
            'article-wide CS2' => ['{{CS1 config|mode=cs2}}', $book, true],
            'article-wide CS2 on cite web' => ['{{cs1 config|mode=cs2}}', '{{cite web|title=Example|postscript=.}}', true],
            'article-wide CS2 on cite news' => ['{{cs1 config|mode=cs2}}', '{{cite news|title=Example|postscript=.}}', true],
            'article-wide CS2 on cite journal' => ['{{cs1 config|mode=cs2}}', '{{cite journal|title=Example|postscript=.}}', true],
            'article-wide CS2 on cite conference' => ['{{cs1 config|mode=cs2}}', '{{cite conference|title=Example|postscript=.}}', true],
            'article-wide CS2 with local CS1 override' => ['{{cs1 config|mode=cs2}}', '{{cite book|title=Example|mode=cs1|postscript=.}}', false],
            'article-wide CS1' => ['{{cs1 config|mode=cs1}}', $book, false],
            'no configuration' => ['', $book, false],
            'local CS2 without page configuration' => ['', '{{cite book|title=Example|mode=cs2|postscript=.}}', true],
            'case-insensitive local CS2' => ['', '{{cite book|title=Example|mode=CS2|postscript=.}}', true],
            'local CS2 with surrounding whitespace' => ['', '{{cite book|title=Example|mode= CS2 |postscript=.}}', true],
            'local CS2 overrides article-wide CS1' => ['{{cs1 config|mode=cs1}}', '{{cite book|title=Example|mode=cs2|postscript=.}}', true],
            'local uppercase CS1 overrides article-wide CS2' => ['{{cs1 config|mode=cs2}}', '{{cite book|title=Example|mode=CS1|postscript=.}}', false],
            'local padded CS1 overrides article-wide CS2' => ['{{cs1 config|mode=cs2}}', '{{cite book|title=Example|mode= cs1 |postscript=.}}', false],
            'invalid local mode inherits article-wide CS2' => ['{{cs1 config|mode=cs2}}', '{{cite book|title=Example|mode=cs3|postscript=.}}', true],
            'blank local mode inherits article-wide CS2' => ['{{cs1 config|mode=cs2}}', '{{cite book|title=Example|mode=|postscript=.}}', true],
            'invalid local mode without configuration' => ['', '{{cite book|title=Example|mode=cs22|postscript=.}}', false],
            'invalid local mode under article-wide CS1' => ['{{cs1 config|mode=cs1}}', '{{cite book|title=Example|mode=bogus|postscript=.}}', false],
            'postscript value with spaces in CS2' => ['{{cs1 config|mode=cs2}}', '{{cite book|title=Example|postscript= . }}', true],
            'CS2 configuration with mixed case' => ['{{CS1 CONFIG|MODE=CS2}}', $book, true],
            'CS2 configuration with whitespace' => ['{{ cs1 config | mode = cs2 }}', $book, true],
            'CS2 configuration with underscore name' => ['{{CS1_config|mode=cs2}}', $book, true],
            'CS2 configuration with template namespace' => ['{{Template:CS1 config|mode=cs2}}', $book, true],
            'CS2 configuration with other settings' => ['{{cs1 config|name-list-style=vanc|mode=cs2}}', $book, true],
            'first explicit CS2 configuration wins' => ['{{cs1 config|mode=cs2}}{{cs1 config|mode=cs1}}', $book, true],
            'first explicit CS1 configuration wins' => ['{{cs1 config|mode=cs1}}{{cs1 config|mode=cs2}}', $book, false],
            'first invalid configuration does not block valid CS2' => ['{{cs1 config|mode=wrong}}{{cs1 config|mode=cs2}}', $book, true],
            'later invalid configuration does not override CS2' => ['{{cs1 config|mode=cs2}}{{cs1 config|mode=wrong}}', $book, true],
            'invalid configuration mode' => ['{{cs1 config|mode=cs3}}', $book, false],
            'blank configuration mode' => ['{{cs1 config|mode=}}', $book, false],
            'missing configuration mode' => ['{{cs1 config|name-list-style=vanc}}', $book, false],
            'wrong parameter name' => ['{{cs1 config|notmode=cs2}}', $book, false],
            'wrong template name' => ['{{not cs1 config|mode=cs2}}', $book, false],
            'commented-out configuration' => ['<!-- {{cs1 config|mode=cs2}} -->', $book, false],
            'nowiki configuration' => ['<nowiki>{{cs1 config|mode=cs2}}</nowiki>', $book, false],
            'source configuration' => ['<source>{{cs1 config|mode=cs2}}</source>', $book, false],
            'syntaxhighlight configuration' => ['<syntaxhighlight lang="wikitext">{{cs1 config|mode=cs2}}</syntaxhighlight>', $book, false],
            'pre configuration' => ['<pre>{{cs1 config|mode=cs2}}</pre>', $book, false],
            'math configuration' => ['<math>{{cs1 config|mode=cs2}}</math>', $book, false],
            'live CS2 after commented configuration' => ['<!-- {{cs1 config|mode=cs1}} -->{{cs1 config|mode=cs2}}', $book, true],
            'citation template without configuration' => ['', '{{citation|title=Example|postscript=.}}', true],
            'citation template with explicit CS1 mode' => ['', '{{citation|title=Example|mode=cs1|postscript=.}}', true],
            'citation template with page CS2' => ['{{cs1 config|mode=cs2}}', '{{citation|title=Example|postscript=.}}', true],
            'citation template with page CS1' => ['{{cs1 config|mode=cs1}}', '{{citation|title=Example|postscript=.}}', true],
        ];
    }

    #[DataProvider('postscriptPageStyleCases')]
    public function testPostscriptRespectsEffectiveCitationMode(string $prefix, string $citation, bool $keepPostscript): void {
        $page = $this->process_page($prefix . '<ref>' . $citation . '</ref>');
        $output = $page->parsed_text();
        // Accept harmless whitespace and parameter-name capitalization, but require
        // an actual punctuation parameter instead of merely matching text in a title.
        $hasDot = preg_match('~\|\s*postscript\s*=\s*\.(?=\s*(?:\||\}\}))~i', $output) === 1;
        $this->assertSame($keepPostscript, $hasDot, $output);
    }

    /** @return array<string, array{string, string, string}> */
    public static function nonDotPostscriptCases(): array {
        return [
            'none in ordinary CS1' => ['', '{{cite book|title=Example|postscript=none}}', 'none'],
            'none in article-wide CS2' => ['{{cs1 config|mode=cs2}}', '{{cite book|title=Example|postscript=none}}', 'none'],
            'none in citation template' => ['', '{{citation|title=Example|postscript=none}}', 'none'],
            'semicolon in CS1' => ['', '{{cite book|title=Example|postscript=;}}', ';'],
            'semicolon in CS2' => ['{{cs1 config|mode=cs2}}', '{{cite book|title=Example|postscript=;}}', ';'],
            'question mark in CS2' => ['{{cs1 config|mode=cs2}}', '{{cite book|title=Example|postscript=?}}', '?'],
            'exclamation mark in CS1' => ['', '{{cite book|title=Example|postscript=!}}', '!'],
            'comma in CS2' => ['{{cs1 config|mode=cs2}}', '{{cite book|title=Example|postscript=,}}', ','],
        ];
    }

    #[DataProvider('nonDotPostscriptCases')]
    public function testNonDotPostscriptIsNotRemoved(string $prefix, string $citation, string $value): void {
        $page = $this->process_page($prefix . '<ref>' . $citation . '</ref>');
        $output = $page->parsed_text();
        $matches = [];
        $this->assertSame(1, preg_match('~\|\s*postscript\s*=\s*([^|}]+?)\s*(?=\||\}\})~u', $output, $matches), $output);
        $this->assertSame($value, mb_trim($matches[1]));
    }

    #[DataProvider('pageWithNoPostscriptCases')]
    public function testAbsentOrEmptyPostscriptDoesNotCreatePunctuation(string $prefix, string $citation): void {
        $page = $this->process_page($prefix . '<ref>' . $citation . '</ref>');
        $this->assertDoesNotMatchRegularExpression('~\|\s*postscript\s*=~i', $page->parsed_text());
    }

    /** @return array<string, array{string, string}> */
    public static function pageWithNoPostscriptCases(): array {
        return [
            'CS1 with no postscript' => ['', '{{cite book|title=Example}}'],
            'CS2 with no postscript' => ['{{cs1 config|mode=cs2}}', '{{cite book|title=Example}}'],
            'CS1 with empty postscript' => ['', '{{cite book|title=Example|postscript=}}'],
            'CS2 with empty postscript' => ['{{cs1 config|mode=cs2}}', '{{cite book|title=Example|postscript=}}'],
        ];
    }

    public function testPerCitationOverridesDoNotLeakToAdjacentReferences(): void {
        $text = '{{cs1 config|mode=cs2}}' .
            '<ref>{{cite book|title=Before|postscript=.}}</ref>' .
            '<ref>{{cite book|title=Override|mode=cs1|postscript=.}}</ref>' .
            '<ref>{{cite book|title=After|postscript=.}}</ref>';
        $page = $this->process_page($text);
        $refs = [];
        $this->assertSame(3, preg_match_all('~<ref>(.*?)</ref>~s', $page->parsed_text(), $refs));
        $this->assertStringContainsString('postscript=.', $refs[1][0]);
        $this->assertStringNotContainsString('postscript=.', $refs[1][1]);
        $this->assertStringContainsString('postscript=.', $refs[1][2]);
    }

    public function testPageConfigurationDoesNotLeakToTheNextPage(): void {
        $cs2 = $this->process_page('{{cs1 config|mode=cs2}}<ref>{{cite book|title=CS2|postscript=.}}</ref>');
        $this->assertStringContainsString('postscript=.', $cs2->parsed_text());
        $cs1 = $this->process_page('<ref>{{cite book|title=CS1|postscript=.}}</ref>');
        $this->assertStringNotContainsString('postscript=.', $cs1->parsed_text());
    }

}
