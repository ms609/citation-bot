<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class DoiRedirectDestinationTest extends testBaseClass {
    public function testDoiRedirectDoesNotDiscardDistinctPaths(): void {
        $this->assertTrue(uopz_set_return('bot_curl_exec', str_repeat('a', 55)));
        $this->assertTrue(uopz_set_return('curl_exec', false));
        $this->assertTrue(uopz_set_return('sleep', 0));
        try {
            foreach ([
                ['https://example.org/école', 'https://example.org/École', false],
                ['https://example.org/caf%C3%A9%23annexe', 'https://example.org/caf%C3%A9', false],
                ['https://EXAMPLE.org/café', 'https://example.org/café', true],
            ] as [$url, $redirect, $duplicate]) {
                $this->assertTrue(uopz_set_return('curl_getinfo', $redirect));
                try {
                    $template = $this->make_citation('{{cite journal|title=Example article|journal=Example Journal' .
                        '|volume=1|issue=1|pages=2–3|year=2024|last=Smith|doi=10.1234/example|doi-access=free' .
                        '|pmc=1234567|url=' . $url . '}}');
                    $templates = [$template];
                    drop_urls_that_match_dois($templates);
                    $this->assertSame($duplicate ? '' : $url, $template->get('url'));
                } finally {
                    $this->assertTrue(uopz_unset_return('curl_getinfo'));
                }
            }
        } finally {
            $this->assertTrue(uopz_unset_return('sleep'));
            $this->assertTrue(uopz_unset_return('curl_exec'));
            $this->assertTrue(uopz_unset_return('bot_curl_exec'));
        }
    }
}
