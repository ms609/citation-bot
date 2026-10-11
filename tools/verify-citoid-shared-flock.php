<?php

declare(strict_types=1);

/**
 * Offline, cross-host smoke check of PHP flock on an existing Citoid mutex.
 * Never creates, truncates or unlinks rate.lock or rate.state.
 * Stop ALL Citoid writers on ALL hosts before running the probes.
 */
final class CitoidSharedFlockSmoke {
    private const EXIT_INVALID = 2;

    /** @param list<string> $args */
    public static function run(array $args): int {
        $mode = $args[2] ?? '';
        if (($args[1] ?? '') !== '--offline' ||
            !in_array($mode, ['--hold', '--expect-busy', '--expect-free'], true) ||
            count($args) !== ($mode === '--hold' ? 5 : 4)) {
            self::error('Usage: verify-citoid-shared-flock.php --offline --hold DIRECTORY SECONDS');
            self::error('   or: verify-citoid-shared-flock.php --offline --expect-busy|--expect-free DIRECTORY');
            return self::EXIT_INVALID;
        }
        if (!function_exists('posix_geteuid') || PHP_OS_FAMILY !== 'Linux') {
            self::error('Linux POSIX filesystem is required');
            return self::EXIT_INVALID;
        }
        $seconds = 0;
        if ($mode === '--hold') {
            $seconds = filter_var($args[4], FILTER_VALIDATE_INT);
            if ($seconds === false || $seconds < 2 || $seconds > 30) {
                self::error('Hold duration must be an integer from 2 to 30 seconds');
                return self::EXIT_INVALID;
            }
        }
        $argument = $args[3];
        $directory = realpath($argument);
        if ($directory === false || $directory !== $argument || $argument[0] !== '/') {
            self::error('Refusing a noncanonical or nonabsolute directory');
            return self::EXIT_INVALID;
        }
        $owner = posix_geteuid();
        $directory_stat = self::privateEntry($directory, 0040000, $owner);
        if ($directory_stat === false || ($directory_stat['mode'] & 0077) !== 0) {
            self::error('Directory must be owned by the current UID and mode 0700');
            return self::EXIT_INVALID;
        }
        $path = $directory . '/rate.lock';
        $named = self::privateEntry($path, 0100000, $owner);
        if ($named === false || ($named['mode'] & 0022) !== 0 || $named['nlink'] !== 1) {
            self::error('Missing, writable-by-others or unsafe permanent lock');
            return self::EXIT_INVALID;
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            self::error('Unable to open the permanent lock');
            return self::EXIT_INVALID;
        }
        try {
            if (!self::matches($handle, $path, $named, $owner) ||
                !self::directoryMatches($directory, $directory_stat, $owner)) {
                self::error('Lock or directory identity changed');
                return self::EXIT_INVALID;
            }
            $acquired = @flock($handle, LOCK_EX | LOCK_NB);
            if ($mode === '--expect-busy') {
                if ($acquired) {
                    self::error('FAIL: lock was unexpectedly available on this host');
                    return 1;
                }
                echo "PASS: remote holder excluded this host\n";
                return 0;
            }
            if (!$acquired) {
                self::error('FAIL: lock could not be acquired');
                return 1;
            }
            if (!self::matches($handle, $path, $named, $owner)) {
                self::error('Lock was replaced during acquisition');
                return self::EXIT_INVALID;
            }
            if ($mode === '--expect-free') {
                echo "PASS: lock is available after the holder exits\n";
                return 0;
            }
            echo "LOCK_HELD\n";
            flush();
            sleep($seconds);
            if (!self::matches($handle, $path, $named, $owner) ||
                !self::directoryMatches($directory, $directory_stat, $owner)) {
                self::error('Lock or directory changed during hold');
                return self::EXIT_INVALID;
            }
            echo "LOCK_RELEASED\n";
            return 0;
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Read mutable filesystem metadata; never assume a second check will agree.
     * @return array<string|int, int>|false
     * @phpstan-impure
     */
    private static function privateEntry(string $path, int $type, int $owner): array|false {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || ($stat['mode'] & 0170000) !== $type || $stat['uid'] !== $owner) {
            return false;
        }
        return $stat;
    }

    /** @param resource $handle
     * @param string $path
     * @param array<string|int, int> $original
     * @phpstan-impure
     */
    private static function matches($handle, string $path, array $original, int $owner): bool {
        $current = self::privateEntry($path, 0100000, $owner);
        $opened = @fstat($handle);
        return $current !== false && $opened !== false &&
            ($opened['mode'] & 0170000) === 0100000 && $opened['nlink'] === 1 &&
            $opened['uid'] === $owner && $opened['dev'] === $current['dev'] &&
            $opened['ino'] === $current['ino'] && $current['dev'] === $original['dev'] &&
            $current['ino'] === $original['ino'] && ($current['mode'] & 0022) === 0;
    }

    /** @param string $path
     * @param array<string|int, int> $original
     * @phpstan-impure
     */
    private static function directoryMatches(string $path, array $original, int $owner): bool {
        $current = self::privateEntry($path, 0040000, $owner);
        return $current !== false && ($current['mode'] & 0077) === 0 &&
            $current['dev'] === $original['dev'] && $current['ino'] === $original['ino'];
    }

    private static function error(string $message): void {
        fwrite(STDERR, $message . "\n");
    }
}

exit(CitoidSharedFlockSmoke::run($argv));
