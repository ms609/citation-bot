<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class JournalArticleNumberAliasTest extends testBaseClass {
    public function testJournalArticleLocatorUsesSupportedParameter(): void {
        $citation = $this->make_citation('{{cite journal|title=Example|journal=PLOS ONE|article=e153}}');
        $citation->tidy();
        $this->assertSame('', $citation->get('article'));
        $this->assertSame('e153', $citation->get('article-number'));
        $text = $citation->parsed_text();
        $citation->tidy();
        $this->assertSame($text, $citation->parsed_text());
    }

    public function testConflictingOrNonLocatorValuesArePreserved(): void {
        foreach ([
            '{{cite journal|title=Example|article=e153|article-number=e154}}',
            '{{cite journal|title=Example|article=An article title}}',
            '{{cite encyclopedia|article=E153|encyclopedia=Example}}',
        ] as $text) {
            $citation = $this->make_citation($text);
            $citation->tidy_parameter('article');
            $this->assertSame($text, $citation->parsed_text());
        }
    }
}
