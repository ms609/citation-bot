<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class S2apiTest extends testBaseClass {
    public function testSemanticScholar(): void {
        $text = "{{cite journal|doi=10.0000/Rubbish_bot_failure_test}}";
        $template = $this->make_citation($text);
        $return = get_unpaywall_url($template, $template->get('doi'));
        $this->assertSame('nothing', $return);
        $this->assertNull($template->get2('url'));
    }
}
