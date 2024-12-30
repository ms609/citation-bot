<?php
declare(strict_types=1);

/*
 * expandFns.php tests
 */

require_once __DIR__ . '/../testBaseClass.php';

final class expandFnsTest extends testBaseClass {

    protected function setUp(): void {
        if (BAD_PAGE_API !== '') {
            $this->markTestSkipped();
        }
    }

    public function testFillCache(): void {
        $this->fill_cache();
        $this->assertTrue(true);
    }

    public function testCapitalization1a(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $this->assertSame('Molecular and Cellular Biology', title_capitalization(title_case('Molecular and cellular biology'), true));
    }
    public function testCapitalization1b(): void {
        $this->assertSame('z/Journal', title_capitalization(title_case('z/Journal'), true));
    }
    public function testCapitalization1c(): void {
        $this->assertSame('The Journal of Journals', title_capitalization('The Journal Of Journals', true));
    }
    public function testCapitalization1d(): void {
        $this->assertSame('A Journal of Chemistry A', title_capitalization('A Journal of Chemistry A', true));
    }
    public function testCapitalization1e(): void {
        $this->assertSame('A Journal of Chemistry E', title_capitalization('A Journal of Chemistry E', true));
    }
    public function testCapitalization2a(): void {
        $this->assertSame('This a Journal', title_capitalization('THIS A JOURNAL', true));
    }
    public function testCapitalization2b(): void {
        $this->assertSame('This a Journal', title_capitalization('THIS A JOURNAL', true));
    }
    public function testCapitalization2c(): void {
        $this->assertSame("THIS 'A' JOURNAL mittEilUngen", title_capitalization("THIS `A` JOURNAL mittEilUngen", true));
    }
    public function testCapitalization3(): void {
        $this->assertSame('[Johsnon And me]', title_capitalization('[Johsnon And me]', true)); // Do not touch links
    }
    public function testCapitalization4(): void {
        $this->assertSame('This is robert WWW', title_capitalization('This is robert www' , true));
    }
    public function testCapitalization5(): void {
        $this->assertSame('This is robert http://', title_capitalization('This is robert http://', true));
    }
    public function testCapitalization6(): void {
        $this->assertSame('This is robert www.', title_capitalization('This is robert www.' , true));
    }
    public function testCapitalization7(): void {
        $this->assertSame('This is robert www-', title_capitalization('This is robert www-' , true));
    }
    public function testCapitalization8a(): void {
        $this->assertSame('I the Las Vegas.  Trip.', title_capitalization('I the las Vegas.  Trip.' , true));
    }
    public function testCapitalization8b(): void {
        $this->assertSame('I the Las Vegas,  Trip.', title_capitalization('I the las Vegas,  Trip.' , true));
    }
    public function testCapitalization8c(): void {
        $this->assertSame('I the Las Vegas:  Trip.', title_capitalization('I the las Vegas:  Trip.' , true));
    }
    public function testCapitalization8d(): void {
        $this->assertSame('I the Las Vegas;  Trip.', title_capitalization('I the las Vegas;  Trip.' , true));
    }
    public function testCapitalization8e(): void {
        $this->assertSame('I the las Vegas...Trip.', title_capitalization('I the las Vegas...Trip.' , true));
    }
    public function testCapitalization9(): void {
        $this->assertSame('SAGE Open', title_capitalization('Sage Open' , true));
    }
    public function testCapitalization10(): void {
        $this->assertSame('CA', title_capitalization('Ca' , true));
    }

    public function testCapitalization11(): void {
        $this->assertSame('The Series A and B qu', title_capitalization('The Series a and b qu' , true));
    }

    public function testCapitalization12(): void {
        $this->assertSame('PEN International', title_capitalization('Pen International' , true));
    }

    public function testCapitalization13(): void {
        $this->assertSame('Time Off', title_capitalization('Time off' , true));
    }

    public function testCapitalization14(): void {
        $this->assertSame('IT Professional', title_capitalization('It Professional' , true));
    }

    public function testCapitalization15(): void {
        $this->assertSame('JOM', title_capitalization('Jom' , true));
    }

    public function testFrenchCapitalization1(): void {
        $this->assertSame("L'Aerotecnica", title_capitalization(title_case("L'Aerotecnica"), true));
    }
    public function testFrenchCapitalization2(): void {
        $this->assertSame("Phénomènes d'Évaporation d'Hydrologie", title_capitalization(title_case("Phénomènes d'Évaporation d’hydrologie"), true));
    }
    public function testFrenchCapitalization3(): void {
        $this->assertSame("D'Hydrologie Phénomènes d'Évaporation d'Hydrologie l'Aerotecnica", title_capitalization("D'Hydrologie Phénomènes d&#x2019;Évaporation d&#8217;Hydrologie l&rsquo;Aerotecnica", true));
    }

    public function testITS(): void {
        $this->assertSame(                       "Keep case of its Its and ITS",
                            title_capitalization("Keep case of its Its and ITS", true));
        $this->assertSame(                       "ITS Keep case of its Its and ITS",
                            title_capitalization("ITS Keep case of its Its and ITS", true));
    }

    public function testExtractDoi(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/full')[1]);
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', extract_doi('http://onlinelibrary.wiley.com/doi/10.1111/j.1475-4983.2012.01203.x/abstract')[1]);
        $this->assertSame('10.1016/j.physletb.2010.03.064', extract_doi(' 10.1016%2Fj.physletb.2010.03.064')[1]);
        $this->assertSame('10.1093/acref/9780199204632.001.0001', extract_doi('http://www.oxfordreference.com/view/10.1093/acref/9780199204632.001.0001/acref-9780199204632-e-4022')[1]);
        $this->assertSame('10.1038/nature11111', extract_doi('http://www.oxfordreference.com/view/10.1038/nature11111/figures#display.aspx?quest=solve&problem=punctuation')[1]);
        $the_return = extract_doi('https://somenewssite.com/date/25.10.2015/2137303/default.htm'); // 10.2015/2137303 looks like a DOI
        $this->assertSame('', $the_return[0]);
        $this->assertSame('', $the_return[1]);
    }

    public function testSanitizeDoi1(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', sanitize_doi('10.1111/j.1475-4983.2012.01203.x'));
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', sanitize_doi('10.1111/j.1475-4983.2012.01203.x.')); // extra dot
        $this->assertSame('10.1111/j.1475-4983.2012.01203.x', sanitize_doi('10.1111/j.1475-4983.2012.01203.'));  // Missing x after dot
    }
    public function testSanitizeDoi2(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame('10.0000/Rubbish_bot_failure_test', sanitize_doi('10.0000/Rubbish_bot_failure_test.')); // Rubbish with trailing dot, just remove it
        $this->assertSame('10.0000/Rubbish_bot_failure_test', sanitize_doi('10.0000/Rubbish_bot_failure_test#page_scan_tab_contents'));
        $this->assertSame('10.0000/Rubbish_bot_failure_test', sanitize_doi('10.0000/Rubbish_bot_failure_test;jsessionid'));
        $this->assertSame('10.0000/Rubbish_bot_failure_test', sanitize_doi('10.0000/Rubbish_bot_failure_test/summary'));
    }

    public function testTidyDate1(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame('2014', tidy_date('maanantai 14. heinäkuuta 2014'));
        $this->assertSame('2012-04-20', tidy_date('2012年4月20日 星期五'));
        $this->assertSame('2011-05-10', tidy_date('2011-05-10T06:34:00-0400'));
        $this->assertSame('July 2014', tidy_date('2014-07-01T23:50:00Z, 2014-07-01'));
        $this->assertSame('', tidy_date('۱۳۸۶/۱۰/۰۴ - ۱۱:۳۰'));
    }
    public function testTidyDate2(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame('2014-01-24', tidy_date('01/24/2014 16:01:06'));
        $this->assertSame('2011-11-30', tidy_date('30/11/2011 12:52:08'));
        $this->assertSame('2011'      , tidy_date('05/11/2011 12:52:08'));
        $this->assertSame('2011-11-11', tidy_date('11/11/2011 12:52:08'));
        $this->assertSame('2018-10-21', tidy_date('Date published (2018-10-21'));
        $this->assertSame('2008-04-29', tidy_date('07:30 , 04.29.08'));
    }
    public function testTidyDate3(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame('', tidy_date('-0001-11-30T00:00:00+00:00'));
        $this->assertSame('', tidy_date('22/22/2010'));  // That is not valid date code
        $this->assertSame('', tidy_date('The date is 88 but not three')); // Not a date, but has some numbers
        $this->assertSame('2016-10-03', tidy_date('3 October, 2016')); // evil comma
    }
    public function testTidyDate4(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame('22 October 1999 – 22 September 2000', tidy_date('1999-10-22 - 2000-09-22'));
        $this->assertSame('22 October – 22 September 1999', tidy_date('1999-10-22 - 1999-09-22'));
    }

    public function testTidyDate5(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame('', tidy_date('Invalid'));
        $this->assertSame('', tidy_date('1/1/0001'));
        $this->assertSame('', tidy_date('0001-01-01'));
        $this->assertSame('', tidy_date('1969-12-31'));
        $this->assertSame('', tidy_date('19xx'));
    }
    public function testTidyDate6(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame('', tidy_date('2000 1999-1998'));
        $this->assertSame('', tidy_date('1969-12-31'));
        $this->assertSame('', tidy_date('0011-10-07'));
        $this->assertSame('', tidy_date('4444-10-07'));
    }
    public function testTidyDate7(): void {
        $this->assertSame('1999-09-09', tidy_date('1999-09-09T22:10:11+08:00'));
    }
    public function testTidyDate7b(): void {
        $this->assertSame('2001-11-11', tidy_date('dafdsafsd    2001-11-11'));
    }
    public function testTidyDate8(): void {
        $this->assertSame('2000-03-27' , tidy_date('3/27/2000 dafdsafsd dafdsafsd'));
    }
    public function testTidyDate8b(): void {
        $this->assertSame('2000-03-27' , tidy_date('dafdsafsd3/27/2000'));
    }
    public function testTidyDate8c(): void {
        $this->assertSame('' , tidy_date('23--'));
    }
    
    public function testRemoveComments(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame('ABC', remove_comments('A<!-- -->B# # # CITATION_BOT_PLACEHOLDER_COMMENT 33 # # #C'));
    }

    public function testJstorInDoi(): void {
        $template = $this->prepare_citation('{{cite journal|jstor=}}');
        $doi = '10.2307/3241423?junk'; // test 10.2307 code and ? code
        check_doi_for_jstor($doi, $template);
        $this->assertSame('3241423', $template->get2('jstor'));
    }

    public function testJstorInDoi2(): void {
        $template = $this->prepare_citation('{{cite journal|jstor=3111111}}');
        $doi = '10.2307/3241423?junk';
        check_doi_for_jstor($doi, $template);
        $this->assertSame('3111111', $template->get2('jstor'));
    }

    public function testJstorInDoi3(): void {
        $template = $this->prepare_citation('{{cite journal|jstor=3111111}}');
        $doi = '3241423';
        check_doi_for_jstor($doi, $template);
        $this->assertSame('3111111', $template->get2('jstor'));
    }

    public function test_titles_are_dissimilar_LONG(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $big1 = "asdfgtrewxcvbnjy67rreffdsffdsgfbdfni goreinagoidfhgaodusfhaoleghwc89foxyehoif2faewaeifhajeowhf;oaiwehfa;ociboes;";
        $big1 = $big1 . $big1 .$big1 .$big1 .$big1 ;
        $big2 = $big1 . "X"; // stuff...X
        $big1 = $big1 . "Y"; // stuff...Y
        $big3 = $big1 . $big1 ; // stuff...Xstuff...X
        $this->assertTrue(titles_are_similar($big1, $big2));
        $this->assertTrue(titles_are_dissimilar($big1, $big3));
    }

    public function test_titles_are_similar_ticks(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame('ejscriptgammaramshg', strip_diacritics('ɞɟɡɣɤɥɠ'));
        $this->assertTrue(titles_are_similar('ɞɟɡɣɤɥɠ', 'ejscriptgammaramshg'));
    }

    public function test_titles_are_similar_series(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertTrue(titles_are_similar('ABC  (clifton, n j ) ', 'ABC  '));
    }

    public function test_titles_are_similar_junk(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertTrue(titles_are_similar('DSFrHdseyJhgdtyhTSFDhge5safdsfasdfa', '��D��S��F��r��H��d��s��e��y��J��h��g��d��t��y��h��T��S��F��D��h��g��e��5��s��a��f��d��s��f��a��s��d��f��a��'));
    }

    public function test_titles_are_similar_junk2(): void {
        $x = 'Eulerian Numbers';
        $this->assertFalse(str_i_same($x, $x));
    }

    public function test_chapters_are_simple(): void {
        $this->assertSame('zbcder', titles_simple('Chapter 3 - Zbcder'));
    }

    public function testArrowAreQuotes1(): void {
        $text = "This » That";
        $this->assertSame($text,straighten_quotes($text, true));
    }
    public function testArrowAreQuotes2(): void {
        $text = "X«Y»Z";
        $this->assertSame('X"Y"Z',straighten_quotes($text, true));
    }
    public function testArrowAreQuotes3(): void {
        $text = "This › That";
        $this->assertSame($text,straighten_quotes($text, true));
    }
    public function testArrowAreQuotes4(): void {
        $text = "X‹Y›Z";
        $this->assertSame("X'Y'Z",straighten_quotes($text, true));
    }
    public function testArrowAreQuotes5(): void {
        $text = "This » That";
        $this->assertSame($text,straighten_quotes($text, false));
    }
    public function testArrowAreQuotes6(): void {
        $text = "X«Y»Z";
        $this->assertSame($text,straighten_quotes($text, false));
    }
    public function testArrowAreQuotes7(): void {
        $text = "This › That";
        $this->assertSame($text,straighten_quotes($text, false));
    }
    public function testArrowAreQuotes8(): void {
        $text = "X‹Y›Z";
        $this->assertSame("X'Y'Z",straighten_quotes($text, false));
    }
    public function testArrowAreQuotes9(): void {
        $text = "«XY»Z";
        $this->assertSame($text,straighten_quotes($text, false));
    }
    public function testArrowAreQuotes10(): void {
        $text = "«XY»Z";
        $this->assertSame('"XY"Z',straighten_quotes($text, true));
    }
    public function testArrowAreQuotes11(): void {
        $text = "«Y»";
        $this->assertSame('"Y"',straighten_quotes($text, true));
    }
    public function testArrowAreQuotes12(): void {
        $text = "‹Y›";
        $this->assertSame("'Y'",straighten_quotes($text, true));
    }
    public function testArrowAreQuotes13(): void {
        $text = "«Y»";
        $this->assertSame('"Y"',straighten_quotes($text, false));
    }
    public function testArrowAreQuotes14(): void {
        $text = "‹Y›";
        $this->assertSame("'Y'",straighten_quotes($text, false));
    }
    public function testArrowAreQuotes15(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $text = '«Lastronaute» du vox pop de Guy Nantel était candidat aux élections fédérales... et a perdu';
        $this->assertSame($text,straighten_quotes($text, false));
    }
    public function testArrowAreQuotes16(): void {
        $text = '«Lastronaute» du vox pop de Guy Nantel était candidat aux élections fédérales... et a perdu';
        $this->assertSame('"Lastronaute" du vox pop de Guy Nantel était candidat aux élections fédérales... et a perdu', straighten_quotes($text, true));
    }

    // This MML code comes from a real CrossRef search of DOI 10.1016/j.newast.2009.05.001  // TODO - should do more than just give up and wrap in nowiki
    public function testMathInTitle1(): void {
        $text_math = 'Spectroscopic analysis of the candidate <math><mrow>ß</mrow></math> Cephei star <math><mrow>s</mrow></math> Cas: Atmospheric characterization and line-profile variability';
        $this->assertSame($text_math, sanitize_string($text_math));
    }
    public function testMathInTitle2(): void {
        $text_math = 'Spectroscopic analysis of the candidate <math><mrow>ß</mrow></math> Cephei star <math><mrow>s</mrow></math> Cas: Atmospheric characterization and line-profile variability';
        $this->assertSame('<nowiki>' . $text_math . '</nowiki>', wikify_external_text($text_math));
    }
    public function testMathInTitle3(): void {
        $text_math = 'Spectroscopic analysis of the candidate <math><mrow>ß</mrow></math> Cephei star <math><mrow>s</mrow></math> Cas: Atmospheric characterization and line-profile variability';
        $text_mml    = 'Spectroscopic analysis of the candidate <mml:math altimg="si37.gif" overflow="scroll" xmlns:xocs="http://www.elsevier.com/xml/xocs/dtd" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.elsevier.com/xml/ja/dtd" xmlns:ja="http://www.elsevier.com/xml/ja/dtd" xmlns:mml="http://www.w3.org/1998/Math/MathML" xmlns:tb="http://www.elsevier.com/xml/common/table/dtd" xmlns:sb="http://www.elsevier.com/xml/common/struct-bib/dtd" xmlns:ce="http://www.elsevier.com/xml/common/dtd" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:cals="http://www.elsevier.com/xml/common/cals/dtd"><mml:mrow><mml:mi>ß</mml:mi></mml:mrow></mml:math> Cephei star <mml:math altimg="si38.gif" overflow="scroll" xmlns:xocs="http://www.elsevier.com/xml/xocs/dtd" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.elsevier.com/xml/ja/dtd" xmlns:ja="http://www.elsevier.com/xml/ja/dtd" xmlns:mml="http://www.w3.org/1998/Math/MathML" xmlns:tb="http://www.elsevier.com/xml/common/table/dtd" xmlns:sb="http://www.elsevier.com/xml/common/struct-bib/dtd" xmlns:ce="http://www.elsevier.com/xml/common/dtd" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:cals="http://www.elsevier.com/xml/common/cals/dtd"><mml:mrow><mml:mi>s</mml:mi></mml:mrow></mml:math> Cas: Atmospheric characterization and line-profile variability';
        $this->assertSame('<nowiki>' . $text_math . '</nowiki>',wikify_external_text($text_mml));
    }

    public function testURLInTitle(): void {
        $text = '[http://dfadfd]';
        $this->assertSame($text, sanitize_string($text));
    }

    public function testTrailingPeriods1(): void {
        $this->assertSame('In the X.Y.', wikify_external_text('In the X.Y.'));
    }
    public function testTrailingPeriods2(): void {
        $this->assertSame('In the X. Y.', wikify_external_text('In the X. Y.'));
    }
    public function testTrailingPeriods3(): void {
        $this->assertSame('In the X. And Y', wikify_external_text('In the X. and Y.'));
    }
    public function testTrailingPeriods4(): void {
        $this->assertSame('A.B.C.', wikify_external_text('A.B.C.'));
     }
    public function testTrailingPeriods5(): void {
        $this->assertSame('Blahy', wikify_external_text('Blahy.'));
    }
    public function testTrailingPeriods6(): void {
        $this->assertSame('Blahy', wikify_external_text('Blahy............'));
    }
    public function testTrailingPeriods7(): void {
        $this->assertSame('Blahy.', wikify_external_text('Blahy....... ....'));
    }
    public function testTrailingPeriods8(): void {
        $this->assertSame('Dfadsfds Hoser......', wikify_external_text('Dfadsfds Hoser..... . .'));
    }

    public function testTrailingNbsp(): void {
        $this->assertSame('Dfadsfds', wikify_external_text('Dfadsfds&nbsp;'));
        $this->assertSame('Dfadsfds', wikify_external_text('Dfadsfds&amp;nbsp;'));
    }

    public function testItal(): void {
        $this->assertSame("''A''", wikify_external_text('<italics>A</italics>'));
    }

    public function testEm(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame("'''A'''", wikify_external_text('<Emphasis Type="Bold">A</Emphasis>'));
    }

    public function testEm2(): void {
        $this->assertSame("''A''", wikify_external_text('<em>A</em>'));
    }

    public function testEmIt(): void {
        $this->assertSame("''A''", wikify_external_text('<Emphasis Type="Italic">A</Emphasis>'));
    }

    public function testDollarMath(): void {
        $this->assertSame("<math>Abs</math>", wikify_external_text('$$Abs$$'));
    }

    public function testBrackets(): void {
        $this->assertSame("ABC",remove_brackets('{}{}{A[][][][][]B()(){}[]][][[][C][][][[()()'));
    }

    public function testStrong(): void {
        $this->assertSame('A new genus and two new species of Apomecynini, a new species of Desmiphorini, and new records in Lamiinae and Disteniidae (Coleoptera)', wikify_external_text('. <strong>A new genus and two new species of Apomecynini, a new species of Desmiphorini, and new records in Lamiinae and Disteniidae (Coleoptera)</strong>.'));
    }

    // The X prevents first character caps
    public function testCapitalization_lots_more(): void { // Double check that constants are in order when we sort - paranoid
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame('X BJPsych', title_capitalization(title_case('X Bjpsych'), true));
        $this->assertSame('X delle', title_capitalization(title_case('X delle'), true));
        $this->assertSame('X IEEE', title_capitalization(title_case('X Ieee'), true));
        $this->assertSame('X NASA', title_capitalization(title_case('X Nasa'), true));
        $this->assertSame('X over', title_capitalization(title_case('X Over'), true));
        $this->assertSame('X und', title_capitalization(title_case('X Und'), true));
        $this->assertSame('X within', title_capitalization(title_case('X Within'), true));
        $this->assertSame('X AAPS', title_capitalization(title_case('X Aaps'), true));
        $this->assertSame('X BJOG', title_capitalization(title_case('X Bjog'), true));
    }
    public function testCapitalization_lots_more2(): void {
        $this->assertSame('X e-Neuroforum', title_capitalization(title_case('X E-Neuroforum'), true));
        $this->assertSame('X eGEMs', title_capitalization(title_case('X Egems'), true));
        $this->assertSame('X eNeuro', title_capitalization(title_case('X Eneuro'), true));
        $this->assertSame('X eVolo', title_capitalization(title_case('X EVolo'), true));
        $this->assertSame('X HannahArendt.net', title_capitalization(title_case('X hannaharendt.net'), true));
        $this->assertSame('X iJournal', title_capitalization(title_case('X IJournal'), true));
        $this->assertSame('X JABS : Journal of Applied Biological Sciences', title_capitalization(title_case('X Jabs : Journal of Applied Biological Sciences'), true));
    }
    public function testCapitalization_lots_more3(): void {
        $this->assertSame('X La Trobe', title_capitalization(title_case('X La Trobe'), true));
        $this->assertSame('X MERIP', title_capitalization(title_case('X Merip'), true));
        $this->assertSame('X mSystems', title_capitalization(title_case('X MSystems'), true));
        $this->assertSame('X PhytoKeys', title_capitalization(title_case('X Phytokeys'), true));
        $this->assertSame('X PNAS', title_capitalization(title_case('X Pnas'), true));
    }
    public function testCapitalization_lots_more4(): void {
        $this->assertSame('X Srp Arh Celok Lek', title_capitalization(title_case('X SRP Arh Celok Lek'), true));
        $this->assertSame('X Time Out London', title_capitalization(title_case('X Time out London'), true));
        $this->assertSame('X z/Journal', title_capitalization(title_case('X Z/journal'), true));
        $this->assertSame('X ZooKeys', title_capitalization(title_case('X zookeys'), true));
    }

    public function testCapitalization_lots_more5(): void {
        $this->assertSame('Www', title_case('www'));
        $this->assertSame('www.', title_case('www.'));
        $this->assertSame('http://', title_case('http://'));
        $this->assertSame('abx www-x', title_case('abx www-x'));
        $this->assertSame('Hello There', title_case('hello there'));
    }

    public function testCapitalization_lots_more6(): void {
        $this->assertSame('The DOS is Faster', title_capitalization('The DOS is Faster', true));
        $this->assertSame('The dos is Faster', title_capitalization('The dos is Faster', true));
        $this->assertSame('The DoS is Faster', title_capitalization('The DoS is Faster', true));
        $this->assertSame('The dOs is Faster', title_capitalization('The dOs is Faster', true));
        $this->assertSame('The DOS Dos dOs is dos Faster', title_capitalization('The DOS Dos dOs is dos Faster', true));
        $this->assertSame('The DOS Dos dOs is dos Faster', title_capitalization('The DOS Dos dOs is dos Faster', false));
        $this->assertSame('DOS', title_capitalization('DOS', true));
        $this->assertSame('dos', title_capitalization('dos', true));
        $this->assertSame('DoS', title_capitalization('DoS', true));
        $this->assertSame('dOs', title_capitalization('dOs', true));
    }

    public function testCapitalization_lots_more7(): void {
        $this->assertSame('AIDS', title_capitalization('Aids', true));
        $this->assertSame('BioScience', title_capitalization('Bioscience', true));
        $this->assertSame('BioMedical Engineering OnLine', title_capitalization('Biomedical Engineering Online', true));
    }

    public function testDOIWorks(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertFalse(doi_works(''));
        $this->assertFalse(doi_active(''));
        $this->assertFalse(doi_works('   '));
        $this->assertFalse(doi_active('      '));
    }

    public function testDOIWorks2(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertTrue(doi_works('10.1594/PANGAEA.667386'));
        $this->assertFalse(doi_active('10.1594/PANGAEA.667386'));
    }

    public function testDOIWorks3a(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertTrue(doi_works('10.1107/S2056989021000116'));
    }

    public function testDOIWorks3b(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertTrue(doi_works('10.1126/scidip.ado5059'));
    }

    public function testDOIWorks4(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertFalse(doi_works('10.1126/scidip.CITATION_BOT_PLACEHOLDER.ado5059'));
        $this->assertFalse(doi_works('10.1007/springerreference.ado5059'));
        $this->assertFalse(doi_works('10.1126scidip.ado5059'));
        $this->assertFalse(doi_works('123456789/32131423'));
        $this->assertFalse(doi_works('dfasdfsd/CITATION_BOT_PLACEHOLDER'));
        $this->assertFalse(doi_works('/dfadsfasf'));
    }

    public function testHDLworks(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertFalse(hdl_works('10.1126fwerw4w4r2342314'));
        $this->assertFalse(hdl_works('10.1007/CITATION_BOT_PLACEHOLDER.ado5059'));
        $this->assertFalse(hdl_works('10.112/springerreference.ado5059'));
    }

    public function testConference(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertFalse(conference_doi('10.1007/978-3-662-44777_ch3'));
    }
    
    public function testThrottle(): void { // Just runs over the code and basically does nothing
        for ($x = 0; $x <= 25; $x++) {
            $this->assertNull(throttle());
        }
    }

    public function testDoubleHopDOI(): void { // Just runs over the code and basically does nothing
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertTrue(doi_works('10.25300/MISQ/2014/38.2.08'));
        $this->assertTrue(doi_works('10.5479/si.00963801.5-301.449'));
    }

    public function testHeaderProblemDOI(): void { // Just runs over the code and basically does nothing
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertTrue(doi_works('10.3403/bsiso10294')); // this one seems to be fussy
    }

    public function testHostIsGoneDOIbasic(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        foreach (NULL_DOI_LIST as $doi => $value) {
            $this->assertSame(trim($doi), $doi);
            $this->assertTrue($value);
            $this->assertSame(safe_preg_replace('~\s~u', '', $doi), $doi);
        }
        foreach (NULL_DOI_ANNOYING as $doi => $value) {
            $this->assertSame(trim($doi), $doi);
            $this->assertTrue($value);
            $this->assertSame(safe_preg_replace('~\s~u', '', $doi), $doi);
        }
        foreach (NULL_DOI_BUT_GOOD as $doi => $value) {
            $this->assertSame(trim($doi), $doi);
            $this->assertTrue($value);
            $this->assertTrue(strpos($doi, '10.') === 0); // No HDLs allowed
            $this->assertSame(safe_preg_replace('~\s~u', '', $doi), $doi);
        }
        $changes = "";
        foreach (NULL_DOI_LIST as $doi => $value) {
            if (isset(NULL_DOI_BUT_GOOD[$doi])) {
                $changes = $changes . "In Both: " . $doi . "                ";
            }
        }
        foreach (NULL_DOI_LIST as $doi => $value) {
            foreach (NULL_DOI_STARTS_BAD as $bad_start) {
                if (stripos($doi, $bad_start) === 0) {
                    $changes = $changes . "Both in bad and bad start: " . $doi . "                ";
                }
            }
        }
        foreach (NULL_DOI_BUT_GOOD as $doi => $value) {
            foreach (NULL_DOI_STARTS_BAD as $bad_start) {
                if (stripos($doi, $bad_start) === 0) {
                    $changes = $changes . "Both in good and bad start: " . $doi . "                ";
                }
            }
        }
        foreach (NULL_DOI_ANNOYING as $doi => $value) {
            if (!isset(NULL_DOI_LIST[$doi])) {
                $changes = $changes . "Needs to also be in main null list: " . $doi . "           ";
            }
        }
        $this->assertSame("", $changes);  
    }

    public function testHostIsGoneDOILoop(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $changes = "";
        $this->assertSame("", $changes);
        $null_list = array_keys(NULL_DOI_LIST);
        shuffle($null_list); // Avoid doing similar ones next to each other
        foreach ($null_list as $doi) {
            if (isset(NULL_DOI_ANNOYING[$doi])) {
                $works = false;
            } else {
                $works = doi_works($doi);
            }
            if ($works === true) {
                $changes = $changes . "Flagged as good: " . $doi . "             ";
            } elseif ($works === null) { // These nulls are permanent and get mapped to false
                $changes = $changes . "Flagged as null: " . $doi . "             ";
            }
        }
        $this->assertSame("", $changes);
    }

    public function testHostIsGoneDOIHosts(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $changes = "";
        // Deal with super common ones that flood the list and are bulk covered with NULL_DOI_STARTS_BAD
        $this->assertSame("", $changes);
        foreach (BAD_DOI_EXAMPLES as $doi) {
            $works = doi_works($doi);
            if ($works === null) {
                $changes = $changes . "NULL_DOI_STARTS_BAD Flagged as null: " . $doi . "             ";
            } elseif ($works === true) {
                $changes = $changes . "NULL_DOI_STARTS_BAD Flagged as good: " . $doi . "             ";
            }
        }
        $this->assertSame("", $changes);
    }

    public function testBankruptDOICompany(): void {
        $text = "{{cite journal|doi=10.2277/JUNK_INVALID}}";
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('doi'));
    }

    public function testRestorItalicsRegex1(): void {
        $text = "{{cite journal|doi=10.7717/peerj.7240 }}";
        $template = $this->process_citation($text);
        $this->assertSame("''Ngwevu intloko'': A new early sauropodomorph dinosaur from the Lower Jurassic Elliot Formation of South Africa and comments on cranial ontogeny in ''Massospondylus carinatus''", $template->get2('title'));
    }

    public function testRestorItalicsRegex2(): void {
        $text = "{{cite journal|doi=10.7717/peerj.4224 }}";
        $template = $this->process_citation($text);
        $this->assertSame("A revised cranial description of ''Massospondylus carinatus'' Owen (Dinosauria: Sauropodomorpha) based on computed tomographic scans and a review of cranial characters for basal Sauropodomorpha", $template->get2('title'));
    }

    public function testVariousEncodes2(): void {
        $test="ショッピング";
        $decoded = smart_decode($test, 'UTF-8','');
        $this->assertSame($test, $decoded);
    }

    public function testVariousEncodes3(): void {
        $test="ショッピング";
        $decoded=smart_decode($test, "iso-8859-11",'');
        $this->assertSame('ใทใงใใใณใฐ', $decoded); // Clearly random junk
    }

    public function testVariousEncodes1(): void {
        $input  = "\xe3\x82\xb7\xe3\x83\xa7\xe3\x83\x83\xe3\x83\x94\xe3\x83\xb3\xe3\x82\xb0";
        $sample = 'ショッピング';
        $decoded = convert_to_utf8($input);
        $this->assertSame($sample, $decoded);
    }

    public function testVariousEncodes4(): void {
        $sample = "2xSP!#$%&'()*+,-./3x0123456789:;<=>?4x@ABCDEFGHIJKLMNO5xPQRSTUVWXYZ[\]^_6x`abcdefghijklmno7xpqrstuvwxyz{|}~8x9xAxNBSP¡¢£¤¥¦§¨©ª«¬SHY®¯Bx°±²³´µ¶·¸¹º»¼½¾¿CxÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏDxÐÑÒÓÔÕÖ×ØÙÚÛÜÝÞßExàáâãäåæçèéêëìíîïFxðñòóôõö÷øùúûüýþÿ";
        $urlencoded_iso_8859_1 = '2xSP%21%23%24%25%26%27%28%29%2A%2B%2C-.%2F3x0123456789%3A%3B%3C%3D%3E%3F4x%40ABCDEFGHIJKLMNO5xPQRSTUVWXYZ%5B%5C%5D%5E_6x%60abcdefghijklmno7xpqrstuvwxyz%7B%7C%7D%7E8x9xAxNBSP%A1%A2%A3%A4%A5%A6%A7%A8%A9%AA%AB%ACSHY%AE%AFBx%B0%B1%B2%B3%B4%B5%B6%B7%B8%B9%BA%BB%BC%BD%BE%BFCx%C0%C1%C2%C3%C4%C5%C6%C7%C8%C9%CA%CB%CC%CD%CE%CFDx%D0%D1%D2%D3%D4%D5%D6%D7%D8%D9%DA%DB%DC%DD%DE%DFEx%E0%E1%E2%E3%E4%E5%E6%E7%E8%E9%EA%EB%EC%ED%EE%EFFx%F0%F1%F2%F3%F4%F5%F6%F7%F8%F9%FA%FB%FC%FD%FE%FF';
        $decoded = mb_convert_encoding(urldecode($urlencoded_iso_8859_1), "UTF-8", "iso-8859-1");
        $this->assertSame($sample, $decoded);
    }

    public function testVariousEncodes5(): void {
        $test="2xSP!#$%&'()*+,-./3x0123456789:;<=>?4x@ABCDEFGHIJKLMNO5xPQRSTUVWXYZ[\]^_6x`abcdefghijklmno7xpqrstuvwxyz{|}~8x9xAxNBSP¡¢£€20AC¥Š0160§š0161©ª«¬SHY®¯Bx°±²³Ž017Dµ¶·ž017E¹º»Œ0152œ0153Ÿ0178¿CxÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏDxÐÑÒÓÔÕÖ×ØÙÚÛÜÝÞßExàáâãäåæçèéêëìíîïFxðñòóôõö÷øùúûüýþÿ";
        $string_utf8_urlencoded = "2xSP%21%23%24%25%26%27%28%29%2A%2B%2C-.%2F3x0123456789%3A%3B%3C%3D%3E%3F4x%40ABCDEFGHIJKLMNO5xPQRSTUVWXYZ%5B%5C%5D%5E_6x%60abcdefghijklmno7xpqrstuvwxyz%7B%7C%7D%7E8x9xAxNBSP%C2%A1%C2%A2%C2%A3%E2%82%AC20AC%C2%A5%C5%A00160%C2%A7%C5%A10161%C2%A9%C2%AA%C2%AB%C2%ACSHY%C2%AE%C2%AFBx%C2%B0%C2%B1%C2%B2%C2%B3%C5%BD017D%C2%B5%C2%B6%C2%B7%C5%BE017E%C2%B9%C2%BA%C2%BB%C5%920152%C5%930153%C5%B80178%C2%BFCx%C3%80%C3%81%C3%82%C3%83%C3%84%C3%85%C3%86%C3%87%C3%88%C3%89%C3%8A%C3%8B%C3%8C%C3%8D%C3%8E%C3%8FDx%C3%90%C3%91%C3%92%C3%93%C3%94%C3%95%C3%96%C3%97%C3%98%C3%99%C3%9A%C3%9B%C3%9C%C3%9D%C3%9E%C3%9FEx%C3%A0%C3%A1%C3%A2%C3%A3%C3%A4%C3%A5%C3%A6%C3%A7%C3%A8%C3%A9%C3%AA%C3%AB%C3%AC%C3%AD%C3%AE%C3%AFFx%C3%B0%C3%B1%C3%B2%C3%B3%C3%B4%C3%B5%C3%B6%C3%B7%C3%B8%C3%B9%C3%BA%C3%BB%C3%BC%C3%BD%C3%BE%C3%BF";
        $string_utf8 = urldecode($string_utf8_urlencoded);
        $string_windows1252_urlencoded = "2xSP%21%23%24%25%26%27%28%29%2A%2B%2C-.%2F3x0123456789%3A%3B%3C%3D%3E%3F4x%40ABCDEFGHIJKLMNO5xPQRSTUVWXYZ%5B%5C%5D%5E_6x%60abcdefghijklmno7xpqrstuvwxyz%7B%7C%7D%7E8x9xAxNBSP%A1%A2%A3%8020AC%A5%8A0160%A7%9A0161%A9%AA%AB%ACSHY%AE%AFBx%B0%B1%B2%B3%8E017D%B5%B6%B7%9E017E%B9%BA%BB%8C0152%9C0153%9F0178%BFCx%C0%C1%C2%C3%C4%C5%C6%C7%C8%C9%CA%CB%CC%CD%CE%CFDx%D0%D1%D2%D3%D4%D5%D6%D7%D8%D9%DA%DB%DC%DD%DE%DFEx%E0%E1%E2%E3%E4%E5%E6%E7%E8%E9%EA%EB%EC%ED%EE%EFFx%F0%F1%F2%F3%F4%F5%F6%F7%F8%F9%FA%FB%FC%FD%FE%FF";
        $string_windows1252 = urldecode($string_windows1252_urlencoded);
        $string_windows1252_converted_to_utf8 = mb_convert_encoding($string_windows1252, "UTF-8", "WINDOWS-1252");
        $string_utf8_coverted_to_windows1252 = mb_convert_encoding($string_utf8, "WINDOWS-1252", "UTF-8");

        $this->assertSame($test, $string_utf8);
        $this->assertSame($test, $string_windows1252_converted_to_utf8);
        $this->assertSame($string_utf8_coverted_to_windows1252, $string_windows1252);
    }

    public function testVariousEncodes6(): void {
        $test="ア イ ウ エ オ カ キ ク ケ コ ガ ギ グ ゲ ゴ サ シ ス セ ソ ザ ジ ズ ゼ ゾ タ チ ツ テ ト ダ ヂ ヅ デ ド ナ ニ ヌ ネ ノ ハ ヒ フ ヘ ホ バ ビ ブ ベ ボ パ ピ プ ペ ポ マ ミ ム メ モ ヤ ユ ヨ ラ リ ル レ ロ ワ ヰ ヱ ヲ";
        $this->assertSame($test, convert_to_utf8(mb_convert_encoding($test, "ISO-2022-JP", "UTF-8")));
    }

    public function testVariousEncodes7(): void {
        $test="说文解字简称说文是由东汉经学家文字学家许慎编著的语文工具书著作是中国最早的系统分析汉字字形和考究字源的语文辞书也是世界上最早的字典之说文解字内容共十五卷其中前十四卷为文字解说字头以小篆书写此书编著时首次对“六书做出了具体的解释逐字解释字体来源第十五卷为叙目记录汉字的产生发展功用结构等方面的问题以及作者创作的目的说文解字是最早的按部首编排的汉语字典全书共分个部首收字9353另有“重文即异体字个共10516字说文解字原书作于汉和帝永元十二年100到安帝建光元年（121年）宋太宗雍熙三年年宋太宗命徐铉句中正葛湍王惟恭等同校说文解字分成上下共三十卷奉敕雕版流布后代研究说文多以此版为蓝本如清代段玉裁注释本即用此版说文为底稿而加以注释[1]说文解字是科学文字学和文献语言学的奠基之作在中国语言学史上有重要的地位历代对于说文解字都有许多学者研究清朝时研究最为兴盛段玉裁的说文解字注朱骏声的说文通训定声桂馥的说文解字义证王筠的说文释例说文句读尤备推崇四人也获尊称为说文四大家";
        $this->assertSame($test, convert_to_utf8(mb_convert_encoding($test, "EUC-CN", "UTF-8")));
    }

    public function testVariousEncodes8(): void {
        $test="당신 이름이 무엇입니까 이름이 키얀인 어린 소년을 만나보세요. 그러나 그는 다른 많은 이름도 가지고 있습니다. 당신은 얼마나 많은 이름을 가지고 있습니까?";
        $this->assertSame($test, convert_to_utf8(mb_convert_encoding($test, "EUC-KR", "UTF-8")));
    }

    public function testVariousEncodes9(): void {
        $this->assertSame('', smart_decode('test', 'utf-8-sig', 'http'));
        $this->assertSame('', smart_decode('test', 'x-user-defined', 'http'));
    }

    public function testRomanNumbers(): void {
        $this->assertSame('MMCCCXXXI', numberToRomanRepresentation(2331));
    }

    public function testForDOIGettingFixed(): void { // These do not work, but it would be nice if they did.    They all have checks in code
        // https://search.informit.org/doi/10.3316/aeipt.20772 and such
        $this->assertFalse(doi_works('10.3316/informit.550258516430914'));
        $this->assertFalse(doi_works('10.3316/ielapa.347150294724689'));
        $this->assertFalse(doi_works('10.3316/agispt.19930546'));
        $this->assertFalse(doi_works('10.3316/aeipt.207729'));
        // DO's, not DOIs
        $this->assertFalse(doi_works('10.1002/was.00020423'));
    }

    public function testRestoreItalics1(): void {
        $this->assertSame('Buitreraptor gonzalezorum', restore_italics('Buitreraptor gonzalezorum'));
    }

    public function testRestoreItalics2(): void {
        $this->assertSame("Ca ''Buitreraptor gonzalezorum'' X", restore_italics('CaBuitreraptor gonzalezorumX'));
    }

    public function testRestoreItalics3(): void {
        $this->assertSame('Buitreraptor gonzalezorumXXXX', restore_italics('Buitreraptor gonzalezorumXXXX'));
    }

    public function testRestoreItalics4(): void {
        $this->assertSame("To a ''Tyrannotitan chubutensis''-", restore_italics("To aTyrannotitan chubutensis-"));
    }

    public function testcheck_memory_usage(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        check_memory_usage('testcheck_memory_usage');
        $this->assertTrue(true);
    }

    public function testCleanDates1(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $this->assertSame('', clean_dates(''));
    }

    public function testCleanDates2(): void {
        $this->assertSame('April–May 1995', clean_dates('April-May 1995'));
    }

    public function testCleanDates3(): void {
        $this->assertSame('December 7, 2023', clean_dates('December 7 2023'));
    }

    public function testCleanDates4(): void {
        $this->assertSame('8 December 2022', clean_dates('8 December 2022.'));
    } 

    public function testCleanDates5(): void {
        $this->assertSame('8 December 2022', clean_dates('08 December 2022'));
    }

    public function testCleanDates6(): void {
        $this->assertSame('November 2, 1981', clean_dates('Monday, November 2, 1981'));
    }

    public function testOur_mb_substr_replace(): void {
        $in  = "ショッピング";
        $out = "ショッXング";
        $this->assertSame($out, mb_substr_replace($in, 'X', 3, 1));
        $this->assertNotSame($out, substr_replace($in, 'X', 3, 1));
    }

    public function testGoogleBookNormalize0(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $removed_redundant = 0;
        $removed_parts = '';
        $gid = [];
        $gid[1] = 'm8W2AgAAQBAJ';
        $url_in  = 'https://books.google.com/books?id=m8W2AgAAQBAJ&bsq=1234';
        $url_out = 'https://books.google.com/books?id=m8W2AgAAQBAJ&q=1234';
        normalize_google_books($url_in, $removed_redundant, $removed_parts, $gid); // Reference passed
        $this->assertSame($url_out, $url_in);
    }

    public function testGoogleBookNormalize1(): void {
        $removed_redundant = 0;
        $removed_parts = '';
        $gid = [];
        $gid[1] = 'm8W2AgAAQBAJ';
        $url_in  = 'https://books.google.com/books?id=m8W2AgAAQBAJ&bsq=1234&q=abc';
        $url_out = 'https://books.google.com/books?id=m8W2AgAAQBAJ&q=abc';
        normalize_google_books($url_in, $removed_redundant, $removed_parts, $gid); // Reference passed
        $this->assertSame($url_out, $url_in);
    }

    public function testGoogleBookNormalize2(): void {
        $removed_redundant = 0;
        $removed_parts = '';
        $gid = [];
        $gid[1] = 'm8W2AgAAQBAJ';
        $url_in  = 'https://books.google.com/books?id=m8W2AgAAQBAJ#PPA333,M1';
        $url_out = 'https://books.google.com/books?id=m8W2AgAAQBAJ&pg=PA333';
        normalize_google_books($url_in, $removed_redundant, $removed_parts, $gid); // Reference passed
        $this->assertSame($url_out, $url_in);
    }
 
    public function testGoogleBookNormalize3(): void {
        $removed_redundant = 0;
        $removed_parts = '';
        $gid = [];
        $gid[1] = 'm8W2AgAAQBAJ';
        $url_in  = 'https://books.google.com/books?id=m8W2AgAAQBAJ#PP333,M1';
        $url_out = 'https://books.google.com/books?id=m8W2AgAAQBAJ&pg=PP333';
        normalize_google_books($url_in, $removed_redundant, $removed_parts, $gid); // Reference passed
        $this->assertSame($url_out, $url_in);
    }
 
    public function testGoogleBookNormalize4(): void {
        $removed_redundant = 0;
        $removed_parts = '';
        $gid = [];
        $gid[1] = 'm8W2AgAAQBAJ';
        $url_in  = 'https://books.google.com/books?id=m8W2AgAAQBAJ#PPT333,M1';
        $url_out = 'https://books.google.com/books?id=m8W2AgAAQBAJ&pg=PT333';
        normalize_google_books($url_in, $removed_redundant, $removed_parts, $gid); // Reference passed
        $this->assertSame($url_out, $url_in);
    }

    public function testGoogleBookNormalize5(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $removed_redundant = 0;
        $removed_parts = '';
        $gid = [];
        $gid[1] = 'm8W2AgAAQBAJ';
        $url_in  = 'https://books.google.com/books?id=m8W2AgAAQBAJ#PR333,M1';
        $url_out = 'https://books.google.com/books?id=m8W2AgAAQBAJ&pg=PR333';
        normalize_google_books($url_in, $removed_redundant, $removed_parts, $gid); // Reference passed
        $this->assertSame($url_out, $url_in);
    }

    public function testRubbishISBN(): void {
        $junk = "12342";
        $this->assertSame($junk, addISBNdashes($junk));
    }

    public function testFixupGoogle(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame('https://www.google.com/search?x=cows', simplify_google_search('https://www.google.com/search?x=cows'));
        $this->assertSame('https://www.google.com/search/?q=cows', simplify_google_search('https://www.google.com/search/?q=cows'));
    }
    
    public function testTitles10(): void {
        $junk = "(ab)(cd) (ef)";
        $this->assertSame('(ab)(cd) (Ef)', title_capitalization($junk, true));
    }

    public function testTitles11(): void {
        $junk = "ac's";
        $this->assertSame("Ac's", title_capitalization($junk, true));
    }

    public function testTitles12(): void {
        $junk = "This Des Doggy Des";
        $this->assertSame("This des Doggy Des", title_capitalization($junk, true));
    }

    public function testTitles13(): void {
        $junk = "Now and Then";
        $this->assertSame("Now and Then", title_capitalization($junk, true));
    }

    public function testTitleRoman1(): void {
        $junk = 'A Part xvi: Dogs';
        $this->assertSame('A Part XVI: Dogs', title_capitalization($junk, true));
    }

    public function testTitleRoman2(): void {
        $junk = 'A Part xvi Dogs';
        $this->assertSame('A Part XVI Dogs', title_capitalization($junk, true));
    }

    public function testTitleRoman3(): void {
        $junk = 'Dogs Vii';
        $this->assertSame('Dogs VII', title_capitalization($junk, true));
    }

    public function testTitleRoman4(): void {
        $junk = 'Vii: Dogs';
        $this->assertSame('VII: Dogs', title_capitalization($junk, true));
    }

    public function testTitleProc(): void {
        $junk = 'This is Proceedings a Dog';
        $this->assertSame('This is Proceedings A Dog', title_capitalization($junk, true));
    }

    public function testTitleVar(): void {
        $junk = 'This is var. abc';
        $this->assertSame('This is var. abc', title_capitalization($junk, true));
    }

    public function testTitlePPM(): void {
        $junk = 'This PPM Code';
        $this->assertSame('This ppm Code', title_capitalization($junk, true));
    }

    public function testStringEquSer(): void {
        $s1 = 'advances in anatomy embryology and cell biology';
        $s2 = 'adv anat embryol cell biol';
        $this->assertTrue(str_equivalent($s1, $s2));
    }
    
}
