<?php

declare(strict_types=1);

set_time_limit(120);

// usage: https://citations.toolforge.org/generate_template.php?doi=<DOI> and such
// We do not include html_headers.php, since this should be cachable

echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Make a Template</title></head><body><main><pre>';

require_once 'setup.php';

if (count($_GET) !== 1) {
    echo 'Exactly one parameters must be passed</pre></main></body></html>';
    exit;
}
$param = array_keys($_GET)[0];
$value = $_GET[$param];

if (!is_string($param) || !is_string($value)) {
    echo 'Invalid parameter type error for passed parameter</pre></main></body></html>';
    exit;
}
if (strlen($value) < 3) {
    echo 'Unset parameter error</pre></main></body></html>';
    exit;
}
if (strlen($value) > 100) {
    echo 'Excessive parameter error</pre></main></body></html>';
    exit;
}
if ((strpos($value, "'") !== false) || (strpos($value, '"') !== false) || (strpos($value, "|") !== false) || (strpos($value, " ") !== false)) {
    echo 'Invalid parameter value error</pre></main></body></html>';
    exit;
}
$param = mb_strtolower($param);
if (!in_array($param, ['jstor', 'doi', 'pmc', 's2cid', 'pmid', 'hdl', 'osti', 'isbn', 'lccn', 'ol', 'oclc'], true)) {
   echo 'Unexpected parameter passed</pre></main></body></html>';
   exit;
}

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

echo "\n\n", echoable('<ref>' . $text . '</ref>'), "\n\n</pre></main></body></html>";

?>
