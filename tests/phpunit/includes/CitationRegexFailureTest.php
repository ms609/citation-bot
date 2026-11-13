<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class CitationRegexFailureTest extends testBaseClass {
    public function testVolumeCleanupKeepsValueWhenUnicodeRegexFails(): void {
        $template = $this->make_citation('{{cite journal|title=Example article}}');
        $value = "Vol. 6 \xE9";
        $template->set('volume', $value);
        $template->tidy_parameter('volume');
        $this->assertSame($value, $template->get('volume'));
    }
}
