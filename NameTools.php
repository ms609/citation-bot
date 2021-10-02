<?php
declare(strict_types=1);

require_once "constants.php";    // @codeCoverageIgnore 

/* junior_test - tests a name for a Junior appellation
 *  Input: $name - the name to be tested
 * Output: array ($name without Jr, if $name ends in Jr, Jr)
 */
function junior_test(string $name) : array {
  $junior = (substr($name, -3) == " Jr")?" Jr":"";
  if ($junior) {
    $name = substr($name, 0, -3);
  } else {
    $junior = (substr($name, -4) == " Jr.")?" Jr.":"";
    if ($junior) {
      $name = substr($name, 0, -4);
    }
  }
  if (substr($name, -1) == ",") {
    $name = substr($name, 0, -1);
  }
  return array($name, $junior);
}

function format_surname(string $surname) : string {
  if ($surname == '-') return '';
  if (preg_match('~^\S\.?$~u', $surname)) return mb_strtoupper($surname); // Just a single initial, with or without period
  $surname = mb_convert_case(trim(mb_ereg_replace("-", " - ", $surname)), MB_CASE_LOWER);
  if (mb_substr($surname, 0, 2) == "o'") {
        return "O'" . format_surname_2(mb_substr($surname, 2));
  } elseif (mb_substr($surname, 0, 2) == "mc") {
        return "Mc" . format_surname_2(mb_substr($surname, 2));
  } elseif (mb_substr($surname, 0, 3) == "mac" && strlen($surname) > 5 && !mb_strpos($surname, "-") && mb_substr($surname, 3, 1) != "h") {
        return "Mac" . format_surname_2(mb_substr($surname, 3));
  } elseif (mb_substr($surname, 0, 1) == "&") {
        return "&" . format_surname_2(mb_substr($surname, 1));
  } else {
        return format_surname_2($surname); // Case of surname
  }
}

function format_surname_2(string $surname) : string {
  $ret = preg_replace_callback("~(\p{L})(\p{L}+)~u", 
        function(array $matches) : string {
                return mb_strtoupper($matches[1]) . mb_strtolower($matches[2]);
        },
    mb_ereg_replace(" - ", "-", $surname));
  $ret = str_ireplace(array('Von ', 'Und ', 'De La '), array('von ', 'und ', 'de la '), $ret);
  $ret = preg_replace_callback('~;\w~', function(array $matches) : string {return strtolower($matches[0]);}, $ret);
  return $ret;
}

function format_forename(string $forename) : string {
  if ($forename == '-') return '';
  return str_replace(array(" ."), "", trim(preg_replace_callback("~(\p{L})(\p{L}{3,})~u",  function(
            array $matches) : string {
            return mb_strtoupper($matches[1]) . mb_strtolower($matches[2]);}
         , $forename)));
}

/* format_initials
 * Returns a string of initals, formatted for Cite Doi output
 *
 * $str: A series of initials, in any format.  NOTE! Do not pass a forename here!
 *
 */
function format_initials(string $str) : string {
  $match = ['', '']; // prevent memory leak in some PHP versions
  $str = trim($str);
        if ($str == "") return "";
        $end = (substr($str, strlen($str)-1) == ";") ? ";" : '';
        preg_match_all("~\w~", $str, $match);
        return mb_strtoupper(implode(".",$match[0]) . ".") . $end;
}

function is_initials(string $str) : bool {
        $str = trim($str);
        if (!$str) return FALSE;
        if (strlen(str_replace(array("-", ".", ";"), "", $str)) >3) return FALSE;
        if (strlen(str_replace(array("-", ".", ";"), "", $str)) ==1) return TRUE;
        if (mb_strtoupper($str) != $str) return FALSE;
        return TRUE;
}

/*
 * author_is_human
 * Runs some tests to see if the full name of a single author is unlikely to be the name of a person.
 */
function author_is_human(string $author) : bool {
  $author = trim($author);
  $chars = count_chars($author);
  if ($chars[ord(":")] > 0 || $chars[ord(" ")] > 3 || strlen($author) > 33
    || substr(strtolower($author), 0, 4) === "the " 
    || (str_ireplace(NON_HUMAN_AUTHORS, '', $author) != $author)  // This is the use a replace to see if a substring is present trick
    || preg_match("~[A-Z]{3}~", $author)
    || substr(strtolower($author),-4) === " inc"
    || substr(strtolower($author),-5) === " inc."
    || substr(strtolower($author),-4) === " llc"
    || substr(strtolower($author),-5) === " llc."
    || substr(strtolower($author),-5) === " book"
    || substr(strtolower($author),-5) === " books"
    || substr_count($author, ' ') > 3 // Even if human, hard to format
  ) {
    return FALSE;
  }
  return TRUE;
}

// Returns the author's name formatted as Surname, F.I.
function format_author(string $author) : string {
  
  // Requires an author who is formatted as SURNAME, FORENAME or SURNAME FORENAME or FORENAME SURNAME. Substitute initials for forenames if nec.
  $surname = '';
  // Google and Zotero sometimes have these (sir) and just sir
  $author = preg_replace("~ ?\((?i)sir(?-i)\.?\)~", "", html_entity_decode($author, ENT_COMPAT | ENT_HTML401, 'UTF-8'));
  $author = preg_replace("~^( ?sir )~", "", $author);
  $author = preg_replace("~^(, sir )~", ", ", $author);
  
  $ends_with_period = (substr(trim($author), -1) === ".");
  
  $author = preg_replace("~(^[;,.\s]+|[;,.\s]+$)~", "", trim($author)); //Housekeeping
  $author = preg_replace("~^[aA]nd ~", "", trim($author)); // Just in case it has been split from a Smith; Jones; and Western
  if ($author == "") {
      return "";
  }

  $auth = explode(",", $author);
  if (isset($auth[1])) {
    /* Possibilities:
    Smith, A. B.
    */
    $surname = $auth[0];
    $fore = $auth[1];
  }
  //Otherwise we've got no handy comma to separate; we'll have to use spaces and periods.
  else {
    $auth = explode(".", $author);
    if (isset($auth[1])){
      /* Possibilities are:
      M.A. Smith
      Smith M.A.
      Smith MA.
      Martin A. Smith
      MA Smith.
      Martin Smith.
      */
      $countAuth = count($auth);
      if ($ends_with_period) {
        $i = array();
        // it ends in a .
        if (is_initials($auth[$countAuth-1])) {
          // it's Conway Morris S.C.
          foreach (explode(" ", $auth[0]) as $bit){
            if (is_initials($bit)) $i[] = format_initials($bit); else $surname .= "$bit ";
          }
          unset($auth[0]);
          foreach ($auth as $bit){
            if (is_initials($bit)) {
              $i[] = format_initials($bit) . '.';
            } else {
              $i[] = $bit;
            }
          }
        } else {
          foreach ($auth as $A) {
            if (is_initials($A)) {
                $i[] = format_initials($A) . '.';
            } else {
                $i[] = $A;
            }
          }
        }
        $fore = mb_strtoupper(implode(" ", $i));
      } else {
        // it ends with the surname
        $surname = $auth[$countAuth-1];
        unset($auth[$countAuth-1]);
        $fore = implode(".", $auth);
      }
    } else {
      // We have no punctuation! Let's delimit with spaces.
      $chunks = array_reverse(explode(" ", $author));
      $i = array();
      foreach ($chunks as $chunk){
        if (!$surname && !is_initials($chunk)) $surname = $chunk;
        else array_unshift($i, is_initials($chunk)?format_initials($chunk):$chunk);
      }
      $fore = implode(" ", $i);
    }
  }
  // Special cases when code cannot fully determine things, or if the name is only Smith
  if (trim($surname) == '') { // get this with A. B. C.
    $full_name = format_forename($fore);
  } elseif (trim($fore) == '') {  // Get this with just Smith
    $full_name = format_surname($surname);
  } else {
    $full_name = format_surname($surname) . ", " . format_forename($fore);
  }
  $full_name = str_replace("..", ".", $full_name);  // Sometimes add period after period
  $full_name = str_replace(".", ". ", $full_name);  // Add spaces after all periods
  $full_name = str_replace(["   ", "  "], [" ", " "], $full_name); // Remove extra spaces
  return trim($full_name);
}

function format_multiple_authors(string $authors) : string {
  $authors = html_entity_decode($authors, ENT_COMPAT | ENT_HTML401, "UTF-8");

  $return = array();
  ## Split the citation into an author by author account
  $authors = preg_replace(array("~\band\b~i", "~[\d\+\*]+~"), ";", $authors); //Remove "and" and affiliation symbols

  $authors = str_replace(array("&nbsp;", "(", ")"), array(" "), $authors); //Remove spaces and weird puntcuation
  $authors = str_replace(array(".,", "&", "  "), ";", $authors); //Remove "and"
  if (preg_match("~[,;]$~", trim($authors))) $authors = substr(trim($authors), 0, strlen(trim($authors))-1); // remove trailing punctuation

  $authors = trim($authors);
  if ($authors == "") {
    return '';
  }

  $authors = explode(";", $authors);
  $savedChunk = '';
  $bits = array();
  if (isset($authors[1])) {
    foreach ($authors as $A){
      if (trim($A) != "") $return[] = format_author($A);
    }
  } else {
    //Use commas as delimiters
    $chunks = explode(",", $authors[0]);
    foreach ($chunks as $chunk){
      $chunk = trim($chunk);
      if ($chunk == '') continue; // Odd things with extra commas
      $bits = explode(" ", $chunk);
      $bitts = array();
      foreach ($bits as $bit){
        if ($bit) $bitts[] = $bit;
      }
      $bits = $bitts; unset($bitts);
      if (isset($bits[1]) || $savedChunk) {
        $return[] = format_author($savedChunk .  ($savedChunk?", ":"") . $chunk);
        $savedChunk = '';
      } else {
        $savedChunk = $chunk;// could be first author, or an author with no initials, or a surname with initials to follow.
      }
    }
  }
  if ($savedChunk) $return[0] = @$bits[0];
  $return = implode("; ", $return);
  $frags = explode(" ", $return);
  $return = array();
  foreach ($frags as $frag){
    $return[] = is_initials($frag)?format_initials($frag):$frag;
  }
  $returnString = preg_replace("~;$~", "", trim(implode(" ", $return)));
  return $returnString;
}

function under_two_authors(string $text) : bool {
  return !(strpos($text, ';') !== FALSE  //if there is a semicolon
          || substr_count($text, ',') > 1  //if there is more than one comma
          || substr_count($text, ',') < substr_count(trim($text), ' ')  //if the number of commas is less than the number of spaces in the trimmed string
          );
}

/* split_authors
 * Assumes that there is more than one author to start with; 
 * check this using under_two_authors()
 */
function split_authors(string $str) : array {
  if (strpos($str, ';')) return explode(';', $str);
  return explode(',', $str);
}
