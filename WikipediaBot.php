<?php
require_once("credentials/wiki.php");

class WikipediaBot {
  
  private $oauth, $ch;
  
  function __construct() {
    $this->oauth = new OAuth(OAUTH_CONSUMER_TOKEN, OAUTH_CONSUMER_SECRET, 
                             OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_AUTHORIZATION);
    $this->oauth->setToken(OAUTH_ACCESS_TOKEN, OAUTH_ACCESS_SECRET);
    $this->oauth->enableDebug();
    $this->oauth->setSSLChecks(0);
    $this->oauth->setRequestEngine(OAUTH_REQENGINE_CURL);
  }
  
  function __destruct() {
    if ($this->ch) curl_close($this->ch);
  }
      
  public function log_in() {
    $response = $this->fetch(array('action' => 'query', 'meta'=>'tokens', 'type'=>'login'));
    if (!isset($response->batchcomplete)) return FALSE;
    if (!isset($response->query->tokens->logintoken)) return FALSE;
    
    $lgVars = ['action' => 'login',
               'lgname' => WP_USERNAME, 'lgpassword' => WP_PASSWORD,
               'lgtoken' => $response->query->tokens->logintoken,
              ];
              
    $response = $this->fetch($lgVars, 'POST');
    if (!isset($response->login->result)) return FALSE;
    if ($response->login->result == "Success") return TRUE;
    trigger_error($response->login->reason, E_USER_WARNING);
    return FALSE;
  }
  
  private function ret_okay($response) {
    if ($response === CURLE_HTTP_RETURNED_ERROR) {
      trigger_error("Curl encountered HTTP response error", E_USER_ERROR);
    }
    if (isset($response->error)) {
      trigger_error((string) $response->error->info, E_USER_ERROR);
      return FALSE;
    }
    return TRUE;
  }
  
  private function reset_curl() {
    if (!$this->ch) {
      $this->ch = curl_init();
      if (!$this->log_in()) {
        curl_close($this->ch);
        trigger_error("Could not log in to Wikipedia servers", E_USER_ERROR);
      }        
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
        CURLOPT_USERAGENT => 'Citation bot',
        #CURLOPT_XOAUTH2_BEARER => OAUTH_ACCESS_TOKEN,
        #CURLOPT_HTTPHEADER => ###,
      ]);
  }
  
  public function fetch($params, $method = 'GET') {
    if (!$this->reset_curl()) {
      curl_close($this->ch);
      trigger_error('Could not initialize CURL resource: ' .
        htmlspecialchars(curl_error($this->ch)), E_USER_ERROR);
      return FALSE;
    }
    $check_logged_in = ((isset($params['type']) && $params['type'] == 'login') 
      || (isset($params['action']) && $params['action'] == 'login')) ? FALSE : TRUE;
    if ($check_logged_in) $params['assert'] = 'user';
    $params['format'] = 'json';
    
    try {
      switch (strtolower($method)) {
        
        case 'get':
          $url = API_ROOT . '?' . http_build_query($params);
          $header = 'Authentication: ' . 
            $this->oauth->getRequestHeader(OAUTH_HTTP_METHOD_POST, $url);
            
          curl_setopt_array($this->ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [$header],
          ]);
          
          print "\n - $url";
          $ret = json_decode($data = curl_exec($this->ch));
          if (!$data) {
            trigger_error("Curl error: " . htmlspecialchars(curl_error($this->ch)), E_USER_NOTICE);
            return FALSE;
          }
          if (isset($ret->error) && $ret->error->code == 'assertuserfailed') {
            $this->log_in();
            return $this->fetch($params, $method);
          }
          return ($this->ret_okay($ret)) ? $ret : FALSE;
          
        case 'post':
        
          $header = 'Authentication: ' . $this->oauth->getRequestHeader(
            OAUTH_HTTP_METHOD_POST, API_ROOT, http_build_query($params));
          curl_setopt_array($this->ch, [
            CURLOPT_POST => TRUE,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [$header],
          ]);
          
          $ret = json_decode($data = curl_exec($this->ch));
          if ( !$data ) {
            echo "\n ! Curl error: " . htmlspecialchars(curl_error($this->ch));
            exit(0);
          }
          
          if (isset($ret->error) && $ret->error->code == 'assertuserfailed') {
            $this->log_in();
            return $this->fetch($params, $method);
          }
          
          return ($this->ret_okay($ret)) ? $ret : FALSE;
          
        echo " ! Unrecognized method."; // @codecov ignore - will only be hit if error in our code
        return NULL;
      }
    } catch(OAuthException $E) {
      echo " ! Exception caught!\n";
      echo "   Response: ". $E->lastResponse . "\n";
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
    
    if (!$response) return FALSE;
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
      echo "\n ! Possible edit conflict detected. Aborting.";
      return FALSE;
    }
    if (stripos($text, "CITATION_BOT_PLACEHOLDER") != FALSE)  {
      trigger_error("\n ! Placeholder left escaped in text. Aborting.", E_USER_WARNING);
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
      echo "\n ! Write error: " . htmlspecialchars(strtoupper($result->error->code)) . ": " . str_replace(array("You ", " have "), array("This bot ", " has "), htmlspecialchars($result->error->info));
      return FALSE;
    } elseif (isset($result->edit)) {
      if (isset($result->edit->captcha)) {
        echo "\n ! Write error: We encountered a captcha, so can't be properly logged in.\n";
        return FALSE;
      } elseif ($result->edit->result == "Success") {
        // Need to check for this string whereever our behaviour is dependant on the success or failure of the write operation
        if (HTML_OUTPUT) {
          echo "\n <span style='color: #e21'>Written to <a href='" 
          . WIKI_ROOT . "?title=" . urlencode($myPage->title) . "'>" 
          . htmlspecialchars($myPage->title) . '</a></span>';
        }
        else echo "\n Written to " . htmlspecialchars($myPage->title) . '.  ';
        return TRUE;
      } elseif (isset($result->edit->result)) {
        echo "\n ! " . htmlspecialchars($result->edit->result);
        return FALSE;
      }
    } else {
      echo "\n ! Unhandled write error.  Please copy this output and <a href=https://github.com/ms609/citation-bot/issues/new>report a bug.</a>";
      return FALSE;
    }
  }  
}

