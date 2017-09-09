<?php
/* Treats comments and templates as objects */

/* 
# TODO # 
 - Associate initials with surnames: don't put them on a new line
*/

// Include classes
require_once("Page.php");
require_once("Item.php");
require_once("Template.php");
require_once("Parameter.php");
require_once("Comment.php");

/* FUNCTIONS */

/** Returns a properly capitalised title.
 *      If sents is true (or there is an abundance of periods), it assumes it is dealing with a title made up of sentences, and capitalises the letter after any period.
  *             If not, it will assume it is a journal abbreviation and won't capitalise after periods.
 */
function capitalize_title($in, $sents = TRUE, $could_be_italics = TRUE) {
        if ($in == mb_strtoupper($in) && mb_strlen(str_replace(array("[", "]"), "", trim($in))) > 6) {
                $in = mb_convert_case($in, MB_CASE_TITLE, "UTF-8");
        }
  $in = str_ireplace(" (New York, N.Y.)", "", $in); // Pubmed likes to include this after "Science", for some reason
  if ($could_be_italics) $in = preg_replace('~([a-z]+)([A-Z][a-z]+\b)~', "$1 ''$2''", $in); // <em> tags often go missing around species namesin CrossRef
  $captIn = str_replace(dontCap, unCapped, " " .  $in . " ");
        if ($sents || (substr_count($in, '.') / strlen($in)) > .07) { // If there are lots of periods, then they probably mark abbrev.s, not sentance ends
    $newcase = preg_replace("~(\w\s+)A(\s+\w)~u", "$1a$2",
                                        preg_replace_callback("~\w{2}'[A-Z]\b~u" /*Apostrophes*/, create_function(
                    '$matches',
                    'return mb_strtolower($matches[0]);'
                ), preg_replace_callback("~[?.:!]\s+[a-z]~u" /*Capitalise after punctuation*/, create_function(
                    '$matches',
                    'return mb_strtoupper($matches[0]);'
                ), trim($captIn))));
        } else {
                $newcase = preg_replace("~(\w\s+)A(\s+\w)~u", "$1a$2",
                                        preg_replace_callback("~\w{2}'[A-Z]\b~u" /*Apostrophes*/, create_function(
                    '$matches',
                    'return mb_strtolower($matches[0]);'
                ), trim(($captIn))));
        }
  $newcase = preg_replace_callback("~(?:'')?(?P<taxon>\p{L}+\s+\p{L}+)(?:'')?\s+(?P<nova>(?:(?:gen\.? no?v?|sp\.? no?v?|no?v?\.? sp|no?v?\.? gen)\b[\.,\s]*)+)~ui", create_function('$matches',
          'return "\'\'" . ucfirst(strtolower($matches[\'taxon\'])) . "\'\' " . strtolower($matches["nova"]);'), $newcase);
  // Use 'straight quotes' per WP:MOS
  $newcase = straighten_quotes($newcase);
  if (in_array(" " . trim($newcase) . " ", $unCapped)) {
    // Keep "z/Journal" with lcfirst
    return $newcase;
  } else {
    // Catch "the Journal" --> "The Journal"
    $newcase = mb_convert_case(mb_substr($newcase, 0, 1), MB_CASE_TITLE, "UTF-8") . mb_substr($newcase, 1);
     return $newcase;
  }
}

function tag($long = FALSE) {
  $dbg = array_reverse(debug_backtrace());
  array_pop($dbg);
  array_shift($dbg);
  foreach ($dbg as $d) {
    if ($long) {
      $output = '> ' . $d['function'];
    } else {
      $output = '> ' . substr(preg_replace('~_(\w)~', strtoupper("$1"), $d['function']), -7);
    }
  }
  echo ' [..' . htmlspecialchars($output) . ']';
}
