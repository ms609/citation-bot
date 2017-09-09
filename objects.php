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

global $author_parameters;
$author_parameters = array(
    1  => array('surname'  , 'forename'  , 'initials'  , 'first'  , 'last'  , 'author',
                'surname1' , 'forename1' , 'initials1' , 'first1' , 'last1' , 'author1', 'authors', 'vauthors'),
    2  => array('surname2' , 'forename2' , 'initials2' , 'first2' , 'last2' , 'author2' , 'coauthors', 'coauthor'),
    3  => array('surname3' , 'forename3' , 'initials3' , 'first3' , 'last3' , 'author3' ),
    4  => array('surname4' , 'forename4' , 'initials4' , 'first4' , 'last4' , 'author4' ),
    5  => array('surname5' , 'forename5' , 'initials5' , 'first5' , 'last5' , 'author5' ),
    6  => array('surname6' , 'forename6' , 'initials6' , 'first6' , 'last6' , 'author6' ),
    7  => array('surname7' , 'forename7' , 'initials7' , 'first7' , 'last7' , 'author7' ),
    8  => array('surname8' , 'forename8' , 'initials8' , 'first8' , 'last8' , 'author8' ),
    9  => array('surname9' , 'forename9' , 'initials9' , 'first9' , 'last9' , 'author9' ),
    10 => array('surname10', 'forename10', 'initials10', 'first10', 'last10', 'author10'),
    11 => array('surname11', 'forename11', 'initials11', 'first11', 'last11', 'author11'),
    12 => array('surname12', 'forename12', 'initials12', 'first12', 'last12', 'author12'),
    13 => array('surname13', 'forename13', 'initials13', 'first13', 'last13', 'author13'),
    14 => array('surname14', 'forename14', 'initials14', 'first14', 'last14', 'author14'),
    15 => array('surname15', 'forename15', 'initials15', 'first15', 'last15', 'author15'),
    16 => array('surname16', 'forename16', 'initials16', 'first16', 'last16', 'author16'),
    17 => array('surname17', 'forename17', 'initials17', 'first17', 'last17', 'author17'),
    18 => array('surname18', 'forename18', 'initials18', 'first18', 'last18', 'author18'),
    19 => array('surname19', 'forename19', 'initials19', 'first19', 'last19', 'author19'),
    20 => array('surname20', 'forename20', 'initials20', 'first20', 'last20', 'author20'),
    21 => array('surname21', 'forename21', 'initials21', 'first21', 'last21', 'author21'),
    22 => array('surname22', 'forename22', 'initials22', 'first22', 'last22', 'author22'),
    23 => array('surname23', 'forename23', 'initials23', 'first23', 'last23', 'author23'),
    24 => array('surname24', 'forename24', 'initials24', 'first24', 'last24', 'author24'),
    25 => array('surname25', 'forename25', 'initials25', 'first25', 'last25', 'author25'),
    26 => array('surname26', 'forename26', 'initials26', 'first26', 'last26', 'author26'),
    27 => array('surname27', 'forename27', 'initials27', 'first27', 'last27', 'author27'),
    28 => array('surname28', 'forename28', 'initials28', 'first28', 'last28', 'author28'),
    29 => array('surname29', 'forename29', 'initials29', 'first29', 'last29', 'author29'),
    30 => array('surname30', 'forename30', 'initials30', 'first30', 'last30', 'author30'),
    31 => array('surname31', 'forename31', 'initials31', 'first31', 'last31', 'author31'),
    32 => array('surname32', 'forename32', 'initials32', 'first32', 'last32', 'author32'),
    33 => array('surname33', 'forename33', 'initials33', 'first33', 'last33', 'author33'),
    34 => array('surname34', 'forename34', 'initials34', 'first34', 'last34', 'author34'),
    35 => array('surname35', 'forename35', 'initials35', 'first35', 'last35', 'author35'),
    36 => array('surname36', 'forename36', 'initials36', 'first36', 'last36', 'author36'),
    37 => array('surname37', 'forename37', 'initials37', 'first37', 'last37', 'author37'),
    38 => array('surname38', 'forename38', 'initials38', 'first38', 'last38', 'author38'),
    39 => array('surname39', 'forename39', 'initials39', 'first39', 'last39', 'author39'),
    40 => array('surname40', 'forename40', 'initials40', 'first40', 'last40', 'author40'),
    41 => array('surname41', 'forename41', 'initials41', 'first41', 'last41', 'author41'),
    42 => array('surname42', 'forename42', 'initials42', 'first42', 'last42', 'author42'),
    43 => array('surname43', 'forename43', 'initials43', 'first43', 'last43', 'author43'),
    44 => array('surname44', 'forename44', 'initials44', 'first44', 'last44', 'author44'),
    45 => array('surname45', 'forename45', 'initials45', 'first45', 'last45', 'author45'),
    46 => array('surname46', 'forename46', 'initials46', 'first46', 'last46', 'author46'),
    47 => array('surname47', 'forename47', 'initials47', 'first47', 'last47', 'author47'),
    48 => array('surname48', 'forename48', 'initials48', 'first48', 'last48', 'author48'),
    49 => array('surname49', 'forename49', 'initials49', 'first49', 'last49', 'author49'),
    50 => array('surname50', 'forename50', 'initials50', 'first50', 'last50', 'author50'),
    51 => array('surname51', 'forename51', 'initials51', 'first51', 'last51', 'author51'),
    52 => array('surname52', 'forename52', 'initials52', 'first52', 'last52', 'author52'),
    53 => array('surname53', 'forename53', 'initials53', 'first53', 'last53', 'author53'),
    54 => array('surname54', 'forename54', 'initials54', 'first54', 'last54', 'author54'),
    55 => array('surname55', 'forename55', 'initials55', 'first55', 'last55', 'author55'),
    56 => array('surname56', 'forename56', 'initials56', 'first56', 'last56', 'author56'),
    57 => array('surname57', 'forename57', 'initials57', 'first57', 'last57', 'author57'),
    58 => array('surname58', 'forename58', 'initials58', 'first58', 'last58', 'author58'),
    59 => array('surname59', 'forename59', 'initials59', 'first59', 'last59', 'author59'),
    60 => array('surname60', 'forename60', 'initials60', 'first60', 'last60', 'author60'),
    61 => array('surname61', 'forename61', 'initials61', 'first61', 'last61', 'author61'),
    62 => array('surname62', 'forename62', 'initials62', 'first62', 'last62', 'author62'),
    63 => array('surname63', 'forename63', 'initials63', 'first63', 'last63', 'author63'),
    64 => array('surname64', 'forename64', 'initials64', 'first64', 'last64', 'author64'),
    65 => array('surname65', 'forename65', 'initials65', 'first65', 'last65', 'author65'),
    66 => array('surname66', 'forename66', 'initials66', 'first66', 'last66', 'author66'),
    67 => array('surname67', 'forename67', 'initials67', 'first67', 'last67', 'author67'),
    68 => array('surname68', 'forename68', 'initials68', 'first68', 'last68', 'author68'),
    69 => array('surname69', 'forename69', 'initials69', 'first69', 'last69', 'author69'),
    70 => array('surname70', 'forename70', 'initials70', 'first70', 'last70', 'author70'),
    71 => array('surname71', 'forename71', 'initials71', 'first71', 'last71', 'author71'),
    72 => array('surname72', 'forename72', 'initials72', 'first72', 'last72', 'author72'),
    73 => array('surname73', 'forename73', 'initials73', 'first73', 'last73', 'author73'),
    74 => array('surname74', 'forename74', 'initials74', 'first74', 'last74', 'author74'),
    75 => array('surname75', 'forename75', 'initials75', 'first75', 'last75', 'author75'),
    76 => array('surname76', 'forename76', 'initials76', 'first76', 'last76', 'author76'),
    77 => array('surname77', 'forename77', 'initials77', 'first77', 'last77', 'author77'),
    78 => array('surname78', 'forename78', 'initials78', 'first78', 'last78', 'author78'),
    79 => array('surname79', 'forename79', 'initials79', 'first79', 'last79', 'author79'),
    80 => array('surname80', 'forename80', 'initials80', 'first80', 'last80', 'author80'),
    81 => array('surname81', 'forename81', 'initials81', 'first81', 'last81', 'author81'),
    82 => array('surname82', 'forename82', 'initials82', 'first82', 'last82', 'author82'),
    83 => array('surname83', 'forename83', 'initials83', 'first83', 'last83', 'author83'),
    84 => array('surname84', 'forename84', 'initials84', 'first84', 'last84', 'author84'),
    85 => array('surname85', 'forename85', 'initials85', 'first85', 'last85', 'author85'),
    86 => array('surname86', 'forename86', 'initials86', 'first86', 'last86', 'author86'),
    87 => array('surname87', 'forename87', 'initials87', 'first87', 'last87', 'author87'),
    88 => array('surname88', 'forename88', 'initials88', 'first88', 'last88', 'author88'),
    89 => array('surname89', 'forename89', 'initials89', 'first89', 'last89', 'author89'),
    90 => array('surname90', 'forename90', 'initials90', 'first90', 'last90', 'author90'),
    91 => array('surname91', 'forename91', 'initials91', 'first91', 'last91', 'author91'),
    92 => array('surname92', 'forename92', 'initials92', 'first92', 'last92', 'author92'),
    93 => array('surname93', 'forename93', 'initials93', 'first93', 'last93', 'author93'),
    94 => array('surname94', 'forename94', 'initials94', 'first94', 'last94', 'author94'),
    95 => array('surname95', 'forename95', 'initials95', 'first95', 'last95', 'author95'),
    96 => array('surname96', 'forename96', 'initials96', 'first96', 'last96', 'author96'),
    97 => array('surname97', 'forename97', 'initials97', 'first97', 'last97', 'author97'),
    98 => array('surname98', 'forename98', 'initials98', 'first98', 'last98', 'author98'),
    99 => array('surname99', 'forename99', 'initials99', 'first99', 'last99', 'author99'),
);

/* FUNCTIONS */

/* Return a flat numerically indexed array containing all the parameters in
 * $author_parameters, and set the global variable $flattened_author_params
 * to that flat array.
 */
function flatten_author_parameters($author_parameters) {
  global $flattened_author_params;
  $flattened_author_params = array();

  foreach ($author_parameters as $i => $group) {
    $flattened_author_params = array_merge($flattened_author_params, $group);
  }

  return $flattened_author_params;
}

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
