<?php

declare(strict_types=1);

require_once __DIR__ . '/user_messages.php';

/**
 * @param array<array-key, mixed>|null &$session
 * @param-out array<array-key, mixed> $session
 */
function ensure_session_csrf_token(?array &$session): string {
    $token = ($session ??= [])['csrf_token'] ?? null;
    if (!is_string($token) || $token === '') {
        $token = bin2hex(random_bytes(32));
        $session['csrf_token'] = $token;
    }
    return $token;
}

/**
 * @param array<array-key, mixed> $server
 * @param array<array-key, mixed> $post
 * @param array<array-key, mixed> $session
 */
function request_has_valid_post_csrf(array $server, array $post, array $session): bool {
    if (($server['REQUEST_METHOD'] ?? '') !== 'POST') {
        return false;
    }

    $posted_token = $post['csrf_token'] ?? null;
    $session_token = $session['csrf_token'] ?? null;
    if (!is_string($posted_token) || !is_string($session_token) || $session_token === '') {
        return false;
    }

    return hash_equals($session_token, $posted_token);
}

/**
 * @param string $page
 * @param array<array-key, mixed> $query
 * @return array<string, string>
 */
function process_page_confirmation_fields(string $page, array $query): array {
    $fields = ['page' => $page];
    foreach (['edit', 'wiki_base', 'pcre'] as $name) {
        if (isset($query[$name]) && is_string($query[$name])) {
            $fields[$name] = $query[$name];
        }
    }
    if (isset($query['slow'])) {
        $fields['slow'] = '1';
    }
    return $fields;
}

/**
 * @param string $category
 * @param array<array-key, mixed> $query
 * @return array<string, string>
 */
function category_confirmation_fields(string $category, array $query): array {
    $fields = [
        'cat' => $category,
        'extended_limit' => '1',
    ];
    foreach (['edit', 'wiki_base', 'pcre'] as $name) {
        if (isset($query[$name]) && is_string($query[$name])) {
            $fields[$name] = $query[$name];
        }
    }
    if (isset($query['slow'])) {
        $fields['slow'] = '1';
    }
    return $fields;
}

const OAUTH_FORM_ACTIONS = [
    'process_page.php' => ['page', 'Process page'],
    'category.php' => ['cat', 'Process category'],
    'linked_pages.php' => ['linkpage', 'Process linked pages'],
];

/**
 * @param array<array-key, mixed> $session
 * @param array<string, string> $fields
 */
function save_oauth_form(array &$session, string $action, array $fields): string {
    $nonce = bin2hex(random_bytes(16));
    $session['citation_bot_oauth_form'] = [
        'nonce' => $nonce,
        'expires' => time() + 900,
        'action' => $action,
        'fields' => $fields,
    ];
    return public_url_path('/resume.php') . '?request=' . $nonce;
}

/**
 * @param array<array-key, mixed> $session
 * @return array{nonce: string, expires: int, action: string, fields: array<string, string>}|null
 */
function take_oauth_form(array &$session, string $nonce): ?array {
    /** @var array{nonce: string, expires: int, action: string, fields: array<string, string>}|null $pending */
    $pending = $session['citation_bot_oauth_form'] ?? null;
    if ($pending === null || !hash_equals($pending['nonce'], $nonce)) {
        return null;
    }
    if ($pending['expires'] < time()) {
        unset($session['citation_bot_oauth_form']);
        return null;
    }
    if (!isset($session['access_key'], $session['access_secret'])) {
        return null;
    }
    unset($session['citation_bot_oauth_form']);
    return $pending;
}

/**
 * @param array<array-key, mixed> $post
 * @return array{action: string, fields: array<string, string>}|null
 */
function oauth_form_request(string $action, string $target, array $post): ?array {
    // Bound persisted input and allowlist fields. Never carry the old CSRF token or API keys.
    if (!isset(OAUTH_FORM_ACTIONS[$action]) || strlen($target) > 12800) {
        return null;
    }
    [$field] = OAUTH_FORM_ACTIONS[$action];
    $fields = [$field => $target];
    foreach (['edit', 'wiki_base', 'pcre', 'extended_limit', 'ignore_block'] as $option) {
        if (isset($post[$option]) && is_string($post[$option]) && strlen($post[$option]) <= 255) {
            $fields[$option] = $post[$option];
        }
    }
    if (isset($post['slow'])) {
        $fields['slow'] = '1';
    }
    return ['action' => $action, 'fields' => $fields];
}

/**
 * @param string $action
 * @param array<string, string> $fields
 * @param string $csrf_token
 * @param string $button_text
 */
function post_confirmation_form(string $action, array $fields, string $csrf_token, string $button_text): string {
    $html = '</pre><form action="';
    $html .= echoable($action);
    $html .= '" method="post">';
    $html .= '<input type="hidden" name="csrf_token" value="';
    $html .= echoable($csrf_token);
    $html .= '" />';
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="';
        $html .= echoable($name);
        $html .= '" value="';
        $html .= echoable($value);
        $html .= '" />';
    }
    $html .= '<p>Requested action: <strong>';
    $html .= echoable($button_text);
    $html .= '</strong></p>';

    $display_fields = [
        'page' => 'Page',
        'cat' => 'Category',
        'linkpage' => 'Linked from',
        'wiki_base' => 'Wiki',
        'slow' => 'Thorough mode',
        'pcre' => 'PCRE option',
        'edit' => 'Request source',
    ];
    $details = '';
    foreach ($display_fields as $name => $label) {
        if (array_key_exists($name, $fields)) {
            $value = $name === 'slow' ? 'enabled' : $fields[$name];
            $details .= '<dt>';
            $details .= echoable($label);
            $details .= '</dt><dd>';
            $details .= echoable($value);
            $details .= '</dd>';
        }
    }
    if ($details !== '') {
        $html .= '<dl class="request-summary">' . $details . '</dl>';
    }

    $html .= '<p>No changes have been made. Confirm to continue.</p>';
    $html .= '<button type="submit">';
    $html .= echoable($button_text);
    $html .= '</button></form><pre>';
    return $html;
}
