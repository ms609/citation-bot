<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class MixedEncodingC1QuoteTest extends testBaseClass {
    public function testLegacyQuotesDoNotDamageValidUtf8InTheSameString(): void {
        $this->assertSame("'АБВГД' \"é\" '𐐑'", normalize_c1_quotes("\x91АБВГД\x92 \u{0093}é\u{0094} \x91𐐑\x92"));
        $this->assertSame("Б'\xFF", normalize_c1_quotes("Б\x91\xFF"));
    }
}
