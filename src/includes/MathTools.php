<?php

declare(strict_types=1);

require_once __DIR__ . '/constants.php';     // @codeCoverageIgnore

/**
 * Parse a complete base-10 integer string without relying on PHP's fuzzy
 * string-to-int cast behavior.
 */
function parse_decimal_integer(string $value): ?int {
    $value = mb_trim($value);
    if (preg_match('~\A([+-]?)(\d+)\z~D', $value, $matches) !== 1) {
        return null;
    }

    // FILTER_VALIDATE_INT rejects otherwise valid decimal strings with
    // leading zeroes, so normalize those before using it for the range check.
    $digits = mb_ltrim($matches[2], '0');
    if ($digits === '') {
        return 0;
    }
    $normalized = ($matches[1] === '-' ? '-' : ($matches[1] === '+' ? '+' : '')) . $digits;
    $parsed = filter_var($normalized, FILTER_VALIDATE_INT);

    return is_int($parsed) ? $parsed : null;
}

/**
 * Explicitly parse a leading base-10 integer from a string.
 *
 * This is for inputs where Citation Bot historically and intentionally used
 * the numeric prefix (for example "12A").  Keeping that parsing explicit
 * avoids relying on fuzzy casts while preserving the established behavior.
 */
function parse_decimal_integer_prefix(string $value): ?int {
    if (preg_match('~\A[ \t\r\n\f\v]*([+-]?\d+)~D', $value, $matches) !== 1) {
        return null;
    }

    return parse_decimal_integer($matches[1]);
}

function mathml_safe_preg_replace_callback(string $regex, callable $replace, string $old): string {
    $new = @preg_replace_callback($regex, $replace, $old);
    return $new ?? $old;
}

/**
 * Convert MathML elements to LaTeX syntax
 * Handles complex MathML structures like mmultiscripts, msup, msub, mfrac, mroot, munder, munderover, etc.
 *
 * NOTE: This conversion only applies when adding NEW parameter values to citation templates
 * via add_if_new(). It does NOT modify existing parameters that already contain MathML.
 * Existing citations with MathML will retain their current values during tidying operations.
 */
function convert_mathml_to_latex(string $mathml): ?string {
    $mathml = mathml_safe_preg_replace_callback(
        '~&#(?:x[0-9a-f]+|[0-9]+);~i',
        static function (array $match): string {
            $decoded = html_entity_decode($match[0], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            // Decode mathematical symbols, while preserving escaped markup.
            return in_array($decoded, ['<', '>', '&', '"', "'"], true) ? $match[0] : $decoded;
        },
        $mathml
    );
    // Remove mml: namespace prefix if present
    $mathml = str_replace(['<mml:', '</mml:'], ['<', '</'], $mathml);

    if (preg_match('~</?m(?:frac|root|subsup|sub|sup|table|tr|td|over|enclose|phantom|action|error|stack|longdiv|padded)\b~', $mathml) === 1) {
        // Wrap the fragment and keep named math entities for the symbol pass below.
        $document = new DOMDocument();
        if (!$document->loadXML('<math>' . str_replace('&', '&amp;', $mathml) . '</math>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)
            || $document->documentElement === null) {
            return null;
        }
        $rendered = render_mathml_element($document->documentElement);
        return $rendered === null ? null : finish_mathml_conversion($rendered);
    }

    // Handle mmultiscripts for isotope notation: <mmultiscripts>base<mprescripts/>prescript</mmultiscripts>
    // Example: <mmultiscripts>Ni<mprescripts/><none/>67</mmultiscripts> -> ^{67}\mathrm{Ni}
    $mathml = mathml_safe_preg_replace_callback(
        '~<mmultiscripts>(.*?)<mprescripts/>(.*?)</mmultiscripts>~s',
        static function (array $matches): string {
            $base = mb_trim($matches[1]);
            $prescripts = mb_trim($matches[2]);

            // Handle <none/> tags - they represent empty positions
            $parts = preg_split('~<none/>~', $prescripts);
            if ($parts === false) {
                $parts = []; // preg_split only fails on regex compile error; fall back to no split
            }

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

    // Handle munder (underscript): <munder><mo>lim</mo><mrow>x→0</mrow></munder> -> \underset{x→0}{\lim}
    $mathml = mathml_safe_preg_replace_callback(
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
    $mathml = mathml_safe_preg_replace_callback(
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

    return finish_mathml_conversion($mathml);
}

/** Render grouped presentation MathML before the shared entity and symbol pass. */
function render_mathml_element(DOMElement $element): ?string {
    $tag = $element->tagName;
    if ($element->hasAttributes() && !($tag === 'mo' && $element->attributes->length === 1 && $element->getAttribute('stretchy') === 'false')) {
        return null;
    }

    $operands = [];
    $child_tags = [];
    $sequence = '';
    $has_text = false;
    foreach ($element->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $operand = render_mathml_element($child);
            if ($operand === null) {
                return null;
            }
            $operands[] = $operand;
            $child_tags[] = $child->tagName;
            $sequence .= $operand;
        } elseif ($child instanceof DOMText) {
            $sequence .= $child->data;
            $has_text = $has_text || mb_trim($child->data) !== '';
        }
    }

    $sequence = mb_trim($sequence);
    switch ($tag) {
        case 'math':
        case 'mrow':
            return $sequence;
        case 'mi':
        case 'mn':
        case 'mo':
            return $operands === [] ? $sequence : null;
        case 'mtext':
        case 'msqrt':
        case 'mfenced':
            if ($tag === 'mfenced' && count($operands) > 1) {
                return null; // Implicit separators require their own rendering.
            }
            return MML_TAGS['<' . $tag . '>'] . $sequence . MML_TAGS['</' . $tag . '>'];
        case 'mspace':
            return $sequence === '' ? MML_TAGS['<mspace/>'] : null;
    }

    $arity = match ($tag) {
        'mfrac', 'mroot', 'msub', 'msup', 'munder' => 2,
        'msubsup', 'munderover' => 3,
        default => null,
    };
    if ($has_text || count($operands) !== $arity || in_array('', $operands, true)) {
        return null;
    }

    [$base, $script] = $operands;
    if (in_array($tag, ['msub', 'msup', 'msubsup', 'munderover'], true)
        && !in_array($child_tags[0], ['mi', 'mn', 'mo'], true)
        && preg_match('~\A\([^()]+\)\z~', $base) !== 1) {
        $base = '{' . $base . '}';
    }
    return match ($tag) {
        'mfrac' => '\frac{' . $base . '}{' . $script . '}',
        'mroot' => '\sqrt[' . (str_contains($script, ']') ? '{' . $script . '}' : $script) . ']{' . $base . '}',
        'msub' => $base . '_{' . $script . '}',
        'msup' => $base . '^{' . $script . '}',
        'msubsup', 'munderover' => $base . '_{' . $script . '}^{' . $operands[2] . '}',
        'munder' => '\underset{' . $script . '}{' . $base . '}',
        default => null,
    };
}

function finish_mathml_conversion(string $mathml): string {
    // Keep comparison operators away from strip_tags(): once decoded, "p<0.05" looks like the
    // start of a tag and everything after it would be removed.  Private-use placeholders are restored below.
    $mathml = str_replace(['&lt;', '&gt;'], ["\u{E000}", "\u{E001}"], $mathml);

    // Replace MathML tags before stripping them. Process entities and Unicode symbols
    // together afterward so that converted or removed symbols cannot hide a command boundary.
    $tag_replacements = [];
    $entity_replacements = [];
    foreach (MML_TAGS as $symbol => $replacement) {
        if ($symbol[0] === '<') {
            $tag_replacements[$symbol] = $replacement;
        } else {
            $entity_replacements[$symbol] = $replacement;
        }
    }
    $mathml = str_replace(array_keys($tag_replacements), array_values($tag_replacements), $mathml);

    // Clean up any remaining MathML tags (including <mrow>).
    $mathml = strip_tags($mathml);

    // A single expansion pass is essential: e.g. &mu;&omicron; must be \mu{}o,
    // and &mu;&InvisibleTimes;m must be \mu{}m, not \muo or \mum.
    // Only convert symbols inside MathML (never globally in citation text).
    $mathml = mathml_replace_symbols($entity_replacements + UNICODE_MATH_MAP, $mathml);
    return str_replace(["\u{E000}", "\u{E001}"], ['<', '>'], $mathml);
}

/**
 * Expand entity/Unicode symbols in a single pass while protecting generated TeX control words.
 * A generated command ending in ASCII letters needs {} before the next emitted ASCII letter.
 * Track the emitted pieces, not the input lookahead: the next symbol might become ASCII or
 * disappear altogether (e.g. \mu + Greek omicron, or \mu + &InvisibleTimes; + m).
 *
 * @param array<string, string> $map
 */
function mathml_replace_symbols(array $map, string $text): string {
    if ($map === [] || $text === '') {
        return $text;
    }
    $keys = array_map('strval', array_keys($map));
    usort($keys, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a)); // Longest first
    $alternatives = [];
    foreach ($keys as $key) {
        $alternatives[] = preg_quote($key, '~');
    }
    // Captured delimiters alternate with untouched input fragments. This lets us decide
    // boundaries from the FINAL emitted fragments rather than from the original characters.
    $regex = '~(' . implode('|', $alternatives) . ')~u';
    $parts = @preg_split($regex, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $text; // Preserve input on invalid UTF-8 or a PCRE failure.
    }

    $output = '';
    $pending_command = false;
    foreach ($parts as $index => $part) {
        $is_symbol = ($index % 2) === 1;
        $fragment = $is_symbol ? $map[$part] : $part;
        if ($fragment === '') {
            continue; // Do not lose a pending command across empty replacements.
        }
        if ($pending_command && preg_match('~^[A-Za-z]~', $fragment) === 1) {
            $output .= '{}';
        }
        $output .= $fragment;
        $pending_command = $is_symbol && preg_match('~\\\\[A-Za-z]+$~', $fragment) === 1;
    }
    return $output;
}
