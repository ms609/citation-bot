<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="keywords" content="User:DOI bot,Citation, citation bot,Digital object identifier,wikipedia,cite journal" />
		<link rel="apple-touch-icon" href="https://en.wikipedia.org/apple-touch-icon.png" />
		<link rel="copyright" href="http://www.gnu.org/copyleft/fdl.html" />
		<title>Citation bot: Preparing to run</title>
		<style type="text/css" media="screen, projection">/*<![CDATA[*/
			@import "https://en.wikipedia.org/skins-1.5/common/shared.css?144";
			@import "https://en.wikipedia.org/skins-1.5/monobook/main.css?144";
		/*]]>*/</style>
		<link rel="stylesheet" type="text/css" media="print" href="https://en.wikipedia.org/skins-1.5/common/commonPrint.css?144" />
		<!--[if lt IE 5.5000]><style type="text/css">@import "https://en.wikipedia.org/skins-1.5/monobook/IE50Fixes.css?144";</style><![endif]-->
		<!--[if IE 5.5000]><style type="text/css">@import "https://en.wikipedia.org/skins-1.5/monobook/IE55Fixes.css?144";</style><![endif]-->
		<!--[if IE 6]><style type="text/css">@import "https://en.wikipedia.org/skins-1.5/monobook/IE60Fixes.css?144";</style><![endif]-->
		<!--[if IE 7]><style type="text/css">@import "https://en.wikipedia.org/skins-1.5/monobook/IE70Fixes.css?144";</style><![endif]-->
		<!--[if lt IE 7]><script type="text/javascript" src="https://en.wikipedia.org/skins-1.5/common/IEFixes.js?144"></script>
		<meta http-equiv="imagetoolbar" content="no" /><![endif]-->
   	<style type="text/css">/*<![CDATA[*/ @import "https://en.wikipedia.org/w/index.php?title=MediaWiki:Gadget-navpop.css&action=raw&ctype=text/css"; /*]]>*/</style>

		<script type="text/javascript" src="https://en.wikipedia.org/w/index.php?title=-&amp;action=raw&amp;smaxage=0&amp;gen=js&amp;useskin=monobook"><!-- site js --></script>
		<style type="text/css">/*<![CDATA[*/
@import "https://en.wikipedia.org/w/index.php?title=MediaWiki:Common.css&usemsgcache=yes&action=raw&ctype=text/css&smaxage=2678400";
@import "https://en.wikipedia.org/w/index.php?title=MediaWiki:Monobook.css&usemsgcache=yes&action=raw&ctype=text/css&smaxage=2678400";
@import "https://en.wikipedia.org/w/index.php?title=-&action=raw&gen=css&maxage=2678400&smaxage=0&ts=20080516172257";
@import "https://en.wikipedia.org/w/index.php?title=User:Smith609/monobook.css&action=raw&ctype=text/css";
/*]]>*/</style>
	</head>
<body class="mediawiki ns-2 ltr">
	<div id="globalWrapper">
		<div id="column-content">
      <div id="content">
        <h1 class="firstHeading">Welcome to Citation Bot</h1>
        <div id="bodyContent">
          <h3 id="siteSub">Please wait while the <a href="https://en.wikipedia.org/wiki/User:Citation_bot">Citation bot</a> processes the page you requested.</h3>
            <pre><?php
## Set up - including DOT_DECODE array
define("HTML_OUTPUT", TRUE);
require_once("expandFns.php");
require_once("login.php");
$user = isset($_REQUEST["user"]) ? $_REQUEST["user"] : NULL;
if (is_valid_user($user)) {
  print "Activated by $user\n";
  $edit_summary_end = " | [[User:$user|$user]]";
} else {
  $edit_summary_end = " | [[WP:UCB|User-activated]].";
}

$title = trim(ucfirst(strip_tags($_REQUEST["page"])));
print "\n\n Expanding '" . htmlspecialchars($title) . "'; " . ($ON ? "will" : "won't") . " commit edits.";
$my_page = new Page();
if ($my_page->get_text_from($_REQUEST["page"])) {
  $text_expanded = $my_page->expand_text();
  if ($text_expanded and $ON) {
    while (!$my_page->write() && $attempts < 2) {
      ++$attempts;
    }
    if ($attempts < 3 ) {
      echo HTML_OUTPUT ?
        " <small><a href=https://en.wikipedia.org/w/index.php?title=" . urlencode($title) . "&action=history>history</a> / "
        . "<a href=https://en.wikipedia.org/w/index.php?title=" . urlencode($title) . "&diff=prev&oldid="
        . urlencode(get_last_revision($title)) . ">last edit</a></small></i>\n\n<br>"
        : ".";
    } else {
      echo "\n # Failed. Text was:\n" . htmlspecialchars($my_page->text);
    }
  } elseif (!$ON) {
    echo "\n # Proposed code for " . htmlspecialchars($my_page->title) . ', which you have asked the bot to commit with edit summary ' . htmlspecialchars($my_page->edit_summary()) . "<br><pre>";
    echo htmlspecialchars($my_page->text);
    echo "</pre>";
?>
<form method="post" action="doibot.php">
  <input type="hidden" name="page" value="<?php echo $title;?>"></input>
  <input type="hidden" name="user" value="<?php echo $user;?>"></input>
  <input type="hidden" name="edit" value="on"></input>
  <input type="hidden" name="slow" value="<?php echo $SLOW_MODE;?>"></input>
  <input type=submit value="Submit edits"></input>
</form>
<?php
  } else {
    echo "\n # " . ($my_page->text ? 'No changes required.' : 'Blank page') . "\n # # # ";
  }
} else {
  echo "\n Page      '" . htmlspecialchars($title) . "' not found.";
}

?>

</pre>
    </div><!-- div 'bodyContent' -->
  </div><!-- div 'content' -->
  <div id="column-one">
    <div id="p-cactions" class="portlet">
      <h5>Views</h5>
      <div class="pBody">
        <ul>
           <li id="ca-nstab-user" class="selected"><a href="https://en.wikipedia.org/wiki/User:Citation_bot" title="View the user page [c]" accesskey="c">User page</a></li>
           <li id="ca-talk" class="new"><a href="https://en.wikipedia.org/w/index.php?title=User_talk:Citation_bot" title="Discussion about the content page [t]" accesskey="t">Discussion</a></li>
           <li id="ca-edit"><a href="https://en.wikipedia.org/w/index.php?title=User_talk:Citation_bot" title="Click here to report an error [e]" accesskey="e">Report error</a></li>
           <li id="ca-history"><a href="https://en.wikipedia.org/wiki/Special:Contributions/Citation_bot" title="This bot's contributions [x]" accesskey="x">Contributions</a></li>
        </ul>
      </div>
    </div>
    <div class="portlet" id="p-logo">
      <a style="background-image: url(https://wiki.ts.wikimedia.org/images/wiki-en.png);" ></a>
    </div>
	<script type="text/javascript"> if (window.isMSIE55) fixalpha(); </script>
  </div><!-- end of the left (by default at least) column -->
			<div class="visualClear"></div>
		</div>
</div>
</body></html>
