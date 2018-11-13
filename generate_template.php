<?php
// outputs a Wikipedia reference from a DOI 
// usage: https://tools.wmflabs.org/citations/generate_template.php?doi=<DOI>

header("Access-Control-Allow-Origin: *"); //This is ok because the API is not authenticated
header("Content-Type: text/plain");

include('expandFns.php');
$t = new Template();
$t->parse_text('{{cite web}}');
$count = 0;
foreach ($_GET as $param=>$value) {
  $count++;
  if ($count > 10) die;
  $t->set($param, $value);
}
if ($count === 0} die;
$t->process(); // better than calling expand_by_doi because it also sets the wikiname
echo "\n";
echo "\n";
print("<ref>".$t->parsed_text()."</ref>");
?>
