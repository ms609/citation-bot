<?php
declare(strict_types=1);

/*
 * Tests for generate_template.php
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once dirname(__DIR__) . '/testBaseClass.php';

final class generate_templateTest extends testBaseClass {

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGeneratesTemplateFromStubbedJstorMetadata(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to stub citation metadata');
        }
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('request_rate_limit_consume_layered', null);
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('jstor_expand_via_zotero', static function (Template $template, string $jstor): void {
            if ($jstor === '373737') {
                $template->add_if_new('title', 'Fixture article');
                $template->add_if_new('journal', 'Fixture journal');
            }
        }, true);
        try {
            ob_start();
            $_GET = ['jstor' => '373737'];
            require dirname(__DIR__, 2) . '/src/generate_template.php';
            $body = (string) ob_get_contents();
        } finally {
            ob_end_clean();
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('jstor_expand_via_zotero');
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('request_rate_limit_consume_layered');
        }
        $this->assertStringContainsString('{{cite journal', $body);
        $this->assertMatchesRegularExpression('~jstor\s*=\s*373737~', $body);
        $this->assertMatchesRegularExpression('~title\s*=\s*Fixture article~', $body);
        $this->assertTemplateFooter($body);
    }

    #[DataProvider('invalidIdentifiers')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTemplateGeneratorRejectsInvalidInput(string $query, string $message): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to observe the endpoint exit');
        }
        parse_str($query, $_GET);
        $response_ended = new RuntimeException('Template generator exited');
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('exit', static function () use ($response_ended): never {
            throw $response_ended;
        }, true);
        try {
            ob_start();
            try {
                require dirname(__DIR__, 2) . '/src/generate_template.php';
                $this->fail('Invalid input did not terminate the endpoint');
            } catch (RuntimeException $exception) {
                $this->assertSame($response_ended, $exception);
            }
            $body = (string) ob_get_contents();
        } finally {
            ob_end_clean();
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('exit');
        }
        $this->assertSame(400, http_response_code());
        $this->assertStringContainsString($message, $body);
        $this->assertTemplateFooter($body);
    }

    private function assertTemplateFooter(string $body): void {
        $this->assertMatchesRegularExpression('~</pre></main><footer>.*</footer></body></html>\s*\z~s', $body);
        $this->assertStringContainsString('Back to Citation Bot</a>', $body);
        $this->assertStringContainsString('Terms&nbsp;of&nbsp;Use</a>', $body);
        $this->assertStringContainsString('Privacy&nbsp;Policy</a>', $body);
    }

    /** @return array<string, array{string, string}> */
    public static function invalidIdentifiers(): array {
        return [
            'missing' => ['', 'Exactly one parameters must be passed'],
            'array' => ['doi%5B%5D=123', 'Invalid parameter type'],
            'invalid UTF-8' => ['doi=%FFabc', 'Invalid UTF-8'],
            'too short' => ['jstor=1', 'Unset parameter'],
            'too long' => ['jstor=' . str_repeat('1', 101), 'Excessive parameter'],
            'forbidden character' => ['doi=10.1%2Ffoo%7Cbar', 'Invalid parameter value'],
            'unknown type' => ['unknown=123', 'Unexpected parameter'],
        ];
    }

    public function testGenTemplate(): void {
        $this->require_live_jstor_ris();
        new TestPage(); // Fill page name with test name for debugging
        $base_buffer_level = ob_get_level();
        // Run API
        ob_start();
        ob_start();
        $_GET['jstor'] = '373737';
        require(dirname(__DIR__, 2) . '/src/generate_template.php');
        unset($_GET);
        $template_text = '';
        while (ob_get_level() > $base_buffer_level) {
            $template_text = ob_get_contents() . $template_text;
            ob_end_clean();
        };
        // Output checking time
        $this->assertTrue((bool) mb_strpos($template_text, '{{cite journal |'));
        $this->assertTrue((bool) mb_strpos($template_text, 'jstor=373737'));
        $this->assertTrue((bool) mb_strpos($template_text, 'DOCTYPE html'));
    }
}
