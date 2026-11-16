<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class WikipediaWriteSafetyTest extends testBaseClass {
    public function testEditAssertionVariesWithCredentials(): void {
        $this->assertSame(
            ['text' => 'Text', 'assert' => 'bot', 'md5' => md5('Text')],
            WikipediaBot::edit_safety_parameters('Text', false)
        );
        $this->assertSame(
            ['text' => 'Text', 'assert' => 'user', 'md5' => md5('Text')],
            WikipediaBot::edit_safety_parameters('Text', true)
        );
    }

    public function testChecksumUsesExactUtf8Bytes(): void {
        $text = "{{cite web|title=Zürich|url=https://example.org}}
";
        $params = WikipediaBot::edit_safety_parameters($text, false);
        $this->assertNotNull($params);
        $this->assertSame($text, $params['text']);
        $this->assertSame(md5($text), $params['md5']);
        $this->assertNotSame(md5(mb_rtrim($text)), $params['md5']);
    }

    public function testNfcNormalizationUsesTheExactSubmittedBytes(): void {
        $decomposed = "Cafe\u{0301} Zu\u{0308}rich\n";
        $normalized = "Café Zürich\n";
        $params = WikipediaBot::edit_safety_parameters($decomposed, false)
            ?? throw new \UnexpectedValueException('Normalization unexpectedly failed');
        $this->assertSame($normalized, $params['text']);
        $this->assertSame(md5($normalized), $params['md5']);
        $this->assertNotSame(md5($decomposed), $params['md5']);

        // PHP's form encoder preserves the normalized UTF-8 bytes.
        parse_str(http_build_query($params), $decoded);
        $this->assertSame($normalized, $decoded['text']);
        $this->assertSame($params['md5'], md5($decoded['text']));
    }

    public function testInvalidUtf8IsRepairedBeforeNfcAndHash(): void {
        $input = "Cafe\u{0301}\xC3(";
        $normalized = "Café\u{FFFD}(";
        $previous_substitute = mb_substitute_character();
        try {
            // Verify that repair ignores the pre-existing mbstring setting.
            /** @psalm-suppress UnusedFunctionCall */
            mb_substitute_character('none'); // Ensure the repair ignores the ambient setting.
            $params = WikipediaBot::edit_safety_parameters($input, false)
                ?? throw new \UnexpectedValueException('Repair unexpectedly failed');
            $this->assertSame($normalized, $params['text']);
            $this->assertSame(md5($normalized), $params['md5']);
            $this->assertTrue(mb_check_encoding($params['text'], 'UTF-8'));
            $this->assertSame('none', mb_substitute_character());

            parse_str(http_build_query($params), $decoded);
            $this->assertSame($normalized, $decoded['text']);
            $this->assertSame(md5($decoded['text']), $decoded['md5']);
        } finally {
            // Restore the caller's mbstring configuration after the test.
            /** @psalm-suppress UnusedFunctionCall */
            mb_substitute_character($previous_substitute);
        }
    }

    public function testMalformedUtf8DoesNotBlockEitherWritePreflight(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub the MediaWiki API');
        }
        $actions = [];
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return(WikipediaBot::class, 'fetch', static function (array $request) use (&$actions): ?object {
            $actions[] = $request['action'];
            return null;
        }, true);
        try {
            $bot = new WikipediaBot();
            $this->assertFalse($bot->write_page('Example', "invalid\xC3(", 'Fix citation', 2, ''));
            $this->assertFalse($bot->write_statistics_page('User:Example/stats', "invalid\xC3(", 'Update statistics'));
            $this->assertSame(['query', 'query'], $actions);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return(WikipediaBot::class, 'fetch');
        }
    }
}
