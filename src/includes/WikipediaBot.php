<?php

declare(strict_types=1);

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Request;
use MediaWiki\OAuthClient\SignatureMethod\HmacSha1;
use MediaWiki\OAuthClient\Token;

require_once __DIR__ . '/user_messages.php';  // @codeCoverageIgnore
require_once __DIR__ . '/constants.php';      // @codeCoverageIgnore
require_once __DIR__ . '/PublicConfig.php';   // @codeCoverageIgnore

final class WikipediaBot {
    private Consumer $bot_consumer;
    private Token $bot_token;
    private Consumer $user_consumer;
    private Client $user_client;
    private Token $user_token;
    private static CurlHandle $ch_write;
    private static CurlHandle $ch_logout;
    private string $the_user = '';
    private static ?self $last_WikipediaBot = null;

    public static function make_ch(): void {
        static $init_done = false;
        if ($init_done) {
            return;
        }
        $init_done = true;
        $referer = public_url('/');
        // This is a little paranoid - see https://curl.se/libcurl/c/CURLOPT_FAILONERROR.html
        self::$ch_write = bot_curl_init(1.0,
                [CURLOPT_FAILONERROR => true,
                    CURLOPT_POST => true,
                    CURLOPT_REFERER => $referer,
                    CURLOPT_URL => API_ROOT,
                ]);
        bot_curl_set_max_response_bytes(self::$ch_write, 16 * 1024 * 1024);
        self::$ch_logout = bot_curl_init(1.0,
                [CURLOPT_REFERER => $referer, CURLOPT_FAILONERROR => true ]);
        bot_curl_set_max_response_bytes(self::$ch_logout, 16 * 1024 * 1024);
        unset($referer);
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

        if (CI) {
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
            sleep(run_type_mods(10, 2, 2, 1, 2));
            return false;
        }
        if (isset($response->error)) {
            $error_fields = self::mediawiki_error_fields($response->error);
            if ($error_fields === null) {
                report_warning('Wikipedia API returned a malformed error response.');
                return false;
            }
            [$error_code, $response_info] = $error_fields;
            if ($error_code === 'blocked') { // Most CI IPs are blocked, even to logged in users.
                report_error('Bot account or this IP is blocked from editing.');  // @codeCoverageIgnore
            } elseif (mb_strpos($response_info, 'The database has been automatically locked') !== false) {
                report_warning('Wikipedia database Locked.  Aborting changes for this page.  Will sleep and move on.');
            } elseif (mb_strpos($response_info, 'abusefilter-warning-predatory') !== false) {
                report_warning('Wikipedia page contains predatory references.  Aborting changes for this page.');
                return true;
            } elseif (mb_strpos($response_info, 'protected') !== false) {
                report_warning('Wikipedia page is protected from editing.  Aborting changes for this page.');
                return true;
            } elseif (mb_strpos($response_info, 'Wikipedia:Why create an account') !== false) {
                report_error('The bot is editing as you, and you have not granted that permission.  Go to ' . WIKI_ROOT . '?title=Special:OAuthManageMyGrants/update/230820 and grant Citation Bot "Edit existing pages" rights.');  // @codeCoverageIgnore
            } elseif (mb_strpos($response_info, 'The authorization headers in your request are not valid') !== false) {
                report_error('There is something wrong with your Oauth tokens');  // @codeCoverageIgnore
            } elseif (mb_strpos($response_info, 'Edit conflict') !== false) {
                report_warning('Edit Conflict while saving changes');  // @codeCoverageIgnore
                return true;  // @codeCoverageIgnore
            } elseif (mb_strpos($response_info, 'Invalid CSRF token') !== false) {
                report_warning('Invalid CSRF token - probably bot edit conflict with itself.  Will sleep and move on');  // @codeCoverageIgnore
            } elseif (mb_strpos($response_info, 'Bad title') !== false) {
                report_warning('Bad title error - You probably did a category as a page or pasted invisible characters or some other typo.  Will sleep and move on');  // @codeCoverageIgnore
            } elseif (mb_strpos($response_info, "The page you specified does not exist") !== false) {
                report_warning('Bad title error - This page does not exist.  Will sleep and move on');  // @codeCoverageIgnore
            } elseif (mb_strpos($response_info, "The page you specified doesn") !== false) {
                report_warning('Bad title error - This page does not exist.  Will sleep and move on');  // @codeCoverageIgnore
            } else {
                $err_string = 'API call failed for unexpected reason.  Will sleep and move on: ' . echoable($response_info);
                bot_debug_log($err_string); // Good to know about about these things
                report_warning($err_string);
            }
            sleep(run_type_mods(10, 2, 2, 1, 2));
            return false;
        }
        return true;
    }

    /**
     * @phpstan-impure
     * @param array<string> $params
     */
    private function fetch(array $params, int $depth = 1): ?object { // The $params array is strings only.  No bools or ints, since http_build_query() turns everything into strings
        set_time_limit(120);
        if ($depth > 4) {
            return null;  // @codeCoverageIgnore
        }
        if ($depth > 1) {
            sleep($depth + 2); // @codeCoverageIgnore
        }
        $params['format'] = 'json';

        try {
            $token = $this->bot_token;
            $consumer = $this->bot_consumer;
            if (defined('EDIT_AS_USER')) { // @codeCoverageIgnoreStart
                 $token = $this->user_token;
                 $consumer = $this->user_consumer;
            }                                                                // @codeCoverageIgnoreEnd
            $request = Request::fromConsumerAndToken($consumer, $token, 'POST', API_ROOT, $params);
            $request->signRequest(new HmacSha1(), $consumer, $token);
            $authenticationHeader = $request->toHeader();

            curl_setopt_array(self::$ch_write, [
                CURLOPT_POSTFIELDS => http_build_query($params),
                CURLOPT_HTTPHEADER => [$authenticationHeader],
            ]);

            $data = bot_curl_exec_withFalse(self::$ch_write);
            if ($data === false) {     // @codeCoverageIgnoreStart
                $errnoInt = curl_errno(self::$ch_write);
                $errorStr = curl_error(self::$ch_write);
                report_warning('Curl error #' . $errnoInt . ' on a Wikipedia write query: ' . $errorStr);
            }     // @codeCoverageIgnoreEnd
            $data = (string) $data;
            $ret = @json_decode($data);
            unset($data);
            if (self::fetch_response_is_retryable($ret)) {
                unset($ret, $token, $consumer, $request, $authenticationHeader); // save memory during recursion
                return $this->fetch($params, $depth + 1);

            }         // @codeCoverageIgnoreEnd
            return self::ret_okay($ret) ? $ret : null;
            // @codeCoverageIgnoreStart
        } catch (Throwable $E) {
            bot_debug_log('Wikipedia write API failure: ' . $E::class . ': ' . $E->getMessage());
            report_warning("Wikipedia write API request failed; continuing.\n");
            report_info("Response: " . echoable($E->getMessage()));
        }
        return null;
        // @codeCoverageIgnoreEnd
    }

    /** @phpstan-impure */
    public function write_page(string $page, string $text, string $editSummary, int $lastRevId, string $startedEditing): bool {
        if (mb_stripos($text, "CITATION_BOT_PLACEHOLDER") !== false) {
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

    public static function response2page(?object $response): ?stdClass {
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

        if (
            !isset($response->query) ||
            !is_object($response->query) ||
            !isset($response->query->pages) ||
            !is_object($response->query->pages)
        ) {
            report_warning("Pages list is non-existent.  Aborting.");
            return null;
        }
        $myPage = self::first_page_from_response($response);
        if ($myPage === null) {
            report_warning("Pages list was ambiguous or malformed.  Aborting.");
            return null;
        }

        if (
            !isset($myPage->lastrevid) ||
            !is_scalar($myPage->lastrevid) ||
            !isset($myPage->title) ||
            !is_scalar($myPage->title) ||
            !isset($myPage->revisions) ||
            !is_array($myPage->revisions) ||
            !isset($myPage->revisions[0]) ||
            !is_object($myPage->revisions[0]) ||
            !isset($myPage->revisions[0]->timestamp) ||
            !is_scalar($myPage->revisions[0]->timestamp)
        ) {
            report_warning("Page seems not to exist. Aborting.");
            return null;
        }
        if (
            !isset($response->query->tokens) ||
            !is_object($response->query->tokens) ||
            !isset($response->query->tokens->csrftoken) ||
            !is_scalar($response->query->tokens->csrftoken)
        ) {
            report_warning("Response object lacked tokens.  Aborting. ");
            return null;
        }
        return $myPage;
    }

    public static function resultsGood(?object $result): bool {
        if ($result === null) {
            report_warning("Unhandled write error. No response was returned.");
            return false;
        }
        if (isset($result->error)) {
            if (!is_object($result->error)) {
                report_warning("Write error response was malformed.");
                return false;
            }
            $code = $result->error->code ?? '';
            $info = $result->error->info ?? '';
            if (!is_scalar($code) || !is_scalar($info)) {
                report_warning("Write error response was malformed.");
                return false;
            }
            report_warning("Write error: " .
                           echoable(mb_strtoupper((string) $code)) . ": " .
                           str_replace(["You ", " have "], ["This bot ", " has "],
                           echoable((string) $info)));
            return false;
        }
        if (!isset($result->edit) || !is_object($result->edit)) {
            report_warning("Unhandled write error. Write response was malformed.");
            return false;
        }
        if (isset($result->edit->captcha)) {
            report_error("Write error: We encountered a captcha, so the bot cannot be properly logged in.");  // @codeCoverageIgnore
        }
        if (!isset($result->edit->result) || !is_string($result->edit->result) || $result->edit->result === '') {
            report_warning("Unhandled write error.  Please copy this output and " .
                           "<a href='https://en.wikipedia.org/wiki/User_talk:Citation_bot'>" .
                           "report a bug</a>.  There is no need to report the database being locked unless it continues to be a problem. ");
            sleep(5);
            return false;
        }
        if ($result->edit->result !== "Success") {
            report_warning('Attempt to write page returned error: ' . echoable($result->edit->result));
            return false;
        }
        return true;
    }

    /**
     * Extract usable category-member titles from a decoded MediaWiki response.
     *
     * @return array<string>|null
     */
    public static function category_member_titles_from_response(mixed $response): ?array {
        if (
            !is_object($response) ||
            !isset($response->query) ||
            !is_object($response->query) ||
            !isset($response->query->categorymembers) ||
            !is_array($response->query->categorymembers)
        ) {
            return null;
        }

        $list = [];
        foreach ($response->query->categorymembers as $page) {
            if (!is_object($page) || !isset($page->title) || !is_string($page->title)) {
                continue;
            }
            $title = $page->title;
            if (mb_stripos($title, 'talk:') === false &&
                mb_stripos($title, 'Special:') === false &&
                mb_stripos($title, '/doc') === false &&
                mb_stripos($title, 'Template:') === false &&
                mb_stripos($title, 'Mediawiki:') === false &&
                mb_stripos($title, 'help:') === false &&
                mb_stripos($title, 'Gadget:') === false &&
                mb_stripos($title, 'Portal:') === false &&
                mb_stripos($title, 'timedtext:') === false &&
                mb_stripos($title, 'module:') === false &&
                mb_stripos($title, 'category:') === false &&
                mb_stripos($title, 'Wikipedia:') === false &&
                mb_stripos($title, 'Gadget definition:') === false &&
                mb_stripos($title, 'Topic:') === false &&
                mb_stripos($title, 'Education Program:') === false &&
                mb_stripos($title, 'Book:') === false) {
                $list[] = $title;
            }
        }
        return $list;
    }

    /**
     * Normalize existing article links from a MediaWiki parse response.
     *
     * @return array<array{ns: int, title: string}>|null
     */
    public static function parse_links_response(string $json): ?array {
        $response = json_decode($json, true);
        if (!is_array($response) || !isset($response['parse']) || !is_array($response['parse']) ||
            !isset($response['parse']['links']) || !is_array($response['parse']['links'])) {
            return null;
        }

        $links = [];
        foreach ($response['parse']['links'] as $link) {
            if (!is_array($link) || !array_key_exists('exists', $link) ||
                !isset($link['ns']) || !is_int($link['ns']) ||
                !isset($link['*']) || !is_string($link['*']) || $link['*'] === '') {
                continue;
            }
            $links[] = ['ns' => $link['ns'], 'title' => $link['*']];
        }
        return $links;
    }

    /** @return array{0: string, 1: string}|null */
    public static function mediawiki_error_fields(mixed $error): ?array {
        if (!is_object($error)) {
            return null;
        }
        $code = $error->code ?? '';
        $info = $error->info ?? '';
        if (!is_scalar($code) || !is_scalar($info)) {
            return null;
        }
        return [(string) $code, (string) $info];
    }

    public static function first_page_from_response(mixed $response): ?stdClass {
        if (
            !is_object($response) ||
            !isset($response->query) ||
            !is_object($response->query) ||
            !isset($response->query->pages) ||
            !is_object($response->query->pages)
        ) {
            return null;
        }
        $pages = array_values((array) $response->query->pages);
        if (count($pages) !== 1 || !is_object($pages[0])) {
            return null;
        }
        return (object) (array) $pages[0];
    }

    public static function redirect_target_from_response(mixed $response): ?string {
        if (
            !is_object($response) ||
            !isset($response->query) ||
            !is_object($response->query) ||
            !isset($response->query->redirects) ||
            !is_array($response->query->redirects) ||
            !isset($response->query->redirects[0]) ||
            !is_object($response->query->redirects[0]) ||
            !isset($response->query->redirects[0]->to) ||
            !is_string($response->query->redirects[0]->to) ||
            $response->query->redirects[0]->to === ''
        ) {
            return null;
        }
        return $response->query->redirects[0]->to;
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
            $res = self::query_api($vars);
            $res = @json_decode($res);
            $titles = self::category_member_titles_from_response($res);
            if ($titles === null) {
                report_warning('Error reading API for category ' . echoable($cat) . "\n\n");   // @codeCoverageIgnore
                return [];                                                                     // @codeCoverageIgnore
            }
            array_push($list, ...$titles);
            $continue = $res->continue->cmcontinue ?? null;
            $vars["cmcontinue"] = is_string($continue) && $continue !== '' ? $continue : false;
        } while ($vars["cmcontinue"]);
        return $list;
    }

    public static function get_last_revision(string $page): string {
        $res = self::query_api([
            "action" => "query",
            "prop" => "revisions",
            "titles" => $page,
        ]);
        $res = @json_decode($res);
        $page_object = self::first_page_from_response($res);
        if ($page_object === null) {
            report_minor_error("Failed to get article's last revision for " . echoable($page));      // @codeCoverageIgnore
            return '';                                                                     // @codeCoverageIgnore
        }
        if (!isset($page_object->revisions) || !is_array($page_object->revisions) ||
            !isset($page_object->revisions[0]) || !is_object($page_object->revisions[0]) ||
            !isset($page_object->revisions[0]->revid) || !is_scalar($page_object->revisions[0]->revid)) {
            return '';
        }
        return (string) $page_object->revisions[0]->revid;
    }

    /** @return int -1 if page does not exist; 0 if exists and not redirect; 1 if is redirect */
    public static function is_redirect(string $page): int {
        $res = self::query_api([
            "action" => "query",
            "prop" => "info",
            "titles" => $page,
        ]);
        $res = @json_decode($res);
        if (!isset($res->query->pages)) {
            sleep(5);
            $res = self::query_api([
                "action" => "query",
                "prop" => "info",
                "titles" => $page,
            ]);
            $res = @json_decode($res);
        }
        $page_object = self::first_page_from_response($res);
        if ($page_object === null) {
            report_warning("Failed to get redirect status");
            return -2;
        }
        return isset($page_object->missing) ? -1 : (isset($page_object->redirect) ? 1 : 0);
    }

    public static function redirect_target(string $page): ?string {
        $res = self::query_api([
            "action" => "query",
            "redirects" => "1",
            "titles" => $page,
        ]);
        $res = @json_decode($res);
        $target = self::redirect_target_from_response($res);
        if ($target === null) {
            report_warning("Failed to get redirect target");     // @codeCoverageIgnore
            return null;                                         // @codeCoverageIgnore
        }
        return $target;
    }

    /** @param array<string> $params */
    private static function query_api(array $params): string {
        try {
            $params['format'] = 'json';
            /** @psalm-suppress UnnecessaryVarAnnotation */
            /** @var non-empty-string $api_root */
            $api_root = API_ROOT;
            curl_setopt_array(self::$ch_logout, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_URL => $api_root,
            ]);

            $data = bot_curl_exec_withFalse(self::$ch_logout);
            if ($data === false) {
                // @codeCoverageIgnoreStart
                $errnoInt = curl_errno(self::$ch_logout);
                $errorStr = curl_error(self::$ch_logout);
                report_warning('Curl error #' . $errnoInt . ' on a Wikipedia API query: ' . $errorStr);
            }   // @codeCoverageIgnoreEnd
            $data = (string) $data;
            if ($data === '') {
                sleep(4);                                       // @codeCoverageIgnore
                $data = bot_curl_exec(self::$ch_logout);  // @codeCoverageIgnore
            }
            return self::ret_okay(@json_decode($data)) ? $data : '';
            // @codeCoverageIgnoreStart
        } catch (Throwable $E) {
            bot_debug_log('Wikipedia read API failure: ' . $E::class . ': ' . $E->getMessage());
            report_warning("Wikipedia read API request failed; continuing.\n");
            report_info("Response: " . echoable($E->getMessage()));
        }
        return '';
        // @codeCoverageIgnoreEnd
    }

    public static function read_details(string $title): object {
        $details = self::query_api([
            'action' => 'query',
            'prop' => 'info',
            'titles' => $title,
            'curtimestamp' => 'true',
            'inprop' => 'protection',
        ]);
        return (object) @json_decode($details);
    }

    public static function get_links(string $title): string {
        return self::query_api(['action' => 'parse', 'prop' => 'links', 'page' => $title]);
    }

    public static function get_a_page(string $title): string {
        curl_setopt_array(self::$ch_logout,
                                [CURLOPT_HTTPGET => true,
                                    CURLOPT_URL => WIKI_ROOT . '?' . http_build_query(['title' => $title, 'action' => 'raw',]),
                                ]);
        $text = bot_curl_exec_withFalse(self::$ch_logout);
        if ($text === false) {
            // @codeCoverageIgnoreStart
            $errnoInt = curl_errno(self::$ch_logout);
            $errorStr = curl_error(self::$ch_logout);
            report_warning('Curl error #' . $errnoInt . ' on getting Wikipedia page ' . $title . ': ' . $errorStr);
        }   // @codeCoverageIgnoreEnd
        return (string) $text;
    }

    public static function valid_user_from_response(string $response): ?bool {
        $decoded = @json_decode($response);
        if (
            !is_object($decoded) ||
            !isset($decoded->query) ||
            !is_object($decoded->query) ||
            !isset($decoded->query->users) ||
            !is_array($decoded->query->users) ||
            count($decoded->query->users) !== 1 ||
            !isset($decoded->query->users[0]) ||
            !is_object($decoded->query->users[0])
        ) {
            return null;
        }

        $user = $decoded->query->users[0];
        if (property_exists($user, 'invalid') || property_exists($user, 'missing')) {
            return false;
        }
        if (!isset($user->userid) || !is_int($user->userid) || $user->userid <= 0) {
            return null;
        }
        if (property_exists($user, 'blockid') && !property_exists($user, 'blockpartial')) {
            return false;
        }
        return true;
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
        $response = self::query_api($query);
        $valid = self::valid_user_from_response($response);
        if ($valid === null) { // try again if weird
            sleep(5);
            $response = self::query_api($query);
            $valid = self::valid_user_from_response($response);
        }
        if ($valid === null) { // try again if weird
            sleep(10);
            $response = self::query_api($query);
            $valid = self::valid_user_from_response($response);
        }
        return $valid ?? false;
    }

    public static function non_standard_mode(): bool {
        return isset(self::$last_WikipediaBot) && self::$last_WikipediaBot->get_the_user() === 'AManWithNoPlan';
    }

    private function get_the_user_internal(): string {
        return $this->the_user;
    }

    public static function get_last_user(): string {
        if (isset(self::$last_WikipediaBot)) {
            return self::$last_WikipediaBot->get_the_user_internal();
        }
        return '';  // @codeCoverageIgnore
    }

    public static function is_automated_tools_request(?string $request_uri): bool {
        return $request_uri !== null && mb_strpos($request_uri, 'automated_tools') !== false;
    }

    /**
     * @codeCoverageIgnore
     */
    private function authenticate_user(): void {
        $session_name = session_name();
        $session_id = session_id();
        if ($session_name !== false && $session_id !== false) {
            $cookie_params = session_get_cookie_params();
            $session_options = public_session_start_options();
            @setcookie($session_name, $session_id, [
                'expires' => time() + (7 * 24 * 3600),
                'path' => $cookie_params['path'],
                'domain' => $cookie_params['domain'],
                'secure' => (bool) $session_options['cookie_secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]); // 7 days
        }
        if (isset($_SESSION['citation_bot_user_id']) &&
            isset($_SESSION['access_key']) &&
            isset($_SESSION['access_secret']) &&
            is_string($_SESSION['citation_bot_user_id']) &&
            self::is_valid_user($_SESSION['citation_bot_user_id'])) {
            $this->the_user = $_SESSION['citation_bot_user_id'];
            $this->user_token = new Token($_SESSION['access_key'], $_SESSION['access_secret']);
            return;
        }
        $request_uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : null;
        if (self::is_automated_tools_request($request_uri)) {
            report_warning('You need to run the bot on a page normally first to get permission tokens');
            bot_html_footer();
            exit(0);
        }
        @session_start(public_session_start_options()); // Need write access
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
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
                session_regenerate_id(true);
                session_write_close(); // Done with the session
                return;
            } catch (Throwable) {
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
            if (mb_substr($return, 0, 1) !== '/' || mb_substr($return, 0, 2) === '//' || preg_match('~\s+~', $return)) { // Security paranoia
                report_error('Invalid URL passes to internal API');
            }
            /** @psalm-taint-escape header */
            $authentication_url = oauth_authentication_url($return);
            header("Location: " . $authentication_url);
        }
        exit(0);
    }

    public static function fetch_response_is_retryable(mixed $response): bool {
        if (!is_object($response)) {
            return true;
        }
        if (!isset($response->error)) {
            return false;
        }
        $error_fields = self::mediawiki_error_fields($response->error);
        if ($error_fields === null) {
            return true;
        }
        [$error_code, $response_info] = $error_fields;

        if (in_array($error_code, ['maxlag', 'ratelimited', 'readonly'], true)) {
            return true;
        }
        if (mb_strpos($response_info, 'The database has been automatically locked') !== false) {
            return true;
        }
        if (mb_strpos($response_info, 'Nonce already used') !== false) {
            return true;
        }

        return false;
    }
}
