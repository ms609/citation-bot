<?php
declare(strict_types=1);

/*
 * Tests for gadgetapi.php
 */

require_once __DIR__ . '/../testBaseClass.php';

final class gadgetapiTest extends testBaseClass {

    public function testGadget(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        ob_start();
        $_POST['text'] = '{{cite|pmid=34213}}';
        $_POST['summary'] = 'Something Nice';
        // Note: gadgetapi.php runs in fast mode by default to prevent timeouts
        require(__DIR__ . '/../../src/gadgetapi.php');
        $json_text = ob_get_contents();
        ob_end_clean();
        while (ob_get_level()) {
            ob_end_flush();
        }
        ob_start(); // PHPUnit turns on a level of buffering itself -- Give it back to avoid "Risky Test"
        unset($_POST);
        unset($_REQUEST);
        // Output checking time
        $json = json_decode($json_text);
        $this->assertSame('{{citation|last1=Weber |first1=F. |last2=Kayser |first2=F. H. |title=Antimicrobial resistance and serotypes of Streptococcus pneumoniae in Switzerland |journal=Schweizerische Medizinische Wochenschrift |date=1979 |volume=109 |issue=11 |pages=395–399 |pmid=34213}}', $json->expandedtext);
        $this->assertSame('Something Nice | Altered template type. Add: pages, issue, volume, date, journal, title, authors 1-2. Removed Template redirect. | [[:en:WP:UCB|Use this tool]]. [[:en:WP:DBUG|Report bugs]]. | #UCB_Gadget ', $json->editsummary);
    }
}
