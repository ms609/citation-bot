<?php
declare(strict_types=1);

/**
 * Trailing trademark symbols are removed from a work name; a trailing pipe is not a trademark symbol.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditTrademarkSuffixTest extends testBaseClass {

    public function testTrademarkIsRemoved(): void {
        $template = $this->make_citation('{{cite journal|journal=Example Journal™}}');
        $template->tidy_parameter('journal');
        $this->assertSame('Example Journal', $template->get2('journal'));
    }

    public function testRegisteredSignIsRemoved(): void {
        $template = $this->make_citation('{{cite journal|journal=Example Journal®}}');
        $template->tidy_parameter('journal');
        $this->assertSame('Example Journal', $template->get2('journal'));
    }
}
