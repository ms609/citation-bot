<?php

declare(strict_types=1);

/**
 * Authenticate the 256-bit, lowercase-hex deployment secret.
 * Exclude the published example even if configured deliberately.
 * Keep this standalone: deployment/recovery must not need normal bootstrap.
 */
function citation_bot_valid_deploy_token(string $token): bool {
    if ($token === str_repeat('0123456789abcdef', 4)) {
        return false;
    }
    return preg_match('~\A[a-f0-9]{64}\z~D', $token) === 1;
}
