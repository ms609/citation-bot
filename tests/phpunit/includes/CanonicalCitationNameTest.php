<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class CanonicalCitationNameTest extends testBaseClass {
    public function testPublisherAndJournalCompareCanonicallyWithoutChangingStoredTitle(): void {
        $title = "Revue de ge\u{0301}ographie";
        $template = $this->make_citation('{{cite journal|title=Example|journal=' . $title . '|publisher=Revue de géographie}}');
        $template->tidy_parameter('publisher');
        $this->assertSame('', $template->get('publisher'));
        $this->assertSame($title, $template->get('journal'));
    }

    public function testZoteroCreatorMatchesCanonicallyEquivalentSiteTitle(): void {
        $url = 'https://example.org/article';
        foreach (['José García' => '', 'Jose García' => 'García'] as $site => $expected) {
            $template = $this->make_citation('{{cite journal|title=Example article|url=' . $url . '}}');
            Zotero::process_zotero_response(json_encode([[
                'itemType' => 'journalArticle', 'title' => 'Example article', 'publicationTitle' => $site,
                'creators' => [['firstName' => "Jose\u{0301}", 'lastName' => 'García', 'creatorType' => 'author']],
            ]], JSON_THROW_ON_ERROR), $template, $url, 0, false, true, true);
            $this->assertSame($expected, $template->get('last1'));
        }
    }
}
