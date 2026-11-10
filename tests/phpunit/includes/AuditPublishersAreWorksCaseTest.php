<?php
declare(strict_types=1);

/**
 * PUBLISHERS_ARE_WORKS is compared with a lower-cased publisher.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditPublishersAreWorksCaseTest extends testBaseClass {

    public function testForbesRussiaIsListedInLowerCase(): void {
        $this->assertContains('forbes (russia)', PUBLISHERS_ARE_WORKS);
    }

    public function testEveryEntryIsLowerCase(): void {
        foreach (PUBLISHERS_ARE_WORKS as $publisher) {
            $this->assertSame(mb_strtolower($publisher), $publisher);
        }
    }
}
