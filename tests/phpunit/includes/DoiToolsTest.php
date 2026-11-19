<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;

final class DoiToolsTest extends testBaseClass {

    public function testExtractDoi(): void {
        new TestPage(); // Fill page name with test name for debugging
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/full')[1]);
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract')[1]);
    }

    public function testExtractDoi_A(): void {
        $this->assertSame('10.1016/j.physletb.2010.03.064', extract_doi(' 10.1016%2Fj.physletb.2010.03.064')[1]);
    }

    public function testExtractDoi_B(): void {
        $this->assertSame('10.1093/acref/9780199204632.001.0001', extract_doi('http://www.oxfordreference.com/view/10.1093/acref/9780199204632.001.0001/acref-9780199204632-e-4022')[1]);
    }

    public function testExtractDoi_C(): void {
        $this->assertSame('10.1038/nature11111', extract_doi('http://www.oxfordreference.com/view/10.1038/nature11111/figures#display.aspx?quest=solve&problem=punctuation')[1]);
    }

    public function testExtractDoi_D(): void {
        $the_return = extract_doi('https://somenewssite.com/date/25.10.2015/2137303/default.htm'); // 10.2015/2137303 looks like a DOI
        $this->assertSame('', $the_return[0]);
        $this->assertSame('', $the_return[1]);
    }

    public function testExtractDoiOvidTildeSuffix(): void {
        HandleCache::$cache_hdl_bad['10.1097/01.jac.0000264606.50123.6d~the-role-of-community-health-centers-in-delivering-primary'] = true;
        HandleCache::$cache_hdl_bad['10.1097/01.jac.0000264606.50123'] = true;
        HandleCache::$cache_hdl_bad['10.1097/01.jac.0000264606'] = true;
        HandleCache::$cache_hdl_bad['10.1097/01.jac'] = true;
        HandleCache::$cache_hdl_bad['10.1097/01'] = true;
        HandleCache::$cache_hdl_bad['10.1097'] = true;
        HandleCache::$cache_good['10.1097/01.jac.0000264606.50123.6d'] = true;
        $the_return = extract_doi('10.1097/01.jac.0000264606.50123.6d~the-role-of-community-health-centers-in-delivering-primary?redirectionsource=fulltextview');
        $this->assertSame('10.1097/01.jac.0000264606.50123.6d', $the_return[1]);
    }

    public function testExtractDoiSixDigitRegistrant(): void {
        $doi = '10.123456/example';
        HandleCache::$cache_good[$doi] = true;
        try {
            $this->assertSame($doi, extract_doi('https://example.org/' . $doi)[1]);
        } finally {
            unset(HandleCache::$cache_good[$doi]);
        }
    }

    public function testGetPossibleDoisSevenDigitRegistrant(): void {
        $this->assertContains('10.1234567/example', get_possible_dois('junk 10.1234567/example'));
    }

    public function testSanitizeDoiRemovesTrailingPunctuation(): void {
        new TestPage(); // Fill page name with test name for debugging
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', sanitize_doi('10.1111/j.1475-4983.2012.01203.x'));
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', sanitize_doi('10.1111/j.1475-4983.2012.01203.x.')); // extra dot
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', sanitize_doi('10.1111/j.1475-4983.2012.01203.'));  // Missing x after dot
    }

    public function testSanitizeDoiRemovesTrackingAndSessionSuffixes(): void {
        new TestPage(); // Fill page name with test name for debugging
        $this->assertSame('10.0001/Rubbish_bot_failure_test', sanitize_doi('10.0001/Rubbish_bot_failure_test.')); // Rubbish with trailing dot, just remove it
        $this->assertSame('10.0001/Rubbish_bot_failure_test', sanitize_doi('10.0001/Rubbish_bot_failure_test#page_scan_tab_contents'));
        $this->assertSame('10.0001/Rubbish_bot_failure_test', sanitize_doi('10.0001/Rubbish_bot_failure_test;jsessionid'));
        $this->assertSame('10.0001/Rubbish_bot_failure_test', sanitize_doi('10.0001/Rubbish_bot_failure_test/summary'));
    }

    public function testSanitizeDoiTrailingBackslash(): void {
        new TestPage(); // Fill page name with test name for debugging
        $this->assertSame('10.0001/Rubbish_bot_failure_test', sanitize_doi('10.0001/Rubbish_bot_failure_test\\')); // Trailing backslash
        $this->assertSame('10.0001/Rubbish_bot_failure_test', sanitize_doi('10.0001/Rubbish_bot_failure_test')); // No backslash, unchanged
    }

    public function testJstorInDoi(): void {
        $template = $this->prepare_citation('{{cite journal|jstor=|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}');
        $doi = '10.2307/3241423?junk'; // test 10.2307 code and ? code
        check_doi_for_jstor($doi, $template);
        $this->assertSame('3241423', $template->get2('jstor'));
    }

    public function testNonJstorDoiIsNotInventedAsJstorId(): void {
        $template = $this->prepare_citation('{{cite journal|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}');
        check_doi_for_jstor('10.1016/j.example.2026.12345', $template);
        $this->assertNull($template->get2('jstor'));
    }

    public function testJstorInDoiChecksJstor3111111PmidPmc(): void {
        $template = $this->prepare_citation('{{cite journal|jstor=3111111|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}');
        $doi = '10.2307/3241423?junk';
        check_doi_for_jstor($doi, $template);
        $this->assertSame('3111111', $template->get2('jstor'));
    }

    public function testJstorInDoiChecksJstor3111111PmidPmcCiteJournalJstor3111111(): void {
        $template = $this->prepare_citation('{{cite journal|jstor=3111111|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}');
        $doi = '3241423';
        check_doi_for_jstor($doi, $template);
        $this->assertSame('3111111', $template->get2('jstor'));
    }

    public function testBlankDoisAreRejectedByWorkAndActiveChecks(): void {
        new TestPage(); // Fill page name with test name for debugging
        $this->assertFalse(doi_works(''));
        $this->assertFalse(doi_active(''));
        $this->assertFalse(doi_works('   '));
        $this->assertFalse(doi_active('      '));
    }

    public function testPangaeaDoiIsWellFormedButNotActive(): void {
        new TestPage(); // Fill page name with test name for debugging
        $this->assertTrue(doi_works('10.1594/PANGAEA.667386'));
        $this->assertFalse(doi_active('10.1594/PANGAEA.667386'));
    }

    public function testCrystallographyDoiIsRecognizedAsValid(): void {
        new TestPage(); // Fill page name with test name for debugging
        $this->assertTrue(doi_works('10.1107/S2056989021000116'));
    }

    public function testScienceDoiAcceptedButPlaceholderRejected(): void {
        new TestPage(); // Fill page name with test name for debugging
        $this->assertTrue(doi_works('10.1126/scidip.ado5059'));
    }

    public function testDOIWorksRejects101126ScidipCITATIONBOTPLACE(): void {
        new TestPage(); // Fill page name with test name for debugging
        $this->assertFalse(doi_works('10.1126/scidip.CITATION_BOT_PLACEHOLDER.ado5059'));
    }

    public function testDOIWorksRejects101007SpringerreferenceAdo50(): void {
        $this->assertFalse(doi_works('10.1007/springerreference.ado5059'));
    }

    public function testScienceDoiMissingSlashIsRejected(): void {
        $this->assertFalse(doi_works('10.1126scidip.ado5059'));
    }

    public function testMalformedDoiAndHandleFormatsAreRejected(): void {
        $this->assertFalse(doi_works('123456789/32131423'));
    }

    public function testDOIWorksRejectsDfasdfsdCITATIONBOTPLACEHOLD(): void {
        $this->assertFalse(doi_works('dfasdfsd/CITATION_BOT_PLACEHOLDER'));
    }

    public function testDOIWorksRejectsDfadsfasf(): void {
        $this->assertFalse(doi_works('/dfadsfasf'));
    }

    public function testMalformedHandleIsRejected(): void {
        new TestPage(); // Fill page name with test name for debugging
        $this->assertFalse(hdl_works('10.1126fwerw4w4r2342314'));
    }

    public function testHDLworksb(): void {
        $this->assertFalse(hdl_works('10.1007/CITATION_BOT_PLACEHOLDER.ado5059'));
    }

    public function testHDLworksc(): void {
        $this->assertFalse(hdl_works('10.112/springerreference.ado5059'));
    }

    public function testConference(): void {
        new TestPage(); // Fill page name with test name for debugging
        $this->assertFalse(conference_doi('10.1007/978-3-662-44777_ch3'));
    }

    public function testDoubleHopDOI(): void { // Just runs over the code and basically does nothing
        new TestPage(); // Fill page name with test name for debugging
        $this->assertTrue(doi_works('10.25300/MISQ/2014/38.2.08'));
        $this->assertTrue(doi_works('10.5479/si.00963801.5-301.449'));
    }

    public function testHeaderProblemDOI(): void { // Just runs over the code and basically does nothing
        new TestPage(); // Fill page name with test name for debugging
        $this->assertTrue(doi_works('10.3403/bsiso10294')); // this one seems to be fussy
    }

    public function testHostIsGoneDOIbasic(): void {
        new TestPage(); // Fill page name with test name for debugging
        foreach (NULL_DOI_LIST as $doi => $value) {
            $this->assertSame(mb_trim($doi), $doi);
            $this->assertTrue($value);
            $this->assertSame(safe_preg_replace('~\s~u', '', $doi), $doi);
        }
        foreach (NULL_DOI_ANNOYING as $doi => $value) {
            $this->assertSame(mb_trim($doi), $doi);
            $this->assertTrue($value);
            $this->assertSame(safe_preg_replace('~\s~u', '', $doi), $doi);
        }
        foreach (NULL_DOI_BUT_GOOD as $doi => $value) {
            $this->assertSame(mb_trim($doi), $doi);
            $this->assertTrue($value);
            $this->assertTrue(mb_strpos($doi, '10.') === 0); // No HDLs allowed
            $this->assertSame(safe_preg_replace('~\s~u', '', $doi), $doi);
        }
        $changes = "";
        unset($value);
        foreach (array_keys(NULL_DOI_LIST) as $doi) {
            if (isset(NULL_DOI_BUT_GOOD[$doi])) {
                $changes = $changes . "In Both: " . $doi . "                ";
            }
        }
        foreach (array_keys(NULL_DOI_LIST) as $doi) {
            foreach (NULL_DOI_STARTS_BAD as $bad_start) {
                if (mb_stripos($doi, $bad_start) === 0) {
                    $changes = $changes . "Both in bad and bad start: " . $doi . "                ";
                }
            }
        }
        foreach (array_keys(NULL_DOI_BUT_GOOD) as $doi) {
            foreach (NULL_DOI_STARTS_BAD as $bad_start) {
                if (mb_stripos($doi, $bad_start) === 0) {
                    $changes = $changes . "Both in good and bad start: " . $doi . "                ";
                }
            }
        }
        foreach (array_keys(NULL_DOI_ANNOYING) as $doi) {
            if (!isset(NULL_DOI_LIST[$doi])) {
                $changes = $changes . "Needs to also be in main null list: " . $doi . "           ";
            }
        }
        $this->assertSame("", $changes);
    }

    public function testHostIsGoneDOILoop(): void {
        new TestPage(); // Fill page name with test name for debugging
        $changes = "";
        $this->assertSame("", $changes);
        $do_it = run_type_mods(-1, 500, 850, 995, 990);
        $null_list = array_keys(NULL_DOI_LIST);
        shuffle($null_list); // Avoid doing similar ones next to each other
        foreach ($null_list as $doi) {
            if (isset(NULL_DOI_ANNOYING[$doi])) {
                $works = false;
            } elseif (random_int(0, 999) < $do_it) {
                $works = false;
            } else {
                $works = doi_works($doi);
            }
            if ($works === true) {
                $changes = $changes . "Flagged as good: " . $doi . "             ";
            } elseif ($works === null) { // These nulls are permanent and get mapped to false
                $changes = $changes . "Flagged as null: " . $doi . "             ";
            }
        }
        if ($changes !== '') {
            bot_debug_log($changes);
        }
    }

    #[DoesNotPerformAssertions]
    public function testHostIsGoneDOIHosts(): void {
        new TestPage(); // Fill page name with test name for debugging
        $changes = "";
        // Deal with super common ones that flood the list and are bulk covered with NULL_DOI_STARTS_BAD
        foreach (BAD_DOI_EXAMPLES as $doi) {
            $works = is_doi_works($doi); // Avoid doi_works() code that automatically just returns false based upon list we are checking
            if ($works === null) {
                $changes = $changes . "NULL_DOI_STARTS_BAD Flagged as null: " . $doi . "             ";
            } elseif ($works === true) {
                $changes = $changes . "NULL_DOI_STARTS_BAD Flagged as good: " . $doi . "             ";
            }
            $works = doi_works($doi); // Now use special code which should NEVER fail. F vs f in error messages to tell these apart.
            if ($works === null) {
                $changes = $changes . "NULL_DOI_STARTS_BAD flagged as null: " . $doi . "             ";
            } elseif ($works === true) {
                $changes = $changes . "NULL_DOI_STARTS_BAD flagged as good: " . $doi . "             ";
            }
        }
        if ($changes !== '') {
            bot_debug_log($changes);
        }
    }

    public function testBankruptDOICompany(): void {
        $text = "{{cite journal|doi=10.2277/JUNK_INVALID|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}";
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('doi'));
    }

    public function testForDOIGettingFixed(): void { // These do not work, but it would be nice if they did.    They all have checks in code.  https://search.informit.org/doi/10.3316/aeipt.20772 and such
        $this->assertFalse(doi_works('10.3316/informit.550258516430914'));
    }

    public function testForDOIGettingFixedRejects103316Ielapa347150(): void {
        $this->assertFalse(doi_works('10.3316/ielapa.347150294724689'));
    }

    public function testForDOIGettingFixedRejects103316Agispt199305(): void {
        $this->assertFalse(doi_works('10.3316/agispt.19930546'));
    }

    public function testForDOIGettingFixedRejects103316Aeipt207729(): void {
        $this->assertFalse(doi_works('10.3316/aeipt.207729'));
    }

    public function testForDOIGettingFixedRejects101002Was000204(): void { // DO's, not DOIs
        $this->assertFalse(doi_works('10.1002/was.00020423'));
    }

    public function testOxfordReferenceAcrefDoiIsNormalized(): void {
        $text = '{{cite journal| doi= 10.1093/acref/9780195301731.001.0001/acref-9780195301731-e-41463|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acref/9780195301731.013.41463', $template->get2('doi'));
    }

    public function testOxfordResearchEncyclopediaArticle1357DoiIsNormalized(): void {
        $text = '{{cite journal| doi= 10.1093/acrefore/9780190201098.001.0001/acrefore-9780190201098-e-1357|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780190201098.013.1357', $template->get2('doi'));
    }

    public function testOxfordResearchEncyclopediaArticle1195DoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780190228613.001.0001/acrefore-9780190228613-e-1195 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780190228613.013.1195', $template->get2('doi'));
    }

    public function testOxfordResearchEncyclopediaArticle699DoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780190228620.001.0001/acrefore-9780190228620-e-699 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780190228620.013.699', $template->get2('doi'));
    }

    public function testOxfordResearchEncyclopediaArticle181DoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780190228637.001.0001/acrefore-9780190228637-e-181 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780190228637.013.181', $template->get2('doi'));
    }

    public function testOxfordResearchEncyclopediaArticle384DoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780190236557.001.0001/acrefore-9780190236557-e-384 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780190236557.013.384', $template->get2('doi'));
    }

    public function testOxfordResearchEncyclopediaArticle191DoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780190277734.001.0001/acrefore-9780190277734-e-191 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780190277734.013.191', $template->get2('doi'));
    }

    public function testOxfordResearchEncyclopediaArticle39DoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780190846626.001.0001/acrefore-9780190846626-e-39 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780190846626.013.39', $template->get2('doi'));
    }

    public function testOxfordResearchEncyclopediaArticle45DoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780190854584.001.0001/acrefore-9780190854584-e-45 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780190854584.013.45', $template->get2('doi'));
    }

    public function testOxfordResearchEncyclopediaArticle17DoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780199329175.001.0001/acrefore-9780199329175-e-17 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780199329175.013.17', $template->get2('doi'));
    }

    public function testOxfordResearchEncyclopediaArticle568DoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780199340378.001.0001/acrefore-9780199340378-e-568 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780199340378.013.568', $template->get2('doi'));
    }

    public function testOxfordResearchEncyclopediaArticle2DoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780199366439.001.0001/acrefore-9780199366439-e-2 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780199366439.013.2', $template->get2('doi'));
    }

    public function testOxfordResearchEncyclopediaArticle7023DoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780199381135.001.0001/acrefore-9780199381135-e-7023 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780199381135.013.7023', $template->get2('doi'));
    }

    public function testOxfordResearchEncyclopediaArticle224DoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780199389414.001.0001/acrefore-9780199389414-e-224 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780199389414.013.224', $template->get2('doi'));
    }

    public function testAmericanNationalBiographyDoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/anb/9780198606697.001.0001/anb-9780198606697-e-1800262 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/anb/9780198606697.article.1800262', $template->get2('doi'));
    }

    public function testBenezitDictionaryDoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/benz/9780199773787.001.0001/acref-9780199773787-e-00183827 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/benz/9780199773787.article.B00183827', $template->get2('doi'));
    }

    public function testGroveArtOnlineDoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/gao/9781884446054.001.0001/oao-9781884446054-e-7000082129 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/gao/9781884446054.article.T082129', $template->get2('doi'));
    }

    public function testOxfordMedicineChapterDoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/med/9780199592548.001.0001/med-9780199592548-chapter-199 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/med/9780199592548.003.0199', $template->get2('doi'));
    }

    public function testOxfordBiographyLegacyDoiIsCanonicalized(): void {
        $text = '{{cite journal| doi=10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-29929 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/ref:odnb/29929', $template->get2('doi'));
    }

    public function testOxfordBiographyReferenceDoiIsCanonicalized(): void {
        $text = '{{cite journal| doi=10.1093/ref:odnb/9780198614128.001.0001/odnb-9780198614128-e-29929 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/ref:odnb/29929', $template->get2('doi'));
    }

    public function testOxfordBiographyShortDoiIsCanonicalized(): void {
        $text = '{{cite journal| doi=10.1093/odnb/29929 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/ref:odnb/29929', $template->get2('doi'));
    }

    public function testGroveMusicPlainArticleNumberDoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-0000040055 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/gmo/9781561592630.article.40055', $template->get2('doi'));
    }

    public function testGroveMusicArticleWithAPrefixDoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-1002242442 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/gmo/9781561592630.article.A2242442', $template->get2('doi'));
    }

    public function testGroveMusicArticleWithJPrefixDoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-2000095300 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/gmo/9781561592630.article.J095300', $template->get2('doi'));
    }

    public function testGroveMusicArticleWithLPrefixDoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-4002232256|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/gmo/9781561592630.article.L2232256', $template->get2('doi'));
    }

    public function testGroveMusicArticleWithOPrefixDoiIsNormalized(): void {
        $text = '{{cite journal| doi=10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-5000008391 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/gmo/9781561592630.article.O008391', $template->get2('doi'));
    }

    public function testShortOxfordBiographyDoiIsExpanded(): void {
        $text = '{{cite journal| doi=10.1093/ref:odnb/108196 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/odnb/9780198614128.013.108196', $template->get2('doi'));
    }

    public function testBareOxfordBiographyDoiIsExpanded(): void {
        $text = '{{cite journal| doi=10.1093/9780198614128.013.108196 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/odnb/9780198614128.013.108196', $template->get2('doi'));
    }

    public function testOxfordHandbooksDoiIsCanonicalized(): void {
        $text = '{{cite journal| doi=10.1093/oxfordhb/9780199552238.001.0001/oxfordhb-9780199552238-e-023 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/oxfordhb/9780199552238.003.0023', $template->get2('doi'));
    }

    public function testOxfordScholarshipChapterDoiIsCanonicalized(): void {
        $text = '{{cite journal| doi=10.1093/oso/9780198814122.001.0001/oso-9780198814122-chapter-5 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/oso/9780198814122.003.0005', $template->get2('doi'));
    }

    public function testOxfordScholarshipBookDoiIsCanonicalized(): void {
        $text = '{{cite journal| doi=10.1093/oso/9780190124786.001.0001/oso-9780190124786 |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/oso/9780190124786.001.0001', $template->get2('doi'));
    }

    public function testBrokenDoiDetectionRemovesDoiBrokenDateDoi103265NefrologiaPre2010TitleAcuteRenalFailureDueToMultPmid(): void {
        $text = '{{cite journal|doi=10.3265/Nefrologia.pre2010.May.10269|title=Acute renal failure due to multiple stings by Africanized bees. Report on 43 cases|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi-broken-date'));
    }

    public function testBrokenDoiDetectionKeepsDoiBrokenDateDoi103265NefrologiaNOTAREALDOTitleAcuteRenalFailureDueToMultPmid(): void {
        $text = '{{cite journal|doi=10.3265/Nefrologia.NOTAREALDOI.broken|title=Acute renal failure due to multiple stings by Africanized bees. Report on 43 cases|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('doi-broken-date'));
    }

    public function testBrokenDoiDetectionRemovesDoiBrokenDateDoiPmidPmc(): void {
        $text = '{{cite journal|doi= <!-- MC Hammer says to not touch this -->|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi-broken-date'));
        $this->assertSame('<!-- MC Hammer says to not touch this -->', $expanded->get2('doi'));
    }

    public function testBrokenDoiDetectionRemovesDoiBrokenDateDoiMCHammerSaysToNotTouchThis(): void {
        $text = '{{cite journal|doi= {{MC Hammer says to not touch this}} |pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi-broken-date'));
        $this->assertSame('{{MC Hammer says to not touch this}}', $expanded->get2('doi'));
    }

    public function testBrokenDoiDetectionChecksUrlThisIsNotRealCiteJournal(): void {
        $text = '{{Cite journal|url={{This is not real}}|doi={{I am wrong}}|jstor={{yet another bogus one }}|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('{{Cite journal|url={{This is not real}}|doi={{I am wrong}}|jstor={{yet another bogus one }}|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}', $expanded->parsed_text());
    }

    public function testCrossRefEvilDoi(): void {
        $text = '{{cite journal | doi = 10.1002/(SICI)1097-0134(20000515)39:3<216::AID-PROT40>3.0.CO;2-#|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi-broken-date'));
        $this->assertSame('39', $expanded->get2('volume'));
    }

    public function testDoiExpansionBook(): void {
        $text = "{{cite book|doi=10.1007/978-981-10-3180-9_1|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('cite book', $expanded->wikiname());
        $this->assertSame('978-981-10-3179-3', $expanded->get2('isbn'));
    }

    public function testDoiEndingsChecksDoi10111114754983Doi10111114754983Pmid(): void {
        $text = '{{cite journal | doi=10.1111/j.1475-4983.2012.01203.x/full|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $expanded->get2('doi'));
    }

    public function testDoiEndingsChecksDoi10111114754983UrlOnlinelibraryWileyComDoi10Pmid(): void {
        $text = '{{cite journal| url=http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/full|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $expanded->get2('doi'));
    }

    public function test1093DoiStuffRemovesUrlCiteWebDoi101093BADDDDDDDDBADDDDDDDJViaHose(): void {
        $text = '{{cite web|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|via=hose|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('via'));
        $this->assertNull($template->get2('website'));
        $this->assertNull($template->get2('publisher')); // No publisher: cite document conversion must stay blocked
        $this->assertSame('cite web', $template->wikiname());
        $this->assertSame('hose', $template->get2('work'));
    }

    public function test1093DoiStuffRemovesUrlCiteWebDoi101093BADDDDDDDDBADDDDDDDJWebsiteHose(): void {
        $text = '{{Cite web|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=hose|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('via'));
        $this->assertNull($template->get2('website'));
        $this->assertNull($template->get2('publisher')); // No publisher: cite document conversion must stay blocked
        $this->assertSame('cite web', $template->wikiname());
        $this->assertSame('hose', $template->get2('work'));
    }

    public function test1093DoiStuffRemovesUrlCiteWebDoi101093BADDDDDDDDBADDDDDDDJWebsiteHoseWebsite(): void {
        $text = '{{Cite web|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=hose|via=Hose|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('via'));
        $this->assertNull($template->get2('website'));
        $this->assertNull($template->get2('publisher')); // No publisher: cite document conversion must stay blocked
        $this->assertSame('cite web', $template->wikiname());
        $this->assertSame('hose', $template->get2('work'));
    }

    public function test1093DoiStuffRemovesUrlCiteWebDoi101093BADDDDDDDDBADDDDDDDJWebsiteKittens(): void {
        $text = '{{Cite web|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=kittens|via=doggies|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('via'));
        $this->assertNull($template->get2('website'));
        $this->assertNull($template->get2('publisher')); // No publisher: cite document conversion must stay blocked
        $this->assertSame('cite web', $template->wikiname());
        $this->assertSame('kittens via doggies', $template->get2('work'));
    }

    public function test1093DoiStuffRemovesUrlCiteJournalDoi101093BADDDDDDDDBADDDDDDDJViaHose(): void {
        $text = '{{cite journal|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|via=hose|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('via'));
        $this->assertNull($template->get2('website'));
        $this->assertNull($template->get2('publisher')); // No publisher: cite document conversion must stay blocked
        $this->assertSame('cite journal', $template->wikiname());
        $this->assertSame('Hose', $template->get2('journal'));
    }

    public function test1093DoiStuffRemovesUrlCiteJournalDoi101093BADDDDDDDDBADDDDDDDJWebsiteHose(): void {
        $text = '{{Cite journal|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=hose|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('via'));
        $this->assertNull($template->get2('website'));
        $this->assertNull($template->get2('publisher')); // No publisher: cite document conversion must stay blocked
        $this->assertSame('cite journal', $template->wikiname());
        $this->assertSame('Hose', $template->get2('journal'));
    }

    public function test1093DoiStuffRemovesUrlCiteJournalDoi101093BADDDDDDDDBADDDDDDDJWebsiteHoseWebsite(): void {
        $text = '{{Cite journal|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=hose|via=Hose|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('via'));
        $this->assertNull($template->get2('website'));
        $this->assertNull($template->get2('publisher')); // No publisher: cite document conversion must stay blocked
        $this->assertSame('cite journal', $template->wikiname());
        $this->assertSame('Hose', $template->get2('journal'));
    }

    public function test1093DoiStuffRemovesUrlCiteJournalDoi101093BADDDDDDDDBADDDDDDDJWebsiteKittens(): void {
        $text = '{{Cite journal|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=kittens|via=doggies|pmid=<!-- -->|pmc=<!-- -->|arxiv=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('via'));
        $this->assertNull($template->get2('website'));
        $this->assertNull($template->get2('publisher')); // No publisher: cite document conversion must stay blocked
        $this->assertSame('cite journal', $template->wikiname());
        $this->assertSame('Kittens Via Doggies', $template->get2('journal'));
    }

}
