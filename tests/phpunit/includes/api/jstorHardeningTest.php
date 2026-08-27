<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class jstorHardeningTest extends testBaseClass {
    public function testRisLinePartsHandlesMalformedLines(): void {
        [$tag, $value] = ris_line_parts('TY - JOUR');
        $this->assertSame('TY', mb_trim($tag));
        $this->assertSame('JOUR', mb_trim($value));

        $this->assertSame(['', ''], ris_line_parts('malformed upstream line'));
        $this->assertSame(['', ''], ris_line_parts(''));
    }

    public function testExpandRisIgnoresMalformedLines(): void {
        $template = $this->make_citation('{{cite journal}}');
        $data = implode("\n", [
            'TY - JOUR',
            'BROKEN LINE',
            'TI - A deterministic title',
            'AU - Example, Alice',
            'ANOTHER BROKEN LINE',
            'ER -',
        ]);

        expand_by_RIS($template, $data, false);

        $this->assertSame('A deterministic title', $template->get2('title'));
    }
}
