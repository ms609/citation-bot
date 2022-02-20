<?php
declare(strict_types=1);
@session_start();
@header('Content-type: text/html; charset=utf-8');
@header('Content-Encoding: None');
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');

require_once 'setup.php';
$api = new WikipediaBot();

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
  <title>Citation Bot: running in linked page mode</title>
  <link rel="copyright" type="text/html" href="https://www.gnu.org/licenses/gpl-3.0" />
  <link rel="stylesheet" type="text/css" href="results.css" />
  </head>
<body>
  <header>
    <p>Follow Citation bots progress below.</p>
    <p>
      <a href="https://en.wikipedia.org/wiki/User:Citation_bot/use" target="_blank" title="Using Citation Bot">How&nbsp;to&nbsp;Use&nbsp;/&nbsp;Tips&nbsp;and&nbsp;Tricks</a> |
      <a href="https://en.wikipedia.org/wiki/User_talk:Citation_bot" title="Report bugs at Wikipedia" target="_blank">Report&nbsp;bugs</a> |
      <a href="https://github.com/ms609/citation-bot" target="_blank" title="GitHub repository">Source&nbsp;code</a>
    </p>
  </header>
  <main>
  <pre id="botOutput">
<?php

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
$final_edit_overview = "";

$url = API_ROOT . '?action=parse&prop=links&format=json&page=' . $page_name;
$ch = curl_init();
curl_setopt_array($ch,
      [CURLOPT_HEADER => 0,
       CURLOPT_TIMEOUT => 45,
       CURLOPT_RETURNTRANSFER =>  1,
       CURLOPT_URL => $url,
       CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org']);
$json = (string) @curl_exec($ch);
curl_close($ch);
if ($json == '') {
  report_warning(' Error getting page list');
  echo ' </pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
  exit();
}
$array = @json_decode($json, TRUE);
unset($json);
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
if (empty($pages_in_category)) {
  report_warning('No links to expand found');
  echo '</pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
  exit();
}
  $total = count($pages_in_category);
  if ($total > MAX_PAGES) {
    report_warning('Number of links is huge (' . (string) $total . ')  Cancelling run (maximum size is ' . (string) MAX_PAGES . ').  Listen to Obi-Wan Kenobi:  You want to go home and rethink your life.');
    echo '</pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
    exit();
  }
  if ($total > BIG_RUN) check_overused();

  $page = new Page();
  $done = 0;

  gc_collect_cycles();
  foreach ($pages_in_category as $page_title) {
    check_killed();
    $done++;
    if ($page->get_text_from($page_title, $api) && $page->expand_text()) {
      report_phase("Writing to " . echoable($page_title) . '... ');
      $attempts = 0;
      while (!$page->write($api, $edit_summary_end . (string) $done . '/' . (string) $total . ' ') && $attempts < MAX_TRIES) ++$attempts;
      if ($attempts < MAX_TRIES) {
        $last_rev = $api->get_last_revision($page_title);
        echo
        "\n  <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid="
        . $last_rev . ">diff</a>" .
        " | <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&action=history>history</a>";
        $final_edit_overview .=
          "\n [ <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid="
        . $last_rev . ">diff</a>" .
        " | <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&action=history>history</a> ] " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
      } else {
        report_warning("Write failed.");
        $final_edit_overview .= "\n Write failed.      " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
      }
    } else {
      report_phase($page->parsed_text() ? "No changes required. \n\n    # # # " : "Blank page. \n\n    # # # ");
       $final_edit_overview .= "\n No changes needed. " . "<a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . ">" . echoable($page_title) . "</a>";
    }
    echo "\n";
  }
  echo "\n Done all " . (string) $total . " pages linked from " . echoable($page_name) . " \n  # # # \n" . $final_edit_overview  . ' </pre><footer><a href="./" title="Use Citation Bot again">Another</a>?</footer></body></html>';
  exit();
?>
