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

    public function testDecodeObjectAcceptsUtf8Bom(): void {
        $decoded = ExternalApiResponseGuard::decodeObject(
            "\xEF\xBB\xBF{\"id\":123}"
        );

        $this->assertInstanceOf(stdClass::class, $decoded);
        $this->assertSame(123, $decoded->id);
        $this->assertSame(
            ['message' => 'ok'],
            ExternalApiResponseGuard::decodeAssocObject(
                "\xEF\xBB\xBF{\"message\":\"ok\"}"
            )
        );
    }

    public function testDecodeObjectPreservesBomCodePointInsideJsonString(): void {
        $decoded = ExternalApiResponseGuard::decodeObject(
            "{\"value\":\"\xEF\xBB\xBF\"}"
        );

        $this->assertNotNull($decoded);
        $this->assertSame("\xEF\xBB\xBF", $decoded->value);
    }

    public function testDecodeObjectAcceptsEscapedSupplementaryPlaneCharacter(): void {
        $decoded = ExternalApiResponseGuard::decodeObject(
            '{"emoji":"\\uD83D\\uDE00"}'
        );

        $this->assertNotNull($decoded);
        $this->assertSame('😀', $decoded->emoji);
    }

    public function testDecodeJsonRejectsUtf16AndUtf32ApiPayloads(): void {
        $asciiJson = '{"id":1}';
        $codeUnits = array_map(
            static fn (string $char): int => ord($char),
            str_split($asciiJson)
        );

        $utf16Le = pack('v*', ...$codeUnits);
        $utf16Be = pack('n*', ...$codeUnits);
        $utf32Le = pack('V*', ...$codeUnits);
        $utf32Be = pack('N*', ...$codeUnits);

        foreach ([
            'UTF-16LE with BOM' => "\xFF\xFE" . $utf16Le,
            'UTF-16BE with BOM' => "\xFE\xFF" . $utf16Be,
            'UTF-32LE with BOM' => "\xFF\xFE\x00\x00" . $utf32Le,
            'UTF-32BE with BOM' => "\x00\x00\xFE\xFF" . $utf32Be,
            'UTF-16LE without BOM' => $utf16Le,
            'UTF-16BE without BOM' => $utf16Be,
            'UTF-32LE without BOM' => $utf32Le,
            'UTF-32BE without BOM' => $utf32Be,
        ] as $encoding => $response) {
            $this->assertNull(
                ExternalApiResponseGuard::decodeJson($response),
                $encoding
            );
        }
    }

    public function testDecodeJsonRejectsLegacyWesternApiEncodings(): void {
        foreach ([
            'Windows-1252' => "{\"value\":\"Caf\xE9\"}",
            'Windows-1250' => "{\"value\":\"\xC8esk\xFD\"}",
            'Windows-1251' => "{\"value\":\"\xCC\xEE\xF1\xEA\xE2\xE0\"}",
            'Windows-1254' => "{\"value\":\"\xDDstanbul\"}",
            'Windows-1255' => "{\"value\":\"\xF9\xEC\xE5\xED\"}",
            'Windows-1256' => "{\"value\":\"\xC7\xE1\xDA\xD1\xC8\xED\xC9\"}",
            'Windows-874' => "{\"value\":\"\xE4\xB7\xC2\"}",
            'ISO-8859-7' => "{\"value\":\"\xC5\xEB\xEB\xDC\xE4\xE1\"}",
            'ISO-8859-15' => "{\"value\":\"\xA4\"}",
            'KOI8-R' => "{\"value\":\"\xED\xCF\xD3\xCB\xD7\xC1\"}",
            'MacRoman' => "{\"value\":\"Caf\x8E\"}",
        ] as $encoding => $response) {
            $this->assertNull(
                ExternalApiResponseGuard::decodeJson($response),
                $encoding
            );
        }
    }

    public function testDecodeJsonRejectsLegacyEastAsianApiEncodings(): void {
        foreach ([
            'Shift_JIS' => "{\"value\":\"\x93\xFA\x96\x7B\"}",
            'Big5' => "{\"value\":\"\xA4\xA4\xA4\xE5\"}",
            'EUC-JP' => "{\"value\":\"\xC6\xFC\xCB\xDC\"}",
            'EUC-KR' => "{\"value\":\"\xC7\xD1\xB1\xB9\"}",
            'GB2312/GBK' => "{\"value\":\"\xD6\xD0\xCE\xC4\"}",
            'GB18030 four-byte sequence' => "{\"value\":\"\x94\x39\xFC\x36\"}",
        ] as $encoding => $response) {
            $this->assertNull(
                ExternalApiResponseGuard::decodeJson($response),
                $encoding
            );
        }
    }

    public function testDecodeJsonRejectsStatefulAndMalformedUnicodeEncodings(): void {
        foreach ([
            'ISO-2022-JP escape sequence' =>
                "{\"value\":\"\x1B\x24\x42\x46\x7C\x4B\x5C\x1B\x28\x42\"}",
            'CESU-8 surrogate pair' =>
                "{\"value\":\"\xED\xA0\xBD\xED\xB8\x80\"}",
            'modified UTF-8 overlong NUL' =>
                "{\"value\":\"\xC0\x80\"}",
            'overlong slash' =>
                "{\"value\":\"\xC0\xAF\"}",
            'UTF-8 encoded surrogate' =>
                "{\"value\":\"\xED\xA0\x80\"}",
            'code point above U+10FFFF' =>
                "{\"value\":\"\xF4\x90\x80\x80\"}",
            'truncated three-byte sequence' =>
                "{\"value\":\"\xE2\x82\"}",
            'lone continuation byte' =>
                "{\"value\":\"\x80\"}",
        ] as $case => $response) {
            $this->assertNull(
                ExternalApiResponseGuard::decodeJson($response),
                $case
            );
        }
    }

    public function testObjectWrappersAlsoRejectRepresentativeAlternateEncodings(): void {
        $utf16Le = "\xFF\xFE" . pack(
            'v*',
            ...array_map(
                static fn (string $char): int => ord($char),
                str_split('{"id":1}')
            )
        );

        foreach ([
            'UTF-16LE' => $utf16Le,
            'Windows-1252' => "{\"value\":\"Caf\xE9\"}",
            'Shift_JIS' => "{\"value\":\"\x93\xFA\x96\x7B\"}",
        ] as $encoding => $response) {
            $this->assertNull(
                ExternalApiResponseGuard::decodeObject($response),
                $encoding . ' object decoder'
            );
            $this->assertNull(
                ExternalApiResponseGuard::decodeAssocObject($response),
                $encoding . ' associative decoder'
            );
        }
    }

    public function testDecodeObjectAcceptsEquivalentValidUtf8ApiPayloads(): void {
        foreach ([
            'Western European' => 'Café',
            'Central European' => 'Český',
            'Cyrillic' => 'Москва',
            'Greek' => 'Ελλάδα',
            'Hebrew' => 'שלום',
            'Arabic' => 'العربية',
            'Thai' => 'ไทย',
            'Japanese' => '日本',
            'Traditional Chinese' => '中文',
            'Korean' => '한국',
            'supplementary plane' => '😀',
        ] as $case => $value) {
            $response = json_encode(
                ['value' => $value],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
            $decoded = ExternalApiResponseGuard::decodeObject($response);

            $this->assertNotNull($decoded, $case);
            $this->assertSame($value, $decoded->value, $case);
        }
    }

    public function testDecodeObjectDoesNotGuessLegacyEncodingWhenBytesAreValidUtf8(): void {
        foreach ([
            'C3 A9 is UTF-8 é even though CP1252 could read it as mojibake' => [
                "{\"value\":\"\xC3\xA9\"}",
                'é',
            ],
            'C2 A9 is UTF-8 copyright even though CP1252 can represent both bytes' => [
                "{\"value\":\"\xC2\xA9\"}",
                '©',
            ],
        ] as $case => [$response, $expected]) {
            $decoded = ExternalApiResponseGuard::decodeObject($response);

            $this->assertNotNull($decoded, $case);
            $this->assertSame($expected, $decoded->value, $case);
        }
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

   public function testDecodeJsonAcceptsSingleBomAndJsonWhitespace(): void {
       $decoded = ExternalApiResponseGuard::decodeJson(
           "\xEF\xBB\xBF \n{\"id\":123}"
       );

       $this->assertInstanceOf(stdClass::class, $decoded);
       $this->assertSame(123, $decoded->id);
   }

   public function testDecodeJsonRejectsDoubleAndMisplacedBom(): void {
       foreach ([
           "\xEF\xBB\xBF\xEF\xBB\xBF{\"id\":1}",
           " \xEF\xBB\xBF{\"id\":1}",
       ] as $response) {
           $this->assertNull(ExternalApiResponseGuard::decodeJson($response));
       }
   }

   public function testDecodeJsonRejectsInvalidUtf8AfterBom(): void {
       $this->assertNull(
           ExternalApiResponseGuard::decodeJson(
               "\xEF\xBB\xBF{\"value\":\"\xFF\"}"
           )
       );
   }

   public function testDecodeAssocObjectAcceptsBomThenWhitespace(): void {
       $this->assertSame(
           ['message' => 'ok'],
           ExternalApiResponseGuard::decodeAssocObject(
               "\xEF\xBB\xBF \r\n{\"message\":\"ok\"}"
           )
       );
   }

   public function testBomOnlyIsRejectedAcrossJsonDecoders(): void {
       $bom = "\xEF\xBB\xBF";
       $this->assertNull(ExternalApiResponseGuard::decodeJson($bom));
       $this->assertNull(ExternalApiResponseGuard::decodeObject($bom));
       $this->assertNull(ExternalApiResponseGuard::decodeAssocObject($bom));
   }
}
