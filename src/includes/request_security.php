<?php

declare(strict_types=1);

require_once __DIR__ . '/user_messages.php'

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
    foreach (['wiki_base', 'pcre'] as $name) {
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
