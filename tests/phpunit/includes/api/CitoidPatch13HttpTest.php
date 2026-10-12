<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class CitoidPatch13HttpTest extends testBaseClass {
    /** @return array<string, array{int, bool}> */
    public static function statusProvider(): array {
        return [
            'network error' => [0, false],
            'success' => [200, true],
            'created' => [201, true],
            'accepted' => [202, true],
            'no content' => [204, false],
            'reset content' => [205, false],
            'partial content' => [206, true],
            'last success status' => [299, true],
            'redirect' => [302, false],
            'bad request' => [400, false],
            'rate limited' => [429, false],
            'upstream unavailable' => [503, false],
        ];
    }

    #[DataProvider('statusProvider')]
    public function testEmptyResponseRetryIsOnlyForPotentiallyBodyBearingSuccess(
        int $status,
        bool $expected
    ): void {
        $this->assertSame($expected, Zotero::citoid_empty_body_retryable($status));
    }
}
