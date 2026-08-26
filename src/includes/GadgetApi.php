<?php

declare(strict_types=1);

final class GadgetApiRequestException extends RuntimeException {
    public function __construct(
        public readonly string $error_name,
        public readonly int $http_status
    ) {
        parent::__construct($error_name);
    }
}

function gadget_api_error(string $error_name, int $http_status): void {
    http_response_code($http_status);
    @header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        ['error' => $error_name],
        JSON_THROW_ON_ERROR
    );
}

/**
 * @param array<array-key, mixed> $post
 * @return array{0: string, 1: string}
 */
function gadget_api_validate_request(array $post): array {
    if (!isset($post['text'], $post['summary']) ||
        !is_string($post['text']) ||
        !is_string($post['summary'])
    ) {
        throw new GadgetApiRequestException('invalid_parameters', 400);
    }

    $originalText = $post['text'];
    $editSummary = $post['summary'];

    if (!mb_check_encoding($originalText, 'UTF-8') ||
        !mb_check_encoding($editSummary, 'UTF-8')
    ) {
        throw new GadgetApiRequestException('invalid_utf8', 400);
    }

    if (mb_strlen($originalText) < 6) {
        throw new GadgetApiRequestException('page_too_small', 400);
    }

    if (mb_strlen($originalText) > 150000) {
        throw new GadgetApiRequestException('page_too_large', 400);
    }

    if (mb_strlen($editSummary) > 1000) {
        throw new GadgetApiRequestException('summary_too_large', 400);
    }

    return [$originalText, $editSummary];
}
