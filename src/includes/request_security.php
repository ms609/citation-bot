<?php

declare(strict_types=1);

/**
 * @param array<array-key, mixed> &$session
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
 * @param string $action
 * @param array<string, string> $fields
 * @param string $csrf_token
 * @param string $button_text
 */
function post_confirmation_form(string $action, array $fields, string $csrf_token, string $button_text): string {
    $html = '</pre><form action="';
    $html .= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html .= '" method="post">';
    $html .= '<input type="hidden" name="csrf_token" value="';
    $html .= htmlspecialchars($csrf_token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html .= '" />';
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="';
        $html .= htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html .= '" value="';
        $html .= htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html .= '" />';
    }
    $html .= '<p>Requested action: <strong>';
    $html .= htmlspecialchars($button_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
            $details .= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $details .= '</dt><dd>';
            $details .= htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $details .= '</dd>';
        }
    }
    if ($details !== '') {
        $html .= '<dl class="request-summary">' . $details . '</dl>';
    }

    $html .= '<p>No changes have been made. Confirm to continue.</p>';
    $html .= '<button type="submit">';
    $html .= htmlspecialchars($button_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html .= '</button></form><pre>';
    return $html;
}
