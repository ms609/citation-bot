<?php
declare(strict_types=1);
// outputs a Wikipedia reference from a DOI 
// usage: https://citations.toolforge.org/generate_template.php?doi=<DOI>
@header( 'Content-type: text/html; charset=utf-8' );
@header("Content-Encoding: None", TRUE);
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Pragma: no-cache');
@header('Expires: 0');
?>

<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Make a Template</title></head><body><pre>

<?php
require_once('setup.php');

$t = new Template();
$t->parse_text('{{Cite web}}');
if (count($_GET) > 10) exit('Excessive number of parameters passed</pre></body></html>');
if (count($_GET) === 0) exit('No parameters passed</pre></body></html>');
foreach ($_GET as $param=>$value) {
  if (strlen($param . $value) > 256) exit('Excessively long parameter passed</pre></body></html>');
  if (strlen($param) == 0) exit('Parameter error</pre></body></html>');
  if (strlen($value) == 0) exit('Unset parameter error</pre></body></html>');
  $t->set($param, $value);
}

$page = new Page();
$page->parse_text($t->parsed_text());
$page->expand_text();

echo("\n\n<ref>" . htmlspecialchars($page->parsed_text()) . "</ref>\n\n</pre></body></html>");

?>
