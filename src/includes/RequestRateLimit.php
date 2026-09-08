<?php

declare(strict_types=1);

const REQUEST_RATE_LIMIT_STATE_DIRECTORY = 'citation-bot-rate-limit';

const GADGET_API_RATE_LIMIT_CAPACITY = 40;
const GADGET_API_RATE_LIMIT_REFILL_PER_SECOND = 2.0;

const GENERATE_TEMPLATE_RATE_LIMIT_CAPACITY = 20;
const GENERATE_TEMPLATE_RATE_LIMIT_REFILL_PER_SECOND = 0.5;

function request_rate_limit_base_directory(): string {
    $env_val = getenv('PHP_RATE_LIMIT_DIRECTORY');
    if (is_string($env_val) && $env_val !== '') {
        return $env_val;
    }
    return sys_get_temp_dir();
}

/**
 * Consume one token from a process-shared token bucket.
 *
 * State is stored in a small locked file so PHP workers in the same container
 * share one limiter without requiring Redis or a database.
 *
 * Storage failures fail open to avoid taking the tool offline. Lock contention
 * fails closed briefly so a request storm cannot bypass the limiter by making
 * many PHP workers race for the same bucket.
 *
 * @return int|null Retry-After seconds when limited; null when the request may proceed.
 */
function request_rate_limit_consume(
    string $bucket,
    int $capacity,
    float $refill_per_second,
    ?string $base_directory = null,
    ?float $now = null
): ?int {
    if (preg_match('~\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}\z~D', $bucket) !== 1) {
        throw new InvalidArgumentException('Invalid rate-limit bucket name.');
    }
    if ($capacity < 1) {
        throw new InvalidArgumentException('Rate-limit capacity must be positive.');
    }
    if (!is_finite($refill_per_second) || $refill_per_second <= 0.0) {
        throw new InvalidArgumentException('Rate-limit refill rate must be positive and finite.');
    }
    if ($now !== null && (!is_finite($now) || $now < 0.0)) {
        throw new InvalidArgumentException('Rate-limit timestamp must be finite and non-negative.');
    }

    $base_directory = request_rate_limit_base_directory();
    if ($base_directory === '') {
        request_rate_limit_log_failure($bucket, 'temporary directory is empty');
        return null;
    }

    $state_directory =
        mb_rtrim($base_directory, "/\\", '8bit') .
        DIRECTORY_SEPARATOR .
        REQUEST_RATE_LIMIT_STATE_DIRECTORY;

    if (
        !is_dir($state_directory) &&
        !@mkdir($state_directory, 0700, true) &&
        !is_dir($state_directory)
    ) {
        request_rate_limit_log_failure($bucket, 'unable to create state directory');
        return null;
    }
    @chmod($state_directory, 0700);

    $state_path = $state_directory . DIRECTORY_SEPARATOR . $bucket . '.json';
    $handle = @fopen($state_path, 'c+');
    if ($handle === false) {
        request_rate_limit_log_failure($bucket, 'unable to open state file');
        return null;
    }
    @chmod($state_path, 0600);

    $locked = false;
    try {
        // Do not let an attack fill PHP workers with processes waiting on the lock.
        $locked = @flock($handle, LOCK_EX | LOCK_NB);
        if (!$locked) {
            return 1;
        }

        $now ??= microtime(true);
        $tokens = (float) $capacity;
        $updated = $now;

        if (@rewind($handle)) {
            $raw_state = stream_get_contents($handle);
            if (is_string($raw_state) && $raw_state !== '') {
                $state = json_decode($raw_state, true);
                if (
                    is_array($state) &&
                    isset($state['tokens'], $state['updated']) &&
                    is_numeric($state['tokens']) &&
                    is_numeric($state['updated'])
                ) {
                    $saved_tokens = (float) $state['tokens'];
                    $saved_updated = (float) $state['updated'];
                    if (
                        is_finite($saved_tokens) &&
                        is_finite($saved_updated) &&
                        $saved_tokens >= 0.0 &&
                        $saved_updated >= 0.0
                    ) {
                        $tokens = min((float) $capacity, $saved_tokens);
                        // If the wall clock moved backward, resume from "now".
                        $updated = min($now, $saved_updated);
                    }
                }
            }
        }

        $elapsed = max(0.0, $now - $updated);
        $tokens = min(
            (float) $capacity,
            $tokens + ($elapsed * $refill_per_second)
        );

        $retry_after = null;
        if ($tokens >= 1.0) {
            $tokens -= 1.0;
        } else {
            $retry_after = max(
                1,
                (int) ceil((1.0 - $tokens) / $refill_per_second)
            );
        }

        if (!request_rate_limit_store_state($handle, $tokens, $now)) {
            request_rate_limit_log_failure($bucket, 'unable to persist state');
        }

        return $retry_after;
    } finally {
        if ($locked) {
            @flock($handle, LOCK_UN);
        }
        fclose($handle);
    }
}

/**
 * @param resource $handle
 */
function request_rate_limit_store_state($handle, float $tokens, float $updated): bool {
    $encoded = json_encode(
        ['tokens' => $tokens, 'updated' => $updated],
        JSON_PRESERVE_ZERO_FRACTION
    );
    if (!is_string($encoded)) {
        return false;
    }
    if (!@rewind($handle) || !@ftruncate($handle, 0)) {
        return false;
    }

    $written = @fwrite($handle, $encoded);
    return $written === mb_strlen($encoded, '8bit') && @fflush($handle);
}

function request_rate_limit_log_failure(string $bucket, string $reason): void {
    static $reported = [];

    $key = $bucket . ':' . $reason;
    if (isset($reported[$key])) {
        return;
    }

    $reported[$key] = true;
    error_log('Citation Bot rate limiter (' . $bucket . '): ' . $reason . '; failing open.');
}

// Big-run gate: single requests (≤ BIG_RUN_PAGE_THRESHOLD pages) bypass the
// gate entirely. Big runs compete for a nested concurrency pool (total ≤
// BIG_RUN_MAX_TOTAL, of which large runs ≤ BIG_RUN_MAX_LARGE) and must draw
// from a shared token bucket. All state lives in one locked JSON file so the
// php-cgi worker processes share it. Constants are declared here rather than
// at the top of the file so the pre-existing request_rate_limit_consume keeps
// its line numbers (progpilot's false-positive list matches on those).

const BIG_RUN_STATE_FILE = 'big-run.json';
const BIG_RUN_PAGE_THRESHOLD = 4;
const BIG_RUN_LARGE_THRESHOLD = 50;
const BIG_RUN_MAX_TOTAL = 10;
const BIG_RUN_MAX_LARGE = 4;
const BIG_RUN_STALE_TIMEOUT_SECONDS = 300;
const BIG_RUN_NOMINAL_DURATION = 120;
const BIG_RUN_TOKEN_CAPACITY = 400;
const BIG_RUN_TOKEN_REFILL_PER_SECOND = 4.0;

/** Per-page token weight for each activation type. */
const BIG_TOKEN_WEIGHTS = [
    'category' => 1.5,
    'webform_linked' => 1.5,
    'webform' => 1.5,
    'automated_tools' => 0.5,
    'toolbar' => 1.0,
    'template' => 0.5,
    'other' => 1.0,
    'testing' => 0.0,
];

/** Per-page token multiplier for each run-size tier. */
const BIG_SIZE_WEIGHTS = [
    'small' => 1.0,
    'large' => 1.5,
];

function big_run_state_path(string $base_directory): string {
    $state_directory =
        mb_rtrim($base_directory, "/\\", '8bit') .
        DIRECTORY_SEPARATOR .
        REQUEST_RATE_LIMIT_STATE_DIRECTORY;

    return $state_directory . DIRECTORY_SEPARATOR . BIG_RUN_STATE_FILE;
}

/** Classify a run by page count into the small or large size tier. */
function big_run_tier(int $page_count): string {
    return $page_count >= BIG_RUN_LARGE_THRESHOLD ? 'large' : 'small';
}

/**
 * Billing type for token charging.
 *
 * ?edit= is requester-controlled, so page-list source labels must not buy a
 * quota discount: template/automated_tools/toolbar/other all bill at the
 * webform rate. Workload kinds derived server-side (category, webform_linked)
 * keep their own weight; testing keeps its exemption (unreachable from HTTP).
 */
function big_run_charge_type(string $run_type): string {
    if ($run_type === 'category' || $run_type === 'webform_linked') {
        return $run_type;
    }
    if ($run_type === 'testing') {
        return 'testing';
    }
    return 'webform';
}

/**
 * Token cost of a big run, capped at the bucket capacity.
 *
 * Cost = min(capacity, ceil(pages × type_weight × size_weight)). The type
 * weight is per activation type; the size weight scales large runs up.
 */
function big_run_token_cost(int $page_count, string $run_type): int {
    $type_weight = BIG_TOKEN_WEIGHTS[$run_type] ?? BIG_TOKEN_WEIGHTS['other'];
    $size_weight = BIG_SIZE_WEIGHTS[big_run_tier($page_count)];

    $cost = (int) ceil($page_count * $type_weight * $size_weight);
    return min(BIG_RUN_TOKEN_CAPACITY, $cost);
}

function big_run_new_entry_id(): string {
    try {
        return bin2hex(random_bytes(8));
    } catch (Throwable $exception) {
        // Extremely rare RNG failure; a time-based id keeps fail-open paths working.
        return uniqid('bigrun', true);
    }
}

/**
 * Try to admit a big run.
 *
 * @return array{0: bool, 1: int|null, 2: string|null, 3: string|null, 4: int|null}
 *               [0] acquired?, [1] Retry-After seconds, [2] entry id, [3] reason
 *               ('big_full'|'tokens'), [4] number of active big runs.
 */
function big_run_try_acquire(int $page_count, string $run_type, ?string $base_directory = null, ?float $now = null): array {
    $base_directory = $base_directory ?? request_rate_limit_base_directory();
    if ($base_directory === '') {
        request_rate_limit_log_failure('big-run', 'temporary directory is empty');
        return [true, null, big_run_new_entry_id(), null, 0];
    }

    $state_path = big_run_state_path($base_directory);
    $state_directory = dirname($state_path);

    if (
        !is_dir($state_directory) &&
        !@mkdir($state_directory, 0700, true) &&
        !is_dir($state_directory)
    ) {
        request_rate_limit_log_failure('big-run', 'unable to create state directory');
        return [true, null, big_run_new_entry_id(), null, 0];
    }
    @chmod($state_directory, 0700);

    $handle = @fopen($state_path, 'c+');
    if ($handle === false) {
        request_rate_limit_log_failure('big-run', 'unable to open state file');
        return [true, null, big_run_new_entry_id(), null, 0];
    }
    @chmod($state_path, 0600);

    $locked = false;
    try {
        // Do not let a request storm fill PHP workers with processes waiting on the lock.
        $locked = @flock($handle, LOCK_EX | LOCK_NB);
        if (!$locked) {
            error_log('Citation Bot big-run gate: state file lock busy; deferring run.');
            return [false, 1, null, 'retry_later', 0];
        }

        $now ??= microtime(true);
        [$tokens, $updated, $entries] = big_run_read_state($handle, $now);
        $entries = big_run_prune_stale_entries($entries, $now);

        $elapsed = max(0.0, $now - $updated);
        $tokens = min(
            (float) BIG_RUN_TOKEN_CAPACITY,
            $tokens + ($elapsed * BIG_RUN_TOKEN_REFILL_PER_SECOND)
        );

        $active_count = count($entries);
        $tier = big_run_tier($page_count);
        $cost = big_run_token_cost($page_count, big_run_charge_type($run_type));

        $slots_available =
            $active_count < BIG_RUN_MAX_TOTAL &&
            ($tier !== 'large' || big_run_count_tier($entries, 'large') < BIG_RUN_MAX_LARGE);
        $tokens_available = $tokens >= $cost;

        if ($slots_available && $tokens_available) {
            $entry_id = big_run_new_entry_id();
            $entries[$entry_id] = ['started_at' => $now, 'tier' => $tier, 'last_seen_at' => $now];
            if (!big_run_store_state($handle, $tokens - $cost, $now, $entries)) {
                request_rate_limit_log_failure('big-run', 'unable to persist state');
            }
            error_log('Citation Bot big-run gate: admitted ' . $run_type . ' run of ' .
                (string) $page_count . ' pages, cost ' . (string) $cost . ', balance ' .
                (string) ($tokens - $cost) . ', ' . (string) count($entries) . ' active.');
            return [true, null, $entry_id, null, count($entries)];
        }

        $retry_after = null;
        $reason = null;
        if (!$slots_available) {
            // Wait on the blocking pool: the oldest entry overall when the
            // total pool is full, else the oldest large entry when only the
            // large subpool is full (a small entry finishing frees no large slot).
            $blocking_tier = null;
            if ($active_count >= BIG_RUN_MAX_TOTAL) {
                $blocking_tier = null;
            } elseif ($tier === 'large') {
                $blocking_tier = 'large';
            }
            $oldest = big_run_oldest_started_at($entries, $blocking_tier);
            $slot_wait = 1;
            if ($oldest !== null) {
                $slot_wait = (int) max(1, ceil(($oldest + (float) BIG_RUN_NOMINAL_DURATION) - $now));
            }
            $retry_after = $slot_wait;
            $reason = 'big_full';
        }
        if (!$tokens_available) {
            $token_wait = (int) max(1, ceil(($cost - $tokens) / BIG_RUN_TOKEN_REFILL_PER_SECOND));
            if ($retry_after === null || $token_wait > $retry_after) {
                $retry_after = $token_wait;
                $reason = 'tokens';
            }
        }

        // Persist the pruned entries and the refilled balance so pruning sticks.
        if (!big_run_store_state($handle, $tokens, $now, $entries)) {
            request_rate_limit_log_failure('big-run', 'unable to persist state');
        }

        error_log('Citation Bot big-run gate: deferred ' . $run_type . ' run of ' .
            (string) $page_count . ' pages (cost ' . (string) $cost . ', balance ' .
            (string) $tokens . ', ' . (string) $active_count . ' active): ' . $reason);

        return [false, $retry_after, null, $reason, $active_count];
    } finally {
        if ($locked) {
            @flock($handle, LOCK_UN);
        }
        fclose($handle);
    }
}

/**
 * Remove an active-run entry. Best effort; no token refund (tokens are spent
 * at admission). Safe to call from a shutdown function.
 */
function big_run_release(string $entry_id, ?string $base_directory = null): void {
    $base_directory = $base_directory ?? request_rate_limit_base_directory();
    if ($base_directory === '') {
        return;
    }

    $state_path = big_run_state_path($base_directory);
    $handle = @fopen($state_path, 'c+');
    if ($handle === false) {
        return;
    }

    $locked = false;
    try {
        // Release must make a strong effort: a missed release occupies a
        // concurrency slot until stale pruning. Retry briefly on contention
        // (bounded so a shutdown path cannot stall a worker for long).
        $attempts = 0;
        while ($attempts < 50) {
            $locked = @flock($handle, LOCK_EX | LOCK_NB);
            if ($locked) {
                break;
            }
            ++$attempts;
            usleep(2000);
        }
        if (!$locked) {
            request_rate_limit_log_failure('big-run', 'unable to acquire state lock for release');
            return;
        }

        $now = microtime(true);
        [$tokens, $updated, $entries] = big_run_read_state($handle, $now);
        if (isset($entries[$entry_id])) {
            unset($entries[$entry_id]);
            if (!big_run_store_state($handle, $tokens, $updated, $entries)) {
                request_rate_limit_log_failure('big-run', 'unable to persist state');
            }
        }
    } finally {
        if ($locked) {
            @flock($handle, LOCK_UN);
        }
        fclose($handle);
    }
}

/**
 * Best-effort preflight check before expensive discovery work (category
 * enumeration, link-list fetching) that happens ahead of full admission.
 *
 * Reports whether the shared concurrency pool currently has room, without
 * spending tokens or reserving a slot. Callers that find no room should defer
 * immediately instead of occupying a worker with discovery for a run that the
 * full gate would reject. Side-effect free; fail-open on any storage or lock
 * problem.
 *
 * @return array{0: bool, 1: int} [room_available, active_big_run_count].
 */
function big_run_preflight_check(?string $base_directory = null, ?float $now = null): array {
    $base_directory = $base_directory ?? request_rate_limit_base_directory();
    if ($base_directory === '') {
        return [true, 0];
    }

    $state_path = big_run_state_path($base_directory);
    $state_directory = dirname($state_path);
    if (!is_dir($state_directory)) {
        return [true, 0];
    }

    $handle = @fopen($state_path, 'r');
    if ($handle === false) {
        return [true, 0];
    }

    try {
        if (!@flock($handle, LOCK_SH | LOCK_NB)) {
            return [true, 0];
        }
        try {
            $now ??= microtime(true);
            [, , $entries] = big_run_read_state($handle, $now);
            $entries = big_run_prune_stale_entries($entries, $now);
            $active_count = count($entries);
            return [$active_count < BIG_RUN_MAX_TOTAL, $active_count];
        } finally {
            @flock($handle, LOCK_UN);
        }
    } finally {
        fclose($handle);
    }
}

/**
 * Renew the liveness lease of an admitted run. Called from the per-page loop
 * so long runs are not pruned as stale while still processing. Best effort
 * and silent: a missed heartbeat only risks earlier pruning, never a fatal.
 */
function big_run_heartbeat(string $entry_id, ?string $base_directory = null, ?float $now = null): void {
    $base_directory = $base_directory ?? request_rate_limit_base_directory();
    if ($base_directory === '' || $entry_id === '') {
        return;
    }

    $state_path = big_run_state_path($base_directory);
    $handle = @fopen($state_path, 'c+');
    if ($handle === false) {
        return;
    }

    $locked = false;
    try {
        $locked = @flock($handle, LOCK_EX | LOCK_NB);
        if (!$locked) {
            return;
        }

        $now ??= microtime(true);
        [$tokens, $updated, $entries] = big_run_read_state($handle, $now);
        if (isset($entries[$entry_id])) {
            $entries[$entry_id]['last_seen_at'] = $now;
            if (!big_run_store_state($handle, $tokens, $updated, $entries)) {
                request_rate_limit_log_failure('big-run', 'unable to persist state');
            }
        }
    } finally {
        if ($locked) {
            @flock($handle, LOCK_UN);
        }
        fclose($handle);
    }
}

/**
 * @param resource $handle
 * @return array{0: float, 1: float, 2: array<string, array{started_at: float, tier: string, last_seen_at?: float}>}
 */
function big_run_read_state($handle, float $now): array {
    $tokens = (float) BIG_RUN_TOKEN_CAPACITY;
    $updated = $now;
    $entries = [];

    if (@rewind($handle)) {
        $raw_state = stream_get_contents($handle);
        if (is_string($raw_state) && $raw_state !== '') {
            $state = json_decode($raw_state, true);
            if (
                is_array($state) &&
                isset($state['tokens'], $state['updated']) &&
                is_numeric($state['tokens']) &&
                is_numeric($state['updated'])
            ) {
                $saved_tokens = (float) $state['tokens'];
                $saved_updated = (float) $state['updated'];
                if (
                    is_finite($saved_tokens) &&
                    is_finite($saved_updated) &&
                    $saved_tokens >= 0.0 &&
                    $saved_updated >= 0.0
                ) {
                    $tokens = min((float) BIG_RUN_TOKEN_CAPACITY, $saved_tokens);
                    // If the wall clock moved backward, resume from "now".
                    $updated = min($now, $saved_updated);
                }
            }
            if (is_array($state) && isset($state['entries']) && is_array($state['entries'])) {
                foreach ($state['entries'] as $entry_id => $entry) {
                    if (
                        is_string($entry_id) &&
                        is_array($entry) &&
                        isset($entry['started_at'], $entry['tier']) &&
                        is_numeric($entry['started_at']) &&
                        is_string($entry['tier'])
                    ) {
                        $entries[$entry_id] = [
                            'started_at' => (float) $entry['started_at'],
                            'tier' => $entry['tier'],
                            'last_seen_at' => isset($entry['last_seen_at']) && is_numeric($entry['last_seen_at'])
                                ? (float) $entry['last_seen_at']
                                : (float) $entry['started_at'],
                        ];
                    }
                }
            }
        }
    }

    return [$tokens, $updated, $entries];
}

/**
 * Drop entries whose run cannot still be alive (no heartbeat within timeout).
 *
 * Liveness is based on last_seen_at, renewed by big_run_heartbeat() from the
 * per-page loop — not on the original started_at, since bulk runs routinely
 * take longer than the stale timeout.
 *
 * @param array<string, array{started_at: float, tier: string, last_seen_at?: float}> $entries
 * @return array<string, array{started_at: float, tier: string, last_seen_at?: float}>
 */
function big_run_prune_stale_entries(array $entries, float $now): array {
    $cutoff = $now - (float) BIG_RUN_STALE_TIMEOUT_SECONDS;
    $pruned = [];
    foreach ($entries as $entry_id => $entry) {
        $last_seen = $entry['last_seen_at'] ?? $entry['started_at'];
        if ($last_seen >= $cutoff) {
            $pruned[$entry_id] = $entry;
        }
    }
    return $pruned;
}

/**
 * @param array<string, array{started_at: float, tier: string, last_seen_at?: float}> $entries
 */
function big_run_count_tier(array $entries, string $tier): int {
    $count = 0;
    foreach ($entries as $entry) {
        if ($entry['tier'] === $tier) {
            ++$count;
        }
    }
    return $count;
}

/**
 * @param array<string, array{started_at: float, tier: string, last_seen_at?: float}> $entries
 */
function big_run_oldest_started_at(array $entries, ?string $tier = null): ?float {
    $oldest = null;
    foreach ($entries as $entry) {
        if ($tier !== null && $entry['tier'] !== $tier) {
            continue;
        }
        if ($oldest === null || $entry['started_at'] < $oldest) {
            $oldest = $entry['started_at'];
        }
    }
    return $oldest;
}

/**
 * @param resource $handle
 * @param float $tokens
 * @param float $updated
 * @param array<string, array{started_at: float, tier: string, last_seen_at?: float}> $entries
 */
function big_run_store_state($handle, float $tokens, float $updated, array $entries): bool {
    $encoded = json_encode(
        ['tokens' => $tokens, 'updated' => $updated, 'entries' => $entries],
        JSON_PRESERVE_ZERO_FRACTION
    );
    if (!is_string($encoded)) {
        return false;
    }
    if (!@rewind($handle) || !@ftruncate($handle, 0)) {
        return false;
    }

    $written = @fwrite($handle, $encoded);
    return $written === mb_strlen($encoded, '8bit') && @fflush($handle);
}
