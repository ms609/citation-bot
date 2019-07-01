<?php
@session_start();
error_reporting(E_ALL^E_NOTICE);
define("HTML_OUTPUT");
require_once('setup.php');
require_once('constants/bad_data.php');

$api = new WikipediaBot();

$SLOW_MODE = FALSE;
if (isset($_REQUEST["slow"])) {
  $SLOW_MODE = TRUE;
}

?>
<!DOCTYPE html>
<html>
  <body>
  <head>
  <title>Citation bot: Linked page mode</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <link rel="stylesheet" type="text/css" href="css/results.css" />
  </head>
  <body>
    <pre id="botOutput">
<?php

$page = str_replace(' ', '_', trim($_REQUEST['page']));
if ($page == '') report_error('Nothing requested');
if (strlen($page) >256) report_error('Possible invalid page');
$edit_summary_end = "| Activated by [[User:" . $api->get_the_user() . "]] | All pages linked from [[$page]].";

$url = 'https://en.wikipedia.org/w/api.php?action=parse&prop=links&format=json&page=' . $page;
$json = @file_get_contents($url);
if ($json === FALSE) {
  report_error(' Error getting page list');
}    
$array = @json_decode($json, true);
if ($array === FALSE) {
  report_error(' Error interpreting page list');
}
$links = $array['parse']['links'];
$pages_in_category = [];
foreach($links as $link) {
    if (isset($link['exists']) && ($link['ns'] == 0 || $link['ns'] == 118)) {  // normal and draft articles only
        $linked_page = str_replace(' ', '_', $link['*']);
        if(!in_array($linked_page, AVOIDED_LINKS) && stripos($linked_page, 'disambiguation') === FALSE) {
            $pages_in_category[] = $linked_page;
        }
    }
}


  $attempts = 0;
  shuffle($pages_in_category);
  $page = new Page();
  foreach ($pages_in_category as $page_title) {
    // $page->expand_text will take care of this notice if we are in HTML mode.
    html_echo('', "\n\n\n*** Processing page '" . echoable($page_title) . "' : " . date("H:i:s") . "\n");
    if ($page->get_text_from($page_title, $api) && $page->expand_text()) {
      report_phase("Writing to " . echoable($page_title) . '... ');
      while (!$page->write($api, $edit_summary_end) && $attempts < 2) ++$attempts;
      // Parsed text can be viewed by diff link; don't clutter page. 
      // echo "\n\n"; safely_echo($page->parsed_text());
      if ($attempts < 3 ) {
        html_echo(
        " | <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid="
        . $api->get_last_revision($page_title) . ">diff</a>" .
        " | <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&action=history>history</a>", ".");
      } else {
         report_warning("Write failed.");
      }
    } else {
      report_phase($page->parsed_text() ? 'No changes required.' : 'Blank page');
      echo "\n\n    # # # ";
    }
  }
  echo ("\n Done all " . count($pages_in_category) . " pages linked from $page \n");

html_echo(' # # #</pre></body></html>', "\n");
exit(0);
