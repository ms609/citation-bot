<?php

declare(strict_types=1);

// @codeCoverageIgnoreStart
// all new constant files needed listed here
require_once 'constants/bad_data.php';
require_once 'constants/capitalization.php';
require_once 'constants/math.php';
require_once 'constants/mistakes.php';
require_once 'constants/parameters.php';
require_once 'constants/regular_expressions.php';
require_once 'constants/italics.php';
require_once 'constants/isbn.php';
require_once 'constants/null_good_doi.php';
require_once 'constants/null_bad_doi.php';
require_once 'constants/translations.php';
// @codeCoverageIgnoreEnd

const PIPE_PLACEHOLDER = '# # # CITATION_BOT_PLACEHOLDER_PIPE # # #';
const TEMP_PLACEHOLDER = "# # # CITATION_BOT_PLACEHOLDER_TEMPORARY %s # # #";

//Common replacements
const HTML_DECODE = ["[", "]", "<", ">", " "];
const HTML_ENCODE = ["&#x5B;", "&#x5D;", "&#60;", "&#62;", "+"];

const HTML_DECODE_DOI = ["[", "]", "<", ">"];
const HTML_ENCODE_DOI = ["&#x5B;", "&#x5D;", "&#60;", "&#62;"];

const DOT_ENCODE = [".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29"];
const DOT_DECODE = ["/", "[", "{", "}", "]", "<", ">", ";", "(", ")"];

const DOI_URL_ENCODE = ["%23", "%3C", "%3E"];
const DOI_URL_DECODE = ["#", "<", ">"];

const ENGLISH_WIKI = ['en', 'simple', 'mdwiki'];

enum DateStyle {
    case DATES_WHATEVER;
    case DATES_MDY;
    case DATES_DMY;
    case DATES_ISO;
}

enum VancStyle
{
    case NAME_LIST_STYLE_DEFAULT;
    case NAME_LIST_STYLE_AMP;
    case NAME_LIST_STYLE_VANC;
}

const COMMONUSERNAME   = 'citations@tools.wmflabs.org';
const CROSSREFUSERNAME = 'martins@gmail.com';
const PUBMEDUSERNAME   = 'martins+pubmed@gmail.com';

const BOT_CROSSREF_USER_AGENT = "Mozilla/5.0 (compatible; Citation_bot; mailto:".CROSSREFUSERNAME."; +https://citations.toolforge.org/)";
const BOT_USER_AGENT          = "Mozilla/5.0 (compatible; Citation_bot; mailto:".COMMONUSERNAME  ."; +https://citations.toolforge.org/)";

const BOT_HTTP_TIMEOUT = 20;
const BOT_CONNECTION_TIMEOUT = 10;

function curl_limit_page_size(CurlHandle $_ch, int $_DE = 0, int $down = 0, int $_UE = 0, int $_Up = 0): int {
    // MOST things are sane, some things are stupidly large like S2 json data or archived PDFs
    // If $down exceeds max-size of 128MB, returning non-0 breaks the connection!
    if ($down > 134217728) {
         bot_debug_log("Absurdly large curl");
         return 1;
    }
    return 0;
}
/** @param array<int, int|string|bool|array<int, string>> $ops */
function bot_curl_init(float $time, array $ops): CurlHandle {
    $ch = curl_init();
    // 1 - Global Defaults
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_BUFFERSIZE => 524288, // 512kB chunks
        CURLOPT_MAXREDIRS => 20,  // No infinite loops for us, 20 for Elsevier and Springer websites
        CURLOPT_USERAGENT => BOT_USER_AGENT,
        CURLOPT_AUTOREFERER => "1",
        CURLOPT_REFERER => "https://en.wikipedia.org",
        CURLOPT_COOKIESESSION => "1",
        CURLOPT_RETURNTRANSFER => "1",
        CURLOPT_HEADEROPT => CURLHEADER_UNIFIED,
        CURLOPT_PROGRESSFUNCTION => 'curl_limit_page_size',
        CURLOPT_NOPROGRESS => "0",
        CURLOPT_COOKIEJAR => 'cookie.txt', // Needed for proquest
        CURLOPT_COOKIEFILE => 'cookie.txt', // Needed for proquest
        // 2 - Default Time by ratio
        CURLOPT_TIMEOUT => BOT_HTTP_TIMEOUT * $time,
        CURLOPT_CONNECTTIMEOUT => BOT_CONNECTION_TIMEOUT * $time,
    ]);
    // 3 - Specific options and overrides of defaults
    curl_setopt_array($ch, $ops);
    return $ch;
}

function bot_curl_exec(CurlHandle $ch): string {
    curl_setopt($ch, CURLOPT_REFERER, WIKI_ROOT . "title=" . Page::$last_title);
    return (string) @curl_exec($ch);
}
