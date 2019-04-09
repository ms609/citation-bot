<?php
// outputs a Wikipedia reference from a DOI 
// usage: https://tools.wmflabs.org/citations/generate_template.php?doi=<DOI>

header("Access-Control-Allow-Origin: *"); //This is ok because the API is not authenticated

echo "<!DOCTYPE html><html><body><pre>\n";
$SLOW_MODE = TRUE;
require_once('setup.php');
$t = new Template();
$t->parse_text('{{Cite web}}');
if (count($_GET) > 10) exit('Excessive number of parameters passed</pre></body></html>');
if (count($_GET) === 0) exit('No parameters passed</pre></body></html>');
foreach ($_GET as $param=>$value) {
  if (strlen($param . $value) > 256) exit('Excessively long parameter passed</pre></body></html>');
  $t->set($param, $value);
}

$page = new Page();
$page->parse_text($t->parsed_text());
$page->expand_text();
echo "\n";
echo "\n";
echo("<ref>".$page->parsed_text()."</ref>\n</pre></body></html>");
exit(0);

?>
