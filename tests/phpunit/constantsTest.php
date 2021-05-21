<?php
declare(strict_types=1);

/*
 * Tests for constants.php.
 */

require_once(__DIR__ . '/../testBaseClass.php');

final class constantsTest extends testBaseClass {

  protected function setUp(): void {
   if (BAD_PAGE_HTTP !== '' || BAD_PAGE_API !== '') {
     $this->markTestSkipped();
   }
  }

  public function testConstantsDefined() : void {
    $this->assertSame(count(UCFIRST_JOURNAL_ACRONYMS), count(JOURNAL_ACRONYMS));
    for ($i = 0; $i < sizeof(JOURNAL_ACRONYMS); $i++) {
      $this->assertSame(trim(JOURNAL_ACRONYMS[$i]), trim(title_capitalization(ucwords(trim(UCFIRST_JOURNAL_ACRONYMS[$i])), TRUE)));
      // Verify that they are padded with a space
      $this->assertSame   (' ', mb_substr(UCFIRST_JOURNAL_ACRONYMS[$i], -1, 1));
      $this->assertSame   (' ', mb_substr(UCFIRST_JOURNAL_ACRONYMS[$i],  0, 1));
      $this->assertNotEquals(' ', mb_substr(UCFIRST_JOURNAL_ACRONYMS[$i], -2, 1));
      $this->assertNotEquals(' ', mb_substr(UCFIRST_JOURNAL_ACRONYMS[$i],  1, 1));
      $this->assertSame   (' ', mb_substr(JOURNAL_ACRONYMS[$i], -1, 1));
      $this->assertSame   (' ', mb_substr(JOURNAL_ACRONYMS[$i],  0, 1));
      $this->assertNotEquals(' ', mb_substr(JOURNAL_ACRONYMS[$i], -2, 1));
      $this->assertNotEquals(' ', mb_substr(JOURNAL_ACRONYMS[$i],  1, 1));
    }
    $this->assertSame(count(LC_SMALL_WORDS), count(UC_SMALL_WORDS));
    for ($i = 0; $i < sizeof(LC_SMALL_WORDS); $i++) {
      // Verify that they match
      if (substr_count(UC_SMALL_WORDS[$i], ' ') === 2 && substr_count(UC_SMALL_WORDS[$i], '&') === 0) {
        $this->assertSame(UC_SMALL_WORDS[$i], mb_convert_case(LC_SMALL_WORDS[$i], MB_CASE_TITLE, "UTF-8"));
      } else {  // Weaker test for things with internal spaces or an & symbol (PHP 7.3 and 5.6 treat & differently)
        $this->assertSame(strtolower(UC_SMALL_WORDS[$i]), strtolower(LC_SMALL_WORDS[$i]));
      }
      // Verify that they are padded with a space
      $this->assertSame   (' ', mb_substr(UC_SMALL_WORDS[$i], -1, 1));
      $this->assertSame   (' ', mb_substr(UC_SMALL_WORDS[$i],  0, 1));
      $this->assertNotEquals(' ', mb_substr(UC_SMALL_WORDS[$i], -2, 1));
      $this->assertNotEquals(' ', mb_substr(UC_SMALL_WORDS[$i],  1, 1)); 
    }
    // Trailing dots and lots of dots....
    $text = "{{Cite journal|journal=Journal of the A.I.E.E.}}";
    $expanded = $this->process_citation($text);
    $this->assertSame($text, $expanded->parsed_text());
  }
  
  public function testImplicitConstants() : void {
    // Consonants
    $this->assertSame('X', title_capitalization('x', TRUE));
    $this->assertSame('Xz', title_capitalization('xz', TRUE));
    $this->assertSame('XZZ BBBB/EEE', title_capitalization('xzz bbbb/eee', TRUE));
    $this->assertSame('XZZZ', title_capitalization('xzzz', TRUE));
    // Mixed
    $this->assertSame('Xzza', title_capitalization('xzza', TRUE));
    // Vowels
    $this->assertSame('AEIOU', title_capitalization('aeiou', TRUE));
    // Y is neither
    $this->assertSame('Aeiouy', title_capitalization('aeiouy', TRUE));
    $this->assertSame('Xzzzy', title_capitalization('xzzzy', TRUE));
    // Relationship Status = It's Complicated :-)
    $this->assertSame('Xzzzy Aeiouy AEIOU and xzzzy Aeiouy AEIOU', title_capitalization('xzzzy Aeiouy aeiou and xzzzy Aeiouy aeiou', TRUE));
    $this->assertSame('Xzzzy Aeiouy AEIOU and Xzzzy Aeiouy AEIOU', title_capitalization(ucwords('xzzzy Aeiouy aeiou and xzzzy Aeiouy aeiou'), TRUE));
  }
  
  public function testConstantsOrder() : void {
    $acronyms = JOURNAL_ACRONYMS; sort($acronyms, SORT_STRING | SORT_FLAG_CASE);
    $expected = current($acronyms);
    foreach (JOURNAL_ACRONYMS as $actual) {
      $this->assertSame(strtolower($expected), strtolower($actual));
      $expected = next($acronyms);
    }
  }
  
  public function testAllLowerCase() : void {
    $big_array = array_merge(HAS_NO_VOLUME, BAD_ACCEPTED_MANUSCRIPT_TITLES, BAD_AUTHORS,
                             PUBLISHER_ENDINGS, BAD_TITLES, IN_PRESS_ALIASES, NON_PUBLISHERS,
                             JOURNAL_IS_BOOK_SERIES);
    foreach ($big_array as $actual) {
      $this->assertSame(strtolower($actual), $actual);
    }
  }
  
  public function testAtoZ() : void {
    $leader = TRUE;
    $start_alpha = '/* The following will be automatically updated to alphabetical order */';
    $end_alpha = '/* The above will be automatically updated to alphabetical order */';
    $filename = __DIR__ . '/../../constants/capitalization.php';
    $old_contents = file_get_contents($filename);
    $sections = explode($start_alpha, $old_contents);
    foreach ($sections as &$section) {
      $alpha_end = stripos($section, $end_alpha);
      if (!$alpha_end) continue;
      $alpha_bit = substr($section, 0, $alpha_end);
      $alpha_bits = preg_split('~(?<="),~', $alpha_bit);
      $alpha_bits = array_map('trim', $alpha_bits);
      if ($leader) {
        $leader_bits = $alpha_bits;
        sort($alpha_bits, SORT_STRING | SORT_FLAG_CASE);
        $leader = FALSE;
      } else {
        $this->assertSame(count($leader_bits), count($alpha_bits));
        array_multisort($leader_bits, SORT_STRING | SORT_FLAG_CASE, $alpha_bits);
        $leader_bits = NULL;
        $leader = TRUE;
      }
      $bits_length = array_map('strlen', $alpha_bits);
      $bit_length = current($bits_length);
      $chunk_length = 0;
      $new_line = "\n          ";
      $alphaed = $new_line;
      $line_length = 10;
      array_unshift($alpha_bits, ''); // We use next below, need a fake bit at the start
      foreach ($bits_length as $bit_length) {
       $bit = next($alpha_bits);
       $alphaed .= $bit ? ($bit . ", ") : '';
       $line_length += $bit_length + 2;
       if ($line_length > 86) {
         $alphaed .= $new_line;
         $line_length = 10;
        }
      }
      if ($alphaed == $new_line) $alphaed = '';
      $section = $alphaed . substr($section, $alpha_end);
    }
    
    $new_contents = implode($start_alpha, $sections);
    
    if (preg_replace('/\s+/','', $new_contents) == preg_replace('/\s+/','', $old_contents)) {
      $this->assertTrue(TRUE);
    } else {
      ob_flush();
      echo "\n\n" . $filename . " needs alphabetized as follows\n";
      echo $new_contents . "\n\n\n";
      ob_flush();
      $this->assertTrue(FALSE);
    }
  }
  
 public function testWhiteList() : void {
      $we_failed = FALSE;
      $our_original_whitelist = PARAMETER_LIST;
      $our_whitelist = array_unique($our_original_whitelist);
      $our_whitelist_sorted = $our_whitelist;
      sort($our_whitelist_sorted);

      $context = stream_context_create(array(
        'http' => array('ignore_errors' => true),
      ));
      $wikipedia_response = @file_get_contents(WIKI_ROOT . '?title=Module:Citation/CS1/Whitelist&action=raw', FALSE, $context);
      preg_match_all("~\s\[\'([a-zA-Z0-9\#\-\_ ]+?)\'\] = ~" , $wikipedia_response, $matches);
      $their_whitelist = $matches[1];
      $patent_whitelist = array('inventor', 'inventor#', 'inventor-surname', 'inventor#-surname', 'inventor-last',
                                'inventor#-last', 'inventor-given', 'inventor#-given', 'inventor-first', 'inventor#-first',
                                'inventor-first#', 'inventor-link', 'inventor#-link', 'inventor-link#', 'inventor#link',
                                'country-code', 'publication-number', 'patent-number', 'country', 'number', 'description',
                                'status', 'invent#', 'gdate', 'pubdate', 'publication-number', 'pridate', 'assign#',
                                'assignee', 'assign', 'inventor-surname#', 'inventor-last#', 'inventor-given#',
                                'inventorlink', 'inventorlink#', 'issue-date', 'fdate'); // Some are not valid, but people use them anyway
      $their_whitelist = array_merge(array('CITATION_BOT_PLACEHOLDER_BARE_URL', 'citation_bot_placeholder_bare_url'),
                                     $patent_whitelist, $their_whitelist);
      $their_whitelist = array_unique($their_whitelist); // They might list the same thing twice
      $their_whitelist = array_diff($their_whitelist, ["template doc demo"]);

      $our_extra = array_diff($our_whitelist, $their_whitelist);
      $our_missing = array_diff($their_whitelist, $our_whitelist);
      $our_internal_extra = array_diff($our_original_whitelist, $our_whitelist);
 
      if (count($our_internal_extra) !== 0) {
         echo "\n \n testWhiteList:  What the Citation Bot has more than one copy of\n";
         print_r($our_internal_extra);
         $we_failed = TRUE;
      }
      if (count($our_extra) !== 0) {
         echo "\n \n testWhiteList:  What the Citation Bot has that Wikipedia does not\n";
         print_r($our_extra);
         $we_failed = TRUE;
      }
      if (count($our_missing) !== 0) {
         echo "\n \n testWhiteList:  What Wikipedia has that the Citation Bot does not\n";
         print_r($our_missing);
         $we_failed = TRUE;
      }
      if ($our_whitelist !== $our_whitelist_sorted) {
         echo "\n \n testWhiteList:  Citation Bot has values out of order.  Expected order:\n";
         foreach($our_whitelist_sorted as $value) {
           echo "'" . $value . "', ";
         }
         $we_failed = TRUE;
      }
      $this->assertSame(FALSE, $we_failed);
  }

  public function testWhiteListNotBlacklisted() : void {
    $whitelist = array_merge(DEAD_PARAMETERS, PARAMETER_LIST);
    $orig = '';
    $new = '';
    foreach($whitelist as $value) {
      $value = str_replace('#', '1', $value);
      if (stripos($value, '_bot')) $value = 'title'; // basically skip it
      $text = '{{citation | ' . $value . ' = Z123Z }}';
      $prepared = $this->prepare_citation($text); // Use prepare to avoid being "smart"
      $text = str_replace(['authors1', 'editors1', 'publication-date', 'publicationdate',  'publication-place', 'publicationplace', 'chapter-url ', 'chapterurl ', '| p = Z123Z ',    '| pp = Z123Z ',    '| URL = Z123Z ', '| bioRxiv = Z123Z ', '| ARXIV = Z123Z ', '| DOI = Z123Z '],  // Put spaces on end to not change chapter-url-access and such
                          ['author1',  'editor1',  'publication-date', 'publication-date', 'publication-place', 'publication-place', 'url ',        'url '       , '| page = Z123Z ', '| pages = Z123Z ', '| url = Z123Z ', '| biorxiv = Z123Z ', '| arxiv = Z123Z ', '| doi = Z123Z '], $text); // Stuff that get "fixed"
      $text = str_replace(['| access-date = Z123Z ', '| accessdate = Z123Z ', '| doi-broken = Z123Z ', '| doi-broken-date = Z123Z ', '| doi-inactive-date = Z123Z ', '| pmc-embargo-date = Z123Z ', '| embargo = Z123Z '], '', $text);
      $text = str_replace(['displayeditors',  'editor1mask', 'editormask1',  'interviewerlink',  'interviewermask',  'no-cat', 'notracking',  'interviewermask',  'albumlink', 'ISBN13', 'isbn13'],
                          ['display-editors', 'editor-mask', 'editor-mask1', 'interviewer-link', 'interviewer-mask', 'nocat',  'no-tracking', 'interviewer-mask', 'titlelink', 'isbn',   'isbn'], $text);
      $text = str_replace(['editor1link',  'editorlink1',  'subjectlink1'],
                          ['editor1-link', 'editor1-link', 'subject-link1'], $text);
      $text = str_replace(['booktitle',  'nopp',  'displayauthors',  'city',     'editorlink',  ' editors ='],
                          ['book-title', 'no-pp', 'display-authors', 'location', 'editor-link', ' editor ='], $text);
      $text = str_replace(['titlelink',  'nocat',       'nocat',       ' embargo',          'conferenceurl',  'contributionurl',  'laydate',  'laysource',  'layurl',  'sectionurl',  'seriesno',  'timecaption',  'titlelink'],
                          ['title-link', 'no-tracking', 'no-tracking', ' pmc-embargo-date', 'conference-url', 'contribution-url', 'lay-date', 'lay-source', 'lay-url', 'section-url', 'series-no', 'time-caption', 'title-link'], $text);
      if ($prepared->get('doi') === 'Z123Z') {
        $prepared->forget('doi-broken-date');
      }
      if (!str_i_same($text, $prepared->parsed_text())) {
         $orig .= $text;
         $new .= $prepared->parsed_text();
      }
    }
    $this->assertSame($orig, $new);
  }
  
  public function testDead() : void {
    $overlap = array_intersect(DEAD_PARAMETERS, PARAMETER_LIST);
    if (empty($overlap)) {
      $this->assertTrue(TRUE);
    } else {
      print_r($overlap);
      $this->assertNull('testDead Failed - see error array directly above');
    }
  }
  
  public function testAuthorsFlat() {
    $failed = FALSE;
    $test_flat = [];
    foreach (AUTHOR_PARAMETERS as $array) {
      foreach ($array as $param) {
        $test_flat[] = $param;
      }
    }
    $flat = FLATTENED_AUTHOR_PARAMETERS;
    $extra_flat = array_diff($flat, $test_flat);
    $missing_flat = array_diff($test_flat, $flat);
    
    if (!empty($extra_flat)) {
       echo "\n\n missing these in the AUTHOR_PARAMETERS array:\n";
       print_r($extra_flat);
       $failed = TRUE;
    }
    if (!empty($missing_flat)) {
       echo "\n\n missing these in the FLATTENED_AUTHOR_PARAMETERS array:\n";
       print_r($missing_flat);
       echo "\n expected \n";
       print_r($test_flat);
       $failed = TRUE;
    }
    if (count($flat) !== count(array_unique($flat))) {
       echo "\n\n duplicate entries in the FLATTENED_AUTHOR_PARAMETERS array:\n";
       sort($flat);
       $last = 'XXXXXXXX';
       foreach ($flat as $param) {
         if ($param === $last) echo "\n" . $param . "\n";
         $last = $param;
       }
       $failed = TRUE;
    } 
    $this->assertFalse($failed);
  }
}
