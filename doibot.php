<?php include("expandFns.php");
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
<body class="mediawiki ns-2 ltr page-User_DOI_bot_5andbox">
	<div id="globalWrapper">
		<div id="column-content">
	<div id="content">
<h1 class="firstHeading">Welcome to Citation Bot</h1>
<div id="bodyContent">			
	<h3 id="siteSub"><? echo restrictedDuties?"Thanks for using this bot. Please be aware that there are one or two tiny bugs that are yet to be fixed, 
		so the bot will run in 'manual mode' only.  Do carefully check that any edits it makes do not cause unintended consequences! 
		The bot has begun, so p":"P";?>lease wait patiently while the <a href="http://en.wikipedia.org/wiki/User:Citation_bot">Citation bot</a> 
		<small>(<a href="http://en.wikipedia.org/wiki/Special:Contributions/Citation_bot">contribs</a>)</small> works on the citations you requested. 
		You can follow its progress below...</h3>
<pre><?

## Set up - including dotDecode array
$htmlOutput=true;
$editInitiator = "[" . revisionID() . "]";

if ($user && getArticleId("User:$user")) {
  print "Activated by $user\n";
  $editSummaryEnd = "[[User:$user|$user]]";
} else {
  $editSummaryEnd = "User-activated.";
}


###

function nextPage() {
  return null; #Console should take care of the backlog now.
	$db = udbconnect();
	if (restrictedDuties) return false; ######## Stop bot working through backlog until systemic bugs are fixed ########
	$result = mysql_query ("SELECT page FROM citation ORDER BY fast ASC") or die(mysql_error());
	if(rand(1, 5000) == 100000)	{
		print "<p style=font-size:larger>Updating backlog...</p><p>\nSeeing what links to 'Cite Journal'...";
		$cite_journal = whatTranscludes2("Cite_journal", 0);
		print "\nand 'Citation'... ";
		$citation =  whatTranscludes2("Citation", 0);
		$pages = array_merge($cite_journal["title"], $citation["title"]);
		$ids = array_merge($cite_journal["id"], $citation["id"]);
		print "and writing to file...";
		$count = count($pages);
		for ($i=0; $i<$count; $i++){
			$result = mysql_query("SELECT page FROM citation WHERE id = {$ids[$i]}") or die (mysql_error());
			if (!mysql_fetch_row($result)) {
				mysql_query("INSERT INTO citation (id, page) VALUES ('{$ids[$i]}', '". addslashes($pages[$i]) ."')" )or die(mysql_error());
				print "<br>{$pages[$i]} @ {$ids[$i]}";
			} else print ".";
		}
		print "</p><p style='font-size:larger'>done.</p>";
	}
	$result = mysql_query("SELECT page FROM citation ORDER BY fast ASC") or die (mysql_error());
	$result = mysql_fetch_row($result);
	global $page;
	return $result[0];
}

$getDoi = $_GET["doi"];

if ($_GET["pmid"]) {
	$page = "Template:Cite pmid/" . str_replace($dotDecode, $dotEncode, $_GET["pmid"]);
	$pma = (pmArticleDetails($_GET["pmid"]));
	$getDoi=$pma["doi"];
	if ($getDoi) {
		$encDoi = str_replace($dotDecode, $dotEncode, $getDoi);
		write($page, "#REDIRECT[[Template:Cite doi/$encDoi]]", "Redirecting to DOI for consistency");
		print "<p>Redirected to <a href='http://en.wikipedia.org/wiki/Template:Cite doi/$encDoi'>Template:Cite doi/$encDoi</a></p>";
	}
	else $freshcode = "{{Cite journal \n| pmid = {$_GET["pmid"]}\n}}<noinclude>{{template doc|Template:cite_pmid/subpage}}</noinclude>";
	$citedoi = true;
} else if ($_GET["pmc"]) {
	$page = "Template:Cite pmc/" . str_replace($dotDecode, $dotEncode, $_GET["pmc"]);
	$freshcode = "{{Cite journal \n| pmc = {$_GET["pmc"]}\n}}<noinclude>{{template doc|Template:cite_doi/subpage}}</noinclude>";
	$citedoi = true;
}
if ($getDoi) {
	$page = "Template:Cite doi/" . str_replace($dotDecode, $dotEncode, $getDoi);
	$freshcode = "{{Cite journal \n| doi = $getDoi\n}}<noinclude>{{template doc|Template:cite_doi/subpage}}</noinclude>";
	$citedoi = true;
} else if (!$freshcode) {
  $page = ($_REQUEST["page"])?ucfirst($_REQUEST["page"]):nextPage();
}

################## Here we go! ######################
include("expand.php");
################# And we're back. #####################










?></pre>

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
					 <li id="ca-nstab-user" class="selected"><a href="http://en.wikipedia.org/wiki/User:DOI_bot" title="View the user page [c]" accesskey="c">User page</a></li>
					 <li id="ca-talk" class="new"><a href="http://en.wikipedia.org/w/index.php?title=User_talk:DOI_bot&amp;action=edit" title="Discussion about the content page [t]" accesskey="t">Discussion</a></li>
					 <li id="ca-edit"><a href="http://en.wikipedia.org/w/index.php?title=User:DOI_bot/bugs" title="Click here to report an error [e]" accesskey="e">Report error</a></li>
					 <li id="ca-history"><a href="http://en.wikipedia.org/wiki/Special:Contributions/DOI_bot" title="This bot's contributions [x]" accesskey="x">Contributions</a></li>
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
<script type="text/javascript">
var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
var pageTracker = _gat._getTracker("UA-4640652-1");
pageTracker._initData();
pageTracker._trackPageview();
</script>
</body></html>