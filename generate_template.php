<?php
declare(strict_types=1);
set_time_limit(120);
// outputs a Wikipedia reference from a DOI
// usage: https://citations.toolforge.org/generate_template.php?doi=<DOI>

require_once 'html_headers.php';

echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Make a Template</title></head><body><main><pre>';

require_once 'setup.php';

if (count($_GET) !== 1) exit('Exactly one parameters must be passed</pre></body></html>');
$param = array_keys($_GET)[0];
$value = $_GET[$param];

if (!is_string($param) || !is_string($value)) {
    exit('Invalid parameter type error for passed parameter</pre></body></html>'); // @codeCoverageIgnore
}
if (strlen($value) < 3) exit('Unset parameter error</pre></body></html>');
if (strlen($value) > 100) exit('Excessive parameter error</pre></body></html>');
if ((strpos($value, "'") !== FALSE ) || (strpos($value, '"') !== FALSE ) || (strpos($value, "|") !== FALSE ) || (strpos($value, " ") !== FALSE )) {
     exit('Invalid parameter value error</pre></body></html>');  // @codeCoverageIgnore
}
$param = mb_strtolower($param);
if (!in_array($param, ['jstor', 'doi', 'pmc', 's2cid', 'pmid', 'hdl', 'osti', 'isbn', 'lccn', 'ol', 'oclc'])) exit('Unexpected parameter passed</pre></body></html>');

$t = new Template();
$t->parse_text('{{cite web }}');
  /** The user sent this in, so we declare it to not be tainted, and we do some checking */
  /** @psalm-taint-escape ssrf
      @psalm-taint-escape html */
$t->set($param, $value);
$text = $t->parsed_text();
unset($t, $param, $value);

$page = new Page();
$page->parse_text($text);
$page->expand_text();
$text = $page->parsed_text();
unset($page);

echo "\n\n" . echoable('<ref>' . $text . '</ref>') . "\n\n</pre></main></body></html>";

?>
