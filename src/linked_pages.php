<?php

declare(strict_types=1);

set_time_limit(120);

@header('Access-Control-Allow-Origin: https://citations.toolforge.org');

require_once __DIR__ . '/includes/setup.php';

if (isset($_POST['linkpage']) && is_string($_POST['linkpage'])) {
    $page_name = $_POST['linkpage'];
} else {
    bot_html_header();
    report_warning(' Error in passing of linked page name ');
    bot_html_footer();
    exit(0);
}
unset($_GET, $_POST, $_REQUEST); // Memory minimize

session_start(['read_and_close' => true]);

$api = new WikipediaBot();

bot_html_header();
check_blocked();

$page_name = str_replace(' ', '_', mb_trim($page_name));
if ($page_name === '') {
    report_warning('Nothing requested on webform -- OR -- page name got lost during initial authorization ');
    bot_html_footer();
    exit(0);
} elseif (mb_substr($page_name, 0, 5) !== 'User:' && !in_array($api->get_the_user(), ['Headbomb', 'AManWithNoPlan'], true)) { // Do not let people run willy-nilly
    report_warning('API only intended for User generated pages for fixing specific issues ');
    bot_html_footer();
    exit(0);
}

$edit_summary_end = "| Suggested by " . $api->get_the_user() . " | Linked from {$page_name} | #UCB_webform_linked ";

$json = WikipediaBot::get_links($page_name);
unset($page_name);

if ($json === '') {
    report_warning(' Error getting page list');
    bot_html_footer();
    exit(0);
}
$array = json_decode($json, true);
unset($json);
if ($array === false || !isset($array['parse']['links']) || !is_array($array['parse']['links'])) {
    report_warning(' Error interpreting page list - perhaps page requested does not even exist');
    bot_html_footer();
    exit(0);
}
$links = $array['parse']['links']; // @phan-suppress-current-line PhanTypeArraySuspiciousNullable
unset($array);
$pages_in_category = [];
foreach ($links as $link) {
    if (isset($link['exists']) && ($link['ns'] === 0 || $link['ns'] === 118)) {  // normal and draft articles only
        $linked_page = (string) $link['*'];
        $linked_page = str_replace(' ', '_', $linked_page);
        if (!in_array($linked_page, AVOIDED_LINKS, true) && mb_stripos($linked_page, 'disambiguation') === false) {
            $pages_in_category[] = $linked_page;
        }
    }
}
unset($links);
$pages_in_category = array_unique($pages_in_category);

edit_a_list_of_pages($pages_in_category, $api, $edit_summary_end);
