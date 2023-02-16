<?php
declare(strict_types=1);
set_time_limit(120);
// outputs a Wikipedia reference from a DOI 
// usage: https://citations.toolforge.org/generate_template.php?doi=<DOI>

require_once 'html_headers.php';

echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Make a Template</title></head><body><main><pre>';

require_once 'setup.php';

$t = new Template();
$t->parse_text('{{cite web }}');
if (count($_GET) !== 1) exit('Exactly one parameters must be passed</pre></body></html>');
foreach ($_GET as $param=>$value) {
  if (!is_string($param) || !is_string($value)) {
    exit('Invalid parameter type error for passed parameter</pre></body></html>');
  }
  /** The user sent this in, so we declare it to not be tainted, and we do some checking */
  /** @psalm-taint-escape ssrf
      @psalm-taint-escape has_quotes
      @psalm-taint-escape html */
  $value = mb_strtolower($value);
  /** @psalm-taint-escape ssrf
      @psalm-taint-escape has_quotes
      @psalm-taint-escape html */
  $param = mb_strtolower($param);
  if (strlen($value) === 0) exit('Unset parameter error</pre></body></html>');
  if ((strpos($value, "'") !== FALSE ) || (strpos($value, '"') !== FALSE ) || (strpos($value, "|") !== FALSE ) || (strpos($value, " ") !== FALSE )) {
     exit('Invalid parameter value error</pre></body></html>');
  }
  if (!in_array($param, PARAMETER_LIST)) exit('Unknown parameter passed</pre></body></html>');
  $t->set($param, $value);
}

$page = new Page();
$page->parse_text($t->parsed_text());
unset($t);
$page->expand_text();

echo "\n\n" . echoable('<ref>' . $page->parsed_text() . '</ref>') . "\n\n</pre></main></body></html>";

?>
