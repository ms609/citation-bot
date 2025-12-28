<?php

declare(strict_types=1);

require_once __DIR__ . '/constants.php';     // @codeCoverageIgnore
require_once __DIR__ . '/Template.php';      // @codeCoverageIgnore
require_once __DIR__ . '/big_jobs.php';      // @codeCoverageIgnore

const MONTH_SEASONS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'Winter', 'Spring', 'Summer', 'Fall', 'Autumn'];
const DAYS_OF_WEEKS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Mony', 'Tues', 'Wed', 'Thurs', 'Fri', 'Sat', 'Sun'];
const TRY_ENCODE = ["windows-1255", "maccyrillic", "windows-1253", "windows-1256", "tis-620", "windows-874", "iso-8859-11", "big5", "windows-1250"];
const INSANE_ENCODE = ['utf-8-sig', 'x-user-defined'];
const SANE_ENCODE = ['utf-8', 'iso-8859-1', 'windows-1252', 'unicode', 'us-ascii', 'none', 'iso-8859-7', 'latin1', '8859-1', '8859-7'];
const DOI_BAD_ENDS = ['.htm', '.html', '.jpg', '.jpeg', '.pdf', '.png', '.xml', '.full'];
const DOI_BAD_ENDS2 = ['/abstract', '/full', '/pdf', '/epdf', '/asset/', '/summary', '/short', '/meta', '/html', '/'];

// ============================================= String/Text functions ======================================

/**
 * Convert MathML elements to LaTeX syntax
 * Handles complex MathML structures like mmultiscripts, msup, msub, mfrac, etc.
 */
function convert_mathml_to_latex(string $mathml): string {
    // Remove mml: namespace prefix if present
    $mathml = str_replace(['<mml:', '</mml:'], ['<', '</'], $mathml);
    
    // Handle mmultiscripts for isotope notation: <mmultiscripts>base<mprescripts/>prescript</mmultiscripts>
    // Example: <mmultiscripts>Ni<mprescripts/><none/>67</mmultiscripts> -> ^{67}\mathrm{Ni}
    $mathml = preg_replace_callback(
        '~<mmultiscripts>(.*?)<mprescripts/>(.*?)</mmultiscripts>~s',
        static function (array $matches): string {
            $base = trim($matches[1]);
            $prescripts = trim($matches[2]);
            
            // Handle <none/> tags - they represent empty positions
            $parts = preg_split('~<none/>~', $prescripts);
            
            // For isotope notation: <none/>number means superscript on left (mass number)
            if (count($parts) === 2 && trim($parts[0]) === '') {
                $superscript = trim(strip_tags($parts[1]));
                // Wrap base in \mathrm if it's a chemical element (single capital or capital + lowercase)
                if (preg_match('~^[A-Z][a-z]?$~', $base)) {
                    return "^{" . $superscript . "}\\mathrm{" . $base . "}";
                }
                return "^{" . $superscript . "}" . $base;
            }
            
            // Default fallback
            return $base;
        },
        $mathml
    );
    
    // Handle msup (superscript): <msup><mi>x</mi><mn>2</mn></msup> -> x^{2}
    $mathml = preg_replace_callback(
        '~<msup>\s*<mi>(.*?)</mi>\s*<mn>(.*?)</mn>\s*</msup>~s',
        static function (array $matches): string {
            $base = trim($matches[1]);
            $super = trim($matches[2]);
            return $base . "^{" . $super . "}";
        },
        $mathml
    );
    
    // Handle msub (subscript): <msub><mi>H</mi><mn>2</mn></msub> -> H_{2}
    $mathml = preg_replace_callback(
        '~<msub>\s*<mi>(.*?)</mi>\s*<mn>(.*?)</mn>\s*</msub>~s',
        static function (array $matches): string {
            $base = trim($matches[1]);
            $sub = trim($matches[2]);
            return $base . "_{" . $sub . "}";
        },
        $mathml
    );
    
    // Handle msubsup (subscript and superscript): <msubsup><mi>x</mi><mn>1</mn><mn>2</mn></msubsup> -> x_{1}^{2}
    $mathml = preg_replace_callback(
        '~<msubsup>\s*<mi>(.*?)</mi>\s*<mn>(.*?)</mn>\s*<mn>(.*?)</mn>\s*</msubsup>~s',
        static function (array $matches): string {
            $base = trim($matches[1]);
            $sub = trim($matches[2]);
            $super = trim($matches[3]);
            return $base . "_{" . $sub . "}^{" . $super . "}";
        },
        $mathml
    );
    
    // Handle mfrac (fractions): <mfrac><mn>1</mn><mn>2</mn></mfrac> -> \frac{1}{2}
    $mathml = preg_replace_callback(
        '~<mfrac>\s*<m[ino]>(.*?)</m[ino]>\s*<m[ino]>(.*?)</m[ino]>\s*</mfrac>~s',
        static function (array $matches): string {
            $num = trim($matches[1]);
            $den = trim($matches[2]);
            return "\\frac{" . $num . "}{" . $den . "}";
        },
        $mathml
    );
    
    // Apply simple tag replacements from MML_TAGS constant
    $mathml = str_replace(array_keys(MML_TAGS), array_values(MML_TAGS), $mathml);
    
    // Clean up any remaining MathML tags (including <mrow> which is just a grouping element)
    $mathml = strip_tags($mathml);
    
    return $mathml;
}

// phpcs:ignore MediaWiki.Commenting.FunctionComment.WrongStyle
function wikify_external_text(string $title): string {
    $replacement = [];
    $placeholder = [];
    $title = safe_preg_replace_callback('~(?:\$\$)([^\$]+)(?:\$\$)~iu',
            static function (array $matches): string {
                return "<math>" . $matches[1] . "</math>";
            },
            $title);
    if (preg_match_all("~<(?:mml:)?math[^>]*>(.*?)</(?:mml:)?math>~", $title, $matches)) {
        $num_matches = count($matches[0]);
        for ($i = 0; $i < $num_matches; $i++) {
            // Use the new convert_mathml_to_latex function to handle complex MathML
            $converted_latex = convert_mathml_to_latex($matches[1][$i]);
            $replacement[$i] = '<math>' . $converted_latex . '</math>';
            $placeholder[$i] = sprintf(TEMP_PLACEHOLDER, $i);
            // Need to use a placeholder to protect contents from URL-safening
            $title = str_replace($matches[0][$i], $placeholder[$i], $title);
        }
        $title = str_replace(['<mo stretchy="false">', "<mo stretchy='false'>"], '', $title);
    }
    if (mb_substr($title, -6) === "&nbsp;") {
        $title = mb_substr($title, 0, -6);
    }
    if (mb_substr($title, -10) === "&amp;nbsp;") {
        $title = mb_substr($title, 0, -10);
    }
    // Sometimes stuff is encoded more than once
    $title = html_entity_decode($title, ENT_COMPAT | ENT_HTML401, "UTF-8");
    $title = html_entity_decode($title, ENT_COMPAT | ENT_HTML401, "UTF-8");
    $title = html_entity_decode($title, ENT_COMPAT | ENT_HTML401, "UTF-8");
    $title = safe_preg_replace("~\s+~", " ", $title);    // Remove all white spaces before
    if (mb_substr($title, -6) === "&nbsp;") {
        $title = mb_substr($title, 0, -6); // @codeCoverageIgnore
    }
    // Special code for ending periods
    while (mb_substr($title, -2) === "..") {
        $title = mb_substr($title, 0, -1);
    }
    if (mb_substr($title, -1) === ".") { // Ends with a period
        if (mb_substr_count($title, '.') === 1) { // Only one period
            $title = mb_substr($title, 0, -1);
        } elseif (mb_substr_count($title, ' ') === 0) { // No spaces at all and multiple periods
            /** do nothing */
        } else { // Multiple periods and at least one space
            $last_word_start = (int) mb_strrpos(' ' . $title, ' ');
            $last_word = mb_substr($title, $last_word_start);
            if (mb_substr_count($last_word, '.') === 1 && // Do not remove if something like D.C. or D. C.
                mb_substr($title, $last_word_start - 2, 1) !== '.') {
                $title = mb_substr($title, 0, -1);
            }
        }
    }
    $title = safe_preg_replace('~[\*]$~', '', $title);
    $title = title_capitalization($title, true);

    $htmlBraces = ["&lt;", "&gt;"];
    $angleBraces = ["<", ">"];
    $title = str_ireplace($htmlBraces, $angleBraces, $title);

    $originalTags = ['<title>', '</title>', '</ title>', 'From the Cover: ', '<SCP>', '</SCP>', '</ SCP>', '<formula>', '</formula>', '<roman>', '</roman>', ];
    $wikiTags = ['', '', '', '', '', '', '', '', '', '', ''];
    $title = str_ireplace($originalTags, $wikiTags, $title);
    $originalTags = ['<inf>', '</inf>'];
    $wikiTags = ['<sub>', '</sub>'];
    $title = str_ireplace($originalTags, $wikiTags, $title);
    $originalTags = ['.<br>', '.</br>', '.</ br>', '.<p>', '.</p>', '.</ p>', '.<strong>', '.</strong>', '.</ strong>'];
    $wikiTags = ['. ', '. ', '. ', '. ', '. ', '. ', '. ', '. ', '. '];
    $title = str_ireplace($originalTags, $wikiTags, $title);
    $originalTags = ['<br>', '</br>', '</ br>', '<p>', '</p>', '</ p>', '<strong>', '</strong>', '</ strong>'];
    $wikiTags = ['. ', '. ', '. ', '. ', '. ', '. ', ' ', ' ', ' '];
    $title = mb_trim(str_ireplace($originalTags, $wikiTags, $title));
    if (preg_match("~^\. (.+)$~", $title, $matches)) {
        $title = mb_trim($matches[1]);
    }
    if (preg_match("~^(.+)(\.\s+)\.$~s", $title, $matches)) {
        $title = mb_trim($matches[1] . ".");
    }
    $title_orig = '';
    while ($title !== $title_orig) {
        $title_orig = $title;    // Might have to do more than once.     The following do not allow < within the inner match since the end tag is the same :-( and they might nest or who knows what
        $title = safe_preg_replace_callback('~(?:<Emphasis Type="Italic">)([^<]+)(?:</Emphasis>)~iu',
            static function (array $matches): string {
                return "''" . $matches[1] . "''";
            },
            $title);
        $title = safe_preg_replace_callback('~(?:<Emphasis Type="Bold">)([^<]+)(?:</Emphasis>)~iu',
            static function (array $matches): string {
                return "'''" . $matches[1] . "'''";
            },
            $title);
        $title = safe_preg_replace_callback('~(?:<em>)([^<]+)(?:</em>)~iu',
            static function (array $matches): string {
                return "''" . $matches[1] . "''";
            },
            $title);
        $title = safe_preg_replace_callback('~(?:<i>)([^<]+)(?:</i>)~iu',
            static function (array $matches): string {
                return "''" . $matches[1] . "''";
            },
            $title);
        $title = safe_preg_replace_callback('~(?:<italics>)([^<]+)(?:</italics>)~iu',
            static function (array $matches): string {
                return "''" . $matches[1] . "''";
            },
            $title);
    }

    if (mb_substr($title, -1) === '.') {
        $title = sanitize_string($title) . '.';
    } else {
        $title = sanitize_string($title);
    }

    $title = str_replace(['​'], [' '], $title); // Funky spaces

    $title = str_ireplace('<p class="HeadingRun \'\'In\'\'">', ' ', $title);

    $title = str_ireplace(['        ', '     ', '    '], [' ', ' ', ' '], $title);
    if (mb_strlen($title) === mb_strlen($title)) {
        $title = mb_trim($title, " \t\n\r\0\x0B\xc2\xa0");
    } else {
        $title = mb_trim($title, " \t\n\r\0");
    }

    $num_replace = count($replacement);
    for ($i = 0; $i < $num_replace; $i++) {
        $title = str_ireplace($placeholder[$i], $replacement[$i], $title); // @phan-suppress-current-line PhanTypePossiblyInvalidDimOffset
    }

    foreach (['<mroot>', '<munderover>', '<munder>', '<mtable>', '<mtr>', '<mtd>'] as $mathy) {
        if (mb_strpos($title, $mathy) !== false) {
            return '<nowiki>' . $title . '</nowiki>';
        }
    }
    return $title;
}

function restore_italics (string $text): string {
    $text = mb_trim(str_replace(['              ', '            ', '        ', '       ', '    '], [' ', ' ', ' ', ' ', ' '], $text));
    // <em> tags often go missing around species names in CrossRef
    /** $old = $text; */
    $text = str_replace(ITALICS_HARDCODE_IN, ITALICS_HARDCODE_OUT, $text); // Ones to always do, since they keep popping up in our logs
    $text = str_replace("xAzathioprine therapy for patients with systemic lupus erythematosus", "Azathioprine therapy for patients with systemic lupus erythematosus", $text); // Annoying stupid bad data
    $text = mb_trim(str_replace(['              ', '            ', '        ', '       ', '    '], [' ', ' ', ' ', ' ', ' '], $text));
    while (preg_match('~([a-z])(' . ITALICS_LIST . ')([A-Z\-\?\:\.\)\(\,]|species|genus| in| the|$)~', $text, $matches)) {
        if (in_array($matches[3], [':', '.', '-', ','], true)) {
            $pad = "";
        } else {
            $pad = " ";
        }
        $text = str_replace($matches[0], $matches[1] . " ''" . $matches[2] . "''" . $pad . $matches[3], $text);
    }
    $text = mb_trim(str_replace(['              ', '            ', '        ', '       ', '    '], [' ', ' ', ' ', ' ', ' '], $text));
    $padded = ' ' . $text . ' ';
    if (str_replace(CAMEL_CASE, '', $padded) !== $padded) {
        return $text; // Words with capitals in the middle, but not the first character
    }
    $new = safe_preg_replace('~([a-z]+)([A-Z][a-z]+\b)~', "$1 ''$2''", $text);
    if ($new === $text) {
        return $text;
    }
    // Do not return $new, since we are wrong much more often here than wrong with new CrossRef Code
    bot_debug_log('restore_italics: ' . $text . '               SHOULD BE           ' . $new); // @codeCoverageIgnore
    return $text; // @codeCoverageIgnore
}

function sanitize_string(string $str): string {
    // ought only be applied to newly-found data.
    if ($str === '') {
        return '';
    }
    if (mb_strtolower(mb_trim($str)) === 'science (new york, n.y.)') {
        return 'Science';
    }
    if (preg_match('~^\[http.+\]$~', $str)) {
        return $str; // It is a link out
    }
    $replacement = [];
    $placeholder = [];
    $math_templates_present = preg_match_all("~<\s*math\s*>.*<\s*/\s*math\s*>~", $str, $math_hits);
    if ($math_templates_present) {
        $num_maths = count($math_hits[0]);
        for ($i = 0; $i < $num_maths; $i++) {
            $replacement[$i] = $math_hits[0][$i];
            $placeholder[$i] = sprintf(TEMP_PLACEHOLDER, $i);
        }
        $str = str_replace($replacement, $placeholder, $str);
    }
    $dirty = ['[', ']', '|', '{', '}', " what�s "];
    $clean = ['&#91;', '&#93;', '&#124;', '&#123;', '&#125;', " what's "];
    $str = mb_trim(str_replace($dirty, $clean, safe_preg_replace('~[;.,]+$~', '', $str)));
    if ($math_templates_present) {
        $str = str_replace($placeholder, $replacement, $str);
    }
    return $str;
}

function truncate_publisher(string $p): string {
    return safe_preg_replace("~\s+(group|inc|ltd|publishing)\.?\s*$~i", "", $p);
}

function str_remove_irrelevant_bits(string $str): string {
    if ($str === '') {
        return '';
    }
    $str = mb_trim($str);
    $str = str_replace('�', 'X', $str);
    $str = safe_preg_replace(REGEXP_PLAIN_WIKILINK, "$1", $str);     // Convert [[X]] wikilinks into X
    $str = safe_preg_replace(REGEXP_PIPED_WIKILINK, "$2", $str);     // Convert [[Y|X]] wikilinks into X
    $str = mb_trim($str);
    $str = safe_preg_replace("~^the\s+~i", "", $str);    // Ignore leading "the" so "New York Times" == "The New York Times"
    $str = safe_preg_replace("~\s~u", ' ', $str);
    // punctuation
    $str = str_replace(['.', ',', ';', ': ', "…"], [' ', ' ', ' ', ' ', ' '], $str);
    $str = str_replace([':', '-', '&mdash;', '&ndash;', '—', '–'], ['', '', '', '', '', ''], $str);
    $str = str_replace(['       ', '    '], [' ', ' '], $str);
    $str = str_replace(" & ", " and ", $str);
    $str = str_replace(" / ", " and ", $str);
    $str = str_ireplace(["®", "&reg;", "(r)"], [' ', ' ', ' '], $str);
    $str = str_replace(['   ', '  '], [' ', ' '], $str);
    $str = mb_trim($str);
    $str = str_ireplace(
        ['Proceedings', 'Proceeding', 'Symposium', 'Huffington ', 'the Journal of ', 'nytimes.com', '& ', '(Clifton, N.J.)', '(Clifton NJ)'],
        ['Proc', 'Proc', 'Sym', 'Huff ', 'journal of ', 'New York Times', 'and ', '', ''],
        $str
    );
    $str = str_ireplace(['<sub>', '<sup>', '<i>', '<b>', '</sub>', '</sup>', '</i>', '</b>', '<p>', '</p>', '<title>', '</title>'], '', $str);
    $str = str_ireplace(
        ['SpringerVerlag', 'Springer Verlag Springer', 'Springer Verlag', 'Springer Springer'],
        ['Springer', 'Springer', 'Springer', 'Springer'],
        $str
    );
    $str = straighten_quotes($str, true);
    $str = str_replace("′", "'", $str);
    $str = safe_preg_replace('~\(Incorporating .*\)$~i', '', $str);  // Physical Chemistry Chemical Physics (Incorporating Faraday Transactions)
    $str = safe_preg_replace('~\d+ Volume Set$~i', '', $str);    // Ullmann's Encyclopedia of Industrial Chemistry, 40 Volume Set
    $str = safe_preg_replace('~^Retracted~i', '', $str);
    $str = safe_preg_replace('~\d?\d? ?The ?sequence ?of ?\S+ ?has ?been ?deposited ?in ?the ?GenBank ?database ?under ?accession ?number ?\S+ ?\d?~i', '', $str);
    $str = safe_preg_replace('~(?:\:\.\,)? ?(?:an|the) official publication of the.+$~i', '', $str);
    $str = mb_trim($str);
    return strip_diacritics($str);
}

/** See also titles_are_similar() */
function str_equivalent(string $str1, string $str2): bool {
    if (str_i_same(str_remove_irrelevant_bits($str1), str_remove_irrelevant_bits($str2))) {
        return true;
    }
    if (string_is_book_series($str1) && string_is_book_series($str2)) { // Both series, but not the same
        $str1 = mb_trim(str_replace(COMPARE_SERIES_IN, COMPARE_SERIES_OUT, mb_strtolower($str1)));
        $str2 = mb_trim(str_replace(COMPARE_SERIES_IN, COMPARE_SERIES_OUT, mb_strtolower($str2)));
        if ($str1 === $str2) {
            return true;
        }
    }
    return false;
}

/** See also str_equivalent() */
function titles_are_similar(string $title1, string $title2): bool {
    if (!titles_are_dissimilar($title1, $title2)) {
        return true;
    }
    // Try again but with funky stuff mapped out of existence
    $title1 = str_replace('�', '', str_replace(array_keys(MAP_DIACRITICS), '', $title1));
    $title2 = str_replace('�', '', str_replace(array_keys(MAP_DIACRITICS), '', $title2));
    if (!titles_are_dissimilar($title1, $title2)) {
        return true;
    }
    return false;
}

function de_wikify(string $string): string {
    return str_replace(["[", "]", "'''", "''", "&"], ["", "", "'", "'", ""], preg_replace(["~<[^>]*>~", "~\&[\w\d]{2,7};~", "~\[\[[^\|\]]*\|([^\]]*)\]\]~"], ["", "", "$1"], $string));
}

function titles_are_dissimilar(string $inTitle, string $dbTitle): bool {
        // Blow away junk from OLD stuff
    if (mb_stripos($inTitle, 'CITATION_BOT_PLACEHOLDER_') !== false) {
        $possible = preg_replace("~# # # CITATION_BOT_PLACEHOLDER_[A-Z]+ \d+ # # #~isu", ' ', $inTitle);
        if ($possible !== null) {
                $inTitle = $possible;
        } else { // When PHP fails with unicode, try without it
            $inTitle = preg_replace("~# # # CITATION_BOT_PLACEHOLDER_[A-Z]+ \d+ # # #~i", ' ', $inTitle);  // @codeCoverageIgnore
            if ($inTitle === null) {     // @codeCoverageIgnore
                return true;             // @codeCoverageIgnore
            }
        }
    }
    // Strip diacritics before decode
    $inTitle = strip_diacritics($inTitle);
    $dbTitle = strip_diacritics($dbTitle);
    // always decode new data
    $dbTitle = titles_simple(htmlentities(html_entity_decode($dbTitle)));
    // old data both decoded and not
    $inTitle2 = titles_simple($inTitle);
    $inTitle = titles_simple(htmlentities(html_entity_decode($inTitle)));
    $dbTitle = strip_diacritics($dbTitle);
    $inTitle = strip_diacritics($inTitle);
    $inTitle2 = strip_diacritics($inTitle2);
    $dbTitle = mb_strtolower($dbTitle);
    $inTitle = mb_strtolower($inTitle);
    $inTitle2 = mb_strtolower($inTitle2);
    $drops = [" ", "<strong>", "</strong>", "<em>", "</em>", "&nbsp", "&ensp", "&emsp", "&thinsp", "&zwnj",
        "&#45", "&#8208", "&#700", "&#039", "&#022", "&", "'", ",", ".", ";", '"', "\n", "\r", "\t", "\v", "\e", "‐",
        "-", "ʼ", "`", "]", "[", "(", ")", ":", "′", "−",
    ];
    $inTitle = str_replace($drops, "", $inTitle);
    $inTitle2 = str_replace($drops, "", $inTitle2);
    $dbTitle = str_replace($drops, "", $dbTitle);
    // This will convert &delta into delta
    return ((mb_strlen($inTitle) > 254 || mb_strlen($dbTitle) > 254)
                ? (mb_strlen($inTitle) !== mb_strlen($dbTitle)
            || similar_text($inTitle, $dbTitle) / mb_strlen($inTitle) < 0.98)
                : (levenshtein($inTitle, $dbTitle) > 3))
    &&
    ((mb_strlen($inTitle2) > 254 || mb_strlen($dbTitle) > 254)
                ? (mb_strlen($inTitle2) !== mb_strlen($dbTitle)
            || similar_text($inTitle2, $dbTitle) / mb_strlen($inTitle2) < 0.98)
                : (levenshtein($inTitle2, $dbTitle) > 3));
}

function titles_simple(string $inTitle): string {
    // Failure leads to null or empty strings!!!!
    // Leading Chapter # -   Use callback to make sure there are a few characters after this
    $inTitle = safe_preg_replace_callback('~^(?:Chapter \d+ \- )(.....+)~iu',
            static function (array $matches): string {
                return $matches[1];
            }, mb_trim($inTitle));
    // Chapter number at start
    $inTitle = safe_preg_replace('~^\[\d+\]\s*~iu', '', mb_trim($inTitle));
    // Trailing "a review"
    $inTitle = safe_preg_replace('~(?:\: | |\:)a review$~iu', '', mb_trim($inTitle));
    // Strip trailing Online
    $inTitle = safe_preg_replace('~ Online$~iu', '', $inTitle);
    // Strip trailing (Third Edition)
    $inTitle = safe_preg_replace('~\([^\s\(\)]+ Edition\)^~iu', '', $inTitle);
    // Strip leading International Symposium on
    $inTitle = safe_preg_replace('~^International Symposium on ~iu', '', $inTitle);
    // Strip leading the
    $inTitle = safe_preg_replace('~^The ~iu', '', $inTitle);
    // Strip trailing
    $inTitle = safe_preg_replace('~ A literature review$~iu', '', $inTitle);
    $inTitle = safe_preg_replace("~^Editorial: ~ui", "", $inTitle);
    $inTitle = safe_preg_replace("~^Brief communication: ~ui", "", $inTitle);
    // Reduce punctuation
    $inTitle = straighten_quotes(mb_strtolower($inTitle), true);
    $inTitle = safe_preg_replace("~(?: |‐|−|-|—|–|â€™|â€”|â€“)~u", "", $inTitle);
    $inTitle = str_replace(["\n", "\r", "\t", "&#8208;", ":", "&ndash;", "&mdash;", "&ndash", "&mdash"], "", $inTitle);
    // Retracted
    $inTitle = safe_preg_replace("~\[RETRACTED\]~ui", "", $inTitle);
    $inTitle = safe_preg_replace("~\(RETRACTED\)~ui", "", $inTitle);
    $inTitle = safe_preg_replace("~RETRACTED~ui", "", $inTitle);
    // Drop normal quotes
    $inTitle = str_replace(["'", '"'], "", $inTitle);
    // Strip trailing periods
    $inTitle = mb_trim(mb_rtrim($inTitle, '.'));
    // &
    $inTitle = str_replace(" & ", " and ", $inTitle);
    $inTitle = str_replace(" / ", " and ", $inTitle);
    // greek
    $inTitle = strip_diacritics($inTitle);
    return str_remove_irrelevant_bits($inTitle);
}

function strip_diacritics (string $input): string {
    return str_replace(array_keys(MAP_DIACRITICS), array_values(MAP_DIACRITICS), $input);
}

function straighten_quotes(string $str, bool $do_more): string { // (?<!\') and (?!\') means that it cannot have a single quote right before or after it
    // These Regex can die on Unicode because of backward looking
    if ($str === '') {
        return '';
    }
    $str = str_replace('Hawaiʻi', 'CITATION_BOT_PLACEHOLDER_HAWAII', $str);
    $str = str_replace('Ha‘apai', 'CITATION_BOT_PLACEHOLDER_HAAPAI', $str);
    $str = safe_preg_replace('~(?<!\')&#821[679];|&#39;|&#x201[89];|[\x{FF07}\x{2018}-\x{201B}`]|&[rl]s?[b]?quo;(?!\')~u', "'", $str);
    if ((mb_strpos($str, '&rsaquo;') !== false && mb_strpos($str, '&[lsaquo;') !== false) ||
            (mb_strpos($str, '\x{2039}') !== false && mb_strpos($str, '\x{203A}') !== false) ||
            (mb_strpos($str, '‹') !== false && mb_strpos($str, '›') !== false)) { // Only replace single angle quotes if some of both
            $str = safe_preg_replace('~&[lr]saquo;|[\x{2039}\x{203A}]|[‹›]~u', "'", $str);  // Websites tiles: Jobs ›› Iowa ›› Cows ›› Ames
    }
    $str = safe_preg_replace('~&#822[013];|[\x{201C}-\x{201F}]|&[rlb][d]?quo;~u', '"', $str);
    if (in_array(WIKI_BASE, ENGLISH_WIKI, true) && (
            (mb_strpos($str, '&raquo;') !== false && mb_strpos($str, '&laquo;') !== false) ||
            /** @phpstan-ignore notIdentical.alwaysTrue */
            (mb_strpos($str, '\x{00AB}') !== false && mb_strpos($str, '\x{00AB}') !== false) ||
            (mb_strpos($str, '«') !== false && mb_strpos($str, '»') !== false))) { // Only replace double angle quotes if some of both // Websites tiles: Jobs » Iowa » Cows » Ames
        if ($do_more) {
            $str = safe_preg_replace('~&[lr]aquo;|[\x{00AB}\x{00BB}]|[«»]~u', '"', $str);
        } else { // Only outer funky quotes, not inner quotes
            if (preg_match('~^(?:&laquo;|&raquo;|\x{00AB}|\x{00BB}|«|»)~u', $str, $match1) &&
                preg_match('~(?:&laquo;|&raquo;|\x{00AB}|\x{00BB}|«|»)$~u', $str, $match2)
            ) {
                $count1 = mb_substr_count($str, $match1[0]);
                $count2 = mb_substr_count($str, $match2[0]);
                if ($match1[0] === $match2[0]) { // Avoid double counting
                    $count1 -= 1;
                    $count2 -= 1;
                }
                if ($count1 === 1 && $count2 === 1) {
                    $str = safe_preg_replace('~^(?:&laquo;|&raquo;|\x{00AB}|\x{00BB}|«|»)~u', '"', $str);
                    $str = safe_preg_replace('~(?:&laquo;|&raquo;|\x{00AB}|\x{00BB}|«|»)$~u', '"', $str);
                }
            }
        }
    }
    $str = str_ireplace('CITATION_BOT_PLACEHOLDER_HAAPAI', 'Ha‘apai', $str);
    return str_ireplace('CITATION_BOT_PLACEHOLDER_HAWAII', 'Hawaiʻi', $str);
}

// ============================================= Capitalization functions ======================================

// phpcs:ignore MediaWiki.Commenting.FunctionComment.WrongStyle
function title_case(string $text): string {
    if (mb_stripos($text, 'www.') !== false || mb_stripos($text, 'www-') !== false || mb_stripos($text, 'http://') !== false) {
        return $text; // Who knows - duplicate code below
    }
    return mb_convert_case($text, MB_CASE_TITLE, "UTF-8");
}

/** Returns a properly capitalized title.
 *          If $caps_after_punctuation is true (or there is an abundance of periods), it allows the
 *          letter after colons and other punctuation marks to remain capitalized.
 *          If not, it won't capitalize after : etc.
 */
function title_capitalization(string $in, bool $caps_after_punctuation): string {
    // Use 'straight quotes' per WP:MOS
    $new_case = straighten_quotes(mb_trim($in), false);
    if (mb_substr($new_case, 0, 1) === "[" && mb_substr($new_case, -1) === "]") {
        return $new_case; // We ignore wikilinked names and URL linked since who knows what's going on there.
                                             // Changing case may break links (e.g. [[Journal YZ|J. YZ]] etc.)
    }

    if (mb_stripos($new_case, 'www.') !== false || mb_stripos($new_case, 'www-') !== false || mb_stripos($new_case, 'http://') !== false) {
        return $new_case; // Who knows - duplicate code above
    }

    if ($new_case === mb_strtoupper($new_case)
            && mb_strlen(str_replace(["[", "]"], "", mb_trim($in))) > 6
            ) {
        // ALL CAPS to Title Case
        $new_case = mb_convert_case($new_case, MB_CASE_TITLE, "UTF-8");
    }

    // Implicit acronyms
    $new_case = ' ' . $new_case . ' ';
    $new_case = safe_preg_replace_callback("~[^\w&][b-df-hj-np-tv-xz]{3,}(?=\W)~ui",
            static function (array $matches): string {  // Three or more consonants.  NOT Y
                return mb_strtoupper($matches[0]);
            },
            $new_case);
    $new_case = safe_preg_replace_callback("~[^\w&][aeiou]{3,}(?=\W)~ui",
            static function (array $matches): string {  // Three or more vowels.  NOT Y
                return mb_strtoupper($matches[0]);
            },
            $new_case);
    $new_case = mb_trim($new_case); // Remove added spaces

    $new_case = mb_trim(str_replace(UC_SMALL_WORDS, LC_SMALL_WORDS, " " . $new_case . " "));
    foreach (UC_SMALL_WORDS as $key => $_value) {
        $upper = UC_SMALL_WORDS[$key];
        $lower = LC_SMALL_WORDS[$key];
        foreach ([': ', ', ', '. ', '; '] as $char) {
            $new_case = str_replace(mb_substr($upper, 0, -1) . $char, mb_substr($lower, 0, -1) . $char, $new_case);
        }
    }

    if ($caps_after_punctuation || (mb_substr_count($in, '.') / mb_strlen($in)) > .07) {
        // When there are lots of periods, then they probably mark abbreviations, not sentence ends
        // We should therefore capitalize after each punctuation character.
        $new_case = safe_preg_replace_callback("~[?.:!/]\s+[a-z]~u", // Capitalize after punctuation
            static function (array $matches): string {
                return mb_strtoupper($matches[0]);
            },
            $new_case);
        $new_case = safe_preg_replace_callback("~(?<!<)/[a-z]~u", // Capitalize after slash unless part of ending html tag
            static function (array $matches): string {
                return mb_strtoupper($matches[0]);
            },
            $new_case);
        // But not "Ann. Of...." which seems to be common in journal titles
        $new_case = str_replace("Ann. Of ", "Ann. of ", $new_case);
    }

    $new_case = safe_preg_replace_callback(
        "~ \([a-z]~u", // uppercase after parenthesis
        static function (array $matches): string {
            return mb_strtoupper($matches[0]);
        },
        mb_trim($new_case)
    );

    $new_case = safe_preg_replace_callback(
        "~\w{2}'[A-Z]\b~u", // Lowercase after apostrophes
        static function (array $matches): string {
            return mb_strtolower($matches[0]);
        },
        mb_trim($new_case)
    );
    /** French l'Words and d'Words */
    $new_case = safe_preg_replace_callback(
        "~(\s[LD][\'\x{00B4}])([a-zA-ZÀ-ÿ]+)~u",
        static function (array $matches): string {
            return mb_strtolower($matches[1]) . mb_ucfirst($matches[2]);
        },
        ' ' . $new_case
    );

    /** Italian dell'xxx words */
    $new_case = safe_preg_replace_callback(
        "~(\s)(Dell|Degli|Delle)([\'\x{00B4}][a-zA-ZÀ-ÿ]{3})~u",
        static function (array $matches): string {
            return $matches[1] . mb_strtolower($matches[2]) . $matches[3];
        },
        $new_case
    );

    $new_case = mb_ucfirst(mb_trim($new_case));

    // Solitary 'a' should be lowercase
    $new_case = safe_preg_replace("~(\w\s+)A(\s+\w)~u", "$1a$2", $new_case);
    // but not in "U S A"
    $new_case = mb_trim(str_replace(" U S a ", " U S A ", ' ' . $new_case . ' '));

    // This should be capitalized
    $new_case = str_replace(['(new Series)', '(new series)'], ['(New Series)', '(New Series)'], $new_case);

    // Catch some specific epithets, which should be lowercase
    $new_case = safe_preg_replace_callback(
        "~(?:'')?(?P<taxon>\p{L}+\s+\p{L}+)(?:'')?\s+(?P<nova>(?:(?:gen\.? no?v?|sp\.? no?v?|no?v?\.? sp|no?v?\.? gen)\b[\.,\s]*)+)~ui", // Species names to lowercase
        static function (array $matches): string {
            return "''" . mb_ucfirst(mb_strtolower($matches['taxon'])) . "'' " . mb_strtolower($matches["nova"]);
        },
        $new_case);

    // "des" at end is "Des" for Design not german "The"
    if (mb_substr($new_case, -4, 4) === ' des') {
        $new_case = mb_substr($new_case, 0, -4) . ' Des';
    }

    // Capitalization exceptions, e.g. Elife -> eLife
    $new_case = str_replace(UCFIRST_JOURNAL_ACRONYMS, JOURNAL_ACRONYMS, " " . $new_case . " ");
    $new_case = mb_trim($new_case); // remove spaces, needed for matching in LC_SMALL_WORDS

    // Single letter at end should be capitalized    J Chem Phys E for example.  Obviously not the spanish word "e".
    if (mb_substr($new_case, -2, 1) === ' ') {
        $new_case = mb_strrev(mb_ucfirst(mb_strrev($new_case)));
    }

    if ($new_case === 'Now and then') {
        $new_case = 'Now and Then'; // Odd journal name
    }

    // Trust existing "ITS", "its", ...
    $its_in = preg_match_all('~ its(?= )~iu', ' ' . mb_trim($in) . ' ', $matches_in, PREG_OFFSET_CAPTURE);
    $new_case = mb_trim($new_case);
    $its_out = preg_match_all('~ its(?= )~iu', ' ' . $new_case . ' ', $matches_out, PREG_OFFSET_CAPTURE);
    if ($its_in === $its_out && $its_in !== 0 && $its_in !== false) {
        $matches_in = $matches_in[0];
        $matches_out = $matches_out[0];
        foreach ($matches_in as $key => $_value) {
            if ($matches_in[$key][0] !== $matches_out[$key][0] &&
                    $matches_in[$key][1] === $matches_out[$key][1]) {
                $new_case = mb_substr_replace($new_case, mb_trim($matches_in[$key][0]), $matches_out[$key][1], 3); // PREG_OFFSET_CAPTURE is ALWAYS in BYTES, even for unicode
            }
        }
    }
    // Trust existing "DOS", "dos", ...
    $its_in = preg_match_all('~ dos(?= )~iu', ' ' . mb_trim($in) . ' ', $matches_in, PREG_OFFSET_CAPTURE);
    $new_case = mb_trim($new_case);
    $its_out = preg_match_all('~ dos(?= )~iu', ' ' . $new_case . ' ', $matches_out, PREG_OFFSET_CAPTURE);
    if ($its_in === $its_out && $its_in !== 0 && $its_in !== false) {
        $matches_in = $matches_in[0];
        $matches_out = $matches_out[0];
        foreach ($matches_in as $key => $_value) {
            if ($matches_in[$key][0] !== $matches_out[$key][0] &&
                    $matches_in[$key][1] === $matches_out[$key][1]) {
                $new_case = mb_substr_replace($new_case, mb_trim($matches_in[$key][0]), $matches_out[$key][1], 3); // PREG_OFFSET_CAPTURE is ALWAYS in BYTES, even for unicode
            }
        }
    }

    if (preg_match('~Series ([a-zA-Z] )(\&|and)( [a-zA-Z] )~', $new_case . ' ', $matches)) {
        $replace_me = 'Series ' . $matches[1] . $matches[2] . $matches[3];
        $replace = 'Series ' . mb_strtoupper($matches[1]) . $matches[2] . mb_strtoupper($matches[3]);
        $new_case = mb_trim(str_replace($replace_me, $replace, $new_case . ' '));
    }

    // 42th, 33rd, 1st, ...
    if (preg_match('~\s\d+(?:st|nd|rd|th)[\s\,\;\:\.]~i', ' ' . $new_case . ' ', $matches)) {
        $replace_me = $matches[0];
        $replace = mb_strtolower($matches[0]);
        $new_case = mb_trim(str_replace($replace_me, $replace, ' ' . $new_case . ' '));
    }

    // Part XII: Roman numerals
    $new_case = safe_preg_replace_callback(
        "~ part ([xvil]+): ~iu",
        static function (array $matches): string {
            return " Part " . mb_strtoupper($matches[1]) . ": ";
        },
        $new_case);
    $new_case = safe_preg_replace_callback(
        "~ part ([xvi]+) ~iu",
        static function (array $matches): string {
            return " Part " . mb_strtoupper($matches[1]) . " ";
        },
        $new_case);
    $new_case = safe_preg_replace_callback(
        "~ (?:Ii|Iii|Iv|Vi|Vii|Vii|Ix)$~u",
        static function (array $matches): string {
            return mb_strtoupper($matches[0]);
        },
        $new_case);
    $new_case = safe_preg_replace_callback(
        "~^(?:Ii|Iii|Iv|Vi|Vii|Vii|Ix):~u",
        static function (array $matches): string {
            return mb_strtoupper($matches[0]);
        },
        $new_case);
    $new_case = safe_preg_replace_callback(
        "~ Proceedings ([a-z]) ~u",
        static function (array $matches): string {
            return ' Proceedings ' . mb_strtoupper($matches[1]) . ' ';
        },
        $new_case);
    $new_case = safe_preg_replace_callback(
        "~ var\. ([A-Z])~u",
        static function (array $matches): string {
            return ' var. ' . mb_strtolower($matches[1]);
        },
        $new_case);
    $new_case = safe_preg_replace_callback(
        "~([\–\- ])(PPM)([\.\,\:\; ])~u",
        static function (array $matches): string {
            return $matches[1] . 'ppm' . $matches[3];
        },
        $new_case);
    $new_case = safe_preg_replace_callback(
        "~(Series )([a-z])( )~u",
        static function (array $matches): string {
            return $matches[1] . mb_strtoupper($matches[2]) . $matches[3];
        },
        $new_case);
    $new_case = mb_trim($new_case);
    // Special cases - Only if the full title
    if ($new_case === 'Bioscience') {
        $new_case = 'BioScience';
    } elseif ($new_case === 'Aids') {
        $new_case = 'AIDS';
    } elseif ($new_case === 'Biomedical Engineering Online') {
        $new_case = 'BioMedical Engineering OnLine';
    } elseif ($new_case === 'Sage Open') {
        $new_case = 'SAGE Open';
    } elseif ($new_case === 'Ca') {
        $new_case = 'CA';
    } elseif ($new_case === 'Time off') {
        $new_case = 'Time Off';
    } elseif ($new_case === 'It Professional') {
        $new_case = 'IT Professional';
    } elseif ($new_case === 'Jom') {
        $new_case = 'JOM';
    } elseif ($new_case === 'NetWorker') {
        $new_case = 'netWorker';
    } elseif ($new_case === 'Melus') {
        $new_case = 'MELUS';
    }
    return $new_case;
}

function mb_strrev(string $string, string $encode = ''): string {
    $chars = mb_str_split($string, 1, $encode ? '' : mb_internal_encoding());
    return implode('', array_reverse($chars));
}

function mb_ucwords(string $string): string {
    if (mb_ereg_search_init($string, '(\S)(\S*\s*)|(\s+)')) {
        $output = '';
        while ($match = mb_ereg_search_regs()) {
            $output .= $match[3] ? $match[3] : mb_strtoupper($match[1]) . $match[2];
        }
        return $output;
    } else {
        return $string;  // @codeCoverageIgnore
    }
}

function mb_substr_replace(string $string, string $replacement, int $start, int $length): string {
    return mb_substr($string, 0, $start) . $replacement . mb_substr($string, $start + $length);
}

function remove_brackets(string $string): string {
    return str_replace(['(', ')', '{', '}', '[', ']'], '', $string);
}

// ============================================= Data processing functions ======================================

// phpcs:ignore MediaWiki.Commenting.FunctionComment.WrongStyle
function tidy_date(string $string): string { // Wrapper to change all pre-1900 dates to just years
    $string = tidy_date_inside($string);
    if ($string === '') {
        return $string;
    }
    $time = strtotime($string);
    if (!$time) {
        return $string;
    }
    $old = strtotime('1 January 1900');
    if ($old < $time) {
        return $string;
    }
    $new = date('Y', $time);
    if (mb_strlen($new) === 4) {
        return mb_ltrim($new, "0"); // Also cleans up 0000
    }
    if (mb_strlen($new) === 5 && mb_substr($new, 0, 1) === '-') {
        $new = mb_ltrim($new, "-");
        $new = mb_ltrim($new, "0");
        $new = $new . ' BC';
        return $new;
    }
    return $string;
}

function tidy_date_inside(string $string): string {
    $string = mb_trim($string);
    if (mb_stripos($string, 'Invalid') !== false) {
        return '';
    }
    if (mb_strpos($string, '1/1/0001') !== false) {
        return '';
    }
    if (mb_strpos($string, '0001-01-01') !== false) {
        return '';
    }
    if (!preg_match('~\d{2}~', $string)) {
        return ''; // Not two numbers next to each other
    }
    if (preg_match('~^\d{2}\-\-$~', $string)) {
        return '';
    }
    // Google sends ranges
    if (preg_match('~^(\d{4})(\-\d{2}\-\d{2})\s+\-\s+(\d{4})(\-\d{2}\-\d{2})$~', $string, $matches)) { // Date range
        if ($matches[1] === $matches[3]) {
            return date('j F', strtotime($matches[1] . $matches[2])) . ' – ' . date('j F Y', strtotime($matches[3] . $matches[4]));
        } else {
            return date('j F Y', strtotime($matches[1] . $matches[2])) . ' – ' . date('j F Y', strtotime($matches[3] . $matches[4]));
        }
    }
    // Huge amount of character cleaning
    if (mb_strlen($string) !== mb_strlen($string)) {    // Convert all multi-byte characters to dashes
        $cleaned = '';
        $the_str_length = mb_strlen($string);
        for ($i = 0; $i < $the_str_length; $i++) {
            $char = mb_substr($string, $i, 1);
            if (mb_strlen($char) === mb_strlen($char)) {
                $cleaned .= $char;
            } else {
                $cleaned .= '-';
            }
        }
        $string = $cleaned;
    }
    $string = safe_preg_replace("~[^\x01-\x7F]~", "-", $string); // Convert any non-ASCII Characters to dashes
    $string = safe_preg_replace('~[\s\-]*\-[\s\-]*~', '-', $string); // Combine dash with any following or preceding white space and other dash
    $string = safe_preg_replace('~^\-*(.+?)\-*$~', '\1', $string);  // Remove trailing/leading dashes
    $string = mb_trim($string);
    // End of character clean-up
    $string = safe_preg_replace('~[^0-9]+\d{2}:\d{2}:\d{2}$~', '', $string); //trailing time
    $string = safe_preg_replace('~^Date published \(~', '', $string); // seen this
    // https://stackoverflow.com/questions/29917598/why-does-0000-00-00-000000-return-0001-11-30-000000
    if (mb_strpos($string, '0001-11-30') !== false) {
        return '';
    }
    if (mb_strpos($string, '1969-12-31') !== false) {
        return '';
    }
    if (str_i_same('19xx', $string)) {
        return ''; //archive.org gives this if unknown
    }
    if (preg_match('~^\d{4} \d{4}\-\d{4}$~', $string)) {
        return ''; // si.edu
    }
    if (preg_match('~^(\d\d?)/(\d\d?)/(\d{4})$~', $string, $matches)) { // dates with slashes
        if (intval($matches[1]) < 13 && intval($matches[2]) > 12) {
            if (mb_strlen($matches[1]) === 1) {
                $matches[1] = '0' . $matches[1];
            }
            return $matches[3] . '-' . $matches[1] . '-' . $matches[2];
        } elseif (intval($matches[2]) < 13 && intval($matches[1]) > 12) {
            if (mb_strlen($matches[2]) === 1) {
                $matches[2] = '0' . $matches[2];
            }
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        } elseif (intval($matches[2]) > 12 && intval($matches[1]) > 12) {
            return '';
        } elseif ($matches[1] === $matches[2]) {
            if (mb_strlen($matches[2]) === 1) {
                $matches[2] = '0' . $matches[2];
            }
            return $matches[3] . '-' . $matches[2] . '-' . $matches[2];
        } else {
            return $matches[3];// do not know. just give year
        }
    }
    $string = mb_trim($string);
    if (preg_match('~^(\d{4}\-\d{2}\-\d{2})T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$~', $string, $matches)) {
        return tidy_date_inside($matches[1]); // Remove time zone stuff from standard date format
    }
    if (preg_match('~^\-?\d+$~', $string)) {
        $string = intval($string);
        if ($string < -2000 || $string > (int) date("Y") + 10) {
            return ''; // A number that is not a year; probably garbage
        }
        if ($string > -2 && $string < 2) {
            return ''; // reject -1,0,1
        }
        return (string) $string; // year
    }
    if (preg_match('~^(\d{1,2}) ([A-Za-z]+\.?), ?(\d{4})$~', $string, $matches)) { // strtotime('3 October, 2016') gives 2019-10-03.    The comma is evil and strtotime is stupid
        $string = $matches[1] . ' ' . $matches[2] . ' ' . $matches[3];   // Remove comma
    }
    $time = strtotime($string);
    if ($time) {
        $day = date('d', $time);
        $year = intval(date('Y', $time));
        if ($year < -2000 || $year > (int) date("Y") + 10) {
            return ''; // We got an invalid year
        }
        if ($year < 100 && $year > -100) {
            return '';
        }
        if ($day === '01') { // Probably just got month and year
            $string = date('F Y', $time);
        } else {
            $string = date('Y-m-d', $time);
        }
        if (mb_stripos($string, 'Invalid') !== false) {
            return '';
        }
        return $string;
    }
    if (preg_match('~^(\d{4}\-\d{1,2}\-\d{1,2})[^0-9]~', $string, $matches)) {
        return tidy_date_inside($matches[1]); // Starts with date
    }
    if (preg_match('~\s(\d{4}\-\d{1,2}\-\d{1,2})$~', $string, $matches)) {
        return tidy_date_inside($matches[1]);  // Ends with a date
    }
    if (preg_match('~^(\d{1,2}/\d{1,2}/\d{4})[^0-9]~', $string, $matches)) {
        return tidy_date_inside($matches[1]); // Recursion to clean up 3/27/2000
    }
    if (preg_match('~[^0-9](\d{1,2}/\d{1,2}/\d{4})$~', $string, $matches)) {
        return tidy_date_inside($matches[1]);
    }

    // Dates with dots -- convert to slashes and try again.
    if (preg_match('~(\d\d?)\.(\d\d?)\.(\d{2}(?:\d{2})?)$~', $string, $matches) || preg_match('~^(\d\d?)\.(\d\d?)\.(\d{2}(?:\d{2})?)~', $string, $matches)) {
        if (intval($matches[3]) < ((int) date("y") + 2)) {
            $matches[3] = (int) $matches[3] + 2000;
        }
        if (intval($matches[3]) < 100) {
            $matches[3] = (int) $matches[3] + 1900;
        }
        return tidy_date_inside((string) $matches[1] . '/' . (string) $matches[2] . '/' . (string) $matches[3]);
    }

    if (preg_match('~\s(\d{4})$~', $string, $matches)) {
        return $matches[1]; // Last ditch effort - ends in a year
    }
    return ''; // And we give up
}

// ============================================= Other functions ======================================

// phpcs:ignore MediaWiki.Commenting.FunctionComment.WrongStyle
function remove_comments(string $string): string {
    // See Comment::PLACEHOLDER_TEXT for syntax
    $string = preg_replace('~# # # CITATION_BOT_PLACEHOLDER_COMMENT \d+ # # #~isu', "", $string);
    $ret = preg_replace("~<!--.*?-->~us", "", $string);
    if ($ret === null) {
        report_error("null in remove_comments()");
    }
    return $ret;
}

function can_safely_modify_dashes(string $value): bool {
    return (mb_stripos($value, "http") === false)
            && (mb_strpos($value, "[//") === false)
            && (mb_substr_count($value, "<") === 0) // <span></span> stuff
            && (mb_stripos($value, 'CITATION_BOT_PLACEHOLDER') === false)
            && (mb_strpos($value, "(") === false)
            && (preg_match('~(?:[a-zA-Z].*\s|\s.*[a-zA-Z])~u', mb_trim($value)) !== 1) // Spaces and letters
            && ((mb_substr_count($value, '-') + mb_substr_count($value, '–') + mb_substr_count($value, ',') + mb_substr_count($value, 'dash')) < 3) // This line helps us ignore with 1-5–1-6 stuff
            && (preg_match('~^[a-zA-Z]+[0-9]*.[0-9]+$~u', $value) !== 1) // A-3, A3-5 etc.   Use "." for generic dash
            && (preg_match('~^\d{4}\-[a-zA-Z]+$~u', $value) !== 1); // 2005-A used in {{sfn}} junk
}

function str_i_same(string $str1, string $str2): bool {
    if ($str1 === 'Eulerian Numbers') {
        return false; // very special case
    }
    if (strcasecmp($str1, $str2) === 0) {
        return true; // Quick non-multi-byte compare short cut
    }
    return strcmp(mb_strtoupper($str1), mb_strtoupper($str2)) === 0;
}

function doi_encode (string $doi): string {
    /**
     * @psalm-taint-escape html
     * @psalm-taint-escape has_quotes
     * @psalm-taint-escape ssrf
     */
    $doi = urlencode($doi);
    return str_replace('%2F', '/', $doi);
}

function hdl_decode(string $hdl): string {
    $hdl = urldecode($hdl);
    $hdl = str_replace(';', '%3B', $hdl);
    $hdl = str_replace('#', '%23', $hdl);
    return str_replace(' ', '%20', $hdl);
}

/** Sometimes (UTF-8 non-english characters) preg_replace fails, and we would rather have the original string than a null */
function safe_preg_replace(string $regex, string $replace, string $old): string {
    if ($old === "") {
        return "";
    }
    $new = preg_replace($regex, $replace, $old);
    if ($new === null) {
        return $old; // @codeCoverageIgnore
    }
    return $new;
}

function safe_preg_replace_callback(string $regex, callable $replace, string $old): string {
    if ($old === "") {
        return "";
    }
    $new = preg_replace_callback($regex, $replace, $old);
    if ($new === null) {
        return $old; // @codeCoverageIgnore
    }
    return $new;
}

function wikifyURL(string $url): string {
    $in = [' ', '"', '\'', '<', '>', '[', ']', '{', '|', '}'];
    $out = ['%20', '%22', '%27', '%3C', '%3E', '%5B', '%5D', '%7B', '%7C', '%7D'];
    return str_replace($in, $out, $url);
}

function numberToRomanRepresentation(int $number): string { // https://stackoverflow.com/questions/14994941/numbers-to-roman-numbers-with-php
    $map = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
    $returnValue = '';
    while ($number > 0) {
        foreach ($map as $roman => $int) {
            if ($number >= $int) {
                $number -= $int;
                $returnValue .= $roman;
                break;
            }
        }
    }
    return $returnValue;
}

function clean_dates(string $input): string { // See https://en.wikipedia.org/wiki/Help:CS1_errors#bad_date
    if ($input === '0001-11-30') {
        return '';
    }
    $input = str_ireplace(MONTH_SEASONS, MONTH_SEASONS, $input); // capitalization
    if (preg_match('~^(\d{4})[\-\/](\d{4})$~', $input, $matches)) { // Hyphen or slash in year range (use en dash)
        return $matches[1] . '–' . $matches[2];
    }
    if (preg_match('~^(\d{4})\/ed$~i', $input, $matches)) { // 2002/ed
        return $matches[1];
    }
    if (preg_match('~^First published(?: |\: | in | in\: | in\:)(\d{4})$~i', $input, $matches)) { // First published: 2002
        return $matches[1];
    }
    if (preg_match('~^([A-Z][a-z]+)[\-\/]([A-Z][a-z]+) (\d{4})$~', $input, $matches)) { // Slash or hyphen in date range (use en dash)
        return $matches[1] . '–' . $matches[2] . ' ' . $matches[3];
    }
    if (preg_match('~^([A-Z][a-z]+ \d{4})[\-\–]([A-Z][a-z]+ \d{4})$~', $input, $matches)) { // Missing space around en dash for range of full dates
        return $matches[1] . ' – ' . $matches[2];
    }
    if (preg_match('~^([A-Z][a-z]+), (\d{4})$~', $input, $matches)) { // Comma with month/season and year
        return $matches[1] . ' ' . $matches[2];
    }
    if (preg_match('~^([A-Z][a-z]+), (\d{4})[\-\–](\d{4})$~', $input, $matches)) { // Comma with month/season and years
        return $matches[1] . ' ' . $matches[2] . '–' . $matches[3];
    }
    if (preg_match('~^([A-Z][a-z]+) 0(\d),? (\d{4})$~', $input, $matches)) { // Zero-padding
        return $matches[1] . ' ' . $matches[2] . ', ' . $matches[3];
    }
    if (preg_match('~^([A-Z][a-z]+ \d{1,2})( \d{4})$~', $input, $matches)) { // Missing comma in format which requires it
        return $matches[1] . ',' . $matches[2];
    }
    if (preg_match('~^Collected[\s\:]+((?:|[A-Z][a-z]+ )\d{4})$~', $input, $matches)) { // Collected 1999 stuff
        return $matches[1];
    }
    if (preg_match('~^Effective[\s\:]+((?:|[A-Z][a-z]+ )\d{4})$~', $input, $matches)) { // Effective 1999 stuff
        return $matches[1];
    }
    if (preg_match('~^(\d+ [A-Z][a-z]+ \d{4})\.$~', $input, $matches)) { // 8 December 2022. (period on end)
        return $matches[1];
    }
    if (preg_match('~^0(\d [A-Z][a-z]+ \d{4})$~', $input, $matches)) { // 08 December 2022 - leading zero
        return $matches[1];
    }
    if (preg_match('~^([A-Z][a-z]+)\, ([A-Z][a-z]+ \d+,* \d{4})$~', $input, $matches)) { // Monday, November 2, 1981
        if (in_array($matches[1], DAYS_OF_WEEKS, true)) {
            return $matches[2];
        }
    }
    if (preg_match('~^(\d{4})\s*(?:&|and)\s*(\d{4})$~', $input, $matches)) { // &/and between years
        $first = (int) $matches[1];
        $second = (int) $matches[2];
        if ($second === $first + 1) {
            return $matches[1] . '–' . $matches[2];
        }
    }

    if (preg_match('~^(\d{4})\-(\d{2})$~', $input, $matches) && in_array(WIKI_BASE, ENGLISH_WIKI, true)) { // 2020-12 i.e. backwards
        $year = $matches[1];
        $month = (int) $matches[2];
        if ($month > 0 && $month < 13) {
            return MONTH_SEASONS[$month - 1] . ' ' . $year;
        }
    }
    return $input;
}

function addISBNdashes(string $isbn): string {
    if (mb_substr_count($isbn, '-') > 1) {
        return $isbn;
    }
    $new = str_replace('-', '', $isbn);
    if (mb_strlen($new) === 10) {
        $num = 9780000000000 + (int) str_ireplace('x', '9', $new);
        foreach (ISBN_HYPHEN_POS as $k => $v) {
            if ($num <= (int) $k) {
                $split = $v;
                break;
            }
        }
        if (!isset($split)) {
            return $isbn; // Paranoid
        }
        $v = $split;
        return mb_substr($new, 0, $v[0]) . '-' . mb_substr($new, $v[0], $v[1]) . '-' . mb_substr($new, $v[0] + $v[1], $v[2]) . '-' . mb_substr($new, $v[0] + $v[1] + $v[2], 1);
        // split = SKIP3, $v[0], $v[1], $v[2], 1
    } elseif (mb_strlen($new) === 13) {
        $num = (int) $new;
        foreach (ISBN_HYPHEN_POS as $k => $v) {
            if ($num <= (int) $k) {
                $split = $v;
                break;
            }
        }
        if (!isset($split)) {
            return $isbn; // Paranoid
        }
        $v = $split;
        return mb_substr($new, 0, 3) . '-' . mb_substr($new, 3, $v[0]) . '-' . mb_substr($new, 3 + $v[0], $v[1]) . '-' . mb_substr($new, 3 + $v[0] + $v[1], $v[2]) . '-' . mb_substr($new, 3 + $v[0] + $v[1] + $v[2], 1);
        // split = 3, $v[0], $v[1], $v[2], 1
    } else {
        return $isbn;
    }
}

function changeisbn10Toisbn13(string $isbn10, int $year): string {
    $isbn10 = mb_trim($isbn10); // Remove leading and trailing spaces
    $test = str_replace(['—', '?', '–', '-', '?', ' '], '', $isbn10);
    if (mb_strlen($test) < 10 || mb_strlen($test) > 13) {
        return $isbn10;
    }
    $isbn10 = str_replace('x', 'X', $isbn10);
    if (preg_match("~^[0-9Xx ]+$~", $isbn10) === 1) {
        // Uses spaces
        $isbn10 = str_replace(' ', '-', $isbn10);
    }
    $isbn10 = str_replace(['—', '?', '–', '-', '?'], '-', $isbn10); // Standardize dahses : en dash, horizontal bar, em dash, minus sign, figure dash, to hyphen.
    if (preg_match("~[^0-9Xx\-]~", $isbn10) === 1) {
        return $isbn10;
    } // Contains invalid characters
    if (mb_substr($isbn10, -1) === "-" || mb_substr($isbn10, 0, 1) === "-") {
        return $isbn10;
    } // Ends or starts with a dash
    if ($year < 2007) {
        return $isbn10;
    } // Older books does not have ISBN-13, see [[WP:ISBN]]
    $isbn13 = str_replace('-', '', $isbn10); // Remove dashes to do math
    if (mb_strlen($isbn13) !== 10) {
        return $isbn10;
    } // Might be an ISBN 13 already, or rubbish
    $isbn13 = '978' . mb_substr($isbn13, 0, -1); // Convert without check digit - do not need and might be X
    if (preg_match("~[^0123456789]~", $isbn13) === 1) {
        return $isbn10;
    } // Not just numbers
    $sum = 0;
    for ($count = 0; $count < 12; $count++) {
        $sum = $sum + intval($isbn13[$count]) * ($count % 2 ? 3 : 1); // Depending upon even or odd, we multiply by 3 or 1 (strange but true)
    }
    $sum = (10 - ($sum % 10)) % 10;
    $isbn13 = '978' . '-' . mb_substr($isbn10, 0, -1) . (string) $sum; // Assume existing dashes (if any) are right
    report_info("Converted ISBN10 to ISBN13");
    return $isbn13;
}

function echoable_doi(string $doi): string {
    return str_ireplace(['&lt;', '&gt;'], ['<', '>'], echoable($doi));
}

function clean_volume(string $volume): string {
    if (mb_strpos($volume, "(") !== false) {
        return '';
    }
    if (preg_match('~[a-zA-Z]~', $volume) && (bool) strtotime($volume)) {
        return ''; // Do not add date
    }
    if (mb_stripos($volume, "november") !== false) {
        return '';
    }
    if (mb_stripos($volume, "nostradamus") !== false) {
        return '';
    }
    return mb_trim(str_ireplace(['volumes', 'volume', 'vol.', 'vols.', 'vols',
     'vol', 'issues', 'issue', 'iss.', 'iss', 'numbers', 'number',
     'num.', 'num', 'nos.', 'nos', 'nr.', 'nr', '°', '№'], '', $volume));
}
