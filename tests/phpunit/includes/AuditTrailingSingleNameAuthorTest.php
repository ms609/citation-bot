<?php
declare(strict_types=1);

/**
 * A trailing single-word name is appended, not written over the first author.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class AuditTrailingSingleNameAuthorTest extends testBaseClass {

    public function testFirstAuthorSurvivesTrailingSingleName(): void {
        $result = format_multiple_authors('Smith AB, Jones CD, Brown');
        $this->assertStringContainsString('Smith', $result);
        $this->assertStringContainsString('Jones', $result);
        $this->assertStringEndsWith('Brown', $result);
        $this->assertSame(2, mb_substr_count($result, ';'));
    }

    public function testSingleNameAloneIsUnchanged(): void {
        $this->assertSame('A.', format_multiple_authors('a'));
    }
}
