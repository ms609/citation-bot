<?php

declare(strict_types=1);

/**
 * The index contains byte offsets only. Read and verify each match against
 * the CSV from the same immutable snapshot.
 */
final class RetractionWatch {
    public const string CSV = 'retraction_watch.csv';
    public const array COLUMNS = [
        'Record ID', 'OriginalPaperDOI', 'OriginalPaperPubMedID', 'RetractionNature',
        'RetractionDate', 'RetractionDOI', 'RetractionPubMedID', 'URLS',
    ];
    public const array TYPES = ['Retraction', 'Reinstatement', 'Expression of concern', 'Correction'];

    /** @var resource */
    private $csv;

    /** @var array<string, int> */
    private array $columns;

    /** @var array<string, array<array-key, mixed>> */
    private array $shards = [];

    private function __construct(private readonly string $snapshot) {
        $csv = @fopen($snapshot . '/' . self::CSV, 'rb');
        if ($csv === false) {
            throw new RuntimeException('Missing Retraction Watch CSV');
        }
        $this->csv = $csv;
        $this->columns = self::readHeader($csv);
    }

    public static function directory(): string {
        return getenv('RETRACTION_WATCH_DIRECTORY') ?: '/data/project/citations/retraction-watch';
    }

    public static function open(string $directory): ?self {
        $generation = @readlink($directory . '/current');
        if ($generation === false || preg_match('~\Asnapshot-[a-f0-9]{16}\z~D', $generation) !== 1) {
            return null;
        }
        try {
            return new self($directory . '/' . $generation);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * @param resource $csv
     * @return array<string, int>
     */
    public static function readHeader($csv): array {
        $header = fgetcsv($csv, 0, ',', '"', '');
        if ($header === false || !is_string($header[0])) {
            throw new RuntimeException('Missing Retraction Watch CSV header');
        }
        $header[0] = preg_replace('~^\xEF\xBB\xBF~', '', $header[0]);
        $columns = [];
        foreach (self::COLUMNS as $name) {
            $positions = array_keys($header, $name, true);
            if (count($positions) !== 1) {
                throw new RuntimeException('Missing or duplicate Retraction Watch column ' . $name);
            }
            $columns[$name] = $positions[0];
        }
        // Keep the upstream width for validating the import. Its current header has an empty last field.
        $columns['width'] = count($header);
        return $columns;
    }

    public static function doi(string $value): string {
        $value = mb_strtolower(mb_trim($value));
        $value = preg_replace('~\A(?:https?://(?:dx\.)?doi\.org/|doi:\s*)~', '', $value) ?? '';
        return doi_valid($value) && preg_match('~[\x00-\x1F\x7F]~', $value) !== 1 ? $value : '';
    }

    public static function pmid(string $value): string {
        $value = mb_trim($value);
        return pmid_valid($value) ? mb_ltrim($value, '0') : '';
    }

    public static function shard(string $key): string {
        return mb_substr(hash('sha256', $key), 0, 1);
    }

    /** @return list<string> */
    public static function keys(string $doi, string $pmid): array {
        $keys = [];
        if ($doi !== '') {
            $keys[] = 'doi:' . $doi;
        }
        if ($pmid !== '') {
            $keys[] = 'pmid:' . $pmid;
        }
        return $keys;
    }

    /** @return array<array-key, mixed> */
    private function offsets(string $key): array {
        $shard = self::shard($key);
        if (!isset($this->shards[$shard])) {
            $directory = realpath($this->snapshot);
            $path = realpath($this->snapshot . '/index-' . $shard . '.json');
            if ($directory === false || $path === false ||
                $path !== $directory . '/index-' . $shard . '.json' || !is_file($path)) {
                throw new RuntimeException('Unreadable Retraction Watch index');
            }
            $json = @file_get_contents($path, false, null, 0, 8 * 1024 * 1024);
            $data = $json === false ? null : json_decode($json, true);
            if (!is_array($data)) {
                throw new RuntimeException('Unreadable Retraction Watch index');
            }
            $this->shards[$shard] = $data;
        }
        $offsets = $this->shards[$shard][$key] ?? [];
        if (!is_array($offsets)) {
            throw new RuntimeException('Invalid Retraction Watch index entry');
        }
        return $offsets;
    }

    /** @return array<string, string> */
    private function row(int $offset): array {
        if ($offset <= 0 || fseek($this->csv, $offset) !== 0) {
            throw new RuntimeException('Invalid Retraction Watch CSV offset');
        }
        $fields = fgetcsv($this->csv, 0, ',', '"', '');
        if ($fields === false || count($fields) !== $this->columns['width']) {
            throw new RuntimeException('Invalid Retraction Watch CSV row');
        }
        $row = [];
        foreach (self::COLUMNS as $name) {
            $row[$name] = (string) $fields[$this->columns[$name]];
        }
        return $row;
    }

    /**
     * Follow an exact DOI/PMID pair once so a later event indexed under only
     * the other identifier still counts. Conflicting identities are unknown.
     *
     * @return array{doi: string, pmid: string, id: string, type: string, date: string, noticeDoi: string, noticePmid: string, url: string}|null
     */
    public function find(string $doi, string $pmid): ?array {
        $doi = self::doi($doi);
        $pmid = self::pmid($pmid);
        $rows = [];
        $keys = self::keys($doi, $pmid);
        $matched = [];
        $hasPair = false;
        try {
            for ($i = 0; $i < count($keys); $i++) {
                $key = $keys[$i];
                foreach ($this->offsets($key) as $offset) {
                    if (!is_int($offset)) {
                        return null;
                    }
                    $row = $rows[$offset] ?? $this->row($offset);
                    $rowDoi = self::doi($row['OriginalPaperDOI']);
                    $rowPmid = self::pmid($row['OriginalPaperPubMedID']);
                    if (!in_array($key, self::keys($rowDoi, $rowPmid), true) ||
                        ($doi !== '' && $rowDoi !== '' && $doi !== $rowDoi) ||
                        ($pmid !== '' && $rowPmid !== '' && $pmid !== $rowPmid)) {
                        return null;
                    }
                    if ($doi === '' && $rowDoi !== '') {
                        $doi = $rowDoi;
                        $keys[] = 'doi:' . $doi;
                    }
                    if ($pmid === '' && $rowPmid !== '') {
                        $pmid = $rowPmid;
                        $keys[] = 'pmid:' . $pmid;
                    }
                    $hasPair = $hasPair || ($rowDoi !== '' && $rowPmid !== '');
                    $matched[$key] = true;
                    $rows[$offset] = $row;
                }
            }
        } catch (RuntimeException) {
            return null;
        }

        if (count($matched) === 2 && !$hasPair) {
            return null; // The CSV has separate events but provides no link between the two identifiers.
        }
        return self::latestNotice($rows, $doi, $pmid);
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @return array{doi: string, pmid: string, id: string, type: string, date: string, noticeDoi: string, noticePmid: string, url: string}|null
     */
    private static function latestNotice(array $rows, string $doi, string $pmid): ?array {
        $status = [];
        $concern = null;
        foreach ($rows as $row) {
            $type = $row['RetractionNature'];
            if ($type === 'Correction') {
                continue;
            }
            $date = DateTimeImmutable::createFromFormat('!n/j/Y G:i', mb_trim($row['RetractionDate']), new DateTimeZone('UTC'));
            $date_errors = DateTimeImmutable::getLastErrors();
            if (!in_array($type, self::TYPES, true) || $date === false ||
                ($date_errors['warning_count'] ?? 0) !== 0 || ($date_errors['error_count'] ?? 0) !== 0) {
                return null;
            }
            $day = $date->format('Y-m-d');
            if ($type === 'Expression of concern') {
                if ($concern === null || $day > $concern['date']) {
                    $concern = ['date' => $day, 'row' => $row];
                }
            } elseif (!isset($status[$type]) || $day > $status[$type]['date']) {
                $status[$type] = ['date' => $day, 'row' => $row];
            }
        }
        $retraction = $status['Retraction'] ?? null;
        $reinstatement = $status['Reinstatement']['date'] ?? '';
        if ($retraction !== null && $retraction['date'] === $reinstatement) {
            return null;
        }
        $notice = $retraction !== null && $retraction['date'] > $reinstatement ? $retraction : $concern;
        if ($notice === null || $notice['date'] <= $reinstatement) {
            return null;
        }
        $row = $notice['row'];
        $noticeDoi = self::doi($row['RetractionDOI']);
        $noticePmid = self::pmid($row['RetractionPubMedID']);
        $url = '';
        foreach (explode(';', $row['URLS']) as $candidate) {
            $candidate = mb_trim($candidate);
            if (preg_match('~\Ahttps?://(?:www\.)?retractionwatch\.com/[^\x00-\x20\x7F]+\z~i', $candidate) === 1) {
                $url = 'https://' . explode('://', $candidate, 2)[1];
                break;
            }
        }

        return [
            'doi' => $doi, 'pmid' => $pmid, 'id' => $row['Record ID'], 'type' => $row['RetractionNature'],
            'date' => $notice['date'], 'noticeDoi' => $noticeDoi === $doi ? '' : $noticeDoi,
            'noticePmid' => $noticePmid === $pmid ? '' : $noticePmid, 'url' => $url,
        ];
    }
}

/** @param array<Template> $templates */
function report_retraction_watch_notices(array $templates): void {
    $snapshot = RetractionWatch::open(RetractionWatch::directory());
    if ($snapshot === null) {
        return;
    }
    $reported = [];
    foreach ($templates as $template) {
        $name = $template->wikiname();
        if (in_array($name, ['doi', 'doi-inline', 'pmid'], true)) {
            $first = $template->has_but_maybe_blank('1') ? '1' : '';
            $identifier = $template->get_without_comments_and_placeholders($first);
            $doi = $name === 'pmid' ? '' : $identifier;
            $pmid = $name === 'pmid' ? $identifier : '';
        } elseif (in_array($name, [...TEMPLATES_WE_PROCESS, ...TEMPLATES_WE_SLIGHTLY_PROCESS, ...TEMPLATES_WE_BARELY_PROCESS,
            'cite magazine', 'cite periodical'], true)) {
            $doi = $template->get_without_comments_and_placeholders('doi');
            $pmid = $template->get_without_comments_and_placeholders('pmid');
        } else {
            continue;
        }
        $notice = $snapshot->find($doi, $pmid);
        if ($notice === null || isset($reported[$notice['id']])) {
            continue;
        }
        $reported[$notice['id']] = true;
        $subject = $notice['doi'] !== '' ? 'DOI ' . doi_link($notice['doi']) : pubmed_link('pmid', $notice['pmid']);
        $message = 'Retraction Watch lists ' . ($notice['type'] === 'Retraction' ? 'a retraction notice' : 'an expression of concern') .
            ' for ' . $subject . ' (' . echoable($notice['date']) . ')';
        if ($notice['noticeDoi'] !== '') {
            $message .= ' — notice DOI ' . doi_link($notice['noticeDoi']);
        } elseif ($notice['noticePmid'] !== '') {
            $message .= ' — notice ' . pubmed_link('pmid', $notice['noticePmid']);
        } elseif ($notice['url'] !== '') {
            $url = echoable($notice['url']);
            $message .= HTML_OUTPUT ? ' — <a href="' . $url . '">Retraction Watch</a>' : ' — ' . $url;
        }
        report_warning($message);
    }
}
