<?php

declare(strict_types=1);

require_once __DIR__ . '/constants.php';     // @codeCoverageIgnore
require_once __DIR__ . '/Template.php';      // @codeCoverageIgnore

const MONTH_SEASONS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'Winter', 'Spring', 'Summer', 'Fall', 'Autumn'];
const DAYS_OF_WEEKS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Mony', 'Tues', 'Wed', 'Thurs', 'Fri', 'Sat', 'Sun'];
const TRY_ENCODE = ["windows-1255", "maccyrillic", "windows-1253", "windows-1256", "tis-620", "windows-874", "iso-8859-11", "big5", "windows-1250"];
const INSANE_ENCODE = ['utf-8-sig', 'x-user-defined'];
const SANE_ENCODE = ['utf-8', 'iso-8859-1', 'windows-1252', 'unicode', 'us-ascii', 'none', 'iso-8859-7', 'latin1', '8859-1', '8859-7'];
const DOI_BAD_ENDS = ['.htm', '.html', '.jpg', '.jpeg', '.pdf', '.png', '.xml', '.full'];
const DOI_BAD_ENDS2 = ['/abstract', '/full', '/pdf', '/epdf', '/asset/', '/summary', '/short', '/meta', '/html', '/'];

// ============================================= String/Text functions ======================================

/**
 * This function converts formatting from external sources into wiki style
 */
function wikify_external_text(string $title): string {
    $title = str_replace(["\r\n", "\n", "\r"], ' ', $title);
    $replacement = [];
    $placeholder = [];
    $title = safe_preg_replace_callback('~(?:\$\$)([^\$]+)(?:\$\$)~iu',
            static function (array $matches): string {
                return "<math>" . $matches[1] . "</math>";
            },
            $title);
    // Single-dollar LaTeX such as $z\sim10$ gets the same <math> treatment as $$...$$ so that the
    // output does not depend on which upstream source supplied the title.  To avoid mistaking
    // ordinary text such as "costs $5 and $10" for math, the span must start and end with a
    // non-space character, must not be an escaped dollar sign, and must contain at least one
    // unambiguous LaTeX command (a backslash followed by letters).
    $title = safe_preg_replace_callback('~(?<![\\\\$])\$(?![\$\s])([^\$<>]*)(?<=\S)\$(?!\$)~u',
            static function (array $matches): string {
                if (preg_match('~\\\\[a-zA-Z]+~', $matches[1]) !== 1) {
                    return $matches[0];
                }
                return "<math>" . $matches[1] . "</math>";
            },
            $title);
    if (preg_match_all("~<(?:mml:)?math[^>]*>(.*?)</(?:mml:)?math>~", $title, $matches)) {
        $num_matches = count($matches[0]);
        for ($i = 0; $i < $num_matches; $i++) {
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
    $title = html_entity_decode($title, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML5, "UTF-8");
    $title = html_entity_decode($title, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML5, "UTF-8");
    $title = html_entity_decode($title, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML5, "UTF-8");
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
        $title = wikify_italic_tags($title);
    }

    if (mb_substr($title, -1) === '.') {
        $title = sanitize_string($title) . '.';
    } else {
        $title = sanitize_string($title);
    }

    $title = str_ireplace('<p class="HeadingRun \'\'In\'\'">', ' ', $title);

    $title = str_ireplace(['        ', '     ', '    '], [' ', ' ', ' '], $title);
    $title = mb_trim($title, " \t\n\r\0\x0B\u{00A0}");

    $num_replace = count($replacement);
    for ($i = 0; $i < $num_replace; $i++) {
        $title = str_ireplace($placeholder[$i], $replacement[$i], $title); // @phan-suppress-current-line PhanTypePossiblyInvalidDimOffset
    }

    // Ensure a space separates <math> tags from adjacent letters/digits, since some
    // external sources (e.g. CrossRef) omit the spaces around inline math markup.
    $title = safe_preg_replace('~([a-zA-Z0-9])<math~', '$1 <math', $title);
    $title = safe_preg_replace('~</math>([a-zA-Z])~', '</math> $1', $title);

    foreach (['<mtable>', '<mtr>', '<mtd>'] as $mathy) {
        if (mb_strpos($title, $mathy) !== false) {
            return '<nowiki>' . $title . '</nowiki>';
        }
    }
    return $title;
}

/** Convert paired italic tags without otherwise normalizing an existing title. */
function wikify_italic_tags(string $text): string {
    return safe_preg_replace_callback(
        '~(?:<|&lt;)(i|em|italics?)(?:>|&gt;)([^<]+?)(?:<|&lt;)/\1(?:>|&gt;)~iu',
        static function (array $matches): string {
            return "''" . $matches[2] . "''";
        },
        $text
    );
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
    $math_templates_present = preg_match_all("~<\s*math\s*>.*?<\s*/\s*math\s*>~", $str, $math_hits); // Lazy: protect each formula, not everything between the first and the last
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
    // Keep the semicolon that closes an entity, even before trailing punctuation.
    $str = safe_preg_replace('~(&(?:[a-z][a-z0-9]*|#[0-9]+|#x[0-9a-f]+);)[;.,]*+\z|[;.,]++\z~i', '$1', $str);
    $str = mb_trim(str_replace($dirty, $clean, $str));
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
    $str = safe_preg_replace('~[:.,]? ?(?:an|the) official publication of the.+$~i', '', $str);
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

/** Annual Review of Cell and Developmental Biology cites these articles by volume. */
function is_annual_review_cell_biology(string $journal, string $doi = ''): bool {
    return preg_match('~\A10\.1146/annurev[-.]cellbio[-.]~i', $doi) === 1 ||
        str_equivalent($journal, 'Annual Review of Cell and Developmental Biology');
}

/**
 * Remove a CS1 language tag before comparing original script text.
 */
function citation_script_title_text(string $title): string {
    $title = safe_preg_replace('~^[a-z]{2,3}(?:-[a-z0-9]{1,8})*:\s*~i', '', $title);
    return normalize_unicode_nfc(mb_trim(html_entity_decode($title, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8')));
}

/** A Latin title and a genuinely non-Latin original can coexist. */
function citation_titles_use_romanization(string $title, string $native): bool {
    $latin = normalize_unicode_nfc(mb_trim(html_entity_decode($title, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8')));
    $native = citation_script_title_text($native);
    return preg_match('~\p{Latin}~u', $latin) === 1 &&
        preg_match('~^[\p{Latin}\p{M}0-9\s.,;:!?\'’"()\-–—]+$~uD', $latin) === 1 &&
        preg_match('~[^\p{Latin}\p{Common}\p{Inherited}]~u', $native) === 1;
}

/** See also str_equivalent() */
function titles_are_similar(string $title1, string $title2): bool {
    if (!titles_are_dissimilar($title1, $title2)) {
        return true;
    }
    // The first pass already transliterates diacritics. Removing their letters
    // here makes different titles such as Section Å and Section Ø identical.
    $title1 = str_replace('�', '', $title1);
    $title2 = str_replace('�', '', $title2);
    if (!titles_are_dissimilar($title1, $title2)) {
        return true;
    }
    return false;
}

function de_wikify(string $string, bool $decode_entities = false): string {
    $string = safe_preg_replace('~<[^>]*>~', '', $string);
    $string = safe_preg_replace('~\[\[[^\|\]]*\|([^\]]*)\]\]~', '$1', $string);
    $string = $decode_entities
        ? html_entity_decode($string, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8')
        : safe_preg_replace('~\&[\w\d]{2,7};~', '', $string);
    return str_replace(["[", "]", "'''", "''", "&"], ["", "", "'", "'", ""], $string);
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
    // Keep the original labels for identity checks before accent folding.
    $inSpaced = normalize_unicode_nfc(html_entity_decode($inTitle, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'));
    $dbSpaced = normalize_unicode_nfc(html_entity_decode($dbTitle, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'));
    // always decode new data
    $dbTitle = titles_simple(htmlspecialchars($dbSpaced, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML5, "UTF-8"));
    // old data both decoded and not
    $inTitle2 = titles_simple(strip_diacritics($inTitle));
    $inTitle = titles_simple(htmlspecialchars($inSpaced, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML5, "UTF-8"));
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
    // Require East Asian words to match at every length. Handle script variants
    // explicitly before allowing the ordinary typo tolerance elsewhere.
    $east_asian_match = citation_east_asian_title_match($inTitle, $dbTitle);
    if ($east_asian_match !== null) {
        return !$east_asian_match || compare_title_lettered_labels($inSpaced, $dbSpaced) === false ||
            compare_title_numbering($inSpaced, $dbSpaced) === false;
    }
    // Long titles still require matching section labels and numbers.
    if ((mb_strlen($inTitle) > 254 || mb_strlen($dbTitle) > 254 || mb_strlen($inTitle2) > 254) &&
            (compare_title_lettered_labels($inSpaced, $dbSpaced) === false ||
             compare_title_numbering($inSpaced, $dbSpaced) === false)) {
        return true;
    }
    return ((mb_strlen($inTitle) > 254 || mb_strlen($dbTitle) > 254)
                ? long_titles_are_dissimilar($inTitle, $dbTitle)
                : short_titles_are_dissimilar($inTitle, $dbTitle, $inSpaced, $dbSpaced))
    &&
    ((mb_strlen($inTitle2) > 254 || mb_strlen($dbTitle) > 254)
                ? long_titles_are_dissimilar($inTitle2, $dbTitle)
                : short_titles_are_dissimilar($inTitle2, $dbTitle, $inSpaced, $dbSpaced));
}

/** Compare East Asian titles with script-aware identity rules. */
function citation_east_asian_title_match(string $left, string $right): ?bool {
    $east_asian = '~[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]~u';
    $left_has_east_asian = preg_match($east_asian, $left) === 1;
    $right_has_east_asian = preg_match($east_asian, $right) === 1;
    if (!$left_has_east_asian && !$right_has_east_asian) {
        return null;
    }
    if (!$left_has_east_asian || !$right_has_east_asian) {
        return false;
    }
    if ($left === $right) {
        return true;
    }
    // Accept the common terminal spelling variant of these Japanese loanwords.
    // Keep the long vowel mark significant in other words.
    $loanword_variants = ['コンピューター' => 'コンピュータ', 'プリンター' => 'プリンタ'];
    $left = strtr($left, $loanword_variants);
    $right = strtr($right, $loanword_variants);
    // Common Japanese spellings can differ from simplified Chinese and can
    // occur in titles written entirely in kanji.
    $variants = ['圖' => '図', '國' => '国', '學' => '学', '醫' => '医', '體' => '体', '會' => '会', '歷' => '歴', '實' => '実'];
    if (preg_match('~[\p{Hiragana}\p{Katakana}]~u', $left . $right) === 1) {
        return strtr($left, $variants) === strtr($right, $variants);
    }
    if (strtr($left, $variants) === $right || strtr($right, $variants) === $left) {
        return true;
    }
    if (!function_exists('transliterator_transliterate')) {
        return false;
    }
    // Require one spelling to convert into the other. A shared simplified
    // form alone can conflate distinct traditional characters, e.g. 髮 and 發.
    return transliterator_transliterate('Traditional-Simplified', $left) === $right ||
        transliterator_transliterate('Traditional-Simplified', $right) === $left;
}

/** Compare long titles by characters with bounded fuzzy matching. */
function long_titles_are_dissimilar(string $left, string $right): bool {
    if ($left === $right) {
        return false;
    }
    $length = mb_strlen($left);
    if ($length > 4096 || $length !== mb_strlen($right)) {
        return true;
    }
    $allowed = min(32, intdiv($length, 50));
    return unicode_levenshtein($left, $right, $allowed) > $allowed;
}

/**
 * The comparison behind titles_are_dissimilar() for titles of ordinary length.
 *
 * Two titles are the same work when they are at most three single-character edits apart, which
 * absorbs typos, plurals, spelling variants and punctuation.  Two refinements stop that tolerance
 * from matching different works:
 *   - titles that are identical apart from their numbering ("Part I"/"Part II", "Volume 1"/"Volume 2",
 *     "Annual Report 2019"/"Annual Report 2020", "Hepatitis C"/"Hepatitis D") are different works,
 *     while "Part IV" and "Part 4" carry the same number;
 *   - a short title gets a smaller allowance, because three edits is most of a four-letter title.
 *
 * $inTitle and $dbTitle are the fully normalised strings; the "spaced" versions are the same titles,
 * decoded but with their words still separate.
 */
function short_titles_are_dissimilar(string $inTitle, string $dbTitle, string $inSpaced, string $dbSpaced): bool {
    // Check original, spaced titles first. Normalization can erase chapter numbers,
    // so an edit distance of zero does not always mean the same work.
    if (compare_title_lettered_labels($inSpaced, $dbSpaced) === false) {
        return true;
    }
    $numbering_matches = compare_title_numbering($inSpaced, $dbSpaced);
    if ($numbering_matches === false) {
        return true;
    }
    $shorter = min(mb_strlen($inTitle), mb_strlen($dbTitle));
    $distance = unicode_levenshtein($inTitle, $dbTitle);
    if ($distance === 0 || $numbering_matches === true ||
            titles_have_equivalent_final_y_plural($inTitle, $dbTitle)) {
        return false;
    }
    if ($shorter <= 4) {
        $allowed = 0;
    } elseif ($shorter <= 9) {
        $allowed = 1;
    } elseif ($shorter <= 19) {
        $allowed = 2;
    } else {
        $allowed = 3;
    }
    return $distance > $allowed;
}

/**
 * Recognize a terminal -ies/-y plural without relaxing the edit-distance
 * allowance for other short titles. Both arguments have already had their
 * punctuation and whitespace removed by titles_are_dissimilar().
 *
 * Numbering and lettered-label mismatches must be checked before this rule.
 * The suffix is ASCII, so mb_substr(..., '8bit') removes three bytes; the
 * untouched UTF-8 prefix is compared byte-for-byte.
 */
function titles_have_equivalent_final_y_plural(string $title1, string $title2): bool {
    if (preg_match('~[a-z]{3,}ies\z~', $title1) === 1 &&
            mb_substr($title1, 0, -3, '8bit') . 'y' === $title2) {
        return true;
    }
    return preg_match('~[a-z]{3,}ies\z~', $title2) === 1 &&
        mb_substr($title2, 0, -3, '8bit') . 'y' === $title1;
}

/**
 * Compare a *single-letter* title label (e.g. Hepatitis A/B or Section A/B).
 * Do not treat ordinary one-letter articles as labels: only recognize a single
 * alphabetic token following an established label word.
 * Return null when the label positions are not comparable.
 */
function compare_title_lettered_labels(string $title1, string $title2): ?bool {
    [$skeleton1, $labels1] = title_lettered_labels($title1);
    [$skeleton2, $labels2] = title_lettered_labels($title2);
    if ($labels1 === [] || $labels2 === []) {
        return null;
    }
    // A digit and a letter after the same label word are usually different
    // works (Section A vs Section 1). Do not reject equivalent Roman/Arabic
    // numbering, e.g. Part I vs Part 1 or Section IV vs Section 4.
    if ($labels1 === $labels2 && $skeleton1 === $skeleton2) {
        return true;
    }
    if ($skeleton1 === $skeleton2 || unicode_levenshtein($skeleton1, $skeleton2, 3) <= 3) {
        if ($labels1 !== $labels2) {
            return compare_title_numbering($title1, $title2) === true;
        }
    }
    return null;
}

/** @return array{0: string, 1: array<string>} */
function title_lettered_labels(string $title): array {
    $title = mb_strtolower(normalize_title_numeral_letters(strip_tags(html_entity_decode($title, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'))));
    $parts = preg_split('~[^\p{L}\p{M}\p{N}]+|(?<=\d)(?=\p{L})|(?<=\p{L})(?=\d)~u', $title, -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false) {
        return ['', []];
    }
    if (($parts[0] ?? '') === 'the') {
        array_shift($parts);
    }
    $skeleton = [];
    $labels = [];
    $label_words = ['appendix', 'class', 'figure', 'group', 'hepatitis', 'panel',
        'part', 'phase', 'physica', 'region', 'section', 'series', 'stage',
        'table', 'type', 'variant', 'vitamin', 'гепатит', 'μέρος', 'κεφάλαιο'];
    foreach ($parts as $index => $part) {
        if ($index > 0 && in_array($parts[$index - 1], $label_words, true) &&
                (preg_match('~^\p{L}\p{M}*$~u', $part) === 1 || preg_match('~^\p{Nd}{1,9}$~u', $part) === 1 ||
                 // Multi-character Roman numerals may also be section labels.
                 // Limit to <=50 so ordinary words like 'mix' are not mistaken for 1009.
                 (mb_strlen($part) > 1 &&
                  preg_match('~^(?=[ivxlcdm])m{0,3}(?:cm|cd|d?c{0,3})(?:xc|xl|l?x{0,3})(?:ix|iv|v?i{0,3})$~', $part) === 1 &&
                  roman_numeral_value($part) <= 50))) {
            $skeleton[] = '#letter';
            $labels[] = preg_match('~^\p{Nd}~u', $part) === 1 ? '#' . $part : $part;
        } else {
            $skeleton[] = $part;
        }
    }
    return [implode(' ', $skeleton), $labels];
}

/**
 * Split a decoded title into its words with every number replaced by "#", plus the list of
 * those numbers.  Arabic and Roman numerals are both numbers, and are returned as integers so
 * that "Part 1" and "Part I" carry the same number.
 *
 * @return array{0: string, 1: array<int|string>}
 */
function title_numbering(string $title, bool $require_roman_label = false): array {
    $title = mb_strtolower(normalize_title_numeral_letters(strip_tags($title)));
    $title = safe_preg_replace('~(第[〇零一二三四五六七八九十百千]+[章巻卷])~u', ' $1 ', $title);
    $parts = preg_split('~[^\p{L}\p{M}\p{N}]+|(?<=\d)(?=\p{L})|(?<=\p{L})(?=\d)~u', $title, -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false) {
        return ['', []];
    }
    if (($parts[0] ?? '') === 'the') {
        array_shift($parts); // "The Hobbit, Part 1" and "Hobbit, Part 2" have the same words
    }
    $skeleton = [];
    $numbers = [];
    foreach ($parts as $index => $part) {
        if (preg_match('~^[0-9]{1,9}$~', $part) === 1) {
            $skeleton[] = '#';
            $numbers[] = intval($part);
        } elseif (preg_match('~^第[〇零一二三四五六七八九十百千]+([章巻卷])$~u', $part, $ordinal) === 1) {
            $skeleton[] = '第#' . $ordinal[1];
            $numbers[] = $part;
        } elseif (preg_match('~^\p{Nd}{1,9}$~u', $part) === 1) {
            // Keep the original digits as an identity token. Casting non-ASCII
            // numerals with intval() would collapse different years to zero.
            $skeleton[] = '#';
            $numbers[] = $part;
        } elseif (preg_match('~^(?=[ivxlcdm])m{0,3}(?:cm|cd|d?c{0,3})(?:xc|xl|l?x{0,3})(?:ix|iv|v?i{0,3})$~', $part) === 1 &&
                // Single C/L/D/M tokens are more likely letter labels than 100/50/500/1000.
                (mb_strlen($part) > 1 || in_array($part, ['i', 'v', 'x'], true)) &&
                (!$require_roman_label || ($index > 0 && in_array($parts[$index - 1] ?? '',
                    ['act', 'appendix', 'book', 'chapter', 'episode', 'figure', 'grade',
                    'group', 'issue', 'lecture', 'panel', 'part', 'phase',
                    'region', 'section', 'series', 'stage', 'table', 'type',
                    'vol', 'volume', 'war'], true)))) {
            $skeleton[] = '#';
            $numbers[] = roman_numeral_value($part);
        } else {
            $skeleton[] = $part;
        }
    }
    return [implode(' ', $skeleton), $numbers];
}

/** Expand numeral letters such as Ⅲ to III without changing the rest of a title. */
function normalize_title_numeral_letters(string $title): string {
    $normalized = preg_replace_callback(
        '~\p{Nl}~u',
        static function (array $match): string {
            $value = Normalizer::normalize($match[0], Normalizer::FORM_KC);
            return is_string($value) ? $value : $match[0];
        },
        $title
    );
    return $normalized ?? $title;
}

/** Value of a valid lower-case Roman numeral. */
function roman_numeral_value(string $roman): int {
    $values = ['i' => 1, 'v' => 5, 'x' => 10, 'l' => 50, 'c' => 100, 'd' => 500, 'm' => 1000];
    $total = 0;
    $previous = 0;
    for ($i = mb_strlen($roman, '8bit') - 1; $i >= 0; $i--) {
        $value = $values[$roman[$i]] ?? 0;
        $total += $value < $previous ? -$value : $value;
        $previous = max($previous, $value);
    }
    return $total;
}

/**
 * Compare numbers in titles with the same words or a minor spelling difference.
 * Return null when edit distance should decide.
 */
function compare_title_numbering(string $title1, string $title2): ?bool {
    // Words such as "mix", "li", and "xi" are valid Roman-numeral strings, but
    // should not be interpreted as numbers without a preceding label (Part, War,
    // Volume, etc.). Use the label-restricted parser for *all* comparisons.
    [$skeleton1, $numbers1] = title_numbering($title1, true);
    [$skeleton2, $numbers2] = title_numbering($title2, true);
    if ($numbers1 === [] || $numbers2 === [] || count($numbers1) !== count($numbers2)) {
        return null;
    }
    if ($skeleton1 === $skeleton2) {
        return $numbers1 === $numbers2;
    }
    if ($numbers1 !== $numbers2 && unicode_levenshtein($skeleton1, $skeleton2, 3) <= 3) {
        return false; // Several typos do not erase a different part number.
    }
    return null;
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
    $inTitle = safe_preg_replace('~\([^\s\(\)]+ Edition\)$~iu', '', $inTitle);
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
    // Normalize equivalent connectors before spaces are removed
    $inTitle = str_replace(" & ", " and ", $inTitle);
    $inTitle = str_replace(" / ", " and ", $inTitle);
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
    // greek
    $inTitle = strip_diacritics($inTitle);
    return str_remove_irrelevant_bits($inTitle);
}

function normalize_unicode_nfc(string $input): string {
    if ($input === '' || !mb_check_encoding($input, 'UTF-8')) {
        return $input;
    }
    if (!class_exists(Normalizer::class)) {
        throw new RuntimeException('Unicode NFC normalization support is required.');
    }

    $normalized = Normalizer::normalize($input, Normalizer::FORM_C);
    return is_string($normalized) ? $normalized : $input;
}

/** With a cutoff, return cutoff + 1 for distances above the cutoff. */
function unicode_levenshtein(string $left, string $right, ?int $cutoff = null): int {
    if (!mb_check_encoding($left, 'UTF-8') || !mb_check_encoding($right, 'UTF-8')) {
        $distance = levenshtein($left, $right);
        return $cutoff === null ? $distance : min($distance, $cutoff + 1);
    }

    $left = normalize_unicode_nfc($left);
    $right = normalize_unicode_nfc($right);
    if ($left === $right) {
        return 0;
    }

    $left_chars = preg_split('~~u', $left, -1, PREG_SPLIT_NO_EMPTY);
    $right_chars = preg_split('~~u', $right, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($left_chars) || !is_array($right_chars)) {
        return levenshtein($left, $right); // @codeCoverageIgnore
    }

    $left_length = count($left_chars);
    $right_length = count($right_chars);
    $band = $cutoff ?? max($left_length, $right_length);
    $outside = $band + 1;
    if (abs($left_length - $right_length) > $band) {
        return $outside;
    }

    $previous = range(0, min($right_length, $band));
    foreach ($left_chars as $left_pos => $left_char) {
        $row = $left_pos + 1;
        $current = $row <= $band ? [$row] : [];
        $start = max(1, $row - $band);
        $end = min($right_length, $row + $band);
        for ($column = $start; $column <= $end; ++$column) {
            $current[$column] = min(
                ($previous[$column] ?? $outside) + 1,
                ($current[$column - 1] ?? $outside) + 1,
                ($previous[$column - 1] ?? $outside) + ($left_char === $right_chars[$column - 1] ? 0 : 1)
            );
        }
        if ($cutoff !== null && ($current === [] || min($current) > $band)) {
            return $outside;
        }
        $previous = $current;
    }

    return min($previous[$right_length], $outside);
}

function strip_diacritics (string $input): string {
    $input = normalize_unicode_nfc($input);
    return str_replace(array_keys(MAP_DIACRITICS), array_values(MAP_DIACRITICS), $input);
}

function normalize_c1_quotes(string $str): string {
    // Normalize C1 control characters (0x91-0x94, U+0091-U+0094) to ASCII quotes
    if ($str === '') {
        return '';
    }

    // Skip valid multibyte sequences before handling standalone legacy bytes.
    // C2 + a C1 quote byte is itself a UTF-8 encoded quote control.
    $valid_utf8 = '[\xC2-\xDF][\x80-\xBF]|'
        . '\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE-\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|'
        . '\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}';
    $str = safe_preg_replace_callback('~\xC2[\x91-\x94]|(?:' . $valid_utf8 . ')(*SKIP)(*F)|[\x91-\x94]~',
        static fn (array $match): string => in_array(mb_substr($match[0], -1, 1, '8bit'), ["\x91", "\x92"], true) ? "'" : '"',
        $str);

    return $str;
}

function straighten_quotes(string $str, bool $do_more): string { // (?<!\') and (?!\') means that it cannot have a single quote right before or after it
    // These Regex can die on Unicode because of backward looking
    if ($str === '') {
        return '';
    }
    $str = normalize_c1_quotes($str);
    if (!mb_check_encoding($str, 'UTF-8')) {
        return $str;
    }
    $str = str_replace('Hawaiʻi', 'CITATION_BOT_PLACEHOLDER_HAWAII', $str);
    $str = str_replace('Ha‘apai', 'CITATION_BOT_PLACEHOLDER_HAAPAI', $str);
    $str = safe_preg_replace('~(?<!\')(?:&#821[679];|&#39;|&#x201[89];|[\x{FF07}\x{2018}-\x{201B}`]|&[rl]s?[b]?quo;)(?!\')~u', "'", $str);
    if ((mb_strpos($str, '&rsaquo;') !== false && mb_strpos($str, '&lsaquo;') !== false) ||
            (mb_strpos($str, '‹') !== false && mb_strpos($str, '›') !== false)) { // Only replace single angle quotes if some of both
            $str = safe_preg_replace('~&[lr]saquo;|[\x{2039}\x{203A}]|[‹›]~u', "'", $str);  // Websites tiles: Jobs ›› Iowa ›› Cows ›› Ames
    }
    $str = safe_preg_replace('~&#822[013];|[\x{201C}-\x{201F}]|&[rlb][d]?quo;~u', '"', $str);
    if (in_array(WIKI_BASE, ENGLISH_WIKI, true) && (
            (mb_strpos($str, '&raquo;') !== false && mb_strpos($str, '&laquo;') !== false) ||
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

/**
 * Converts to title case, unless obviously a url
 */
function title_case(string $text): string {
    if (mb_stripos($text, 'www.') !== false || mb_stripos($text, 'www-') !== false || mb_stripos($text, 'http://') !== false || mb_stripos($text, 'https://') !== false) {
        return $text; // Who knows - duplicate code below
    }
    // "ir" may be a lowercase word, while "IR" is an abbreviation. Preserve
    // their explicit case, rather than turning both into "Ir".
    $parts = preg_split('~(?<![\p{L}\p{N}_])(ir|IR)(?![\p{L}\p{N}_])~u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return mb_convert_case($text, MB_CASE_TITLE, "UTF-8");
    }
    foreach ($parts as $i => $part) {
        if ($i % 2 === 0 || ($part === 'ir' && $i === 1 && mb_trim($parts[0]) === '')) {
            $parts[$i] = mb_convert_case($part, MB_CASE_TITLE, "UTF-8");
        }
    }
    return implode('', $parts);
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

    if (mb_stripos($new_case, 'www.') !== false || mb_stripos($new_case, 'www-') !== false || mb_stripos($new_case, 'http://') !== false || mb_stripos($new_case, 'https://') !== false) {
        return $new_case; // Who knows - duplicate code above
    }

    if ($new_case === mb_strtoupper($new_case)
            && mb_strlen(str_replace(["[", "]"], "", mb_trim($in))) > 6
            ) {
        // ALL CAPS to Title Case
        $new_case = mb_convert_case($new_case, MB_CASE_TITLE, "UTF-8");
        // ALL CAPS loses the distinction between French "eau" and the acronym "EAU".
        // Recover EAU only in well-known European Association of Urology contexts.
        // The acronym is also used with a year and/or hyphen in journal titles.
        // Do not promote French "eau", including the elisions l'eau and d'eau.
        $new_case = safe_preg_replace(
            "~(?<!\b[dl]['´])\beau(?=[\s\-‐‑–—]+(?:20\d{2}[\s\-‐‑–—]+)?(?:guidelines?|congress(?:es)?|recommendations?|annual[\s\-‐‑–—]+(?:congress|meeting)|association)\b)~ui",
            'EAU',
            $new_case
        );
    }

    // Implicit acronyms
    $new_case = ' ' . $new_case . ' ';
    $new_case = safe_preg_replace_callback("~([^\w&])(?!str\.)([b-df-hj-np-tv-xz]{3,})(?=\W)~ui", // "str." is the abbreviation in "s. str."
            static function (array $matches): string {  // Three or more consonants.  NOT Y
                if (preg_match('~\p{Ll}\p{Lu}~u', $matches[0]) === 1) {
                    return $matches[0]; // Mixed case such as rbcL, ndhF or trnL is deliberate (gene names), not an acronym
                }
                $word = mb_strtolower($matches[2]);
                if (in_array($word, ['mschr', 'wschr', 'ztschr'], true)) {
                    return $matches[1] . mb_ucfirst($word); // German journal abbreviations
                }
                return mb_strtoupper($matches[0]);
            },
            $new_case);
    $new_case = safe_preg_replace_callback("~[^\w&](?!eau\W)[aeiou]{3,}(?=\W)~ui", // "eau" is the French word (l'eau), not an acronym; an existing "EAU" is left as it is
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
        // The abbreviations "s. str." and "s. l." (sensu stricto, sensu lato) and "str." (street) do not end a sentence
        $new_case = safe_preg_replace_callback("~(?<![\s(\[]s)(?<![\s(\[.]str)(?<![\s(\[]s\. l)(?<![\s(\[]s\.l)[?.:!/]\s+[a-z]~u", // Capitalize after punctuation
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
        "~(\s[LD][\'\x{00B4}])((?:\p{L}\p{M}*)+)~u",
        static function (array $matches): string {
            return mb_strtolower($matches[1]) . mb_ucfirst($matches[2]);
        },
        ' ' . $new_case
    );

    /** Italian dell'xxx words */
    $new_case = safe_preg_replace_callback(
        "~(\s)(Dell|Degli|Delle)([\'\x{00B4}](?:\p{L}\p{M}*){3})~u",
        static function (array $matches): string {
            return $matches[1] . mb_strtolower($matches[2]) . $matches[3];
        },
        $new_case
    );

    $new_case = mb_ucfirst(mb_trim($new_case));

    // "take/took part a little/long time" uses the indefinite article, not a label.
    // These are deliberately limited grammatical exceptions; an ambiguous Part A
    // is otherwise preserved when the input explicitly supplies an uppercase A.
    $new_case = safe_preg_replace(
        '~(\b(?i:(?:take|takes|taking|taken|took)\s+part)\s+)A(?=\s+(?i:bit|couple|few|little|lot|while|long|short|great|brief)\b(?!\p{Pd}))~u',
        '$1a',
        $new_case
    );
    // "in part a result" is likewise an article, not "Part A".
    $new_case = safe_preg_replace(
        '~(\b(?i:in\s+part)\s+)A(?=\s+(?i:result|consequence)\b(?!\p{Pd}))~u',
        '$1a',
        $new_case
    );

    // Lowercase each solitary A unless the preceding word makes it a label.
    $has_non_latin_script = preg_match('~[^\p{Latin}\p{Common}\p{Inherited}]~u', $in) === 1;
    $new_case = safe_preg_replace_callback('~(\w+)(\s+)A(?=\s+\w)~u',
        static function (array $matches) use ($has_non_latin_script): string {
            if ($has_non_latin_script || in_array(mb_strtolower($matches[1]),
                ['class', 'section', 'part', 'series', 'physica', 'vitamin', 'vitamine', 'vitamines'], true)) {
                return $matches[0];
            }
            return $matches[1] . $matches[2] . 'a';
        },
        $new_case
    );
    // but not in "U S A"
    $new_case = mb_trim(str_replace(" U S a ", " U S A ", ' ' . $new_case . ' '));

    // Cyrillic "и" (and) and "у" (at) are small words, like the solitary 'a'.  They are left alone as the first or last word,
    // and the other single letters (в, с, к, о, а) are not touched because they double as labels, as in "Гепатит В".
    $new_case = safe_preg_replace_callback(
        "~(?<=\p{Cyrillic}\s)[ИУ](?=\s\p{Cyrillic})~u",
        static function (array $matches): string {
            return mb_strtolower($matches[0]);
        },
        $new_case
    );

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

    // Single letter at end should be capitalized (e.g. J Chem Phys E),
    // except for a non-Series label word followed by an originally lowercase article a.
    // Series has historically promoted series letters, even when lowercase in the input.
    if (mb_substr($new_case, -2, 1) === ' ' &&
            preg_match('~\b(?i:class|section|part|physica)\s+a$~u', mb_trim($in)) !== 1) {
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
                $new_case = mb_substr_replace($new_case, mb_trim($matches_in[$key][0]), $matches_out[$key][1], 3, '8bit'); // PREG_OFFSET_CAPTURE is always in bytes
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
                $new_case = mb_substr_replace($new_case, mb_trim($matches_in[$key][0]), $matches_out[$key][1], 3, '8bit'); // PREG_OFFSET_CAPTURE is always in bytes
            }
        }
    }

    // Preserve the existing convention: Series a and b -> Series A and B.
    // Unlike Class/Part/Section, Series letters were promoted before this PR.
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
        "~ (?:Ii|Iii|Iv|Vi|Vii|Viii|Ix)$~u",
        static function (array $matches): string {
            return mb_strtoupper($matches[0]);
        },
        $new_case);
    $new_case = safe_preg_replace_callback(
        "~^(?:Ii|Iii|Iv|Vi|Vii|Viii|Ix):~u",
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
    if (mb_strpos($in, 'İ') !== false) {
        // Unicode lowercase İ is i plus combining dot; Turkish lowercase i
        // already has its dot, so retaining both changes the visible spelling.
        $new_case = str_replace("i\u{0307}", 'i', $new_case);
    }
    return $new_case;
}

function mb_strrev(string $string, string $encode = ''): string {
    $encoding = $encode !== '' ? $encode : mb_internal_encoding();
    $chars = mb_str_split($string, 1, $encoding);
    return implode('', array_reverse($chars));
}

function mb_ucwords(string $string): string {
    return preg_replace_callback('~(\S)(\S*\s*)|(\s+)~u',
        static function (array $match): string {
            return $match[3] ?? mb_strtoupper($match[1]) . $match[2];
        },
        $string) ?? $string;
}

function mb_substr_replace(
    string $string,
    string $replacement,
    int $start,
    int $length,
    ?string $encoding = null
): string {
    if ($encoding === null) {
        return mb_substr($string, 0, $start) .
            $replacement .
            mb_substr($string, $start + $length, null);
    } else {
        return mb_substr($string, 0, $start, $encoding) .
            $replacement .
            mb_substr($string, $start + $length, null, $encoding);
    }
}

function remove_brackets(string $string): string {
    return str_replace(['(', ')', '{', '}', '[', ']'], '', $string);
}

// ============================================= Data processing functions ======================================

/**
 * Convert an unambiguous localized day–month–year date for English CS1.
 * Return null for unfamiliar formats and an empty string for invalid dates.
 */
function localized_citation_date(string $date): ?string {
    $date = mb_strtolower(normalize_unicode_nfc(mb_trim($date)));
    if (preg_match('~^([0-9]{1,2})\.?\s+(\p{L}[\p{L}\p{M}]*)\s+([0-9]{4})$~uD', $date, $parts) !== 1) {
        return null;
    }
    $months = [
        'janvier' => 1, 'février' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'août' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12,
        'januar' => 1, 'februar' => 2, 'märz' => 3, 'april' => 4, 'juni' => 6,
        'juli' => 7, 'august' => 8, 'september' => 9, 'oktober' => 10, 'dezember' => 12,
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
        'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
        'января' => 1, 'февраля' => 2, 'марта' => 3, 'апреля' => 4, 'мая' => 5, 'июня' => 6,
        'июля' => 7, 'августа' => 8, 'сентября' => 9, 'октября' => 10, 'ноября' => 11, 'декабря' => 12,
    ];
    $month = $months[$parts[2]] ?? null;
    if ($month === null) {
        return null;
    }
    $day = (int) $parts[1];
    $year = (int) $parts[3];
    if (!checkdate($month, $day, $year)) {
        return '';
    }
    return $day . ' ' . MONTH_SEASONS[$month - 1] . ' ' . $parts[3];
}

/**
 * Wrapper to change all pre-1900 dates to just years
 */
function tidy_date(string $string): string {
    $string = tidy_date_inside($string);
    if ($string === '') {
        return $string;
    }
    $time = strtotime($string);
    if ($time === false) {
        return $string;
    }
    // Check for future dates (more than 3 days ahead)
    $three_days_future = time() + 259200; // 3 days in seconds
    if ($time > $three_days_future) {
        report_warning("Rejected future date: " . echoable($string) . ". Dates cannot be in the future.");
        return '';
    }
    $old = strtotime('1 January 1900');
    if ($old < $time) {
        return $string;
    }
    $new = date('Y', $time);
    if (mb_strlen($new) === 4) {
        return mb_ltrim($new, "0"); // Also cleans up 0000
    }
    return $string;
}

/** Recognize an explicit calendar day before localizing a publication date. */
function publication_date_has_explicit_day(string $date): bool {
    return preg_match(
        '~(?:^|[^0-9])(?:[0-9]{4}[-/][0-9]{1,2}[-/][0-9]{1,2}|[0-9]{1,2}[-/.][0-9]{1,2}[-/.][0-9]{2,4})(?![0-9])~',
        $date
    ) === 1 || preg_match(
        '~^(?:[0-9]{4}-[A-Za-z]{3,9}-[0-9]{1,2}(?![0-9])|(?:[A-Za-z]+,?\s+)?(?:[0-9]{1,2}(?:st|nd|rd|th|\.)?[\s-]+[A-Za-z]+\.?|[A-Za-z]+\.?[\s-]+[0-9]{1,2}(?:st|nd|rd|th)?)[,\s-]+[0-9]{2,4}(?![0-9]))~i',
        $date
    ) === 1;
}

function tidy_date_inside(string $string): string {
    $string = mb_trim($string);
    $string = safe_preg_replace('~^Date published \(\s*~', '', $string); // seen this
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
    // CS1 date fields reject BCE dates. Check the sign before stripping dashes.
    if (preg_match('~\A[-\x{2212}](?:\d+\z|\d{4,}-\d)~u', $string)) {
        return '';
    }
    // Google sends ranges. Check each endpoint before PHP can roll it forward.
    if (preg_match('~^(\d{4})-(\d{2})-(\d{2})\s+-\s+(\d{4})-(\d{2})-(\d{2})$~', $string, $matches)) {
        $start = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        $end = $matches[4] . '-' . $matches[5] . '-' . $matches[6];
        if (!checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]) ||
            !checkdate((int) $matches[5], (int) $matches[6], (int) $matches[4]) ||
            $start > $end) {
            return '';
        }
        $start_date = new DateTimeImmutable($start);
        $end_date = new DateTimeImmutable($end);
        return $start_date->format($matches[1] === $matches[4] ? 'j F' : 'j F Y') . ' – ' . $end_date->format('j F Y');
    }
    // Huge amount of character cleaning
    if (mb_strlen($string, '8bit') !== mb_strlen($string)) {    // Convert all multi-byte characters to dashes
        $cleaned = '';
        $the_str_length = mb_strlen($string);
        for ($i = 0; $i < $the_str_length; $i++) {
            $char = mb_substr($string, $i, 1);
            if (mb_strlen($char, '8bit') === mb_strlen($char)) {
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
    // https://stackoverflow.com/questions/29917598/why-does-0000-00-00-000000-return-0001-11-30-000000
    if (mb_strpos($string, '0001-11-30') !== false) {
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
            if (!checkdate(intval($matches[1]), intval($matches[2]), intval($matches[3]))) {
                return $matches[3]; // Not a real day, such as 4/31/2001. Do not know. just give year
            }
            if (mb_strlen($matches[1]) === 1) {
                $matches[1] = '0' . $matches[1];
            }
            return $matches[3] . '-' . $matches[1] . '-' . $matches[2];
        } elseif (intval($matches[2]) < 13 && intval($matches[1]) > 12) {
            if (!checkdate(intval($matches[2]), intval($matches[1]), intval($matches[3]))) {
                return $matches[3]; // Not a real day, such as 31/2/1913. Do not know. just give year
            }
            if (mb_strlen($matches[2]) === 1) {
                $matches[2] = '0' . $matches[2];
            }
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        } elseif (intval($matches[2]) > 12 && intval($matches[1]) > 12) {
            return '';
        } elseif (intval($matches[1]) === intval($matches[2])) {
            $month_day = intval($matches[1]);
            if (!checkdate($month_day, $month_day, intval($matches[3]))) {
                return $matches[3];
            }
            $padded = sprintf('%02d', $month_day);
            return $matches[3] . '-' . $padded . '-' . $padded;
        } else {
            return $matches[3];// do not know. just give year
        }
    }
    $string = mb_trim($string);
    if (preg_match('~^(\d{4}\-\d{2}\-\d{2})T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$~', $string, $matches)) {
        return tidy_date_inside($matches[1]); // Remove time zone stuff from standard date format
    }
    if (preg_match('~^\d+$~', $string)) {
        $year = intval($string);
        if ($year < 2 || $year > (int) date("Y") + 10) {
            return ''; // A number that is not a year; probably garbage
        }
        return (string) $year;
    }
    if (preg_match('~^(\d{1,2}) ([A-Za-z]+\.?), ?(\d{4})$~', $string, $matches)) { // strtotime('3 October, 2016') gives 2019-10-03.    The comma is evil and strtotime is stupid
        $string = $matches[1] . ' ' . $matches[2] . ' ' . $matches[3];   // Remove comma
    }
    // A dot after a day is an ordinal separator in several date styles.
    $string = safe_preg_replace('~^(\d{1,2})\.(?=\s+[A-Za-z])~', '$1', $string);
    $parsed_date = date_parse($string);
    if (is_int($parsed_date['year']) && in_array('The parsed date was invalid', $parsed_date['warnings'], true)) {
        // strtotime() would quietly roll 31 June over into July, or 30 February into March
        if ($parsed_date['year'] < 100 || $parsed_date['year'] > (int) date("Y") + 10) {
            return ''; // We got an invalid year
        }
        if ($parsed_date['day'] === 0 && is_int($parsed_date['month']) && $parsed_date['month'] >= 1 && $parsed_date['month'] <= 12) {
            $month_start = mktime(0, 0, 0, $parsed_date['month'], 1, $parsed_date['year']);
            if ($month_start !== false) {
                return date('F Y', $month_start); // 2001-05-00 is the usual way to say day unknown
            }
        }
        return (string) $parsed_date['year']; // Do not know the day or month. just give year
    }
    $time = strtotime($string);
    if ($time !== false && !is_int($parsed_date['year'])) {
        // No year was written ("12 hours ago", "May 15", "10:30", "March, 2020"), so strtotime()
        // has borrowed today's.  That is not a date we were given; let the fallbacks below decide.
        $time = false;
    }
    if ($time !== false && is_int($parsed_date['year']) && is_int($parsed_date['month']) &&
        is_int($parsed_date['day']) && $parsed_date['year'] >= 1000 && checkdate($parsed_date['month'], $parsed_date['day'], $parsed_date['year'])) {
        // strtotime() interprets timezone-bearing timestamps as instants: formatting that
        // timestamp in the runner's timezone may change the written calendar day.
        // Use the date_parse() calendar fields, without making another timestamp.
        $year = $parsed_date['year'];
        if ($year > (int) date("Y") + 10) {
            return '';
        }
        // PHP fills in day 1 for bare year/month forms. Keep that inferred day out.
        if ($parsed_date['day'] === 1 && !publication_date_has_explicit_day($string)) {
            return date('F Y', $time);
        }
        return sprintf('%04d-%02d-%02d', $year, $parsed_date['month'], $parsed_date['day']);
    }
    if ($time !== false) {
        $day = date('d', $time);
        $year = intval(date('Y', $time));
        if ($year < 100 || $year > (int) date("Y") + 10) {
            return ''; // We got an invalid year
        }
        if ($day === '01' && !publication_date_has_explicit_day($string)) {
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

/**
 * See Comment::PLACEHOLDER_TEXT for syntax
 */
function remove_comments(string $string): string {
    $string = safe_preg_replace('~# # # CITATION_BOT_PLACEHOLDER_COMMENT \d+ # # #~isu', "", $string);
    return safe_preg_replace("~<!--.*?-->~us", "", $string);
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

function is_placeholder_bib_locator(string $value): bool {
    $trimmed = mb_trim(mb_strtolower($value));
    if ($trimmed === '') {
        return false;
    }
    return in_array($trimmed, IN_PRESS_ALIASES, true);
}

/**
 * True when a title contains <math> markup, either live or as the placeholder that stands in for
 * it while a page is being processed (see Mathematics::PLACEHOLDER_TEXT).  Online metadata cannot be
 * trusted to reproduce math correctly (e.g. CrossRef gives the italic ''s'' form), so a title like
 * this must not be replaced by a fetched title.
 */
function title_has_math_markup(string $title): bool {
    return mb_stripos($title, '<math') !== false
        || mb_stripos($title, 'CITATION_BOT_PLACEHOLDER_MATHEMATICS') !== false;
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

/** Decode percent escapes only when the resulting value remains valid UTF-8. */
function urldecode_valid_utf8(string $value): string {
    $decoded = urldecode($value);
    return mb_check_encoding($decoded, 'UTF-8') ? $decoded : $value;
}

function hdl_decode(string $hdl): string {
    $hdl = urldecode_valid_utf8($hdl);
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
    if (preg_match('~^([A-Z][a-z]+ \d{4})[\-–]([A-Z][a-z]+ \d{4})$~u', $input, $matches)) { // Missing space around en dash for range of full dates
        return $matches[1] . ' – ' . $matches[2];
    }
    if (preg_match('~^([A-Z][a-z]+), (\d{4})$~', $input, $matches)) { // Comma with month/season and year
        return $matches[1] . ' ' . $matches[2];
    }
    if (preg_match('~^([A-Z][a-z]+), (\d{4})[\-–](\d{4})$~u', $input, $matches)) { // Comma with month/season and years
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
    if (mb_strlen($new) === 10 && preg_match('~\A\d{9}[0-9Xx]\z~D', $new) === 1) {
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
    } elseif (mb_strlen($new) === 13 && preg_match('~\A\d{13}\z~D', $new) === 1) {
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

function isbn_valid(string $isbn): bool {
    $digits = str_replace('x', 'X', $isbn);
    $digits = str_replace(['–', '—', '−', '‐', '‑', '‒', '―'], '-', $digits);
    $digits = str_replace(['-', ' '], '', $digits);
    if (preg_match('~^[0-9]{9}[0-9X]$~', $digits) === 1) {
        // ISBN-10: each digit weighted by 10..1 (X = 10); valid if the sum is a multiple of 11
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $digit = $digits[$i] === 'X' ? 10 : (int) $digits[$i];
            $sum = $sum + $digit * (10 - $i);
        }
        return $sum % 11 === 0;
    }
    if (preg_match('~^(978|979)[0-9]{10}$~', $digits) === 1) {
        // ISBN-13 must begin 978/979; the 9790 prefix is reserved for ISMN
        if (mb_substr($digits, 0, 4) === '9790') {
            return false;
        }
        // ISBN-13: digits weighted alternately by 1 and 3; valid if the sum is a multiple of 10
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum = $sum + (int) $digits[$i] * ($i % 2 ? 3 : 1);
        }
        return $sum % 10 === 0;
    }
    return false;
}

/**
 * Validate an arXiv identifier.  Mirrors the bot's own URL-extraction
 * patterns (URLtools.php): the old archive/number form or the new YYMM.NNNNN
 * form, each with an optional lowercase version suffix.  CS1 additionally
 * constrains the year/month ranges and the digit count by year; we
 * deliberately do not enforce those so values the URL path accepts are never
 * rejected here (defense in depth, not strict CS1 fidelity).
 */
function arxiv_id_valid(string $value): bool {
    if (preg_match('~^[a-zA-Z][a-zA-Z.\-]+/\d{7}(v\d+)?$~', $value) === 1) {
        return true; // old style: e.g. hep-th/9901001
    }
    if (preg_match('~^\d{4}\.\d{4,5}(v\d+)?$~', $value) === 1) {
        return true; // new style: e.g. 1706.05013 or 1706.05013v2
    }
    return false;
}

/**
 * Validate a PubMed identifier: one to eight digits.
 */
function pmid_valid(string $value): bool {
    if (preg_match('~^\d{1,8}$~', $value) !== 1) {
        return false;
    }
    return intval($value) >= 1;
}

/**
 * Validate a PubMed Central identifier: one to eight digits, optionally with
 * a leading "PMC" prefix (which CS1 renders as a maintenance category).
 */
function pmc_valid(string $value): bool {
    if (preg_match('~^[Pp][Mm][Cc](\d+)$~', $value, $match) === 1) {
        $value = $match[1];
    }
    if (preg_match('~^\d{1,8}$~', $value) !== 1) {
        return false;
    }
    return intval($value) >= 1;
}

/** Validate the structure and publication window of a bioRxiv or medRxiv DOI. */
function rxiv_id_valid(string $value, string $server = ''): bool {
    if (!in_array($server, ['', 'biorxiv', 'medrxiv'], true)) {
        return false;
    }
    if (preg_match('~\A10\.1101/([0-9]{6}|[0-9]{8})(?:v[0-9]+)?\z~', $value, $legacy) === 1) {
        return $server === '' || ($server === 'biorxiv' ? mb_strlen($legacy[1], '8bit') === 6 : mb_strlen($legacy[1], '8bit') === 8);
    }
    if (preg_match('~\A10\.(1101|64898)/(20[0-9]{2})\.([0-9]{2})\.([0-9]{2})\.([0-9]{6}|[0-9]{8})(?:v[0-9]+)?\z~', $value, $match) !== 1 ||
        !checkdate((int) $match[3], (int) $match[4], (int) $match[2])) {
        return false;
    }
    $is_medrxiv = mb_strlen($match[5], '8bit') === 8;
    if (($server === 'biorxiv' && $is_medrxiv) || ($server === 'medrxiv' && !$is_medrxiv)) {
        return false;
    }
    // Both servers issued dated identifiers in December 2019. This is also
    // the lower bound used by the deployed CS1 identifier handlers.
    $earliest = '20191211';
    $tomorrow = new DateTimeImmutable('tomorrow', new DateTimeZone('UTC'));
    $date = $match[2] . $match[3] . $match[4];
    return strcmp($date, $earliest) >= 0 && strcmp($date, $tomorrow->format('Ymd')) <= 0;
}

/** CS1 spells legacy medRxiv identifiers without their DOI prefix. */
function rxiv_citation_id(string $value, string $server): string {
    if (preg_match('~\A(?:10\.1101/)?([0-9]{6}|[0-9]{8})(?:v[0-9]+)?\z~', $value, $match) === 1) {
        return $server === 'medrxiv' ? $match[1] : rxiv_doi_from_id($match[1]);
    }
    return rxiv_doi_from_id($value);
}

/**
 * Turn the value of a biorxiv= or medrxiv= parameter into a full DOI.
 * The parameter may hold a bare identifier (the 10.1101 prefix is assumed) or
 * an already-complete DOI with either the 10.1101 or the newer 10.64898
 * prefix, which must not be given a second prefix.
 */
function rxiv_doi_from_id(string $value): string {
    if (mb_strpos($value, '10.1101/') === 0 || mb_strpos($value, '10.64898/') === 0) {
        return $value;
    }
    return '10.1101/' . $value;
}

/**
 * Validate a bibcode: exactly 19 characters whose first four characters form
 * a year beginning with 1 or 2 (matching REGEXP_BIBCODE), with only letters,
 * digits, underscores, ampersands, and dots elsewhere.  CS1 additionally
 * requires a letter at position five and a year within 1000..next year; we
 * deliberately do not enforce those so values the URL path accepts are never
 * rejected here (defense in depth, not strict CS1 fidelity).
 */
function bibcode_valid(string $value): bool {
    if (mb_strlen($value) !== 19) {
        return false;
    }
    // Body charset covers CS1's allowed set (letters, digits, _, &, ., ', +, -).
    if (preg_match('~^[12]\d{3}[\w&.+\'-]{15}$~', $value) !== 1) {
        return false;
    }
    return true;
}

/**
 * Validate a DOI against CS1's structural rules: the 10. directory indicator,
 * a registrant of digits with optional dot-separated numeric subcodes, a
 * slash, and a non-empty suffix.  CS1 additionally requires a valid
 * registrant range; values the bot must still process (test/reserved
 * registrants such as 10.0001) are left to the blocklists, so no range is
 * enforced here.  Spaces, en dashes, and trailing punctuation are refused.
 */
function doi_valid(string $value): bool {
    if (preg_match('~[\s\x{2013}]~u', $value) === 1) {
        return false;
    }
    if (preg_match('~[.,]$~', $value) === 1) {
        return false;
    }
    if (preg_match('~^10\.[0-9]+(?:\.[0-9]+)*/\S+$~u', $value) !== 1) {
        return false;
    }
    return true;
}

/**
 * Validate an SSRN identifier against CS1's rules: a simple number without
 * punctuation or spaces, between 100 and 7500000.
 */
function ssrn_valid(string $value): bool {
    if (preg_match('~^\d+$~', $value) !== 1) {
        return false;
    }
    $number = intval($value);
    return $number >= 100 && $number <= 7500000;
}

/**
 * Validate a Semantic Scholar corpus identifier against CS1's rules: a simple
 * number without punctuation or spaces, between 1 and 290000000.
 */
function s2cid_valid(string $value): bool {
    if (preg_match('~^\d+$~', $value) !== 1) {
        return false;
    }
    $number = intval($value);
    return $number >= 1 && $number <= 290000000;
}

/**
 * Validate a JSTOR identifier against CS1's documented refusals: the value
 * must not contain the string 'jstor' (case-insensitive), a URI scheme, or
 * spaces.  Non-numeric stable forms (e.g. j.ctt802dw) remain acceptable.
 */
function jstor_valid(string $value): bool {
    if (mb_stripos($value, 'jstor') !== false) {
        return false;
    }
    if (preg_match('~^[a-zA-Z][a-zA-Z0-9+.\-]*://~', $value) === 1) {
        return false;
    }
    if (preg_match('~\s~u', $value) === 1) {
        return false;
    }
    if ($value === '') {
        return false;
    }
    return true;
}

/**
 * Validate a handle identifier against CS1's rules: no spaces or en dashes,
 * and no trailing punctuation.  Further validation is not performed.
 */
function hdl_valid(string $value): bool {
    if (preg_match('~[\s\x{2013}]~u', $value) === 1) {
        return false;
    }
    if (preg_match('~[.,]$~', $value) === 1) {
        return false;
    }
    if (mb_strpos($value, '/') === false) {
        return false;
    }
    return true;
}

/**
 * Validate an OCLC identifier against CS1's documented forms: ocm followed
 * by 8 digits, ocn followed by 9 digits, on followed by 10 or more digits,
 * (OCoLC) followed by digits without leading zeros, or plain digits.
 */
function oclc_valid(string $value): bool {
    if (preg_match('~^ocm\d{8}$~', $value) === 1) {
        return true;
    }
    if (preg_match('~^ocn\d{9}$~', $value) === 1) {
        return true;
    }
    if (preg_match('~^on\d{10,}$~', $value) === 1) {
        return true;
    }
    if (preg_match('~^\(OCoLC\)[1-9]\d*$~', $value) === 1) {
        return true;
    }
    if (preg_match('~^\d+$~', $value) === 1) {
        return true;
    }
    return false;
}

/**
 * Validate an Open Library identifier against CS1's rules: one or more
 * digits followed by A (authors), M (books), or W (works), without the OL
 * prefix.  Checker-only: do NOT gate add_if_new with this, since established
 * subtemplate extraction produces bare digits ({{ol|1234}}).
 */
function ol_valid(string $value): bool {
    $value = preg_replace('~^OL~i', '', $value);
    if ($value === null || preg_match('~^\d+[AMW]$~', $value) !== 1) {
        return false;
    }
    return true;
}

/**
 * Validate a Library of Congress Control Number against CS1's length table:
 * 8-12 characters whose rightmost eight are always digits, with the leading
 * characters constrained by total length (8: all digits; 9: one lowercase
 * letter; 10: two lowercase letters or two digits; 11: one lowercase letter
 * plus two lowercase letters or two digits; 12: two leading lowercase
 * letters).  Checker-only: do NOT gate add_if_new with this, since
 * established subtemplate extraction produces bare digits ({{lccn|1234}}).
 */
function lccn_valid(string $value): bool {
    $length = mb_strlen($value);
    if ($length < 8 || $length > 12) {
        return false;
    }
    if (preg_match('~^\d{8}$~', mb_substr($value, -8)) !== 1) {
        return false;
    }
    $head = mb_substr($value, 0, $length - 8);
    switch ($length) {
        case 8:
            return $head === '';
        case 9:
            return preg_match('~^[a-z]$~', $head) === 1;
        case 10:
            return preg_match('~^([a-z]{2}|\d{2})$~', $head) === 1;
        case 11:
            return preg_match('~^[a-z]([a-z]{2}|\d{2})$~', $head) === 1;
        default:
            return preg_match('~^[a-z]{2}[a-z0-9]{2}$~', $head) === 1;
    }
}

/**
 * The single definition of which DOI registrant codes can exist.  $registrant is the part
 * between "10." and the first slash, for example "1234", "12345" or "1234.56".
 *
 * Mirrors registrant_err_patterns in https://en.wikipedia.org/wiki/Module:Citation/CS1/Identifiers
 * and will need updating when that list changes:
 *   - fewer than three digits: never valid; exactly three digits: only with a subcode
 *   - four digits: 1000-9999 (5555 is the test registrant and never resolves)
 *   - five digits: 10000-89999 without a subcode, 10000-39999 with one
 *   - six or more digits, a leading zero, or anything but digits and dots: never valid
 *
 * Both is_doi_works() and doi_registrant_valid() call this; do not copy the rules elsewhere.
 */
function doi_registrant_code_valid(string $registrant): bool {
    if (preg_match('~^(\d+)((?:\.\d+)*)$~', $registrant, $match) !== 1) {
        return false;
    }
    $main = $match[1];
    $has_subcode = $match[2] !== '';
    if ($main === '5555' || $main[0] === '0') {
        return false;
    }
    switch (mb_strlen($main, '8bit')) {
        case 3:
            return $has_subcode;
        case 4:
            return true;
        case 5:
            return intval($main) <= ($has_subcode ? 39999 : 89999);
        default:
            return false; // one, two, or six and more digits
    }
}

/**
 * Validate the registrant code of a whole DOI.  Checker-only: do NOT gate add_if_new with this,
 * since reserved/test registrants such as 10.0001 must stay processable.
 * The ranges themselves are defined once, in doi_registrant_code_valid().
 */
function doi_registrant_valid(string $value): bool {
    if (preg_match('~^10\.(\d+(?:\.\d+)*)/\S+$~', $value, $match) !== 1) {
        return false;
    }
    return doi_registrant_code_valid($match[1]);
}

/**
 * True when a value matches CS1's "Cite uses generic name" triggers
 * (role labels, site names, and generic phrases from the Configuration
 * module's generic_names reject list).
 */
function is_generic_name(string $value): bool {
    $lower = mb_strtolower($value);
    foreach (GENERIC_NAMES as $phrase) {
        if (mb_strpos($lower, $phrase) !== false) {
            return true;
        }
    }
    foreach (GENERIC_NAME_PATTERNS as $pattern) {
        if (preg_match($pattern, $value) === 1) {
            return true;
        }
    }
    return false;
}

/**
 * True when a value matches CS1's "Cite uses generic title" triggers.
 * The plain-text phrases here are those not already covered by BAD_TITLES /
 * ZOTERO_BAD_TITLES; the patterns cover the bracket/404 forms those exact
 * lists cannot express.
 */
function is_generic_title(string $value): bool {
    $lower = mb_strtolower($value);
    foreach (GENERIC_TITLES as $phrase) {
        if (mb_strpos($lower, $phrase) !== false) {
            return true;
        }
    }
    foreach (GENERIC_TITLE_PATTERNS as $pattern) {
        if (preg_match($pattern, $value) === 1) {
            return true;
        }
    }
    return false;
}

/**
 * True when a value contains an unexpanded CMS placeholder token such as
 * Drupal's [node:title] or [site:name].  These appear verbatim in <title>
 * tags when the site's metatag configuration is broken, and Citoid/Zotero
 * faithfully returns them as the page title.  They must never become
 * |title=, so reject any bracketed token with a colon and no spaces
 * (legitimate bracketed titles such as "[Review] Foo" have no colon).
 * See https://en.mapofmemory.org/54-07 whose <title> is literally
 * "[node:field_nane] | [site:name]".
 */
function has_unexpanded_cms_token(string $value): bool {
    return preg_match('~\[[\w\-.]+(?::[\w\-.]+)+\]~u', $value) === 1;
}

/**
 * True when a value is a structurally valid URL for CS1 (error 40,
 * "Check |url= value"): no whitespace/control characters, a proper scheme
 * (RFC 3986 scheme syntax), and non-empty content after the scheme.
 */
function url_valid(string $value): bool {
    if (preg_match('~\s|[[:cntrl:]]~u', $value) === 1) {
        return false; // CS1 rejects URLs containing whitespace or control characters
    }
    if (preg_match('~^[a-zA-Z][a-zA-Z0-9+.\-]*:~', $value, $scheme_match) !== 1) {
        return false; // must begin with a scheme
    }
    $rest = mb_substr($value, mb_strlen($scheme_match[0]));
    if ($rest === '' || preg_match('~^/+$~', $rest) === 1) {
        return false; // scheme must be followed by an authority/path
    }
    return true;
}

/**
 * True when an archive URL is structurally usable as |archive-url=, mirroring
 * the CS1 "|archive-url= is malformed" reasons the bot can detect locally.
 * A save-command URL triggers a new snapshot instead of linking one, and a
 * wildcard URL is a search page rather than a snapshot.  Timestamp coupling
 * is handled separately by archive_url_has_timestamp().
 */
function archive_url_valid(string $value): bool {
    if (!url_valid($value)) {
        return false;
    }
    if (preg_match('~^https?://(?:www\.|web\.)?archive\.org/save/~i', $value) === 1) {
        return false; // save command triggers a snapshot instead of linking one
    }
    if (preg_match('~^https?://(?:www\.|web\.)?archive\.org/web/\*/~i', $value) === 1) {
        return false; // wildcard is a search page, not a snapshot
    }
    return true;
}

/**
 * True when an archive URL carries an extractable snapshot timestamp, mirroring
 * the timestamp extraction in Template::tidy_parameter('archive-url').  If no
 * timestamp can be extracted and |archive-date= is absent, CS1 would report
 * "|archive-url= requires |archive-date=".
 */
function archive_url_has_timestamp(string $value): bool {
    return preg_match('~^https?://(?:(?:www\.|web\.)?archive\.org/web/|archive\.today/|archive\.\S\S/|webarchive\.loc\.gov/all/|www\.webarchive\.org\.uk/wayback/archive/)\d{14}~', $value) === 1
        || preg_match('~^https?://wayback\.archive\-it\.org/\d{4}/\d{14}~', $value) === 1
        || preg_match('~^https?://(?:www\.|)webcitation\.org/[0-9a-zA-Z]{9}~', $value) === 1;
}

/**
 * True when a value is a supported |asin-tld= Amazon country/TLD code per
 * Module:Citation/CS1/Identifiers asin() (err_bad_asin_tld,
 * "Check |asin-tld= value").
 */
function asin_tld_valid(string $value): bool {
    return $value !== '' && in_array(mb_strtolower(mb_trim($value)), VALID_ASIN_TLDS, true);
}

function changeisbn10Toisbn13(string $isbn10, int $year): string {
    $isbn10 = mb_trim($isbn10); // Remove leading and trailing spaces
    // Written as escapes (U+2014 em dash, U+2015 horizontal bar, U+2013 en dash, U+2012 figure dash, U+2212 minus sign, U+2010 hyphen) so that an encoding round trip cannot corrupt them into '?'
    $dash_variants = ["\u{2014}", "\u{2015}", "\u{2013}", "\u{2012}", "\u{2011}", "\u{2212}", "\u{2010}"];
    $test = str_replace([...$dash_variants, '-', ' '], '', $isbn10);
    if (mb_strlen($test) < 10 || mb_strlen($test) > 13) {
        return $isbn10;
    }
    $isbn10 = str_replace('x', 'X', $isbn10);
    if (preg_match("~^[0-9Xx ]+$~", $isbn10) === 1) {
        // Uses spaces
        $isbn10 = str_replace(' ', '-', $isbn10);
    }
    $isbn10 = str_replace($dash_variants, '-', $isbn10); // Standardize dashes: em dash, horizontal bar, en dash, figure dash, minus sign, and Unicode hyphen to hyphen.
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
    if (!isbn_valid($isbn13)) {
        // A wrong check digit means a typo somewhere.  Converting would give it a correct ISBN-13
        // check digit and hide the error that CS1 reports, so leave it for a human.
        return $isbn10;
    }
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

function logable_doi(string $doi): string {
    return str_ireplace(['&lt;', '&gt;'], ['<', '>'], echoable($doi));
}

function clean_volume(string $volume): string {
    if (mb_strpos($volume, "(") !== false) {
        return '';
    }
    // A single letter is a legitimate volume ("A", "B"), but strtotime() reads it as a military time zone
    // The same goes for a letter followed by a number ("T5", "A-1"): a time-zone letter with an offset
    if (preg_match('~[a-zA-Z]~', $volume) && preg_match('~^\s*[a-zA-Z]\s*(?:-?\s*\d+\s*)?$~', $volume) !== 1 && (bool) strtotime($volume)) {
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
