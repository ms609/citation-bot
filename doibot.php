<!DOCTYPE html>
<html lang="en" dir="ltr">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="keywords" content="User:DOI bot,Citation, citation bot,Digital object identifier,wikipedia,cite journal" />
		<link rel="apple-touch-icon" href="https://en.wikipedia.org/apple-touch-icon.png" />
		<link rel="copyright" href="https://www.gnu.org/copyleft/fdl.html" />
		<title>Citation bot: Preparing to run</title>
    <style>
      header, footer {
        font-family: 'Gill Sans', 'Gill Sans MT', Arial, 'sans serif'; 
        background-color: #eed; 
        padding: 0.5em 1em;
        margin: -9px;
        text-align: center;
        text-decoration: none;
      }
      
      header {
        border-bottom: 1px solid #335;
      }
      footer {
        border-top: 1px solid #335;
        box-shadow: 0 50vh 0 50vh #eed;
      }
      
      header a, footer a {
        text-decoration: none;
      }
    </style>
	</head>
<body class="mediawiki ns-2 ltr">
  <header>
    <p>Follow <a href="https://en.wikipedia.org/wiki/User:Citation_bot">Citation&nbsp;bot</a>&rsquo;s progress below.</p>
    <p>
      <a href="https://en.wikipedia.org/wiki/User:Citation_bot/use" target="_blank" title="Using Citation Bot">More&nbsp;details</a> | 
      <a href="https://en.wikipedia.org/wiki/Special:Contributions/Citation_bot" target="_blank" title="Recent contributions">Bot&rsquo;s&nbsp;recent&nbsp;edits</a> | 
      <a href="https://en.wikipedia.org/wiki/User_talk:Citation_bot" title="Report bugs at Wikipedia" target="_blank">Report&nbsp;bugs</a> |
      <a href="https://github.com/ms609/citation-bot" target="_blank" title="GitHub repository">Source&nbsp;code</a>
    </p>
  </header>

<pre><?php
## Set up - including DOT_DECODE array
define("HTML_OUTPUT", TRUE);
require_once("expandFns.php");
$user = isset($_REQUEST["user"]) ? $_REQUEST["user"] : NULL;
if (is_valid_user($user)) {
  echo " Activated by $user.\n";
  $edit_summary_end = " | [[User:$user|$user]]";
} else {
  $edit_summary_end = " | [[WP:UCB|User-activated]].";
}

$title = trim(ucfirst(strip_tags($_REQUEST["page"])));
if (trim($title) === '') {  // Default is to edit Wikipedia's main page if user just clicks button.  Let's not even try
   echo "\n\n No page given.  Aborting. \n\n";
   exit(0);
}
echo "\n\n Expanding '" . echoable($title) . "'; " . ($ON ? "will" : "won't") . " commit edits.";
$my_page = new Page();
$api = new WikipediaBot();
if ($my_page->get_text_from($_REQUEST["page"], $api)) {
  $text_expanded = $my_page->expand_text();
  if ($text_expanded && $ON) {
    while (!$my_page->write($api) && $attempts < 2) {
      ++$attempts;
    }
    if ($attempts < 3 ) {
      html_echo(
        " <small><a href=https://en.wikipedia.org/w/index.php?title=" . urlencode($title) . "&action=history>history</a> / "
        . "<a href=https://en.wikipedia.org/w/index.php?title=" . urlencode($title) . "&diff=prev&oldid="
        . urlencode($api->get_last_revision($title)) . ">last edit</a></small></i>\n\n<br>"
        , ".");
    } else {
      echo "\n # Failed. Text was:\n" . echoable($my_page->parsed_text());
    }
  } elseif (!$ON) {
    echo "\n # Proposed code for " . echoable($title) . ', which you have asked the bot to commit with edit summary ' . echoable($my_page->edit_summary()) . "<br><pre>";
    safely_echo($my_page->parsed_text());
    echo "</pre>";
?>
<form method="post" action="doibot.php">
  <input type="hidden" name="page" value="<?php echo $title;?>" />
  <input type="hidden" name="user" value="<?php echo $user;?>" />
  <input type="hidden" name="edit" value="on" />
  <input type="hidden" name="slow" value="<?php echo $SLOW_MODE;?>" />
  <input type=submit value="Submit edits" />
</form>
<?php
  } else {
    echo "\n # " . ($my_page->parsed_text() ? 'No changes required.' : 'Blank page') . "\n # # # ";
  }
} else {
  echo "\n Page      '" . htmlspecialchars($title) . "' not found.";
}

?>
</pre>
<footer>
<a href="../" title="Use Citation Bot again">Another&nbsp;page</a>? 
</footer>
</body></html>
