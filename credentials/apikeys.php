<?php
const CROSSREFUSERNAME = 'martins@gmail.com';
const NYTUSERNAME   = 'citation_bot';
const ADSABSAPIKEY  = 'Dl6Dp2GU1rOl3Nu3OkfAhee6ywC42rC5wh9dtpUk'; # Replace this with a working key
if ($_SERVER['REMOTE_ADDR'] == '132.456.789.123') {
  // restricted to wikipedia toolserver
  define('GOOGLE_KEY', '&key=AIzaSyBNhyC5a5EirreJEDQ1muw0ZBAmSMs8R4E');
} elseif (getenv('TRAVIS')) {
  // Restricted to Travis CI servers
  define ('GOOGLE_KEY', '&key=AIzaSyC7Sx7pAK5MsYY1yxeEHKmnU-P4WxGQPj4');
} else {
  trigger_error("No Google keys found for IP " . $_SERVER['REMOTE_ADDR'] . " // " . $_SERVER['HTTP_CLIENT_IP'] . ".  Specify one manually in credentials/apikeys.php.");
  print_r($_SERVER);
  print_r($_ENV);
  define ('GOOGLE_KEY', '');
  die;
}
