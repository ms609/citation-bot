<?php 
$constant_files = glob('constants/*.php');
foreach ($constant_files as $file) {
    require($file);   
}

define('HOME', dirname(__FILE__) . '/');

const PIPE_PLACEHOLDER = '# # # CITATION_BOT_PLACEHOLDER_PIPE # # #';
const TEMP_PLACEHOLDER = "# # # CITATION_BOT_PLACEHOLDER_TEMPORARY %s # # #";
const WIKI_ROOT = "https://en.wikipedia.org/w/index.php";
const API_ROOT = "https://en.wikipedia.org/w/api.php"; // wiki's API endpoint
const TOOLFORGE_IP = '208.80.155.131';
const BRACESPACE = "!BOTCODE-spaceBeforeTheBrace";

//Common replacements
const HTML_DECODE = array("[", "]", "<", ">", " ");
const HTML_ENCODE = array("&#x5B;", "&#x5D;", "&#60;", "&#62;", "+");

const DOT_ENCODE = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29");
const DOT_DECODE = array("/", "[", "{", "}", "]", "<", ">", ";", "(", ")");

const DOI_URL_ENCODE = array("%23", "%3C", "%3E");
const DOI_URL_DECODE = array("#", "<", ">");

const NON_STANDARD_WHITESPACE = array();
U+2000 	  	e2 80 80 	EN QUAD
U+2001 	  	e2 80 81 	EM QUAD
U+2002 	  	e2 80 82 	EN SPACE
U+2003 	  	e2 80 83 	EM SPACE
U+2004 	  	e2 80 84 	THREE-PER-EM SPACE
U+2005 	  	e2 80 85 	FOUR-PER-EM SPACE
U+2006 	  	e2 80 86 	SIX-PER-EM SPACE
U+2007 	  	e2 80 87 	FIGURE SPACE
U+2008 	  	e2 80 88 	PUNCTUATION SPACE
U+2009 	  	e2 80 89 	THIN SPACE
U+200A 	  	e2 80 8a 	HAIR SPACE 
