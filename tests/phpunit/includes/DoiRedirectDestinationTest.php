<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class DoiRedirectDestinationTest extends testBaseClass {
    public function testIeeeDownloadUrlsSurviveExtractionAndDoiCleanup(): void {
        foreach ([
            'https://ieeexplore.ieee.org/iel5/12/34/1234567.pdf?download=1',
            'https://ieeexplore.ieee.org/stamp/stamp.jsp?arnumber=1234567',
            'https://ieeexplore.ieee.org/document/1234567/pdf',
        ] as $url) {
            $archive = 'https://web.archive.org/web/20240201000000/' . $url;
            $template = $this->make_citation('{{cite journal|title=Example|doi=10.1109/EXAMPLE.2024.1234567|url=' .
                $url . '|archive-url=' . $archive . '}}');
            $template->get_identifiers_from_url();
            $templates = [$template];
            drop_urls_that_match_dois($templates);
            $this->assertSame($url, $template->get2('url'));
            $this->assertSame($archive, $template->get2('archive-url'));
        }
    }

    public function testIeeeUrlIsDroppedOnlyAfterResolvingToTheSameDocument(): void {
        $url = 'https://ieeexplore.ieee.org/document/1234567';
        $archive = 'https://web.archive.org/web/20240201000000/' . $url;
        foreach ([
            ['10.1109/EXAMPLE.2024.9876543', 'https://ieeexplore.ieee.org/document/7654321', 302, 'body', 1, false],
            ['10.1109/EXAMPLE.2024.9876543', 'https://ieeexplore.ieee.org.evil.example/document/1234567', 302, 'body', 1, false],
            ['10.1109/EXAMPLE.2024.9876543', 'https://publisher.example/redirect', 302, 'body', 1, false],
            ['10.11090/example', $url, 302, 'body', 0, false],
            ['10.1109/EXAMPLE.2024.9876543', $url, 404, 'body', 1, false],
            ['10.1109/EXAMPLE.2024.9876543', $url, 302, false, 1, false],
            ['10.1109/EXAMPLE.2024.9876543', $url . '/', 302, '', 1, true],
        ] as [$doi, $destination, $status, $response, $expected_requests, $duplicate]) {
            $requests = 0;
            $this->assertTrue(uopz_set_return('bot_curl_exec_withFalse', static function () use (&$requests, $response): string|false {
                ++$requests;
                return $response;
            }, true));
            $this->assertTrue(uopz_set_return('curl_getinfo', static function (CurlHandle $handle, int $option) use ($destination, $status): string|int {
                return $option === CURLINFO_REDIRECT_URL ? $destination : $status;
            }, true));
            try {
                $template = $this->make_citation('{{cite journal|title=Example article|doi=' . $doi .
                    '|url=' . $url . '|archive-url=' . $archive . '|access-date=2024-02-01|url-access=subscription}}');
                $templates = [$template];
                drop_urls_that_match_dois($templates);
                $this->assertSame($expected_requests, $requests);
                $this->assertSame($duplicate ? null : $url, $template->get2('url'));
                $this->assertSame($duplicate ? null : $archive, $template->get2('archive-url'));
                $this->assertSame($duplicate ? null : '2024-02-01', $template->get2('access-date'));
                $this->assertSame($duplicate ? null : 'subscription', $template->get2('url-access'));
            } finally {
                $this->assertTrue(uopz_unset_return('curl_getinfo'));
                $this->assertTrue(uopz_unset_return('bot_curl_exec_withFalse'));
            }
        }
    }

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
