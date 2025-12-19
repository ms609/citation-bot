<?php
declare(strict_types=1);

require_once __DIR__ . '../../doiToolsTest.php';

final class apiFunctionsTest extends testBaseClass {

    public function testExtractDoi(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
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
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', sanitize_doi('10.1111/j.1475-4983.2012.01203.x'));
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', sanitize_doi('10.1111/j.1475-4983.2012.01203.x.')); // extra dot
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', sanitize_doi('10.1111/j.1475-4983.2012.01203.'));  // Missing x after dot
    }
    public function testSanitizeDoi2(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
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
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertFalse(doi_works(''));
        $this->assertFalse(doi_active(''));
        $this->assertFalse(doi_works('   '));
        $this->assertFalse(doi_active('      '));
    }

    public function testDOIWorks2(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertTrue(doi_works('10.1594/PANGAEA.667386'));
        $this->assertFalse(doi_active('10.1594/PANGAEA.667386'));
    }

    public function testDOIWorks3a(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertTrue(doi_works('10.1107/S2056989021000116'));
    }

    public function testDOIWorks3b(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertTrue(doi_works('10.1126/scidip.ado5059'));
    }

    public function testDOIWorks4(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertFalse(doi_works('10.1126/scidip.CITATION_BOT_PLACEHOLDER.ado5059'));
        $this->assertFalse(doi_works('10.1007/springerreference.ado5059'));
        $this->assertFalse(doi_works('10.1126scidip.ado5059'));
        $this->assertFalse(doi_works('123456789/32131423'));
        $this->assertFalse(doi_works('dfasdfsd/CITATION_BOT_PLACEHOLDER'));
        $this->assertFalse(doi_works('/dfadsfasf'));
    }

    public function testHDLworks(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertFalse(hdl_works('10.1126fwerw4w4r2342314'));
        $this->assertFalse(hdl_works('10.1007/CITATION_BOT_PLACEHOLDER.ado5059'));
        $this->assertFalse(hdl_works('10.112/springerreference.ado5059'));
    }

    public function testConference(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertFalse(conference_doi('10.1007/978-3-662-44777_ch3'));
    }

  
    public function testDoubleHopDOI(): void { // Just runs over the code and basically does nothing
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertTrue(doi_works('10.25300/MISQ/2014/38.2.08'));
        $this->assertTrue(doi_works('10.5479/si.00963801.5-301.449'));
    }

    public function testHeaderProblemDOI(): void { // Just runs over the code and basically does nothing
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertTrue(doi_works('10.3403/bsiso10294')); // this one seems to be fussy
    }

    public function testHostIsGoneDOIbasic(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
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
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
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
            $this->assertTrue(true);
        } else {
            bot_debug_log($changes);
            $this->assertTrue(true); // We just have to manually look at this EVERY time
        }
    }

    public function testHostIsGoneDOIHosts(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
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
            $this->assertTrue(true);
        } else {
            bot_debug_log($changes);
            $this->assertTrue(true); // We just have to manually look at this EVERY time
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

}
