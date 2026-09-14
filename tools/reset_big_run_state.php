#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This tool is CLI-only.\n");
    exit(64);
}

require_once __DIR__ . '/../src/includes/RequestRateLimit.php';

function big_run_recovery_tool_usage(): void {
    $script = basename(__FILE__);
    echo "Usage:\n";
    echo "  php tools/" . $script . " --check\n";
    echo "  php tools/" . $script . " --reset\n";
}

/** @param array<int, string> $argv */
function big_run_recovery_tool_main(array $argv): int {
    $mode = $argv[1] ?? '--help';
    if (count($argv) !== 2 || !in_array($mode, ['--check', '--reset', '--help'], true)) {
        big_run_recovery_tool_usage();
        return 64;
    }
    if ($mode === '--help') {
        big_run_recovery_tool_usage();
        return 0;
    }

    if ($mode === '--check') {
        $result = big_run_recovery_check();
        echo 'Big-run state: ' . ($result['ok'] ? 'VALID' : 'INVALID') . PHP_EOL;
        echo 'State path: ' . $result['state_path'] . PHP_EOL;
        if ($result['ok']) {
            echo 'Persisted lease entries: ' . (string) ($result['lease_entries'] ?? 0) . PHP_EOL;
            echo 'Token balance: ' . (string) ($result['tokens'] ?? 0.0) . PHP_EOL;
            return 0;
        }

        echo 'Reason: ' . (string) ($result['reason'] ?? 'unknown') . PHP_EOL;
        echo 'Bulk admission remains fail-closed.' . PHP_EOL;
        echo 'After draining/quiescing bulk workers, recover with:' . PHP_EOL;
        echo '  php tools/' . basename(__FILE__) . ' --reset' . PHP_EOL;
        return 2;
    }

    fwrite(
        STDERR,
        "WARNING: reset forgets every shared big-run lease. Drain/quiesce bulk workers first.\n"
    );
    $result = big_run_recovery_reset();
    if (!$result['ok']) {
        fwrite(
            STDERR,
            'Big-run state reset FAILED (' . (string) ($result['reason'] ?? 'unknown') . ').' . PHP_EOL
        );
        fwrite(STDERR, 'State path: ' . $result['state_path'] . PHP_EOL);
        if ($result['backup_path'] !== null) {
            fwrite(STDERR, 'Preserved backup: ' . $result['backup_path'] . PHP_EOL);
        }
        return 3;
    }

    echo 'Big-run state reset: OK' . PHP_EOL;
    echo 'State path: ' . $result['state_path'] . PHP_EOL;
    if ($result['backup_path'] !== null) {
        echo 'Preserved previous snapshot: ' . $result['backup_path'] . PHP_EOL;
    } else {
        echo 'No previous snapshot existed.' . PHP_EOL;
    }
    echo 'Fresh state contains no leases and a full token bucket.' . PHP_EOL;
    return 0;
}

exit(big_run_recovery_tool_main($argv));
