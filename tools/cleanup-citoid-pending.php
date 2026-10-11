<?php

declare(strict_types=1);

/**
 * Return an explicitly validated CLI directory, never an unchecked path.
 * Whitelist literal pathname characters, reject traversal and glob syntax,
 * and require a canonical absolute path with no symlink aliases.
 *
 * @return string|false
 */
function citoid_cleanup_canonical_directory(string $path): string|false {
    if ($path === '' || $path[0] !== '/' ||
        preg_match('~\A/[a-zA-Z0-9._/-]+\z~D', $path) !== 1 ||
        preg_match('~(?:\A|/)\.{1,2}(?:/|\z)~D', $path) === 1 ||
        realpath($path) !== $path || !function_exists('posix_geteuid')) {
        return false;
    }
    return $path;
}

/**
 * Offline-only cleanup of stale Citoid pending writes.
 *
 * Stop/drain ALL workers on ALL hosts first. Holding flock here is not a
 * substitute for draining workers: new writers may start after this exits.
 *
 * Usage: php tools/cleanup-citoid-pending.php --offline --dry-run /absolute/dir
 *        php tools/cleanup-citoid-pending.php --offline --delete /absolute/dir
 */
final class CitoidPendingCleanup {
    private const MIN_AGE_SECONDS = 300;

    /** @param list<string> $args */
    public static function run(array $args): int {
        if (count($args) !== 4 || $args[1] !== '--offline' ||
            !in_array($args[2], ['--dry-run', '--delete'], true)) {
            self::error('Usage: php cleanup-citoid-pending.php --offline --dry-run|--delete /absolute/dir');
            return 2;
        }
        // Convert untrusted CLI input into a validated canonical path before
        // constructing the lock filename or enumerating pending files.
        $directory = citoid_cleanup_canonical_directory($args[3]);
        if ($directory === false) {
            self::error('Refusing a noncanonical, nonabsolute, or unsupported directory');
            return 2;
        }
        $owner = posix_geteuid();
        $directory_stat = self::privateFile($directory, 0040000, $owner);
        if ($directory_stat === false) {
            self::error('Directory must be owned by the current UID and mode 0700');
            return 2;
        }
        $lock_path = $directory . '/rate.lock';
        // Never open an untrusted lock type (including a FIFO that could
        // block indefinitely). A missing lock may indicate a wrong mount.
        if (self::privateFile($lock_path, 0100000, $owner, true) === false) {
            self::error('Missing or unsafe permanent rate.lock');
            return 2;
        }
        $lock = @fopen($lock_path, 'rb');
        if ($lock === false) {
            self::error('Missing or unreadable permanent rate.lock');
            return 2;
        }
        try {
            if (!@flock($lock, LOCK_EX | LOCK_NB)) {
                self::error('Lock is held: workers have not been drained');
                return 2;
            }
            if (!self::identityMatches($lock, $lock_path, $owner) ||
                !self::directoryMatches($directory, $directory_stat, $owner)) {
                self::error('Lock or directory identity changed');
                return 2;
            }
            $candidates = @glob($directory . '/rate.pending.*');
            if ($candidates === false) {
                self::error('Cannot enumerate pending files');
                return 2;
            }
            $cutoff = time() - self::MIN_AGE_SECONDS;
            $stale = [];
            foreach ($candidates as $path) {
                // Only production's 12-random-byte hexadecimal filenames.
                if (preg_match('/^rate\.pending\.[0-9a-f]{24}$/D', basename($path)) !== 1) {
                    continue;
                }
                $stat = self::privateFile($path, 0100000, $owner);
                if ($stat === false) {
                    self::error('Unsafe pending entry, refusing to clean: ' . basename($path));
                    return 2;
                }
                if ($stat['mtime'] <= $cutoff) {
                    $stale[$path] = $stat;
                }
            }
            foreach ($stale as $path => $before) {
                $now = self::privateFile($path, 0100000, $owner);
                if ($now === false || $now['dev'] !== $before['dev'] ||
                    $now['ino'] !== $before['ino'] || $now['mtime'] > $cutoff ||
                    !self::directoryMatches($directory, $directory_stat, $owner) ||
                    !self::identityMatches($lock, $lock_path, $owner)) {
                    self::error('A file, directory or lock changed during cleanup');
                    return 2;
                }
                if ($args[2] === '--delete' && !@unlink($path)) {
                    self::error('Failed to delete: ' . basename($path));
                    return 2;
                }
                echo ($args[2] === '--delete' ? 'Deleted ' : 'Would delete ') . basename($path) . "\n";
            }
            echo 'Eligible files: ' . count($stale) . "\n";
            return 0;
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * The permanent lock may be 0644 (normal 0022 umask), but must not be
     * writable by group/others. Pending files and directories remain private.
     *
     * @return array<string|int, int>|false
     * @phpstan-impure
     */
    private static function privateFile(
        string $path,
        int $type,
        int $owner,
        bool $allow_public_read = false
    ): array|false {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || ($stat['mode'] & 0170000) !== $type ||
            ($allow_public_read ? ($stat['mode'] & 0022) !== 0 : ($stat['mode'] & 0077) !== 0) ||
            $stat['uid'] !== $owner ||
            ($type === 0100000 && $stat['nlink'] !== 1)) {
            return false;
        }
        return $stat;
    }

    /**
     * @param string $path
     * @param array<string|int, int> $original
     * @param int $owner
     * @phpstan-impure
     */
    private static function directoryMatches(string $path, array $original, int $owner): bool {
        $current = self::privateFile($path, 0040000, $owner);
        return $current !== false && $current['dev'] === $original['dev'] &&
            $current['ino'] === $original['ino'];
    }

    /**
     * @param resource $handle
     * @phpstan-impure
     */
    private static function identityMatches($handle, string $path, int $owner): bool {
        $named = self::privateFile($path, 0100000, $owner, true);
        $opened = @fstat($handle);
        return $named !== false && $opened !== false &&
            ($opened['mode'] & 0170000) === 0100000 && $opened['nlink'] === 1 &&
            $opened['uid'] === $owner && $opened['dev'] === $named['dev'] &&
            $opened['ino'] === $named['ino'];
    }

    private static function error(string $message): void {
        fwrite(STDERR, $message . "\n");
    }
}

exit(CitoidPendingCleanup::run($argv));
