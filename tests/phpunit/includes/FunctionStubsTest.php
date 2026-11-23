<?php
declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class FunctionStubsTest extends testBaseClass {
    public function testPartialStubSetupIsCleanedUp(): void {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIsOrContains('Could not stub missing_citation_bot_test_function');

        $stubs = [
            'parse_pii_doi_response' => 'stubbed',
            'missing_citation_bot_test_function' => false,
        ];
        try {
            $this->with_function_stubs($stubs, static function (): void {
                self::fail('The callback must be skipped after a stub fails to install');
            });
        } finally {
            $this->assertSame('', parse_pii_doi_response(''));
        }
    }
}
