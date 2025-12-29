<?php

declare(strict_types=1);

require_once __DIR__ . '/constants.php';     // @codeCoverageIgnore

/**
 * Convert MathML elements to LaTeX syntax
 * Handles complex MathML structures like mmultiscripts, msup, msub, mfrac, mroot, munder, munderover, etc.
 *
 * NOTE: This conversion only applies when adding NEW parameter values to citation templates
 * via add_if_new(). It does NOT modify existing parameters that already contain MathML.
 * Existing citations with MathML will retain their current values during tidying operations.
 */
function convert_mathml_to_latex(string $mathml): string {
    // Remove mml: namespace prefix if present
    $mathml = str_replace(['<mml:', '</mml:'], ['<', '</'], $mathml);

    // Handle mmultiscripts for isotope notation: <mmultiscripts>base<mprescripts/>prescript</mmultiscripts>
    // Example: <mmultiscripts>Ni<mprescripts/><none/>67</mmultiscripts> -> ^{67}\mathrm{Ni}
    $mathml = preg_replace_callback(
        '~<mmultiscripts>(.*?)<mprescripts/>(.*?)</mmultiscripts>~s',
        static function (array $matches): string {
            $base = mb_trim($matches[1]);
            $prescripts = mb_trim($matches[2]);

            // Handle <none/> tags - they represent empty positions
            $parts = preg_split('~<none/>~', $prescripts);

            // For isotope notation: <none/>number means superscript on left (mass number)
            if (count($parts) === 2 && mb_trim($parts[0]) === '') {
                $superscript = mb_trim(strip_tags($parts[1]));
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
            $base = mb_trim($matches[1]);
            $super = mb_trim($matches[2]);
            return $base . "^{" . $super . "}";
        },
        $mathml
    );

    // Handle msub (subscript): <msub><mi>H</mi><mn>2</mn></msub> -> H_{2}
    $mathml = preg_replace_callback(
        '~<msub>\s*<mi>(.*?)</mi>\s*<mn>(.*?)</mn>\s*</msub>~s',
        static function (array $matches): string {
            $base = mb_trim($matches[1]);
            $sub = mb_trim($matches[2]);
            return $base . "_{" . $sub . "}";
        },
        $mathml
    );

    // Handle msubsup (subscript and superscript): <msubsup><mi>x</mi><mn>1</mn><mn>2</mn></msubsup> -> x_{1}^{2}
    $mathml = preg_replace_callback(
        '~<msubsup>\s*<mi>(.*?)</mi>\s*<mn>(.*?)</mn>\s*<mn>(.*?)</mn>\s*</msubsup>~s',
        static function (array $matches): string {
            $base = mb_trim($matches[1]);
            $sub = mb_trim($matches[2]);
            $super = mb_trim($matches[3]);
            return $base . "_{" . $sub . "}^{" . $super . "}";
        },
        $mathml
    );

    // Handle mfrac (fractions): <mfrac><mn>1</mn><mn>2</mn></mfrac> -> \frac{1}{2}
    $mathml = preg_replace_callback(
        '~<mfrac>\s*<m[ino]>(.*?)</m[ino]>\s*<m[ino]>(.*?)</m[ino]>\s*</mfrac>~s',
        static function (array $matches): string {
            $num = mb_trim($matches[1]);
            $den = mb_trim($matches[2]);
            return "\\frac{" . $num . "}{" . $den . "}";
        },
        $mathml
    );

    // Handle mroot (nth root): <mroot><mi>x</mi><mn>3</mn></mroot> -> \sqrt[3]{x}
    $mathml = preg_replace_callback(
        '~<mroot>\s*<m[ino]>(.*?)</m[ino]>\s*<m[ino]>(.*?)</m[ino]>\s*</mroot>~s',
        static function (array $matches): string {
            $base = mb_trim($matches[1]);
            $index = mb_trim($matches[2]);
            return "\\sqrt[" . $index . "]{" . $base . "}";
        },
        $mathml
    );

    // Handle munder (underscript): <munder><mo>lim</mo><mrow>x→0</mrow></munder> -> \underset{x→0}{\lim}
    $mathml = preg_replace_callback(
        '~<munder>(.*?)</munder>~s',
        static function (array $matches): string {
            $content = $matches[1];
            // Try to extract base and underscript
            if (preg_match('~^(.*?)<m[inor]>(.*?)</m[inor]>(.*)$~s', $content, $parts)) {
                $base = mb_trim(strip_tags($parts[1] . $parts[2]));
                $under = mb_trim(strip_tags($parts[3]));
                if ($under !== '') {
                    return "\\underset{" . $under . "}{" . $base . "}";
                }
                return $base;
            }
            return strip_tags($content);
        },
        $mathml
    );

    // Handle munderover (underscript and overscript): <munderover><mo>∑</mo><mn>0</mn><mi>n</mi></munderover> -> \sum_{0}^{n}
    $mathml = preg_replace_callback(
        '~<munderover>(.*?)</munderover>~s',
        static function (array $matches): string {
            $content = $matches[1];
            // Try to extract base, under, and over - for simple cases with three m[ino] elements
            if (preg_match_all('~<m[ino]>(.*?)</m[ino]>~s', $content, $parts)) {
                if (count($parts[1]) === 3) {
                    $base = mb_trim($parts[1][0]);
                    $under = mb_trim($parts[1][1]);
                    $over = mb_trim($parts[1][2]);
                    // For sum/integral/product symbols, use subscript/superscript notation
                    return $base . "_{" . $under . "}^{" . $over . "}";
                }
            }
            return strip_tags($content);
        },
        $mathml
    );

    // Apply simple tag replacements from MML_TAGS constant
    $mathml = str_replace(array_keys(MML_TAGS), array_values(MML_TAGS), $mathml);

    // Clean up any remaining MathML tags (including <mrow> which is just a grouping element)
    $mathml = strip_tags($mathml);

    // Apply Unicode-to-LaTeX replacements for raw Unicode math symbols
    $mathml = str_replace(array_keys(UNICODE_MATH_MAP), array_values(UNICODE_MATH_MAP), $mathml);

    return $mathml;
}
