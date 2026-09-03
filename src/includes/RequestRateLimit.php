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
