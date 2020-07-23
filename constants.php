<?php
declare(strict_types=1);
// all new constant files need listed here
require_once('constants/bad_data.php');
require_once('constants/capitalization.php');
require_once('constants/math.php');
require_once('constants/mistakes.php');
require_once('constants/namespaces.php');
require_once('constants/parameters.php');  
require_once('constants/regular_expressions.php');

const PIPE_PLACEHOLDER = '# # # CITATION_BOT_PLACEHOLDER_PIPE # # #';
const TEMP_PLACEHOLDER = "# # # CITATION_BOT_PLACEHOLDER_TEMPORARY %s # # #";
const WIKI_ROOT = "https://en.wikipedia.org/w/index.php";
const API_ROOT = "https://en.wikipedia.org/w/api.php"; // wiki's API endpoint
const ZOTERO_ROOT = "https://translation-server.toolforge.org/web";

//Common replacements
const HTML_DECODE = array("[", "]", "<", ">", " ");
const HTML_ENCODE = array("&#x5B;", "&#x5D;", "&#60;", "&#62;", "+");

const HTML_DECODE_DOI = array("[", "]", "<", ">");
const HTML_ENCODE_DOI = array("&#x5B;", "&#x5D;", "&#60;", "&#62;");

const DOT_ENCODE = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29");
const DOT_DECODE = array("/", "[", "{", "}", "]", "<", ">", ";", "(", ")");

const DOI_URL_ENCODE = array("%23", "%3C", "%3E");
const DOI_URL_DECODE = array("#", "<", ">");

const DATES_WHATEVER = 0;
const DATES_MDY      = 1;
const DATES_DMY      = 2;

const CROSSREFUSERNAME = 'martins@gmail.com';
