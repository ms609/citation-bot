<?php
// outputs a Wikipedia reference from a DOI 
// usage: https://tools.wmflabs.org/citations/doi2ref.php?doi=<DOI>
// example: https://tools.wmflabs.org/citations/doi2ref.php?doi=

header("Access-Control-Allow-Origin: *"); //This is ok because the API is not authenticated
header("Content-Type: text/plain");

include('expandFns.php');
$t = new Template();
$t->name = 'cite web';
foreach ($_GET as $param=>$value) $t->set($param, $value);
$t->process(); // better than calling expand_by_doi because it also sets the wikiname
echo "\n";
echo "\n";
print("<ref>".$t->parsed_text()."</ref>");
?>
