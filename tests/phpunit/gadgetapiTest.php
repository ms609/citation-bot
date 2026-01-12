<?php
declare(strict_types=1);

/*
 * Tests for gadgetapi.php
 */

require_once __DIR__ . '/../testBaseClass.php';

final class gadgetapiTest extends testBaseClass {

    public function testGadget(): void {
        new TestPage(); // Fill page name with test name for debugging
        ob_start();
        $_POST['text'] = '{{cite journal|doi=10.1021/acs.jpca.4c00688 |pmid=<!-- --> |arxiv=<!-- --> |pmc=<!-- --> |url=<!-- --> }}';
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
        $this->assertSame('{{cite journal|last1=Leyser Da Costa Gouveia |first1=Tiago |last2=Maganas |first2=Dimitrios |last3=Neese |first3=Frank |title=Restricted Open-Shell Hartree–Fock Method for a General Configuration State Function Featuring Arbitrarily Complex Spin-Couplings |journal=The Journal of Physical Chemistry A |date=2024 |volume=128 |issue=25 |pages=5041–5053 |doi=10.1021/acs.jpca.4c00688 |pmid=<!-- --> |arxiv=<!-- --> |pmc=<!-- --> |url=<!-- --> }}', $json->expandedtext);
        $this->assertSame('Something Nice | Add: pages, issue, volume, date, journal, title, authors 1-3. | [[:en:WP:UCB|Use this tool]]. [[:en:WP:DBUG|Report bugs]]. | #UCB_Gadget ', $json->editsummary);
    }
}
