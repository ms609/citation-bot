<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class siciTest extends testBaseClass {
    public function testSiciExtraction1(): void {
        $text = '{{cite journal|url=http://fake.url/9999-9999(2002)152[0215:XXXXXX]2.0.CO;2}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('9999-9999', $expanded->get2('issn')); // Fake to avoid cross-ref search
        $this->assertSame('2002', $this->getDateAndYear($expanded));
        $this->assertSame('152', $expanded->get2('volume'));
        $this->assertSame('215', $expanded->get2('page'));
    }

    public function testSiciExtraction2(): void {
        // Now check that parameters are NOT extracted when certain parameters exist
        $text = "{{cite journal|date=2002|journal=SET|url=http:/1/fake.url/0097-3157(2002)152[0215:HPOVBM]2.0.CO;2}}";
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('issn'));
        $this->assertSame('2002', $this->getDateAndYear($expanded));
        $this->assertSame('152', $expanded->get2('volume'));
        $this->assertSame('215', $expanded->get2('page'));
    }
}
