<?php
declare(strict_types=1);
// all new constant files needed listed here
require_once 'constants/bad_data.php';
require_once 'constants/capitalization.php';
require_once 'constants/math.php';
require_once 'constants/mistakes.php';
require_once 'constants/parameters.php';  
require_once 'constants/regular_expressions.php';

const PIPE_PLACEHOLDER = '# # # CITATION_BOT_PLACEHOLDER_PIPE # # #';
const TEMP_PLACEHOLDER = "# # # CITATION_BOT_PLACEHOLDER_TEMPORARY %s # # #";
const ZOTERO_ROOT = "https://translation-server.toolforge.org/web";
const CITOID_ZOTERO = "https://en.wikipedia.org/api/rest_v1/data/citation/zotero/";

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
const PUBMEDUSERNAME   = 'martins+pubmed@gmail.com';

const GET_THE_HEADERS = TRUE; // expects 1 in early PHP, and TRUE in newer versions

const BOT_USER_AGENT = "Citation_bot; citations@tools.wmflabs.org";
