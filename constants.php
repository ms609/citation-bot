<?php
declare(strict_types=1);
// all new constant files needed listed here
require_once 'constants/bad_data.php';
require_once 'constants/capitalization.php';
require_once 'constants/math.php';
require_once 'constants/mistakes.php';
require_once 'constants/parameters.php';  
require_once 'constants/regular_expressions.php';
require_once 'constants/italics.php';

const PIPE_PLACEHOLDER = '# # # CITATION_BOT_PLACEHOLDER_PIPE # # #';
const TEMP_PLACEHOLDER = "# # # CITATION_BOT_PLACEHOLDER_TEMPORARY %s # # #";

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

const NAME_LIST_STYLE_DEFAULT = 0;
const NAME_LIST_STYLE_AMP     = 1;
const NAME_LIST_STYLE_VANC    = 2;

const CROSSREFUSERNAME = 'martins@gmail.com';
const PUBMEDUSERNAME   = 'martins+pubmed@gmail.com';

const GET_THE_HEADERS = 1; // expects 1 in early PHP, and TRUE in newer versions

const BOT_USER_AGENT = "Mozilla/5.0 (compatible; Citation_bot; mailto:citations@tools.wmflabs.org; +https://citations.toolforge.org/)";

const BOT_HTTP_TIMEOUT = 20;
const BOT_CONNECTION_TIMEOUT = 10;

// Allow cheap journals to work
const CONTEXT_INSECURE = array(
           'ssl' => ['verify_peer' => FALSE, 'verify_peer_name' => FALSE, 'allow_self_signed' => TRUE, 'security_level' => 0, 'verify_depth' => 0],
           'http' => ['ignore_errors' => TRUE, 'max_redirects' => 40, 'timeout' => BOT_HTTP_TIMEOUT * 1.0, 'follow_location' => 1, 'header'=> ['Connection: close'], "user_agent" => BOT_USER_AGENT]
           );
const CONTEXT_INSECURE_11 = array(
           'ssl' => ['verify_peer' => FALSE, 'verify_peer_name' => FALSE, 'allow_self_signed' => TRUE, 'security_level' => 0, 'verify_depth' => 0],
           'http' => ['ignore_errors' => TRUE, 'max_redirects' => 40, 'timeout' => BOT_HTTP_TIMEOUT * 1.0, 'follow_location' => 1, 'protocol_version' => 1.1,  'header'=> ['Connection: close'], "user_agent" => BOT_USER_AGENT]
           );
  
