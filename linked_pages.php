<?php
declare(strict_types=1);
set_time_limit(120);

@session_start();

require_once 'html_headers.php';

require_once 'setup.php';
$api = new WikipediaBot();

bot_html_header();

check_blocked();

$page_name = str_replace(' ', '_', trim((string) @$_POST['linkpage']));
if ($page_name == '') {
  if (isset($_GET['page'])) {
    report_warning('Use the webform.  Passing pages in the URL not supported anymore.');
  } else {
    report_warning('Nothing requested -- OR -- page name got lost during initial authorization ');
  }
  echo ' </pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
  exit();
} elseif (substr($page_name, 0, 5) !== 'User:' && !in_array($api->get_the_user(), ['Headbomb', 'AManWithNoPlan'])) { // Do not let people run willy-nilly
  report_warning('API only intended for User generated pages for fixing specific issues ');
  echo ' </pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
  exit();
}

if (strlen($page_name) > 256)  {
  report_warning('Possible invalid page');
  echo ' </pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
  exit();
}
$edit_summary_end = "| Suggested by " . $api->get_the_user() . " | Linked from $page_name | #UCB_webform_linked ";

$json = WikipediaBot::QueryAPI(['action' => 'parse', 'prop' => 'links', 'page' => $page_name]);

if ($json == '') {
  report_warning(' Error getting page list');
  echo ' </pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
  exit();
}
$array = @json_decode($json, TRUE);
unset($json, $page_name, $url);
if ($array === FALSE || !isset($array['parse']['links']) || !is_array($array['parse']['links'])) {
  report_warning(' Error interpreting page list - perhaps page requested does not even exist');
  echo '</pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
  exit();
}
$links = $array['parse']['links']; // @phan-suppress-current-line PhanTypeArraySuspiciousNullable
unset($array);
$pages_in_category = [];
foreach($links as $link) {
    if (isset($link['exists']) && ($link['ns'] == 0 || $link['ns'] == 118)) {  // normal and draft articles only
        $linked_page = str_replace(' ', '_', $link['*']);
        if(!in_array($linked_page, AVOIDED_LINKS) && stripos($linked_page, 'disambiguation') === FALSE) {
            $pages_in_category[] = $linked_page;
        }
    }
}
unset($links);
$pages_in_category = array_unique($pages_in_category);

edit_a_list_of_pages($pages_in_category, $api, $edit_summary_end);

exit();
?>
