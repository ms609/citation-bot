<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class doiToolsTest extends testBaseClass {

    public function testExtractDoi(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/full')[1]);
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract')[1]);
        $this->assertSame('10.1016/j.physletb.2010.03.064', extract_doi(' 10.1016%2Fj.physletb.2010.03.064')[1]);
        $this->assertSame('10.1093/acref/9780199204632.001.0001', extract_doi('http://www.oxfordreference.com/view/10.1093/acref/9780199204632.001.0001/acref-9780199204632-e-4022')[1]);
        $this->assertSame('10.1038/nature11111', extract_doi('http://www.oxfordreference.com/view/10.1038/nature11111/figures#display.aspx?quest=solve&problem=punctuation')[1]);
        $the_return = extract_doi('https://somenewssite.com/date/25.10.2015/2137303/default.htm'); // 10.2015/2137303 looks like a DOI
        $this->assertSame('', $the_return[0]);
        $this->assertSame('', $the_return[1]);
    }

    public function testSanitizeDoi1(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', sanitize_doi('10.1111/j.1475-4983.2012.01203.x'));
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', sanitize_doi('10.1111/j.1475-4983.2012.01203.x.')); // extra dot
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', sanitize_doi('10.1111/j.1475-4983.2012.01203.'));  // Missing x after dot
    }
    public function testSanitizeDoi2(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        $this->assertSame('10.0000/Rubbish_bot_failure_test', sanitize_doi('10.0000/Rubbish_bot_failure_test.')); // Rubbish with trailing dot, just remove it
        $this->assertSame('10.0000/Rubbish_bot_failure_test', sanitize_doi('10.0000/Rubbish_bot_failure_test#page_scan_tab_contents'));
        $this->assertSame('10.0000/Rubbish_bot_failure_test', sanitize_doi('10.0000/Rubbish_bot_failure_test;jsessionid'));
        $this->assertSame('10.0000/Rubbish_bot_failure_test', sanitize_doi('10.0000/Rubbish_bot_failure_test/summary'));
    }

    public function testJstorInDoi(): void {
        $template = $this->prepare_citation('{{cite journal|jstor=}}');
        $doi = '10.2307/3241423?junk'; // test 10.2307 code and ? code
        check_doi_for_jstor($doi, $template);
        $this->assertSame('3241423', $template->get2('jstor'));
    }

    public function testJstorInDoi2(): void {
        $template = $this->prepare_citation('{{cite journal|jstor=3111111}}');
        $doi = '10.2307/3241423?junk';
        check_doi_for_jstor($doi, $template);
        $this->assertSame('3111111', $template->get2('jstor'));
    }

    public function testJstorInDoi3(): void {
        $template = $this->prepare_citation('{{cite journal|jstor=3111111}}');
        $doi = '3241423';
        check_doi_for_jstor($doi, $template);
        $this->assertSame('3111111', $template->get2('jstor'));
    }

    public function testDOIWorks(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        $this->assertFalse(doi_works(''));
        $this->assertFalse(doi_active(''));
        $this->assertFalse(doi_works('   '));
        $this->assertFalse(doi_active('      '));
    }

    public function testDOIWorks2(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        $this->assertTrue(doi_works('10.1594/PANGAEA.667386'));
        $this->assertFalse(doi_active('10.1594/PANGAEA.667386'));
    }

    public function testDOIWorks3a(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        $this->assertTrue(doi_works('10.1107/S2056989021000116'));
    }

    public function testDOIWorks3b(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        $this->assertTrue(doi_works('10.1126/scidip.ado5059'));
    }

    public function testDOIWorks4(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        $this->assertFalse(doi_works('10.1126/scidip.CITATION_BOT_PLACEHOLDER.ado5059'));
        $this->assertFalse(doi_works('10.1007/springerreference.ado5059'));
        $this->assertFalse(doi_works('10.1126scidip.ado5059'));
        $this->assertFalse(doi_works('123456789/32131423'));
        $this->assertFalse(doi_works('dfasdfsd/CITATION_BOT_PLACEHOLDER'));
        $this->assertFalse(doi_works('/dfadsfasf'));
    }

    public function testHDLworks(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        $this->assertFalse(hdl_works('10.1126fwerw4w4r2342314'));
        $this->assertFalse(hdl_works('10.1007/CITATION_BOT_PLACEHOLDER.ado5059'));
        $this->assertFalse(hdl_works('10.112/springerreference.ado5059'));
    }

    public function testConference(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        $this->assertFalse(conference_doi('10.1007/978-3-662-44777_ch3'));
    }

    public function testDoubleHopDOI(): void { // Just runs over the code and basically does nothing
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        $this->assertTrue(doi_works('10.25300/MISQ/2014/38.2.08'));
        $this->assertTrue(doi_works('10.5479/si.00963801.5-301.449'));
    }

    public function testHeaderProblemDOI(): void { // Just runs over the code and basically does nothing
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        $this->assertTrue(doi_works('10.3403/bsiso10294')); // this one seems to be fussy
    }

    public function testHostIsGoneDOIbasic(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
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
        foreach (NULL_DOI_LIST as $doi => $value) {
            if (isset(NULL_DOI_BUT_GOOD[$doi])) {
                $changes = $changes . "In Both: " . $doi . "                ";
            }
        }
        foreach (NULL_DOI_LIST as $doi => $value) {
            foreach (NULL_DOI_STARTS_BAD as $bad_start) {
                if (mb_stripos($doi, $bad_start) === 0) {
                    $changes = $changes . "Both in bad and bad start: " . $doi . "                ";
                }
            }
        }
        foreach (NULL_DOI_BUT_GOOD as $doi => $value) {
            foreach (NULL_DOI_STARTS_BAD as $bad_start) {
                if (mb_stripos($doi, $bad_start) === 0) {
                    $changes = $changes . "Both in good and bad start: " . $doi . "                ";
                }
            }
        }
        foreach (NULL_DOI_ANNOYING as $doi => $value) {
            if (!isset(NULL_DOI_LIST[$doi])) {
                $changes = $changes . "Needs to also be in main null list: " . $doi . "           ";
            }
        }
        $this->assertSame("", $changes);
    }

    public function testHostIsGoneDOILoop(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        $changes = "";
        $this->assertSame("", $changes);
        $eventName = getenv('GITHUB_EVENT_NAME');
        if ($eventName === 'schedule') {
            $do_it = 50;
        } elseif ($eventName === 'push') {
            $do_it = 85;
        } elseif ($eventName === 'pull_request') {
            $do_it = 98;
        } else {
            $do_int = -1;
            report_error('We got wrong data in testHostIsGoneDOILoop: ' . echoable($eventName));
        }
        $null_list = array_keys(NULL_DOI_LIST);
        shuffle($null_list); // Avoid doing similar ones next to each other
        foreach ($null_list as $doi) {
            if (isset(NULL_DOI_ANNOYING[$doi])) {
                $works = false;
            } elseif (random_int(0, 99) < $do_it) {
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
        if ($changes === '') {
            $this->assertFaker();
        } else {
            bot_debug_log($changes);
            $this->assertFaker(); // We just have to manually look at this EVERY time
        }
    }

    public function testHostIsGoneDOIHosts(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        $changes = "";
        // Deal with super common ones that flood the list and are bulk covered with NULL_DOI_STARTS_BAD
        $this->assertSame("", $changes);
        foreach (BAD_DOI_EXAMPLES as $doi) {
            $works = doi_works($doi);
            if ($works === null) {
                $changes = $changes . "NULL_DOI_STARTS_BAD Flagged as null: " . $doi . "             ";
            } elseif ($works === true) {
                $changes = $changes . "NULL_DOI_STARTS_BAD Flagged as good: " . $doi . "             ";
            }
        }
        if ($changes === '') {
            $this->assertFaker();
        } else {
            bot_debug_log($changes);
            $this->assertFaker(); // We just have to manually look at this EVERY time
        }
    }

    public function testBankruptDOICompany(): void {
        $text = "{{cite journal|doi=10.2277/JUNK_INVALID}}";
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('doi'));
    }

    public function testForDOIGettingFixed(): void { // These do not work, but it would be nice if they did.    They all have checks in code
        // https://search.informit.org/doi/10.3316/aeipt.20772 and such
        $this->assertFalse(doi_works('10.3316/informit.550258516430914'));
        $this->assertFalse(doi_works('10.3316/ielapa.347150294724689'));
        $this->assertFalse(doi_works('10.3316/agispt.19930546'));
        $this->assertFalse(doi_works('10.3316/aeipt.207729'));
        // DO's, not DOIs
        $this->assertFalse(doi_works('10.1002/was.00020423'));
    }

    public function testFixLotsOfDOIs1(): void {
        $text = '{{cite journal| doi= 10.1093/acref/9780195301731.001.0001/acref-9780195301731-e-41463}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acref/9780195301731.013.41463', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs2(): void {
        $text = '{{cite journal| doi= 10.1093/acrefore/9780190201098.001.0001/acrefore-9780190201098-e-1357}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780190201098.013.1357', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs3(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780190228613.001.0001/acrefore-9780190228613-e-1195 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780190228613.013.1195', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs4(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780190228620.001.0001/acrefore-9780190228620-e-699 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780190228620.013.699', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs5(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780190228637.001.0001/acrefore-9780190228637-e-181 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780190228637.013.181', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs6(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780190236557.001.0001/acrefore-9780190236557-e-384 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780190236557.013.384', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs7(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780190277734.001.0001/acrefore-9780190277734-e-191 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780190277734.013.191', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs8(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780190846626.001.0001/acrefore-9780190846626-e-39 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780190846626.013.39', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs9(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780190854584.001.0001/acrefore-9780190854584-e-45 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780190854584.013.45', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs10(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780199329175.001.0001/acrefore-9780199329175-e-17 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780199329175.013.17', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs11(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780199340378.001.0001/acrefore-9780199340378-e-568 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780199340378.013.568', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs12(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780199366439.001.0001/acrefore-9780199366439-e-2 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780199366439.013.2', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs13(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780199381135.001.0001/acrefore-9780199381135-e-7023 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780199381135.013.7023', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs14(): void {
        $text = '{{cite journal| doi=10.1093/acrefore/9780199389414.001.0001/acrefore-9780199389414-e-224 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/acrefore/9780199389414.013.224', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs15(): void {
        $text = '{{cite journal| doi=10.1093/anb/9780198606697.001.0001/anb-9780198606697-e-1800262 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/anb/9780198606697.article.1800262', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs16(): void {
        $text = '{{cite journal| doi=10.1093/benz/9780199773787.001.0001/acref-9780199773787-e-00183827 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/benz/9780199773787.article.B00183827', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs17(): void {
        $text = '{{cite journal| doi=10.1093/gao/9781884446054.001.0001/oao-9781884446054-e-7000082129 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/gao/9781884446054.article.T082129', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs18(): void {
        $text = '{{cite journal| doi=10.1093/med/9780199592548.001.0001/med-9780199592548-chapter-199 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/med/9780199592548.003.0199', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs19(): void {
        $text = '{{cite journal| doi=10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-29929 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/ref:odnb/29929', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs20(): void {
        $text = '{{cite journal| doi=10.1093/ref:odnb/9780198614128.001.0001/odnb-9780198614128-e-29929 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/ref:odnb/29929', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs21(): void {
        $text = '{{cite journal| doi=10.1093/odnb/29929 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/ref:odnb/29929', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs23(): void {
        $text = '{{cite journal| doi=10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-0000040055 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/gmo/9781561592630.article.40055', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs24(): void {
        $text = '{{cite journal| doi=10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-1002242442 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/gmo/9781561592630.article.A2242442', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs25(): void {
        $text = '{{cite journal| doi=10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-2000095300 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/gmo/9781561592630.article.J095300', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs26(): void {
        $text = '{{cite journal| doi=10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-4002232256}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/gmo/9781561592630.article.L2232256', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs27(): void {
        $text = '{{cite journal| doi=10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-5000008391 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/gmo/9781561592630.article.O008391', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs28(): void {
        $text = '{{cite journal| doi=10.1093/ref:odnb/108196 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/odnb/9780198614128.013.108196', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs29(): void {
        $text = '{{cite journal| doi=10.1093/9780198614128.013.108196 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/odnb/9780198614128.013.108196', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs30(): void {
        $text = '{{cite journal| doi=10.1093/oxfordhb/9780199552238.001.0001/oxfordhb-9780199552238-e-023 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/oxfordhb/9780199552238.003.0023', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs31(): void {
        $text = '{{cite journal| doi=10.1093/oso/9780198814122.001.0001/oso-9780198814122-chapter-5 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/oso/9780198814122.003.0005', $template->get2('doi'));
    }

    public function testFixLotsOfDOIs32(): void {
        $text = '{{cite journal| doi=10.1093/oso/9780190124786.001.0001/oso-9780190124786 }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertSame('10.1093/oso/9780190124786.001.0001', $template->get2('doi'));
    }

    public function testBrokenDoiDetection1(): void {
        $text = '{{cite journal|doi=10.3265/Nefrologia.pre2010.May.10269|title=Acute renal failure due to multiple stings by Africanized bees. Report on 43 cases}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi-broken-date'));
    }

    public function testBrokenDoiDetection2(): void {
        $text = '{{cite journal|doi=10.3265/Nefrologia.NOTAREALDOI.broken|title=Acute renal failure due to multiple stings by Africanized bees. Report on 43 cases}}';
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('doi-broken-date'));
    }

    public function testBrokenDoiDetection3(): void {
        $text = '{{cite journal|doi= <!-- MC Hammer says to not touch this -->}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi-broken-date'));
        $this->assertSame('<!-- MC Hammer says to not touch this -->', $expanded->get2('doi'));
    }

    public function testBrokenDoiDetection4(): void {
        $text = '{{cite journal|doi= {{MC Hammer says to not touch this}} }}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi-broken-date'));
        $this->assertSame('{{MC Hammer says to not touch this}}', $expanded->get2('doi'));
    }

    public function testBrokenDoiDetection5(): void {
        $text = '{{Cite journal|url={{This is not real}}|doi={{I am wrong}}|jstor={{yet another bogus one }}}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('{{Cite journal|url={{This is not real}}|doi={{I am wrong}}|jstor={{yet another bogus one }}}}', $expanded->parsed_text());
    }

    public function testCrossRefEvilDoi(): void {
        $text = '{{cite journal | doi = 10.1002/(SICI)1097-0134(20000515)39:3<216::AID-PROT40>3.0.CO;2-#}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi-broken-date'));
        $this->assertSame('39', $expanded->get2('volume'));
    }

    public function testDoiExpansionBook(): void {
        $text = "{{cite book|doi=10.1007/978-981-10-3180-9_1}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('cite book', $expanded->wikiname());
        $this->assertSame('978-981-10-3179-3', $expanded->get2('isbn'));
    }

    public function testDoiEndings1(): void {
        $text = '{{cite journal | doi=10.1111/j.1475-4983.2012.01203.x/full}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $expanded->get2('doi'));
    }

    public function testDoiEndings2(): void {
        $text = '{{cite journal| url=http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/full}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', $expanded->get2('doi'));
    }

    public function test1093DoiStuff1(): void {
        $text = '{{cite web|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|via=hose}}';
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('via'));
        $this->assertNull($template->get2('website'));
        $this->assertSame('cite document', $template->wikiname());
        $this->assertSame('hose', $template->get2('work'));
    }

    public function test1093DoiStuff2(): void {
        $text = '{{Cite web|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=hose}}';
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('via'));
        $this->assertNull($template->get2('website'));
        $this->assertSame('cite document', $template->wikiname());
        $this->assertSame('hose', $template->get2('work'));
    }

    public function test1093DoiStuff3(): void {
        $text = '{{Cite web|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=hose|via=Hose}}';
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('via'));
        $this->assertNull($template->get2('website'));
        $this->assertSame('cite document', $template->wikiname());
        $this->assertSame('hose', $template->get2('work'));
    }

    public function test1093DoiStuff4(): void {
        $text = '{{Cite web|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=kittens|via=doggies}}';
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('via'));
        $this->assertNull($template->get2('website'));
        $this->assertSame('cite document', $template->wikiname());
        $this->assertSame('kittens via doggies', $template->get2('work'));
    }

    public function test1093DoiStuff5(): void {
        $text = '{{cite journal|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|via=hose}}';
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('via'));
        $this->assertNull($template->get2('website'));
        $this->assertSame('cite document', $template->wikiname());
        $this->assertSame('Hose', $template->get2('journal'));
    }

    public function test1093DoiStuff6(): void {
        $text = '{{Cite journal|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=hose}}';
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('via'));
        $this->assertNull($template->get2('website'));
        $this->assertSame('cite document', $template->wikiname());
        $this->assertSame('Hose', $template->get2('journal'));
    }

    public function test1093DoiStuff7(): void {
        $text = '{{Cite journal|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=hose|via=Hose}}';
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('via'));
        $this->assertNull($template->get2('website'));
        $this->assertSame('cite document', $template->wikiname());
        $this->assertSame('Hose', $template->get2('journal'));
    }

    public function test1093DoiStuff8(): void {
        $text = '{{Cite journal|url=X|doi=10.1093/BADDDDDDDD/BADDDDDDD/junl|website=kittens|via=doggies}}';
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('via'));
        $this->assertNull($template->get2('website'));
        $this->assertSame('cite document', $template->wikiname());
        $this->assertSame('Kittens Via Doggies', $template->get2('journal'));
    }

}
