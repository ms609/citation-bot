<?php
declare(strict_types=1);
@session_start();
@header('Content-type: text/html; charset=utf-8');
@header('Content-Encoding: None', TRUE);
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');

require_once('setup.php');
$api = new WikipediaBot();

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
  <title>Citation bot: Linked page mode</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta content="Smith609" name="author">
  <meta name="keywords" content="User:DOI bot,Citation, citation bot,Digital object identifier,wikipedia,cite journal" />
  <link rel="apple-touch-icon" href="https://en.wikipedia.org/apple-touch-icon.png" />
  <link rel="copyright" href="https://www.gnu.org/copyleft/fdl.html" />
  <link rel="stylesheet" type="text/css" href="css/results.css" />
  </head>
<body>
  <header>
    <p>Follow <a href="https://en.wikipedia.org/wiki/User:Citation_bot">Citation&nbsp;bot</a>&rsquo;s&nbsp;progress&nbsp;below.</p>
    <p>
      <a href="https://en.wikipedia.org/wiki/User:Citation_bot/use" target="_blank" title="Using Citation Bot">How&nbsp;to&nbsp;Use&nbsp;/&nbsp;Tips&nbsp;and&nbsp;Tricks</a> |
      <a href="https://en.wikipedia.org/wiki/Special:Contributions/Citation_bot" target="_blank" title="Recent contributions">Bot&rsquo;s&nbsp;recent&nbsp;edits</a> |
      <a href="https://en.wikipedia.org/wiki/User_talk:Citation_bot" title="Report bugs at Wikipedia" target="_blank">Report&nbsp;bugs</a> |
      <a href="https://github.com/ms609/citation-bot" target="_blank" title="GitHub repository">Source&nbsp;code</a>
    </p>
  </header>
  <main>
  <pre id="botOutput">
<?php

check_blocked();
check_overused();

$page_name = str_replace(' ', '_', trim((string) @$_POST['linkpage']));
if ($page_name == '') {
  if (isset($_GET['page'])) {
    report_warning('Use the webform.  Passing pages via URL not supported anymore.');
  } else {
    report_warning('Nothing requested -- OR -- page name got lost during initial authorization ');
  }
  echo("\n </pre></body></html>");
  exit(0);
} elseif (substr($page_name, 0, 5) !== 'User:' && !in_array($api->get_the_user(), ['Headbomb', 'AManWithNoPlan'])) { // Do not let people run willy-nilly
  report_warning('API only intended for User generated pages for fixing specific issues ');
  echo("\n </pre></body></html>");
  exit(0);
}

if (strlen($page_name) > 256)  {
  report_warning('Possible invalid page');
  echo("\n </pre></body></html>");
  exit(0);
}
$edit_summary_end = "| Suggested by " . $api->get_the_user() . " | Pages linked from cached $page_name | via #UCB_webform_linked ";
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
  echo("\n </pre></body></html>");
  exit(0);
}
$array = @json_decode($json, TRUE);
unset($json);
if ($array === FALSE || !isset($array['parse']['links']) || !is_array($array['parse']['links'])) {
  report_warning(' Error interpreting page list - perhaps page requested does not even exist');
  echo("\n </pre></body></html>");
  exit(0);
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
  echo("\n </pre></body></html>");
  exit(0);
}
  if (count($pages_in_category) > MAX_PAGES) {
    report_warning('Number of links is huge (' . (string) count($pages_in_category) . ')  Cancelling run (maximum size is ' . (string) MAX_PAGES . ').  Listen to Obi-Wan Kenobi:  You want to go home and rethink your life.');
    echo("\n </pre></body></html>");
    exit(0);
  }

  $page = new Page();
  gc_collect_cycles();
  $done = 0;
  $total = count($pages_in_category);
  foreach ($pages_in_category as $page_title) {
    $done++;
    // $page->expand_text will take care of this notice if we are in HTML mode.
    html_echo('', "\n\n\n*** Processing page '" . echoable($page_title) . "' : " . date("H:i:s") . "\n");
    if ($page->get_text_from($page_title, $api) && $page->expand_text()) {
      report_phase("Writing to " . echoable($page_title) . '... ');
      $attempts = 0;
      while (!$page->write($api, $edit_summary_end . (string) $done . '/' . (string) $total . ' ') && $attempts < MAX_TRIES) ++$attempts;
      if ($attempts < MAX_TRIES) {
        $last_rev = $api->get_last_revision($page_title);
        html_echo(
        "\n  <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&diff=prev&oldid="
        . $last_rev . ">diff</a>" .
        " | <a href=" . WIKI_ROOT . "?title=" . urlencode($page_title) . "&action=history>history</a>", ".");
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
  echo ("\n Done all " . (string) count($pages_in_category) . " pages linked from " . echoable($page_name) . " \n  # # # \n" . $final_edit_overview  . "\n </pre></main></body></html>");
  exit(0);
?>
