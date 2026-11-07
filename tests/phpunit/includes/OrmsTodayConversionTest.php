<?php
declare(strict_types=1);

/*
 * Tests that citations to ORMS Today (an INFORMS magazine, DOI prefix 10.1287/orms.) are not
 * converted from cite web to cite journal.  The conversion used to leave a cite journal with no
 * journal= and the old container name in website=, see User talk:Citation bot, "Half-assed
 * conversion of cite web to cite journal" (Special:Diff/1271282424).
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class OrmsTodayConversionTest extends testBaseClass {

    private const string ORMS_DOI = '10.1287/orms.2022.05.15n';
    // Management Science, known to resolve
    private const string OTHER_INFORMS_DOI = '10.1287/mnsc.35.12.1504';

    /** The DOI lookups would need the network, so mark the ORMS DOI as one that resolves and is in Crossref. */
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        HandleCache::$cache_good[self::ORMS_DOI] = true;
        HandleCache::$cache_active[self::ORMS_DOI] = true;
    }

    #[\Override]
    protected function tearDown(): void {
        unset(HandleCache::$cache_good[self::ORMS_DOI], HandleCache::$cache_active[self::ORMS_DOI]);
        parent::tearDown();
    }

    public function testUrlOnlyOrmsCitationIsNotConvertedToCiteJournal(): void {
        // The citation in Special:Diff/1271282424: website= holds the article title, and the DOI comes from the URL
        $text = '{{Cite web |date=October 4, 2022 |title=INFORMS 2023 Board of Directors Election Results |url=https://pubsonline.informs.org/do/10.1287/orms.2022.05.15n/full/ |access-date=October 4, 2022 |website=INFORMS 2023 Board of Directors Election Results}}';
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $template->tidy();
        $this->assertSame('cite web', $template->wikiname());
        $this->assertSame(self::ORMS_DOI, $template->get('doi'));
        $this->assertSame('INFORMS 2023 Board of Directors Election Results', $template->get('website'));
        $this->assertNull($template->get2('journal'));
    }

    public function testWebsiteOrmsTodayIsNotMadeJournal(): void {
        // website= is not one of the parameters the earlier ORMS Today block looked at
        $text = '{{Cite web |title=Some ORMS article |url=https://pubsonline.informs.org/do/10.1287/orms.2022.05.15n/full/ |website=ORMS Today |doi=' . self::ORMS_DOI . ' }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame($text, $template->parsed_text());
    }

    public function testWorkOrmsTodayIsStillNotMadeJournal(): void {
        $text = '{{Cite web |title=Some ORMS article |url=https://pubsonline.informs.org/do/10.1287/orms.2022.05.15n/full/ |work=ORMS Today |doi=' . self::ORMS_DOI . ' }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame($text, $template->parsed_text());
    }

    public function testChangeNameToCiteJournalIsDeclinedForOrmsDoi(): void {
        $text = '{{Cite web |title=Some ORMS article |url=https://example.com/orms |website=Some site |doi=' . self::ORMS_DOI . ' }}';
        $template = $this->make_citation($text);
        $template->change_name_to('cite journal');
        $this->assertSame($text, $template->parsed_text());
        $template->change_name_to('cite journal', true, true);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testOtherInformsDoiStillBecomesJournal(): void {
        // Only ORMS Today is held back, not the rest of the INFORMS 10.1287 prefix
        $text = '{{Cite web |title=Some article |url=https://example.com/paper |work=Management Science |doi=' . self::OTHER_INFORMS_DOI . ' }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('cite journal', $template->wikiname());
    }
}
