<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../src/includes/api/APIResponseGuard.php';

final class APIResponseGuardTest extends PHPUnit\Framework\TestCase {
    public function testDecodeObjectAcceptsExpectedShape(): void {
        $decoded = ExternalApiResponseGuard::decodeObject(
            '{"id":123,"nested":{"value":"ok"}}'
        );

        $this->assertInstanceOf(stdClass::class, $decoded);
        $this->assertSame(123, $decoded->id);
        $this->assertSame('ok', $decoded->nested->value);
    }

    public function testDecodeObjectRejectsMalformedAndWrongTopLevelShapes(): void {
        foreach ([
            '',
            'not json',
            '{"truncated":',
            '[]',
            '["valid","json","but","wrong","shape"]',
            '"scalar"',
            '123',
            'true',
            'null',
            "\xB1\x31",
        ] as $response) {
            $this->assertNull(
                ExternalApiResponseGuard::decodeObject($response),
                'Unexpectedly accepted: ' . bin2hex($response)
            );
        }
    }

    public function testDecodeAssocObjectDistinguishesObjectFromList(): void {
        $this->assertSame(
            ['message' => ['title' => ['Example']]],
            ExternalApiResponseGuard::decodeAssocObject(
                '{"message":{"title":["Example"]}}'
            )
        );
        $this->assertSame([], ExternalApiResponseGuard::decodeAssocObject('{}'));
        $this->assertNull(ExternalApiResponseGuard::decodeAssocObject('[]'));
        $this->assertNull(ExternalApiResponseGuard::decodeAssocObject('[{"x":1}]'));
    }

    public function testLargeJsonIntegerCannotOverflowIntoFloat(): void {
        $decoded = ExternalApiResponseGuard::decodeObject(
            '{"id":922337203685477580799999}'
        );

        $this->assertNotNull($decoded);
        $this->assertSame('922337203685477580799999', $decoded->id);
    }

    public function testExcessiveJsonNestingIsRejected(): void {
        $response = str_repeat('{"x":', 140) . '1' . str_repeat('}', 140);
        $this->assertNull(ExternalApiResponseGuard::decodeObject($response));
    }

    public function testGuardReturnsNormalResult(): void {
        $result = ExternalApiResponseGuard::run(
            'test API',
            static fn (): string => 'ok',
            'fallback'
        );

        $this->assertSame('ok', $result);
    }

    public function testGuardContainsUnexpectedParserThrowable(): void { // This test has invalid code, and verifies that it fails
        $result = ExternalApiResponseGuard::run(
            'test API',
            static function (): string {
                /** @psalm-suppress UnusedFunctionCall */ /** @psalm-suppress InvalidCast */ /** @psalm-suppress InvalidArgument */ /** @phpstan-ignore-next-line */ /** @phan-suppress-next-line PhanTypeMismatchArgumentInternalReal */
                mb_strlen([]);
                return 'unreachable';
            },
            'fallback'
        );

        $this->assertSame('fallback', $result);
    }
}
