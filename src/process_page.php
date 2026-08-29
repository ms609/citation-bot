<?php

declare(strict_types=1);

set_time_limit(120);

if (file_exists(__DIR__ . '/env.php')) {
    /** @psalm-suppress MissingFile */
    include_once __DIR__ . '/env.php';
}
require_once __DIR__ . '/includes/PublicConfig.php';
enforce_public_request_configuration(is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : null);
send_configured_cors_header(is_string($_SERVER['HTTP_ORIGIN'] ?? null) ? $_SERVER['HTTP_ORIGIN'] : null);

require_once __DIR__ . '/includes/setup.php';
require_once __DIR__ . '/includes/request_security.php';

if (isset($argv[1])) {
    $pages = $argv[1];
    if (in_array($pages, ['page_list.txt', 'page_list2.txt'], true)) {
        $pages = mb_trim((string) file_get_contents($pages));
    }
    $from_get = false;
} elseif (isset($_POST["page"])) {
    $pages = $_POST["page"];
    if (!is_string($pages)) {
        bot_html_header();
        report_warning('Non-string found in POST for page.');
        bot_html_footer();
        exit(0);
    }
    $from_get = false;
} elseif (isset($_GET["page"])) {
    $pages = $_GET["page"];
    if (!is_string($pages)) {
        bot_html_header();
        report_warning('Non-string found in GET for page.');
        bot_html_footer();
        exit(0);
    }
    if (mb_strpos($pages, '|') !== false) {
        bot_html_header();
        report_warning('Use the webform for multiple pages.');
        bot_html_footer();
        exit(0);
    }
    $from_get = true;
} else {
    bot_html_header();
    report_warning('Nothing requested -- OR -- pages got lost during initial authorization ');
    bot_html_footer();
    exit(0);
}

if (!page_batch_input_within_limit($pages)) {
    http_response_code(413);
    bot_html_header();
    report_warning('Requested page list is too large.');
    bot_html_footer();
    exit(0);
}

// Do not open session until we know we have good data
session_start();
$csrf_token = ensure_session_csrf_token($_SESSION);
session_write_close();

if ($from_get) {
    // Authenticate while REQUEST_URI still contains the GET action. OAuth can then return here
    // before the action is converted to a confirmed POST.
    $request_uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : null;
    $automated_tools_request = WikipediaBot::is_automated_tools_request($request_uri);
    if ($automated_tools_request) {
        // This authentication path renders a warning instead of redirecting, so it needs
        // the document header before WikipediaBot can emit its warning and footer.
        bot_html_header();
    }
    $api = new WikipediaBot();
    unset($api);
    if (!$automated_tools_request) {
        bot_html_header();
    }
    $fields = ['page' => $pages];
    foreach (['edit', 'wiki_base', 'pcre'] as $name) {
        if (isset($_GET[$name]) && is_string($_GET[$name])) {
            $fields[$name] = $_GET[$name];
        }
    }
    if (isset($_GET['slow'])) {
        $fields['slow'] = '1';
    }
    echo post_confirmation_form('process_page.php', $fields, $csrf_token, 'Process page');
    bot_html_footer();
    exit(0);
}
unset($from_get);

bot_html_header();

if (!isset($argv[1]) && !request_has_valid_post_csrf($_SERVER, $_POST, $_SESSION)) {
    http_response_code(403);
    report_warning('A POST request with a valid CSRF token is required.');
    bot_html_footer();
    exit(0);
}

$api = new WikipediaBot();

if (HTML_OUTPUT) {
    $edit_summary_end = "| Suggested by " . $api->get_the_user() . " ";
} else {
    $edit_summary_end = ""; // Command line edits as the person
}

check_blocked();

if (!empty($_REQUEST["edit"]) && is_string($_REQUEST["edit"])) {
    if ($_REQUEST["edit"] === 'automated_tools') {
        $edit_summary_end .= "| #UCB_automated_tools ";
    } elseif ($_REQUEST["edit"] === 'toolbar') {
        $edit_summary_end .= "| #UCB_toolbar ";
    } elseif ($_REQUEST["edit"] === 'webform') {
        $edit_summary_end .= "| #UCB_webform ";
    } elseif ($_REQUEST["edit"] === 'Headbomb') {
        $edit_summary_end .= "| #UCB_Headbomb ";
    } elseif ($_REQUEST["edit"] === 'Smith609') {
        $edit_summary_end  .= "| #UCB_Smith609 ";
    } elseif ($_REQUEST["edit"] === 'arXiv') {
        $edit_summary_end .= "| #UCB_arXiv ";
    } else {
        $edit_summary_end .= "| #UCB_Other ";
    }
} else {
    if (HTML_OUTPUT) {
        $edit_summary_end .= "| #UCB_webform ";
    } else {
        $edit_summary_end .= "| #UCB_CommandLine ";
    }
}

$pages_to_do = array_unique(explode('|', $pages));
unset($pages);
unset($_GET, $_POST, $_REQUEST); // Memory minimize

edit_a_list_of_pages($pages_to_do, $api, $edit_summary_end);
