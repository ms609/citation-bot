<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 2) . '/testBaseClass.php';
require_once dirname(__DIR__, 3) . '/tools/update_retraction_watch.php';

final class RetractionWatchTest extends testBaseClass {
    private string $directory;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/citation-bot-rw-' . bin2hex(random_bytes(8));
    }

    #[\Override]
    protected function tearDown(): void {
        if (is_dir($this->directory)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($files as $file) {
                if ($file->isDir() && !$file->isLink()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            rmdir($this->directory);
        }
        parent::tearDown();
    }

    /** @param list<list<string>> $rows */
    private function publish(array $rows): RetractionWatch {
        $this->assertSame(count($rows), RetractionWatchRefresh::run($this->directory, static function ($file) use ($rows): void {
            fputcsv($file, RetractionWatch::COLUMNS, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($file, $row, ',', '"', '');
            }
        }, 1));
        $snapshot = RetractionWatch::open($this->directory);
        if ($snapshot === null) {
            $this->fail('Could not open the published Retraction Watch snapshot');
        }
        return $snapshot;
    }

    /** @return list<string> */
    private static function row(
        string $id,
        string $doi = '10.1234/original',
        string $pmid = '12345',
        string $type = 'Retraction',
        string $date = '8/1/2020 0:00',
        string $noticeDoi = '10.1234/notice',
        string $noticePmid = '67890',
        string $url = '',
    ): array {
        return [$id, $doi, $pmid, $type, $date, $noticeDoi, $noticePmid, $url];
    }

    /** @param list<list<string>> $rows */
    #[DataProvider('noticeCases')]
    public function testLookup(array $rows, string $doi, string $pmid, ?string $type, string $expectedId = '1'): void {
        $notice = $this->publish($rows)->find($doi, $pmid);
        if ($type === null) {
            $this->assertNull($notice);
        } else {
            $this->assertNotNull($notice);
            $this->assertSame($type, $notice['type']);
            $this->assertSame($expectedId, $notice['id']);
        }
    }

    /** @return array<string, array{list<list<string>>, string, string, ?string, 4?: string}> */
    public static function noticeCases(): array {
        $retraction = self::row('1');
        $reinstatement = self::row('2', type: 'Reinstatement', date: '9/1/2020 0:00');
        $concern = self::row('3', type: 'Expression of concern', date: '10/1/2020 0:00');
        $correction = self::row('4', type: 'Correction', date: '11/1/2020 0:00');
        return [
            'DOI with resolver prefix and case differences' => [[$retraction], ' https://doi.org/10.1234/ORIGINAL ', '', 'Retraction'],
            'PMID only with leading zeros' => [[self::row('1', 'Unavailable')], '', '0012345', 'Retraction'],
            'both identifiers for the same original' => [[$retraction], '10.1234/original', '12345', 'Retraction'],
            'notice DOI and PMID are not originals' => [[$retraction], '10.1234/notice', '67890', null],
            'different work' => [[$retraction], '10.1234/other', '', null],
            'invalid identifiers' => [[$retraction], 'Unavailable', '0', null],
            'conflicting supplied identifiers' => [[$retraction], '10.1234/original', '54321', null],
            'two different matching works' => [[$retraction, self::row('2', '10.1234/other', '54321')], '10.1234/original', '54321', null],
            'two unlinked identifier matches' => [[self::row('1', pmid: ''), self::row('2', '', type: 'Reinstatement')], '10.1234/original', '12345', null],
            'conflict discovered through paired identifiers' => [[$retraction, self::row('2', '10.1234/other')], '10.1234/original', '', null],
            'standalone concern' => [[$concern], '', '12345', 'Expression of concern', '3'],
            'correction alone' => [[$correction], '10.1234/original', '', null],
            'later correction keeps retraction' => [[$correction, $retraction], '10.1234/original', '', 'Retraction'],
            'later concern keeps active retraction' => [[$concern, $retraction], '10.1234/original', '', 'Retraction'],
            'later reinstatement clears retraction' => [[$reinstatement, $retraction], '10.1234/original', '', null],
            'reinstatement via PMID-only event' => [[self::row('2', '', type: 'Reinstatement', date: '9/1/2020 0:00'), $retraction], '10.1234/original', '', null],
            'reinstatement via DOI-only event' => [[self::row('2', pmid: '', type: 'Reinstatement', date: '9/1/2020 0:00'), $retraction], '', '12345', null],
            'concern after reinstatement' => [[$reinstatement, $retraction, $concern], '10.1234/original', '', 'Expression of concern', '3'],
            'later retraction after reinstatement' => [[$reinstatement, $retraction, self::row('5', date: '12/1/2020 0:00')], '', '12345', 'Retraction', '5'],
            'same day contradictory notices' => [[$retraction, self::row('2', type: 'Reinstatement')], '10.1234/original', '', null],
            'invalid calendar date' => [[self::row('1', date: '2/30/2020 0:00')], '10.1234/original', '', null],
            'undated reinstatement cannot establish status' => [[$retraction, self::row('2', type: 'Reinstatement', date: '')], '10.1234/original', '', null],
        ];
    }

    public function testNoticeLinkDoesNotPointBackToThePaper(): void {
        $snapshot = $this->publish([self::row(
            '1',
            noticeDoi: '10.1234/ORIGINAL',
            noticePmid: '0012345',
            url: 'https://retractionwatch.com.example.org/fake;http://retractionwatch.com/valid/',
        )]);
        $notice = $snapshot->find('10.1234/original', '');
        $this->assertNotNull($notice);
        $this->assertSame('', $notice['noticeDoi']);
        $this->assertSame('', $notice['noticePmid']);
        $this->assertSame('https://retractionwatch.com/valid/', $notice['url']);
    }

    public function testCsvQuotesNewlinesAndOffsets(): void {
        $snapshot = $this->publish([self::row('1', url: "text with a comma, a \"quote\" and a\nnewline"), self::row('2', '10.1234/second', '54321')]);
        $this->assertSame('1', $snapshot->find('10.1234/original', '')['id'] ?? null);
        $this->assertSame('2', $snapshot->find('', '54321')['id'] ?? null);
    }

    public function testMissingCsvCannotBeReplacedByIndex(): void {
        $this->assertNull(RetractionWatch::open($this->directory));
        $this->publish([self::row('1')]);
        unlink($this->directory . '/current/' . RetractionWatch::CSV);
        $this->assertNull(RetractionWatch::open($this->directory));
    }

    public function testMissingIndexIsUnknown(): void {
        $this->publish([self::row('1')]);
        unlink($this->directory . '/current/index-' . RetractionWatch::shard('doi:10.1234/original') . '.json');
        $snapshot = RetractionWatch::open($this->directory);
        $this->assertNotNull($snapshot);
        $this->assertNull($snapshot->find('10.1234/original', ''));
    }

    public function testIndexCannotBeReadThroughASymlink(): void {
        $snapshot = $this->publish([self::row('1')]);
        $shard = $this->directory . '/current/index-' . RetractionWatch::shard('doi:10.1234/original') . '.json';
        $moved = $this->directory . '/other-index.json';
        $this->assertTrue(rename($shard, $moved));
        $this->assertTrue(symlink($moved, $shard));

        $this->assertNull($snapshot->find('10.1234/original', ''));
    }

    public function testIndexCannotAttributeAnotherCsvRowToTheCitedWork(): void {
        $this->publish([self::row('1')]);
        $key = 'doi:10.1234/different';
        $file = fopen($this->directory . '/current/' . RetractionWatch::CSV, 'rb');
        $this->assertIsResource($file);
        RetractionWatch::readHeader($file);
        $offset = ftell($file);
        fclose($file);
        file_put_contents(
            $this->directory . '/current/index-' . RetractionWatch::shard($key) . '.json',
            json_encode([$key => [$offset]], JSON_THROW_ON_ERROR),
        );
        $snapshot = RetractionWatch::open($this->directory);
        $this->assertNotNull($snapshot);
        $this->assertNull($snapshot->find('10.1234/different', ''));
    }

    public function testRefreshKeepsSnapshotPinnedForAnExistingReader(): void {
        $first = $this->publish([self::row('1')]);
        $second = $this->publish([self::row('2', type: 'Reinstatement')]);
        $this->assertNull($second->find('10.1234/original', ''));
        $this->assertSame('1', $first->find('10.1234/original', '')['id'] ?? null);
    }

    #[DataProvider('failedRefreshes')]
    public function testFailedRefreshKeepsLastGoodCsv(string $failure): void {
        $this->publish([self::row('1'), self::row('2', '10.1234/other', '54321')]);
        $before = readlink($this->directory . '/current');
        try {
            RetractionWatchRefresh::run($this->directory, static function ($file) use ($failure): void {
                if ($failure === 'transport') {
                    fwrite($file, "partial download\n");
                    throw new RuntimeException('Network error');
                }
                if ($failure === 'schema') {
                    fwrite($file, "This is not a Retraction Watch CSV\n");
                    return;
                }
                fputcsv($file, RetractionWatch::COLUMNS, ',', '"', '');
                fputcsv($file, self::row('1'), ',', '"', '');
                if ($failure === 'truncated row') {
                    fwrite($file, "2,10.1234/other\n");
                } elseif ($failure === 'no final newline') {
                    fwrite($file, '2,10.1234/other,54321,Retraction,8/1/2020 0:00,10.1234/notice,67890,');
                }
            }, 1);
            $this->fail('The invalid refresh must fail');
        } catch (RuntimeException) {
            $this->assertSame($before, readlink($this->directory . '/current'));
            $this->assertCount(1, glob($this->directory . '/snapshot-*') ?: []);
            $this->assertSame('1', RetractionWatch::open($this->directory)?->find('10.1234/original', '')['id'] ?? null);
        }
    }

    /** @return array<string, array{string}> */
    public static function failedRefreshes(): array {
        return [
            'transport failed after writing' => ['transport'],
            'wrong header' => ['schema'],
            'truncated data row' => ['truncated row'],
            'no final newline' => ['no final newline'],
            'sharp decrease from prior row count' => ['row count'],
        ];
    }

    public function testRefreshRespectsSharedWriterLock(): void {
        $this->publish([self::row('1')]);
        $file = fopen($this->directory . '/refresh.lock', 'c+b');
        $this->assertIsResource($file);
        $this->assertTrue(flock($file, LOCK_EX | LOCK_NB));
        try {
            $this->assertNull(RetractionWatchRefresh::run($this->directory, static function (): void {
                throw new LogicException('Concurrent refresh tried to download');
            }, 1));
        } finally {
            fclose($file);
        }
    }

    public function testPageReportsOneWarningAndPreservesCitationText(): void {
        if (!function_exists('uopz_set_return')) {
            $this->markTestSkipped('uopz is required to capture report_warning');
        }
        $this->publish([self::row('1', doi: '', noticeDoi: '')]);
        $previous = getenv('RETRACTION_WATCH_DIRECTORY');
        putenv('RETRACTION_WATCH_DIRECTORY=' . $this->directory);
        $warnings = [];
        /** @psalm-suppress UnusedFunctionCall */
        uopz_set_return('report_warning', static function (string $warning) use (&$warnings): void {
            if (str_starts_with($warning, 'Retraction Watch lists ')) {
                $warnings[] = $warning;
            }
        }, true);
        try {
            $page = new TestPage();
            $text = 'First {{pmid|12345}} and again {{pmid|1=12345}}.';
            $page->parse_text($text);
            $page->expand_text();
            $this->assertSame($text, $page->parsed_text());
            $this->assertSame(['Retraction Watch lists a retraction notice for PMID 12345 (2020-08-01) — notice PMID 67890'], $warnings);
        } finally {
            /** @psalm-suppress UnusedFunctionCall */
            uopz_unset_return('report_warning');
            putenv($previous === false ? 'RETRACTION_WATCH_DIRECTORY' : 'RETRACTION_WATCH_DIRECTORY=' . $previous);
        }
    }
}
