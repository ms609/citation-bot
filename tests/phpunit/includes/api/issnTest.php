<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class issnTest extends testBaseClass {
    public function testUse_ISSN(): void {
        $prepared = $this->process_citation('{{cite news|issn=0140-0460 }}');
        $this->assertSame('[[The Times]]', $prepared->get2('newspaper')); 

        $prepared = $this->process_citation('{{cite news|issn=0190-8286 }}');
        $this->assertSame('[[The Washington Post]]', $prepared->get2('newspaper')); 

        $prepared = $this->process_citation('{{cite news|issn=0362-4331 }}');
        $this->assertSame('[[The New York Times]]', $prepared->get2('newspaper')); 

        $prepared = $this->process_citation('{{cite news|issn=0163-089X }}');
        $this->assertSame('[[The Wall Street Journal]]', $prepared->get2('newspaper')); 

        $prepared = $this->process_citation('{{cite news|issn=1092-0935 }}');
        $this->assertSame('[[The Wall Street Journal]]', $prepared->get2('newspaper')); 
    }
}
