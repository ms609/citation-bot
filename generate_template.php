<?php

declare(strict_types=1);

const VALID_PARAMS = ['jstor', 'doi', 'pmc', 's2cid', 'pmid', 'hdl', 'osti', 'isbn', 'lccn', 'ol', 'oclc'];
set_time_limit(120);

@header("Access-Control-Allow-Origin: null"); // This should not be set, this API is for humans

// usage: https://citations.toolforge.org/generate_template.php?doi=<DOI> and such

echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>Make a Template</title></head><body><main><pre>';

require_once 'setup.php';

function die_in_template(string $err): never {
    echo $err, '</pre></main></body></html>'; // @codeCoverageIgnore
    exit; // @codeCoverageIgnore
} 

if (count($_GET) !== 1) {
    die_in_template('Exactly one parameters must be passed'); // @codeCoverageIgnore
}
$param = array_keys($_GET)[0];
$value = $_GET[$param];
unset($_GET, $_POST, $_REQUEST); // Memory minimize

if (!is_string($param) || !is_string($value)) {
    die_in_template('Invalid parameter type error for passed parameter'); // @codeCoverageIgnore
}
if (strlen($value) < 3) {
    die_in_template('Unset parameter error'); // @codeCoverageIgnore
}
if (strlen($value) > 100) {
    die_in_template('Excessive parameter error'); // @codeCoverageIgnore
}
if ((strpos($value, "'") !== false) || (strpos($value, '"') !== false) || (strpos($value, "|") !== false) || (strpos($value, " ") !== false)) {
    die_in_template('Invalid parameter value error'); // @codeCoverageIgnore
}
$param = mb_strtolower($param);
if (!in_array($param, VALID_PARAMS, true)) {
    die_in_template('Unexpected parameter passed'); // @codeCoverageIgnore
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
