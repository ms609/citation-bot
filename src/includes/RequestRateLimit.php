<?php

declare(strict_types=1);

const REQUEST_RATE_LIMIT_STATE_DIRECTORY = 'citation-bot-rate-limit';

const GADGET_API_RATE_LIMIT_CAPACITY = 40;
const GADGET_API_RATE_LIMIT_REFILL_PER_SECOND = 2.0;
const GADGET_API_CLIENT_RATE_LIMIT_CAPACITY = 20;
const GADGET_API_CLIENT_RATE_LIMIT_REFILL_PER_SECOND = 1.0;

const GENERATE_TEMPLATE_RATE_LIMIT_CAPACITY = 20;
const GENERATE_TEMPLATE_RATE_LIMIT_REFILL_PER_SECOND = 0.5;
const GENERATE_TEMPLATE_CLIENT_RATE_LIMIT_CAPACITY = 10;
const GENERATE_TEMPLATE_CLIENT_RATE_LIMIT_REFILL_PER_SECOND = 0.25;

function request_rate_limit_base_directory(): string {
    $env_val = getenv('PHP_RATE_LIMIT_DIRECTORY');
    if (is_string($env_val) && $env_val !== '') {
        return $env_val;
    }
    return sys_get_temp_dir();
}

/**
 * Build an opaque bucket name for one direct, globally routed client.
 *
 * REMOTE_ADDR is supplied by the web server and is therefore preferable to
 * user-controlled forwarding headers. Canonicalizing the address makes
 * equivalent IPv6 spellings share a bucket. Private, loopback, invalid, or
 * unavailable peer addresses skip the client layer; those values commonly
 * identify a reverse proxy, and treating one proxy as one client would
 * accidentally throttle unrelated users. The global limiter still applies.
 */
function request_rate_limit_client_bucket(
    string $bucket,
    ?string $remote_address
): ?string {
    if (preg_match('~\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}\z~D', $bucket) !== 1) {
        throw new InvalidArgumentException('Invalid rate-limit bucket name.');
    }

    if (!is_string($remote_address) || $remote_address === '') {
        return null;
    }
    $packed_address = @inet_pton($remote_address);
    if ($packed_address === false) {
        return null;
    }
    $canonical_address = inet_ntop($packed_address);
    if (
        !is_string($canonical_address) ||
        filter_var(
            $canonical_address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_GLOBAL_RANGE
        ) === false
    ) {
        return null;
    }

    /*
     * Keep the raw address out of filenames/log-visible bucket names. The
     * digest is an opaque rate-limit identifier, not an anonymization boundary.
     */
    return 'client-' . substr(
        hash('sha256', $bucket . "\0" . $canonical_address),
        0,
        32
    );
}

/**
 * Apply a per-client limiter before the existing process-wide limiter.
 *
 * Checking the client bucket first prevents a single client that has already
 * exhausted its own allowance from continuing to drain the shared bucket.
 * A request must pass both limits.
 *
 * @return int|null Retry-After seconds when limited; null when allowed.
 */
function request_rate_limit_consume_layered(
    string $bucket,
    int $global_capacity,
    float $global_refill_per_second,
    int $client_capacity,
    float $client_refill_per_second,
    ?string $remote_address,
    ?string $base_directory = null,
    ?float $now = null
): ?int {
    $client_bucket = request_rate_limit_client_bucket($bucket, $remote_address);
    if ($client_bucket !== null) {
        $client_retry_after = request_rate_limit_consume(
            $client_bucket,
            $client_capacity,
            $client_refill_per_second,
            $base_directory,
            $now
        );
        if ($client_retry_after !== null) {
            return $client_retry_after;
        }
    }

    return request_rate_limit_consume(
        $bucket,
        $global_capacity,
        $global_refill_per_second,
        $base_directory,
        $now
    );
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

    $base_directory ??= request_rate_limit_base_directory();
    if ($base_directory === '') {
        request_rate_limit_log_failure($bucket, 'temporary directory is empty');
        return null;
    }

    $state_directory = big_run_prepare_state_directory($base_directory);
    if ($state_directory === null) {
        request_rate_limit_log_failure($bucket, 'unsafe or unavailable state directory');
        return null;
    }

    $handle = request_rate_limit_open_state_handle($state_directory, $bucket);
    if ($handle === false) {
        request_rate_limit_log_failure($bucket, 'unsafe or unavailable state file');
        return null;
    }

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

/**
 * Emit a rate-limit/gate diagnostic without requiring the full application
 * bootstrap. Normal web/CLI application paths use bot_debug_log(); deliberately
 * standalone consumers fall back to PHP's error_log().
 */
function request_rate_limit_debug_log(string $message): void {
    if (function_exists('bot_debug_log')) {
        bot_debug_log($message);
        return;
    }

    error_log($message);
}

function request_rate_limit_log_failure(string $bucket, string $reason): void {
    static $reported = [];

    $key = $bucket . ':' . $reason;
    if (isset($reported[$key])) {
        return;
    }

    $reported[$key] = true;
    request_rate_limit_debug_log(
        'Citation Bot rate limiter (' . $bucket . '): ' . $reason . '; failing open.'
    );
}

// Big-run gate: single-page processing (<= BIG_RUN_PAGE_THRESHOLD pages)
// bypasses bulk admission. Category/linked-page discovery acquires a small
// probe lease before its first remote API call. Probe leases use a separate
// bounded pool; once discovery proves the request is bulk, the probe is
// atomically promoted into the normal total pool before more bulk work.

const BIG_RUN_STATE_FILE = 'big-run.json';
const BIG_RUN_LOCK_FILE = 'big-run.lock';
const BIG_RUN_STATE_MAX_BYTES = 65536;
const BIG_RUN_PAGE_THRESHOLD = 4;
const BIG_RUN_DISCOVERY_RAW_PROBE_LIMIT = 25;
const BIG_RUN_DISCOVERY_BATCH_PROBE_LIMIT = 5;
const BIG_RUN_MAX_DISCOVERY_PROBES = 4;
const BIG_RUN_LARGE_THRESHOLD = 50;
const BIG_RUN_MAX_TOTAL = 10;
const BIG_RUN_MAX_LARGE = 4;
const BIG_RUN_STALE_TIMEOUT_SECONDS = 900;
const BIG_RUN_HEARTBEAT_INTERVAL_SECONDS = 30;
const BIG_RUN_POOL_RETRY_SECONDS = 30;
const BIG_RUN_TOKEN_CAPACITY = 400;
const BIG_RUN_TOKEN_REFILL_PER_SECOND = 4.0;

/** Trusted billing classes only. User-controlled attribution is normalized. */
const BIG_TOKEN_WEIGHTS = [
    'category' => 1.5,
    'webform_linked' => 1.5,
    'webform' => 1.5,
    'testing' => 0.0,
];

/** Per-page token multiplier for each run-size tier. */
const BIG_SIZE_WEIGHTS = [
    'small' => 1.0,
    'large' => 1.5,
];

function big_run_config_int(string $name, int $default, int $minimum, int $maximum): int {
    $raw = getenv($name);
    if (!is_string($raw) || $raw === '' || preg_match('~\A[0-9]+\z~D', $raw) !== 1) {
        return $default;
    }
    $value = (int) $raw;
    return ($value >= $minimum && $value <= $maximum) ? $value : $default;
}

function big_run_config_float(string $name, float $default, float $minimum, float $maximum): float {
    $raw = getenv($name);
    if (!is_string($raw) || $raw === '' || !is_numeric($raw)) {
        return $default;
    }
    $value = (float) $raw;
    if (!is_finite($value) || $value < $minimum || $value > $maximum) {
        return $default;
    }
    return $value;
}

function big_run_max_total(): int {
    return big_run_config_int('CITATION_BOT_BIG_RUN_MAX_TOTAL', BIG_RUN_MAX_TOTAL, 1, 100);
}

function big_run_max_discovery_probes(): int {
    return big_run_config_int(
        'CITATION_BOT_BIG_RUN_MAX_DISCOVERY_PROBES',
        BIG_RUN_MAX_DISCOVERY_PROBES,
        1,
        100
    );
}

function big_run_max_large(): int {
    return min(
        big_run_max_total(),
        big_run_config_int('CITATION_BOT_BIG_RUN_MAX_LARGE', BIG_RUN_MAX_LARGE, 1, 100)
    );
}

function big_run_stale_timeout_seconds(): int {
    return big_run_config_int(
        'CITATION_BOT_BIG_RUN_STALE_TIMEOUT_SECONDS',
        BIG_RUN_STALE_TIMEOUT_SECONDS,
        60,
        86400
    );
}

function big_run_heartbeat_interval_seconds(): int {
    $stale_timeout = big_run_stale_timeout_seconds();
    $configured = big_run_config_int(
        'CITATION_BOT_BIG_RUN_HEARTBEAT_INTERVAL_SECONDS',
        BIG_RUN_HEARTBEAT_INTERVAL_SECONDS,
        5,
        600
    );

    // Leave enough room for more than one retry before a healthy lease can be
    // mistaken for a crashed request. The stale timeout is at least 60s.
    return min($configured, max(5, intdiv($stale_timeout, 3)));
}

function big_run_pool_retry_seconds(): int {
    return big_run_config_int(
        'CITATION_BOT_BIG_RUN_POOL_RETRY_SECONDS',
        BIG_RUN_POOL_RETRY_SECONDS,
        5,
        600
    );
}

function big_run_token_capacity(): int {
    return big_run_config_int(
        'CITATION_BOT_BIG_RUN_TOKEN_CAPACITY',
        BIG_RUN_TOKEN_CAPACITY,
        1,
        1000000
    );
}

function big_run_token_refill_per_second(): float {
    return big_run_config_float(
        'CITATION_BOT_BIG_RUN_TOKEN_REFILL_PER_SECOND',
        BIG_RUN_TOKEN_REFILL_PER_SECOND,
        0.001,
        1000000.0
    );
}

function big_run_resolve_now(?float $now): float {
    $now ??= microtime(true);
    if (!is_finite($now) || $now < 0.0) {
        throw new InvalidArgumentException('Big-run timestamp must be finite and non-negative.');
    }
    return $now;
}

function big_run_state_directory(string $base_directory): string {
    return mb_rtrim($base_directory, "/\\", '8bit') .
        DIRECTORY_SEPARATOR .
        REQUEST_RATE_LIMIT_STATE_DIRECTORY;
}

/**
 * Create/validate the private state directory used by the big-run gate.
 *
 * The generic request-rate limiter shares this directory, so the check accepts
 * an existing directory only when it is a real, writable directory owned by
 * the effective process user (when POSIX ownership APIs are available).
 */
function big_run_prepare_state_directory(string $base_directory): ?string {
    $state_directory = big_run_state_directory($base_directory);
    if (is_link($state_directory)) {
        return null;
    }

    if (
        !is_dir($state_directory) &&
        !@mkdir($state_directory, 0700, true) &&
        !is_dir($state_directory)
    ) {
        return null;
    }

    clearstatcache(true, $state_directory);
    if (is_link($state_directory) || !is_dir($state_directory) || !is_writable($state_directory)) {
        return null;
    }

    $directory_stat = @lstat($state_directory);
    if (!is_array($directory_stat) || (($directory_stat['mode'] & 0170000) !== 0040000)) {
        return null;
    }

    if (function_exists('posix_geteuid')) {
        $owner = @fileowner($state_directory);
        $effective_uid = posix_geteuid();
        if (!is_int($owner) || $owner !== $effective_uid) {
            return null;
        }
    }

    @chmod($state_directory, 0700);
    return $state_directory;
}

function big_run_state_path(string $base_directory): string {
    return big_run_state_directory($base_directory) . DIRECTORY_SEPARATOR . BIG_RUN_STATE_FILE;
}

function big_run_lock_path(string $base_directory): string {
    return big_run_state_directory($base_directory) . DIRECTORY_SEPARATOR . BIG_RUN_LOCK_FILE;
}

/** @return resource|false */
function big_run_open_lock_handle(string $base_directory) {
    $state_directory = big_run_prepare_state_directory($base_directory);
    if ($state_directory === null) {
        return false;
    }

    $lock_path = $state_directory . DIRECTORY_SEPARATOR . BIG_RUN_LOCK_FILE;
    if (is_link($lock_path)) {
        return false;
    }

    $handle = @fopen($lock_path, 'c+');
    if ($handle === false) {
        return false;
    }

    clearstatcache(true, $lock_path);
    $held_stat = @fstat($handle);
    $path_stat = @lstat($lock_path);
    if (
        !is_array($held_stat) ||
        !is_array($path_stat) ||
        (($held_stat['mode'] & 0170000) !== 0100000) ||
        (($path_stat['mode'] & 0170000) !== 0100000) ||
        $held_stat['dev'] !== $path_stat['dev'] ||
        $held_stat['ino'] !== $path_stat['ino']
    ) {
        @fclose($handle);
        return false;
    }

    @chmod($lock_path, 0600);
    return $handle;
}

/** @param resource $handle */
function big_run_try_lock($handle, int $attempts = 1, int $sleep_microseconds = 2000): bool {
    for ($attempt = 0; $attempt < $attempts; ++$attempt) {
        if (@flock($handle, LOCK_EX | LOCK_NB)) {
            return true;
        }
        if ($attempt + 1 < $attempts) {
            usleep($sleep_microseconds);
        }
    }
    return false;
}

/** Classify a run by page count into the small or large size tier. */
function big_run_tier(int $page_count): string {
    return $page_count >= BIG_RUN_LARGE_THRESHOLD ? 'large' : 'small';
}

/**
 * Whether ambiguous discovery must enter the shared pool before another API
 * batch. Candidate and request-count bounds are both enforced so unusual API
 * responses cannot create unbounded ungated discovery.
 */
function big_run_discovery_probe_requires_lease(
    int $raw_candidates,
    int $api_batches,
    bool $has_continuation
): bool {
    if (!$has_continuation) {
        return false;
    }
    return $raw_candidates >= BIG_RUN_DISCOVERY_RAW_PROBE_LIMIT ||
        $api_batches >= BIG_RUN_DISCOVERY_BATCH_PROBE_LIMIT;
}

/**
 * Map all requester-controlled page-list attribution to the normal webform
 * billing class. Only server-derived workload kinds retain distinct classes.
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
 * Token cost of a big run, capped at the configured bucket capacity.
 * Safe-by-default: normalization is performed here, not left to callers.
 */
function big_run_token_cost(int $page_count, string $run_type): int {
    if ($page_count < 0) {
        throw new InvalidArgumentException('Big-run page count must be non-negative.');
    }
    $charge_type = big_run_charge_type($run_type);
    $type_weight = BIG_TOKEN_WEIGHTS[$charge_type] ?? BIG_TOKEN_WEIGHTS['webform'];
    $size_weight = BIG_SIZE_WEIGHTS[big_run_tier($page_count)];

    $cost = (int) ceil($page_count * $type_weight * $size_weight);
    return min(big_run_token_capacity(), $cost);
}

function big_run_new_entry_id(): string {
    try {
        // Prefix the hexadecimal id so PHP can never coerce an all-digit JSON
        // object key to an integer when decoding persisted lease state.
        return 'b' . bin2hex(random_bytes(8));
    } catch (Throwable $exception) {
        return uniqid('bigrun', true);
    }
}

/** @return array{0: float, 1: float} */
function big_run_refill_tokens(float $tokens, float $updated, float $now): array {
    $elapsed = max(0.0, $now - $updated);
    $tokens = min(
        (float) big_run_token_capacity(),
        $tokens + ($elapsed * big_run_token_refill_per_second())
    );
    return [$tokens, $now];
}

/**
 * @param string $event
 * @param array<string, scalar|null> $fields
 */
function big_run_log(string $event, array $fields = []): void {
    $payload = ['event' => $event] + $fields;
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
    request_rate_limit_debug_log(
        'Citation Bot big-run gate: ' . (is_string($encoded) ? $encoded : $event)
    );
}

/**
 * Record an invalid shared snapshot and emit an operator-visible recovery hint.
 *
 * Raw snapshot contents are intentionally never logged.
 *
 * @param string $reason
 * @param array<string, scalar|null> $fields
 */
function big_run_log_state_invalid(string $reason, array $fields = []): void {
    $GLOBALS['citation_bot_big_run_last_state_invalid_reason'] = $reason;
    big_run_log('state_invalid', ['reason' => $reason] + $fields);

    static $reported = [];
    if (isset($reported[$reason])) {
        return;
    }
    $reported[$reason] = true;
    request_rate_limit_debug_log(
        'Citation Bot big-run gate: INVALID SHARED STATE (' . $reason . '); ' .
        'bulk admission is FAIL-CLOSED and no automatic recovery will run. ' .
        'Diagnose with `php tools/reset_big_run_state.php --check`; after draining/quiescing bulk workers ' .
        'recover with `php tools/reset_big_run_state.php --reset` or the DEPLOY_TOKEN-protected ' .
        '`reset_big_run_state.php` web endpoint.'
    );
}

function big_run_last_state_invalid_reason(): ?string {
    $reason = $GLOBALS['citation_bot_big_run_last_state_invalid_reason'] ?? null;
    return is_string($reason) ? $reason : null;
}

function big_run_clear_last_state_invalid_reason(): void {
    unset($GLOBALS['citation_bot_big_run_last_state_invalid_reason']);
}

/**
 * Log a gate-infrastructure failure once per request without reusing the
 * generic token-bucket logger, whose documented fallback is fail-open.
 */
function big_run_log_failure(string $reason, string $effect = 'bulk_admission_closed'): void {
    static $reported = [];

    $key = $reason . ':' . $effect;
    if (isset($reported[$key])) {
        return;
    }
    $reported[$key] = true;
    big_run_log('infrastructure_failure', ['reason' => $reason, 'effect' => $effect]);
}

/**
 * Read state while the caller holds BIG_RUN_LOCK_FILE.
 *
 * Missing state means a fresh bucket. A present but malformed state file is
 * not treated as fresh: admissions fail closed rather than forgetting live
 * leases/tokens after corruption.
 *
 * @return array{
 *   0: float,
 *   1: float,
 *   2: array<string, array{started_at: float, tier: string, last_seen_at: float, phase: string}>,
 *   3: bool
 * }
 */
function big_run_read_state_file(string $state_path, float $now): array {
    $tokens = (float) big_run_token_capacity();
    $updated = $now;
    $entries = [];

    if (is_link($state_path)) {
        big_run_log_state_invalid('state_path_symlink');
        return [$tokens, $updated, $entries, false];
    }
    if (!file_exists($state_path)) {
        return [$tokens, $updated, $entries, true];
    }

    $state_stat = @lstat($state_path);
    if (!is_array($state_stat) || (($state_stat['mode'] & 0170000) !== 0100000)) {
        big_run_log_state_invalid('state_path_not_regular');
        return [$tokens, $updated, $entries, false];
    }

    $raw_state = @file_get_contents(
        $state_path,
        false,
        null,
        0,
        BIG_RUN_STATE_MAX_BYTES + 1
    );
    if (!is_string($raw_state)) {
        big_run_log_state_invalid('state_unreadable');
        return [$tokens, $updated, $entries, false];
    }
    if ($raw_state === '') {
        big_run_log_state_invalid('state_empty');
        return [$tokens, $updated, $entries, false];
    }
    if (mb_strlen($raw_state, '8bit') > BIG_RUN_STATE_MAX_BYTES) {
        big_run_log_state_invalid('state_oversized');
        return [$tokens, $updated, $entries, false];
    }

    try {
        $state = json_decode($raw_state, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        // Keep malformed state fail-closed without logging its contents.
        big_run_log_state_invalid('json_decode', [
            'json_error' => $exception->getCode(),
        ]);
        return [$tokens, $updated, $entries, false];
    }
    if (
        !is_array($state) ||
        !isset($state['tokens'], $state['updated']) ||
        !is_numeric($state['tokens']) ||
        !is_numeric($state['updated']) ||
        !array_key_exists('entries', $state) ||
        !is_array($state['entries'])
    ) {
        big_run_log_state_invalid('top_level_schema');
        return [$tokens, $updated, $entries, false];
    }

    $saved_tokens = (float) $state['tokens'];
    $saved_updated = (float) $state['updated'];
    if (
        !is_finite($saved_tokens) ||
        !is_finite($saved_updated) ||
        $saved_tokens < 0.0 ||
        $saved_updated < 0.0
    ) {
        big_run_log_state_invalid('top_level_numeric');
        return [$tokens, $updated, $entries, false];
    }

    $tokens = min((float) big_run_token_capacity(), $saved_tokens);
    // A wall-clock rollback must not create negative refill time.
    $updated = min($now, $saved_updated);

    foreach ($state['entries'] as $entry_id => $entry) {
        if (
            !is_string($entry_id) ||
            preg_match('~\A[a-zA-Z0-9._-]{1,80}\z~D', $entry_id) !== 1 ||
            !is_array($entry) ||
            !isset($entry['started_at'], $entry['tier'], $entry['last_seen_at'], $entry['phase']) ||
            !is_numeric($entry['started_at']) ||
            !is_numeric($entry['last_seen_at']) ||
            !is_string($entry['tier']) ||
            !is_string($entry['phase']) ||
            !in_array($entry['tier'], ['small', 'large'], true) ||
            !in_array($entry['phase'], ['probe', 'discovery', 'running'], true)
        ) {
            big_run_log_state_invalid('entry_schema');
            return [$tokens, $updated, [], false];
        }

        $started_at = (float) $entry['started_at'];
        $last_seen_at = (float) $entry['last_seen_at'];
        if (
            !is_finite($started_at) ||
            !is_finite($last_seen_at) ||
            $started_at < 0.0 ||
            $last_seen_at < 0.0 ||
            $last_seen_at < $started_at ||
            ($entry['phase'] === 'probe' && $entry['tier'] !== 'small')
        ) {
            big_run_log_state_invalid('entry_value');
            return [$tokens, $updated, [], false];
        }

        // Future timestamps must not create immortal leases.
        $started_at = min($now, $started_at);
        $last_seen_at = min($now, max($started_at, $last_seen_at));
        $entries[$entry_id] = [
            'started_at' => $started_at,
            'tier' => $entry['tier'],
            'last_seen_at' => $last_seen_at,
            'phase' => $entry['phase'],
        ];
    }

    return [$tokens, $updated, $entries, true];
}

/**
 * Crash-atomic state replacement. The permanent lock file is distinct from
 * the replaceable JSON file so every process continues locking the same inode.
 *
 * Caller must hold BIG_RUN_LOCK_FILE exclusively.
 *
 * @param string $state_path
 * @param float $tokens
 * @param float $updated
 * @param array<string, array{started_at: float, tier: string, last_seen_at: float, phase: string}> $entries
 */
function big_run_store_state_file(string $state_path, float $tokens, float $updated, array $entries): bool {
    $encoded = json_encode(
        ['tokens' => $tokens, 'updated' => $updated, 'entries' => $entries],
        JSON_PRESERVE_ZERO_FRACTION
    );
    if (
        !is_string($encoded) ||
        mb_strlen($encoded, '8bit') > BIG_RUN_STATE_MAX_BYTES
    ) {
        return false;
    }

    $directory = dirname($state_path);
    $temporary_path = @tempnam($directory, '.big-run-state-');
    if ($temporary_path === false) {
        return false;
    }
    @chmod($temporary_path, 0600);

    $handle = @fopen($temporary_path, 'wb');
    if ($handle === false) {
        @unlink($temporary_path);
        return false;
    }

    $ok = true;
    $length = mb_strlen($encoded, '8bit');
    $offset = 0;
    while ($offset < $length) {
        $written = @fwrite($handle, mb_substr($encoded, $offset, null, '8bit'));
        if (!is_int($written) || $written <= 0) {
            $ok = false;
            break;
        }
        $offset += $written;
    }

    if ($ok && !@fflush($handle)) {
        $ok = false;
    }
    if ($ok && function_exists('fsync')) {
        /** @psalm-suppress UnusedFunctionCall */
        @fsync($handle); // Best effort: atomic rename is the required invariant.
    }
    fclose($handle);

    if (!$ok || !@rename($temporary_path, $state_path)) {
        @unlink($temporary_path);
        return false;
    }

    @chmod($state_path, 0600);
    return true;
}

/**
 * Inspect shared big-run state under the permanent lock without changing it.
 *
 * @return array{
 *   ok: bool,
 *   reason: string|null,
 *   state_path: string,
 *   lease_entries: int|null,
 *   tokens: float|null
 * }
 */
function big_run_recovery_check(
    ?string $base_directory = null,
    ?float $now = null,
    int $lock_attempts = 2500
): array {
    if ($lock_attempts < 1) {
        throw new InvalidArgumentException('Recovery lock attempts must be positive.');
    }

    $base_directory = $base_directory ?? request_rate_limit_base_directory();
    if ($base_directory === '') {
        return [
            'ok' => false,
            'reason' => 'base_directory_empty',
            'state_path' => '',
            'lease_entries' => null,
            'tokens' => null,
        ];
    }

    $state_path = big_run_state_path($base_directory);
    $lock_handle = big_run_open_lock_handle($base_directory);
    if ($lock_handle === false) {
        return [
            'ok' => false,
            'reason' => 'lock_open_failed',
            'state_path' => $state_path,
            'lease_entries' => null,
            'tokens' => null,
        ];
    }

    $locked = false;
    try {
        $locked = big_run_try_lock($lock_handle, $lock_attempts);
        if (!$locked) {
            return [
                'ok' => false,
                'reason' => 'lock_busy',
                'state_path' => $state_path,
                'lease_entries' => null,
                'tokens' => null,
            ];
        }

        $now = big_run_resolve_now($now);
        big_run_clear_last_state_invalid_reason();
        [$tokens, , $entries, $valid] = big_run_read_state_file($state_path, $now);
        if (!$valid) {
            return [
                'ok' => false,
                'reason' => big_run_last_state_invalid_reason() ?? 'state_invalid',
                'state_path' => $state_path,
                'lease_entries' => null,
                'tokens' => null,
            ];
        }

        return [
            'ok' => true,
            'reason' => null,
            'state_path' => $state_path,
            'lease_entries' => count($entries),
            'tokens' => $tokens,
        ];
    } finally {
        if ($locked) {
            @flock($lock_handle, LOCK_UN);
        }
        fclose($lock_handle);
    }
}

function big_run_recovery_backup_path(string $state_path): ?string {
    $timestamp = gmdate('Ymd\THis\Z');
    $pid = getmypid();
    $pid_text = is_int($pid) ? (string) $pid : 'pid';

    for ($suffix = 0; $suffix < 100; ++$suffix) {
        $candidate = $state_path . '.recovery-' . $timestamp . '-' . $pid_text;
        if ($suffix > 0) {
            $candidate .= '-' . (string) $suffix;
        }
        clearstatcache(true, $candidate);
        if (@lstat($candidate) === false) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Operator-requested shared-state reset.
 *
 * Caller should drain/quiesce bulk workers first. The permanent lock is held
 * across backup + reset. Any existing regular snapshot is renamed to a
 * timestamped same-directory backup before an atomically written fresh state
 * is installed. On write failure the old snapshot is restored when possible.
 *
 * @return array{ok: bool, reason: string|null, state_path: string, backup_path: string|null}
 */
function big_run_recovery_reset(
    ?string $base_directory = null,
    ?float $now = null,
    int $lock_attempts = 2500
): array {
    if ($lock_attempts < 1) {
        throw new InvalidArgumentException('Recovery lock attempts must be positive.');
    }

    $base_directory = $base_directory ?? request_rate_limit_base_directory();
    if ($base_directory === '') {
        return [
            'ok' => false,
            'reason' => 'base_directory_empty',
            'state_path' => '',
            'backup_path' => null,
        ];
    }

    $state_path = big_run_state_path($base_directory);
    $lock_handle = big_run_open_lock_handle($base_directory);
    if ($lock_handle === false) {
        return [
            'ok' => false,
            'reason' => 'lock_open_failed',
            'state_path' => $state_path,
            'backup_path' => null,
        ];
    }

    $locked = false;
    $backup_path = null;
    try {
        $locked = big_run_try_lock($lock_handle, $lock_attempts);
        if (!$locked) {
            return [
                'ok' => false,
                'reason' => 'lock_busy',
                'state_path' => $state_path,
                'backup_path' => null,
            ];
        }

        if (is_link($state_path)) {
            return [
                'ok' => false,
                'reason' => 'state_path_symlink',
                'state_path' => $state_path,
                'backup_path' => null,
            ];
        }

        if (file_exists($state_path)) {
            $state_stat = @lstat($state_path);
            if (!is_array($state_stat) || (($state_stat['mode'] & 0170000) !== 0100000)) {
                return [
                    'ok' => false,
                    'reason' => 'state_path_not_regular',
                    'state_path' => $state_path,
                    'backup_path' => null,
                ];
            }

            $backup_path = big_run_recovery_backup_path($state_path);
            if ($backup_path === null || !@rename($state_path, $backup_path)) {
                return [
                    'ok' => false,
                    'reason' => 'backup_failed',
                    'state_path' => $state_path,
                    'backup_path' => null,
                ];
            }
            @chmod($backup_path, 0600);
        }

        $now = big_run_resolve_now($now);
        if (
            !big_run_store_state_file(
                $state_path,
                (float) big_run_token_capacity(),
                $now,
                []
            )
        ) {
            if ($backup_path !== null) {
                if (!@rename($backup_path, $state_path)) {
                    big_run_log_failure(
                        'operator reset write failed and previous snapshot could not be restored',
                        'manual_recovery_required'
                    );
                    return [
                        'ok' => false,
                        'reason' => 'reset_write_and_rollback_failed',
                        'state_path' => $state_path,
                        'backup_path' => $backup_path,
                    ];
                }
                @chmod($state_path, 0600);
            }

            return [
                'ok' => false,
                'reason' => 'reset_write_failed',
                'state_path' => $state_path,
                'backup_path' => null,
            ];
        }

        big_run_clear_last_state_invalid_reason();
        big_run_log('operator_state_reset', [
            'effect' => 'leases_cleared_tokens_full',
            'backup' => $backup_path === null ? null : basename($backup_path),
        ]);

        return [
            'ok' => true,
            'reason' => null,
            'state_path' => $state_path,
            'backup_path' => $backup_path,
        ];
    } finally {
        if ($locked) {
            @flock($lock_handle, LOCK_UN);
        }
        fclose($lock_handle);
    }
}

/**
 * @param array<string, array{started_at: float, tier: string, last_seen_at: float, phase: string}> $entries
 * @return array<string, array{started_at: float, tier: string, last_seen_at: float, phase: string}>
 */
function big_run_prune_stale_entries(array $entries, float $now): array {
    $cutoff = $now - (float) big_run_stale_timeout_seconds();
    $pruned = [];
    foreach ($entries as $entry_id => $entry) {
        $last_seen = $entry['last_seen_at'];
        if ($last_seen >= $cutoff && $last_seen <= $now) {
            $pruned[$entry_id] = $entry;
        }
    }
    return $pruned;
}

/** @param array<string, array{started_at: float, tier: string, last_seen_at: float, phase: string}> $entries */
function big_run_count_tier(array $entries, string $tier): int {
    $count = 0;
    foreach ($entries as $entry) {
        if ($entry['tier'] === $tier) {
            ++$count;
        }
    }
    return $count;
}

/** @param array<string, array{started_at: float, tier: string, last_seen_at: float, phase: string}> $entries */
function big_run_count_phase(array $entries, string $phase): int {
    $count = 0;
    foreach ($entries as $entry) {
        if ($entry['phase'] === $phase) {
            ++$count;
        }
    }
    return $count;
}

/**
 * Probe leases are deliberately outside the normal bulk total. With the
 * defaults this permits at most 10 admitted bulk runs plus 4 short discovery
 * probes, preserving the remaining workers for interactive traffic.
 *
 * @param array<string, array{started_at: float, tier: string, last_seen_at: float, phase: string}> $entries
 */
function big_run_count_bulk_entries(array $entries): int {
    $count = 0;
    foreach ($entries as $entry) {
        if ($entry['phase'] !== 'probe') {
            ++$count;
        }
    }
    return $count;
}

/**
 * @return array{0: bool, 1: int|null, 2: string|null, 3: string|null, 4: int|null}
 */
function big_run_try_acquire(
    int $page_count,
    string $run_type,
    ?string $base_directory = null,
    ?float $now = null,
    bool $charge_tokens = true
): array {
    $base_directory = $base_directory ?? request_rate_limit_base_directory();
    if ($base_directory === '') {
        big_run_log_failure('temporary directory is empty');
        return [false, 2, null, 'retry_later', 0];
    }

    $lock_handle = big_run_open_lock_handle($base_directory);
    if ($lock_handle === false) {
        big_run_log_failure('unable to open lock file');
        return [false, 2, null, 'retry_later', 0];
    }

    $locked = false;
    try {
        $locked = big_run_try_lock($lock_handle);
        if (!$locked) {
            big_run_log('deferred', ['reason' => 'retry_later', 'phase' => 'admission']);
            return [false, 1, null, 'retry_later', 0];
        }

        $now = big_run_resolve_now($now);
        $state_path = big_run_state_path($base_directory);
        [$tokens, $updated, $entries, $state_valid] = big_run_read_state_file($state_path, $now);
        if (!$state_valid) {
            big_run_log('deferred', ['reason' => 'state_invalid', 'phase' => 'admission']);
            return [false, 2, null, 'retry_later', 0];
        }

        $entries = big_run_prune_stale_entries($entries, $now);
        [$tokens, $updated] = big_run_refill_tokens($tokens, $updated, $now);

        $active_count = big_run_count_bulk_entries($entries);
        $probe_count = big_run_count_phase($entries, 'probe');
        $large_count = big_run_count_tier($entries, 'large');
        $tier = big_run_tier($page_count);
        $cost = $charge_tokens ? big_run_token_cost($page_count, $run_type) : 0;

        $pool_reason = null;
        if ($active_count >= big_run_max_total()) {
            $pool_reason = 'total_full';
        } elseif ($tier === 'large' && $large_count >= big_run_max_large()) {
            $pool_reason = 'large_full';
        }

        $tokens_available = $tokens >= $cost;
        if ($pool_reason === null && $tokens_available) {
            $entry_id = big_run_new_entry_id();
            $entries[$entry_id] = [
                'started_at' => $now,
                'tier' => $tier,
                'last_seen_at' => $now,
                'phase' => 'running',
            ];
            if (!big_run_store_state_file($state_path, $tokens - $cost, $updated, $entries)) {
                big_run_log_failure('unable to persist admitted state');
                return [false, 2, null, 'retry_later', $active_count];
            }

            big_run_log('admitted', [
                'run_type' => $run_type,
                'tier' => $tier,
                'pages' => $page_count,
                'cost' => $cost,
                'tokens_after' => $tokens - $cost,
                'active_total' => $active_count + 1,
                'active_probes' => $probe_count,
                'active_large' => $large_count + ($tier === 'large' ? 1 : 0),
            ]);
            return [true, null, $entry_id, null, $active_count + 1];
        }

        $reason = $pool_reason;
        $retry_after = $pool_reason === null ? null : big_run_pool_retry_seconds();
        if (!$tokens_available) {
            $token_wait = (int) max(
                1,
                ceil(($cost - $tokens) / big_run_token_refill_per_second())
            );
            if ($retry_after === null || $token_wait > $retry_after) {
                $retry_after = $token_wait;
                $reason = 'tokens';
            }
        }

        if (!big_run_store_state_file($state_path, $tokens, $updated, $entries)) {
            big_run_log_failure('unable to persist deferred state');
            return [false, 2, null, 'retry_later', $active_count];
        }

        big_run_log('deferred', [
            'reason' => $reason,
            'phase' => 'admission',
            'run_type' => $run_type,
            'tier' => $tier,
            'pages' => $page_count,
            'cost' => $cost,
            'tokens' => $tokens,
            'active_total' => $active_count,
            'active_probes' => $probe_count,
            'active_large' => $large_count,
        ]);
        return [false, $retry_after, null, $reason, $active_count];
    } finally {
        if ($locked) {
            @flock($lock_handle, LOCK_UN);
        }
        fclose($lock_handle);
    }
}

/**
 * Atomically reserve one total-pool slot for expensive discovery without
 * spending tokens. Bulk state/storage failures fail closed; singles that never
 * need this function remain unaffected.
 *
 * @return array{0: bool, 1: int|null, 2: string|null, 3: string|null, 4: int|null}
 */
function big_run_try_acquire_discovery(?string $base_directory = null, ?float $now = null): array {
    $base_directory = $base_directory ?? request_rate_limit_base_directory();
    if ($base_directory === '') {
        big_run_log_failure('temporary directory is empty');
        return [false, 2, null, 'retry_later', 0];
    }

    $lock_handle = big_run_open_lock_handle($base_directory);
    if ($lock_handle === false) {
        big_run_log_failure('unable to open lock file');
        return [false, 2, null, 'retry_later', 0];
    }

    $locked = false;
    try {
        $locked = big_run_try_lock($lock_handle);
        if (!$locked) {
            return [false, 1, null, 'retry_later', 0];
        }

        $now = big_run_resolve_now($now);
        $state_path = big_run_state_path($base_directory);
        [$tokens, $updated, $entries, $state_valid] = big_run_read_state_file($state_path, $now);
        if (!$state_valid) {
            big_run_log('deferred', ['reason' => 'state_invalid', 'phase' => 'discovery']);
            return [false, 2, null, 'retry_later', 0];
        }

        $entries = big_run_prune_stale_entries($entries, $now);
        [$tokens, $updated] = big_run_refill_tokens($tokens, $updated, $now);
        $active_count = big_run_count_bulk_entries($entries);

        if ($active_count >= big_run_max_total()) {
            if (!big_run_store_state_file($state_path, $tokens, $updated, $entries)) {
                big_run_log_failure('unable to persist deferred discovery state');
                return [false, 2, null, 'retry_later', $active_count];
            }
            big_run_log('deferred', [
                'reason' => 'total_full',
                'phase' => 'discovery',
                'active_total' => $active_count,
                'active_probes' => big_run_count_phase($entries, 'probe'),
                'active_large' => big_run_count_tier($entries, 'large'),
            ]);
            return [false, big_run_pool_retry_seconds(), null, 'total_full', $active_count];
        }

        $entry_id = big_run_new_entry_id();
        $entries[$entry_id] = [
            'started_at' => $now,
            'tier' => 'small',
            'last_seen_at' => $now,
            'phase' => 'discovery',
        ];
        if (!big_run_store_state_file($state_path, $tokens, $updated, $entries)) {
            big_run_log_failure('unable to persist discovery lease');
            return [false, 2, null, 'retry_later', $active_count];
        }

        big_run_log('discovery_admitted', [
            'active_total' => $active_count + 1,
            'active_probes' => big_run_count_phase($entries, 'probe'),
            'active_large' => big_run_count_tier($entries, 'large'),
        ]);
        return [true, null, $entry_id, null, $active_count + 1];
    } finally {
        if ($locked) {
            @flock($lock_handle, LOCK_UN);
        }
        fclose($lock_handle);
    }
}

/**
 * Acquire a short-lived discovery probe before the first remote category/link
 * API call. Probe leases do not consume the normal total pool or tokens.
 *
 * @return array{0: bool, 1: int|null, 2: string|null, 3: string|null, 4: int|null}
 */
function big_run_try_acquire_probe(?string $base_directory = null, ?float $now = null): array {
    $base_directory = $base_directory ?? request_rate_limit_base_directory();
    if ($base_directory === '') {
        big_run_log_failure('temporary directory is empty');
        return [false, 2, null, 'retry_later', 0];
    }

    $lock_handle = big_run_open_lock_handle($base_directory);
    if ($lock_handle === false) {
        big_run_log_failure('unable to open lock file');
        return [false, 2, null, 'retry_later', 0];
    }

    $locked = false;
    try {
        $locked = big_run_try_lock($lock_handle);
        if (!$locked) {
            return [false, 1, null, 'retry_later', 0];
        }

        $now = big_run_resolve_now($now);
        $state_path = big_run_state_path($base_directory);
        [$tokens, $updated, $entries, $state_valid] = big_run_read_state_file($state_path, $now);
        if (!$state_valid) {
            big_run_log('deferred', ['reason' => 'state_invalid', 'phase' => 'probe']);
            return [false, 2, null, 'retry_later', 0];
        }

        $entries = big_run_prune_stale_entries($entries, $now);
        [$tokens, $updated] = big_run_refill_tokens($tokens, $updated, $now);
        $probe_count = big_run_count_phase($entries, 'probe');
        $active_count = big_run_count_bulk_entries($entries);

        if ($probe_count >= big_run_max_discovery_probes()) {
            if (!big_run_store_state_file($state_path, $tokens, $updated, $entries)) {
                big_run_log_failure('unable to persist deferred probe state');
                return [false, 2, null, 'retry_later', $active_count];
            }
            big_run_log('deferred', [
                'reason' => 'probe_full',
                'phase' => 'probe',
                'active_total' => $active_count,
                'active_probes' => $probe_count,
            ]);
            return [false, big_run_pool_retry_seconds(), null, 'probe_full', $probe_count];
        }

        $entry_id = big_run_new_entry_id();
        $entries[$entry_id] = [
            'started_at' => $now,
            'tier' => 'small',
            'last_seen_at' => $now,
            'phase' => 'probe',
        ];
        if (!big_run_store_state_file($state_path, $tokens, $updated, $entries)) {
            big_run_log_failure('unable to persist probe lease');
            return [false, 2, null, 'retry_later', $active_count];
        }

        big_run_log('probe_admitted', [
            'active_total' => $active_count,
            'active_probes' => $probe_count + 1,
        ]);
        return [true, null, $entry_id, null, $active_count];
    } finally {
        if ($locked) {
            @flock($lock_handle, LOCK_UN);
        }
        fclose($lock_handle);
    }
}

/**
 * Atomically convert a probe lease into a normal discovery lease. The normal
 * total pool is checked at this boundary; on denial the probe is removed.
 *
 * @return array{0: bool, 1: int|null, 2: string|null, 3: string|null, 4: int|null}
 */
function big_run_try_promote_probe_to_discovery(
    string $entry_id,
    ?string $base_directory = null,
    ?float $now = null
): array {
    $base_directory = $base_directory ?? request_rate_limit_base_directory();
    if ($base_directory === '') {
        big_run_log_failure('temporary directory is empty');
        return [false, 2, null, 'retry_later', 0];
    }

    $lock_handle = big_run_open_lock_handle($base_directory);
    if ($lock_handle === false) {
        big_run_log_failure('unable to open lock file');
        return [false, 2, null, 'retry_later', 0];
    }

    $locked = false;
    try {
        $locked = big_run_try_lock($lock_handle);
        if (!$locked) {
            return [false, 1, null, 'retry_later', 0];
        }

        $now = big_run_resolve_now($now);
        $state_path = big_run_state_path($base_directory);
        [$tokens, $updated, $entries, $state_valid] = big_run_read_state_file($state_path, $now);
        if (!$state_valid) {
            return [false, 2, null, 'retry_later', 0];
        }

        $entries = big_run_prune_stale_entries($entries, $now);
        [$tokens, $updated] = big_run_refill_tokens($tokens, $updated, $now);
        if (!isset($entries[$entry_id])) {
            return [false, 2, null, 'retry_later', big_run_count_bulk_entries($entries)];
        }
        if ($entries[$entry_id]['phase'] === 'discovery') {
            return [true, null, $entry_id, null, big_run_count_bulk_entries($entries)];
        }
        if ($entries[$entry_id]['phase'] !== 'probe') {
            return [false, 2, null, 'retry_later', big_run_count_bulk_entries($entries)];
        }

        $active_count = big_run_count_bulk_entries($entries);
        if ($active_count >= big_run_max_total()) {
            unset($entries[$entry_id]);
            if (!big_run_store_state_file($state_path, $tokens, $updated, $entries)) {
                big_run_log_failure('unable to persist failed probe promotion cleanup');
                return [false, 2, null, 'retry_later', $active_count];
            }
            big_run_log('probe_deferred', [
                'reason' => 'total_full',
                'active_total' => $active_count,
                'active_probes' => big_run_count_phase($entries, 'probe'),
            ]);
            return [false, big_run_pool_retry_seconds(), null, 'total_full', $active_count];
        }

        $entries[$entry_id]['phase'] = 'discovery';
        $entries[$entry_id]['last_seen_at'] = $now;
        if (!big_run_store_state_file($state_path, $tokens, $updated, $entries)) {
            big_run_log_failure('unable to persist probe promotion');
            return [false, 2, null, 'retry_later', $active_count];
        }

        big_run_log('probe_promoted', [
            'active_total' => $active_count + 1,
            'active_probes' => big_run_count_phase($entries, 'probe'),
        ]);
        return [true, null, $entry_id, null, $active_count + 1];
    } finally {
        if ($locked) {
            @flock($lock_handle, LOCK_UN);
        }
        fclose($lock_handle);
    }
}

/**
 * Move a provisional discovery lease into the shared large subpool as soon as
 * discovery proves the request has at least BIG_RUN_LARGE_THRESHOLD pages.
 * No tokens are charged until final discovery -> running promotion.
 *
 * @return array{0: bool, 1: int|null, 2: string|null, 3: string|null, 4: int|null}
 */
function big_run_try_mark_discovery_large(
    string $entry_id,
    ?string $base_directory = null,
    ?float $now = null
): array {
    $base_directory = $base_directory ?? request_rate_limit_base_directory();
    if ($base_directory === '') {
        big_run_log_failure('temporary directory is empty');
        return [false, 2, null, 'retry_later', 0];
    }

    $lock_handle = big_run_open_lock_handle($base_directory);
    if ($lock_handle === false) {
        big_run_log_failure('unable to open lock file');
        return [false, 2, null, 'retry_later', 0];
    }

    $locked = false;
    try {
        $locked = big_run_try_lock($lock_handle);
        if (!$locked) {
            return [false, 1, null, 'retry_later', 0];
        }

        $now = big_run_resolve_now($now);
        $state_path = big_run_state_path($base_directory);
        [$tokens, $updated, $entries, $state_valid] = big_run_read_state_file($state_path, $now);
        if (!$state_valid) {
            return [false, 2, null, 'retry_later', 0];
        }
        $entries = big_run_prune_stale_entries($entries, $now);
        [$tokens, $updated] = big_run_refill_tokens($tokens, $updated, $now);

        if (!isset($entries[$entry_id]) || $entries[$entry_id]['phase'] !== 'discovery') {
            return [false, 2, null, 'retry_later', count($entries)];
        }
        if ($entries[$entry_id]['tier'] === 'large') {
            return [true, null, $entry_id, null, count($entries)];
        }

        $large_count = big_run_count_tier($entries, 'large');
        if ($large_count >= big_run_max_large()) {
            unset($entries[$entry_id]);
            if (!big_run_store_state_file($state_path, $tokens, $updated, $entries)) {
                big_run_log_failure('unable to persist large-discovery denial cleanup');
                return [false, 2, null, 'retry_later', count($entries) + 1];
            }
            big_run_log('discovery_deferred', [
                'reason' => 'large_full',
                'active_total' => count($entries),
                'active_large' => $large_count,
            ]);
            return [false, big_run_pool_retry_seconds(), null, 'large_full', count($entries)];
        }

        $entries[$entry_id]['tier'] = 'large';
        $entries[$entry_id]['last_seen_at'] = $now;
        if (!big_run_store_state_file($state_path, $tokens, $updated, $entries)) {
            big_run_log_failure('unable to persist large discovery lease');
            return [false, 2, null, 'retry_later', count($entries)];
        }
        big_run_log('discovery_marked_large', [
            'active_total' => count($entries),
            'active_large' => $large_count + 1,
        ]);
        return [true, null, $entry_id, null, count($entries)];
    } finally {
        if ($locked) {
            @flock($lock_handle, LOCK_UN);
        }
        fclose($lock_handle);
    }
}

/**
 * Convert an existing provisional discovery lease into a charged running
 * lease. Promotion is a strict discovery -> running state transition. Missing
 * or already-promoted leases fail closed rather than launching untracked work.
 *
 * Failed capacity/token promotion removes the provisional lease atomically.
 *
 * @return array{0: bool, 1: int|null, 2: string|null, 3: string|null, 4: int|null}
 */
function big_run_try_promote(
    string $entry_id,
    int $page_count,
    string $run_type,
    ?string $base_directory = null,
    ?float $now = null,
    bool $charge_tokens = true
): array {
    $base_directory = $base_directory ?? request_rate_limit_base_directory();
    if ($base_directory === '') {
        big_run_log_failure('temporary directory is empty');
        return [false, 2, null, 'retry_later', 0];
    }

    $lock_handle = big_run_open_lock_handle($base_directory);
    if ($lock_handle === false) {
        big_run_log_failure('unable to open lock file');
        return [false, 2, null, 'retry_later', 0];
    }

    $locked = false;
    try {
        $locked = big_run_try_lock($lock_handle);
        if (!$locked) {
            return [false, 1, null, 'retry_later', 0];
        }

        $now = big_run_resolve_now($now);
        $state_path = big_run_state_path($base_directory);
        [$tokens, $updated, $entries, $state_valid] = big_run_read_state_file($state_path, $now);
        if (!$state_valid) {
            big_run_log('promotion_deferred', ['reason' => 'state_invalid']);
            return [false, 2, null, 'retry_later', 0];
        }

        $entries = big_run_prune_stale_entries($entries, $now);
        [$tokens, $updated] = big_run_refill_tokens($tokens, $updated, $now);

        if (
            !isset($entries[$entry_id]) ||
            $entries[$entry_id]['phase'] !== 'discovery'
        ) {
            big_run_log('promotion_deferred', [
                'reason' => 'invalid_lease',
                'entry_present' => isset($entries[$entry_id]),
                'active_total' => count($entries),
            ]);
            return [false, 2, null, 'retry_later', count($entries)];
        }

        $tier = big_run_tier($page_count);
        $cost = $charge_tokens ? big_run_token_cost($page_count, $run_type) : 0;
        $large_count = big_run_count_tier($entries, 'large');
        // A discovery lease may already occupy the large subpool; do not count
        // the lease against itself while atomically promoting it to running.
        if ($entries[$entry_id]['tier'] === 'large') {
            $large_count = max(0, $large_count - 1);
        }

        $reason = null;
        $retry_after = null;
        if ($tier === 'large' && $large_count >= big_run_max_large()) {
            $reason = 'large_full';
            $retry_after = big_run_pool_retry_seconds();
        }
        if ($tokens < $cost) {
            $token_wait = (int) max(
                1,
                ceil(($cost - $tokens) / big_run_token_refill_per_second())
            );
            if ($retry_after === null || $token_wait > $retry_after) {
                $reason = 'tokens';
                $retry_after = $token_wait;
            }
        }

        if ($reason !== null) {
            unset($entries[$entry_id]);
            if (!big_run_store_state_file($state_path, $tokens, $updated, $entries)) {
                big_run_log_failure('unable to persist failed promotion cleanup');
                return [false, 2, null, 'retry_later', count($entries) + 1];
            }
            big_run_log('promotion_deferred', [
                'reason' => $reason,
                'run_type' => $run_type,
                'tier' => $tier,
                'pages' => $page_count,
                'cost' => $cost,
                'tokens' => $tokens,
                'active_total' => count($entries),
                'active_large' => big_run_count_tier($entries, 'large'),
            ]);
            return [false, $retry_after, null, $reason, count($entries)];
        }

        $entries[$entry_id]['tier'] = $tier;
        $entries[$entry_id]['phase'] = 'running';
        $entries[$entry_id]['last_seen_at'] = $now;
        if (!big_run_store_state_file($state_path, $tokens - $cost, $updated, $entries)) {
            big_run_log_failure('unable to persist promoted state');
            return [false, 2, null, 'retry_later', count($entries)];
        }

        big_run_log('promoted', [
            'run_type' => $run_type,
            'tier' => $tier,
            'pages' => $page_count,
            'cost' => $cost,
            'tokens_after' => $tokens - $cost,
            'active_total' => count($entries),
            'active_large' => big_run_count_tier($entries, 'large'),
        ]);
        return [true, null, $entry_id, null, count($entries)];
    } finally {
        if ($locked) {
            @flock($lock_handle, LOCK_UN);
        }
        fclose($lock_handle);
    }
}

/**
 * Remove an active or provisional entry. Best effort; charged tokens are not
 * refunded. Safe to call both explicitly and from a shutdown function.
 */
function big_run_release(string $entry_id, ?string $base_directory = null): void {
    $base_directory = $base_directory ?? request_rate_limit_base_directory();
    if ($base_directory === '' || $entry_id === '') {
        return;
    }

    $lock_handle = big_run_open_lock_handle($base_directory);
    if ($lock_handle === false) {
        return;
    }

    $locked = false;
    try {
        $locked = big_run_try_lock($lock_handle, 50);
        if (!$locked) {
            big_run_log_failure('unable to acquire state lock for release', 'best_effort_release');
            return;
        }

        $now = microtime(true);
        $state_path = big_run_state_path($base_directory);
        [$tokens, $updated, $entries, $state_valid] = big_run_read_state_file($state_path, $now);
        if (!$state_valid) {
            big_run_log_failure('unable to read valid state for release', 'best_effort_release');
            return;
        }

        if (isset($entries[$entry_id])) {
            unset($entries[$entry_id]);
            if (!big_run_store_state_file($state_path, $tokens, $updated, $entries)) {
                big_run_log_failure('unable to persist released state', 'best_effort_release');
            }
        }
    } finally {
        if ($locked) {
            @flock($lock_handle, LOCK_UN);
        }
        fclose($lock_handle);
    }
}

function big_run_set_current_entry(string $entry_id, ?string $base_directory = null, ?float $now = null): void {
    $now = big_run_resolve_now($now);
    $GLOBALS['citation_bot_big_run_current'] = [
        'entry_id' => $entry_id,
        'base_directory' => $base_directory,
        'last_heartbeat_attempt' => $now,
    ];
}

function big_run_clear_current_entry(?string $entry_id = null): void {
    $current = $GLOBALS['citation_bot_big_run_current'] ?? null;
    if (!is_array($current)) {
        return;
    }
    if ($entry_id !== null && ($current['entry_id'] ?? null) !== $entry_id) {
        return;
    }
    unset($GLOBALS['citation_bot_big_run_current']);
}

/**
 * Rate-limited request-wide heartbeat hook. The common HTTP layer calls this
 * before, during, and after external requests so a single slow transfer cannot
 * age out its lease merely because curl_exec() or the page loop has not advanced.
 */
function big_run_maybe_heartbeat_current(?float $now = null): void {
    $current = $GLOBALS['citation_bot_big_run_current'] ?? null;
    if (!is_array($current) || !isset($current['entry_id']) || !is_string($current['entry_id'])) {
        return;
    }

    $now = big_run_resolve_now($now);
    $last_attempt = isset($current['last_heartbeat_attempt']) && is_numeric($current['last_heartbeat_attempt'])
        ? (float) $current['last_heartbeat_attempt']
        : 0.0;
    if (($now - $last_attempt) < big_run_heartbeat_interval_seconds()) {
        return;
    }

    $base_directory = isset($current['base_directory']) && is_string($current['base_directory'])
        ? $current['base_directory']
        : null;
    if (big_run_heartbeat_or_stop($current['entry_id'], $base_directory, $now)) {
        // A missed lock/state write gets another chance on the next hook rather
        // than suppressing retries for a full heartbeat interval.
        $current['last_heartbeat_attempt'] = $now;
        $GLOBALS['citation_bot_big_run_current'] = $current;
    }
}

/** @return 'ok'|'retry'|'lost' */
function big_run_heartbeat_status(
    string $entry_id,
    ?string $base_directory = null,
    ?float $now = null
): string {
    $base_directory = $base_directory ?? request_rate_limit_base_directory();
    if ($base_directory === '' || $entry_id === '') {
        return 'retry';
    }

    $lock_handle = big_run_open_lock_handle($base_directory);
    if ($lock_handle === false) {
        return 'retry';
    }

    $locked = false;
    try {
        $locked = big_run_try_lock($lock_handle, 3);
        if (!$locked) {
            return 'retry';
        }

        $now = big_run_resolve_now($now);
        $state_path = big_run_state_path($base_directory);
        [$tokens, $updated, $entries, $state_valid] = big_run_read_state_file($state_path, $now);
        if (!$state_valid) {
            return 'retry';
        }
        if (!isset($entries[$entry_id])) {
            return 'lost';
        }

        $entries[$entry_id]['last_seen_at'] = $now;
        if (!big_run_store_state_file($state_path, $tokens, $updated, $entries)) {
            big_run_log_failure('unable to persist heartbeat', 'best_effort_heartbeat');
            return 'retry';
        }
        return 'ok';
    } finally {
        if ($locked) {
            @flock($lock_handle, LOCK_UN);
        }
        fclose($lock_handle);
    }
}

/** Renew a shared lease without changing legacy bool call sites. */
function big_run_heartbeat(string $entry_id, ?string $base_directory = null, ?float $now = null): bool {
    return big_run_heartbeat_status($entry_id, $base_directory, $now) === 'ok';
}

/**
 * Stop a request that definitively lost its shared lease after another
 * admission pruned/replaced it. Continuing would make the worker invisible to
 * the concurrency accounting.
 */
function big_run_stop_after_lease_loss(string $entry_id): never {
    big_run_clear_current_entry($entry_id);
    big_run_log('lease_lost', ['entry_id' => $entry_id]);

    $buffer_level = $GLOBALS['citation_bot_admission_buffer_level'] ?? null;
    if (
        is_int($buffer_level) &&
        function_exists('big_run_render_busy_page')
    ) {
        big_run_render_busy_page('lease_lost', null, big_run_pool_retry_seconds());
        exit(0);
    }

    if (!headers_sent()) {
        http_response_code(503);
        @header('Retry-After: ' . (string) big_run_pool_retry_seconds());
        @header('Cache-Control: no-store');
    }
    if (function_exists('report_warning')) {
        report_warning(
            'Citation Bot lost ownership of this bulk-run lease. ' .
            'The run was stopped to preserve concurrency limits; please retry.'
        );
    }
    if (function_exists('bot_html_footer')) {
        bot_html_footer();
    }
    exit(0);
}

/**
 * Heartbeat a shared lease and terminate only when ownership is definitively
 * lost. Transient lock/storage failures remain retryable.
 */
function big_run_heartbeat_or_stop(
    string $entry_id,
    ?string $base_directory = null,
    ?float $now = null
): bool {
    $status = big_run_heartbeat_status($entry_id, $base_directory, $now);
    if ($status === 'lost') {
        big_run_stop_after_lease_loss($entry_id);
    }
    return $status === 'ok';
}

/**
 * Open a generic rate-limit state file without following or accepting a
 * substituted pathname. The directory has already been validated as private
 * and process-owned by big_run_prepare_state_directory().
 *
 * @return resource|false
 */
function request_rate_limit_open_state_handle(string $state_directory, string $bucket) {
    $state_path = $state_directory . DIRECTORY_SEPARATOR . $bucket . '.json';
    clearstatcache(true, $state_path);
    if (is_link($state_path)) {
        return false;
    }

    $handle = @fopen($state_path, 'c+');
    if ($handle === false) {
        return false;
    }

    clearstatcache(true, $state_path);
    $held_stat = @fstat($handle);
    $path_stat = @lstat($state_path);
    if (
        !is_array($held_stat) ||
        !is_array($path_stat) ||
        (($held_stat['mode'] & 0170000) !== 0100000) ||
        (($path_stat['mode'] & 0170000) !== 0100000) ||
        $held_stat['dev'] !== $path_stat['dev'] ||
        $held_stat['ino'] !== $path_stat['ino']
    ) {
        @fclose($handle);
        return false;
    }

    if (function_exists('posix_geteuid')) {
        $owner = @fileowner($state_path);
        $effective_uid = posix_geteuid();
        if (!is_int($owner) || $owner !== $effective_uid) {
            @fclose($handle);
            return false;
        }
    }

    @chmod($state_path, 0600);
    return $handle;
}
