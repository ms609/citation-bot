<?php
declare(strict_types=1);
// This library did the edits as the users in https://github.com/ms609/citation-bot/blob/439dc557d1c56c9a71b30a9c51e37234ff710dad/WikipediaBot.php
// To use the oauthclient library, run:
// composer require mediawiki/oauthclient
use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use MediaWiki\OAuthClient\Request;
use MediaWiki\OAuthClient\SignatureMethod\HmacSha1;

require_once('user_messages.php');  // @codeCoverageIgnore
require_once("constants.php");      // @codeCoverageIgnore

final class WikipediaBot {

  private $consumer;
  private $token;
  /** @var resource $ch */
  private $ch;
  private $the_user = '';
  private static $last_WikipediaBot;  // This leads what looks like a circular memory-leak in the test suite, but not in real-life

  function __construct(bool $no_user = FALSE) {
    $this->ch = curl_init();
    curl_setopt_array($this->ch, [
        CURLOPT_FAILONERROR => TRUE, // This is a little paranoid, but we don't have trouble yet, and should deal with i
        CURLOPT_FOLLOWLOCATION => TRUE,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_HEADER => 0, // Don't include header in output
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_COOKIESESSION => TRUE,
        CURLOPT_COOKIEFILE => 'cookie.txt',
        CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org'
    ]);
    // setup.php must already be run at this point
    if (!getenv('PHP_OAUTH_CONSUMER_TOKEN'))  report_error("PHP_OAUTH_CONSUMER_TOKEN not set");
    if (!getenv('PHP_OAUTH_CONSUMER_SECRET')) report_error("PHP_OAUTH_CONSUMER_SECRET not set");
    if (!getenv('PHP_OAUTH_ACCESS_TOKEN'))    report_error("PHP_OAUTH_ACCESS_TOKEN not set");
    if (!getenv('PHP_OAUTH_ACCESS_SECRET'))   report_error("PHP_OAUTH_ACCESS_SECRET not set");

    /** @psalm-suppress RedundantCondition */  /* PSALM thinks TRAVIS cannot be FALSE */
    if (TRAVIS) {
      $this->the_user = 'Citation_bot';
    } elseif ($no_user) {           // @codeCoverageIgnore
      $this->the_user = '';         // @codeCoverageIgnore
    } else {
      $this->authenticate_user();  // @codeCoverageIgnore
    }
    $this->consumer = new Consumer((string) getenv('PHP_OAUTH_CONSUMER_TOKEN'), (string) getenv('PHP_OAUTH_CONSUMER_SECRET'));
    $this->token = new Token((string) getenv('PHP_OAUTH_ACCESS_TOKEN'), (string) getenv('PHP_OAUTH_ACCESS_SECRET'));
    self::$last_WikipediaBot = $this;
  }
  
  function __destruct() {
    curl_close($this->ch);
  }
  
  public function username() : string {
    $userQuery = $this->fetch(['action' => 'query', 'meta' => 'userinfo'], 'GET');
    return (isset($userQuery->query->userinfo->name)) ? $userQuery->query->userinfo->name : '';
  }
  
  public function get_the_user() : string {
    if ($this->the_user == '') {
      report_error('User Not Set');         // @codeCoverageIgnore
    }
    return $this->the_user; // Might or might not match the above
  }
  
  private function ret_okay(?object $response) : bool {
    if (is_null($response)) {
      report_minor_error('Wikipedia responce was not decoded.');  // @codeCoverageIgnore
      return FALSE;                                               // @codeCoverageIgnore
    }
    if (isset($response->error)) {
      // @codeCoverageIgnoreStart
      if ((string) $response->error->code == 'blocked') { // Travis CI IPs are blocked, even to logged in users.
        report_error('Account "' . $this->username() .  '" or this IP is blocked from editing.');
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
      } else {
        report_minor_error('API call failed: ' . (string) $response->error->info);
      }
      return FALSE;
      // @codeCoverageIgnoreEnd
    }
    return TRUE;
  }
  
  public function fetch(array $params, string $method, int $depth = 1) : ?object {
    if ($depth > 1) sleep($depth);
    if ($depth > 5) return NULL;
    $params['format'] = 'json';
     
    $request = Request::fromConsumerAndToken($this->consumer, $this->token, $method, API_ROOT, $params);
    $request->signRequest(new HmacSha1(), $this->consumer, $this->token);
    $authenticationHeader = $request->toHeader();
    
    try {
      switch (strtolower($method)) {
        case 'get':
          $url = API_ROOT . '?' . http_build_query($params);            
          curl_setopt_array($this->ch, [
            CURLOPT_HTTPGET => TRUE,
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [$authenticationHeader],
          ]);
          set_time_limit(45);
          $data = (string) @curl_exec($this->ch);
          if (!$data) {
            report_error("Curl error: " . echoable(curl_error($this->ch)));        // @codeCoverageIgnore
            return NULL;                                                           // @codeCoverageIgnore
          }
          $ret = @json_decode($data);
          set_time_limit(120);
          if (isset($ret->error->code) && $ret->error->code == 'assertuserfailed') {
            // @codeCoverageIgnoreStart
            unset($data);
            unset($ret);
            return $this->fetch($params, $method, $depth+1);
            // @codeCoverageIgnoreEnd
          }
          return ($this->ret_okay($ret)) ? $ret : NULL;
          
        case 'post':
          curl_setopt_array($this->ch, [
            CURLOPT_POST => TRUE,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [$authenticationHeader],
            CURLOPT_URL => API_ROOT
          ]);
          set_time_limit(45);
          $data = (string) @curl_exec($this->ch);
          if ( !$data ) {
            report_error("Curl error: " . echoable(curl_error($this->ch)));     // @codeCoverageIgnore
          }
          $ret = @json_decode($data);
          set_time_limit(120);    
          if (isset($ret->error) && (
            (string) $ret->error->code === 'assertuserfailed' ||
            stripos((string) $ret->error->info, 'The database has been automatically locked') !== FALSE ||
            stripos((string) $ret->error->info, 'abusefilter-warning-predatory') !== FALSE ||
            stripos((string) $ret->error->info, 'protected') !== FALSE ||
            stripos((string) $ret->error->info, 'Nonce already used') !== FALSE)
          ) {
            // @codeCoverageIgnoreStart
            unset($data);
            unset($ret);
            return $this->fetch($params, $method, $depth+1);
            // @codeCoverageIgnoreEnd
          }
          return ($this->ret_okay($ret)) ? $ret : NULL;

        default:  // will only be hit if error in our code
          report_error("Unrecognized method in Fetch."); // @codeCoverageIgnore
      }
    } catch(Exception $E) {
      report_warning("Exception caught!\n");
      report_info("Response: ". $E->getMessage());
    }
    return NULL;
  }
  
  public function write_page(string $page, string $text, string $editSummary, int $lastRevId, string $startedEditing) : bool {
    $response = $this->fetch([
            'action' => 'query',
            'prop' => 'info|revisions',
            'rvprop' => 'timestamp',
            'meta' => 'tokens',
            'titles' => $page
          ], 'GET');
    
    if (!$response) {
      report_minor_error("Write request failed");     // @codeCoverageIgnore
      return FALSE;                                   // @codeCoverageIgnore
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
    
    if (!isset($myPage->lastrevid) || !isset($myPage->revisions[0]) || !isset($myPage->revisions[0]->timestamp || !isset($myPage->title)) {
      report_minor_error("Page seems not to exist. Aborting.");   // @codeCoverageIgnore
      return FALSE;                                               // @codeCoverageIgnore
    }
    $baseTimeStamp = $myPage->revisions[0]->timestamp;
    
    if (($lastRevId != 0 && $myPage->lastrevid != $lastRevId)
     || ($startedEditing != '' && strtotime($baseTimeStamp) > strtotime($startedEditing))) {
      report_minor_error("Possible edit conflict detected. Aborting.");      // @codeCoverageIgnore
      return FALSE;                                                          // @codeCoverageIgnore
    }
    if (stripos($text, "CITATION_BOT_PLACEHOLDER") != FALSE)  {
      report_minor_error("\n ! Placeholder left escaped in text. Aborting.");  // @codeCoverageIgnore
      return FALSE;                                                            // @codeCoverageIgnore
    }
    
    // No obvious errors; looks like we're good to go ahead and edit
    $auth_token = $response->query->tokens->csrftoken; // Citation bot tokens
    $submit_vars = array(
        "action" => "edit",
        "title" => $page,
        "text" => $text,
        "summary" => $editSummary,
        "notminor" => "1",
        "bot" => "1",
        "basetimestamp" => $baseTimeStamp,
        "starttimestamp" => $startedEditing,
        #"md5"       => hash('md5', $data), // removed by MS because I can't figure out how to make the hash of the UTF-8 encoded string that I send match that generated by the server.
        "watchlist" => "nochange",
        "format" => "json",
        'token' => $auth_token,
    );
    $result = $this->fetch($submit_vars, 'POST');
    
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
        report_error("Write error: We encountered a captcha, so can't be properly logged in.");
      } elseif ($result->edit->result == "Success") {
        // Need to check for this string wherever our behaviour is dependant on the success or failure of the write operation
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
      if (!TRAVIS) report_error("Unhandled write error.  Please copy this output and " .
                    "<a href='https://en.wikipedia.org/wiki/User_talk:Citation_bot'>" .
                    "report a bug.</a>.  There is no need to report the database being locked unless it continues to be a problem. ");
      // @codeCoverageIgnoreEnd
    }
    return FALSE;
  }
  
  public function category_members(string $cat) : array {
    $list = [];
    $vars = [
      "cmtitle" => "Category:$cat", // Don't URLencode.
      "action" => "query",
      "cmlimit" => "500",
      "list" => "categorymembers",
    ];
    
    do {
      set_time_limit(8);
      $res = $this->fetch($vars, 'POST');
      if (isset($res->query->categorymembers)) {
        foreach ($res->query->categorymembers as $page) {
          // We probably only want to visit pages in the main namespace
          if (stripos($page->title, 'talk:') === FALSE &&
              stripos($page->title, 'Template:') === FALSE &&
              stripos($page->title, 'Special:') === FALSE &&
              stripos($page->title, 'Wikipedia:') === FALSE) {
            $list[] = $page->title;
          }
        }
      } else {
        report_error('Error reading API for category ' . echoable($cat) . "\n\n");   // @codeCoverageIgnore
      }
      $vars["cmcontinue"] = isset($res->continue) ? $res->continue->cmcontinue : FALSE;
    } while ($vars["cmcontinue"]);
    set_time_limit(120);
    return $list;
  }
  
  // Returns an array; Array ("title1", "title2" ... );
  public function what_transcludes(string $template, int $namespace = 99) : array {
    $titles = $this->what_transcludes_2($template, $namespace);
    return $titles["title"];
  }

  protected function what_transcludes_2(string $template, int $namespace = 99) : array {
    $vars = Array (
      "action" => "query",
      "list" => "embeddedin",
      "eilimit" => "5000",
      "eititle" => "Template:" . $template,
      "einamespace" => ($namespace==99)?"":(string)$namespace,
    );
    $list = ['title' => NULL];
    
    do {
      set_time_limit(20);
      $res = $this->fetch($vars, 'POST');
      if (isset($res->query->embeddedin->ei) || $res == NULL) {
        report_error('Error reading API for template/namespace: ' . echoable($template) . '/' . echoable(($namespace==99)?"Normal":(string)$namespace));   // @codeCoverageIgnore
      } else {
        foreach($res->query->embeddedin as $page) {
          $list["title"][] = $page->title;
          $list["id"][] = $page->pageid;
        }
      }
      $vars["eicontinue"] = isset($res->continue) ? (string) $res->continue->eicontinue : FALSE;
    } while ($vars["eicontinue"]);
    set_time_limit(120);
    return $list;
  }

  public function get_last_revision(string $page) : string {
    $res = $this->fetch([
        "action" => "query",
        "prop" => "revisions",
        "titles" => $page,
      ], 'GET');
    if (!isset($res->query->pages)) {
        report_error("Failed to get article's last revision");      // @codeCoverageIgnore
        return '';                                                  // @codeCoverageIgnore
    }
    $page = reset($res->query->pages);
    return  (isset($page->revisions[0]->revid) ? (string) $page->revisions[0]->revid : '');
  }

  public function get_prefix_index(string $prefix, int $namespace = 0, string $start = "") : array {
    $page_titles = [];
    $vars = ["action" => "query",
      "list" => "allpages",
      "apnamespace" => $namespace,
      "apprefix" => $prefix,
      "aplimit" => "500",
      "apfrom" => $start
    ];
    
    do {
      set_time_limit(10);
      $res = $this->fetch($vars, 'POST');
      if ($res && !isset($res->error) && isset($res->query->allpages)) {
        foreach ($res->query->allpages as $page) {
          $page_titles[] = $page->title;
          # $page_ids[] = $page->pageid;
        }
      } else {
        report_error('Error reading API with vars ' . http_build_query($vars));     // @codeCoverageIgnore
        if (isset($res->error)) echo $res->error;                                   // @codeCoverageIgnore
      }
      $vars["apfrom"] = isset($res->continue) ? $res->continue->apcontinue : FALSE;
    } while ($vars["apfrom"]);
    set_time_limit(120);
    return $page_titles;
  }
  public function get_namespace(string $page) : int {
    $res = $this->fetch([
        "action" => "query",
        "prop" => "info",
        "titles" => $page,
        ], 'GET'); 
    if (!isset($res->query->pages)) {
        report_warning("Failed to get article namespace");       // @codeCoverageIgnore
        return -99999;                                           // @codeCoverageIgnore
    }
    return (int) reset($res->query->pages)->ns;
  }
  # @return -1 if page does not exist; 0 if exists and not redirect; 1 if is redirect
  static public function is_redirect(string $page) : int {
    if (isset(self::$last_WikipediaBot)) {
      $api = self::$last_WikipediaBot;
    } elseif (getenv('PHP_OAUTH_CONSUMER_TOKEN')) {
      $api = new WikipediaBot(TRUE);
    } else {
      return 0; // This is when we are in TRAVIS but have no secret keys.  // @codeCoverageIgnore
    }
    $res = $api->fetch([
        "action" => "query",
        "prop" => "info",
        "titles" => $page,
        ], 'POST');
    
    if (!isset($res->query->pages)) {
        report_warning("Failed to get redirect status");    // @codeCoverageIgnore
        return -1;                                          // @codeCoverageIgnore
    }
    $res = reset($res->query->pages);
    return (isset($res->missing) ? -1 : (isset($res->redirect) ? 1 : 0));
  }
  public function redirect_target(string $page) : ?string {
    $res = $this->fetch([
        "action" => "query",
        "redirects" => "1",
        "titles" => $page,
        ], 'POST');
    if (!isset($res->query->redirects[0]->to)) {
        report_warning("Failed to get redirect target");     // @codeCoverageIgnore
        return NULL;                                         // @codeCoverageIgnore
    }
    return (string) $res->query->redirects[0]->to;
  }
  public function namespace_id(string $name) : int {
    $lc_name = strtolower($name);
    return array_key_exists($lc_name, NAMESPACE_ID) ? NAMESPACE_ID[$lc_name] : 0;
  }
  public function namespace_name(int $id) : ?string {
    return array_key_exists($id, NAMESPACES) ? NAMESPACES[$id] : NULL;
  }
  
  static public function is_valid_user(string $user) : bool {
    if (!$user) return FALSE;
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_HEADER => 0,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_TIMEOUT => 20,
      CURLOPT_USERAGENT => 'Citation_bot; citations@tools.wmflabs.org',
      CURLOPT_URL => API_ROOT . '?action=query&usprop=blockinfo&format=json&list=users&ususers=' . urlencode(str_replace(" ", "_", $user))
    ]);
    $response = (string) @curl_exec($ch);
    curl_close($ch);
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
    if (session_status() !== PHP_SESSION_ACTIVE) report_error('No active session found'); // Tried to create more than one WikipediaBot() instance?!
    // These would be old and unusable if we are here
    unset($_SESSION['request_key']);
    unset($_SESSION['request_secret']);
    if (isset($_SESSION['citation_bot_user_id'])) {
      if (is_string($_SESSION['citation_bot_user_id']) && self::is_valid_user($_SESSION['citation_bot_user_id'])) {
        $this->the_user = $_SESSION['citation_bot_user_id'];
        @setcookie(session_name(),session_id(),time()+(24*3600)); // 24 hours
        session_write_close(); // Done with it
        return;
      } else {
        unset($_SESSION['citation_bot_user_id']);
      }
    }
    if (isset($_SESSION['access_key']) && isset($_SESSION['access_secret'])) {
     try {
      $user_token = new Token($_SESSION['access_key'], $_SESSION['access_secret']);
      // Validate the credentials.
      $conf = new ClientConfig(WIKI_ROOT . '?title=Special:OAuth');
      if (!getenv('PHP_WP_OAUTH_CONSUMER')) report_error("PHP_WP_OAUTH_CONSUMER not set");
      if (!getenv('PHP_WP_OAUTH_SECRET'))   report_error("PHP_WP_OAUTH_SECRET not set");
      $conf->setConsumer(new Consumer((string) getenv('PHP_WP_OAUTH_CONSUMER'), (string) getenv('PHP_WP_OAUTH_SECRET')));
      $client = new Client($conf);
      $ident = $client->identify( $user_token );
      $user = (string) $ident->username;
      if (!self::is_valid_user($user)) {
        unset($_SESSION['access_key']);
        unset($_SESSION['access_secret']);
        report_error('User is either invalid or blocked on ' . WIKI_ROOT);
      }
      $this->the_user = $user;
      $_SESSION['citation_bot_user_id'] = $this->the_user;
      session_write_close(); // Done with the session
      return;
     }
     catch (Throwable $e) { ; }
    }
    unset($_SESSION['access_key']);
    unset($_SESSION['access_secret']);
    $return = urlencode($_SERVER['REQUEST_URI']);
    session_write_close();
    @header("Location: authenticate.php?return=" . $return);
    exit(0);
  }
}
