<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

final class AdsAbsBatchPatch13Test extends testBaseClass {
    public function testOfflineBatchRemapsIdentifierAndPopulatesMetadata(): void {
        $original = '2024FakeJ....00001A';
        $corrected = '2024FakeJ....00002B';
        $template = $this->make_citation('{{cite journal|bibcode=' . $original . '}}');
        $templates = [$template];
        $invocations = 0;
        $provider = static function (array $options, string $url) use (&$invocations, $original, $corrected): stdClass {
            ++$invocations;
            if (!isset($options[CURLOPT_POSTFIELDS]) || !str_contains($url, 'bigquery')) {
                throw new RuntimeException('Unexpected ADS batch request');
            }
            return (object) ['docs' => [(object) [
                'bibcode' => $corrected,
                'identifier' => [$original],
                'title' => ['Offline astronomy reference'],
                'pub' => 'Journal of Synthetic Astronomy',
                'year' => '2024',
                'volume' => '42',
                'issue' => '3',
                'page' => ['12', '15'],
                'author' => ['Offline Researcher'],
            ]]];
        };
        adsabs_api([$original], $templates, 'bibcode', $provider);
        $this->assertSame(1, $invocations);
        $this->assertSame($corrected, $template->get('bibcode'));
        $this->assertSame('Offline astronomy reference', $template->get('title'));
        $this->assertSame('2024', $template->get('year'));
    }

    public function testOfflineBatchCorrectsIdentifierCaseAndSkipsUnmatched(): void {
        $bibcode = '2024FakeJ....00003C';
        $template = $this->make_citation('{{cite journal|bibcode=' . mb_strtolower($bibcode) . '}}');
        $templates = [$template];
        $provider = static fn (array $options, string $url): stdClass => (object) ['docs' => [(object) [
            'bibcode' => $bibcode,
            'title' => ['Offline case-sensitive identifier'],
            'pub' => 'Journal of Synthetic Astronomy',
            'year' => '2024',
        ]]];
        adsabs_api([mb_strtolower($bibcode)], $templates, 'bibcode', $provider);
        $this->assertSame($bibcode, $template->get('bibcode'));
        $this->assertSame('Offline case-sensitive identifier', $template->get('title'));
    }

    public function testOfflineBatchRejectsEmptyAndCompleteInputsBeforeDispatch(): void {
        $sent = 0;
        $provider = static function (array $options, string $url) use (&$sent): stdClass {
            ++$sent;
            return (object) ['docs' => []];
        };
        $templates = [];
        adsabs_api([], $templates, 'bibcode', $provider);
        $this->assertSame(0, $sent);
        $complete = $this->make_citation('{{cite journal|title=Offline reference}}');
        $templates = [$complete];
        adsabs_api([], $templates, 'bibcode', $provider);
        $this->assertSame(0, $sent);
    }

    public function testOfflineBatchRepairsUnmatchedNatSRIdentifier(): void {
        $wrong = '2024NatSR0000E00001';
        $template = $this->make_citation('{{cite journal|bibcode=' . $wrong . '}}');
        $templates = [$template];
        $sent = 0;
        $provider = static function (array $options, string $url) use (&$sent): stdClass {
            ++$sent;
            return (object) ['docs' => []];
        };
        adsabs_api([$wrong], $templates, 'bibcode', $provider);
        $this->assertSame(1, $sent);
        $this->assertSame('2024NatSR0000.00001', $template->get('bibcode'));
    }
}
