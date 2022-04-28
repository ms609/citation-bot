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

  private $bot_consumer;
  private $bot_token;
  private $user_consumer;
  private $user_client;
  private $user_token;
  /** @var resource $ch */
  private static $ch; // All wikipedia connections share a session
  private $the_user = '';
  private static $last_WikipediaBot; // For NonStandardMode()
  
  public static function make_ch() : void { // Executed below at end of file
    if (isset(self::$ch)) curl_close(self::$ch);
    self::$ch = curl_init();
        curl_setopt_array(self::$ch, [
        CURLOPT_FAILONERROR => TRUE, // This is a little paranoid - see https://curl.se/libcurl/c/CURLOPT_FAILONERROR.html
        CURLOPT_FOLLOWLOCATION => TRUE,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_HEADER => 0, // Don't include header in output
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => BOT_USER_AGENT,
        CURLOPT_COOKIESESSION => TRUE,
    ]);
  }

  function __construct() {
    // setup.php must already be run at this point

    $this->bot_consumer = new Consumer((string) getenv('PHP_OAUTH_CONSUMER_TOKEN'), (string) getenv('PHP_OAUTH_CONSUMER_SECRET'));
    $this->bot_token = new Token((string) getenv('PHP_OAUTH_ACCESS_TOKEN'), (string) getenv('PHP_OAUTH_ACCESS_SECRET'));
    // These are only needed if editing as a user
    $this->user_consumer = new Consumer((string) getenv('PHP_WP_OAUTH_CONSUMER'), (string) getenv('PHP_WP_OAUTH_SECRET'));
    $conf = new ClientConfig(WIKI_ROOT . '?title=Special:OAuth');
    $conf->setConsumer($this->user_consumer);
    if (method_exists($conf, 'setUserAgent')) {
      $conf->setUserAgent(BOT_USER_AGENT);
    }
    $this->user_client = new Client($conf);

    /** @psalm-suppress RedundantCondition */  /* PSALM thinks TRAVIS cannot be FALSE */
    if (TRAVIS) {
      $this->the_user = 'Citation_bot';
      $this->user_token = new Token("", "");
      // @codeCoverageIgnoreStart
    } elseif (!HTML_OUTPUT) { // Running on the command line, and editing using main tokens
      $this->the_user = '';
      $this->user_token = new Token("", "");
    } else {
      $this->authenticate_user();
      // @codeCoverageIgnoreEnd
    }
    self::$last_WikipediaBot = $this;
  }
  
  public function get_the_user() : string {
    if ($this->the_user == '') {
      report_error('User Not Set');         // @codeCoverageIgnore
    }
    return $this->the_user;
  }
  
  private static function ret_okay(?object $response) : bool {
    if (is_null($response)) {
      report_minor_error('Wikipedia response was not decoded.');  // @codeCoverageIgnore
      return FALSE;                                               // @codeCoverageIgnore
    }
    if (isset($response->error)) {
      // @codeCoverageIgnoreStart
      if ((string) $response->error->code == 'blocked') { // Travis CI IPs are blocked, even to logged in users.
        report_error('Bot account or this IP is blocked from editing.');
      } elseif (strpos((string) $response->error->info, 'The database has been automatically locked') !== FALSE) {
        report_minor_error('Wikipedia database Locked.  Aborting changes for this page.  Will sleep and move on.');
        sleep(10);
        return TRUE;
      } elseif (strpos((string) $response->error->info, 'abusefilter-warning-predatory') !== FALSE) {
        report_minor_error('Wikipedia page contains predatory references.  Aborting changes for this page.  Will sleep and move on.');
        return TRUE;
      } elseif (strpos((string) $response->error->info, 'protected') !== FALSE) {
        report_minor_error('Wikipedia page is protected from editing.  Aborting changes for this page.  Will sleep and move on.');
        return TRUE;
      } elseif (strpos((string) $response->error->info, 'Wikipedia:Why create an account') !== FALSE) {
        report_error('The bot is editing as you, and you have not granted that permission.  Go to ' . WIKI_ROOT . '?title=Special:OAuthManageMyGrants/update/230820 and grant Citation Bot "Edit existing pages" rights.');
      } else {
        report_minor_error('API call failed: ' . (string) $response->error->info);
      }
      return FALSE;
      // @codeCoverageIgnoreEnd
    }
    return TRUE;
  }
  
  /** @phpstan-impure **/
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
          curl_setopt_array(self::$ch, [
            CURLOPT_POST => TRUE,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [$authenticationHeader],
            CURLOPT_URL => API_ROOT
          ]);
    
      $data = (string) @curl_exec(self::$ch);
      $ret = @json_decode($data); 
      if (($ret == NULL) || (isset($ret->error) && (
        (string) $ret->error->code === 'assertuserfailed' ||
        stripos((string) $ret->error->info, 'The database has been automatically locked') !== FALSE ||
        stripos((string) $ret->error->info, 'abusefilter-warning-predatory') !== FALSE ||
        stripos((string) $ret->error->info, 'protected') !== FALSE ||
        stripos((string) $ret->error->info, 'Nonce already used') !== FALSE))
      ) {
        // @codeCoverageIgnoreStart
        unset($data, $ret, $token, $consumer, $request, $authenticationHeader); // save memory during recursion
        return $this->fetch($params, $depth+1);
        // @codeCoverageIgnoreEnd
      }
      return (self::ret_okay($ret)) ? $ret : NULL;
    } catch(Exception $E) {
      report_warning("Exception caught!\n");
      report_info("Response: ". $E->getMessage());
    }
    return NULL;
  }
  
  /** @phpstan-impure **/
  public function write_page(string $page, string $text, string $editSummary, int $lastRevId, string $startedEditing) : bool {
    if (stripos($text, "CITATION_BOT_PLACEHOLDER") != FALSE)  {
      report_minor_error("\n ! Placeholder left escaped in text. Aborting.");  // @codeCoverageIgnore
      return FALSE;                                                            // @codeCoverageIgnore
    }

    $response = $this->fetch([
            'action' => 'query',
            'prop' => 'info|revisions',
            'rvprop' => 'timestamp',
            'meta' => 'tokens',
            'titles' => $page
          ]);
    
    if (!$response) {
      report_warning("Write request failed");     // @codeCoverageIgnore
      return FALSE;                               // @codeCoverageIgnore
    }
    if (isset($response->warnings)) {
      // @codeCoverageIgnoreStart
      if (isset($response->warnings->prop)) {
        report_minor_error((string) $response->warnings->prop->{'*'});
        return FALSE;
      }
      if (isset($response->warnings->info)) {
        report_minor_error((string) $response->warnings->info->{'*'});
        return FALSE;
      }
      // @codeCoverageIgnoreEnd
    }
    if (!isset($response->batchcomplete)) {
      report_minor_error("Write request triggered no response from server");   // @codeCoverageIgnore
      return FALSE;                                                            // @codeCoverageIgnore
    }
    
    if (!isset($response->query) || !isset($response->query->pages)) {
      report_minor_error("Pages array is non-existent.  Aborting.");   // @codeCoverageIgnore
      return FALSE;                                                    // @codeCoverageIgnore
    }
    $myPage = reset($response->query->pages); // reset gives first element in list
    
    if (!isset($myPage->lastrevid) || !isset($myPage->revisions) || !isset($myPage->revisions[0]) ||
        !isset($myPage->revisions[0]->timestamp) || !isset($myPage->title)) {
      report_minor_error("Page seems not to exist. Aborting.");   // @codeCoverageIgnore
      return FALSE;                                               // @codeCoverageIgnore
    }
    $baseTimeStamp = $myPage->revisions[0]->timestamp;
    
    if (($lastRevId != 0 && $myPage->lastrevid != $lastRevId)
     || ($startedEditing != '' && strtotime($baseTimeStamp) > strtotime($startedEditing))) {
      report_minor_error("Possible edit conflict detected. Aborting.");      // @codeCoverageIgnore
      return FALSE;                                                          // @codeCoverageIgnore
    }
    if (!isset($response->query) || !isset($response->query->tokens) ||
        !isset($response->query->tokens->csrftoken)) {
      report_minor_error("Response object was invalid.  Aborting. ");  // @codeCoverageIgnore
      return FALSE;                                                    // @codeCoverageIgnore
    }
    
    // No obvious errors; looks like we're good to go ahead and edit
    $auth_token = $response->query->tokens->csrftoken;
    if (defined('EDIT_AS_USER')) {  // @codeCoverageIgnoreStart
      $auth_token = json_decode( $this->user_client->makeOAuthCall(
        $this->user_token,
       API_ROOT . '?action=query&meta=tokens&format=json'
       ) )->query->tokens->csrftoken;
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
    
    if (isset($result->error)) {
      // @codeCoverageIgnoreStart
      report_minor_error("Write error: " . 
                    echoable(strtoupper($result->error->code)) . ": " . 
                    str_replace(array("You ", " have "), array("This bot ", " has "), 
                    echoable($result->error->info)));
      return FALSE;
      // @codeCoverageIgnoreEnd
    } elseif (isset($result->edit)) {
      // @codeCoverageIgnoreStart
      if (isset($result->edit->captcha)) {
        report_error("Write error: We encountered a captcha, so can't be properly logged in."); // Bot account has flags set on en.wikipedia.org and simple.wikipedia.org to avoid captchas
      } elseif ($result->edit->result == "Success") {
        // Need to check for this string wherever our behavior is dependant on the success or failure of the write operation
        if (HTML_OUTPUT) {
          report_inline("\n <span style='reddish'>Written to <a href='" 
          . WIKI_ROOT . "?title=" . urlencode($myPage->title) . "'>" 
          . echoable($myPage->title) . '</a></span>');
        } else {
          report_inline("\n Written to " . echoable($myPage->title) . ". \n");
        }
        return TRUE;
      } elseif (isset($result->edit->result)) {
        report_warning(echoable('Attempt to write page returned error: ' .  $result->edit->result));
        return FALSE;
      }
      // @codeCoverageIgnoreEnd
    } else {
      // @codeCoverageIgnoreStart
      report_warning("Unhandled write error.  Please copy this output and " .
                    "<a href='https://en.wikipedia.org/wiki/User_talk:Citation_bot'>" .
                    "report a bug</a>.  There is no need to report the database being locked unless it continues to be a problem. ");
      sleep(15);
      // @codeCoverageIgnoreEnd
    }
    return FALSE;
  }
  
  public static function category_members(string $cat) : array {
    $list = [];
    $vars = [
      "cmtitle" => "Category:$cat", // Don't URLencode.
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
        report_error('Error reading API for category ' . echoable($cat) . "\n\n");   // @codeCoverageIgnore
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
        report_minor_error("Failed to get article's last revision");      // @codeCoverageIgnore
        return '';                                                        // @codeCoverageIgnore
    }
    $page = reset($res->query->pages);
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
    $res = reset($res->query->pages);
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
  
  static private function QueryAPI(array $params) : string {
   try {
    $params['format'] = 'json';

            curl_setopt_array(self::$ch, [
                CURLOPT_POST => TRUE,
                CURLOPT_POSTFIELDS => http_build_query($params),
                CURLOPT_URL => API_ROOT,
                CURLOPT_HTTPHEADER => []
          ]);

    $data = (string) @curl_exec(self::$ch);
    if ($data === '') {
       sleep(4);                                // @codeCoverageIgnore
       $data = (string) @curl_exec(self::$ch);  // @codeCoverageIgnore
    }
    return (self::ret_okay(@json_decode($data))) ? $data : '';
    // @codeCoverageIgnoreStart
   } catch(Exception $E) {
      report_warning("Exception caught!!\n");
      report_info("Response: ". $E->getMessage());
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
    return @json_decode($details);
  }
  
  static public function get_links(string $title) : string {
     return self::QueryAPI(['action' => 'parse', 'prop' => 'links', 'page' => $title]);
  }
  
  static public function GetAPage(string $title) : string {
    curl_setopt_array(self::$ch,
              [CURLOPT_HTTPGET => TRUE,
               CURLOPT_HTTPHEADER => [],
               CURLOPT_URL => WIKI_ROOT . '?' . http_build_query(['title' => $title, 'action' =>'raw'])]);
    $text = (string) @curl_exec(self::$ch);
    return $text;
  }
  
  
  static public function is_valid_user(string $user) : bool {
    if (!$user) return FALSE;
    $query = [
         "action" => "query",
         "usprop" => "blockinfo",
         "list" => "users",
         "ususers" => urlencode(str_replace(" ", "_", $user)),
      ];
    $response = self::QueryAPI($query);
    if ($response === NULL || (strpos($response, '"userid"')  === FALSE)) { // try again if weird
      sleep(5);
      $response = self::QueryAPI($query);
    }
    if ($response == '') return FALSE;
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
  
/**
 * Human interaction needed
 * @codeCoverageIgnore
 */
  private function authenticate_user() : void {
    if (session_status() !== PHP_SESSION_ACTIVE) report_error('No active session found');    
    unset($_SESSION['request_key'], $_SESSION['request_secret']); // These would be old and unusable if we are here
    if (isset($_SESSION['citation_bot_user_id']) &&
        isset($_SESSION['access_key']) &&
        isset($_SESSION['access_secret']) &&
        is_string($_SESSION['citation_bot_user_id']) &&
        self::is_valid_user($_SESSION['citation_bot_user_id'])) {
          $this->the_user = $_SESSION['citation_bot_user_id'];
          @setcookie(session_name(),session_id(),time()+(24*3600)); // 24 hours
          $this->user_token = new Token($_SESSION['access_key'], $_SESSION['access_secret']);
          session_write_close(); // Done with it
          return;
    }
    unset($_SESSION['citation_bot_user_id']);
    if (isset($_SESSION['access_key']) && isset($_SESSION['access_secret'])) {
     try {
      $this->user_token = new Token($_SESSION['access_key'], $_SESSION['access_secret']);
      // Validate the credentials.
      $ident = $this->user_client->identify($this->user_token);
      $user = (string) $ident->username;
      if (!self::is_valid_user($user)) {
        unset($_SESSION['access_key'], $_SESSION['access_secret']);
        report_error('User is either invalid or blocked according to ' . API_ROOT . '?action=query&usprop=blockinfo&format=json&list=users&ususers=' . urlencode(str_replace(" ", "_", $user)));
      }
      $this->the_user = $user;
      $_SESSION['citation_bot_user_id'] = $this->the_user;
      session_write_close(); // Done with the session
      return;
     }
     catch (Throwable $e) { ; }
    }
    unset($_SESSION['access_key'], $_SESSION['access_secret']);
    $return = urlencode($_SERVER['REQUEST_URI']);
    session_write_close();
    @header("Location: authenticate.php?return=" . $return);
    exit(0);
  }
}

WikipediaBot::make_ch(); // @codeCoverageIgnore


