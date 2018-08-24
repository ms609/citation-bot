<?php
// To use the oauthclient library, run:
// composer require mediawiki/oauthclient
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use MediaWiki\OAuthClient\Request;
use MediaWiki\OAuthClient\SignatureMethod\HmacSha1;

class WikipediaBot {
  
  protected $consumer, $token, $ch;
  
  function __construct() {
    if (!getenv('PHP_OAUTH_CONSUMER_TOKEN') && file_exists('env.php')) {
      // An opportunity to set the PHP_OAUTH_ environment variables used in this function,
      // if they are not set already. Remember to set permissions (not readable!)
      include_once('env.php'); 
    }
    if (!getenv('PHP_OAUTH_CONSUMER_TOKEN')) trigger_error("PHP_OAUTH_CONSUMER_TOKEN not set", E_USER_ERROR);
    if (!getenv('PHP_OAUTH_ACCESS_TOKEN')) trigger_error("PHP_OAUTH_ACCESS_TOKEN not set", E_USER_ERROR);
    $this->consumer = new Consumer(getenv('PHP_OAUTH_CONSUMER_TOKEN'), getenv('PHP_OAUTH_CONSUMER_SECRET'));
    $this->token = new Token(getenv('PHP_OAUTH_ACCESS_TOKEN'), getenv('PHP_OAUTH_ACCESS_SECRET'));
  }
  
  function __destruct() {
    if ($this->ch) curl_close($this->ch);
  }
  
  public function username() {
    $userQuery = $this->fetch(['action' => 'query', 'meta' => 'userinfo']);
    return (isset($userQuery->query->userinfo->name)) ? $userQuery->query->userinfo->name : FALSE;
  }
  
  private function ret_okay($response) {
    if ($response === CURLE_HTTP_RETURNED_ERROR) {
      trigger_error("Curl encountered HTTP response error", E_USER_ERROR);
    }
    if (isset($response->error)) {
      if ($response->error->code == 'blocked') {
        trigger_error('Account "' . $this->username() . 
        '" or this IP is blocked from editing.', E_USER_ERROR); // Yes, Travis CI IPs are blocked, even to logged in users.
      } else {
        trigger_error('API call failed: ' . $response->error->info, E_USER_ERROR);
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
        CURLOPT_FAILONERROR => TRUE, // #TODO Remove this line once debugging complete
        CURLOPT_FOLLOWLOCATION => TRUE,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_HEADER => FALSE, // Don't include header in output
        CURLOPT_HTTPGET => TRUE, // Reset to default GET
        CURLOPT_RETURNTRANSFER => TRUE,
        
        CURLOPT_CONNECTTIMEOUT_MS => 1200,
        
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
      trigger_error('Could not initialize CURL resource: ' .
        echoable(curl_error($this->ch)), E_USER_ERROR);
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
          
          $ret = @json_decode($data = curl_exec($this->ch));
          if (!$data) {
            trigger_error("Curl error: " . echoable(curl_error($this->ch)), E_USER_NOTICE);
            return FALSE;
          }
          if (isset($ret->error->code) && $ret->error->code == 'assertuserfailed') {
            return $this->fetch($params, $method);
          }
          return ($this->ret_okay($ret)) ? $ret : FALSE;
          
        case 'post':
          curl_setopt_array($this->ch, [
            CURLOPT_POST => TRUE,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [$authenticationHeader],
          ]);
          
          $ret = @json_decode($data = curl_exec($this->ch));
          if ( !$data ) {
            report_warning("Curl error: " . echoable(curl_error($this->ch)));
            exit(0);
          }
          
          if (isset($ret->error) && $ret->error->code == 'assertuserfailed') {
            return $this->fetch($params, $method);
          }
          
          return ($this->ret_okay($ret)) ? $ret : FALSE;
          
        report_warning("Unrecognized method."); // @codecov ignore - will only be hit if error in our code
        return NULL;
      }
    } catch(OAuthException $E) {
      report_warning("Exception caught!\n");
      report_info("Response: ". $E->lastResponse);
    }
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
      trigger_error("Write request failed", E_USER_WARNING);
      return FALSE;
    }
    if (isset($response->warnings)) {
      if (isset($response->warnings->prop)) {
        trigger_error((string) $response->warnings->prop->{'*'}, E_USER_WARNING);
      }
      if (isset($response->warnings->info)) {
        trigger_error((string) $response->warnings->info->{'*'}, E_USER_WARNING);
      }
    }
    if (!isset($response->batchcomplete)) {
      trigger_error("Write request triggered no response from server", E_USER_WARNING);
      return FALSE;
    }
    
    $myPage = reset($response->query->pages); // reset gives first element in list
    
    if (!isset($myPage->lastrevid)) {
      trigger_error("Page seems not to exist. Aborting.", E_USER_WARNING);
      return FALSE;
    }
    $baseTimeStamp = $myPage->revisions[0]->timestamp;
    
    if ((!is_null($lastRevId) && $myPage->lastrevid != $lastRevId)
     || (!is_null($startedEditing) && strtotime($baseTimeStamp) > strtotime($startedEditing))) {
      trigger_error("Possible edit conflict detected. Aborting.", E_USER_WARNING);
      return FALSE;
    }
    if (stripos($text, "CITATION_BOT_PLACEHOLDER") != FALSE)  {
      trigger_error("\n ! Placeholder left escaped in text. Aborting.", E_USER_ERROR);
      return FALSE;
    }
    
    // No obvious errors; looks like we're good to go ahead and edit
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
        'token' => $response->query->tokens->csrftoken,
    );
    $result = $this->fetch($submit_vars, 'POST');
    
    if (isset($result->error)) {
      trigger_error("Write error: " . 
                    echoable(strtoupper($result->error->code)) . ": " . 
                    str_replace(array("You ", " have "), array("This bot ", " has "), 
                    echoable($result->error->info)), E_USER_ERROR);
      return FALSE;
    } elseif (isset($result->edit)) {
      if (isset($result->edit->captcha)) {
        trigger_error("Write error: We encountered a captcha, so can't be properly logged in.", E_USER_ERROR);
        return FALSE;
      } elseif ($result->edit->result == "Success") {
        // Need to check for this string whereever our behaviour is dependant on the success or failure of the write operation
        if (HTML_OUTPUT) {
          echo "\n <span style='reddish'>Written to <a href='" 
          . WIKI_ROOT . "?title=" . urlencode($myPage->title) . "'>" 
          . echoable($myPage->title) . '</a></span>';
        }
        else echo "\n Written to " . echoable($myPage->title) . '.  ';
        return TRUE;
      } elseif (isset($result->edit->result)) {
        report_warning(echoable($result->edit->result));
        return FALSE;
      }
    } else {
      trigger_error("Unhandled write error.  Please copy this output and " .
                    "<a href='https://github.com/ms609/citation-bot/issues/new'>" .
                    "report a bug.</a>", E_USER_ERROR);
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
          // We probably only want to visit pages in the main namespace.  Remove any talk: etc at the start of the page name.
          $list[] = str_replace(array('_talk:', ' talk:'), ':', (string) $page->title); 
        }
      } else {
        trigger_error('Error reading API from ' . echoable($url) . "\n\n", E_USER_WARNING);
      }
      $vars["cmcontinue"] = isset($res->continue) ? $res->continue->cmcontinue : FALSE;
    } while ($vars["cmcontinue"]);
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
      if (isset($res->query->embeddedin->ei)) {
        trigger_error('Error reading API from ' . echoable($url), E_USER_NOTICE);
      } else {
        foreach($res->query->embeddedin as $page) {
          $list["title"][] = $page->title;
          $list["id"][] = $page->pageid;
        }
      }
      $vars["eicontinue"] = isset($res->continue) ? (string) $res->continue->eicontinue : FALSE;
    } while ($vars["eicontinue"]);
    return $list;
  }

  /**
   * Unused
   * @codeCoverageIgnore
   */
  public function wikititle_encode($in) {
    return str_replace(DOT_DECODE, DOT_ENCODE, $in);
  }

  public function get_last_revision($page) {
    $res = $this->fetch(Array(
        "action" => "query",
        "prop" => "revisions",
        "titles" => $page,
      ));
    if (!isset($res->query->pages)) {
        trigger_error("Failed to get article's last revision", E_USER_NOTICE);
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
        trigger_error('Error reading API with vars ' . http_build_query($vars), E_USER_NOTICE);
        if (isset($res->error)) echo $res->error;
      }
      $vars["apfrom"] = isset($res->continue) ? $res->continue->apcontinue : FALSE;
    } while ($vars["apfrom"]);
    set_time_limit(45);
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

  # @return -1 if page does not exist; 0 if exists and not redirect; 1 if is redirect.
  public function is_redirect($page) {
    $res = $this->fetch(Array(
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

  /**
   * Unused
   * @codeCoverageIgnore
   */
  public function redirect_target($page) {
    $res = $this->fetch(Array(
        "action" => "query",
        "redirects" => "1",
        "titles" => $page,
        ), 'POST');
    if (!isset($res->pages->page)) {
        report_warning("Failed to get redirect target");
        return FALSE;
    }
    return $xml->pages->page["title"];
  }

  /**
   * Unused
   * @codeCoverageIgnore
   */
  public function parse_wikitext($text, $title = "API") {
    $vars = array(
          'format' => 'json',
          'action' => 'parse',
          'text'   => $text,
          'title'  => $title,
      );
    $res = $this->fetch($vars, 'POST');
    if (!$res) {
      // Wait a sec and try again
      sleep(2);
      $res = $this->fetch($vars, 'POST');
    }
    if (!isset($res->parse->text)) {
      trigger_error("Could not parse text of $title.", E_USER_WARNING);
      return FALSE;
    }
    return $res->parse->text->{"*"};
  }

  public function namespace_id($name) {
    $lc_name = strtolower($name);
    return array_key_exists($lc_name, NAMESPACE_ID) ? NAMESPACE_ID[$lc_name] : NULL;
  }

  public function namespace_name($id) {
    return array_key_exists($id, NAMESPACES) ? NAMESPACES[$id] : NULL;
  }

  // TODO mysql login is failing.
    /*
     * unused
   * @codeCoverageIgnore
   */
  public function article_id($page, $namespace = 0) {
    if (stripos($page, ':')) {
      $bits = explode(':', $page);
      if (isset($bits[2])) return NULL; # Too many colons; improperly formatted page name?
      $namespace = $this->namespace_id($bits[0]);
      if (is_null($namespace)) return NULL; # unrecognized namespace
      $page = $bits[1];
    }
    $page = addslashes(str_replace(' ', '_', strtoupper($page[0]) . substr($page,1)));
    $enwiki_db = udbconnect('enwiki_p', 'enwiki.labsdb');
    if (defined('PHP_VERSION_ID') && (PHP_VERSION_ID >= 50600)) { 
       $result = NULL; // mysql_query does not exist in PHP 7
    } else {
       $result = @mysql_query("SELECT page_id FROM page WHERE page_namespace='" . addslashes($namespace)
            . "' && page_title='$page'");
    }
    if (!$result) {
      echo @mysql_error();
      @mysql_close($enwiki_db);
      return NULL;
    }
    $results = @mysql_fetch_array($result, MYSQL_ASSOC);
    @mysql_close($enwiki_db);
    if (!$results) return NULL;
    return $results['page_id'];
  }

  /**
   * Unused
   * @codeCoverageIgnore
   */
  public function touch_page($page) {
    $text = $this->get_raw_wikitext($page);
    if ($text) {
      $this->write_page($page, $text, " Touching page to update categories.  ** THIS EDIT SHOULD PROBABLY BE REVERTED ** as page content will only be changed if there was an edit conflict.");
      return TRUE;
    } else {
      return FALSE;
    }
  }

}
