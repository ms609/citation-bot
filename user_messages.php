<?php
  
function html_echo($text, $alternate_text='') {
  if (!getenv('TRAVIS')) echo HTML_OUTPUT ? $text : $alternate_text;
}

function user_notice($symbol, $class, $text) {
  if (!getenv('TRAVIS')) {
    echo "\n " . (HTML_OUTPUT ? "<span class='$class'>" : "")
     . "$symbol $text" . (HTML_OUTPUT ? "</span>" : "");
  }
}

function report_phase($text)  { user_notice("\n>", "phase", $text); }
function report_action($text)  { user_notice(">", "subitem", $text); }
function report_info($text)  { user_notice("  >", "subsubitem", $text); }
function report_inaction($text)  { user_notice("  .", "boring", $text); }
function report_warning($text) { user_notice("  !", "warning", $text); }
function report_modification($text) { user_notice("  ~", "changed", $text); }
function report_add($text) { user_notice("  +", "added", $text); }
function report_forget($text) { user_notice("  -", "removed", $text); }
function report_inline($text) { if (!getenv('TRAVIS')) echo " $text"; }

function quietly($function = report_info, $text) {
  if (defined('VERBOSE') || HTML_OUTPUT ) {
    $function($text);
  }
}

function safely_echo ($string) {
  echo echoable($string);
}

function echoable($string) {
  return HTML_OUTPUT ? htmlspecialchars($string) : $string;
}

function pubmed_link($identifier, $pm) {
  return HTML_OUTPUT 
       ? '<a href="https://www.ncbi.nlm.nih.gov/pubmed/' . urlencode($pm) . '" target="_blank">'
         . strtoupper($identifier) . ' ' . $pm . "</a>"
       : strtoupper($identifier) . ' ' . $pm;
}

function bibcode_link($id) {
  return HTML_OUTPUT
    ? '<a href="http://adsabs.harvard.edu/abs/' . urlencode($id) . '" target="_blank">'
    : $id;
}

function doi_link($doi) {
  return HTML_OUTPUT
    ? '<a href="http://dx.doi.org/' . urlencode($doi) . '" target="_blank">'
    : $doi;
}
