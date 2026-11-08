<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/RetractionWatch.php';

final class RetractionWatchRefresh {
    private const string URL = 'https://gitlab.com/crossref/retraction-watch-data/-/raw/main/retraction_watch.csv';
    private const int MAX_BYTES = 128 * 1024 * 1024;

    /**
     * @param string $directory
     * @param callable(resource): void $download
     * @param int $minimumRows
     * @return int|null Number of imported rows, or null when another refresh is running.
     */
    public static function run(string $directory, callable $download, int $minimumRows = 1000): ?int {
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create Retraction Watch directory');
        }
        $lock = @fopen($directory . '/refresh.lock', 'c+b');
        if ($lock === false) {
            throw new RuntimeException('Cannot open Retraction Watch refresh lock');
        }
        try {
            $wouldBlock = 0;
            if (!flock($lock, LOCK_EX | LOCK_NB, $wouldBlock)) {
                if ($wouldBlock !== 0) {
                    return null;
                }
                throw new RuntimeException('Cannot acquire Retraction Watch refresh lock');
            }
            $lastRun = @file_get_contents($directory . '/current/rows', false, null, 0, 20);
            if ($lastRun !== false && ctype_digit($lastRun)) {
                $minimumRows = max($minimumRows, (int) ceil((int) $lastRun * 0.9));
            }
            return self::publish($directory, $download, $minimumRows);
        } finally {
            fclose($lock);
        }
    }

    /**
     * @param string $directory
     * @param callable(resource): void $download
     * @param int $minimumRows
     */
    private static function publish(string $directory, callable $download, int $minimumRows): int {
        $generation = 'snapshot-' . bin2hex(random_bytes(8));
        $snapshot = $directory . '/' . $generation;
        if (!@mkdir($snapshot, 0700)) {
            throw new RuntimeException('Cannot stage Retraction Watch snapshot');
        }
        $csv = $snapshot . '/' . RetractionWatch::CSV;
        $current = $directory . '/current';
        $pending = $directory . '/.current-' . $generation;
        $published = false;
        try {
            $file = @fopen($csv, 'x+b');
            if ($file === false) {
                throw new RuntimeException('Cannot stage Retraction Watch CSV');
            }
            try {
                $download($file);
                if (!fflush($file) || !fsync($file)) {
                    throw new RuntimeException('Cannot flush Retraction Watch CSV');
                }
                if (fseek($file, -1, SEEK_END) !== 0 || fgetc($file) !== "\n" || !rewind($file)) {
                    throw new RuntimeException('Incomplete Retraction Watch CSV download');
                }
                $rows = self::buildIndex($file, $snapshot, $minimumRows);
            } finally {
                fclose($file);
            }
            $count = (string) $rows;
            if (file_put_contents($snapshot . '/rows', $count) !== mb_strlen($count, '8bit')) {
                throw new RuntimeException('Cannot write Retraction Watch row count');
            }
            $previous = @readlink($current);
            if (!@symlink($generation, $pending) || !@rename($pending, $current)) {
                throw new RuntimeException('Cannot publish Retraction Watch snapshot');
            }
            $published = true;
            self::prune($directory, $generation, $previous);
            return $rows;
        } finally {
            if (!$published) {
                @unlink($pending);
                self::removeSnapshot($snapshot);
            }
        }
    }

    /** @param resource $csv */
    private static function buildIndex($csv, string $snapshot, int $minimumRows): int {
        $columns = RetractionWatch::readHeader($csv);
        $shards = [];
        $rows = 0;
        while (($offset = ftell($csv)) !== false && ($row = fgetcsv($csv, 0, ',', '"', '')) !== false) {
            if (count($row) !== $columns['width'] ||
                preg_match('~\A[1-9][0-9]*\z~', (string) $row[$columns['Record ID']]) !== 1 ||
                !in_array($row[$columns['RetractionNature']], RetractionWatch::TYPES, true)) {
                throw new RuntimeException('Invalid Retraction Watch CSV row ' . (string) ($rows + 2));
            }
            $rows++;
            $doi = RetractionWatch::doi((string) $row[$columns['OriginalPaperDOI']]);
            $pmid = RetractionWatch::pmid((string) $row[$columns['OriginalPaperPubMedID']]);
            foreach (RetractionWatch::keys($doi, $pmid) as $key) {
                $shards[RetractionWatch::shard($key)][$key][] = $offset;
            }
        }
        if (!feof($csv) || $rows < $minimumRows || $shards === []) {
            throw new RuntimeException('Incomplete or empty Retraction Watch CSV');
        }
        // Every shard must exist, including empty ones, so the reader can
        // distinguish a missing index from a valid negative result.
        foreach (mb_str_split('0123456789abcdef') as $shard) {
            $json = json_encode($shards[$shard] ?? [], JSON_THROW_ON_ERROR);
            $index = $snapshot . '/index-' . $shard . '.json';
            if (file_put_contents($index, $json) !== mb_strlen($json, '8bit')) {
                throw new RuntimeException('Cannot write Retraction Watch index');
            }
        }
        return $rows;
    }

    private static function prune(string $directory, string $current, string|false $previous): void {
        // Keep the preceding snapshot through at least the next day so a
        // page that pinned it before publication can finish reading its index.
        if ($previous !== false && preg_match('~\Asnapshot-[a-f0-9]{16}\z~D', $previous) === 1) {
            @touch($directory . '/' . $previous);
        }
        foreach (glob($directory . '/snapshot-*', GLOB_ONLYDIR) ?: [] as $path) {
            $name = basename($path);
            $modified = filemtime($path);
            if ($name !== $current && $name !== $previous && !is_link($path) &&
                preg_match('~\Asnapshot-[a-f0-9]{16}\z~D', $name) === 1 &&
                $modified !== false && $modified < time() - 86400) {
                self::removeSnapshot($path);
            }
        }
    }

    private static function removeSnapshot(string $path): void {
        @unlink($path . '/' . RetractionWatch::CSV);
        @unlink($path . '/rows');
        foreach (mb_str_split('0123456789abcdef') as $shard) {
            @unlink($path . '/index-' . $shard . '.json');
        }
        @rmdir($path);
    }

    /** @param resource $file */
    public static function download($file): void {
        $curl = bot_curl_init(8.0, [
            CURLOPT_URL => self::URL,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FAILONERROR => true,
            CURLOPT_MAXFILESIZE => self::MAX_BYTES,
        ], self::MAX_BYTES);
        if (!curl_setopt($curl, CURLOPT_FILE, $file) ||
            bot_curl_exec_withFalse($curl) === false ||
            curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) {
            throw new RuntimeException('Retraction Watch CSV download failed');
        }
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require_once dirname(__DIR__) . '/src/includes/setup.php';
    $directory = RetractionWatch::directory();
    try {
        $rows = RetractionWatchRefresh::run($directory, RetractionWatchRefresh::download(...));
        echo $rows === null ? "Retraction Watch refresh already running\n" : "Published Retraction Watch snapshot with {$rows} rows\n";
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        $last = @filemtime($directory . '/current/' . RetractionWatch::CSV);
        if ($last === false || $last < time() - 5 * 86400) {
            fwrite(STDERR, "No Retraction Watch snapshot published in the last five days\n");
        }
        throw $error;
    }
}
