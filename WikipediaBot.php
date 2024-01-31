<?php
declare(strict_types=1);

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use MediaWiki\OAuthClient\Request;
use MediaWiki\OAuthClient\SignatureMethod\HmacSha1;

require_once 'user_messages.php';  // @codeCoverageIgnore
require_once 'constants.php';      // @codeCoverageIgnore

final class WikipediaBot {

  private Consumer $bot_consumer;
  private Token $bot_token;
  private Consumer $user_consumer;
  private Client $user_client;
  private Token $user_token;
  private static CurlHandle $ch_login;
  private static CurlHandle $ch_logout;
  private string $the_user = '';
  private static ?self $last_WikipediaBot; // For NonStandardMode()

  public static function make_ch() : void {
    static $init_done = FALSE;
    if ($init_done) return;
    $init_done = TRUE;
    // This is a little paranoid - see https://curl.se/libcurl/c/CURLOPT_FAILONERROR.html
    self::$ch_login  = curl_init_array(1.0, [CURLOPT_FAILONERROR => TRUE ]); 
    self::$ch_logout = curl_init_array(1.0, [CURLOPT_FAILONERROR => TRUE ]);
  }

  function __construct() {
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

    /** @psalm-suppress RedundantCondition */  /* PSALM thinks TRAVIS cannot be FALSE */
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

  public function get_the_user() : string {
    if ($this->the_user === '') {
      report_error('User Not Set');         // @codeCoverageIgnore
    }
    return $this->the_user;
  }

  public static function ret_okay(?object $response) : bool { // We send back TRUE for thing that are page specific
    if (is_null($response)) {
      report_warning('Wikipedia response was not decoded.  Will sleep and move on.');
      sleep(10);
      return FALSE;
    }
    if (isset($response->error)) {
      if ((string) @$response->error->code === 'blocked') { // Travis CI IPs are blocked, even to logged in users.
	report_error('Bot account or this IP is blocked from editing.');  // @codeCoverageIgnore
      } elseif (strpos((string) @$response->error->info, 'The database has been automatically locked') !== FALSE) {
	report_warning('Wikipedia database Locked.  Aborting changes for this page.  Will sleep and move on.');
      } elseif (strpos((string) @$response->error->info, 'abusefilter-warning-predatory') !== FALSE) {
	report_warning('Wikipedia page contains predatory references.  Aborting changes for this page.');
	return TRUE;
      } elseif (strpos((string) @$response->error->info, 'protected') !== FALSE) {
	report_warning('Wikipedia page is protected from editing.  Aborting changes for this page.');
	return TRUE;
      } elseif (strpos((string) @$response->error->info, 'Wikipedia:Why create an account') !== FALSE) {
	report_error('The bot is editing as you, and you have not granted that permission.  Go to ' . WIKI_ROOT . '?title=Special:OAuthManageMyGrants/update/230820 and grant Citation Bot "Edit existing pages" rights.');  // @codeCoverageIgnore
      } elseif (strpos((string) @$response->error->info, 'The authorization headers in your request are not valid') !== FALSE) {
	report_error('There is something wrong with your Oauth tokens');  // @codeCoverageIgnore
      } else {
	bot_debug_log(html_entity_decode((string) @$response->error->info)); // Good to know about about these things
	report_warning('API call failed: ' . echoable((string) @$response->error->info) . '.  Will sleep and move on.');
      }
      sleep (10);
      return FALSE;
    }
    return TRUE;
  }

  /** @phpstan-impure
      @param array<mixed> $params **/
  private function fetch(array $params, int $depth = 1) : ?object {
    set_time_limit(120);
    if ($depth > 1) sleep($depth+2);
    if ($depth > 4) return NULL;
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
	  curl_setopt_array(self::$ch_login, [
	    CURLOPT_POST => TRUE,
	    CURLOPT_POSTFIELDS => http_build_query($params),
	    CURLOPT_HTTPHEADER => [$authenticationHeader],
	    CURLOPT_URL => API_ROOT
	  ]);

      $data = (string) @curl_exec(self::$ch_login);
      $ret = @json_decode($data);
      if (($ret === NULL) || ($ret === FALSE) || (isset($ret->error) && (   // @codeCoverageIgnoreStart
	(string) $ret->error->code === 'assertuserfailed' ||
	stripos((string) $ret->error->info, 'The database has been automatically locked') !== FALSE ||
	stripos((string) $ret->error->info, 'abusefilter-warning-predatory') !== FALSE ||
	stripos((string) $ret->error->info, 'protected') !== FALSE ||
	stripos((string) $ret->error->info, 'Nonce already used') !== FALSE))
      ) {
	unset($data, $ret, $token, $consumer, $request, $authenticationHeader); // save memory during recursion
	return $this->fetch($params, $depth+1);

      }         // @codeCoverageIgnoreEnd
      return (self::ret_okay($ret)) ? $ret : NULL;
    // @codeCoverageIgnoreStart
    } catch(Exception $E) {
      report_warning("Exception caught!\n");
      report_info("Response: ". echoable($E->getMessage()));
    }
    return NULL;
    // @codeCoverageIgnoreEnd
  }

  /** @phpstan-impure **/
  public function write_page(string $page, string $text, string $editSummary, int $lastRevId, string $startedEditing) : bool {
    if (stripos($text, "CITATION_BOT_PLACEHOLDER") !== FALSE)  {
      report_minor_error("\n ! Placeholder left escaped in text. Aborting for page " . echoable($page));  // @codeCoverageIgnore
      return FALSE;                                                                             // @codeCoverageIgnore
    }

    $response = $this->fetch([
	    'action' => 'query',
	    'prop' => 'info|revisions',
	    'rvprop' => 'timestamp',
	    'meta' => 'tokens',
	    'titles' => $page
	  ]);

    $myPage = self::response2page($response);
    if ($myPage === NULL) return FALSE;

    $baseTimeStamp = $myPage->revisions[0]->timestamp;

    if (($lastRevId !== 0 && $myPage->lastrevid !== $lastRevId)
     || ($startedEditing !== '' && strtotime($baseTimeStamp) > strtotime($startedEditing))) {
      report_warning("Possible edit conflict detected. Aborting.");      // @codeCoverageIgnore
      return TRUE;                                                      // @codeCoverageIgnore
    }  // This returns true so that we do not try again

    if (empty($response->query->tokens->csrftoken) || !is_string($response->query->tokens->csrftoken)) {
	report_warning('unable to get bot tokens');     // @codeCoverageIgnore
	return FALSE;                                   // @codeCoverageIgnore
    }
    // No obvious errors; looks like we're good to go ahead and edit
    $auth_token = $response->query->tokens->csrftoken;
    if (defined('EDIT_AS_USER')) {  // @codeCoverageIgnoreStart
      $auth_token = @json_decode( $this->user_client->makeOAuthCall(
	$this->user_token,
       API_ROOT . '?action=query&meta=tokens&format=json'
       ) )->query->tokens->csrftoken;
      if ($auth_token === NULL) {
	report_error('unable to get user tokens');
      }
    }                              // @codeCoverageIgnoreEnd
    $submit_vars = array(
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
    );
    $result = $this->fetch($submit_vars);

    if (!self::resultsGood($result)) return FALSE;

    if (HTML_OUTPUT) {
      report_inline("\n <span style='reddish'>Written to <a href='"   // @codeCoverageIgnore
	. WIKI_ROOT . "?title=" . urlencode($myPage->title) . "'>"    // @codeCoverageIgnore
	. echoable($myPage->title) . '</a></span>');                  // @codeCoverageIgnore
    } else {
	report_inline("\n Written to " . echoable($myPage->title) . ". \n");
    }
    return TRUE;
  }

  public static function response2page(?object $response) : ?object {
    if ($response === NULL) {
      report_warning("Write request failed");
      return NULL;
    }
    if (isset($response->warnings)) {
      if (isset($response->warnings->prop)) {
	report_warning(echoable((string) $response->warnings->prop->{'*'}));
	return NULL;
      }
      if (isset($response->warnings->info)) {
	report_warning(echoable((string) $response->warnings->info->{'*'}));
	return NULL;
      }
    }
    if (!isset($response->batchcomplete)) {
      report_warning("Write request triggered no response from server");
      return NULL;
    }

    if (!isset($response->query->pages)) {
      report_warning("Pages array is non-existent.  Aborting.");
      return NULL;
    }
    $myPage = self::reset($response->query->pages);

    if (!isset($myPage->lastrevid) || !isset($myPage->revisions[0]->timestamp) || !isset($myPage->title)) {
      report_warning("Page seems not to exist. Aborting.");
      return NULL;
    }
    if (!isset($response->query->tokens->csrftoken)) {
      report_warning("Response object lacked tokens.  Aborting. ");
      return NULL;
    }
    return $myPage;
  }

  public static function resultsGood(?object $result) : bool {
    if (isset($result->error)) {
      report_warning("Write error: " .
		    echoable(mb_strtoupper($result->error->code)) . ": " .
		    str_replace(array("You ", " have "), array("This bot ", " has "),
		    echoable((string) @$result->error->info)));
      return FALSE;
    } elseif (isset($result->edit->captcha)) {  // Bot account has flags set on en.wikipedia.org and simple.wikipedia.org to avoid captchas
      report_error("Write error: We encountered a captcha, so can't be properly logged in.");  // @codeCoverageIgnore
    } elseif (empty($result->edit->result)) { // Includes results === NULL
      report_warning("Unhandled write error.  Please copy this output and " .
		    "<a href='https://en.wikipedia.org/wiki/User_talk:Citation_bot'>" .
		    "report a bug</a>.  There is no need to report the database being locked unless it continues to be a problem. ");
      sleep(5);
      return FALSE;
    } elseif ($result->edit->result !== "Success") {
      report_warning('Attempt to write page returned error: ' .  echoable($result->edit->result));
      return FALSE;
    }
    return TRUE;
  }

  /** @return array<string> **/
  public static function category_members(string $cat) : array {
    $list = [];
    $vars = [
      "cmtitle" => "Category:$cat", // Don't urlencode.
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
	  if (stripos($page->title, 'talk:') === FALSE &&
	      stripos($page->title, 'Special:') === FALSE &&
	      stripos($page->title, '/doc') === FALSE &&
	      stripos($page->title, 'Template:') === FALSE &&
	      stripos($page->title, 'Mediawiki:') === FALSE &&
	      stripos($page->title, 'help:') === FALSE &&
	      stripos($page->title, 'Gadget:') === FALSE &&
	      stripos($page->title, 'Portal:') === FALSE &&
	      stripos($page->title, 'timedtext:') === FALSE &&
	      stripos($page->title, 'module:') === FALSE &&
	      stripos($page->title, 'category:') === FALSE &&
	      stripos($page->title, 'Wikipedia:') === FALSE &&
	      stripos($page->title, 'Gadget definition:') ===FALSE &&
	      stripos($page->title, 'Topic:') === FALSE &&
	      stripos($page->title, 'Education Program:') === FALSE &&
	      stripos($page->title, 'Book:') === FALSE) {
	    $list[] = $page->title;
	  }
	}
      } else {
	report_warning('Error reading API for category ' . echoable($cat) . "\n\n");   // @codeCoverageIgnore
	return array();                                                                // @codeCoverageIgnore
      }
      $vars["cmcontinue"] = isset($res->continue) ? $res->continue->cmcontinue : FALSE;
    } while ($vars["cmcontinue"]);
    return $list;
  }

  public static function get_last_revision(string $page) : string {
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
    return  (isset($page->revisions[0]->revid) ? (string) $page->revisions[0]->revid : '');
  }

  # @return -1 if page does not exist; 0 if exists and not redirect; 1 if is redirect
  static public function is_redirect(string $page) : int {
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
    return (isset($res->missing) ? -1 : (isset($res->redirect) ? 1 : 0));
  }
  public static function redirect_target(string $page) : ?string {
    $res = self::QueryAPI([
	"action" => "query",
	"redirects" => "1",
	"titles" => $page,
	]);
    $res = @json_decode($res);
    if (!isset($res->query->redirects[0]->to)) {
	report_warning("Failed to get redirect target");     // @codeCoverageIgnore
	return NULL;                                         // @codeCoverageIgnore
    }
    return (string) $res->query->redirects[0]->to;
  }

  /** @param array<string> $params **/
  static private function QueryAPI(array $params) : string {
   try {
    $params['format'] = 'json';

	    curl_setopt_array(self::$ch_logout, [
		CURLOPT_POST => TRUE,
		CURLOPT_POSTFIELDS => http_build_query($params),
		CURLOPT_URL => API_ROOT,
	  ]);

    $data = (string) @curl_exec(self::$ch_logout);
    if ($data === '') {
       sleep(4);                                // @codeCoverageIgnore
       $data = (string) @curl_exec(self::$ch_logout);  // @codeCoverageIgnore
    }
    return (self::ret_okay(@json_decode($data))) ? $data : '';
    // @codeCoverageIgnoreStart
   } catch(Exception $E) {
      report_warning("Exception caught!!\n");
      report_info("Response: ". echoable($E->getMessage()));
   }
   return '';
  // @codeCoverageIgnoreEnd
  }

  static public function ReadDetails(string $title) : object {
      $details = self::QueryAPI([
	    'action'=>'query',
	    'prop'=>'info',
	    'titles'=> $title,
	    'curtimestamp'=>'true',
	    'inprop' => 'protection',
	  ]);
    return (object) @json_decode($details);
  }

  static public function get_links(string $title) : string {
     return self::QueryAPI(['action' => 'parse', 'prop' => 'links', 'page' => $title]);
  }

  static public function GetAPage(string $title) : string {
    curl_setopt_array(self::$ch_logout,
	      [CURLOPT_HTTPGET => TRUE,
	       CURLOPT_URL => WIKI_ROOT . '?' . http_build_query(['title' => $title, 'action' =>'raw'])]);
    $text = (string) @curl_exec(self::$ch_logout);
    return $text;
  }


  static public function is_valid_user(string $user) : bool {
    if (!$user) return FALSE;
    $query = [
	 "action" => "query",
	 "usprop" => "blockinfo",
	 "list" => "users",
	 "ususers" => $user,
      ];
    $response = self::QueryAPI($query);
    if (strpos($response, '"userid"')  === FALSE) { // try again if weird
      sleep(5);
      $response = self::QueryAPI($query);
    }
    if (strpos($response, '"userid"')  === FALSE) { // try yet again if weird
      sleep(10);
      $response = self::QueryAPI($query);
    }
    if ($response === '') return FALSE;
    $response = str_replace(array("\r", "\n"), '', $response);  // paranoid
    if (strpos($response, '"invalid"') !== FALSE) return FALSE; // IP Address and similar stuff
    if (strpos($response, '"blockid"') !== FALSE) return FALSE; // Valid but blocked
    if (strpos($response, '"missing"') !== FALSE) return FALSE; // No such account
    if (strpos($response, '"userid"')  === FALSE) return FALSE; // Double check, should actually never return FALSE here
    return TRUE;
  }

  static public function NonStandardMode() : bool {
    return !TRAVIS && isset(self::$last_WikipediaBot) && self::$last_WikipediaBot->get_the_user() === 'AManWithNoPlan';
  }

  private function get_the_user_internal() : string {
    return $this->the_user;
  }
  static public function GetLastUser() : string {
    if(isset(self::$last_WikipediaBot)) return self::$last_WikipediaBot->get_the_user_internal();
    return '';
  }

/**
 * Human interaction needed
 * @codeCoverageIgnore
 */
  private function authenticate_user() : void {
    @setcookie(session_name(),session_id(),time()+(7*24*3600), "", "", TRUE, TRUE); // 7 days
    if (isset($_SESSION['citation_bot_user_id']) &&
	isset($_SESSION['access_key']) &&
	isset($_SESSION['access_secret']) &&
	is_string($_SESSION['citation_bot_user_id']) &&
	self::is_valid_user($_SESSION['citation_bot_user_id'])) {
	  $this->the_user = $_SESSION['citation_bot_user_id'];
	  $this->user_token = new Token($_SESSION['access_key'], $_SESSION['access_secret']);
	  return;
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
	unset($_SESSION['access_key'], $_SESSION['access_secret']);
	// report_error('User is either invalid or blocked according to ' . API_ROOT . '?action=query&usprop=blockinfo&format=json&list=users&ususers=' . urlencode(str_replace(" ", "_", $user)));
	report_error('User ' . echoable(str_replace(" ", "_", $user)) . ' is either invalid or blocked');
      }
      $this->the_user = $user;
      $_SESSION['citation_bot_user_id'] = $this->the_user;
      session_write_close(); // Done with the session
      return;
     }
     catch (Throwable $e) { ; }
    }
    if (empty($_SERVER['REQUEST_URI'])) {
       $name = (string) @session_name();
       $id = (string) @session_id();
       session_destroy(); // This is really bad news
       @setcookie($name, $id, time()-42000, "", "", TRUE, TRUE);
       report_error('Invalid access attempt to internal API');
    } else {
       unset($_SESSION['access_key'], $_SESSION['access_secret']);
       session_write_close();
       $return = $_SERVER['REQUEST_URI'];
       $return = preg_replace('~\s+~', '', $return); // Security paranoia
       /** @psalm-taint-escape header */
       $return = urlencode($return);
       @header("Location: authenticate.php?return=" . $return);
    }
    exit(0);
  }

  private static function reset(object &$obj) : object { // Make PHP 8 happy
     $arr = (array) $obj;
     return (object) reset($arr);
  }
}
