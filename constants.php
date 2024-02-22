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
require_once 'constants/isbn.php';
require_once 'constants/null_doi.php';

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

const COMMONUSERNAME   = 'citations@tools.wmflabs.org';
const CROSSREFUSERNAME = 'martins@gmail.com';
const PUBMEDUSERNAME   = 'martins+pubmed@gmail.com';

const BOT_CROSSREF_USER_AGENT = "Mozilla/5.0 (compatible; Citation_bot; mailto:".CROSSREFUSERNAME."; +https://citations.toolforge.org/)";
const BOT_USER_AGENT          = "Mozilla/5.0 (compatible; Citation_bot; mailto:".COMMONUSERNAME  ."; +https://citations.toolforge.org/)";

const BOT_HTTP_TIMEOUT = 20;
const BOT_CONNECTION_TIMEOUT = 10;

function curl_limit_page_size(CurlHandle $_ch, int $_DE = 0, int $down = 0, int $_UE = 0, int $_Up = 0) : int {
	if ($down > 134217728) {  // MOST things are sane, some things are stupidly large like S2 json data or archived PDFs
	     bot_debug_log("Absurdly large curl");
	     return 1;  // If $down exceeds max-size of 128MB, returning non-0 breaks the connection!
	}
	return 0;
}
/** @param array<mixed> $ops **/
function curl_init_array(float $time, array $ops) : CurlHandle {
	$ch = curl_init();
	// 1 - Global Defaults
	curl_setopt_array($ch, [
		CURLOPT_FOLLOWLOCATION => TRUE,
		CURLOPT_BUFFERSIZE => 524288, // 512kB chunks
		CURLOPT_MAXREDIRS => 20,  // No infinite loops for us, 20 for Elsevier and Springer websites
		CURLOPT_USERAGENT => BOT_USER_AGENT,
		CURLOPT_AUTOREFERER => TRUE,
		CURLOPT_REFERER => "https://en.wikipedia.org",
		CURLOPT_COOKIESESSION => TRUE,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_HEADEROPT => CURLHEADER_UNIFIED,
		CURLOPT_PROGRESSFUNCTION => 'curl_limit_page_size',
		CURLOPT_NOPROGRESS => FALSE,
	// 2 - Default Time by ratio
		CURLOPT_TIMEOUT => BOT_HTTP_TIMEOUT * $time,
		CURLOPT_CONNECTTIMEOUT => BOT_CONNECTION_TIMEOUT * $time]);
	// 3 - Specific options and overrides of defaults
	curl_setopt_array($ch, $ops);
	return $ch;
}

function bot_curl_exec(CurlHandle $ch) : string {
  curl_setopt($ch, CURLOPT_REFERER, WIKI_ROOT . "title=" . Page::$last_title);
  return (string) @curl_exec($ch);
}
