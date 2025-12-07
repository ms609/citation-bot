<?php

declare(strict_types=1);


final class AdsAbsControl {
    private const MAX_CACHE_SIZE = 50000;
    private static int $big_counter = 0;
    private static int $small_counter = 0;
    /** @var array<string> $doi2bib */
    private static array $doi2bib = [];
    /** @var array<string> $bib2doi */
    private static array $bib2doi = [];

    public static function big_gave_up_yet(): bool {
        self::$big_counter = max(self::$big_counter - 1, 0);
        return self::$big_counter !== 0;
    }
    public static function big_give_up(): void {
        self::$big_counter = 1000;
    }
    public static function big_back_on(): void {
        self::$big_counter = 0;
    }

    public static function small_gave_up_yet(): bool {
        self::$small_counter = max(self::$small_counter - 1, 0);
        return self::$small_counter !== 0;
    }
    public static function small_give_up(): void {
        self::$small_counter = 1000;
    }
    public static function small_back_on(): void {
        self::$small_counter = 0;
    }

    public static function add_doi_map(string $bib, string $doi): void {
        self::check_memory_use();
        if ($bib === '' || $doi === '') {
            report_minor_error('Bad parameter in add_doi_map: ' . echoable($bib) . ' : ' . echoable($doi)); // @codeCoverageIgnore
            return; // @codeCoverageIgnore
        }
        if ($doi === 'X') {
            self::$bib2doi[$bib] = 'X';
        } elseif (doi_works($doi)) { // paranoid
            self::$bib2doi[$bib] = $doi;
            if (stripos($bib, 'tmp') === false && stripos($bib, 'arxiv') === false) {
                self::$doi2bib[$doi] = $bib;
            }
        }
    }
    public static function get_doi2bib(string $doi): string {
        return (string) @self::$doi2bib[$doi];
    }
    public static function get_bib2doi(string $bib): string {
        return (string) @self::$bib2doi[$bib];
    }

    public static function check_memory_use(): void {
        $usage = count(self::$doi2bib) + count(self::$bib2doi);
        if ($usage > self::MAX_CACHE_SIZE) {
            self::free_memory(); // @codeCoverageIgnore
        }
    }
    public static function free_memory(): void {
        self::$doi2bib = [];
        self::$bib2doi = [];
        gc_collect_cycles();
    }

}

/**
  @param array<string> $bibcodes
  @param array<Template> $templates
*/
function query_bibcode_api(array $bibcodes, array &$templates): void {  // Pointer to save memory
    adsabs_api($bibcodes, $templates, 'bibcode');
}


function is_a_book_bibcode(string $id): bool {
    $check = str_replace(['book', 'conf', 'PhD'], '', $id);
    return ($check !== $id);
}



function expand_by_adsabs(Template $template): void
 {
  static $needs_told = true;
  set_time_limit(120);
  if ($template->has('bibcode') && $template->blank('doi')) {
   $doi = AdsAbsControl::get_bib2doi($template->get('bibcode'));
   if (doi_works($doi)) {
    $template->add_if_new('doi', $doi);
   }
  }
  if ($template->has('doi') && ($template->blank('bibcode') || stripos($template->get('bibcode'), 'tmp') !== false || stripos($template->get('bibcode'), 'arxiv') !== false)) {
   $doi = $template->get('doi');
   if (doi_works($doi)) {
    $bib = AdsAbsControl::get_doi2bib($doi);
    if (strlen($bib) > 12) {
     $template->add_if_new('bibcode_nosearch', $bib);
    }
   }
  }

  // API docs at https://github.com/adsabs/adsabs-dev-api
  if (
   $template->has('bibcode') &&
   !$template->incomplete() &&
   stripos($template->get('bibcode'), 'tmp') === false &&
   stripos($template->get('bibcode'), 'arxiv') === false &&
   ($template->has('doi') || AdsAbsControl::get_bib2doi($template->get('bibcode')) === 'X')
  ) {
   // Don't waste a query, if it has a doi or will not find a doi
   return; // @codeCoverageIgnore
  }

  if (!SLOW_MODE && $template->blank('bibcode')) {
   return;
  } // Only look for new bibcodes in slow mode
  if (stripos($template->get('bibcode'), 'CITATION') !== false) {
   return;
  }
  // Do not search if it is a book - might find book review
  if (stripos($template->get('jstor'), 'document') !== false) {
   return;
  }
  if (stripos($template->get('jstor'), '.ch.') !== false) {
   return;
  }

  if (!$template->blank_other_than_comments('bibcode') && stripos($template->get('bibcode'), 'tmp') === false && stripos($template->get('bibcode'), 'arxiv') === false) {
   return;
  }

  if ($template->api_has_used('adsabs', equivalent_parameters('bibcode'))) {
   return;
  }

  if ($template->has('bibcode')) {
   $template->record_api_usage('adsabs', 'bibcode');
  }
  if (strpos($template->get('doi'), '10.1093/') === 0) {
   return;
  }
  report_action("Checking AdsAbs database");
  if ($template->has('doi') && preg_match(REGEXP_DOI, $template->get_without_comments_and_placeholders('doi'), $doi)) {
   $result = query_adsabs("identifier:" . urlencode('"' . $doi[0] . '"')); // In DOI we trust
  } elseif ($template->has('eprint')) {
   $result = query_adsabs("identifier:" . urlencode('"' . $template->get('eprint') . '"'));
  } elseif ($template->has('arxiv')) {
   $result = query_adsabs("identifier:" . urlencode('"' . $template->get('arxiv') . '"')); // @codeCoverageIgnore
  } else {
   $result = (object) ["numFound" => 0];
  }

  if ($result->numFound > 1) {
   report_warning("Multiple articles match identifiers "); // @codeCoverageIgnore
   return; // @codeCoverageIgnore
  }

  if ($result->numFound === 0) {
   // Avoid blowing through our quota
   if (
    !in_array($template->wikiname(), ['cite journal', 'citation', 'cite conference', 'cite book', 'cite arxiv'], true) || // Unlikely to find anything
    // If the book has someway to find it, or it is just a chapter and not the full book, or it has a location and publisher so it can be googled
    // This also greatly reduces the book review false positives
    (($template->wikiname() === 'cite book' || $template->wikiname() === 'citation') && ($template->has('isbn') || $template->has('oclc') || $template->has('chapter') || ($template->has('location') && $template->has('publisher')))) ||
    $template->has_good_free_copy() || // Alreadly links out to something free
    $template->has('s2cid') || // good enough, usually includes abstract and link to copy
    ($template->has('doi') && doi_works($template->get('doi'))) || // good enough, usually includes abstract
    $template->has('bibcode')
   ) {
    // Must be GIGO
    report_inline('no record retrieved.'); // @codeCoverageIgnore
    return; // @codeCoverageIgnore
   }
  }

  if ($result->numFound !== 1 && $template->has('title')) {
   // Do assume failure to find arXiv means that it is not there
   $have_more = false;
   if (strlen($template->get_without_comments_and_placeholders("title")) < 15 ||
       strpos($template->get_without_comments_and_placeholders("title"), ' ') === false) {
    return;
   }
   $the_query = "title:" . urlencode('"' . trim(remove_brackets(str_replace(['"', "\\", "^", "_", '   ', '  '], [' ', ' ', ' ', ' ', ' ', ' '], $template->get_without_comments_and_placeholders("title")))) . '"');
   $pages = $template->page_range();
   if ($pages) {
    $the_query = $the_query . "&fq=page:" . urlencode('"' . $pages[1] . '"');
    $have_more = true;
   }
   if ($template->year()) {
    $the_query = $the_query . "&fq=year:" . urlencode($template->year());
    $have_more = true;
   }
   if ($template->has('volume')) {
    $the_query = $the_query . "&fq=volume:" . urlencode('"' . $template->get('volume') . '"');
    $have_more = true;
   }
   if ($template->has('issn')) {
    $the_query = $the_query . "&fq=issn:" . urlencode($template->get('issn'));
    $have_more = true;
   }
   if (!$have_more) {
    return; // A title is not enough
   }
   $result = query_adsabs($the_query);
   if ($result->numFound === 0) {
    return;
   }
   $record = $result->docs[0];
   if (titles_are_dissimilar($template->get_without_comments_and_placeholders("title"), $record->title[0])) {
    // Considering we searched for title, this is very paranoid
    report_inline("Similar title not found in database."); // @codeCoverageIgnore
    return; // @codeCoverageIgnore
   }
   // If we have a match, but other links exists, and we have nothing journal like, then require exact title match
   if (
    !$template->blank(array_merge(['doi', 'pmc', 'pmid', 'eprint', 'arxiv'], ALL_URL_TYPES)) &&
    $template->blank(['issn', 'journal', 'volume', 'issue', 'number']) &&
    mb_strtolower($record->title[0]) !== mb_strtolower($template->get_without_comments_and_placeholders('title'))
   ) {
    // Probably not a journal, trust zotero more
    report_inline("Exact title match not found in database."); // @codeCoverageIgnore
    return; // @codeCoverageIgnore
   }
  }

  if ($result->numFound !== 1 && ($template->has('journal') || $template->has('issn'))) {
   $journal = $template->get('journal');
   // try partial search using bibcode components:
   $pages = $template->page_range();
   if (!$pages) {
    return;
   }
   if ($template->blank('volume') && !$template->year()) {
    return;
   }
   $result = query_adsabs(
    ($template->has('journal') ? "pub:" . urlencode('"' . remove_brackets($journal) . '"') : "&fq=issn:" . urlencode($template->get('issn'))) .
     ($template->year() ? "&fq=year:" . urlencode($template->year()) : '') .
     ($template->has('volume') ? "&fq=volume:" . urlencode('"' . $template->get('volume') . '"') : '') .
     ("&fq=page:" . urlencode('"' . $pages[1] . '"'))
   );
   if ($result->numFound === 0 || !isset($result->docs[0]->pub)) {
    report_inline('no record retrieved.'); // @codeCoverageIgnore
    return; // @codeCoverageIgnore
   }
   $journal_string = explode(",", (string) $result->docs[0]->pub);
   $journal_fuzzyer = "~\([iI]ncorporating.+|\bof\b|\bthe\b|\ba|eedings\b|\W~";
   if (strlen($journal_string[0]) && strpos(mb_strtolower(safe_preg_replace($journal_fuzzyer, "", $journal)), mb_strtolower(safe_preg_replace($journal_fuzzyer, "", $journal_string[0]))) === false) {
    report_inline(   // @codeCoverageIgnoreStart
     "Partial match but database journal \"" .
      echoable($journal_string[0]) .
      "\" didn't match \"" .
      echoable($journal) .
      "\"."
    );
    return; // @codeCoverageIgnoreEnd
   }
  }
  if ($result->numFound === 1) {
   $record = $result->docs[0];
   if (isset($record->year) && $template->year()) {
    $diff = abs((int) $record->year - (int) $template->year()); // Check for book reviews (fuzzy >2 for arxiv data)
    $today = (int) date("Y");
    if ($diff > 2) {
     return;
    }
    if ($record->year < $today - 5 && $diff > 1) {
     return;
    }
    if ($record->year < $today - 10 && $diff !== 0) {
     return;
    }
    if ($template->has('doi') && $diff !== 0) {
     return;
    }
   }

   if (!isset($record->title[0]) || !isset($record->bibcode)) {
    report_inline("Database entry not complete"); // @codeCoverageIgnore
    return; // @codeCoverageIgnore
   }
   if ($template->has('title') && titles_are_dissimilar($template->get('title'), $record->title[0]) && !in_array($template->get('title'), GOOFY_TITLES, true)) {
    // Verify the title matches. We get some strange mis-matches {
    report_inline("Similar title not found in database"); // @codeCoverageIgnore
    return; // @codeCoverageIgnore
   }

   if (isset($record->doi) && $template->get_without_comments_and_placeholders('doi')) {
    if (!str_i_same((string) $record->doi[0], $template->get_without_comments_and_placeholders('doi'))) {
     return;
    } // New DOI does not match
   }

   if (strpos((string) $record->bibcode, '.......') !== false) {
    return;  // Reject things like 2012PhDT.........1B
   }
   if (is_a_book_bibcode((string) $record->bibcode)) {
    $template->add_if_new('bibcode_nosearch', (string) $record->bibcode);
    expand_book_adsabs($template, $record);
    return;
   }

   if ($template->looksLikeBookReview($record)) {
    // Possible book and we found book review in journal
    report_info("Suspect that BibCode " . bibcode_link((string) $record->bibcode) . " is book review. Rejecting.");
    return;
   }

   if ($template->blank('bibcode')) {
    $template->add_if_new('bibcode_nosearch', (string) $record->bibcode);
   }
   process_bibcode_data($template, $record);
   return;
  } elseif ($result->numFound === 0) {
   // @codeCoverageIgnoreStart
   report_inline('no record retrieved.');
   return;
  } else {
   report_inline('multiple records retrieved.  Ignoring.');
   return; // @codeCoverageIgnoreEnd
  }
 }
 
 
 function get_open_access_url(Template $template): void
 {
  if (!$template->blank(DOI_BROKEN_ALIASES)) {
   return;
  }
  $doi = $template->get_without_comments_and_placeholders('doi');
  if (!$doi) {
   return;
  }
  if (strpos($doi, '10.1093/') === 0) {
   return;
  }
  $return = get_unpaywall_url($template, $doi);
  if (in_array($return, GOOD_FREE, true)) {
   return;
  } // Do continue on
  get_semanticscholar_url($template, $doi);
 }




