<?php
declare(strict_types=1);

/**
 * TEMPLATES_WE_HARV is compared with the lower-cased template name.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditHarvTemplateCiteIetfTest extends testBaseClass {

    public function testCiteIetfIsListedInLowerCase(): void {
        $this->assertContains('cite ietf', TEMPLATES_WE_HARV);
        $this->assertNotContains('cite IETF', TEMPLATES_WE_HARV);
    }

    public function testEveryEntryIsLowerCase(): void {
        foreach (TEMPLATES_WE_HARV as $name) {
            $this->assertSame(mb_strtolower($name), $name);
        }
    }
}
