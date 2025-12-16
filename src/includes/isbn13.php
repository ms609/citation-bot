<?php

declare(strict_types=1);

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
    quietly('report_modification', "Converted ISBN10 to ISBN13");
    return $isbn13;
}
    
