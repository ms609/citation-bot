<?php

declare(strict_types=1);

// @codeCoverageIgnoreStart
require_once __DIR__ . '/WikiThings.php';
// @codeCoverageIgnoreEnd

final class WikitextTooComplexException extends RuntimeException {
}

/** Bound the work of expanding wikitext submitted directly to the gadget. */
final class WikitextWorkBudget {
    private const int MAX_DEPTH = 48;
    private const int MAX_TEMPLATES = 1200;
    private const int MAX_TEMPLATE_FIELDS = 256;
    private const int MAX_TEMPLATE_BYTES = 64 * 1024;
    private const int MAX_FIELDS = 20000;
    private const int MAX_PROTECTED_REGIONS = 2000;
    private const int MAX_TEXT_BYTES = 1024 * 1024;
    private const int MAX_SCAN_BYTES = 256 * 1024 * 1024;

    private int $scan_bytes = 0;
    private int $templates = 0;
    private int $fields = 0;

    public function preflight(string $text): void {
        $this->templates = 0;
        $this->fields = 0;
        $this->scan_bytes = 0;
        $regions = 0;
        foreach ([Comment::class, Nowiki::class, Chemistry::class, Mathematics::class,
                  Musicscores::class, Preformated::class, SyntaxHighlight::class, SourceCode::class] as $class) {
            $this->scan($text);
            $masked = preg_replace($class::REGEXP, ' ', $text, -1, $matches);
            $regions += $matches;
            if ($masked === null || $regions > self::MAX_PROTECTED_REGIONS) {
                throw new WikitextTooComplexException();
            }
            $text = $masked;
        }

        // A conservative token count is enough for preflight. Extraction also
        // checks the objects and fields it actually creates after conversion.
        $depth = 0;
        $templates = 0;
        $fields = 0;
        $position = 0;
        while (true) {
            $matched = preg_match('~\{++|\}++|\|~', $text, $match, PREG_OFFSET_CAPTURE, $position);
            if ($matched !== 1) {
                break;
            }
            [$token, $offset] = $match[0];
            $length = mb_strlen($token, '8bit');
            $position = $offset + $length;
            if ($token[0] === '{') {
                $opened = intdiv($length, 2);
                $depth += $opened;
                $templates += $opened;
            } elseif ($token[0] === '}') {
                $depth = max(0, $depth - intdiv($length, 2));
            } elseif ($depth > 0) {
                ++$fields;
            }
            if ($depth > self::MAX_DEPTH || $templates > self::MAX_TEMPLATES || $fields > self::MAX_FIELDS) {
                throw new WikitextTooComplexException();
            }
        }
        if ($matched === false) {
            throw new WikitextTooComplexException();
        }
    }

    public function checkDepth(int $depth): void {
        if ($depth > self::MAX_DEPTH) {
            throw new WikitextTooComplexException();
        }
    }

    public function scan(string $text): void {
        $bytes = mb_strlen($text, '8bit');
        $this->scan_bytes += $bytes;
        if ($bytes > self::MAX_TEXT_BYTES || $this->scan_bytes > self::MAX_SCAN_BYTES) {
            throw new WikitextTooComplexException();
        }
    }

    /**
     * Limit the page as each converted reference is appended to the regex result.
     * @param string $text
     * @param callable(array<string>):string $replace
     * @return Closure(array<string>):string
     */
    public function boundConversions(string $text, callable $replace): Closure {
        $this->scan($text);
        $bytes = mb_strlen($text, '8bit');
        return static function (array $matches) use ($replace, &$bytes): string {
            $replacement = $replace($matches);
            $bytes += mb_strlen($replacement, '8bit') - mb_strlen($matches[0], '8bit');
            if ($bytes > self::MAX_TEXT_BYTES) {
                throw new WikitextTooComplexException();
            }
            return $replacement;
        };
    }

    public function recordTemplate(string $text): void {
        $fields = mb_substr_count($text, '|', '8bit');
        ++$this->templates;
        $this->fields += $fields;
        if (
            $this->templates > self::MAX_TEMPLATES ||
            mb_strlen($text, '8bit') > self::MAX_TEMPLATE_BYTES ||
            $fields > self::MAX_TEMPLATE_FIELDS ||
            $this->fields > self::MAX_FIELDS
        ) {
            throw new WikitextTooComplexException();
        }
    }

    /**
     * Check the size before assembling the replacement. Placeholders are ASCII,
     * and restoration matches them without regard to ASCII letter case.
     */
    public function checkReplacement(string $text, string $needle, string $replacement, bool $case_insensitive = false): void {
        $this->scan($text);
        $growth = mb_strlen($replacement, '8bit') - mb_strlen($needle, '8bit');
        if ($growth <= 0) {
            return;
        }

        if ($case_insensitive) {
            $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $lowercase = 'abcdefghijklmnopqrstuvwxyz';
            $text = strtr($text, $uppercase, $lowercase);
            $needle = strtr($needle, $uppercase, $lowercase);
        }
        $count = mb_substr_count($text, $needle, '8bit');
        if (mb_strlen($text, '8bit') + $count * $growth > self::MAX_TEXT_BYTES) {
            throw new WikitextTooComplexException();
        }
    }
}
