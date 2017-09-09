<?php 
define('HOME', dirname(__FILE__) . '/');

define("editinterval", 10);
define("PIPE_PLACEHOLDER", '%%CITATION_BOT_PIPE_PLACEHOLDER%%');
define("comment_placeholder", "### Citation bot : comment placeholder %s ###");
define("to_en_dash", "--?|\&mdash;|\xe2\x80\x94|\?\?\?"); // regexp for replacing to ndashes using mb_ereg_replace
define("en_dash", "\xe2\x80\x93"); // regexp for replacing to ndashes using mb_ereg_replace
define("wikiroot", "https://en.wikipedia.org/w/index.php?");
define("isbnKey", "268OHQMW");
define("api", "https://en.wikipedia.org/w/api.php"); // wiki's API endpoint
define("bibcode_regexp", "~^(?:" . str_replace(".", "\.", implode("|", Array(
                    "http://(?:\w+.)?adsabs.harvard.edu",
                    "http://ads.ari.uni-heidelberg.de",
                    "http://ads.inasan.ru",
                    "http://ads.mao.kiev.ua",
                    "http://ads.astro.puc.cl",
                    "http://ads.on.br",
                    "http://ads.nao.ac.jp",
                    "http://ads.bao.ac.cn",
                    "http://ads.iucaa.ernet.in",
                    "http://ads.lipi.go.id",
                    "http://cdsads.u-strasbg.fr",
                    "http://esoads.eso.org",
                    "http://ukads.nottingham.ac.uk",
                    "http://www.ads.lipi.go.id",
                ))) . ")/.*(?:abs/|bibcode=|query\?|full/)([12]\d{3}[\w\d\.&]{15})~");
                
define("doiRegexp", "(10\.\d{4}(/|%2F)..([^\s\|\"\?&>]|&l?g?t;|<[^\s\|\"\?&]*>))(?=[\s\|\"\?]|</)"); //Note: if a DOI is superceded by a </span>, it will pick up this tag. Workaround: Replace </ with \s</ in string to search.
define("early", 8000);//Characters into the literated text of an article in which a DOI is considered "early".
define("siciRegExp", "~(\d{4}-\d{4})\((\d{4})(\d\d)?(\d\d)?\)(\d+):?([+\d]*)[<\[](\d+)::?\w+[>\]]2\.0\.CO;2~");

//Common replacements
define("pcDecode", array("[", "]", "<", ">"));
define("pcEncode", array("&#x5B;", "&#x5D;", "&#60;", "&#62;"));

define ("dotEncode", array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29"));
define ("dotDecode", array("/", "[", "{", "}", "]", "<", ">", ";", "(", ")"));

define("common_mistakes", array ( // Common mistakes that aren't picked up by the levenshtein approach
  "albumlink"       =>  "titlelink",
  "artist"          =>  "others",
  "authorurl"       =>  "authorlink",
  "co-author"       =>  "coauthor",
  "co-authors"      =>  "coauthors",
  "dio"             =>  "doi",
  "director"        =>  "others",
  "display-authors" =>  "displayauthors",
  "display_authors" =>  "displayauthors",
  "doi_brokendate"  =>  "doi-broken-date",
  "doi_inactivedate"=>  "doi-broken-date",
  "doi-inactive-date"   =>  "doi-broken-date",
  "ed"              =>  "editor",
  "ed2"             =>  "editor2",
  "ed3"             =>  "editor3",
  "editorlink1"     =>  "editor1-link",
  "editorlink2"     =>  "editor2-link",
  "editorlink3"     =>  "editor3-link",
  "editorlink4"     =>  "editor4-link",
  "editor1link"     =>  "editor1-link",
  "editor2link"     =>  "editor2-link",
  "editor3link"     =>  "editor3-link",
  "editor4link"     =>  "editor4-link",
  "editor-first1"   =>  "editor1-first",
  "editor-first2"   =>  "editor2-first",
  "editor-first3"   =>  "editor3-first",
  "editor-first4"   =>  "editor4-first",
  "editor-last1"    =>  "editor1-last",
  "editor-last2"    =>  "editor2-last",
  "editor-last3"    =>  "editor3-last",
  "editor-last4"    =>  "editor4-last",
  "editorn"         =>  "editor2",
  "editorn-link"    =>  "editor2-link",
  "editorn-last"    =>  "editor2-last",
  "editorn-first"   =>  "editor2-first",
  "firstn"          =>  "first2",
  "ibsn"            =>  "isbn",
  "ibsn2"           =>  "isbn",
  "lastn"           =>  "last2",
  "part"            =>  "issue",
  "no"              =>  "issue",
  "No"              =>  "issue",
  "No."             =>  "issue",
  "notestitle"      =>  "chapter",
  "nurl"            =>  "url",
  "origmonth"       =>  "month",
  "p"               =>  "page",
  "p."              =>  "page",
  "pmpmid"          =>  "pmid",
  "pp"              =>  "pages",
  "pp."             =>  "pages",
  "publisherid"     =>  "id",
  "titleyear"       =>  "origyear",
  "translator"      =>  "others",
  "translators"     =>  "others",
  "vol"             =>  "volume",
  "Vol"             =>  "volume",
  "Vol."            =>  "volume",
  "website"         =>  "url",
));


// dontCap is am array of strings that should not be capitalized in their titlecase format; 
// unCapped is their correct capitalization. Remember to enclose any word in spaces.


define("unCapped", array(' and then ', ' of ',' the ',' and ',' an ',' or ',' nor ',' but ',' is ',' if ',' then ',' else ',' when', 'at ',' from ',' by ',' on ',' off ',' for ',' in ',' over ',' to ',' into ',' with ',' U S A ',' USA ',' et ',
' ACM SIGPLAN Notices ', ' ASME AES ', ' ASME MTD ', ' BMJ ', ' CBD Ubiquitin ', ' CFSK-DT ', ' e-Neuroforum ', 
' Early Modern Japan: an Interdisciplinary Journal ', ' eLife ', ' EMBO J ', ' EMBO J. ', ' EMBO Journal ',
' EMBO Rep ', ' EMBO Rep. ', ' EMBO Reports ', ' FASEB J ', ' FASEB J. ', ' FEBS J ', ' FEBS J. ', ' FEBS Journal ',
' HOAJ biology ', ' ISRN Genetics ', ' iConference ', ' JABS : Journal of Applied Biological Sciences ',
' Molecular and Cellular Biology ', ' Ocean Science Journal : OSJ ', ' PALAIOS ', ' PLOS ONE ', ' PNAS ',
' S.A.P.I.EN.S ', ' Star Trek: The Official Monthly Magazine ', ' The EMBO Journal ', ' Time Out London ',
' z/Journal ', ' Zeitschrift für Physik A Hadrons and Nuclei ', ' Zeitschrift für Physik A: Hadrons and Nuclei '));

foreach (unCapped as $exclusion) {
  $dontCap[] = mb_convert_case($exclusion, MB_CASE_TITLE, "UTF-8");
}

define("dontCap", $dontCap);

//define("doiRegexp", "(10\.\d{4}/([^\s;\"\?&<])*)(?=[\s;\"\?&]|</)");
#define("doiRegexp", "(10\.\d{4}(/|%2F)[^\s\"\?&]*)(?=[\s\"\?&]|</)"); //Note: if a DOI is superceded by a </span>, it will pick up this tag. Workaround: Replace </ with \s</ in string to search.
?>
