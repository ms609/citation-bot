<?php

/*
 * setup.php sets up the environment
 * Most of the page expansion depends on everything else
 */

declare(strict_types=1);

error_reporting(E_ALL);

date_default_timezone_set('UTC');

if (file_exists('git_pull.lock')) {
    sleep(5);
    echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Citation Bot: error</title></head><body><h1>GIT pull in progress - please retry again in a moment</h1></body></html>';
    exit;
}

function bot_debug_log(string $log_this): void {
    if (function_exists('echoable')) {
        // Avoid making a new huge string, so do not combine
        if (defined('WIKI_BASE')) {
            $base = WIKI_BASE;
        } else {
            $base = "  ";
        }
        @clearstatcache(); // Deal with multiple writers, but not so paranoid that we get a file lock
        // Do all at once to avoid spreading over lines in file
        file_put_contents('CodeCoverage', $base . ' :: ' . echoable(WikipediaBot::GetLastUser()) . " :: " . echoable(Page::$last_title) . " :: " . $log_this . "\n", FILE_APPEND);
    }
}

// Bot account has flags set to avoid captchas.  Having an account is not enough. https://en.wikipedia.org/wiki/Special:CentralAuth/Citation_bot
// Should add all these to index.html web interface
// Might need to translate the messages in constants/translations.php and must add to Page->edit_summary() list
if (isset($_REQUEST["wiki_base"])){
    $wiki_base = mb_trim((string) $_REQUEST["wiki_base"]);
    if (!in_array($wiki_base, ['en', 'simple', 'mk', 'ru', 'mdwiki', 'sr', 'vi'], true)) {
        echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Citation Bot: error</title></head><body><h1>Unsupported wiki requested - aborting</h1></body></html>';
        exit;
    }
} else {
    $wiki_base = 'en';
}
if ($wiki_base === 'mdwiki') {
    define('WIKI_ROOT', 'https://mdwiki.org/w/index.php');
    define('API_ROOT', 'https://mdwiki.org/w/api.php');
    /** The wiki language code. For example, en, simple, or mdwiki. Note that mdwiki is non-standard */
    define('WIKI_BASE', 'mdwiki');
    define('EDIT_AS_USER', true); // TODO - does this work?
} else {
    define('WIKI_ROOT', 'https://'. $wiki_base . '.wikipedia.org/w/index.php');
    define('API_ROOT', 'https://'. $wiki_base . '.wikipedia.org/w/api.php');
    define('WIKI_BASE', $wiki_base);
}
unset($wiki_base);

require_once __DIR__ . '/constants.php';

ini_set("user_agent", BOT_USER_AGENT);
include_once __DIR__ . '/../../vendor/autoload.php';

define("TRAVIS", (bool) getenv('CI') || defined('__PHPUNIT_PHAR__') || defined('PHPUNIT_COMPOSER_INSTALL') || (mb_strpos((string) @$_SERVER['argv'][0], 'phpunit') !== false));

define('TRUST_DOI_GOOD', true); // TODO - this a bit too trusting

if ((string) @$_REQUEST["page"] . (string) @$argv[1] === "User:AManWithNoPlan/sandbox3") { // Specific page to make sure this code path keeps working
    define('EDIT_AS_USER', true);
}

if (TRAVIS || isset($argv)) {
    define("HTML_OUTPUT", false);
} else {
    define("HTML_OUTPUT", true);
}

// This is needed because the Gadget API expects only JSON back, therefore ALL output from the citation bot is thrown away
if (mb_strpos((string) @$_SERVER['PHP_SELF'], '/gadgetapi.php') === false) {
    define("FLUSHING_OKAY", true);
} else {
    define("FLUSHING_OKAY", false);
}

if (isset($_REQUEST["slow"]) || TRAVIS || (isset($argv) && in_array('--slow', $argv, true))) {
    define("SLOW_MODE", true);
} else {
    define("SLOW_MODE", false);
}

if (isset($argv) && in_array('--savetofiles', $argv, true)) {
    define("SAVETOFILES_MODE", true);
} else {
    define("SAVETOFILES_MODE", false);
}

if (file_exists(__DIR__ . '/../env.php')) {
    // Set the environment variables with putenv(). Remember to set permissions (not readable!)
    ob_start();
    /** @psalm-suppress MissingFile */
    include_once __DIR__ . '/../env.php';
    $env_output = mb_trim(ob_get_contents());
    if ($env_output) {
        bot_debug_log("got this:\n" . $env_output);  // Something unexpected, so log it
    }
    unset($env_output);
    ob_end_clean();
}

if (!mb_internal_encoding('UTF-8') || !mb_regex_encoding('UTF-8')) { /** @phpstan-ignore-line */ /** We are very paranoid */
    echo 'Unable to set encoding';
    exit;
}

ini_set("memory_limit", "3648M"); // Use Megabytes to match memory usage check code
ini_set("pcre.backtrack_limit", "1425000000");
ini_set("pcre.recursion_limit", "425000000");
if ((isset($_REQUEST["pcre"]) && $_REQUEST["pcre"] !== '0') || (mb_strpos((string) @$_SERVER['PHP_SELF'], '/gadgetapi.php') !== false)) { // Willing to take slight performance penalty on Gadget
    ini_set("pcre.jit", "0");
}

if (isset($_REQUEST['PHP_ADSABSAPIKEY'])) {
    $key = (string) $_REQUEST['PHP_ADSABSAPIKEY'];
    $key = mb_trim($key);
    if (preg_match('~^[a-zA-Z0-9]{16,120}$~', $key)) {
        define('PHP_ADSABSAPIKEY', $key);
    } else {
        exit;
    }
} else {
    define('PHP_ADSABSAPIKEY', (string) getenv('PHP_ADSABSAPIKEY'));
}

$env_limit_action = mb_trim((string) getenv("PHP_ADSABSAPILIMITACTION"));
if ($env_limit_action !== '') {
    define("PHP_ADSABSAPILIMITACTION", $env_limit_action);
}
unset($env_limit_action);

if ((string) getenv("PHP_S2APIKEY") !== "") {
    define("HEADER_S2", [CURLOPT_HTTPHEADER => ["x-api-key: " . (string) getenv("PHP_S2APIKEY")]]);
} else {
    define("HEADER_S2", []);
}

// see https://www.ncbi.nlm.nih.gov/books/NBK25497/ for more information
// Without an API key, any site IP address posting more than 3 requests per second will receive an error message.
$nlm_tool = "WikipediaCitationBot";
$nlm_apikey = (string) getenv("NLM_APIKEY");
$nlm_email = (string) getenv("NLM_EMAIL");
if (!(mb_strpos($nlm_email, '@') > 0)) {
    $nlm_email = PUBMEDUSERNAME;
}
if (mb_strlen($nlm_apikey) < 8) {
    $nlm_apikey = "";
}
define("NLM_LOGIN", "tool=" . urlencode($nlm_tool) . "&email=" . urlencode($nlm_email) . (($nlm_apikey === "") ? "" : ("&api_key=" . urlencode($nlm_apikey))));
unset($nlm_email, $nlm_apikey, $nlm_tool);

function check_blocked(): void {
    if (!WikipediaBot::is_valid_user('Citation_bot')) {
        if (defined('EDIT_AS_USER')) {
            echo '</pre><div style="text-align:center"><h1>Citation Bot is currently blocked because of a malfunction or disagreement about its edits - so BE CAREFUL.</h1></div><pre>';
        } else {
            echo '</pre><div style="text-align:center"><h1>Citation Bot is currently blocked because of a malfunction or disagreement about its edits.</h1><br/><h1>Alternatively, the bot has not been fully enabled on this wiki yet.</h1><h2><a href="https://en.wikipedia.org/wiki/User_talk:Citation_bot" title="Join the discussion" target="_blank"  aria-label="Join the discussion (opens a new window)">Please join in the discussion</a></h2></div><footer><a href="./" title="Use Citation Bot again"> Edit another page</a>?</footer></body></html>';
            exit;
        }
    }
}

define("MAX_TRIES", 2);
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/bot_curl.php';
require_once __DIR__ . '/WikiThings.php';
require_once __DIR__ . '/user_messages.php';
require_once __DIR__ . '/NameTools.php';
require_once __DIR__ . '/WikipediaBot.php';
require_once __DIR__ . '/Parameter.php';
require_once __DIR__ . '/TextTools.php';
require_once __DIR__ . '/WebTools.php';
require_once __DIR__ . '/doiTools.php';
require_once __DIR__ . '/miscTools.php';
require_once __DIR__ . '/URLtools.php';
require_once __DIR__ . '/Template.php';
require_once __DIR__ . '/api/APIzotero.php';
require_once __DIR__ . '/api/APIieee.php';
require_once __DIR__ . '/api/APIpii.php';
require_once __DIR__ . '/api/APIdoi.php';
require_once __DIR__ . '/api/APIS2.php';
require_once __DIR__ . '/api/APIBibCode.php';
require_once __DIR__ . '/api/APIPubMed.php';
require_once __DIR__ . '/api/APIgoogle.php';
require_once __DIR__ . '/api/APIunpaywall.php';
require_once __DIR__ . '/api/APIjstor.php';
require_once __DIR__ . '/api/APIarXiv.php';
require_once __DIR__ . '/api/APIarchives.php';
require_once __DIR__ . '/Page.php';

if (isset($argv)) {
    define("MAX_PAGES", 1000000);
} else {
    define("MAX_PAGES", 2);
}

if (!TRAVIS) { // This is explicity "tested" in test suite
    Zotero::create_ch_zotero();
    WikipediaBot::make_ch();
}
