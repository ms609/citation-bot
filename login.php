<?php
if (!defined("FAST_MODE")) {
  exit("expandFns.php must be called before login.php");
}

require_once(HOME . "credentials/doiBot.login");
quiet_echo("\n Establishing connection to Wikipedia servers with username " . USERNAME . "... ");

global $bot; // Snoopy class loaded in DOItools.php
// Set POST variables to retrieve a token
$submit_vars["format"] = "json";
$submit_vars["action"] = "login";
$submit_vars["lgname"] = USERNAME;
$submit_vars["lgpassword"] = PASSWORD;
// Submit POST variables and retrieve a token
$bot->submit(API_ROOT, $submit_vars);
if (!$bot->results) {
  exit("\n Could not log in to Wikipedia servers.  Edits will not be committed.\n");
}
$first_response = @json_decode($bot->results);
if ($first_response === FALSE) {
  exit("\n Could not log in to Wikipedia servers.  Edits will not be committed.\n");
}
$submit_vars["lgtoken"] = $first_response->login->token;
// Resubmit with new request (which has token added to post vars)
$bot->submit(API_ROOT, $submit_vars);
$login_result = @json_decode($bot->results);
if ($login_result && $login_result->login->result == "Success") {
  quiet_echo("\n Using account " . htmlspecialchars($login_result->login->lgusername) . ".");
  // Add other cookies, which are necessary to remain logged in.
  $cookie_prefix = "enwiki";
  $bot->cookies[$cookie_prefix . "UserName"] = $login_result->login->lgusername;
  $bot->cookies[$cookie_prefix . "UserID"] = $login_result->login->lguserid;
  $bot->cookies[$cookie_prefix . "Token"] = (isset($login_result->login->lgtoken)) 
                                          ? $login_result->login->lgtoken
                                          : NULL;
  $bot->cookies[$cookie_prefix . "_session"] = (isset($login_result->login->sessionid))
                                             ? $login_result->login->sessionid
                                             : NULL;
  return TRUE;
} else {
  exit("\n Could not log in to Wikipedia servers.  Edits will not be committed.\n");
}
