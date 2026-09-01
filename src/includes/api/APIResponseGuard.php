<?php

declare(strict_types=1);

/**
 * Defensive decoding and last-resort exception containment for data received
 * from external APIs.
 *
 * The normal pattern is:
 *
 *   $json = ExternalApiResponseGuard::decodeObject($response);
 *   if ($json === null) {
 *       // Treat as "no usable metadata".
 *       return;
 *   }
 *
 * Use run() around a parser/processor when malformed upstream data could still
 * reach a code path that throws TypeError, ValueError, JsonException, etc.
 */
final class ExternalApiResponseGuard {
    private const int MAX_RESPONSE_BYTES = 16 * 1024 * 1024;
    private const int JSON_DEPTH = 128;

    private function __construct() {
        // Static utility class.
    }

    private static function responseIsSafeToDecode(string $response): bool {
        if ($response === '' || mb_strlen($response, '8bit') > self::MAX_RESPONSE_BYTES) {
            return false;
        }

        // JSON received over HTTP must be valid UTF-8. Reject malformed bytes
        // rather than allowing them to leak into string functions later.
        return preg_match('//u', $response) === 1;
    }

    /**
     * Decode arbitrary JSON without allowing malformed input to throw.
     *
     * @return mixed null means the response was invalid, JSON null, too large,
     *               too deeply nested, or not UTF-8.
     */
    public static function decodeJson(string $response): mixed {
        if (!self::responseIsSafeToDecode($response)) {
            return null;
        }

        try {
            return json_decode(
                $response,
                false,
                self::JSON_DEPTH,
                JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING
            );
        } catch (JsonException | ValueError) {
            return null;
        }
    }

    /**
     * Decode a JSON object. Arrays/scalars are rejected even when valid JSON.
     */
    public static function decodeObject(string $response): ?stdClass {
        $decoded = self::decodeJson($response);
        return $decoded instanceof stdClass ? $decoded : null;
    }

    /**
     * Decode a top-level JSON object as an associative array.
     *
     * This deliberately distinguishes {} from [] before decoding; both become
     * PHP arrays when json_decode(..., true) is used.
     *
     * @return array<mixed>|null
     */
    public static function decodeAssocObject(string $response): ?array {
        if (!self::responseIsSafeToDecode($response)) {
            return null;
        }

        $trimmed = mb_ltrim($response);
        if ($trimmed === '' || $trimmed[0] !== '{') {
            return null;
        }

        try {
            $decoded = json_decode(
                $response,
                true,
                self::JSON_DEPTH,
                JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING
            );
        } catch (JsonException | ValueError) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Execute parsing/processing code at an external API trust boundary.
     *
     * This is a final containment layer, not a substitute for shape validation:
     * validate before mutating citation data whenever practical.
     */
    public static function run(string $service, callable $operation, mixed $fallback = null): mixed {
        try {
            return $operation();
        } catch (Throwable $exception) {
            $message = str_replace(
                ["\r", "\n"],
                ' ',
                $exception::class . ': ' . $exception->getMessage()
            );
            $message = mb_substr($message, 0, 500);

            if (function_exists('bot_debug_log')) {
                bot_debug_log($service . ' returned invalid data: ' . $message);
            }
            if (function_exists('report_warning')) {
                report_warning(
                    $service . ' returned malformed data; continuing without this metadata.'
                );
            }

            return $fallback;
        }
    }
}
