<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

final class ZoteroResearchGateTitleTest extends testBaseClass {
    public function testResearchGateLandingTitleIsRejected(): void {
        $url = 'https://www.researchgate.net/publication/280984329';
        $citation = $this->make_citation('{{cite web|url=' . $url . '}}');
        Zotero::process_zotero_response(
            '[{"itemType":"webpage","title":"160+ million publication pages organized by topic on ResearchGate"}]',
            $citation,
            $url,
            0,
            false,
            true,
            true
        );
        $this->assertSame('', $citation->get('title'));

        Zotero::process_zotero_response(
            '[{"itemType":"webpage","title":"A study of Viennese humor"}]',
            $citation,
            $url,
            0,
            false,
            true,
            true
        );
        $this->assertSame('A study of Viennese humor', $citation->get('title'));
    }
}
