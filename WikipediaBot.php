<?php
// This library did the edits as the users in https://github.com/ms609/citation-bot/blob/439dc557d1c56c9a71b30a9c51e37234ff710dad/WikipediaBot.php
// To use the oauthclient library, run:
// composer require mediawiki/oauthclient
use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use MediaWiki\OAuthClient\Request;
use MediaWiki\OAuthClient\SignatureMethod\HmacSha1;
class WikipediaBot {
  
  protected $consumer, $token, $ch, $the_user;
  private static $last_WikipediaBot = NULL;

  function __construct($no_user = FALSE) {
    // setup.php must already be run at this point
    if (!getenv('PHP_OAUTH_CONSUMER_TOKEN')) report_error("PHP_OAUTH_CONSUMER_TOKEN not set");
    if (!getenv('PHP_OAUTH_ACCESS_TOKEN')) report_error("PHP_OAUTH_ACCESS_TOKEN not set");
    if ($no_user) {
      ; // Do not set the username
    } elseif (getenv('TRAVIS')) {
      $this->the_user = 'Citation_bot';
    } else {
      $this->authenticate_user();
    }
    $this->consumer = new Consumer(getenv('PHP_OAUTH_CONSUMER_TOKEN'), getenv('PHP_OAUTH_CONSUMER_SECRET'));
    // Hard coded token and secret.
    $this->token = new Token(getenv('PHP_OAUTH_ACCESS_TOKEN'), getenv('PHP_OAUTH_ACCESS_SECRET'));
    self::$last_WikipediaBot = $this;
  }
  
  function __destruct() {
    if ($this->ch) curl_close($this->ch);
  }
  
  public function username() {
    $userQuery = $this->fetch(['action' => 'query', 'meta' => 'userinfo']);
    return (isset($userQuery->query->userinfo->name)) ? $userQuery->query->userinfo->name : FALSE;
  }
  
  public function get_the_user() {
    if (!isset($this->the_user) || @$this->the_user == NULL) {
      report_error('User Not Set');
    }
    return $this->the_user; // Might or might not match the above
  }
  
  private function ret_okay($response) {
    if ($response === CURLE_HTTP_RETURNED_ERROR) {
      report_error("Curl encountered HTTP response error");
    }
    if (isset($response->error)) {
      if ($response->error->code == 'blocked') {
        report_error('Account "' . $this->username() . 
        '" or this IP is blocked from editing.'); // Yes, Travis CI IPs are blocked, even to logged in users.
      } else {
        if (strpos((string) $response->error->info, 'The database has been automatically locked') !== FALSE) {
           report_minor_error('Wikipedia database Locked.  Aborting changes for this page.  Will sleep and move on.  Specifically: ' . $response->error->info);
           sleep(5);
           return FALSE;  // Would be best to retry, but we are down in the weeds of the code
        }
        report_error('API call failed: ' . $response->error->info);
      }
      return FALSE;
    }
    return TRUE;
  }
  
  private function reset_curl() {
    if (!$this->ch) {
      $this->ch = curl_init();
    }
    return curl_setopt_array($this->ch, [
        CURLOPT_FAILONERROR => TRUE, // This is a little paranoid, but we don't have trouble yet, and should deal with i
        CURLOPT_FOLLOWLOCATION => TRUE,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_HEADER => FALSE, // Don't include header in output
        CURLOPT_HTTPGET => TRUE, // Reset to default GET
        CURLOPT_RETURNTRANSFER => TRUE,
        
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 20,
        
        CURLOPT_COOKIESESSION => TRUE,
        CURLOPT_COOKIEFILE => 'cookie.txt',
        CURLOPT_COOKIEJAR => 'cookiejar.txt',
        CURLOPT_URL => API_ROOT,
        CURLOPT_USERAGENT => 'Citation bot'
      ]);
  }
  
  public function fetch($params, $method = 'GET') {
    if (!$this->reset_curl()) {
      curl_close($this->ch);
      report_error('Could not initialize CURL resource: ' . echoable(curl_error($this->ch)));
      return FALSE;
    }
    $params['format'] = 'json';
    
  
    $request = Request::fromConsumerAndToken($this->consumer, $this->token, $method, API_ROOT, $params);
    $request->signRequest(new HmacSha1(), $this->consumer, $this->token);
    $authenticationHeader = $request->toHeader();
    
    try {
      switch (strtolower($method)) {
        case 'get':
          $url = API_ROOT . '?' . http_build_query($params);            
          curl_setopt_array($this->ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [$authenticationHeader],
          ]);
          set_time_limit(45);
          $data = curl_exec($this->ch);
          if (!$data) {
            report_error("Curl error: " . echoable(curl_error($this->ch)));
            return FALSE;
          }
          $ret = @json_decode($data);
          set_time_limit(120);
          if (isset($ret->error->code) && $ret->error->code == 'assertuserfailed') {
            unset($data);
            unset($ret);
            return $this->fetch($params, $method);
          }
          return ($this->ret_okay($ret)) ? $ret : FALSE;
          
        case 'post':
          curl_setopt_array($this->ch, [
            CURLOPT_POST => TRUE,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [$authenticationHeader],
          ]);
          set_time_limit(45);
          $data = curl_exec($this->ch);
          if ( !$data ) {
            report_error("Curl error: " . echoable(curl_error($this->ch)));
          }
          $ret = @json_decode($data);
          set_time_limit(120);    
          if (isset($ret->error) && $ret->error->code == 'assertuserfailed') {
            unset($data);
            unset($ret);
            return $this->fetch($params, $method);
          }
          return ($this->ret_okay($ret)) ? $ret : FALSE;
          
        report_error("Unrecognized method in Fetch."); // @codecov ignore - will only be hit if error in our code
      }
    } catch(Exception $E) {
      report_warning("Exception caught!\n");
      report_info("Response: ". $E->getMessage());
    }
    return FALSE;
  }
  
  public function write_page($page, $text, $editSummary, $lastRevId = NULL, $startedEditing = NULL) {
    $response = $this->fetch(array(
            'action' => 'query',
            'prop' => 'info|revisions',
            'rvprop' => 'timestamp',
            'meta' => 'tokens',
            'titles' => $page
          ));
    
    if (!$response) {
      report_error("Write request failed");
    }
    if (isset($response->warnings)) {
      if (isset($response->warnings->prop)) {
        report_error((string) $response->warnings->prop->{'*'});
      }
      if (isset($response->warnings->info)) {
        report_error((string) $response->warnings->info->{'*'});
      }
    }
    if (!isset($response->batchcomplete)) {
      report_error("Write request triggered no response from server");
    }
    
    $myPage = reset($response->query->pages); // reset gives first element in list
    
    if (!isset($myPage->lastrevid)) {
      report_error("Page seems not to exist. Aborting.");
    }
    $baseTimeStamp = $myPage->revisions[0]->timestamp;
    
    if ((!is_null($lastRevId) && $myPage->lastrevid != $lastRevId)
     || (!is_null($startedEditing) && strtotime($baseTimeStamp) > strtotime($startedEditing))) {
      report_minor_error("Possible edit conflict detected. Aborting.");
      return FALSE;
    }
    if (stripos($text, "CITATION_BOT_PLACEHOLDER") != FALSE)  {
      report_minor_error("\n ! Placeholder left escaped in text. Aborting.");
      return FALSE;
    }
    
    // No obvious errors; looks like we're good to go ahead and edit
    $auth_token = $response->query->tokens->csrftoken; // Citation bot tokens
    $submit_vars = array(
        "action" => "edit",
        "title" => $page,
        "text" => $text,
        "summary" => $editSummary,
        "minor" => "1",
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
      report_error("Write error: " . 
                    echoable(strtoupper($result->error->code)) . ": " . 
                    str_replace(array("You ", " have "), array("This bot ", " has "), 
                    echoable($result->error->info)));
    } elseif (isset($result->edit)) {
      if (isset($result->edit->captcha)) {
        report_error("Write error: We encountered a captcha, so can't be properly logged in.");
      } elseif ($result->edit->result == "Success") {
        // Need to check for this string whereever our behaviour is dependant on the success or failure of the write operation
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
    } else {
      if (!getenv('TRAVIS')) report_error("Unhandled write error.  Please copy this output and " .
                    "<a href='https://github.com/ms609/citation-bot/issues/new'>" .
                    "report a bug.</a>");
      return FALSE;
    }
  }
  
  public function category_members($cat){
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
        report_error('Error reading API for category ' . echoable($cat) . "\n\n");
      }
      $vars["cmcontinue"] = isset($res->continue) ? $res->continue->cmcontinue : FALSE;
    } while ($vars["cmcontinue"]);
    set_time_limit(120);
    return $list;
  }
  
  // Returns an array; Array ("title1", "title2" ... );
  public function what_transcludes($template, $namespace = 99){
    $titles = $this->what_transcludes_2($template, $namespace);
    return $titles["title"];
  }
  protected function what_transcludes_2($template, $namespace = 99) {
    
    $vars = Array (
      "action" => "query",
      "list" => "embeddedin",
      "eilimit" => "5000",
      "eititle" => "Template:" . $template,
      "einamespace" => ($namespace==99)?"":$namespace,
    );
    $list = ['title' => NULL];
    
    do {
      set_time_limit(20);
      $res = $this->fetch($vars, 'POST');
      if (isset($res->query->embeddedin->ei) || $res === FALSE) {
        report_error('Error reading API for template/namespace: ' . echoable($template) . '/' . echoable(($namespace==99)?"Normal":$namespace));
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
  public function get_last_revision($page) {
    $res = $this->fetch(Array(
        "action" => "query",
        "prop" => "revisions",
        "titles" => $page,
      ));
    if (!isset($res->query->pages)) {
        report_error("Failed to get article's last revision");
        return FALSE;
    }
    $page = reset($res->query->pages);
    return  (isset($page->revisions[0]->revid) ? $page->revisions[0]->revid : FALSE);
  }
  public function get_prefix_index($prefix, $namespace = 0, $start = "") {
    $page_titles = [];
    # $page_ids = [];
    $vars["apfrom"] = $start;
    $vars = ["action" => "query",
      "list" => "allpages",
      "apnamespace" => $namespace,
      "apprefix" => $prefix,
      "aplimit" => "500",
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
        report_error('Error reading API with vars ' . http_build_query($vars));
        if (isset($res->error)) echo $res->error;
      }
      $vars["apfrom"] = isset($res->continue) ? $res->continue->apcontinue : FALSE;
    } while ($vars["apfrom"]);
    set_time_limit(120);
    return $page_titles;
  }
  public function get_namespace($page) {
    $res = $this->fetch([
        "action" => "query",
        "prop" => "info",
        "titles" => $page,
        ]); 
    if (!isset($res->query->pages)) {
        report_warning("Failed to get article namespace");
        return FALSE;
    }
    return (int) reset($res->query->pages)->ns;
  }
  # @return -1 if page does not exist; 0 if exists and not redirect; 1 if is redirect
  static public function is_redirect($page, $api = NULL) {
    if (self::$last_WikipediaBot == NULL) {
       new WikipediaBot(TRUE);
    }
    if ($api == NULL) { // Nother passed in
        $api = self::$last_WikipediaBot;
    }
    if ($api == NULL) {
        report_error('No API found in is_redirect()');
    }
    $res = $api->fetch(Array(
        "action" => "query",
        "prop" => "info",
        "titles" => $page,
        ), 'POST');
    
    if (!isset($res->query->pages)) {
        report_warning("Failed to get redirect status");
        return -1;
    }
    $res = reset($res->query->pages);
    return (isset($res->missing) ? -1 : (isset($res->redirect) ? 1 : 0));
  }
  public function redirect_target($page) {
    $res = $this->fetch(Array(
        "action" => "query",
        "redirects" => "1",
        "titles" => $page,
        ), 'POST');
    if (!isset($res->query->redirects[0]->to)) {
        report_warning("Failed to get redirect target");
        return FALSE;
    }
    return $res->query->redirects[0]->to;
  }
  public function namespace_id($name) {
    $lc_name = strtolower($name);
    return array_key_exists($lc_name, NAMESPACE_ID) ? (int) NAMESPACE_ID[$lc_name] : 0;
  }
  public function namespace_name($id) {
    return array_key_exists($id, NAMESPACES) ? NAMESPACES[$id] : NULL;
  }
  
  static public function is_valid_user($user) {
    if (!$user) return FALSE;
    $response = @file_get_contents('https://en.wikipedia.org/w/api.php?action=query&usprop=blockinfo&format=json&list=users&ususers=' . urlencode(str_replace(" ", "_", $user)));
    if ($response == FALSE) return FALSE;
    $response = str_replace(array("\r", "\n"), '', $response);  // paranoid
    if (strpos($response, '"invalid"') !== FALSE) return FALSE; // IP Address and similar stuff
    if (strpos($response, '"blockid"') !== FALSE) return FALSE; // Valid but blocked
    if (strpos($response, '"missing"') !== FALSE) return FALSE; // No such account
    if (strpos($response, '"userid"')  === FALSE) return FALSE; // Double check, should actually never return FALSE here
    return TRUE;
  }
  private function authenticate_user() {
    if (isset($_SESSION['access_key']) && isset($_SESSION['access_secret'])) {
     try {
      $user_token = new Token($_SESSION['access_key'], $_SESSION['access_secret']);
      // Validate the credentials.
      $conf = new ClientConfig('https://en.wikipedia.org/w/index.php?title=Special:OAuth');
      $conf->setConsumer(new Consumer(getenv('PHP_WP_OAUTH_CONSUMER'), getenv('PHP_WP_OAUTH_SECRET')));
      $client = new Client($conf);
      $ident = $client->identify( $user_token );
      if (!$this->is_valid_user($ident->username)) {
        @session_destroy();
        report_error('User is either invalid or blocked on en.wikipedia.org');
      }
      $this->the_user = $ident->username;
      return;
     }
     catch (Throwable $e) { ; } // PHP 7
     catch (Exception $e) { ; } // PHP 5
    }
    @session_destroy();
    $return = urlencode($_SERVER['REQUEST_URI']);
    @header("Location: authenticate.php?return=$return");
    sleep(3);
    report_error('Valid user Token not found, go to <a href="authenticate.php">authenticate.php</a>');
  }
}
