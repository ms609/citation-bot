<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $server
 * @param array<string, mixed> $post
 * @param array<string, mixed> $session
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
    $escape = static function (string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $html = '</pre><form action="' . $escape($action) . '" method="post">';
    $html .= '<input type="hidden" name="csrf_token" value="' . $escape($csrf_token) . '" />';
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . $escape($name) . '" value="' . $escape($value) . '" />';
    }
    $html .= '<p>Requested action: <strong>' . $escape($button_text) . '</strong></p>';

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
            $details .= '<dt>' . $escape($label) . '</dt><dd>' . $escape($value) . '</dd>';
        }
    }
    if ($details !== '') {
        $html .= '<dl class="request-summary">' . $details . '</dl>';
    }

    $html .= '<p>No changes have been made. Confirm to continue.</p>';
    $html .= '<button type="submit">' . $escape($button_text) . '</button></form><pre>';
    return $html;
}
