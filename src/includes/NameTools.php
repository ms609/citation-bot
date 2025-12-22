<?php

declare(strict_types=1);

require_once __DIR__ . '/constants.php';    // @codeCoverageIgnore

/* junior_test - tests a name for a Junior appellation
 * Input: $name - the name to be tested
 * Output: array ($name without Jr, if $name ends in Jr, Jr)
 */

/** @return array<string> */
function junior_test(string $name): array {
    $junior = mb_substr($name, -3) === " Jr" ? " Jr" : "";
    if ($junior) {
        $name = mb_substr($name, 0, -3);
    } else {
        $junior = mb_substr($name, -4) === " Jr." ? " Jr." : "";
        if ($junior) {
            $name = mb_substr($name, 0, -4);
        }
    }
    if (mb_substr($name, -1) === ",") {
        $name = mb_substr($name, 0, -1);
    }
    return [$name, $junior];
}

/** @return array<string> */
function split_author(string $value): array {
    if (mb_substr_count($value, ',') !== 1) {
        return [];
    }
    return explode(',', $value, 2);
}

function clean_up_full_names(string $value): string {
    $value = mb_trim($value);
    $value = str_replace([",;", " and;", " and ", " ;", "  ", "+", "*"], [";", ";", " and ", ";", " ", "", ""], $value);
    $value = mb_trim(straighten_quotes($value, true));
    if (mb_substr($value, -1) === '.') { // Do not lose last period
        $value = sanitize_string($value) . '.';
    } else {
        $value = sanitize_string($value);
    }
    return $value;
}

function clean_up_last_names(string $value): string {
    $value = mb_trim($value);
    $value = str_replace([",;", " and;", " and ", " ;", "  ", "+", "*"], [";", ";", " ", ";", " ", "", ""], $value);
    $value = mb_trim(straighten_quotes($value, true));
    if (mb_substr($value, -1) === '.') { // Do not lose last period
        $value = sanitize_string($value) . '.';
    } else {
        $value = sanitize_string($value);
    }
    return str_replace('..', '.', $value);
}

function clean_up_first_names(string $value): string {
    $value = mb_trim($value);
    $value = str_replace([",;", " and;", " and ", " ;", "  ", "+", "*"], [";", ";", " ", ";", " ", "", ""], $value);
    $value = mb_trim(straighten_quotes($value, true));
    if (mb_substr($value, -1) === '.') { // Do not lose last period
        $value = sanitize_string($value) . '.';
    } else {
        $value = sanitize_string($value);
    }
    if (mb_strlen($value) === 1) {
        $value .= '.';
    } elseif (mb_substr($value, -2, 1) === " ") {
        if (mb_strlen($value) === 3) { // Special case for "F M" -- add dots to both
            $value = mb_substr($value, 0, 1) . '. ' . mb_substr($value, -1, 1) . '.';
        } elseif (mb_strlen($value) > 3) { // Single character at end
             $value .= '.';
        }
    }
    return $value;
}

function format_surname(string $surname): string {
    $surname = mb_trim($surname);
    if ($surname === '-') {
        return '';
    }
    if ($surname === '') {
        return '';
    }
    if (preg_match('~^\S\.?$~u', $surname)) {
        return mb_strtoupper($surname); // Just a single initial, with or without period
    }
    $surname = mb_convert_case(mb_trim(mb_ereg_replace("-", " - ", $surname)), MB_CASE_LOWER);
    if (mb_substr($surname, 0, 2) === "o'") {
        return "O'" . format_surname_2(mb_substr($surname, 2));
    }
    if (mb_substr($surname, 0, 2) === "mc") {
        return "Mc" . format_surname_2(mb_substr($surname, 2));
    }
    if (mb_substr($surname, 0, 3) === "mac" && mb_strlen($surname) > 5 && !mb_strpos($surname, "-") && mb_substr($surname, 3, 1) !== "h") {
        return "Mac" . format_surname_2(mb_substr($surname, 3));
    }
    if (mb_substr($surname, 0, 1) === "&") {
        return "&" . format_surname_2(mb_substr($surname, 1));
    }
    return format_surname_2($surname); // Case of surname
}

function format_surname_2(string $surname): string {
    $ret = mb_ereg_replace(" - ", "-", $surname);
    $ret = preg_replace_callback("~(\p{L})(\p{L}+)~u",
        static function (array $matches): string {
                return mb_strtoupper($matches[1]) . mb_strtolower($matches[2]);
        },
        $ret);
    $ret = str_ireplace(['Von ', 'Und ', 'De La '], ['von ', 'und ', 'de la '], $ret);
    return safe_preg_replace_callback('~;\w~',
        static function (array $matches): string {
            return mb_strtolower($matches[0]);
        },
        $ret);
}

function format_forename(string $forename): string {
    $forename = mb_trim($forename);
    if ($forename === '-' || $forename === '') {
        return '';
    }
    return str_replace([" ."], "", mb_trim(preg_replace_callback("~(\p{L})(\p{L}{3,})~u",
            static function (array $matches): string {
                return mb_strtoupper($matches[1]) . mb_strtolower($matches[2]);
            },
            $forename)));
}

/**
 * Returns a string of initials
 *
 * @param string $str A series of initials, in any format.  NOTE! Do not pass a forename here!
 */
function format_initials(string $str): string {
    $str = mb_trim($str);
    if ($str === "") {
        return "";
    }
    $end = mb_substr($str, mb_strlen($str)-1) === ";" ? ";" : '';
    preg_match_all("~\w~u", $str, $match);
    return mb_strtoupper(implode(".", $match[0]) . ".") . $end;
}

function is_initials(string $str): bool {
    $str = mb_trim($str);
    if (!$str) {
        return false;
    }
    if (mb_strlen(str_replace(["-", ".", ";"], "", $str)) > 3) {
        return false;
    }
    if (mb_strlen(str_replace(["-", ".", ";"], "", $str)) === 1) {
        return true;
    }
    if (mb_strtoupper($str) !== $str) {
        return false;
    }
    return true;
}

/**
 * Runs some tests to see if the full name of a single author is unlikely to be the name of a person.
 */
function author_is_human(string $author): bool {
    $author = mb_trim($author);
    $chars = count_chars($author);
    if ($chars[ord(":")] > 0 || $chars[ord(" ")] > 3 || mb_strlen($author) > 33
        || mb_substr(mb_strtolower($author), 0, 4) === "the "
        || (str_ireplace(NON_HUMAN_AUTHORS, '', $author) !== $author)  // This is the use a replace to see if a substring is present trick
        || preg_match("~[A-Z]{3}~", $author)
        || mb_substr(mb_strtolower($author), -4) === " inc"
        || mb_substr(mb_strtolower($author), -5) === " inc."
        || mb_substr(mb_strtolower($author), -4) === " llc"
        || mb_substr(mb_strtolower($author), -5) === " llc."
        || mb_substr(mb_strtolower($author), -5) === " book"
        || mb_substr(mb_strtolower($author), -6) === " books"
        || mb_substr(mb_strtolower($author), -8) === " nyheter"
        || mb_substr_count($author, ' ') > 3 // Even if human, hard to format
    ) {
        return false;
    }
    return true;
}

/** Returns the author's name formatted as Surname, F.I. */
function format_author(string $author): string {
    // Requires an author who is formatted as SURNAME, FORENAME or SURNAME FORENAME or FORENAME SURNAME. Substitute initials for forenames if needed
    $surname = '';
    // Google and Zotero sometimes have these (sir) and just sir
    $author = preg_replace("~ ?\((?i)sir(?-i)\.?\)~", "", html_entity_decode($author, ENT_COMPAT | ENT_HTML401, 'UTF-8'));
    $author = preg_replace("~^( ?sir )~", "", $author);
    $author = preg_replace("~^(, sir )~", ", ", $author);

    $ends_with_period = (mb_substr(mb_trim($author), -1) === ".");

    $author = preg_replace("~(^[;,.\s]+|[;,.\s]+$)~", "", mb_trim($author)); //Housekeeping
    $author = preg_replace("~^[aA]nd ~", "", mb_trim($author)); // Just in case it has been split from a Smith; Jones; and Western
    if ($author === "") {
            return "";
    }

    $auth = explode(",", $author);
    if (isset($auth[1])) {
        /* Possibilities:
        Smith, A. B.
        */
        $surname = $auth[0];
        $fore = $auth[1];
    } else { //Otherwise we've got no handy comma to separate; we'll have to use spaces and periods.
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
                $i = [];
                // it ends in a .
                if (is_initials($auth[$countAuth-1])) {
                    // it's Conway Morris S.C.
                    foreach (explode(" ", $auth[0]) as $bit){
                        if (is_initials($bit)) {
                            $i[] = format_initials($bit);
                        } else {
                            $surname .= "{$bit} ";
                        }
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
            $i = [];
            foreach ($chunks as $chunk){
                if (!$surname && !is_initials($chunk)) {
                    $surname = $chunk;
                } else {
                    array_unshift($i, is_initials($chunk) ? format_initials($chunk) : $chunk);
                }
            }
            $fore = implode(" ", $i);
        }
    }
    // Special cases when code cannot fully determine things, or if the name is only Smith
    if (mb_trim($surname) === '') { // get this with A. B. C.
        $full_name = format_forename($fore);
    } elseif (mb_trim($fore) === '') {  // Get this with just Smith
        $full_name = format_surname($surname);
    } else {
        $full_name = format_surname($surname) . ", " . format_forename($fore);
    }
    $full_name = str_replace("..", ".", $full_name);  // Sometimes add period after period
    $full_name = str_replace(".", ". ", $full_name);  // Add spaces after all periods
    $full_name = str_replace(["   ", "  "], [" ", " "], $full_name); // Remove extra spaces
    return mb_trim($full_name);
}

function format_multiple_authors(string $authors): string {
    $authors = html_entity_decode($authors, ENT_COMPAT | ENT_HTML401, "UTF-8");

    $return = [];
    ## Split the citation into an author by author account
    $authors = preg_replace(["~\band\b~iu", "~[\d\+\*]+~u"], ";", $authors); //Remove "and" and affiliation symbols

    $authors = str_replace(["&nbsp;", "(", ")"], [" "], $authors); //Remove spaces and weird punctuation
    $authors = str_replace([".,", "&", "  "], ";", $authors); //Remove "and"
    if (preg_match("~[,;]$~", mb_trim($authors))) {
        $authors = mb_substr(mb_trim($authors), 0, mb_strlen(mb_trim($authors))-1); // remove trailing punctuation
    }

    $authors = mb_trim($authors);
    if ($authors === "") {
        return '';
    }

    $authors = explode(";", $authors);
    $savedChunk = '';
    $bits = [];
    if (isset($authors[1])) {
        foreach ($authors as $A){
            if (mb_trim($A) !== "") {
                $return[] = format_author($A);
            }
        }
    } else {
        //Use commas as delimiters
        $chunks = explode(",", $authors[0]);
        foreach ($chunks as $chunk){
            $chunk = mb_trim($chunk);
            if ($chunk === '') {
                continue; // Odd things with extra commas
            }
            $bits = explode(" ", $chunk);
            $bitts = [];
            foreach ($bits as $bit){
                if ($bit) {
                    $bitts[] = $bit;
                }
            }
            $bits = $bitts;
            unset($bitts);
            if (isset($bits[1]) || $savedChunk) {
                $return[] = format_author($savedChunk . ($savedChunk ? ", " : '') . $chunk);
                $savedChunk = '';
            } else {
                $savedChunk = $chunk;// could be first author, or an author with no initials, or a surname with initials to follow.
            }
        }
    }
    if ($savedChunk && isset($bits[0])) {
        $return[0] = $bits[0];
    }
    $return = implode("; ", $return);
    $frags = explode(" ", $return);
    $return = [];
    foreach ($frags as $frag){
        $return[] = is_initials($frag) ? format_initials($frag) : $frag;
    }
    return safe_preg_replace("~;$~", "", mb_trim(implode(" ", $return)));
}

function under_two_authors(string $text): bool {
    return !(mb_strpos($text, ';') !== false  //if there is a semicolon
            || mb_substr_count($text, ',') > 1  //if there is more than one comma
            || mb_substr_count($text, ',') < mb_substr_count(mb_trim($text), ' '));  //if the number of commas is less than the number of spaces in the shrunk string
}

/**
 * Assumes that there is more than one author to start with;
 * check this using under_two_authors()
 * @return array<string>
 */
function split_authors(string $str): array {
    if (mb_strpos($str, ';')) {
        return explode(';', $str);
    }
    return explode(',', $str);
}

/** Sometimes zotero lists the last name as "published" and puts the whole name in the first place or other silliness */
function is_bad_author(string $aut): bool {
    if ($aut === '|') {
        return true;
    }
    $aut = mb_strtolower($aut);
    if ($aut === 'published') {
        return true;
    }
    return false;
}
