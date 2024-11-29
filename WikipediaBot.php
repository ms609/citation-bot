<?php

declare(strict_types=1);

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Request;
use MediaWiki\OAuthClient\SignatureMethod\HmacSha1;
use MediaWiki\OAuthClient\Token;

require_once 'user_messages.php';  // @codeCoverageIgnore
require_once 'constants.php';      // @codeCoverageIgnore

final class WikipediaBot {
    private Consumer $bot_consumer;
    private Token $bot_token;
    private Consumer $user_consumer;
    private Client $user_client;
    private Token $user_token;
    private static CurlHandle $ch_write;
    private static CurlHandle $ch_logout;
    private string $the_user = '';
    private static ?self $last_WikipediaBot; // For NonStandardMode()

    public static function make_ch(): void {
        static $init_done = false;
        if ($init_done) {
            return;
        }
        $init_done = true;
        // This is a little paranoid - see https://curl.se/libcurl/c/CURLOPT_FAILONERROR.html
        self::$ch_write  = bot_curl_init(1.0,
                [CURLOPT_FAILONERROR => true,
                    CURLOPT_POST => true,
                    CURLOPT_REFERER => "https://citations.toolforge.org/",
                    CURLOPT_URL => API_ROOT,
                ]);
        self::$ch_logout = bot_curl_init(1.0,
                [CURLOPT_REFERER => "https://citations.toolforge.org/", CURLOPT_FAILONERROR => true ]);
    }

    public function __construct() {
        // setup.php must already be run at this point

        $this->bot_consumer = new Consumer((string) getenv('PHP_OAUTH_CONSUMER_TOKEN'), (string) getenv('PHP_OAUTH_CONSUMER_SECRET'));
        $this->bot_token = new Token((string) getenv('PHP_OAUTH_ACCESS_TOKEN'), (string) getenv('PHP_OAUTH_ACCESS_SECRET'));
        // These are only needed if editing as a user
        $this->user_consumer = new Consumer((string) getenv('PHP_WP_OAUTH_CONSUMER'), (string) getenv('PHP_WP_OAUTH_SECRET'));
        $conf = new ClientConfig(WIKI_ROOT . '?title=Special:OAuth');
        $conf->setConsumer($this->user_consumer);
        $conf->setUserAgent(BOT_USER_AGENT);
        $this->user_client = new Client($conf);
        $this->user_token = new Token("", "");

        if (TRAVIS) {
            $this->the_user = 'Citation_bot';
            // @codeCoverageIgnoreStart
        } elseif (!HTML_OUTPUT) { // Running on the command line, and editing using main tokens
            $this->the_user = '';
        } else {
            $this->authenticate_user();
            // @codeCoverageIgnoreEnd
        }
        self::$last_WikipediaBot = $this;
    }

    public function get_the_user(): string {
        if ($this->the_user === '') {
            report_error('User Not Set');         // @codeCoverageIgnore
        }
        return $this->the_user;
    }

    public static function ret_okay(?object $response): bool { // We send back true for thing that are page specific
        if (is_null($response)) {
            report_warning('Wikipedia response was not decoded.  Will sleep and move on.');
            sleep(10);
            return false;
        }
        if (isset($response->error)) {
            $error_code = (string) @$response->error->code;
            $respone_info = (string) @$response->error->info;
            if ($error_code === 'blocked') { // Travis CI IPs are blocked, even to logged in users.
                report_error('Bot account or this IP is blocked from editing.');  // @codeCoverageIgnore
            } elseif (strpos($respone_info, 'The database has been automatically locked') !== false) {
                report_warning('Wikipedia database Locked.  Aborting changes for this page.  Will sleep and move on.');
            } elseif (strpos($respone_info, 'abusefilter-warning-predatory') !== false) {
                report_warning('Wikipedia page contains predatory references.  Aborting changes for this page.');
                return true;
            } elseif (strpos($respone_info, 'protected') !== false) {
                report_warning('Wikipedia page is protected from editing.  Aborting changes for this page.');
                return true;
            } elseif (strpos($respone_info, 'Wikipedia:Why create an account') !== false) {
                report_error('The bot is editing as you, and you have not granted that permission.  Go to ' . WIKI_ROOT . '?title=Special:OAuthManageMyGrants/update/230820 and grant Citation Bot "Edit existing pages" rights.');  // @codeCoverageIgnore
            } elseif (strpos($respone_info, 'The authorization headers in your request are not valid') !== false) {
                report_error('There is something wrong with your Oauth tokens');  // @codeCoverageIgnore
            } elseif (strpos($respone_info, 'Edit conflict') !== false) {
                report_warning('Edit Conflict while saving changes');  // @codeCoverageIgnore
                return true;  // @codeCoverageIgnore
            } elseif (strpos($respone_info, 'Invalid CSRF token') !== false) {
                report_warning('Invalid CSRF token - probably bot edit conflict with itself.  Will sleep and move on');  // @codeCoverageIgnore
            } elseif (strpos($respone_info, 'Bad title') !== false) {
                report_warning('Bad title error - You probably did a category as a page or pasted invisible characters or some other typo.  Will sleep and move on');  // @codeCoverageIgnore
            } elseif (strpos($respone_info, "The page you specified does not exist") !== false) {
                report_warning('Bad title error - This page does not exist.  Will sleep and move on');  // @codeCoverageIgnore
            } elseif (strpos($respone_info, "The page you specified doesn") !== false) {
                report_warning('Bad title error - This page does not exist.  Will sleep and move on');  // @codeCoverageIgnore  
            } else {
                $err_string = 'API call failed for unexpected reason.  Will sleep and move on: ' . echoable($respone_info);
                bot_debug_log($err_string); // Good to know about about these things
                report_warning($err_string);
            }
            sleep(10);
            return false;
        }
        return true;
    }

    /** @phpstan-impure

        @param array<string> $params */
    private function fetch(array $params, int $depth = 1): ?object {
        set_time_limit(120);
        if ($depth > 1) {
            sleep($depth+2); // @codeCoverageIgnore
        }
        if ($depth > 4) {
            return null;  // @codeCoverageIgnore
        }
        $params['format'] = 'json';

        $token = $this->bot_token;
        $consumer = $this->bot_consumer;
        if (defined('EDIT_AS_USER') && ($params["action"] === "edit")) { // @codeCoverageIgnoreStart
             $token = $this->user_token;
             $consumer = $this->user_consumer;
        }                                                                // @codeCoverageIgnoreEnd
        $request = Request::fromConsumerAndToken($consumer, $token, 'POST', API_ROOT, $params);
        $request->signRequest(new HmacSha1(), $consumer, $token);
        $authenticationHeader = $request->toHeader();

        try {
            curl_setopt_array(self::$ch_write, [
                CURLOPT_POSTFIELDS => http_build_query($params),
                CURLOPT_HTTPHEADER => [$authenticationHeader],
            ]);

            $data = @curl_exec(self::$ch_write);
            if ($data === false)
            {     // @codeCoverageIgnoreStart
                $errnoInt = curl_errno(self::$ch_write);
                $errorStr = curl_error(self::$ch_write);
                report_warning('Curl error #'.$errnoInt.' on a Wikipedia write query: '.$errorStr);
            }     // @codeCoverageIgnoreEnd
            $data = (string) $data;
            $ret = @json_decode($data);
            unset($data);
            if (($ret === null) || ($ret === false) || (isset($ret->error) && (   // @codeCoverageIgnoreStart
                (string) $ret->error->code === 'assertuserfailed' ||
                stripos((string) $ret->error->info, 'The database has been automatically locked') !== false ||
                stripos((string) $ret->error->info, 'abusefilter-warning-predatory') !== false ||
                stripos((string) $ret->error->info, 'protected') !== false ||
                stripos((string) $ret->error->info, 'Nonce already used') !== false))
            ) {
                unset($ret, $token, $consumer, $request, $authenticationHeader); // save memory during recursion
                return $this->fetch($params, $depth+1);

            }         // @codeCoverageIgnoreEnd
            return self::ret_okay($ret) ? $ret : null;
        // @codeCoverageIgnoreStart
        } catch(Exception $E) {
            report_warning("Exception caught!\n");
            report_info("Response: ". echoable($E->getMessage()));
        }
        return null;
        // @codeCoverageIgnoreEnd
    }

    /** @phpstan-impure */
    public function write_page(string $page, string $text, string $editSummary, int $lastRevId, string $startedEditing): bool {
        if (stripos($text, "CITATION_BOT_PLACEHOLDER") !== false)  {
            report_minor_error("\n ! Placeholder left escaped in text. Aborting for page " . echoable($page));  // @codeCoverageIgnore
            return false;                                                                             // @codeCoverageIgnore
        }

        $response = $this->fetch([
            'action' => 'query',
            'prop' => 'info|revisions',
            'rvprop' => 'timestamp',
            'meta' => 'tokens',
            'titles' => $page,
        ]);

        $myPage = self::response2page($response);
        if ($myPage === null) {
            return false;  // @codeCoverageIgnore
        }

        $baseTimeStamp = (string) $myPage->revisions[0]->timestamp;

        if (($lastRevId !== 0 && $myPage->lastrevid !== $lastRevId)
         || ($startedEditing !== '' && strtotime($baseTimeStamp) > strtotime($startedEditing))) {
            report_warning("Possible edit conflict detected. Aborting.");      // @codeCoverageIgnore
            return true;                                                      // @codeCoverageIgnore
        }  // This returns true so that we do not try again

        if (empty($response->query->tokens->csrftoken) || !is_string($response->query->tokens->csrftoken)) {
            report_warning('unable to get bot tokens');     // @codeCoverageIgnore
            return false;                                   // @codeCoverageIgnore
        }
        // No obvious errors; looks like we're good to go ahead and edit
        $auth_token = $response->query->tokens->csrftoken;
        if (defined('EDIT_AS_USER')) {  // @codeCoverageIgnoreStart
            $auth_token = (string) @json_decode( $this->user_client->makeOAuthCall(
                $this->user_token,
                API_ROOT . '?action=query&meta=tokens&format=json'
             ) )->query->tokens->csrftoken;
            if ($auth_token === '') {
                report_error('unable to get user tokens');
            }
        }                              // @codeCoverageIgnoreEnd
        $submit_vars = [
            "action" => "edit",
            "title" => $page,
            "text" => $text,
            "summary" => $editSummary,
            "notminor" => "1",
            "bot" => "1",
            "basetimestamp" => $baseTimeStamp,
            "starttimestamp" => $startedEditing,
            "nocreate" => "1",
            "watchlist" => "nochange",
            'token' => $auth_token,
        ];
        $result = $this->fetch($submit_vars);

        if (!self::resultsGood($result)) {
            return false;  // @codeCoverageIgnore
        }

        if (HTML_OUTPUT) {
            report_inline("\n <span style='reddish'>Written to <a href='"   // @codeCoverageIgnore
                . WIKI_ROOT . "?title=" . urlencode($myPage->title) . "'>"  // @codeCoverageIgnore
                . echoable($myPage->title) . '</a></span>');                // @codeCoverageIgnore
        } else {
            report_inline("\n Written to " . echoable($myPage->title) . ". \n");
        }
        return true;
    }

    public static function response2page(?object $response): ?object {
        if ($response === null) {
            report_warning("Write request failed");
            return null;
        }
        if (isset($response->warnings)) {
            if (isset($response->warnings->prop)) {
                report_warning(echoable((string) $response->warnings->prop->{'*'}));
                return null;
            }
            if (isset($response->warnings->info)) {
                report_warning(echoable((string) $response->warnings->info->{'*'}));
                return null;
            }
        }
        if (!isset($response->batchcomplete)) {
            report_warning("Write request triggered no response from server");
            return null;
        }

        if (!isset($response->query->pages)) {
            report_warning("Pages list is non-existent.  Aborting.");
            return null;
        }
        $myPage = self::reset($response->query->pages);

        if (!isset($myPage->lastrevid) || !isset($myPage->revisions[0]->timestamp) || !isset($myPage->title)) {
            report_warning("Page seems not to exist. Aborting.");
            return null;
        }
        if (!isset($response->query->tokens->csrftoken)) {
            report_warning("Response object lacked tokens.  Aborting. ");
            return null;
        }
        return $myPage;
    }

    public static function resultsGood(?object $result): bool {
        if (isset($result->error)) {
            report_warning("Write error: " .
                           echoable(mb_strtoupper($result->error->code)) . ": " .
                           str_replace(["You ", " have "], ["This bot ", " has "],
                           echoable((string) @$result->error->info)));
            return false;
        } elseif (isset($result->edit->captcha)) {
            report_error("Write error: We encountered a captcha, so the bot cannot be properly logged in.");  // @codeCoverageIgnore
        } elseif (empty($result->edit->result)) { // Includes results === null
            report_warning("Unhandled write error.  Please copy this output and " .
                           "<a href='https://en.wikipedia.org/wiki/User_talk:Citation_bot'>" .
                           "report a bug</a>.  There is no need to report the database being locked unless it continues to be a problem. ");
            sleep(5);
            return false;
        } elseif ($result->edit->result !== "Success") {
            report_warning('Attempt to write page returned error: ' .  echoable($result->edit->result));
            return false;
        }
        return true;
    }

    /** @return array<string> */
    public static function category_members(string $cat): array {
        $list = [];
        $vars = [
            "cmtitle" => "Category:{$cat}", // Do not urlencode.
            "action" => "query",
            "cmlimit" => "500",
            "list" => "categorymembers",
        ];

        do {
            $res = self::QueryAPI($vars);
            $res = @json_decode($res);
            if (isset($res->query->categorymembers)) {
                foreach ($res->query->categorymembers as $page) {
                    // We probably only want to visit pages in the main namespace
                    if (stripos($page->title, 'talk:') === false &&
                            stripos($page->title, 'Special:') === false &&
                            stripos($page->title, '/doc') === false &&
                            stripos($page->title, 'Template:') === false &&
                            stripos($page->title, 'Mediawiki:') === false &&
                            stripos($page->title, 'help:') === false &&
                            stripos($page->title, 'Gadget:') === false &&
                            stripos($page->title, 'Portal:') === false &&
                            stripos($page->title, 'timedtext:') === false &&
                            stripos($page->title, 'module:') === false &&
                            stripos($page->title, 'category:') === false &&
                            stripos($page->title, 'Wikipedia:') === false &&
                            stripos($page->title, 'Gadget definition:') === false &&
                            stripos($page->title, 'Topic:') === false &&
                            stripos($page->title, 'Education Program:') === false &&
                            stripos($page->title, 'Book:') === false) {
                        $list[] = $page->title;
                    }
                }
            } else {
                report_warning('Error reading API for category ' . echoable($cat) . "\n\n");   // @codeCoverageIgnore
                return [];                                                                     // @codeCoverageIgnore
            }
            $vars["cmcontinue"] = isset($res->continue) ? $res->continue->cmcontinue : false;
        } while ($vars["cmcontinue"]);
        return $list;
    }

    public static function get_last_revision(string $page): string {
        $res = self::QueryAPI([
            "action" => "query",
            "prop" => "revisions",
            "titles" => $page,
        ]);
        $res = @json_decode($res);
        if (!isset($res->query->pages)) {
            report_minor_error("Failed to get article's last revision for " . echoable($page));      // @codeCoverageIgnore
            return '';                                                                     // @codeCoverageIgnore
        }
        $page = self::reset($res->query->pages);
        return isset($page->revisions[0]->revid) ? (string) $page->revisions[0]->revid : '';
    }

    // @return -1 if page does not exist; 0 if exists and not redirect; 1 if is redirect
    public static function is_redirect(string $page): int {
        $res = self::QueryAPI([
            "action" => "query",
            "prop" => "info",
            "titles" => $page,
        ]);
        $res = @json_decode($res);
        if (!isset($res->query->pages)) {
            report_warning("Failed to get redirect status");    // @codeCoverageIgnore
            return -1;                                          // @codeCoverageIgnore
        }
        $res = self::reset($res->query->pages);
        return isset($res->missing) ? -1 : (isset($res->redirect) ? 1 : 0);
    }
    public static function redirect_target(string $page): ?string {
        $res = self::QueryAPI([
            "action" => "query",
            "redirects" => "1",
            "titles" => $page,
        ]);
        $res = @json_decode($res);
        if (!isset($res->query->redirects[0]->to)) {
            report_warning("Failed to get redirect target");     // @codeCoverageIgnore
            return null;                                         // @codeCoverageIgnore
        }
        return (string) $res->query->redirects[0]->to;
    }

    /** @param array<string> $params */
    private static function QueryAPI(array $params): string {
     try {
        $params['format'] = 'json';
        curl_setopt_array(self::$ch_logout, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_URL => API_ROOT,
        ]);

        $data = @curl_exec(self::$ch_logout);
        if ($data === false) {
            // @codeCoverageIgnoreStart
            $errnoInt = curl_errno(self::$ch_logout);
            $errorStr = curl_error(self::$ch_logout);
            report_warning('Curl error #'.$errnoInt.' on a Wikipedia API query: '.$errorStr);
        }   // @codeCoverageIgnoreEnd
        $data = (string) $data;
        if ($data === '') {
             sleep(4);                                       // @codeCoverageIgnore
             $data = (string) @curl_exec(self::$ch_logout);  // @codeCoverageIgnore
        }
        return self::ret_okay(@json_decode($data)) ? $data : '';
        // @codeCoverageIgnoreStart
     } catch(Exception $E) {
        report_warning("Exception caught!!\n");
        report_info("Response: ". echoable($E->getMessage()));
     }
     return '';
    // @codeCoverageIgnoreEnd
    }

    public static function ReadDetails(string $title): object {
        $details = self::QueryAPI([
            'action'=>'query',
            'prop'=>'info',
            'titles'=> $title,
            'curtimestamp'=>'true',
            'inprop' => 'protection',
        ]);
        return (object) @json_decode($details);
    }

    public static function get_links(string $title): string {
        return self::QueryAPI(['action' => 'parse', 'prop' => 'links', 'page' => $title]);
    }

    public static function GetAPage(string $title): string {
        curl_setopt_array(self::$ch_logout,
                                [CURLOPT_HTTPGET => true,
                                    CURLOPT_URL => WIKI_ROOT . '?' . http_build_query(['title' => $title, 'action' =>'raw',]),
                                ]);
        $text = @curl_exec(self::$ch_logout);
        if ($text === false) {
            // @codeCoverageIgnoreStart
            $errnoInt = curl_errno(self::$ch_logout);
            $errorStr = curl_error(self::$ch_logout);
            report_warning('Curl error #'.$errnoInt.' on getting Wikipedia page '.$title.': '.$errorStr);
        }   // @codeCoverageIgnoreEnd
        return (string) $text;
    }

    public static function is_valid_user(string $user): bool {
        if (!$user) {
            return false;
        }
        $query = [
            "action" => "query",
            "usprop" => "blockinfo",
            "list" => "users",
            "ususers" => $user,
        ];
        $response = self::QueryAPI($query);
        if (strpos($response, '"userid"')  === false) { // try again if weird
            sleep(5);
            $response = self::QueryAPI($query);
        }
        if (strpos($response, '"userid"')  === false) { // try yet again if weird
            sleep(10);
            $response = self::QueryAPI($query);
        }
        if ($response === '') {
            return false;  // @codeCoverageIgnore
        }
        $response = str_replace(["\r", "\n"], '', $response);  // paranoid
        if (strpos($response, '"invalid"') !== false || // IP Address and similar stuff
            strpos($response, '"blockid"') !== false || // Valid but blocked
            strpos($response, '"missing"') !== false || // No such account
            strpos($response, '"userid"')  === false) { // should actually never return false here
            return false;
        }
        return true;
    }

    public static function NonStandardMode(): bool {
        return !TRAVIS && isset(self::$last_WikipediaBot) && self::$last_WikipediaBot->get_the_user() === 'AManWithNoPlan';
    }

    private function get_the_user_internal(): string {
        return $this->the_user;
    }
    public static function GetLastUser(): string {
        if(isset(self::$last_WikipediaBot)) {
            return self::$last_WikipediaBot->get_the_user_internal();
        }
        return '';  // @codeCoverageIgnore
    }

/**
 * @codeCoverageIgnore
 */
    private function authenticate_user(): void {
        @setcookie(session_name(), session_id(), time()+(7*24*3600), "", "", true, true); // 7 days
        if (isset($_SESSION['citation_bot_user_id']) &&
            isset($_SESSION['access_key']) &&
            isset($_SESSION['access_secret']) &&
            is_string($_SESSION['citation_bot_user_id']) &&
            self::is_valid_user($_SESSION['citation_bot_user_id'])) {
            $this->the_user = $_SESSION['citation_bot_user_id'];
            $this->user_token = new Token($_SESSION['access_key'], $_SESSION['access_secret']);
            return;
        }
        if (strpos((string) @$_SERVER['REQUEST_URI'], 'automated_tools') !== false) {
            report_warning('You need to run the bot on a page normally first to get permission tokens');
            bot_html_footer();
            exit;
        }
        @session_start(); // Need write access
        unset($_SESSION['request_key'], $_SESSION['request_secret'], $_SESSION['citation_bot_user_id']); // These would be old and unusable if we are here
        if (isset($_SESSION['access_key']) && isset($_SESSION['access_secret'])) {
            try {
                $this->user_token = new Token($_SESSION['access_key'], $_SESSION['access_secret']);
                // Validate the credentials.
                $ident = $this->user_client->identify($this->user_token);
                $user = (string) $ident->username;
                if (!self::is_valid_user($user)) {
                    report_error('User is either invalid or blocked according to ' . API_ROOT . '?action=query&usprop=blockinfo&format=json&list=users&ususers=' . urlencode(str_replace(" ", "_", $user)) . '  If this is the wrong wiki (default en), then try again, and it should work.');
                }
                $this->the_user = $user;
                $_SESSION['citation_bot_user_id'] = $this->the_user;
                session_write_close(); // Done with the session
                flush();
                return;
            }
            catch (Throwable $e) {
                /** fall through */
            }
        }
        if (empty($_SERVER['REQUEST_URI'])) {
            unset($_SESSION['access_key'], $_SESSION['access_secret'], $_SESSION['citation_bot_user_id'], $_SESSION['request_key'], $_SESSION['request_secret']); // Blow everything away
            report_error('Invalid access attempt to internal API');
        } else {
            unset($_SESSION['access_key'], $_SESSION['access_secret']);
            $return = $_SERVER['REQUEST_URI'];
            unset($_SERVER['REQUEST_URI']);
            session_write_close();
            flush();
            if (mb_substr($return, 0, 1) !== '/' || preg_match('~\s+~', $return)) { // Security paranoia
                report_error('Invalid URL passes to internal API');
            }
            /** @psalm-taint-escape header */
            $return = urlencode($return);
            header("Location: authenticate.php?return=" . $return);
        }
        exit;
    }

    private static function reset(object &$obj): object { // We use old php 7 style reset, so emulate
        $arr = (array) $obj;
        return (object) reset($arr);
    }
}
