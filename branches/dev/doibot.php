<?php require_once ("expandFns.php");
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="keywords" content="User:DOI bot,Citation, citation bot,Digital object identifier,wikipedia,cite journal" />
		<link rel="apple-touch-icon" href="http://en.wikipedia.org/apple-touch-icon.png" />
		<link rel="copyright" href="http://www.gnu.org/copyleft/fdl.html" />
		<title>Citation bot: Preparing to run</title>
		<style type="text/css" media="screen, projection">/*<![CDATA[*/
			@import "http://en.wikipedia.org/skins-1.5/common/shared.css?144";
			@import "http://en.wikipedia.org/skins-1.5/monobook/main.css?144";
		/*]]>*/</style>
		<link rel="stylesheet" type="text/css" media="print" href="http://en.wikipedia.org/skins-1.5/common/commonPrint.css?144" />
		<!--[if lt IE 5.5000]><style type="text/css">@import "http://en.wikipedia.org/skins-1.5/monobook/IE50Fixes.css?144";</style><![endif]-->
		<!--[if IE 5.5000]><style type="text/css">@import "http://en.wikipedia.org/skins-1.5/monobook/IE55Fixes.css?144";</style><![endif]-->
		<!--[if IE 6]><style type="text/css">@import "http://en.wikipedia.org/skins-1.5/monobook/IE60Fixes.css?144";</style><![endif]-->
		<!--[if IE 7]><style type="text/css">@import "http://en.wikipedia.org/skins-1.5/monobook/IE70Fixes.css?144";</style><![endif]-->
		<!--[if lt IE 7]><script type="text/javascript" src="http://en.wikipedia.org/skins-1.5/common/IEFixes.js?144"></script>
		<meta http-equiv="imagetoolbar" content="no" /><![endif]-->
   	<style type="text/css">/*<![CDATA[*/ @import "http://en.wikipedia.org/w/index.php?title=MediaWiki:Gadget-navpop.css&action=raw&ctype=text/css"; /*]]>*/</style>

		<script type="text/javascript" src="http://en.wikipedia.org/w/index.php?title=-&amp;action=raw&amp;smaxage=0&amp;gen=js&amp;useskin=monobook"><!-- site js --></script>
		<style type="text/css">/*<![CDATA[*/
@import "http://en.wikipedia.org/w/index.php?title=MediaWiki:Common.css&usemsgcache=yes&action=raw&ctype=text/css&smaxage=2678400";
@import "http://en.wikipedia.org/w/index.php?title=MediaWiki:Monobook.css&usemsgcache=yes&action=raw&ctype=text/css&smaxage=2678400";
@import "http://en.wikipedia.org/w/index.php?title=-&action=raw&gen=css&maxage=2678400&smaxage=0&ts=20080516172257";
@import "http://en.wikipedia.org/w/index.php?title=User:Smith609/monobook.css&action=raw&ctype=text/css";
/*]]>*/</style>
	</head>
<body class="mediawiki ns-2 ltr">
	<div id="globalWrapper">
		<div id="column-content">
	<div id="content">
<h1 class="firstHeading">Welcome to Citation Bot</h1>
<div id="bodyContent">
	<h3 id="siteSub">Please wait while the <a href="http://en.wikipedia.org/wiki/User:Citation_bot">Citation bot</a>
		processes the page you requested.</h3>
<pre><?

## Set up - including dotDecode array
$html_output = true;
$editInitiator = "[" . revisionID() . "]";

if (is_valid_user($user)) {
  print "Activated by $user\n";
  $edit_summary_end = " | [[User:$user|$user]]";
} else {
  $edit_summary_end = " | [[WP:UCB|User-activated]].";
}

$doi_input = $_GET["doi"];
$pmid_input = str_replace(array("pmid", "PMID"), "", $_GET["pmid"]);
$pmc_input = str_replace(array("pmc", "PMC"), "", $_GET["pmc"]);

if ($pmc_input) {
  $page = "Template:Cite pmc/" . $pmc_input;
  $article_details = pmArticleDetails($pmc_input, "pmc");
  print_r($article_details);
  if ($article_details) {
    $doi_input = $article_details["doi"];
    if ($doi_input) {
      $encDoi = str_replace($dotDecode, $dotEncode, $doi_input);
      write($page, "#REDIRECT[[Template:Cite doi/$encDoi]]", "Redirecting to DOI for consistency");
      print "\n<p>Redirected to <a href='http://en.wikipedia.org/wiki/Template:Cite doi/$encDoi'>Template:Cite doi/$encDoi</a></p>";
    }	else {
      $pmid_input = $article_details["pmid"];
      write($page, "#REDIRECT[[Template:Cite pmid/$pmid_input]]", "Redirecting to PMID for consistency");
      print "\n<p>Redirected to <a href='http://en.wikipedia.org/wiki/Template:Cite pmid/$pmid_input'>Template:Cite pmid/$pmid_input</a></p>";
      $cite_doi_start_code = "{{Cite journal \n| pmid = {$pmid_input}\n}}<noinclude>{{Documentation|Template:cite_pmid/subpage}}</noinclude>";
    }
  } else {
   print ("\n<p>PMC $pmc_input not found. </p>");
   $dont_expand = true;
  }
}
if ($pmid_input) {
	$page = "Template:Cite pmid/" . str_replace($dotDecode, $dotEncode, $pmid_input);
	$pma = pmArticleDetails($pmid_input);
	$doi_input = $pma["doi"];
	if ($doi_input) {
		$encDoi = str_replace($dotDecode, $dotEncode, $doi_input);
		write($page, "#REDIRECT[[Template:Cite doi/$encDoi]]", "Redirecting to DOI for consistency");
		print "\n<p>Redirected to <a href='http://en.wikipedia.org/wiki/Template:Cite doi/$encDoi'>Template:Cite doi/$encDoi</a></p>";
	}	else {
    $cite_doi_start_code = "{{Cite journal \n| pmid = {$pmid_input}\n}}<noinclude>{{Documentation|Template:cite_pmid/subpage}}</noinclude>";
  }
}
if ($doi_input) {
	$page = "Template:Cite doi/" . str_replace($dotDecode, $dotEncode, $doi_input);
	$cite_doi_start_code = "{{Cite journal \n| doi = $doi_input \n| pmid = $pmid_input \n| pmc = $pmc_input\n}}<noinclude>{{Documentation|Template:cite_doi/subpage}}</noinclude>";
} else if (!$cite_doi_start_code) {
  $page = ucfirst(strip_tags($_REQUEST["page"]));
}

if ($cite_doi_start_code) {
  $editing_cite_doi_template = true;
  $ON = true;
}

$slowMode = $_REQUEST["slow"];

if (!$dont_expand) {
  print "Expanding '$page'; " . ($ON ? "will" : "won't") . " commit edits.";
  $my_page = new Page();
  if ($my_page->get_text_from($page) && $my_page->expand_text()) {
    while (!$my_page->write() && $attempts < 2) ++$attempts;
    if ($attempts < 3 ) echo $html_output ?
         " <small><a href=http://en.wikipedia.org/w/index.php?title=" . urlencode($page) . "&action=history>history</a> / "
         . "<a href=http://en.wikipedia.org/w/index.php?title=" . urlencode($page) . "&diff=prev&oldid="
         . getLastRev($page) . ">last edit</a></small></i>\n\n<br>"
         : ".";
    else echo "\n # Failed. Text was:\n" . $my_page->text;
  } else {
    echo "\n # " . ($my_page->text ? 'No changes required.' : 'Blank page') . "\n # # # ";
    updateBacklog($my_page->title);
  }
}
?>

End of output
   # # #
</pre>

<div class="printfooter">

						<!-- end content -->
			<div class="visualClear"></div>
		</div>
	</div>
		</div>
		<div id="column-one">
	<div id="p-cactions" class="portlet">
		<h5>Views</h5>

		<div class="pBody">
			<ul>
					 <li id="ca-nstab-user" class="selected"><a href="http://en.wikipedia.org/wiki/User:Citation_bot" title="View the user page [c]" accesskey="c">User page</a></li>
					 <li id="ca-talk" class="new"><a href="http://en.wikipedia.org/w/index.php?title=User_talk:Citation_bot" title="Discussion about the content page [t]" accesskey="t">Discussion</a></li>
					 <li id="ca-edit"><a href="http://en.wikipedia.org/w/index.php?title=User_talk:Citation_bot" title="Click here to report an error [e]" accesskey="e">Report error</a></li>
					 <li id="ca-history"><a href="http://en.wikipedia.org/wiki/Special:Contributions/Citation_bot" title="This bot's contributions [x]" accesskey="x">Contributions</a></li>
				</ul>
		</div>
	</div>
	<div class="portlet" id="p-logo">
		<a style="background-image: url(http://wiki.ts.wikimedia.org/images/wiki-en.png);" ></a>
	</div>
	<script type="text/javascript"> if (window.isMSIE55) fixalpha(); </script>
		</div><!-- end of the left (by default at least) column -->
			<div class="visualClear"></div>
			<div id="footer">
				<div id="f-poweredbyico"><a href="http://wiki.ts.wikimedia.org/view/Main_Page"><img src="http://tools.wikimedia.de/images/wikimedia-toolserver-button.png" alt="Powered by Toolserver" /></a></div>
			<ul id="f-list">
				<li id="copyright">All text is available under the terms of the <a class='internal' href="http://en.wikipedia.org/wiki/Wikipedia:Text_of_the_GNU_Free_Documentation_License" title="Wikipedia:Text of the GNU Free Documentation License">GNU Free Documentation License</a>. (See <b><a class='internal' href="http://en.wikipedia.org/wiki/Wikipedia:Copyrights" title="Wikipedia:Copyrights">Copyrights</a></b> for details.)</li>
			</ul>
		</div>
</div>
</body></html>