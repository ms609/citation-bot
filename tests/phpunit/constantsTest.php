<?php
declare(strict_types=1);

/*
 * Tests for constants.php.
 */

require_once __DIR__ . '/../testBaseClass.php';

final class constantsTest extends testBaseClass {

    protected function setUp(): void {
        if (BAD_PAGE_API !== '') {
            $this->markTestSkipped();
        }
    }

    public function testFillCache(): void {
        $this->fill_cache();
        $this->assertTrue(true);
    }

    public function testConstantsDefined(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $this->assertSame(count(UCFIRST_JOURNAL_ACRONYMS), count(JOURNAL_ACRONYMS));
        for ($i = 0; $i < count(JOURNAL_ACRONYMS); $i++) {
            $this->assertSame(trim(JOURNAL_ACRONYMS[$i]), trim(title_capitalization(mb_ucwords(trim(UCFIRST_JOURNAL_ACRONYMS[$i])), true)));
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
        for ($i = 0; $i < count(LC_SMALL_WORDS); $i++) {
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

    public function testConstantsOoops(): void { // Did we forget to upper/lower case one of them?
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        for ($i = 0; $i < count(JOURNAL_ACRONYMS); $i++) {
            $this->assertNotEquals(trim(JOURNAL_ACRONYMS[$i]), trim(UCFIRST_JOURNAL_ACRONYMS[$i]));
        }
        for ($i = 0; $i < count(LC_SMALL_WORDS); $i++) {
            $this->assertNotEquals(trim(UC_SMALL_WORDS[$i]), trim(LC_SMALL_WORDS[$i]));
        }
    }

    public function testForDisasters(): void { // Did we get things out of order and cause a disaster?
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $this->assertSame('BJPsych', title_capitalization('Bjpsych', true));
        $this->assertSame('HortScience', title_capitalization('Hortscience', true));
        $this->assertSame('TheMarker', title_capitalization('Themarker', true));
        $this->assertSame('Algebra i Analiz', title_capitalization('Algebra I Analiz', true));
        $this->assertSame('ChemSystemsChem', title_capitalization('Chemsystemschem', true));
        $this->assertSame('hessenARCHÄOLOGIE', title_capitalization('HessenARCHÄOLOGIE', true));
        $this->assertSame('Ocean Science Journal : OSJ', title_capitalization('Ocean Science Journal : Osj', true));
        $this->assertSame('Starine Jugoslavenske akademije znanosti i umjetnosti', title_capitalization('Starine Jugoslavenske Akademije Znanosti I Umjetnosti', true));
        $this->assertSame('voor de geschiedenis der Nederlanden', title_capitalization('Voor De Geschiedenis Der Nederlanden', true));
        $this->assertSame('Zprávy o zasedání Král. čes. společnosti nauk v Praze', title_capitalization('Zprávy O Zasedání Král. Čes. Společnosti Nauk V Praze', true));
    }

    public function testImplicitConstants(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        // Consonants
        $this->assertSame('X', title_capitalization('x', true));
        $this->assertSame('Xz', title_capitalization('xz', true));
        $this->assertSame('XZZ BBBB/EEE', title_capitalization('xzz bbbb/eee', true));
        $this->assertSame('XZZZ', title_capitalization('xzzz', true));
        // Both
        $this->assertSame('Xzza', title_capitalization('xzza', true));
        // Vowels
        $this->assertSame('AEIOU', title_capitalization('aeiou', true));
        // Y is neither
        $this->assertSame('Aeiouy', title_capitalization('aeiouy', true));
        $this->assertSame('Xzzzy', title_capitalization('xzzzy', true));
        // Relationship Status = It's Complicated :-)
        $this->assertSame('Xzzzy Aeiouy AEIOU and xzzzy Aeiouy AEIOU', title_capitalization('xzzzy Aeiouy aeiou and xzzzy Aeiouy aeiou', true));
        $this->assertSame('Xzzzy Aeiouy AEIOU and Xzzzy Aeiouy AEIOU', title_capitalization(mb_ucwords('xzzzy Aeiouy aeiou and xzzzy Aeiouy aeiou'), true));
    }

    public function testAllLowerCase(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $big_array = array_merge(HAS_NO_VOLUME, BAD_ACCEPTED_MANUSCRIPT_TITLES, BAD_AUTHORS,
                                 PUBLISHER_ENDINGS, BAD_TITLES, IN_PRESS_ALIASES, NON_PUBLISHERS,
                                 JOURNAL_IS_BOOK_SERIES, HAS_NO_ISSUE, WORKS_ARE_PUBLISHERS, PREFER_VOLUMES,
                                 PREFER_ISSUES);
        foreach ($big_array as $actual) {
            $this->assertSame(strtolower($actual), $actual);
        }
    }

    public function testMinimized(): void { // See is_book_series() function
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $big_array = JOURNAL_IS_BOOK_SERIES;
        foreach ($big_array as $actual) {
            $simple = trim(str_replace(['-', '.',  '   ', '  ', '[[', ']]'], [' ', ' ', ' ', ' ', ' ', ' '], $actual));
            $simple = trim(str_replace(['    ', '   ',  '  '], [' ', ' ', ' '], $simple));
            $this->assertSame($simple, $actual);
        }
    }

    public function testAllFreeOfUTF(): void { // If this fails, then we have to switch everything to MB_ (BAD_AUTHORS, HAS_NO_ISSUE already has UTF-8)
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $big_array = array_merge(HAS_NO_VOLUME, BAD_ACCEPTED_MANUSCRIPT_TITLES,
                                 PUBLISHER_ENDINGS, BAD_TITLES, IN_PRESS_ALIASES, NON_PUBLISHERS,
                                 JOURNAL_IS_BOOK_SERIES, WORKS_ARE_PUBLISHERS, PREFER_VOLUMES,
                                 PREFER_ISSUES, PARAMETER_LIST, LOTS_OF_EDITORS,
                                 TEMPLATES_WE_HARV, FLATTENED_AUTHOR_PARAMETERS, TEMPLATES_VCITE,
                                 TEMPLATES_WE_CHAPTER_URL, TEMPLATES_WE_RENAME, TEMPLATES_WE_BARELY_PROCESS,
                                 TEMPLATES_WE_SLIGHTLY_PROCESS, TEMPLATES_WE_PROCESS);
        foreach ($big_array as $actual) {
            $this->assertSame(mb_strtolower($actual), strtolower($actual));
            $this->assertSame(mb_strtoupper($actual), strtoupper($actual));
        }
    }

    public function testNoSpacesOnEnds(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $big_array = array_merge(HAS_NO_VOLUME, BAD_ACCEPTED_MANUSCRIPT_TITLES, BAD_AUTHORS,
                                 PUBLISHER_ENDINGS, BAD_TITLES, IN_PRESS_ALIASES, NON_PUBLISHERS,
                                 JOURNAL_IS_BOOK_SERIES, HAS_NO_ISSUE, WORKS_ARE_PUBLISHERS, PREFER_VOLUMES,
                                 PREFER_ISSUES);
        foreach ($big_array as $actual) {
            if (!in_array($actual, ["sign up "], true)) {
                $this->assertSame(trim($actual), $actual);
            }
        }
    }

    public function testAtoZ(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $leader = true;
        $start_alpha = '/* The following will be automatically updated to alphabetical order */';
        $end_alpha = '/* The above will be automatically updated to alphabetical order */';
        $filename = __DIR__ . '/../../constants/capitalization.php';
        $old_contents = file_get_contents($filename);
        $sections = explode($start_alpha, $old_contents);
        foreach ($sections as &$section) {
            $alpha_end = stripos($section, $end_alpha);
            if (!$alpha_end) continue;
            $alpha_bit = substr($section, 0, $alpha_end);
            $alpha_bits = preg_split('~(?<=\'),~', $alpha_bit);
            $alpha_bits = array_map('trim', $alpha_bits);
            if ($leader) {
                $leader_bits = $alpha_bits;
                sort($alpha_bits, SORT_STRING | SORT_FLAG_CASE);
                $leader = false;
            } else {
                $this->assertSame(count($leader_bits), count($alpha_bits));
                array_multisort($leader_bits, SORT_STRING | SORT_FLAG_CASE, $alpha_bits);
                $leader_bits = null;
                $leader = true;
            }
            $bits_length = array_map('strlen', $alpha_bits);
            $bit_length = current($bits_length);
            $chunk_length = 0;
            $new_line = "\n    ";
            $alphaed = '';
            array_unshift($alpha_bits, ''); // We use next below, need a fake bit at the start
            foreach ($bits_length as $bit_length) {
                $bit = next($alpha_bits);
                $alphaed .= $bit ? ($bit . ",") : '';
                $alphaed .= $new_line;
            }
            if ($alphaed === $new_line) $alphaed = '';
            $section = $alphaed . substr($section, $alpha_end);
        }
        unset ($section); // Destroy pointer to be safe

        $new_contents = implode($start_alpha, $sections);

        if (preg_replace('/\s+/','', $new_contents) === preg_replace('/\s+/','', $old_contents)) {
            $this->assertTrue(true);
        } else {
            $this->flush();
            echo "\n\n" . $filename . " needs alphabetized as follows\n";
            echo $new_contents . "\n\n\n";
            $this->flush();
            $this->assertTrue(false);
        }
    }

    public function testWhiteList(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $we_failed = false;
        $our_original_whitelist = PARAMETER_LIST;
        $our_whitelist = array_unique($our_original_whitelist);
        $our_whitelist_sorted = $our_whitelist;
        sort($our_whitelist_sorted);

        $wikipedia_response = WikipediaBot::GetAPage('Module:Citation/CS1/Whitelist');
        preg_match_all("~\s\[\'([a-zA-Z0-9\#\-\_ ]+?)\'\] = ~" , $wikipedia_response, $matches);
        $their_whitelist = $matches[1];
        $patent_whitelist = ['inventor', 'inventor#', 'inventor-surname', 'inventor#-surname', 'inventor-last',
                             'inventor#-last', 'inventor-given', 'inventor#-given', 'inventor-first', 'inventor#-first',
                             'inventor-first#', 'inventor-link', 'inventor#-link', 'inventor-link#', 'inventor#link',
                             'country-code', 'publication-number', 'patent-number', 'country', 'number', 'description',
                             'status', 'invent#', 'gdate', 'pubdate', 'publication-number', 'pridate', 'assign#',
                             'assignee', 'assign', 'inventor-surname#', 'inventor-last#', 'inventor-given#',
                             'inventorlink', 'inventorlink#', 'issue-date', 'fdate']; // Some are not valid, but people use them anyway
        $their_whitelist = array_merge(['CITATION_BOT_PLACEHOLDER_BARE_URL', 'citation_bot_placeholder_bare_url'],
                                             $patent_whitelist, $their_whitelist);
        $their_whitelist = array_unique($their_whitelist); // They might list the same thing twice
        $their_whitelist = array_diff($their_whitelist, ["template doc demo"]);

        $our_extra = array_diff($our_whitelist, $their_whitelist);
        $our_missing = array_diff($their_whitelist, $our_whitelist);
        $our_internal_extra = array_diff($our_original_whitelist, $our_whitelist);

        if (count($our_internal_extra) !== 0) {
             $this->flush();
             echo "\n \n testWhiteList:  What the Citation Bot has more than one copy of\n";
             print_r($our_internal_extra);
             $this->flush();
             $we_failed = true;
        }
        if (count($our_extra) !== 0) {
             $this->flush();
             echo "\n \n testWhiteList:  What the Citation Bot has that Wikipedia does not\n";
             print_r($our_extra);
             $this->flush();
             $we_failed = true;
        }
        if (count($our_missing) !== 0) {
             $this->flush();
             echo "\n \n testWhiteList:  What Wikipedia has that the Citation Bot does not\n";
             print_r($our_missing);
             $this->flush();
             $we_failed = true;
        }
        if ($our_whitelist !== $our_whitelist_sorted) {
             $this->flush();
             echo "\n \n testWhiteList:  Citation Bot has values out of order.  Expected order:\n";
             foreach($our_whitelist_sorted as $value) {
                 echo "    '" . $value . "',\n";
             }
             $this->flush();
             $we_failed = true;
        }
        $this->assertFalse($we_failed);
    }

    public function testWhiteListNotBlacklisted(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $whitelist = array_merge(DEAD_PARAMETERS, PARAMETER_LIST);
        $orig = '';
        $new = '';
        foreach($whitelist as $value) {
            $value = str_replace('#', '1', $value);
            if (stripos($value, '_bot')) {
                $value = 'title'; // basically skip it
            }
            $text = '{{citation | ' . $value . ' = Z123Z }}';
            $prepared = $this->prepare_citation($text); // Use prepare to avoid being "smart"
            $text = str_replace(['authors1', 'editors1', 'publication-date', 'publicationdate',  'publication-place', 'publicationplace', 'chapter-url ', 'chapterurl ', '| p = Z123Z ',    '| pp = Z123Z ',    '| URL = Z123Z ', '| bioRxiv = Z123Z ', '| ARXIV = Z123Z ', '| DOI = Z123Z '],  // Put spaces on end to not change chapter-url-access and such
                                ['author1',  'editor1',  'publication-date', 'publication-date', 'publication-place', 'publication-place', 'url ',        'url '       , '| page = Z123Z ', '| pages = Z123Z ', '| url = Z123Z ', '| biorxiv = Z123Z ', '| arxiv = Z123Z ', '| doi = Z123Z '], $text); // Stuff that get "fixed"
            $text = str_replace(['| doi-access = Z123Z ', '| access-date = Z123Z ', '| accessdate = Z123Z ', '| doi-broken = Z123Z ', '| doi-broken-date = Z123Z ', '| doi-inactive-date = Z123Z ', '| pmc-embargo-date = Z123Z ', '| embargo = Z123Z ', '| arşivengelli = Z123Z '], '', $text);
            $text = str_replace(['displayeditors',  'editor1mask',  'editormask1',  'interviewerlink',  'interviewermask',  'no-cat', 'notracking',  'interviewermask',  'albumlink', 'ISBN13', 'isbn13'],
                                ['display-editors', 'editor-mask1', 'editor-mask1', 'interviewer-link', 'interviewer-mask', 'nocat',  'no-tracking', 'interviewer-mask', 'titlelink', 'isbn',   'isbn'], $text);
            $text = str_replace(['editor1link',  'editorlink1',  'subjectlink1',  'origyear'],
                                ['editor1-link', 'editor1-link', 'subject-link1', 'orig-date'], $text);
            $text = str_replace(['booktitle',  'nopp',  'displayauthors',  'city',     'editorlink',  ' editors ='],
                                ['book-title', 'no-pp', 'display-authors', 'location', 'editor-link', ' editor ='], $text);
            $text = str_replace(['episodelink',  'mailinglist',  'mapurl',  'serieslink' , 'coauthor '],
                                ['episode-link', 'mailing-list', 'map-url', 'series-link', 'coauthors ' ], $text);
            $text = str_replace(['titlelink',  'nocat',       'nocat',       ' embargo',          'conferenceurl',  'contributionurl',  'laydate',  'laysource',  'layurl',  'sectionurl',  'seriesno',  'timecaption',  'titlelink'],
                                ['title-link', 'no-tracking', 'no-tracking', ' pmc-embargo-date', 'conference-url', 'contribution-url', 'lay-date', 'lay-source', 'lay-url', 'section-url', 'series-no', 'time-caption', 'title-link'], $text);
            $text = str_replace(['subjectlink',  'transcripturl',  '| name = ',   'extrait', 'deadlink', 'dead-link'],
                                ['subject-link', 'transcript-url', '| author = ', 'quote',   'deadurl',  'deadurl'  ], $text);
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

    public function testDead(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $overlap = array_intersect(DEAD_PARAMETERS, PARAMETER_LIST);
        if (empty($overlap)) {
            $this->assertTrue(true);
        } else {
            $this->flush();
            print_r($overlap);
            $this->flush();
            $this->assertNull('testDead Failed - see error array directly above');
        }
    }

    public function testMagazinesAndNot(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $overlap = array_intersect(ARE_MAGAZINES, ARE_MANY_THINGS);
        if (empty($overlap)) {
            $this->assertTrue(true);
        } else {
            $this->flush();
            print_r($overlap);
            $this->flush();
            $this->assertNull('testMagazinesAndNot Failed - see error array directly above');
        }
        $overlap = array_intersect(ARE_MAGAZINES, ARE_NEWSPAPERS);
        if (empty($overlap)) {
            $this->assertTrue(true);
        } else {
            $this->flush();
            print_r($overlap);
            $this->flush();
            $this->assertNull('testMagazinesAndNot Failed - see error array directly above');
        }
        $overlap = array_intersect(ARE_MANY_THINGS, ARE_NEWSPAPERS);
        if (empty($overlap)) {
            $this->assertTrue(true);
        } else {
            $this->flush();
            print_r($overlap);
            $this->flush();
            $this->assertNull('testMagazinesAndNot Failed - see error array directly above');
        }
    }

    public function testAuthorsFlat() {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $failed = false;
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
             $this->flush();
             echo "\n\n missing these in the AUTHOR_PARAMETERS array:\n";
             print_r($extra_flat);
             $this->flush();
             $failed = true;
        }
        if (!empty($missing_flat)) {
             $this->flush();
             echo "\n\n missing these in the FLATTENED_AUTHOR_PARAMETERS array:\n";
             print_r($missing_flat);
             echo "\n expected \n";
             print_r($test_flat);
             $this->flush();
             $failed = true;
        }
        if (count($flat) !== count(array_unique($flat))) {
             $this->flush();
             echo "\n\n duplicate entries in the FLATTENED_AUTHOR_PARAMETERS array:\n";
             sort($flat);
             $last = 'XXXXXXXX';
             foreach ($flat as $param) {
                 if ($param === $last) echo "\n" . $param . "\n";
                 $last = $param;
             }
             $this->flush();
             $failed = true;
        }
        $this->assertFalse($failed);
    }

    public function testNonJournalList() {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $flat = NON_JOURNAL_WEBSITES;
        sort($flat);
        $failed = false;
        $last = 'XXXXXXXX';
        foreach ($flat as $param) {
            if (substr($param, -1) !== '/') {
                 $failed = true;
                 $this->flush();
                 echo "\n\n Missing end slash in NON_JOURNAL_WEBSITES: " . $param . "\n\n";
                 $this->flush();
            }
            if ($param === $last) {
                 $failed = true;
                 $this->flush();
                 echo "\n\n Duplicate entry in NON_JOURNAL_WEBSITES: " . $param . "\n\n";
                 $this->flush();
            }
            if (strpos($param, '.') === false) {
                 $failed = true;
                 $this->flush();
                 echo "\n\n Invalid hostname in NON_JOURNAL_WEBSITES: " . $param . "\n\n";
                 $this->flush();
            }
            if (preg_match('~\s~', $param) !== 0) {
                 $failed = true;
                 $this->flush();
                 echo "\n\n Whitespace in NON_JOURNAL_WEBSITES: " . $param . "\n\n";
                 $this->flush();
            }
            $last = $param;
        }
        $this->assertFalse($failed);
    }

    public function testNonJournalListIsNotBad() {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $failed = false;
        foreach (CANONICAL_PUBLISHER_URLS as $journal) {
            $journal = $journal . '/';
            $check = $journal;
            foreach (NON_JOURNAL_WEBSITES as $bad) {
                $check = str_ireplace($bad, '', $check);
            }
            if ($check !== $journal) {
                 $failed = true;
                 $this->flush();
                 echo "\n\n CANONICAL_PUBLISHER_URLS damaged by NON_JOURNAL_WEBSITES: " . $journal . ' changed to ' . $check . "\n\n";
                 $this->flush();
            }
            $check = $journal;
            foreach (JOURNAL_ARCHIVES_SITES as $bad) {
                $check = str_ireplace($bad, '', $check);
            }
            if ($check !== $journal) {
                 $failed = true;
                 $this->flush();
                 echo "\n\n JOURNAL_ARCHIVES_SITES damaged by NON_JOURNAL_WEBSITES: " . $journal . ' changed to ' . $check . "\n\n";
                 $this->flush();
            }
        }
        foreach (NON_JOURNAL_WEBSITES as $journal) {
            $journal = $journal . '/';
            $check = $journal;
            foreach (JOURNAL_ARCHIVES_SITES as $bad) {
                $check = str_ireplace($bad, '', $check);
            }
            if ($check !== $journal) {
                 $failed = true;
                 $this->flush();
                 echo "\n\n NON_JOURNAL_WEBSITES damaged by JOURNAL_ARCHIVES_SITES: " . $journal . ' changed to ' . $check . "\n\n";
                 $this->flush();
            }
        }
        $this->assertFalse($failed);
    }

    public function testItalicsOrder(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $in_order = true;
        $spaces_at = 99999999;
        $max_spaces = 0;
        $italics = explode("|", ITALICS_LIST);
        $this->assertSame("END_OF_CITE_list_junk", end($italics));
        foreach ($italics as $item) {
            $spaces = substr_count($item, " ");
            if ($spaces > $spaces_at) $in_order = false;
            $spaces_at = $spaces;
            $max_spaces = max($max_spaces, $spaces);
        }
        if (!$in_order) {
            $this->flush();
            echo "\n Correct values for italics.php\n";
            echo "\n";
            echo "const ITALICS_LIST =\n";
            for ($i = $max_spaces; $i > -1 ; $i--) {
                foreach ($italics as $item) {
                     if (substr_count($item, " ") === $i && $item !== 'END_OF_CITE_list_junk') {
                          echo ' "' . $item . '|" .' . "\n";
                     }
                }
            }
            echo ' "END_OF_CITE_list_junk";' . "\n";
            $this->flush();
        }
        $this->assertTrue($in_order);

        // If we have "Specius" before "Speciusia" that is bad
        $in_order = true;
        $italics = explode("|", ITALICS_LIST);
        for ($i = 0; $i < count($italics); $i++) {
            $early = $italics[$i];
            for ($j = $i+1; $j < count($italics); $j++) {
                $later = $italics[$j];
                if ((substr_count($later, $early) !== 0) && ($later !== $early)) {
                    $in_order = false;
                    $this->flush();
                    echo "\n\nWRONG ORDER: $later   AND   $early\n\n";
                    $this->flush();
                }
            }
        }
        $this->assertTrue($in_order);
    }

    public function testItalicsNoDuplicates(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $italics = explode("|", ITALICS_LIST);
        sort($italics);
        $last = "123412341234";
        $good = true;
        foreach ($italics as $item) {
            if ($item === $last) {
                $this->flush();
                echo "\n Found duplicate: $item \n";
                $this->flush();
                $good = false;
            }
            $last = $item;
        }
        $this->assertTrue($good);
    }

    public function testCamelNoDuplicates(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $italics = CAMEL_CASE;
        sort($italics);
        $last = "123412341234";
        $good = true;
        foreach ($italics as $item) {
            if ($item === $last) {
                $this->flush();
                echo "\n Found duplicate: $item \n";
                $this->flush();
                $good = false;
            }
            $last = $item;
        }
        $this->assertTrue($good);
    }


    public function testItalicsEscaped1(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $italics = str_replace(['\\(', '\\)', '\\.'], '', ITALICS_LIST);
        $this->assertSame(0 , substr_count($italics, '('));
    }
    public function testItalicsEscaped2(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $italics = str_replace(['\\(', '\\)', '\\.'], '', ITALICS_LIST);
        $this->assertSame(0 , substr_count($italics, ')'));
    }
    public function testItalicsEscaped3(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $italics = str_replace(['\\(', '\\)', '\\.'], '', ITALICS_LIST);
        $this->assertSame(0 , substr_count($italics, '\\'));
    }
    public function testItalicsEscaped4(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $italics = str_replace(['\\(', '\\)', '\\.'], '', ITALICS_LIST);
        $this->assertSame(0 , substr_count($italics, '.'));
    }

    public function testItalicsNoSpaces(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $italics = explode("|", ITALICS_LIST);
        foreach ($italics as $item) {
            $this->assertNotEquals(' ', substr($item, 0, 1));
            $this->assertNotEquals(' ', substr($item, -1));
        }
    }

    public function testItalicsHardCode(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $this->assertSame(count(ITALICS_HARDCODE_IN), count(ITALICS_HARDCODE_OUT));
        for ($i = 0; $i < count(ITALICS_HARDCODE_OUT); $i++) {
            $this->assertSame(0, substr_count("'''", ITALICS_HARDCODE_IN[$i]));
            $this->assertSame(0, substr_count("'''", ITALICS_HARDCODE_OUT[$i]));
            $in  = str_replace(["'", " ", ':', ',', '.'], ['', '', '', '', ''], ITALICS_HARDCODE_IN[$i]);
            $out = str_replace(["'", " ", ':', ',', '.'], ['', '', '', '', ''], ITALICS_HARDCODE_OUT[$i]);
            $this->assertSame($in, $out); // Same once spaces and single quotes are removed
        }
    }

    public function testConversionsGood(): void {
        WikipediaBot::make_ch();
        $page = new TestPage();
        $errors = "";
        foreach (TEMPLATE_CONVERSIONS as $convert) {
            // return -1 if page does not exist; 0 if exists and not redirect; 1 if is redirect
            if ($convert[0] === 'cite standard' || $convert[0] === 'Cite standard') { // A wrapper now, but not usable yet
                continue;
            }
            $tem = 'Template:' . $convert[0];
            $tem = str_replace(' ', '_', $tem);
            // Sometimes it is a redirect, sometimes a safesubst/invoke, and sometimes does not even exist and it comes from copy/paste other wikis
            if (WikipediaBot::is_redirect($tem) === 0) { // The page actually exists
                $page->get_text_from($tem);
                $text = $page->parsed_text();
                if (stripos($text, 'safesubst:') === false) {
                    $errors = $errors . '   Is real:' . $convert[0];
                }
            }
            $tem = 'Template:' . $convert[1];
            $tem = str_replace(' ', '_', $tem);
            if (WikipediaBot::is_redirect($tem) !== 0 &&
                $tem !== 'Template:Cite_paper' && $tem !== 'Template:cite_paper' && // We use code to clean up cite paper
                $tem !== 'Template:LCCN' && $tem !== 'Template:PMC' && $tem !== 'Template:URN') { // We remove one layer of re-direct, but not both
                $errors = $errors . '   Is now a redirect:' . $convert[1];
            }
        }
        $this->assertSame("", $errors); // We want a list of all of them
    }

    public function testFreeDOI(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        foreach (DOI_FREE_PREFIX as $prefix) {
            $this->assertTrue($prefix != '');
            if (strpos($prefix, '/') === false) {
                $this->assertSame('This needs a slash', $prefix);
            }
        }
    }

    public function testISBNlist(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $last = -1;
        foreach (ISBN_HYPHEN_POS as $k => $v) {
            $k = (int) $k;
            $this->assertTrue($k > $last);
            $last = $k;
            $this->assertSame(3, count($v));
            $this->assertTrue(is_int($v[0]));
            $this->assertTrue(is_int($v[1]));
            $this->assertTrue(is_int($v[2]));
        }
    }

    public function testForISBNListUpdates(): void { // https://en.wikipedia.org/w/index.php?title=Module:Format_ISBN/data&action=history
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $wikipedia_response = WikipediaBot::GetAPage('Module:Format_ISBN/data');
        $this->assertSame(1, substr_count($wikipedia_response, 'RangeMessage timestamp:'));
        $this->assertSame(1, substr_count($wikipedia_response, ISBN_TIME_STAMP_USED));
    }

    public function testCurlLimit(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $ch = curl_init();
        $this->assertSame(1, curl_limit_page_size($ch, 1, 134217729));
        $this->assertSame(0, curl_limit_page_size($ch, 1, 134217728));
    }

    public function testDoubleMap(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $errors = '';
        $all_maps = array_merge(COMMON_MISTAKES, COMMON_MISTAKES_TOOL);
        $okay_to_be_bad = ['coauthors', 'deadurl', 'lay-date', 'lay-source', 'lay-url', 'month', 'authors'];  // We upgrade dead paramters to better dead parameters
        foreach ($all_maps as $map_me => $mapped) {
            if (isset($all_maps[$mapped])) {
                $errors .= ' re-mapped: ' . $map_me . '/' . $mapped . '    ';
            }
            if (trim($mapped) !== $mapped || trim($map_me) !== $map_me) {
                $errors .= ' extra white space: ' . $map_me . '/' . $mapped . '    ';
            }
            // Number replaced with pound
            $mappedp = preg_replace('~\d+~', '#', $mapped);
            $mappedp = preg_replace('~##+~', '#', $mappedp);
            if ($mappedp === 's#cid') {
            	$mappedp = 's2cid';
            }
            if (!in_array($mapped, $okay_to_be_bad, true)) {
            	if (!in_array($mappedp, PARAMETER_LIST, true)) {
                	$errors .= ' mapped to non-existant parameter: ' . $map_me . '/' . $mapped . '    ';
            	}
            	if (in_array($mappedp, DEAD_PARAMETERS, true) || in_array($mapped, DEAD_PARAMETERS, true)) {
                	$errors .= ' mapped to dead parameter: ' . $map_me . '/' . $mapped . '    ';
            	}
            }
        }
        foreach (COMMON_MISTAKES_TOOL as $map_me) {
            if (isset(COMMON_MISTAKES[$map_me])) {
                $errors .= ' double-mapped: ' . $map_me . '    ';
            }
        }
        $this->assertSame('', $errors);
    }
  
}
