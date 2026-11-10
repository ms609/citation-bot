<?php
declare(strict_types=1);

/**
 * TEMPLATES_WE_HARV is compared with the trimmed template name.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditHarvTemplateRfk61Test extends testBaseClass {

    public function testRfk61IsListedWithoutPadding(): void {
        $this->assertContains('rfk6.1', TEMPLATES_WE_HARV);
    }

    public function testNoEntryHasEdgeWhitespace(): void {
        foreach (TEMPLATES_WE_HARV as $name) {
            $this->assertSame(mb_trim($name), $name);
        }
    }
}
