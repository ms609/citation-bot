<?php
declare(strict_types=1);

require_once("constants.php");

function html_echo(string $text, string $alternate_text='') : void {
  if (!getenv('TRAVIS')) echo HTML_OUTPUT ? $text : $alternate_text;
}

function user_notice(string $symbol, string $class, string $text) : void {
  static $last_time = 0;
  global $FLUSHING_OKAY;
  if (!getenv('TRAVIS')) {
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

function report_phase(string $text) : void { user_notice("\n>", "phase", $text); }
function report_action(string $text) : void { user_notice(">", "subitem", $text); }
function report_info(string $text) : void { user_notice("  >", "subsubitem", $text); }
function report_inaction(string $text) : void { user_notice("  .", "boring", $text); }
function report_warning(string $text) : void  { user_notice("  !", "warning", $text); }
function report_modification(string $text) : void { user_notice("  ~", "changed", $text); }
function report_add(string $text) : void { user_notice("  +", "added", $text); }
function report_forget(string $text) : void { user_notice("  -", "removed", $text); }
function report_inline(string $text) : void { if (!getenv('TRAVIS')) echo " $text"; }
function report_error(string $text) : void { report_warning($text); trigger_error($text, E_USER_ERROR); } // call report_warning to give users a message before we die
function report_minor_error(string $text) : void {  // For things we want to error on TRAVIS, but continue on Wikipedia
  report_warning($text);                                                   // @codeCoverageIgnore
  if (getenv('TRAVIS')) trigger_error($text, E_USER_ERROR);                // @codeCoverageIgnore
}


function quietly(callable $function, string $text) : void { // Stuff suppressed when running on the command line
  if (HTML_OUTPUT || getenv('TRAVIS')) {
    $function($text);
  }
}

function echoable(?string $string) : string {
  return HTML_OUTPUT ? htmlspecialchars((string) $string) : (string) $string;
}

function pubmed_link(string $identifier, string $pm) : string {
  return HTML_OUTPUT 
       ? '<a href="https://www.ncbi.nlm.nih.gov/pubmed/' . urlencode($pm) . '" target="_blank">' . strtoupper($identifier) . ' ' . $pm . "</a>"   // @codeCoverageIgnore
       : strtoupper($identifier) . ' ' . $pm;
}

function bibcode_link(string $id) : string {
  return HTML_OUTPUT
    ? '<a href="https://ui.adsabs.harvard.edu/abs/' . urlencode($id) . '" target="_blank">' . $id . '</a>'   // @codeCoverageIgnore
    : $id;
}

function doi_link(string $doi) : string {
  return HTML_OUTPUT
    ? '<a href="https://dx.doi.org/' . urlencode($doi) . '" target="_blank">' . $doi . '</a>'      // @codeCoverageIgnore
    : $doi;
}

function jstor_link(string $id) : string {
  return HTML_OUTPUT
    ? '<a href="https://www.jstor.org/citation/ris/' . urlencode($id) . '" target="_blank">JSTOR ' . $id . '</a>'    // @codeCoverageIgnore
    : "JSTOR $id";
}

/**
 * Unused
 * @codeCoverageIgnore
 */
function wiki_link(string $page, string $style = "#036;", ?string $target = NULL) : string {
  if (!$target) $target = $page;
  $css = $style?" style='color:$style !important'":"";
  return "<a href='" . WIKI_ROOT . "?title=" . urlencode($target) . "' title='$page ($target) on Wikipedia'$css>$page</a>";
}
