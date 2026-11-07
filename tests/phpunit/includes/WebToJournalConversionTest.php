<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class WebToJournalConversionTest extends testBaseClass {
    private const string DOI = '10.1287/mnsc.35.12.1504';
    private const string URL = 'https://example.org/article';

    #[DataProvider('incompleteCitations')]
    public function testDeclinesIncompleteConversion(string $parameters): void {
        $input = '{{cite web|title=Example Article|url=' . self::URL . $parameters . '}}';
        $template = $this->make_citation($input);

        $template->change_name_to('cite journal', true, true);

        $this->assertSame($input, $template->parsed_text());
    }

    /** @return array<string, array{string}> */
    public static function incompleteCitations(): array {
        return [
            'no container' => [''],
            'website only' => ['|website=Publisher Portal'],
            'website repeats title' => ['|website=Example Article'],
            'journal repeats title' => ['|journal=Example Article'],
            'work repeats series' => ['|work=Series Name|series=Series Name'],
            'work identifies a book series' => ['|work=Lecture Notes in Computer Science'],
            'journal is a hostname' => ['|journal=example.org'],
            'nonpublication work' => ['|work=Semantic Scholar'],
            'placeholder journal' => ['|journal=<!-- Supply a journal name -->'],
            'newspaper' => ['|newspaper=Example Gazette'],
            'magazine' => ['|magazine=Example Magazine'],
            'encyclopedia' => ['|encyclopedia=Example Encyclopedia'],
            'conflicting aliases' => ['|journal=Example Journal|periodical=Other Publication'],
            'chapter citation' => ['|journal=Example Journal|chapter=Section Title'],
        ];
    }

    #[DataProvider('journalContainers')]
    public function testCompleteJournalConversionsStillWork(string $parameter, string $value): void {
        $input = '{{Cite web|title=Example Article|url=' . self::URL . '|' . $parameter . '=' . $value . '}}';
        $template = $this->make_citation($input);

        $template->change_name_to('cite journal');

        $this->assertSame('cite journal', $template->wikiname());
        $this->assertSame($value, $template->get($parameter));
        $this->assertSame(self::URL, $template->get('url'));
    }

    /** @return array<string, array{string, string}> */
    public static function journalContainers(): array {
        return [
            'journal' => ['journal', 'Management Science'],
            'work' => ['work', 'Management Science'],
            'periodical' => ['periodical', 'Management Science'],
            'script journal' => ['script-journal', 'zh:管理科学'],
        ];
    }

    public function testDoiRetainsWebCitationUntilJournalMetadataIsAvailable(): void {
        $template = $this->make_citation('{{cite web|title=Example Article|url=' . self::URL .
            '|website=Publisher Portal|doi=' . self::DOI . '}}');
        HandleCache::$cache_good[self::DOI] = true;
        HandleCache::$cache_active[self::DOI] = true;
        try {
            $template->tidy_parameter('doi');
            $this->assertSame('cite web', $template->wikiname());
            $this->assertSame('Publisher Portal', $template->get('website'));

            process_doi_json($template, self::DOI, [
                'type' => 'journal-article',
                'container-title' => ['Management Science'],
            ]);
            $template->final_tidy();
        } finally {
            unset(HandleCache::$cache_good[self::DOI], HandleCache::$cache_active[self::DOI]);
        }

        $this->assertSame('cite journal', $template->wikiname());
        $this->assertSame('Management Science', $template->get('journal'));
        $this->assertNull($template->get2('website'));
        $this->assertSame(self::DOI, $template->get('doi'));
        $this->assertSame(self::URL, $template->get('url'));
    }
}
