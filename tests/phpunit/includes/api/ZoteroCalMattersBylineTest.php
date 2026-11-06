<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

final class ZoteroCalMattersBylineTest extends testBaseClass {
    public function testOnlyTheCollectiveBylineIsDiscarded(): void {
        $url = 'https://calmatters.org/commentary/2026/02/insurance-commissioner-race-current-field/';
        $citation = $this->make_citation('{{cite web|url=' . $url . '}}');
        Zotero::process_zotero_response(
            '[{"itemType":"webpage","title":"Candidate field","author":[["Guest","Commentary"],["Jane","Guest"]]}]',
            $citation,
            $url,
            0,
            false,
            true,
            true
        );
        $this->assertSame('Jane', $citation->get('first1'));
        $this->assertSame('Guest', $citation->get('last1'));
        $this->assertSame('', $citation->get('last2'));
    }

    public function testCollectiveBylineIsDiscardedFromCreators(): void {
        $url = 'https://calmatters.org/commentary/2026/02/insurance-commissioner-race-current-field/';
        $citation = $this->make_citation('{{cite web|url=' . $url . '}}');
        Zotero::process_zotero_response(
            '[{"itemType":"webpage","title":"Candidate field","creators":[{"creatorType":"author","firstName":"Guest","lastName":"Commentary"}]}]',
            $citation,
            $url,
            0,
            false,
            true,
            true
        );
        $this->assertSame('Candidate field', $citation->get('title'));
        $this->assertSame('', $citation->get('last1'));
    }
}
