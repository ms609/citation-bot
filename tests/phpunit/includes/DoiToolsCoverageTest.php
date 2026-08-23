<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class DoiToolsCoverageTest extends testBaseClass {

    public function testFreeMemoryResetsEveryCache(): void {
        HandleCache::$cache_active = ['active' => true];
        HandleCache::$cache_inactive = ['inactive' => true];
        HandleCache::$cache_good = ['good' => true];
        HandleCache::$cache_hdl_loc = ['handle' => 'https://example.com'];
        HandleCache::$cache_hdl_bad = ['bad' => true];
        HandleCache::$cache_hdl_null = ['null' => true];

        HandleCache::free_memory();

        $this->assertSame([], HandleCache::$cache_active);
        $this->assertSame(BAD_DOI_ARRAY, HandleCache::$cache_inactive);
        $this->assertSame([], HandleCache::$cache_good);
        $this->assertSame([], HandleCache::$cache_hdl_loc);
        $this->assertSame(BAD_DOI_ARRAY, HandleCache::$cache_hdl_bad);
        $this->assertSame([], HandleCache::$cache_hdl_null);
    }

    public function testHdlWorksRejectsReservedAndOversizedIdentifiers(): void {
        $this->assertFalse(hdl_works('123456789/citation-bot'));
        $this->assertNull(hdl_works('10.' . str_repeat('x', HandleCache::MAX_HDL_SIZE) . '/citation-bot'));
    }

    public function testIsDoiWorksRejectsLocalSpecialCases(): void {
        $this->assertFalse(is_doi_works((string) array_key_first(NULL_DOI_ANNOYING)));
        $this->assertFalse(is_doi_works('10.4435/BSPI.2024.06'));
    }

    public function testIsDoiWorksAcceptsKnownPublisherExceptions(): void {
        $this->assertTrue(is_doi_works($this->knownGoodDoiStartingWith('10.1353/')));
        $this->assertTrue(is_doi_works($this->knownGoodDoiStartingWith('10.1175/')));
    }

    public function testInterpretDoiHeaderHandlesThreeHopResponses(): void {
        $headers = [
            '0' => 'HTTP/1.1 302 Found',
            '1' => 'HTTP/1.1 301 Moved Permanently',
            '2' => 'HTTP/1.1 404 Not Found',
            'Location' => 'https://example.com',
        ];

        $this->assertFalse(interpret_doi_header($headers, (string) array_key_first(NULL_DOI_LIST)));
        $this->assertTrue(interpret_doi_header($headers, (string) array_key_first(NULL_DOI_BUT_GOOD)));
        $this->assertNull(interpret_doi_header($headers, '10.5555/citation-bot-coverage'));
    }

    public function testInterpretDoiHeaderHandlesServiceUnavailableResponses(): void {
        $headers = [
            '0' => 'HTTP/1.1 302 Found',
            '1' => 'HTTP/1.1 503 Service Unavailable',
            '2' => '',
            'Location' => 'https://example.com',
        ];

        $this->assertFalse(interpret_doi_header($headers, (string) array_key_first(NULL_DOI_LIST)));
        $this->assertTrue(interpret_doi_header($headers, (string) array_key_first(NULL_DOI_BUT_GOOD)));
        $this->assertNull(interpret_doi_header($headers, '10.5555/citation-bot-coverage'));
    }

    public function testCheckDoiForJstorIgnoresEmptyAndNumericValues(): void {
        $template = $this->make_citation('{{cite journal}}');

        check_doi_for_jstor('', $template);
        check_doi_for_jstor('3241423', $template);

        $this->assertNull($template->get2('jstor'));
    }

    private function knownGoodDoiStartingWith(string $prefix): string {
        foreach (array_keys(NULL_DOI_BUT_GOOD) as $doi) {
            if (mb_strpos($doi, $prefix) === 0) {
                return $doi;
            }
        }
        $this->fail('No known-good DOI starts with ' . $prefix);
    }
}
