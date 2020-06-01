<?php
  
function html_echo($text, $alternate_text='') {
  if (!getenv('TRAVIS')) echo HTML_OUTPUT ? $text : $alternate_text;
}

function user_notice($symbol, $class, $text) {
  static $last_time = 0;
  global $FLUSHING_OKAY;
  if (TRUE || !getenv('TRAVIS')) {
    echo "\n " . (HTML_OUTPUT ? "<span class='$class'>" : "")               // @codeCoverageIgnore
     . "$symbol $text" . (HTML_OUTPUT ? "</span>" : "");                    // @codeCoverageIgnore
  }
  if ($FLUSHING_OKAY) {
     $now = microtime(TRUE);
     if (in_array($class, array('phase', 'subitem', 'warning')) || 10 < ($now - $last_time)) {
       $last_time = $now;
       ob_flush();
     }
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
function report_inline($text) { if (TRUE || !getenv('TRAVIS')) echo " $text"; }
function report_error($text) { report_warning($text); trigger_error($text, E_USER_ERROR); } // call report_warning to give users a message before we die
function report_minor_error($text) {  // For things we want to error on TRAVIS, but continue on Wikipedia
  report_warning($text);                                                   // @codeCoverageIgnore
  if (getenv('TRAVIS')) trigger_error($text, E_USER_ERROR);                // @codeCoverageIgnore
}


function quietly($function = 'report_info', $text) {
  if (defined('VERBOSE') || HTML_OUTPUT ) {
    $function($text);
  }
}

/**
 * used when sending page output to webpage
 * @codeCoverageIgnore
 */
function safely_echo ($string) {
  echo echoable($string);
}

function echoable($string) {
  return HTML_OUTPUT ? htmlspecialchars($string) : $string;
}

function pubmed_link($identifier, $pm) {
  return HTML_OUTPUT 
       ? '<a href="https://www.ncbi.nlm.nih.gov/pubmed/' . urlencode($pm) . '" target="_blank">' . strtoupper($identifier) . ' ' . $pm . "</a>"   // @codeCoverageIgnore
       : strtoupper($identifier) . ' ' . $pm;
}

function bibcode_link($id) {
  return HTML_OUTPUT
    ? '<a href="https://ui.adsabs.harvard.edu/abs/' . urlencode($id) . '" target="_blank">' . $id . '</a>'   // @codeCoverageIgnore
    : $id;
}

function doi_link($doi) {
  return HTML_OUTPUT
    ? '<a href="https://dx.doi.org/' . urlencode($doi) . '" target="_blank">' . $doi . '</a>'      // @codeCoverageIgnore
    : $doi;
}

function jstor_link($id) {
  return HTML_OUTPUT
    ? '<a href="https://www.jstor.org/citation/ris/' . urlencode($id) . '" target="_blank">JSTOR ' . $id . '</a>'    // @codeCoverageIgnore
    : "JSTOR $id";
}

/**
 * Unused
 * @codeCoverageIgnore
 */
function wiki_link($page, $style = "#036;", $target = NULL) {
  if (!$target) $target = $page;
  $css = $style?" style='color:$style !important'":"";
  return "<a href='" . WIKI_ROOT . "?title=" . urlencode($target) . "' title='$page ($target) on Wikipedia'$css>$page</a>";
}
