<?php
if (!defined("FAST_MODE")) {
  exit("expandFns.php must be called before login.php");
}

require_once(HOME . "credentials/doiBot.login");
quiet_echo("\n Establishing connection to Wikipedia servers with username " . USERNAME . "... ");
logIn(USERNAME, PASSWORD);

?>
