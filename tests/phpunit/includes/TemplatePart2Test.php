<?php
declare(strict_types=1);

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../../testBaseClass.php';

final class TemplatePart2Test extends testBaseClass {

    public function testTidyWork1a(): void {
        $text = "{{citation|work=|website=X}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertNull($template->get2('work'));
    }

    public function testTidyWork1b(): void {
        $text = "{{cite web|work=}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertNull($template->get2('work'));
    }

    public function testTidyWork1c(): void {
        $text = "{{cite journal|work=}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame( "{{cite journal|journal=}}", $template->parsed_text());
    }

    public function testTidyChapterTitleSeries(): void {
        $text = "{{cite book|chapter=X|title=X}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertNull($template->get2('title'));
    }

    public function testTidyChapterTitleSeries2(): void {
        $text = "{{cite journal|chapter=X|title=X}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertNull($template->get2('chapter'));
    }

    public function testTidyChapterNotJournal(): void {
        $text = "{{cite web|chapter=X|title=Y|url=Z}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame('cite book', $template->wikiname());
    }

    public function testTidyChapterNotJournalSpecial1093_1(): void {
        $text = "{{cite web|chapter=X|title=Y|url=Z|doi=10.1093/1}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame('cite book', $template->wikiname());
    }

    public function testTidyChapterNotJournalSpecial1093_2(): void {
        $text = "{{Cite web|chapter=X|title=Y|url=Z|doi=10.1093/1}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame('cite book', $template->wikiname());
    }

    public function testPrintWarning(): void { // We don't check, but it does cover code
        $text = "{{cite journal|page=3-4}}";
        $template = $this->process_citation($text);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testRemovePg(): void {
        $text = "{{cite journal|page=pg. 343}}";
        $template = $this->process_citation($text);
        $this->assertSame('343', $template->get2('page'));
    }

    public function testRemovePg_2(): void {
        $text = "{{cite journal|pages=pg. 343–349}}";
        $template = $this->process_citation($text);
        $this->assertSame('343–349', $template->get2('pages'));
    }

    public function testTidyReuters1(): void {
        $text = "{{cite web|newspaper=Reuters}}";
        $template = $this->process_citation($text);
        $this->assertSame('Reuters', $template->get2('agency'));
        $this->assertNull($template->get2('newspaper'));
    }

    public function testTidyReuters2(): void {
        $text = "{{cite news|newspaper=Reuters}}";
        $template = $this->process_citation($text);
        $this->assertSame('Reuters', $template->get2('newspaper'));
        $this->assertNull($template->get2('work'));
    }

    public function testTidyReuters3(): void {
        $text = "{{cite web|newspaper=Reuters|url=reuters.com}}";
        $template = $this->process_citation($text);
        $this->assertSame('Reuters', $template->get2('work'));
        $this->assertNull($template->get2('newspaper'));
    }

    public function testTidyReuters4(): void {
        $text = "{{cite news|newspaper=Reuters|url=reuters.com}}";
        $template = $this->process_citation($text);
        $this->assertSame('Reuters', $template->get2('newspaper'));
        $this->assertNull($template->get2('work'));
    }

    public function testETCTidy(): void {
        $text = "{{cite web|pages=342 etc}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('pages');
        $this->assertSame('342 etc.', $template->get2('pages'));
    }

    public function testZOOKEYStidy(): void {
        $text = "{{cite journal|journal=[[zOOkeys]]|volume=333|issue=22}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('volume');
        $this->assertNull($template->get2('volume'));
        $this->assertSame('22', $template->get2('issue'));
    }

    public function testTidyViaStuff1(): void {
        $text = "{{cite journal|via=A jstor|jstor=X}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('via');
        $this->assertNull($template->get2('via'));
    }

    public function testTidyViaStuff2(): void {
        $text = "{{cite journal|via=google books etc|isbn=X}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('via');
        $this->assertNull($template->get2('via'));
    }

    public function testTidyViaStuff3(): void {
        $text = "{{cite journal|via=questia etc|isbn=X}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('via');
        $this->assertNull($template->get2('via'));
    }

    public function testTidyViaStuff4(): void {
        $text = "{{cite journal|via=library}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('via');
        $this->assertNull($template->get2('via'));
    }

    public function testConversionOfURL1(): void {
        $text = "{{cite journal|url=https://mathscinet.ams.org/mathscinet-getitem?mr=0012343|chapterurl=https://mathscinet.ams.org/mathscinet-getitem?mr=0012343}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertSame('0012343', $template->get2('mr'));
    }

    public function testConversionOfURL3(): void {
        $text = "{{cite web|url=http://worldcat.org/issn/1234-1234}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertSame('1234-1234', $template->get2('issn'));
        $this->assertNull($template->get2('url'));
    }

    public function testConversionOfURL4(): void {
        $text = "{{cite web|url=http://lccn.loc.gov/1234}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertSame('1234', $template->get2('lccn'));
        $this->assertNull($template->get2('url'));
    }

    public function testConversionOfURL5(): void {
        $text = "{{cite web|url=http://openlibrary.org/books/OL/1234W}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertSame('1234W', $template->get2('ol'));
        $this->assertNull($template->get2('url'));
    }

    public function testTidyJSTOR(): void {
        $text = "{{cite web|jstor=https://www.jstor.org/stable/123456}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('jstor');
        $this->assertSame('123456', $template->get2('jstor'));
        $this->assertSame('cite journal', $template->wikiname());
    }

    public function testAuthor2(): void {
        $text = "{{cite web|title=X}}";
        $template = $this->make_citation($text);
        $template->set('author1', 'Joe Jones Translated by John Smith');
        $template->tidy_parameter('author1');
        $this->assertSame('Joe Jones', $template->get2('author1'));
        $this->assertSame('Translated by John Smith', $template->get2('others'));
    }

    public function testAuthorsAndAppend3(): void {
        $text = "{{cite web|title=X}}";
        $template = $this->make_citation($text);
        $template->set('others', 'Kim Bill'); // Must use set
        $template->set('author1', 'Joe Jones Translated by John Smith');
        $template->tidy_parameter('author1');
        $this->assertSame('Joe Jones', $template->get2('author1'));
        $this->assertSame('Kim Bill; Translated by John Smith', $template->get2('others'));
    }

    public function testAuthorsAndAppend4(): void {
        $text = "{{cite web|title=X}}";
        $template = $this->make_citation($text);
        $template->set('others', 'CITATION_BOT_PLACEHOLDER_COMMENT');
        $template->set('author1', 'Joe Jones Translated by John Smith');
        $this->assertSame('CITATION_BOT_PLACEHOLDER_COMMENT', $template->get2('others'));
        $this->assertSame('Joe Jones Translated by John Smith', $template->get2('author1'));
    }

    public function testVolumeIssueDemixing21(): void {
        $text = '{{cite journal|issue = volume 12|doi=10.0001/Rubbish_bot_failure_test}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('12', $prepared->get2('volume'));
        $this->assertNull($prepared->get2('issue'));
    }

    public function testVolumeIssueDemixing22(): void {
        $text = '{{cite journal|issue = volume 12XX|volume=12XX|doi=10.0001/Rubbish_bot_failure_test}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('12XX', $prepared->get2('volume'));
        $this->assertNull($prepared->get2('issue'));
    }

    public function testNewspaperJournal111(): void {
        $text = "{{cite journal|website=xyz}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('newspaper', 'news.bbc.co.uk'));
        $this->assertNull($template->get2('website'));
        $this->assertSame('News.BBC.co.uk', $template->get2('work'));
        $this->assertNull($template->get2('journal'));
        $this->assertSame('cite journal', $template->wikiname());       // Unchanged
        // This could all change in final_tidy()
    }

    public function testMoreEtAl2(): void {
        $text = "{{cite web|authors=Joe et al.}}";
        $template = $this->make_citation($text);
        $this->assertSame('Joe et al.', $template->get2('authors'));
        $template->handle_et_al();
        $this->assertSame('Joe', $template->get2('author'));
        $this->assertNull($template->get2('authors'));
        $this->assertSame('etal', $template->get2('display-authors'));
    }

    public function testTidyWork2(): void {
        $text = "{{cite magazine|work=}}";
        $template = $this->make_citation($text);
        $template->prepare();
        $this->assertSame( "{{cite magazine|magazine=}}", $template->parsed_text());
    }

    public function testTidyChapterTitleSeries3(): void {
        $text = "{{cite journal|title=XYZ}}";
        $template = $this->make_citation($text);
        $template->add_if_new('series', 'XYZ');
        $this->assertSame('XYZ', $template->get2('title'));
        $this->assertNull($template->get2('series'));

        $text = "{{cite journal|journal=XYZ}}";
        $template = $this->make_citation($text);
        $template->add_if_new('series', 'XYZ');
        $this->assertSame('XYZ', $template->get2('journal'));
        $this->assertNull($template->get2('series'));
    }

    public function testTidyChapterTitleSeries4(): void {
        $text = "{{cite book|journal=X}}";
        $template = $this->make_citation($text);
        $template->add_if_new('series', 'XYZ');
        $template->tidy_parameter('series');
        $this->assertSame('XYZ', $template->get2('series'));
        $this->assertSame('X', $template->get2('journal'));
    }

    public function testTidyChapterTitleSeries4_2(): void {
        $text = "{{cite book|title=X}}";
        $template = $this->make_citation($text);
        $template->add_if_new('series', 'XYZ');
        $template->tidy_parameter('series');
        $this->assertSame('XYZ', $template->get2('series'));
        $this->assertSame('X', $template->get2('title'));
    }

    public function testAllZeroesTidy(): void {
        $text = "{{cite web|issue=000000000}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('issue');
        $this->assertNull($template->get2('issue'));
    }

    public function testAddDupNewsPaper(): void {
        $text = "{{cite web|work=I exist and submit}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('newspaper', 'bbc sports'));
        $this->assertSame('I exist and submit', $template->get2('work'));
        $this->assertNull($template->get2('newspaper'));
    }

    public function testAddBogusBibcode(): void {
        $text = "{{cite web|bibcode=Exists}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('bibcode', 'xyz'));
        $this->assertSame('Exists', $template->get2('bibcode'));
    }

    public function testAddBogusBibcode_2(): void {
        $text = "{{cite web}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('bibcode', 'Z'));
        $this->assertSame('Z..................', $template->get2('bibcode'));
    }

    public function testvalidate_and_add1(): void {
        $text = "{{cite web}}";
        $template = $this->make_citation($text);
        $template->validate_and_add('author1', 'George @Hashtags Billy@hotmail.com', 'Sam @Hashtags Billy@hotmail.com', '', false);
        $this->assertSame("{{cite web}}", $template->parsed_text());
    }

    public function testvalidate_and_add2(): void {
        $text = "{{cite web}}";
        $template = $this->make_citation($text);
        $template->validate_and_add('author1', 'George @Hashtags', '', '', false);
        $this->assertSame("{{cite web| author1=George }}", $template->parsed_text());
    }

    public function testvalidate_and_add3(): void {
        $text = "{{cite web}}";
        $template = $this->make_citation($text);
        $template->validate_and_add('author1', 'George Billy@hotmail.com', 'Sam @Hashtag', '', false);
        $this->assertSame("{{cite web| last1=George | first1=Sam }}", $template->parsed_text());
    }

    public function testvalidate_and_add4(): void {
        $text = "{{cite web}}";
        $template = $this->make_citation($text);
        $template->validate_and_add('author1', 'com', 'Sam', '', false);
        $this->assertSame("{{cite web| last1=Com | first1=Sam }}", $template->parsed_text());
    }

    public function testvalidate_and_add5(): void {
        $text = "{{cite web}}";
        $template = $this->make_citation($text);
        $template->validate_and_add('author1', '', 'George @Hashtags', '', false);
        $this->assertSame("{{cite web| author1=George }}", $template->parsed_text());
    }

    public function testDateYearRedundancyEtc1(): void {
        $text = "{{cite web|year=2004|date=}}";
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertSame("2004", $template->get2('year'));
        $this->assertNull($template->get2('date')); // Not an empty string anymore
    }

    public function testDateYearRedundancyEtc2(): void {
        $text = "{{cite web|date=November 2004|year=}}";
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertSame("November 2004", $template->get2('date'));
        $this->assertNull($template->get2('year')); // Not an empty string anymore
    }

    public function testDateYearRedundancyEtc3(): void {
        $text = "{{cite web|date=November 2004|year=Octorberish 2004}}";
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertSame("November 2004", $template->get2('date'));
        $this->assertNull($template->get2('year'));
    }

    public function testDateYearRedundancyEtc4(): void {
        $text = "{{cite web|date=|year=Sometimes around 2004}}";
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertSame("Sometimes around 2004", $template->get2('date'));
        $this->assertNull($template->get2('year'));
    }

    public function testOddThing(): void {
        $text = '{{journal=capitalization is Good}}';
        $template = $this->process_citation($text);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testTranslator(): void {
        $text = '{{cite web}}';
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('translator1', 'John'));
        $text = '{{cite web|translator=Existing bad data}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('translator1', 'John'));
        $text = '{{cite web}}';
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('translator1', 'John'));
        $this->assertTrue($template->add_if_new('translator2', 'Jill'));
        $this->assertFalse($template->add_if_new('translator2', 'Rob'));  // Add same one again
    }

    public function testAddDuplicateBibcode(): void {
        $text = '{{cite web|url=https://ui.adsabs.harvard.edu/abs/1924MNRAS..84..308E/abstract|bibcode=1924MNRAS..84..308E}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertNotNull($template->get2('url'));
    }

    public function testNonUSAPubMedMore(): void {
        $text = '{{cite web|url=https://europepmc.org/abstract/med/342432/pdf}}';
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertNotNull($template->get2('url'));
        $this->assertSame('342432', $template->get2('pmid'));
        $this->assertSame('cite journal', $template->wikiname());
    }

    public function testNonUSAPubMedMore2(): void {
        $text = '{{cite web|url=https://europepmc.org/scanned?pageindex=1234&articles=pmc43871}}';
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertNull($template->get2('url'));
        $this->assertSame('43871', $template->get2('pmc'));
        $this->assertSame('cite journal', $template->wikiname());
    }

    public function testNonUSAPubMedMore3(): void {
        $text = '{{cite web|url=https://pubmedcentralcanada.ca/pmcc/articles/PMC324123/pdf}}';
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertNull($template->get2('url'));
        $this->assertSame('324123', $template->get2('pmc'));
        $this->assertSame('cite journal', $template->wikiname());
    }

    public function testRubbishArxiv(): void { // Something we do not understand, other than where it is from
        $text = '{{cite web|url=http://arxiv.org/X/abs/3XXX41222342343242}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertNull($template->get2('arxiv'));
        $this->assertNull($template->get2('eprint'));
        $this->assertSame('http://arxiv.org/X/abs/3XXX41222342343242', $template->get2('url'));
        $this->assertSame('cite web', $template->wikiname());
    }

    public function testArchiveAsURL(): void {
        $text = '{{Cite web | url=https://web.archive.org/web/20111030210210/http://www.cap.ca/en/}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url()); // false because we add no parameters or such
        $this->assertSame('http://www.cap.ca/en/', $template->get2('url'));
        $this->assertSame('https://web.archive.org/web/20111030210210/http://www.cap.ca/en/', $template->get2('archive-url'));
        $this->assertSame('30 October 2011', $template->get2('archive-date'));
    }

    public function testCAPSGoingAway1(): void {
        $text = '{{Cite journal | doi=10.1016/j.ifacol.2017.08.010|title=THIS IS A VERY BAD ALL CAPS TITLE|journal=THIS IS A VERY BAD ALL CAPS JOURNAL}}';
        $template = $this->process_citation($text);
        $this->assertSame('Contingency Analysis Post-Processing with Advanced Computing and Visualization', $template->get2('title'));
        $this->assertSame('IFAC-PapersOnLine', $template->get2('journal'));
    }

    public function testCAPSGoingAway2(): void {
        $text = '{{Cite book | doi=10.1109/PESGM.2015.7285996|title=THIS IS A VERY BAD ALL CAPS TITLE|chapter=THIS IS A VERY BAD ALL CAPS CHAPTER}}';
        $template = $this->process_citation($text);
        $this->assertSame('Sub-second state estimation implementation and its evaluation with real data', $template->get2('chapter'));
        $this->assertSame('2015 IEEE Power & Energy Society General Meeting', $template->get2('title'));
    }

    public function testCAPSGoingAway3(): void {
        $text = '{{Cite book | doi=10.1109/PESGM.2015.7285996|title=Same|chapter=Same}}';
        $template = $this->process_citation($text);
        $this->assertSame('Sub-second state estimation implementation and its evaluation with real data', $template->get2('chapter'));
        $this->assertSame('2015 IEEE Power & Energy Society General Meeting', $template->get2('title'));
    }

    public function testCAPSGoingAway4(): void {
        $text = '{{Cite book | doi=10.1109/PESGM.2015.7285996|title=Same|chapter=Same|journal=Same}}';
        $template = $this->process_citation($text);
        $this->assertSame('Sub-second state estimation implementation and its evaluation with real data', $template->get2('chapter'));
        $this->assertSame('2015 IEEE Power & Energy Society General Meeting', $template->get2('title'));
        $this->assertSame('Same', $template->get2('journal'));
    }

    public function testCAPSGoingAway5(): void {
        $text = '{{Cite book | jstor=TEST_DATA_IGNORE |title=Same|chapter=Same|journal=Same}}';
        $template = $this->process_citation($text);
        $this->assertSame('Same', $template->get2('journal'));
        $this->assertSame('Same', $template->get2('title'));
        $this->assertNull($template->get2('chapter'));
    }

    public function testAddDuplicateArchive(): void {
        $text = '{{Cite book | archiveurl=XXX}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('archive-url', 'YYY'));
        $this->assertSame($text, $template->parsed_text());
    }

    public function testReplaceBadDOI(): void {
        $text = '{{Cite journal | doi=10.0001/Rubbish_bot_failure_test|doi-broken-date=1999|pmid=<!-- -->|pmc=<!-- -->}}';
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('doi', '10.1063/1.2263373'));
        $this->assertSame('10.1063/1.2263373', $template->get2('doi'));
    }

    public function testDropBadDOI(): void {
        $text = '{{Cite journal | doi=10.1063/1.2263373|chapter-url=http://dx.doi.org/10.0001/Rubbish_bot_failure_test|pmid=<!-- -->|pmc=<!-- -->}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1063/1.2263373', $template->get2('doi'));
        $this->assertNotNull($template->get2('chapter-url'));
    }

    public function testEmptyJunk(): void {
        $text = '{{Cite journal| dsfasfdfasdfsdafsdafd = | issue = | issue = 33}}';
        $template = $this->process_citation($text);
        $this->assertSame('33', $template->get2('issue'));
        $this->assertNull($template->get2('dsfasfdfasdfsdafsdafd'));
        $this->assertSame('{{Cite journal| issue = 33}}', $template->parsed_text());
    }

    public function testFloaters2(): void {
        $text = '{{Cite journal | url=http://www.apple.com/ |access date 12 December 1990 }}';
        $template = $this->process_citation($text);
        $this->assertSame('12 December 1990', $template->get2('access-date'));
    }

    public function testFloaters3(): void {
        $text = '{{Cite journal | url=http://www.apple.com/ |access date 12 December 1990 |accessdate=}}';
        $template = $this->process_citation($text);
        $this->assertSame('12 December 1990', $template->get2('access-date'));
    }

    public function testFloaters4(): void {
        $text = '{{Cite journal | url=http://www.apple.com/ |access date 12 December 1990 | accessdate = 3 May 1999 }}';
        $template = $this->process_citation($text);
        $this->assertSame('3 May 1999', $template->get2('accessdate'));
        $this->assertNull($template->get2('access-date'));
    }

    public function testFloaters5(): void {
        $text = '{{Cite journal | issue 33 }}';
        $template = $this->process_citation($text);
        $this->assertSame('33', $template->get2('issue'));
    }

    public function testFloaters6(): void {
        $text = '{{Cite journal | issue 33 |issue=}}';
        $template = $this->process_citation($text);
        $this->assertSame('33', $template->get2('issue'));
    }

    public function testFloaters7(): void {
        $text = '{{Cite journal | issue 33 | issue=22 }}';
        $template = $this->process_citation($text);
        $this->assertSame('22', $template->get2('issue'));
    }

    public function testFloaters10(): void {
        $text = '{{Cite journal | url=http://cnn.com/ | https://www.archive.org/web/20160313143910/http://ww38.grlmobile.com/}}';
        $template = $this->process_citation($text);
        $this->assertSame('https://www.archive.org/web/20160313143910/http://ww38.grlmobile.com/', $template->get2('archive-url'));
    }

    public function testSuppressWarnings(): void {
        $text = '{{Cite journal |doi=((10.1063/1.478352 )) |pmid=<!-- -->|pmc=<!-- -->}}';
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('doi-broken-date'));
        $this->assertSame('10.1063/1.478352', $template->get2('doi'));
        $this->assertSame('((10.1063/1.478352 ))', $template->get3('doi'));
        $this->assertNotNull($template->get2('journal'));
    }

    public function testAddEditorFalse(): void {
        $text = '{{Cite journal |display-editors = 5 }}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('display-editors', '5'));
    }

    public function testPMCEmbargo1(): void {
        $text = '{{Cite journal|pmc-embargo-date=January 22, 2020}}';
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('pmc-embargo-date'));
    }

    public function testPMCEmbargo2(): void {
        $text = '{{Cite journal|pmc-embargo-date=January 22, 2090}}';
        $template = $this->process_citation($text);
        $this->assertSame('January 22, 2090', $template->get2('pmc-embargo-date'));
    }

    public function testPMCEmbargo3(): void {
        $text = '{{Cite journal|pmc-embargo-date=}}';
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('pmc-embargo-date'));
    }

    public function testPMCEmbargo4(): void {
        $text = '{{Cite journal}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('pmc-embargo-date', 'November 15, 1990'));
        $this->assertFalse($template->add_if_new('pmc-embargo-date', 'November 15, 2010'));
        $this->assertFalse($template->add_if_new('pmc-embargo-date', 'November 15, 3010'));
        $this->assertTrue($template->add_if_new('pmc-embargo-date', 'November 15, 2090'));
        $this->assertFalse($template->add_if_new('pmc-embargo-date', 'November 15, 2080'));
    }

    public function testOnlineFirst(): void {
        $text = '{{Cite journal|volume=Online First}}';
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('volume'));

        $text = '{{Cite journal|issue=Online First}}';
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('issue'));
    }

    public function testIDconvert1(): void {
        $text = '{{Cite journal | id = {{ASIN|3333|country=eu}} }}';
        $template = $this->process_citation($text);
        $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
        $this->assertSame($text, $template->parsed_text());
    }

    public function testIDconvert2(): void {
        $text = '{{Cite journal | id = {{JSTOR|33333|issn=xxxx}} }}';
        $template = $this->process_citation($text);
        $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
        $this->assertSame($text, $template->parsed_text());
    }

    public function testIDconvert3(): void {
        $text = '{{Cite journal | id = {{ol|44444|author=xxxx}} }}';
        $template = $this->process_citation($text);
        $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
        $this->assertSame($text, $template->parsed_text());
    }

    public function testIDconvert4(): void {
        $text = '{{Cite journal | id = {{inist|44444}} }}';
        $template = $this->process_citation($text);
        $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
        $this->assertSame($text, $template->parsed_text());
    }

    public function testIDconvert5(): void {
        $text = '{{Cite journal | id = {{oclc|02268454}} {{ol|1234}}      }}';
        $template = $this->process_citation($text);
        $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
        $this->assertSame('02268454', $template->get2('oclc'));
        $this->assertSame('1234', $template->get2('ol'));
        $this->assertNull($template->get2('id'));
    }

    public function testIDconvert6(): void {
        $text = '{{Cite journal | id = {{jfm|02268454}} {{lccn|1234}} {{mr|222}} }}';
        $template = $this->process_citation($text);
        $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
        $this->assertSame('02268454', $template->get2('jfm'));
        $this->assertSame('1234', $template->get2('lccn'));
        $this->assertSame('222', $template->get2('mr'));
        $this->assertNull($template->get2('id'));
    }

    public function testIDconvert6b(): void {
        $text = '{{Cite journal | id = {{mr|id=222}} }}';
        $template = $this->process_citation($text);
        $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
        $this->assertSame('222', $template->get2('mr'));
        $this->assertNull($template->get2('id'));
    }

    public function testIDconvert7(): void {
        $text = '{{Cite journal | id = {{osti|02268454}} {{ssrn|1234}} }}';
        $template = $this->process_citation($text);
        $template = $this->make_citation($template->parsed_text()); // Turn sub-templates into text
        $this->assertSame('02268454', $template->get2('osti'));
        $this->assertSame('1234', $template->get2('ssrn'));
        $this->assertNull($template->get2('id'));
    }

    public function testIDconvert8(): void {
        $text = '{{Cite journal | id = {{ASIN|0226845494|country=eu}} }}';
        $template = $this->process_citation($text);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testIDconvert9(): void {
        $text = '{{Cite journal | id = {{inist|0226845494}} }}';
        $template = $this->process_citation($text);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testIDconvert10(): void {
        $text = '{{Cite journal|id = {{arxiv}}}}';
        $template = $this->process_citation($text);
        $this->assertSame('{{Cite journal}}', $template->parsed_text());
    }

    public function testIDconvert11(): void {
        $text = '{{cite journal|id={{isbn}} {{oclc}} {{jstor}} {{arxiv}} }}';
        $page = $this->process_page($text);
        $this->assertSame('{{cite journal|id={{isbn}} {{oclc}} {{JSTOR}} }}', $page->parsed_text());
    }

    public function testIDconvert12(): void {
        $text = '{{cite journal|id=<small></small>}}';
        $page = $this->process_page($text);
        $this->assertSame('{{cite journal}}', $page->parsed_text());
        $text = '{{cite journal|id=<small> </small>}}';
        $page = $this->process_page($text);
        $this->assertSame('{{cite journal}}', $page->parsed_text());
    }

    public function testIDconvert13(): void {
        $text = '{{cite journal|id=<small>{{MR|396410}}</small>}}';
        $page = $this->process_page($text);
        $this->assertSame('{{cite journal|mr=396410 }}', $page->parsed_text());
        $text = '{{cite journal|id=<small> </small>{{MR|396410}}}}';
        $page = $this->process_page($text);
        $this->assertSame('{{cite journal|mr=396410 }}', $page->parsed_text());
    }

    public function testIDconvert14(): void {
        $text = '{{cite journal|id=dafdasfd PMID 3432413 324214324324 }}';
        $template = $this->process_citation($text);
        $this->assertSame('3432413', $template->get2('pmid'));
    }

    public function testIDconvert15(): void {
        $text = '{{Cite journal | id = {{ProQuest|0226845494}} }}';
        $template = $this->process_citation($text);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testCAPS(): void {
        $text = '{{Cite journal | URL = }}';
        $template = $this->process_citation($text);
        $this->assertSame('', $template->get2('url'));
        $this->assertNull($template->get2('URL'));

        $text = '{{Cite journal | QWERTYUIOPASDFGHJKL = ABC}}';
        $template = $this->process_citation($text);
        $this->assertSame('ABC', $template->get2('qwertyuiopasdfghjkl'));
        $this->assertNull($template->get2('QWERTYUIOPASDFGHJKL'));
    }

    public function testDups(): void {
        $text = '{{Cite journal | DUPLICATE_URL = }}';
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('duplicate_url'));
        $this->assertNull($template->get2('DUPLICATE_URL'));
        $text = '{{Cite journal | duplicate_url = }}';
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('duplicate_url'));
        $this->assertNull($template->get2('DUPLICATE_URL'));
        $text = '{{Cite journal|id=|id=|id=|id=|id=|id=|id=|id=|id=|id=|id=|id=}}';
        $template = $this->process_citation($text);
        $this->assertSame('{{Cite journal|id=}}', $template->parsed_text());
    }

    public function testDropSep(): void {
        $text = '{{Cite journal | author_separator = }}';
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('author_separator'));
        $this->assertNull($template->get2('author-separator'));
        $text = '{{Cite journal | author-separator = Something}}';
        $template = $this->process_citation($text);
        $this->assertSame('Something', $template->get2('author-separator'));
    }

    public function testCommonMistakes(): void {
        $text = '{{Cite journal | origmonth = X}}';
        $template = $this->process_citation($text);
        $this->assertSame('X', $template->get2('month'));
        $this->assertNull($template->get2('origmonth'));
    }

    public function testRoman(): void { // No roman and then wrong roman
        $text = '{{Cite journal | title=On q-Functions and a certain Difference Operator|doi=10.1017/S0080456800002751|pmid=<!-- -->|pmc=<!-- -->}}';
        $template = $this->process_citation($text);
        $this->assertSame('Transactions of the Royal Society of Edinburgh', $template->get2('journal'));
        $text = '{{Cite journal | title=XXI.—On q-Functions and a certain Difference Operator|doi=10.1017/S0080456800002751|pmid=<!-- -->|pmc=<!-- -->}}';
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('journal'));
    }

    public function testRoman2(): void { // Bogus roman to start with
        $text = '{{Cite journal | title=Improved heat capacity estimator for path integral simulations. XXXI. part of many|doi=10.1063/1.1493184|pmid=<!-- -->|pmc=<!-- -->}}';
        $template = $this->process_citation($text);
        $this->assertSame('The Journal of Chemical Physics', $template->get2('journal'));
    }

    public function testRoman3(): void { // Bogus roman in the middle
        $text = "{{Cite journal | doi = 10.1016/0301-0104(82)87006-7|title=Are atoms intrinsic to molecular electronic wavefunctions? IIII. Analysis of FORS configurations|pmid=<!-- -->|pmc=<!-- -->}}";
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('journal'));
    }

    public function testRoman4(): void { // Right roman in the middle
        $text = "{{Cite journal | doi = 10.1016/0301-0104(82)87006-7|title=Are atoms intrinsic to molecular electronic wavefunctions? III. Analysis of FORS configurations|pmid=<!-- -->|pmc=<!-- -->}}";
        $template = $this->process_citation($text);
        $this->assertSame('Chemical Physics', $template->get2('journal'));
    }

    public function testAppendToComment(): void {
        $text = '{{cite web}}';
        $template = $this->make_citation($text);
        $template->set('id', 'CITATION_BOT_PLACEHOLDER_COMMENT');
        $template->append_to('id', 'joe');
        $this->assertSame('CITATION_BOT_PLACEHOLDER_COMMENT', $template->get2('id'));
    }

    public function testAppendEmpty(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $template->append_to('id', 'joe');
        $this->assertSame('joe', $template->get2('id'));
    }

    public function testAppendNull(): void {
        $text = '{{cite web}}';
        $template = $this->make_citation($text);
        $template->append_to('id', 'joe');
        $this->assertSame('joe', $template->get2('id'));
    }

    public function testAppendEmpty2(): void {
        $text = '{{cite web|last=|id=}}';
        $template = $this->make_citation($text);
        $template->append_to('id', 'joe');
        $this->assertSame('joe', $template->get2('id'));
    }

    public function testAppendAppend(): void {
        $text = '{{cite web|id=X}}';
        $template = $this->make_citation($text);
        $template->append_to('id', 'joe');
        $this->assertSame('Xjoe', $template->get2('id'));
    }

    public function testDateStyles(): void {
        $text = '{{cite web}}';
        $template = $this->make_citation($text);
        Template::$date_style = DateStyle::DATES_MDY;
        $template->add_if_new('date', '12-02-2019');
        $this->assertSame('February 12, 2019', $template->get2('date'));
        $template = $this->make_citation($text);
        Template::$date_style = DateStyle::DATES_DMY;
        $template->add_if_new('date', '12-02-2019');
        $this->assertSame('12 February 2019', $template->get2('date'));
        $template = $this->make_citation($text);
        Template::$date_style = DateStyle::DATES_WHATEVER;
        $template->add_if_new('date', '12-02-2019');
        $this->assertSame('12-02-2019', $template->get2('date'));
    }

    public function testFinalTidyComplicated(): void {
        $text = '{{cite book|series=A|journal=A}}';
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame('A', $template->get2('series'));
        $this->assertNull($template->get2('journal'));
    }

    public function testFinalTidyComplicated_2(): void {
        $text = '{{cite journal|series=A|journal=A}}';
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame('A', $template->get2('journal'));
        $this->assertNull($template->get2('series'));
    }

    public function testFindDOIBadAuthorAndFinalPage(): void { // Testing this code:        If fail, try again with fewer constraints...
        $text = '{{cite journal|last=THIS_IS_BOGUS_TEST_DATA|pages=4346–43563413241234|title=ISiCLE: A Quantum Chemistry Pipeline for Establishing in Silico Collision Cross Section Libraries|journal=Analytical Chemistry|volume=91|issue=7|year=2019|pmid=<!-- -->|pmc=<!-- -->}}';
        $template = $this->make_citation($text);
        get_doi_from_crossref($template);
        $this->assertSame('10.1021/acs.analchem.8b04567', $template->get2('doi'));
    }

    public function testCAPSParams(): void {
        $text = '{{cite journal|ARXIV=|TITLE=|LAST1=|JOURNAL=}}';
        $template = $this->process_citation($text);
        $this->assertSame(mb_strtolower($text), $template->parsed_text());
    }

    public function testTidyTAXON(): void {
        $text = '{{cite journal|journal=TAXON}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('journal');
        $this->assertSame('Taxon', $template->get2('journal'));
    }

    public function testRemoveBadPublisher(): void {
        $text = '{{cite journal|title=X|journal=X|issue=X|volume=X|pages=X-Y|pmc=1234123|publisher=u.s. National Library of medicine}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
    }

    public function testShortSpelling(): void {
        $text = '{{cite journal|lust=X}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('X', $template->get2('last'));

        $text = '{{cite journal|las=X}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('X', $template->get2('last'));

        $text = '{{cite journal|lis=X}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('X', $template->get2('lis'));
    }

    public function testSpellingLots(): void {
        $text = '{{cite journal|totle=X|journul=X|serias=X|auther=X|lust=X|cows=X|pigs=X|contrubution-url=X|controbution-urls=X|chupter-url=X|orl=X}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('{{cite journal|title=X|journal=X|series=X|author=X|last=X|cows=X|page=X|contribution-url=X|contribution-url=X|chapter-url=X|url=X}}', $template->parsed_text());
    }

    public function testAlmostSame(): void {
        $text = '{{cite journal|publisher=[[Abc|Abc]]|journal=Abc}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('[[abc|abc]]', mb_strtolower($template->get('journal'))); // Might "fix" Abc redirect to ABC
    }

    public function testRemoveAuthorLinks(): void {
        $text = '{{cite journal|author3-link=}}';
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('author3-link'));

        $text = '{{cite journal|author3-link=|author3=X}}';
        $template = $this->process_citation($text);
        $this->assertSame('', $template->get2('author3-link'));
    }

    public function testBogusArxivPub(): void {
        $text = '{{cite journal|publisher=arXiv|arxiv=1234}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));

        $text = '{{cite journal|publisher=arXiv}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('arXiv', $template->get2('publisher'));
    }

    public function testBloombergConvert(): void {
        $text = '{{cite journal|url=https://www.bloomberg.com/tosv2.html?vid=&uuid=367763b0-e798-11e9-9c67-c5e97d1f3156&url=L25ld3MvYXJ0aWNsZXMvMjAxOS0wNi0xMC9ob25nLWtvbmctdm93cy10by1wdXJzdWUtZXh0cmFkaXRpb24tYmlsbC1kZXNwaXRlLWh1Z2UtcHJvdGVzdA==}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.bloomberg.com/news/articles/2019-06-10/hong-kong-vows-to-pursue-extradition-bill-despite-huge-protest', $template->get2('url'));
    }

    public function testWork2Enc_1(): void {
        $text = '{{cite web|url=plato.stanford.edu|work=X}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('work');
        $this->assertNull($template->get2('work'));
        $this->assertSame('X', $template->get2('encyclopedia'));
    }

    public function testWork2Enc_2(): void {
        $text = '{{cite web|work=X from encyclopædia}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('work');
        $this->assertNull($template->get2('work'));
        $this->assertSame('X from encyclopædia', $template->get2('encyclopedia'));
    }

    public function testWork2Enc_3(): void {
        $text = '{{cite journal|url=plato.stanford.edu|work=X}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('work');
        $this->assertNull($template->get2('encyclopedia'));
        $this->assertSame('X', $template->get2('work'));

        $text = '{{cite journal|work=X from encyclopædia}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('work');
        $this->assertNull($template->get2('encyclopedia'));
        $this->assertSame('X from encyclopædia', $template->get2('work'));
    }

    public function testNonPubs(): void {
        $text = '{{cite book|work=citeseerx.ist.psu.edu}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('work');
        $this->assertNull($template->get2('work'));
        $this->assertSame('citeseerx.ist.psu.edu', $template->get2('title'));
    }

    public function testWork2Enc_4(): void {
        $text = '{{cite book|work=citeseerx.ist.psu.edu|title=Exists}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('work');
        $this->assertNull($template->get2('work'));
        $this->assertSame('Exists', $template->get2('title'));
    }

    public function testNullPages(): void {
        $text = '{{cite book|pages=null}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('pages');
        $this->assertNull($template->get2('pages'));
        $template->add_if_new('work', 'null');
        $template->add_if_new('pages', 'null');
        $template->add_if_new('author', 'null');
        $template->add_if_new('journal', 'null');
        $this->assertSame('{{cite book}}', $template->parsed_text());
    }

    public function testUpdateYear1(): void {
        $text = '{{cite journal|date=2000}}';
        $template = $this->make_citation($text);
        $template->add_if_new('year', (string) date('Y'), 'crossref');
        $this->assertSame((string) date('Y'), $template->get2('date'));
    }

    public function testUpdateYear2(): void {
        $text = '{{cite journal|year=ZYX}}';
        $template = $this->make_citation($text);
        $template->add_if_new('year', (string) date('Y'), 'crossref');
        $this->assertSame((string) date('Y'), $template->get('date') . $template->get('year'));
    }

    public function testUpdateYear3(): void {
        $text = '{{cite journal|year=ZYX}}';
        $template = $this->make_citation($text);
        $template->add_if_new('year', (string) date('Y'), 'crossref');
        $this->assertSame((string) date('Y'), $template->get('date') . $template->get('year'));
    }

    public function testUpdateYear4(): void {
        $text = '{{cite journal}}';
        $template = $this->make_citation($text);
        $template->add_if_new('year', (string) date('Y'), 'crossref');
        $this->assertSame((string) date('Y'), $template->get2('date'));
    }

    public function testUpdateYear5(): void {
        $text = '{{cite journal|year=1000}}';
        $template = $this->make_citation($text);
        $template->add_if_new('year', (string) ((int) date('Y') - 10), 'crossref');
        $this->assertSame('1000', $template->get2('year'));
    }

    public function testUpdateYear6(): void {
        $text = '{{cite journal|date=4000}}';
        $template = $this->make_citation($text);
        $template->add_if_new('year', (string) date('Y'), 'crossref');
        $this->assertSame('4000', $template->get2('date'));
    }

    public function testUpdateYear7(): void {
        $text = '{{cite journal|year=4000}}';
        $template = $this->make_citation($text);
        $template->add_if_new('year', (string) date('Y'), 'crossref');
        $this->assertSame('4000', $template->get2('year'));
    }

    public function testVerifyDOI1(): void {
        $text = '{{cite journal|doi=1111/j.1471-0528.1995.tb09132.x}}';
        $template = $this->make_citation($text);
        $template->verify_doi();
        $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
    }

    public function testVerifyDOI2(): void {
        $text = '{{cite journal|doi=.1111/j.1471-0528.1995.tb09132.x}}';
        $template = $this->make_citation($text);
        $template->verify_doi();
        $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
    }

    public function testVerifyDOI3(): void {
        $text = '{{cite journal|doi=0.1111/j.1471-0528.1995.tb09132.x}}';
        $template = $this->make_citation($text);
        $template->verify_doi();
        $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
    }

    public function testVerifyDOI4(): void {
        $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x.full}}';
        $template = $this->make_citation($text);
        $template->verify_doi();
        $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
    }

    public function testVerifyDOI5(): void {
        $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x#page_scan_tab_contents}}';
        $template = $this->make_citation($text);
        $template->verify_doi();
        $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
    }

    public function testVerifyDOI6(): void {
        $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x/abstract}}';
        $template = $this->make_citation($text);
        $template->verify_doi();
        $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
    }

    public function testVerifyDOI7(): void {
        $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.xv2}}';
        $template = $this->make_citation($text);
        $template->verify_doi();
        $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
    }

    public function testVerifyDOI8(): void {
        $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x;jsessionid}}';
        $template = $this->make_citation($text);
        $template->verify_doi();
        $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
    }

    public function testVerifyDOI9(): void {
        $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.}}';
        $template = $this->make_citation($text);
        $template->verify_doi();
        $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
    }

    public function testVerifyDOI10(): void {
        $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x;}}';
        $template = $this->make_citation($text);
        $template->verify_doi();
        $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
    }

    public function testVerifyDOI11(): void {
        $text = '{{cite journal|doi=10.1175/1525-7541(2003)004&lt;1147:TVGPCP&gt;2.0.CO;2}}';
        $template = $this->make_citation($text);
        $template->verify_doi();
        $this->assertSame('10.1175/1525-7541(2003)004<1147:TVGPCP>2.0.CO;2', $template->get2('doi'));
    }

    public function testVerifyDOI12(): void {
        $text = '{{cite journal|doi=0.5240/7B2F-ED76-31F6-8CFB-4DB9-M}}'; // Not in crossref, and no meta data in DX.DOI.ORG
        $template = $this->make_citation($text);
        $template->verify_doi();
        $this->assertSame('10.5240/7B2F-ED76-31F6-8CFB-4DB9-M', $template->get2('doi'));
    }

    public function testVerifyDOI13(): void {
        $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x</a>}}';
        $template = $this->make_citation($text);
        $template->verify_doi();
        $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $template->get2('doi'));
    }

    public function testOxfordTemplate(): void {
        $text = '{{cite web |last1=Courtney |first1=W. P. |last2=Hinings |first2=Jessica |title=Woodley, George (bap. 1786, d. 1846) |url=https://doi.org/10.1093/ref:odnb/29929 |website=Oxford Dictionary of National Biography |publisher=Oxford University Press |accessdate=12 September 2019|pmid=<!-- -->|pmc=<!-- -->}}';
        $template = $this->process_citation($text);
        $this->assertSame('cite odnb', $template->wikiname());
        $this->assertSame('Woodley, George (bap. 1786, d. 1846)', $template->get2('title'));
        $this->assertNotNull($template->get2('url'));
        $this->assertSame('10.1093/ref:odnb/29929', $template->get2('doi'));
        $this->assertNull($template->get2('publisher'));
    }

    /** Now with caps in wikiname */
    public function testOxfordTemplate2(): void {
        $text = '{{Cite web |last1=Courtney |first1=W. P. |last2=Hinings |first2=Jessica |title=Woodley, George (bap. 1786, d. 1846) |url=https://doi.org/10.1093/ref:odnb/29929 |website=Oxford Dictionary of National Biography |publisher=Oxford University Press |accessdate=12 September 2019|pmid=<!-- -->|pmc=<!-- -->}}';
        $template = $this->process_citation($text);
        $this->assertSame('cite odnb', $template->wikiname());
        $this->assertSame('Woodley, George (bap. 1786, d. 1846)', $template->get2('title'));
        $this->assertNotNull($template->get2('url'));
        $this->assertSame('10.1093/ref:odnb/29929', $template->get2('doi'));
        $this->assertNull($template->get2('publisher'));
    }

    public function testJournalIsBookSeries(): void {
        $text = '{{cite journal|journal=advances in enzymology and related areas of molecular biology}}';
        $template = $this->process_citation($text);
        $this->assertSame('cite book', $template->wikiname());
        $this->assertNull($template->get2('journal'));
        $this->assertSame('Advances in Enzymology and Related Areas of Molecular Biology', $template->get2('series'));
    }

    public function testNameStuff(): void {
        $text = '{{cite journal|author1=[[Robert Jay Charlson|Charlson]] |first1=R. J.}}';
        $template = $this->process_citation($text);
        $this->assertSame('Robert Jay Charlson', $template->get2('author1-link'));
        $this->assertSame('Charlson', $template->get2('last1'));
        $this->assertSame('R. J.', $template->get2('first1'));
        $this->assertNull($template->get2('author1'));
    }

    public function testSaveAccessType(): void {
        $text = '{{cite web|url=http://doi.org/10.1063/1.2833100 |url-access=Tested|pmid=<!-- -->|pmc=<!-- -->}}';
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertNull($template->get2('doi-access'));
        $this->assertNotNull($template->get2('url-access'));
    }

    public function testZooKeys2(): void {
        $text = '{{Cite journal|journal=[[Zookeys]]}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('{{Cite journal|journal=[[ZooKeys]]}}', $expanded->parsed_text());
    }

    public function testRedirectFixing(): void {
        $text = '{{cite journal|journal=[[Journal Of Polymer Science]]}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('{{cite journal|journal=[[Journal of Polymer Science]]}}', $template->parsed_text());
    }

    public function testRedirectFixing2(): void {
        $text = '{{cite journal|journal=[[Journal Of Polymer Science|"J Poly Sci"]]}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('[[Journal of Polymer Science|J Poly Sci]]', $template->get2('journal'));
    }

    public function testFixURLinLocation1(): void {
        $text = '{{cite journal|location=http://www.apple.com/indes.html}}';
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('location'));
        $this->assertSame('http://www.apple.com/indes.html', $template->get2('url'));
    }

    public function testFixURLinLocations2(): void {
        $text = '{{cite journal|location=http://www.apple.com/indes.html|url=http://www.apple.com/}}';
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('location'));
        $this->assertSame('http://www.apple.com/indes.html', $template->get2('url'));
    }

    public function testFixURLinLocations3(): void {
        $text = '{{cite journal|url=http://www.apple.com/indes.html|location=http://www.apple.com/}}';
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('location'));
        $this->assertSame('http://www.apple.com/indes.html', $template->get2('url'));
    }

    public function testFixURLinLocations4(): void {
        $text = '{{cite journal|url=http://www.apple.com/indes.html|location=http://www.ibm.com/}}';
        $template = $this->process_citation($text);
        $this->assertSame('http://www.ibm.com/', $template->get2('location'));
        $this->assertSame('http://www.apple.com/indes.html', $template->get2('url'));
    }

    public function testAddingJunk(): void {
        $text = '{{cite journal}}';
        $template = $this->make_citation($text);
        $template->add_if_new('title', 'n/A');
        $template->add_if_new('journal', 'Undefined');
        $this->assertSame($text, $template->parsed_text());
    }

    public function testCleanBritArchive(): void {
        $text = '{{Cite web|title=Register {{!}} British Newspaper Archive|url=https://www.britishnewspaperarchive.co.uk/account/register?countrykey=0&showgiftvoucherclaimingoptions=false&gift=false&nextpage=%2faccount%2flogin%3freturnurl%3d%252fviewer%252fbl%252f0003125%252f18850804%252f069%252f0004&rememberme=false&cookietracking=false&partnershipkey=0&newsletter=false&offers=false&registerreason=none&showsubscriptionoptions=false&showcouponmessaging=false&showfreetrialmessaging=false&showregisteroptions=false&showloginoptions=false&isonlyupgradeable=false|access-date=2022-02-17|website=www.britishnewspaperarchive.co.uk}}';
        $template = $this->process_citation($text);
        $this->assertSame('[[British Newspaper Archive]]', $template->get2('via'));
    }

    public function testHealthAffairs(): void {
        $text = '{{Cite web|url=https://www.healthaffairs.org/do/10.1377/hblog20180605.966625/full/|archiveurl=healthaffairs.org}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1377/forefront.20180605.966625', $template->get2('doi'));
        $this->assertSame('https://www.healthaffairs.org/do/10.1377/forefront.20180605.966625/full/', $template->get2('url'));
        $this->assertNull($template->get2('archiveurl'));
    }

    public function testAddExitingThings1(): void {
        $text = "{{Cite web}}";
        $expanded = $this->make_citation($text);
        $this->assertTrue($expanded->add_if_new('publisher', 'Springer Zone'));
        $this->assertFalse($expanded->add_if_new('publisher', 'Goodbye dead'));
    }

    public function testAddExitingThings2(): void {
        $text = "{{Cite web}}";
        $expanded = $this->make_citation($text);
        $this->assertTrue($expanded->add_if_new('location', 'Springer Zone'));
        $this->assertFalse($expanded->add_if_new('location', 'Goodbye dead'));
    }

    public function testAddExitingThings3(): void {
        $text = "{{Cite web}}";
        $expanded = $this->make_citation($text);
        $this->assertTrue($expanded->add_if_new('website', 'Springer Zone'));
        $this->assertFalse($expanded->add_if_new('website', 'Goodbye dead'));
    }

    public function testAllSortsOfBadData_1(): void {
        $text = "{{Cite journal|journal=arXiv|title=[No title found]|issue=null|volume=n/a|page=n/a|pages=null|pmc=1}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{Cite journal|journal=arXiv|title=[No title found]|volume=n/a|page=n/a|pmc=1}}', $expanded->parsed_text());
    }

    public function testAllSortsOfBadData_2(): void {
        $text = "{{Cite journal|journal=arXiv|title=[No TITLE found]|issue=null|volume=n/a|page=n/a|pages=null|pmc=1}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{Cite journal|journal=arXiv|title=[No TITLE found]|volume=n/a|page=n/a|pmc=1}}', $expanded->parsed_text());
    }

    public function testAllSortsOfBadData_3(): void {
        $text = "{{Cite journal|journal=arXiv: blah|title=arXiv|issue=null|volume=n/a|page=n/a|pages=null|eprint=x}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{Cite arXiv|title=arXiv|volume=n/a|page=n/a|eprint=x}}', $expanded->parsed_text());
    }

    public function testAllSortsOfBadData_4(): void {
        $text = "{{Cite journal|pages=n/a|pmc=1}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{Cite journal|pages=n/a|pmc=1}}', $expanded->parsed_text());
    }

    public function testTidyUpNA(): void {
        $text = "{{Cite journal|volume=n/a|issue=3}}";
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('volume'));
    }

    public function testTidyUpNA_2(): void {
        $text = "{{Cite journal|issue=n/a|volume=3}}";
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('issue'));
    }

    public function testTestExistingVerifiedData(): void {
        $text = "{{Cite journal|volume=((4))}}";
        $expanded = $this->make_citation($text);
        $this->assertFalse($expanded->set('volume', '3'));
    }

    public function testTidyWebsites_1(): void {
        $text = "{{Cite web|website=Undefined}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('website');
        $this->assertNull($expanded->get2('website'));
    }

    public function testTidyWebsites_2(): void {
        $text = "{{Cite web|website=latimes.com}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('website');
        $this->AssertSame('[[Los Angeles Times]]', $expanded->get2('website'));
    }

    public function testTidyWebsites_3(): void {
        $text = "{{Cite web|website=nytimes.com}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('website');
        $this->AssertSame('[[The New York Times]]', $expanded->get2('website'));
    }

    public function testTidyWebsites_4(): void {
        $text = "{{Cite web|website=The Times Digital Archive}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('website');
        $this->AssertSame('[[The Times]]', $expanded->get2('website'));
    }

    public function testTidyWebsites_5(): void {
        $text = "{{Cite web|website=electronic gaming monthly}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('website');
        $this->AssertSame('electronic gaming monthly', $expanded->get2('magazine'));
        $this->AssertSame('cite magazine', $expanded->wikiname());
    }

    public function testTidyWebsites_6(): void {
        $text = "{{Cite web|website=the economist}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('website');
        $this->AssertSame('the economist', $expanded->get2('newspaper'));
        $this->AssertSame('cite news', $expanded->wikiname());
    }

    public function testTidyWorkers_1(): void {
        $text = "{{Cite web|work=latimes.com}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('work');
        $this->AssertSame('[[Los Angeles Times]]', $expanded->get2('work'));
    }

    public function testTidyWorkers_2(): void {
        $text = "{{Cite web|work=nytimes.com}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('work');
        $this->AssertSame('[[The New York Times]]', $expanded->get2('work'));
    }

    public function testTidyWorkers_3(): void {
        $text = "{{Cite web|work=The Times Digital Archive}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('work');
        $this->AssertSame('[[The Times]]', $expanded->get2('work'));
    }

    public function testHasNoIssuesAtAll(): void {
        $text = "{{Cite journal|journal=oceanic linguistics special publications|issue=3|volume=5}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('issue');
        $this->AssertNull($expanded->get2('issue'));
        $this->AssertSame('5', $expanded->get2('volume'));
    }

    public function testHasNoIssuesAtAll_2(): void {
        $text = "{{Cite journal|journal=oceanic linguistics special publications|issue=3}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('issue');
        $this->AssertNull($expanded->get2('issue'));
        $this->AssertSame('3', $expanded->get2('volume'));
    }

    public function testHasNoIssuesAtAll_3(): void {
        $text = "{{Cite journal|journal=oceanic linguistics special publications}}";
        $expanded = $this->make_citation($text);
        $expanded->add_if_new('issue', '3');
        $this->AssertNull($expanded->get2('issue'));
        $this->AssertSame('3', $expanded->get2('volume'));
    }

    public function testHasNoIssuesWithSeries(): void {
        $text = "{{Cite book|series=novartis foundation symposia|issue=57|volume=57}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('issue');
        $this->AssertNull($expanded->get2('issue'));
        $this->AssertSame('57', $expanded->get2('volume'));
    }

    public function testTidyBadArchives_1(): void {
        $text = "{{Cite web|archive-url=https://www.britishnewspaperarchive.co.uk/account/register/dsfads}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('archive-url');
        $this->AssertNull($expanded->get2('archive-url'));
    }

    public function testTidyBadArchives_2(): void {
        $text = "{{Cite web|archive-url=https://meta.wikimedia.org/w/index.php?title=Special:UserLogin:DSFadsfds}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('archive-url');
        $this->AssertNull($expanded->get2('archive-url'));
    }

    public function testTidyBadISSN(): void {
        $text = "{{Cite web|issn=1111222X}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('issn');
        $this->AssertSame('1111-222X', $expanded->get2('issn'));
    }

    public function testTidyBadISSN_2(): void {
        $text = "{{Cite web|issn=1111-222x}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('issn');
        $this->AssertSame('1111-222X', $expanded->get2('issn'));
    }

    public function testTidyBadPeriodical(): void {
        $text = "{{Cite web|periodical=Undefined}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('periodical');
        $this->AssertNull($expanded->get2('periodical'));
    }

    public function testTidyBadPeriodical_2(): void {
        $text = "{{Cite web|periodical=medrxiv}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('periodical');
        $this->AssertNull($expanded->get2('periodical'));
        $this->AssertSame('medRxiv', $expanded->get2('work'));
        $this->AssertSame('cite web', $expanded->wikiname());
    }

    public function testTidyGoogleSupport(): void {
        $text = "{{Cite web|url=https://support.google.com/hello|publisher=Proudly made by the google plex}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('publisher');
        $this->AssertSame('Google Inc.', $expanded->get2('publisher'));
    }

    public function testTidyURLStatus_1(): void {
        $text = "{{cite web|url=http://x.com/|deadurl=sì}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('deadurl');
        $this->AssertSame('dead', $expanded->get2('url-status'));
        $this->AssertNull($expanded->get2('deadurl'));
    }

    public function testTidyURLStatus_2(): void {
        $text = "{{cite web|url=http://x.com/|deadurl=live}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('deadurl');
        $this->AssertSame('live', $expanded->get2('url-status'));
        $this->AssertNull($expanded->get2('deadurl'));
    }

    public function testTidyMonth3(): void {
        $text = "{{cite web|date=March 2000|month=march|day=11}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('month');
        $this->AssertNull($expanded->get2('day'));
        $this->AssertNull($expanded->get2('month'));
    }

    public function testCulturalAdvice(): void {
        $text = "{{cite web|chapter=Cultural Advice|chapter-url=http://anu.edu.au}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('chapter');
        $this->AssertNull($expanded->get2('chapter'));
        $this->AssertNull($expanded->get2('chapter-url'));
        $this->AssertSame('http://anu.edu.au', $expanded->get2('url'));
    }

    public function testChangeNameReject(): void {
        $text = "{{cite document|work=medrxiv}}";
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite journal');
        $this->AssertSame('cite document', $expanded->wikiname());
    }

    public function testCapsNewPublisher(): void {
        $text = "{{cite web}}";
        $expanded = $this->make_citation($text);
        $expanded->add_if_new('publisher', 'EXPANSIONISM');
        $this->AssertSame('Expansionism', $expanded->get2('publisher'));
    }

    public function testCapsNewPublisher_2(): void {
        $text = "{{cite web}}";
        $expanded = $this->make_citation($text);
        $expanded->add_if_new('publisher', 'EXPANSIONISM');
        $this->AssertSame('Expansionism', $expanded->get2('publisher'));
    }

    public function testAddAreManyThings(): void {
        $text = "{{cite news}}";
        $expanded = $this->make_citation($text);
        $expanded->add_if_new('newspaper', 'Rock Paper Shotgun');
        $this->AssertNull($expanded->get2('website'));
        $this->AssertSame('Rock Paper Shotgun', $expanded->get2('newspaper'));
    }

    public function testBloomWithVia(): void {
        $text = "{{cite news|via=bloomberg web services and such}}";
        $expanded = $this->make_citation($text);
        $expanded->add_if_new('newspaper', 'The Bloomberg is the way to go');
        $this->AssertNull($expanded->get2('via'));
    }

    public function testURLhiding(): void {
        $text = "{{cite journal|citeseerx=https://citeseerx.ist.psu.edu/viewdoc/summary?doi=10.1.1.88.5725}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('10.1.1.88.5725', $expanded->get2('citeseerx'));
    }

    public function testURLhiding2(): void {
        $text = "{{cite journal|citeseerx=https://apple.com/stuff}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame($text, $expanded->parsed_text());
    }

    public function testCleanArxivDOI1(): void {
        $text = "{{cite journal|doi=10.48550/arXiv.1234.56789|pmid=<!-- -->|pmc=<!-- -->}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('doi');
        $this->AssertNull($expanded->get2('doi'));
        $this->AssertSame('1234.56789', $expanded->get2('eprint'));
    }

    public function testCleanArxivDOI2(): void {
        $text = "{{cite journal|doi=10.48550/arXiv.1234.56789|eprint=1234.56789|pmid=<!-- -->|pmc=<!-- -->}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('doi');
        $this->AssertNull($expanded->get2('doi'));
        $this->AssertSame('1234.56789', $expanded->get2('eprint'));
    }

    public function testCleanArxivDOI3(): void {
        $text = "{{cite journal|doi=10.48550/arXiv.1234.56789|arxiv=1234.56789|pmid=<!-- -->|pmc=<!-- -->}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('doi');
        $this->AssertNull($expanded->get2('doi'));
        $this->AssertSame('1234.56789', $expanded->get2('arxiv'));
    }

    public function testAddCodeIfThisFails(): void { // Add more oxford code, if these start to work
        $this->AssertFalse(doi_works('10.1093/acref/9780199208951.013.q-author-00005-00000991')); // https://www.oxfordreference.com/view/10.1093/acref/9780199208951.001.0001/q-author-00005-00000991
        $this->AssertFalse(doi_works('10.1093/oao/9781884446054.013.8000020158')); // https://www.oxfordartonline.com/groveart/view/10.1093/gao/9781884446054.001.0001/oao-9781884446054-e-8000020158
    }

    public function testLotsOfZeros(): void {
        $text = "{{cite journal|volume=0000000000000|issue=00000000000}}";
        $expanded = $this->process_citation($text);
        $this->AssertNull($expanded->get2('volume'));
        $this->AssertNull($expanded->get2('issue'));
    }

    public function testWorkToMag(): void {
        $text = "{{cite journal|work=The New Yorker}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('The New Yorker', $expanded->get2('magazine'));
        $this->AssertNull($expanded->get2('work'));
        $this->AssertSame('cite magazine', $expanded->wikiname());
    }

    public function testWorkAgency(): void {
        $text = "{{cite news|work=Reuters|url=SomeThingElse.com}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('Reuters', $expanded->get2('agency'));
        $this->AssertNull($expanded->get2('work'));
    }

    public function testWorkofAmazon(): void {
        $text = "{{cite book|title=Has One|work=Amazon Inc}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('Has One', $expanded->get2('title'));
        $this->AssertNull($expanded->get2('publisher'));
    }

    public function testAbbrInPublisher1(): void {
        $text = "{{cite web|publisher=nytc|work=new york times}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('new york times', $expanded->get2('work'));
        $this->AssertNull($expanded->get2('publisher'));
    }

    public function testAbbrInPublisher2(): void {
        $text = "{{cite web|publisher=nyt|work=new york times}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('new york times', $expanded->get2('work'));
        $this->AssertNull($expanded->get2('publisher'));
    }

    public function testAbbrInPublisher3(): void {
        $text = "{{cite web|publisher=wpc|work=washington post}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('washington post', $expanded->get2('newspaper'));
        $this->AssertNull($expanded->get2('publisher'));
    }

    public function testDoiThatFailsWeird(): void {
        $text = "{{cite web|doi=10.1111/j.1475-4983.2002.32412432423423421314324234233242314234|year=2002|pmid=<!-- -->|pmc=<!-- -->}}"; // Special Papers in Palaeontology - they do not work
        $expanded = $this->process_citation($text);
        $this->AssertNull($expanded->get2('doi'));
    }

    public function testBadURLStatusSettings1(): void {
        $text = "{{cite web|url-status=sì|url=X}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('dead', $expanded->get2('url-status'));
    }

    public function testBadURLStatusSettings2(): void {
        $text = "{{cite web|url-status=no|url=X}}";
        $expanded = $this->process_citation($text);
        $this->AssertNull($expanded->get2('url-status'));
    }

    public function testBadURLStatusSettings3(): void {
        $text = "{{cite web|url-status=sì|url=X|archive-url=Y}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('dead', $expanded->get2('url-status'));
    }

    public function testBadURLStatusSettings4(): void {
        $text = "{{cite web|url-status=no|url=X|archive-url=Y}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('live', $expanded->get2('url-status'));
    }

    public function testBadURLStatusSettings5(): void {
        $text = "{{cite web|url-status=dead|url=X|archive-url=Y}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('dead', $expanded->get2('url-status'));
    }

    public function testBadURLStatusSettings6(): void {
        $text = "{{cite web|url-status=live|url=X|archive-url=Y}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('live', $expanded->get2('url-status'));
    }

    public function testCiteDocument_1(): void {
        $text = "{{cite document|url=x|website=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite web', $expanded->wikiname());
    }

    public function testCiteDocument_2(): void {
        $text = "{{cite document|url=x|magazine=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite magazine', $expanded->wikiname());
    }

    public function testCiteDocument_3(): void {
        $text = "{{cite document|url=x|encyclopedia=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite encyclopedia', $expanded->wikiname());
    }

    public function testCiteDocument_4(): void {
        $text = "{{cite document|url=x|journal=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite journal', $expanded->wikiname());
    }

    public function testCiteDocument_5(): void {
        $text = "{{cite document|url=x|website=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite web', $expanded->wikiname());
    }

    public function testCiteDocument_6(): void {
        $text = "{{cite document|url=x|magazine=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite magazine', $expanded->wikiname());
    }

    public function testCiteDocument_7(): void {
        $text = "{{cite document|url=x|encyclopedia=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite encyclopedia', $expanded->wikiname());
    }

    public function testCiteDocument_8(): void {
        $text = "{{cite document|url=x|journal=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite journal', $expanded->wikiname());
    }

    public function testCiteDocument_9(): void {
        $text = "{{cite document|url=x|pmc=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite journal', $expanded->wikiname());
    }

    public function testCiteDocument_10(): void {
        $text = "{{cite document|title=This|chapter=That}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite book', $expanded->wikiname());
    }

    public function testCitePaper_1(): void {
        $text = "{{cite paper|url=x|website=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite web', $expanded->wikiname());
    }

    public function testCitePaper_2(): void {
        $text = "{{cite paper|url=x|magazine=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite magazine', $expanded->wikiname());
    }

    public function testCitePaper_3(): void {
        $text = "{{cite paper|url=x|encyclopedia=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite encyclopedia', $expanded->wikiname());
    }

    public function testCitePaper_4(): void {
        $text = "{{cite paper|url=x|journal=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite journal', $expanded->wikiname());
    }

    public function testCitePaper_5(): void {
        $text = "{{Cite paper|url=x|website=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite web', $expanded->wikiname());
    }

    public function testCitePaper_6(): void {
        $text = "{{Cite paper|url=x|magazine=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite magazine', $expanded->wikiname());
    }

    public function testCitePaper_7(): void {
        $text = "{{Cite paper|url=x|encyclopedia=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite encyclopedia', $expanded->wikiname());
    }

    public function testCitePaper_8(): void {
        $text = "{{Cite paper|url=x|journal=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite journal', $expanded->wikiname());
    }

    public function testCitePaper_9(): void {
        $text = "{{Cite paper|url=x|pmc=x}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('cite journal', $expanded->wikiname());
    }

    public function testAreManyThings(): void {
        $text = "{{Cite web}}";
        $expanded = $this->make_citation($text);
        $expanded->add_if_new('newspaper', 'Ballotpedia');
        $this->AssertSame('Ballotpedia', $expanded->get2('website'));
    }

    public function testAreManyThings_2(): void {
        $text = "{{Cite news}}";
        $expanded = $this->make_citation($text);
        $expanded->add_if_new('newspaper', 'Ballotpedia');
        $this->AssertSame('Ballotpedia', $expanded->get2('newspaper'));
    }

    public function testAreMagazines(): void {
        $text = "{{Cite web|work=official xbox magazine}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('work');
        $this->AssertSame('official xbox magazine', $expanded->get2('magazine'));
        $this->AssertSame('cite magazine', $expanded->wikiname());
        $this->AssertNull($expanded->get2('work'));
    }

    public function testAreMagazines_2(): void {
        $text = "{{Cite web|work=official xbox magazine|doi=X}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('work');
        $this->AssertSame('official xbox magazine', $expanded->get2('work'));
        $this->AssertSame('cite web', $expanded->wikiname());
        $this->AssertNull($expanded->get2('magazine'));
    }

    public function testHDLneedsShorter1(): void {
        $text = "{{cite paper|hdl=20.1000/100?urlappend=%3Bseq=326%3Bownerid=13510798900390116-35}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('20.1000/100', $expanded->get2('hdl'));
    }

    public function testHDLneedsShorter2(): void {
        $text = "{{cite paper|hdl=20.1000/100?urlappend=%3Bseq=326%3Bownerid=13510798900390116-35urlappend}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('20.1000/100', $expanded->get2('hdl'));
    }

    public function testHDLneedsShorter3(): void {
        $text = "{{cite paper|hdl=2027/mdp.39015077587742?urlappend=}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('2027/mdp.39015077587742', $expanded->get2('hdl'));
    }

    public function testSillyURL(): void { // This get checks by string match, but not regex
        $text = '{{cite web|url=https://stuff.nih.gov}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertNotNull($template->get2('url'));
    }

    public function testSillyURL_2(): void {
        $text = '{{cite web|url=https://europepmc.org}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertNotNull($template->get2('url'));
    }

    public function testSillyURL_3(): void {
        $text = '{{cite web|url=https://pubmedcentralcanada.ca}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertNotNull($template->get2('url'));
    }

    public function testSillyURL_4(): void {
        $text = '{{cite web|url=https://citeseerx}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertNotNull($template->get2('url'));
    }

    public function testSillyURL_5(): void {
        $text = '{{cite web|url=https://worldcat.org}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertNotNull($template->get2('url'));
    }

    public function testSillyURL_6(): void {
        $text = '{{cite web|url=https://zbmath.org}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertNotNull($template->get2('url'));
    }

    public function testSillyURL_7(): void {
        $text = '{{cite web|url=https://www.osti.gov}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertNotNull($template->get2('url'));
    }

    public function testBaleOutDois(): void {
        $text = "{{cite arXiv|doi=10.1093/oi/authority/343214332|pmid=<!-- -->|pmc=<!-- -->}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('doi');
        $this->AssertSame('cite arxiv', $expanded->wikiname());
    }

    public function testFinalTidyThings1a(): void {
        $text = "{{Cite web|title=Stuff|chapter=More Stuff}}";
        $expanded = $this->make_citation($text);
        $expanded->final_tidy();
        $this->AssertSame('cite book', $expanded->wikiname());
    }

    public function testFinalTidyThings1b(): void {
        $text = "{{Cite web|title=Stuff|chapter=More Stuff|series=X|journal=Y}}";
        $expanded = $this->make_citation($text);
        $expanded->final_tidy();
        $this->AssertSame('cite book', $expanded->wikiname());
    }

    public function testFinalTidyThings1c(): void {
        $text = "{{Cite web|title=Stuff|chapter=More Stuff|journal=Y}}";
        $expanded = $this->make_citation($text);
        $expanded->final_tidy();
        $this->AssertSame('cite book', $expanded->wikiname());
    }

    public function testFinalTidyThings1d(): void {
        $text = "{{Cite web|title=Stuff|chapter=More Stuff|series=X}}";
        $expanded = $this->make_citation($text);
        $expanded->final_tidy();
        $this->AssertSame('cite book', $expanded->wikiname());
    }

    public function testFinalTidyThings1e(): void {
        $text = "{{cite web|title=Stuff|chapter=More Stuff}}";
        $expanded = $this->make_citation($text);
        $expanded->final_tidy();
        $this->AssertSame('cite book', $expanded->wikiname());
    }

    public function testFinalTidyThings1f(): void {
        $text = "{{cite web|title=Stuff|chapter=More Stuff|series=X|journal=Y}}";
        $expanded = $this->make_citation($text);
        $expanded->final_tidy();
        $this->AssertSame('cite book', $expanded->wikiname());
    }

    public function testFinalTidyThings1g(): void {
        $text = "{{cite web|title=Stuff|chapter=More Stuff|journal=Y}}";
        $expanded = $this->make_citation($text);
        $expanded->final_tidy();
        $this->AssertSame('cite book', $expanded->wikiname());
    }

    public function testFinalTidyThings1h(): void {
        $text = "{{cite web|title=Stuff|chapter=More Stuff|series=X}}";
        $expanded = $this->make_citation($text);
        $expanded->final_tidy();
        $this->AssertSame('cite book', $expanded->wikiname());
    }

    public function testFinalTidyThings2a(): void {
        $text = "{{Cite web|title=Stuff|url=arxiv_and_such|arxiv=1234}}";
        $expanded = $this->make_citation($text);
        $expanded->final_tidy();
        $this->AssertSame('cite arxiv', $expanded->wikiname());
        $this->AssertNull($expanded->get2('url'));
    }

    public function testFinalTidyThings2b(): void {
        $text = "{{cite web|title=Stuff|url=arxiv_and_such|eprint=1234}}";
        $expanded = $this->make_citation($text);
        $expanded->final_tidy();
        $this->AssertSame('cite arxiv', $expanded->wikiname());
        $this->AssertNull($expanded->get2('url'));
    }

    public function testTidyWPContentURLa(): void {
        $text = "{{Cite book|title=Stuff|chapter=More Stuff|url=http://www.dfadsfdsfasd.com/wp-content/chapter/2332}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('url');
        $this->AssertSame('cite book', $expanded->wikiname());
        $this->AssertSame('http://www.dfadsfdsfasd.com/wp-content/chapter/2332', $expanded->get2('chapter-url'));
        $this->AssertNull($expanded->get2('url'));
    }

    public function testTidyWPContentURLb(): void {
        $text = "{{Cite book|title=Stuff|chapter=More Stuff|url=http://www.dfadsfdsfasd.com/wp-content/pages/23332}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('url');
        $this->AssertSame('cite book', $expanded->wikiname());
        $this->AssertSame('http://www.dfadsfdsfasd.com/wp-content/pages/23332', $expanded->get2('chapter-url'));
        $this->AssertNull($expanded->get2('url'));
    }

    public function testTidyWPContentURLc(): void {
        $text = "{{Cite book|title=Stuff|chapter=More Stuff|url=http://www.dfadsfdsfasd.com/wp-content/blah}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('url');
        $this->AssertSame('cite book', $expanded->wikiname());
        $this->AssertSame('http://www.dfadsfdsfasd.com/wp-content/blah', $expanded->get2('url'));
        $this->AssertNull($expanded->get2('chapter-url'));
    }

    public function testTidyArchiveCloseToStart(): void {
        $text = "{{Cite book|title=Stuff|chapter=More Stuff|url=http://archive.org/details/stuff/page/n11}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('url');
        $this->AssertSame('cite book', $expanded->wikiname());
        $this->AssertSame('http://archive.org/details/stuff/page/n11', $expanded->get2('url'));
        $this->AssertNull($expanded->get2('chapter-url'));
    }

    public function testTidyArchiveCloseToStartb(): void {
        $text = "{{Cite book|title=Stuff|chapter=More Stuff|url=http://archive.org/details/stuff/page/n111}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('url');
        $this->AssertSame('cite book', $expanded->wikiname());
        $this->AssertSame('http://archive.org/details/stuff/page/n111', $expanded->get2('chapter-url'));
        $this->AssertNull($expanded->get2('url'));
    }

    public function testRemoveContentdirectionsDOIs(): void {
        $text = "{{cite web|doi=10.1336/3dasfdsfadsfsdfdsfdsfdsfdsfdsfsdfds|isbn=X}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('doi');
        $this->AssertNull($expanded->get2('doi'));
    }

    public function testRemoveContentdirectionsDOIs_2(): void {
        $text = "{{cite web|doi=10.1036/3dasfdsfadsfsdfdsfdsfdsfdsfdsfsdfds|isbn=X}}";
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('doi');
        $this->AssertNull($expanded->get2('doi'));
    }

    public function testCiteConferenceIncomplete(): void {
        $text = "{{cite conference|title=X|conference=Y}}";
        $expanded = $this->make_citation($text);
        $this->AssertFalse($expanded->incomplete());
    }

    public function testCiteConferenceIncomplete_2(): void {
        $text = "{{cite conference|title=X|book-title=Y}}";
        $expanded = $this->make_citation($text);
        $this->AssertFalse($expanded->incomplete());
    }

    public function testCiteConferenceIncomplete_3(): void {
        $text = "{{cite conference|title=X|chapter=Y}}";
        $expanded = $this->make_citation($text);
        $this->AssertFalse($expanded->incomplete());
    }

    public function testKeepGoogPublish(): void {
        $text = "{{cite web|author=Leeps |url=https://news.google.com/newspapers?nid=1309&dat=19890604&id=tKFUAAAAIBAJ&sjid=NpADAAAAIBAJ&pg=5932,900833&hl=en |title=Rust Busters |publisher=[[New Straits Times]] / [[Google News Archive]] |date=1989-06-04 |access-date=2015-05-03 }}";
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testMulitpleNewspapers(): void {
        $text = "{{cite web |url=https://philstar.com/pilipino-star-ngayon/stuff }}";
        $expanded = $this->process_citation($text);
        $this->assertSame('[[Pilipino Star Ngayon]]', $expanded->get2('website'));
    }

    public function testMulitpleNewspapers_2(): void {
        $text = "{{cite web |url=https://philstar.com/stuff }}";
        $expanded = $this->process_citation($text);
        $this->assertSame('[[The Philippine STAR]]', $expanded->get2('website'));
    }

    public function testAddBadVolume(): void {
        $text = "{{cite journal}}";
        $expanded = $this->make_citation($text);
        $expanded->add_if_new('volume', 'volume 08');
        $this->assertSame('8', $expanded->get2('volume'));
    }

    public function testRandomISSNtests(): void {
        $text = "{{cite journal|issn=AAAA-AAAA}}";
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testRandomISSNtests_2(): void {
        $text = "{{cite journal|issn=1682-5845}}";
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text()); // We no longer have an API
    }

    public function testDuplicateCaps1(): void {
        $text = "{{cite journal|duplicate_X=AAAA}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{cite journal|DUPLICATE_x=AAAA}}', $expanded->parsed_text());
        $expanded = $this->process_citation($expanded->parsed_text());
        $this->assertSame('{{cite journal|DUPLICATE_x=AAAA}}', $expanded->parsed_text());
    }

    public function testDuplicateCaps2(): void {
        $text = "{{cite journal|duplicate_x=AAAA|x=bbbb|X=cccc}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{cite journal|DUPLICATE_x=AAAA|DUPLICATE_x=bbbb|x=cccc}}', $expanded->parsed_text());
        $expanded = $this->process_citation($expanded->parsed_text());
        $this->assertSame('{{cite journal|DUPLICATE_x=AAAA|DUPLICATE_x=bbbb|x=cccc}}', $expanded->parsed_text());
    }

    public function testBadChapterStays(): void {
        $text = "{{cite journal|url=http://oxfordindex.oup.com/view/10.1093/ww/9780199540884.013.U162881|title=Chope, His Honour Robert Charles : Who Was Who - oi|chapter=Chope, His Honour Robert Charles, (26 June 1913–17 Oct. 1988), a Circuit Judge (Formerly Judge of County Courts), 1965–85 |date=December 2007 |doi=10.1093/ww/9780199540884.013.u162881|pmid=<!-- -->|pmc=<!-- -->}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('cite journal', $expanded->wikiname());
        $this->assertSame('Chope, His Honour Robert Charles, (26 June 1913–17 Oct. 1988), a Circuit Judge (Formerly Judge of County Courts), 1965–85', $expanded->get2('chapter'));
    }

    public function testRemoveLinkUnderscores(): void {
        $text = "{{cite journal|author-link3=A_X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('A X', $expanded->get3('author-link3'));

        $text = "{{cite journal|author-link3=A_X http}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('A_X http', $expanded->get3('author-link3'));
    }

    public function testBookTitleCleanUp1(): void {
        $text = "{{cite book|book-title=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('X', $expanded->get3('title'));
        $this->assertNull($expanded->get2('chapter'));
        $this->assertNull($expanded->get2('book-title'));
    }

    public function testBookTitleCleanUp2(): void {
        $text = "{{cite book|book-title=X|title=Y}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('X', $expanded->get3('title'));
        $this->assertSame('Y', $expanded->get3('chapter'));
        $this->assertNull($expanded->get2('book-title'));
    }

    public function testBookTitleCleanUp3(): void {
        $text = "{{cite book|book-title=X|title=X|chapter=Y}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('X', $expanded->get3('title'));
        $this->assertSame('Y', $expanded->get3('chapter'));
        $this->assertNull($expanded->get2('book-title'));
    }

    public function testBookTitleCleanUp4(): void {
        $text = "{{cite book|book-title=This is book-title|title=And title time|chapter=Chapter wapper}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('And title time', $expanded->get3('title'));
        $this->assertSame('This is book-title', $expanded->get3('book-title'));
        $this->assertSame('Chapter wapper', $expanded->get3('chapter'));
    }

    public function testArticleNumber(): void {
        $text = "{{cite journal|doi=10.1038/ncomms15367|pmid=<!-- -->|pmc=<!-- -->}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('15367', $expanded->get3('article-number'));
        $this->assertNull($expanded->get2('pages'));
        $this->assertNull($expanded->get2('page'));
    }

    public function testCleanUpArchives(): void {
        $text = "{{cite book| title=Archived Copy| script-title=Kornbluh}}";
        $prepared = $this->process_citation($text);
        $this->assertSame('Kornbluh', $prepared->get2('script-title'));
        $this->assertNull($prepared->get2('title'));
    }

    public function testBlockUnsupportedParamsInCiteBook(): void {
        // Test that journal, work, and website are blocked
        $text = "{{cite book}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('journal', 'Nature'));
        $this->assertFalse($template->add_if_new('work', 'Encyclopedia Britannica'));
        $this->assertFalse($template->add_if_new('website', 'example.com'));
    }

    public function testAllowEncyclopediaInCiteBook(): void {
        // Encyclopedia IS supported in cite book
        $text = "{{cite book}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('encyclopedia', 'Encyclopedia Britannica'));
    }

    public function testBlockIssueInCiteBook(): void {
        // Test that issue parameter is blocked in cite book
        $text = "{{cite book}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('issue', 'Foundations of Quantum Theory'));
        $this->assertNull($template->get2('issue'));
    }

    public function testRemoveExistingIssueFromCiteBook(): void {
        // Test that existing issue parameter is removed from cite book during final_tidy
        $text = "{{cite book|title=Test Book|issue=Foundations of Quantum Theory}}";
        $template = $this->make_citation($text);
        $this->assertSame('Foundations of Quantum Theory', $template->get2('issue'));
        $template->final_tidy();
        $this->assertNull($template->get2('issue'));
    }

    public function testWarnAboutUnsupportedParamsInCiteBook(): void {
        // Test that warnings are shown for unsupported parameters in cite book
        // Note: This test verifies the warning is triggered, but doesn't capture output
        $text = "{{cite book|title=Test Book|journal=Test Journal|work=Test Work}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        // The parameters should still be present (not removed), but warnings should be shown
        $this->assertSame('Test Journal', $template->get2('journal'));
        $this->assertSame('Test Work', $template->get2('work'));
    }

    public function testBlockUnsupportedParamsInHistoricalBookCitation(): void {
        // Test with real historical book citation (Agrippa's De occulta philosophia, 1533)
        // Verifies that journal and work parameters are blocked from being added
        $text = "{{cite book |last1=Agrippa von Nettesheim |first1=Heinrich Cornelius |title=De occulta philosophia libri tres |date=1533 |location=Cologne |pages=160, 163, 276-277 |url=https://www.loc.gov/resource/rbc0001.2009gen12345/?sp=280 |access-date=28 November 2024 }}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('journal', 'Test Journal'));
        $this->assertFalse($template->add_if_new('work', 'Test Work'));
    }

    public function testRejectURLInArticleNumber(): void {
        $text = "{{cite journal}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('article-number', 'https://www.insightturkey.com/'));
        $this->assertNull($template->get2('article-number'));
    }

    public function testRejectURLInVolume(): void {
        $text = "{{cite journal}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('volume', 'http://example.com/vol23'));
        $this->assertNull($template->get2('volume'));
    }

    public function testAllowURLInURLParameter(): void {
        $text = "{{cite journal}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('url', 'https://example.org/article'));
        $this->assertSame('https://example.org/article', $template->get2('url'));
    }

    public function testWarnAboutExistingURLInNonURLParameter(): void {
        // Test that existing URLs in non-URL parameters trigger warnings but are not removed
        $text = "{{cite journal|article-number=https://www.example.com/}}";
        $template = $this->process_citation($text);
        // The parameter should still be there (we don't remove it)
        $this->assertSame('https://www.example.com/', $template->get2('article-number'));
        // A warning should have been generated (captured by report_warning)
    }

    // Tests for "Progess in Optics" misspelling correction

    public function testSeriesMisspellingCorrectedWhenAdding(): void {
        // Test that misspelling is corrected when adding new series parameter
        $text = "{{cite book|title=Test}}";
        $template = $this->make_citation($text);
        $template->add_if_new('series', 'Progess in Optics');
        $this->assertSame('Progress in Optics', $template->get2('series'));
    }

    public function testSeriesMisspellingCorrectedInTidy(): void {
        // Test that existing misspelling is corrected during tidy
        $text = "{{cite book|series=Progess in Optics}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame('Progress in Optics', $template->get2('series'));
    }

    public function testSeriesMisspellingRecognizedAsBookSeries(): void {
        // Test that misspelling is recognized as a book series and converted
        $text = "{{cite journal|journal=Progess in Optics}}";
        $template = $this->process_citation($text);
        $this->assertSame('cite book', $template->wikiname());
        $this->assertSame('Progress in Optics', $template->get2('series'));
    }

    // Tests for work/website not added when publisher is already present (issue #5301)

    public function testWorkNotAddedWhenPublisherPresent(): void {
        // Bug report: work= is added even though publisher= is already set
        $text = "{{cite web|title=Some Article|publisher=Some Organization|url=https://example.org/}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('work', 'Some Organization'));
        $this->assertNull($template->get2('work'));
    }

    public function testWebsiteNotAddedWhenPublisherPresent(): void {
        // Bug report: website= is added even though publisher= is already set
        $text = "{{cite web|title=Some Article|publisher=Some Organization|url=https://example.org/}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('website', 'Some Organization'));
        $this->assertNull($template->get2('website'));
    }

    public function testWorkAddedWhenNoPublisher(): void {
        // Work should still be added when there is no publisher
        $text = "{{cite web|title=Some Article|url=https://example.org/}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('work', 'Some Publication'));
        $this->assertSame('Some Publication', $template->get2('work'));
    }

    public function testWebsiteAddedWhenNoPublisher(): void {
        // Website should still be added when there is no publisher
        $text = "{{cite web|title=Some Article|url=https://example.org/}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('website', 'example.org'));
        $this->assertSame('example.org', $template->get2('website'));
    }

    public function testWorkNotAddedWhenPublisherPresentCitation(): void {
        // Bug report: work= added to {{citation}} when publisher= is already set
        $text = "{{citation|title=Some Article|publisher=EATCS|url=https://www.eatcs.org/}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('work', 'EATCS'));
        $this->assertNull($template->get2('work'));
    }

    public function testWorkAllowedWhenPublisherIsCommentOnly(): void {
        // publisher=<!-- --> is effectively empty - work= should still be addable
        $text = "{{cite web|title=Some Article|publisher=<!-- -->|url=https://example.org/}}";
        $template = $this->process_citation($text);
        $this->assertTrue($template->add_if_new('work', 'Some Publication'));
        $this->assertSame('Some Publication', $template->get2('work'));
    }

    public function testPublisherNotDroppedWhenWorkAdditionIsBlocked(): void {
        // Bug report #2: publisher= was being removed when work= was added with the same value
        // (Zotero would add work=X matching publisher=X, then tidy would drop publisher because it matched work)
        // With the fix, work= is never added when publisher= is present, so publisher is preserved
        $text = "{{cite web|title=Some Article|publisher=Haldimand County|url=https://www.haldimandcounty.ca/}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero would do: try to add work= with the same value as publisher=
        $this->assertFalse($template->add_if_new('work', 'Haldimand County'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must still be present (not dropped by dedup logic)
        $this->assertSame('Haldimand County', $template->get2('publisher'));
    }

    public function testWorkNotAddedToCiteNewsWhenPublisherPresent(): void {
        // Bug report #3: cite news with publisher=<newspaper name> was being wrongly converted
        // to work=<newspaper name> when Zotero returned publicationTitle for a newspaperArticle.
        // The fix: work= is never added when publisher= is already set (same fix as reports 1 & 2).
        $text = "{{cite news|title=Some Article|publisher=The West Australian|url=https://thewest.com.au/}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero would do for a newspaperArticle: add work= with the publicationTitle
        $this->assertFalse($template->add_if_new('work', 'The West Australian'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must still be 'The West Australian'
        $this->assertSame('The West Australian', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentAndValueDiffersSlightly(): void {
        // Bug report #4: cite web with publisher=Princess of Asturias Foundation was having
        // work=The Princess of Asturias Foundation added by Zotero (same org, but with "The" prefix).
        // The fix: work= is blocked whenever publisher= is set, regardless of the value difference.
        $text = "{{cite web|title=Some Award|publisher=Princess of Asturias Foundation|url=https://www.fpa.es/en/some-award.html|access-date=23 June 2020}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns for fpa.es: publicationTitle with "The" prefix added
        $this->assertFalse($template->add_if_new('work', 'The Princess of Asturias Foundation'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must still be intact
        $this->assertSame('Princess of Asturias Foundation', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherAndLocationPresentAndWorkCombinesBoth(): void {
        // Bug report #5: cite web with publisher=Toyota location=UK was having work=Toyota UK added
        // by Zotero, producing the redundant rendered output "Toyota UK. UK: Toyota."
        // The fix: work= is blocked whenever publisher= is set, regardless of the value difference.
        $text = "{{cite web|title=Some Page|publisher=Toyota|location=UK|url=https://www.toyota.co.uk/some-page}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle combining publisher + location
        $this->assertFalse($template->add_if_new('work', 'Toyota UK'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= and location= must be preserved
        $this->assertSame('Toyota', $template->get2('publisher'));
        $this->assertSame('UK', $template->get2('location'));
    }

    public function testWorkNotAddedWhenPublisherIsWikilinked(): void {
        // Bug report #6: {{citation}} with publisher=[[European Association for Theoretical Computer Science]]
        // was having work=EATCS added by Zotero even though EATCS is the org abbreviation, not a website name.
        // The fix: work= is blocked whenever publisher= is set, including wikilinked publisher values.
        $text = "{{citation|url=https://www.eatcs.org/index.php/component/content/article/20-eatcs-awards/1874-eatcs-ipec-nerode-prize-2014-laudatio|title=EATCS-IPEC Nerode Prize 2014 - Laudatio|publisher=[[European Association for Theoretical Computer Science]]|accessdate=2015-09-03}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle = abbreviated org name
        $this->assertFalse($template->add_if_new('work', 'EATCS'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must still be the full wikilinked name
        $this->assertSame('[[European Association for Theoretical Computer Science]]', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherIsWikilinkAndWorkHasThePrefix(): void {
        // Bug report #7: {{cite web}} with publisher=[[British Museum]] was having
        // work=The British Museum added by Zotero (same org, but with "The" prefix).
        // The fix: work= is blocked whenever publisher= is set, regardless of the value difference.
        $text = "{{cite web|url=https://www.britishmuseum.org/blog/who-was-homer|access-date=7 March 2024|title=Who was Homer?|author=[[Daisy Dunn]]|date=22 January 2020|publisher=[[British Museum]]}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle with "The" prefix
        $this->assertFalse($template->add_if_new('work', 'The British Museum'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must still be the wikilinked name
        $this->assertSame('[[British Museum]]', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherIsDifferentLanguageTranslation(): void {
        // Bug report #9: {{citation}} with publisher=Foundation for Polish Science was having
        // work=Fundacja na rzecz Nauki Polskiej added by Zotero (the same organisation in Polish).
        // The fix: work= is blocked whenever publisher= is set, regardless of the language difference.
        $text = "{{citation|url=https://www.fnp.org.pl/en/fnp-prizes-laureates/|title=FNP Prizes Laureates|publisher=Foundation for Polish Science|access-date=2023-02-21}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle in Polish (different language, same organisation)
        $this->assertFalse($template->add_if_new('work', 'Fundacja na rzecz Nauki Polskiej'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must still be intact
        $this->assertSame('Foundation for Polish Science', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport12a(): void {
        // Bug report #12: cite web with publisher=Alan Turing Institute was having
        // work=The Alan Turing Institute added by Zotero (same org, but with "The" prefix).
        // The fix: work= is blocked whenever publisher= is set, regardless of the value difference.
        $text = "{{cite web|title=Some Page|publisher=Alan Turing Institute|url=https://www.turing.ac.uk/some-page}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle with "The" prefix
        $this->assertFalse($template->add_if_new('work', 'The Alan Turing Institute'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('Alan Turing Institute', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport12b(): void {
        // Bug report #12: cite web with publisher=American Association for the Advancement of Science
        // was having work=AAAS - The World's Largest General Scientific Society added by Zotero
        // (same org, with promotional wording in the publicationTitle).
        // The fix: work= is blocked whenever publisher= is set, regardless of the value difference.
        $text = "{{cite web|title=Some Page|publisher=American Association for the Advancement of Science|url=https://www.science.org/some-page}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle with promotional wording
        $this->assertFalse($template->add_if_new('work', 'AAAS - The World\'s Largest General Scientific Society'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('American Association for the Advancement of Science', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherMoreSpecificThanWork(): void {
        // Bug report #12: cite web with publisher=California State University, Northridge Faculty Senate
        // was having work=California State University, Northridge added by Zotero
        // (publisher is more specific than the publicationTitle Zotero returned).
        // The fix: work= is blocked whenever publisher= is set, even when publisher is more specific.
        $text = "{{cite web|title=Some Page|publisher=California State University, Northridge Faculty Senate|url=https://www.csun.edu/some-page}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle is less specific than the existing publisher
        $this->assertFalse($template->add_if_new('work', 'California State University, Northridge'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved (the more specific value)
        $this->assertSame('California State University, Northridge Faculty Senate', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport14b(): void {
        // Bug report #14.2: {{citation}} with publisher=Seabiscuit Heritage Foundation
        // was having work=Seabiscuit Heritage Foundation added by Zotero, then publisher= removed
        // by tidy() because work and publisher matched, losing the original publisher value.
        // The fix: work= is blocked whenever publisher= is set.
        $text = "{{citation|url=http://www.seabiscuitheritage.org/contact-us/|title=Directions to the ranch|publisher=Seabiscuit Heritage Foundation|accessdate=2019-12-04}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle identical to existing publisher
        $this->assertFalse($template->add_if_new('work', 'Seabiscuit Heritage Foundation'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('Seabiscuit Heritage Foundation', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport14c(): void {
        // Bug report #14.3: {{citation}} with publisher=Ministère de lʼEnseignement supérieur...
        // (using the modifier letter apostrophe U+02BC) was having work= added by Zotero
        // using a regular apostrophe U+0027, resulting in both work= and publisher= being set.
        // The fix: work= is blocked whenever publisher= is set, regardless of apostrophe differences.
        $text = "{{citation|url=http://www.enseignementsup-recherche.gouv.fr/cid20476/cinquieme-edition-du-prix-irene-joliot-curie.html|title=Cinquième édition du Prix Irène Joliot-Curie|publisher=Ministère de lʼEnseignement supérieur, de la Recherche et de lʼInnovation|accessdate=2020-01-04}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle with regular apostrophe instead of modifier letter apostrophe
        $this->assertFalse($template->add_if_new('work', "Ministère de l'Enseignement supérieur, de la Recherche et de l'Innovation"));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved with its original apostrophe characters
        $this->assertStringContainsString('Enseignement', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport14d(): void {
        // Bug report #14.4: {{citation}} with publisher=St. Lawrence University
        // was having work=St. Lawrence University added by Zotero, then publisher= removed
        // by tidy() because work and publisher matched (a university website, not a publication).
        // The fix: work= is blocked whenever publisher= is set.
        $text = "{{citation|url=https://www.stlawu.edu/math-computer-science-and-statistics/news/patti-frazer-lock-honored-distinguished-teaching-award|title=Patti Frazer Lock honored with Distinguished Teaching Award|publisher=St. Lawrence University|date=February 19, 2016|access-date=2020-01-24}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle identical to existing publisher
        $this->assertFalse($template->add_if_new('work', 'St. Lawrence University'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('St. Lawrence University', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport14e(): void {
        // Bug report #14.5: {{citation}} with publisher=[[Royal Society of Edinburgh]]
        // was having work=The Royal Society of Edinburgh added by Zotero alongside the existing
        // publisher= (with wikilink and no "The" prefix), resulting in both being set.
        // The fix: work= is blocked whenever publisher= is set, including wikilinked publishers.
        $text = "{{citation|url=https://www.rse.org.uk/fellow/bonnie-webber/|title=Professor Bonnie Lynn Webber FRSE|publisher=[[Royal Society of Edinburgh]]|accessdate=2020-03-12}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle with "The" prefix and without wikilink
        $this->assertFalse($template->add_if_new('work', 'The Royal Society of Edinburgh'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must still be the original wikilinked name
        $this->assertSame('[[Royal Society of Edinburgh]]', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport15(): void {
        // Bug report #15: {{citation}} with publisher=Mayo Clinic (no work=)
        // was having work=Mayo Clinic added by Zotero (publicationTitle from mayo.edu URL),
        // then tidy() dropped publisher= because it equalled work=, losing the original publisher.
        // The fix: work= is blocked whenever publisher= is set.
        $text = "{{citation|url=https://www.mayo.edu/research/departments-divisions/department-health-sciences-research/division-biomedical-statistics-informatics/research/survival-analysis/people|title=Research departments and divisions: Survival analysis|publisher=Mayo Clinic|accessdate=2020-06-20}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle identical to existing publisher
        $this->assertFalse($template->add_if_new('work', 'Mayo Clinic'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('Mayo Clinic', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport16(): void {
        // Bug report #16: {{citation}} with publisher=New Zealand India Research Institute (no work=)
        // was having work=Victoria University of Wellington added by Zotero,
        // even though publisher= was already set to a different organisation.
        // The fix: work= is blocked whenever publisher= is set.
        $text = "{{citation|url=https://www.wgtn.ac.nz/nziri/fellows/fellows/science/clemency-montelle|title=Dr Clemency Montelle|publisher=New Zealand India Research Institute|accessdate=2020-09-17}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle from the host university
        $this->assertFalse($template->add_if_new('work', 'Victoria University of Wellington'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('New Zealand India Research Institute', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport17(): void {
        // Bug report #17: {{citation}} with publisher=Royal Society of Edinburgh (no work=)
        // was having work=The Royal Society of Edinburgh added by Zotero,
        // even though publisher= was already set.
        // The fix: work= is blocked whenever publisher= is set.
        $text = "{{citation|url=https://www.rse.org.uk/fellow/xiaoyu-luo/|title=Professor Xiaoyu Luo FRSE|publisher=Royal Society of Edinburgh|access-date=2020-09-23}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle with "The" prefix added
        $this->assertFalse($template->add_if_new('work', 'The Royal Society of Edinburgh'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('Royal Society of Edinburgh', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport18(): void {
        // Bug report #18: {{citation}} with publisher=Cardiff University (no work=)
        // was having work=Cardiff University added by Zotero (publicationTitle from cardiff.ac.uk),
        // then tidy() dropped publisher= because it equalled work=, losing the original publisher.
        // The fix: work= is blocked whenever publisher= is set.
        $text = "{{citation|url=https://www.cardiff.ac.uk/people/view/118164-spasic-irena|title=Professor Irena Spasic|publisher=Cardiff University|access-date=2021-05-01}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle identical to existing publisher
        $this->assertFalse($template->add_if_new('work', 'Cardiff University'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('Cardiff University', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport19(): void {
        // Bug report #19: {{citation}} with publisher=Royal Society of Edinburgh (no work=)
        // was having work=The Royal Society of Edinburgh added by Zotero alongside the existing
        // publisher= (with "The" prefix), resulting in both being set simultaneously.
        // The fix: work= is blocked whenever publisher= is set.
        $text = "{{citation|url=https://www.rse.org.uk/fellow/margaret-lucas/|title=Professor Margaret Lucas FRSE|publisher=Royal Society of Edinburgh|access-date=2021-05-19}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle with "The" prefix added
        $this->assertFalse($template->add_if_new('work', 'The Royal Society of Edinburgh'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('Royal Society of Edinburgh', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport20(): void {
        // Bug report #20: {{citation}} with publisher=European Association for Theoretical Computer Science
        // was having work=EATCS added by Zotero alongside the existing publisher=, producing both fields
        // simultaneously.  Additionally, last1=Chita|first1=Efi was being added because the Joomla CMS
        // used by eatcs.org records its posting admin as the page "author".
        // The work= fix: work= is blocked whenever publisher= is set.
        // The author fix: eatcs.org author/creators are suppressed in APIzotero.php.
        $text = "{{citation|url=https://eatcs.org/index.php/component/content/article/1-news/956-presburger-award-2011|title=Presburger Award 2011|publisher=European Association for Theoretical Computer Science|access-date=2021-05-24}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle = EATCS abbreviation
        $this->assertFalse($template->add_if_new('work', 'EATCS'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('European Association for Theoretical Computer Science', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport22(): void {
        // Bug report #22: {{citation}} with publisher=Fundación Gadea Ciencia (no work=)
        // was having work=Fundación Gadea Ciencia added by Zotero (publicationTitle from gadeaciencia.org).
        // The cleanup code then saw publisher==work and called forget('publisher'), leaving only work=.
        // The fix: work= is blocked whenever publisher= is set, so the cleanup code is never triggered.
        $text = "{{citation|url=https://gadeaciencia.org/teams/moya-de-guerra-elvira-2/|title=Elvira Moya de Guerra|publisher=Fundación Gadea Ciencia|access-date=2021-08-01}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle identical to existing publisher
        $this->assertFalse($template->add_if_new('work', 'Fundación Gadea Ciencia'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('Fundación Gadea Ciencia', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport23(): void {
        // Bug report #23: {{citation}} with publisher=Museo de la Mujer (no work=)
        // was having work=Museo de la mujer added by Zotero (publicationTitle from museodelamujer.org.mx).
        // The cleanup code then saw publisher==work (case-insensitively) and called forget('publisher'),
        // leaving only work=.  The fix: work= is blocked whenever publisher= is set.
        $text = "{{citation|url=https://museodelamujer.org.mx/virtual/efenacional/fallece-silvia-de-neymet-urbina/|title=Fallece Silvia de Neymet Urbina|publisher=Museo de la Mujer|access-date=2021-09-27|language=es}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle matching the existing publisher
        $this->assertFalse($template->add_if_new('work', 'Museo de la mujer'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('Museo de la Mujer', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport24(): void {
        // Bug report #24: {{citation}} with publisher=Institut d'ingénierie et de management, Grenoble Alpes University
        // was having work=Grenoble INP Institut d'ingénierie et de management, Université Grenoble Alpes
        // added by Zotero (publicationTitle from grenoble-inp.fr) alongside the existing publisher.
        // The fix: work= is blocked whenever publisher= is set, even when the two values differ
        // (e.g. the same institution named in different languages or with different formatting).
        $text = "{{citation|url=https://www.grenoble-inp.fr/fr/l-institut/jocelyne-troccaz-et-philippe-cinquin-recompenses-par-l-academie-nationale-de-chirurgie|title=Jocelyne Troccaz et Philippe Cinquin récompensés par l'Académie nationale de Chirurgie|date=29 January 2014|publisher=Institut d'ingénierie et de management, Grenoble Alpes University|access-date=2022-06-25}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle is the French name of the same institution
        $this->assertFalse($template->add_if_new('work', "Grenoble INP Institut d'ingénierie et de management, Université Grenoble Alpes"));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved unchanged
        $this->assertSame("Institut d'ingénierie et de management, Grenoble Alpes University", $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport25(): void {
        // Bug report #25: {{citation}} with publisher=European Association for Theoretical Computer Science
        // was having work=EATCS and last1=Chita|first1=Efi added by Zotero for a different eatcs.org URL.
        // This is the same root cause as Report 20 but with a different article URL.
        // The work= fix: work= is blocked whenever publisher= is set (Template.php publisher guard).
        // The author fix: eatcs.org author/creators are suppressed in APIzotero.php (see zoteroTest.php).
        $text = "{{citation|url=https://eatcs.org/index.php/component/content/article/1-news/2103-eatcs-honours-three-outstanding-phd-theses-with-the-first-eatcs-distinguished-dissertation-awards|title=EATCS honours three outstanding PhD theses with the first EATCS Distinguished Dissertation Awards|publisher=European Association for Theoretical Computer Science|year=2015|access-date=2022-06-29}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle = EATCS abbreviation
        $this->assertFalse($template->add_if_new('work', 'EATCS'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('European Association for Theoretical Computer Science', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport26(): void {
        // Bug report #26: {{citation}} with publisher=Penn State Great Valley (no work=)
        // was having work=Penn State Great Valley added by Zotero (publicationTitle from greatvalley.psu.edu).
        // The cleanup code then saw publisher==work and called forget('publisher'), leaving only work=.
        // The fix: work= is blocked whenever publisher= is set, so the cleanup code is never triggered.
        $text = "{{citation|url=https://greatvalley.psu.edu/person/kathryn-w-jablokow|title=Kathryn W. Jablokow|publisher=Penn State Great Valley|access-date=2022-07-14}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle identical to existing publisher
        $this->assertFalse($template->add_if_new('work', 'Penn State Great Valley'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('Penn State Great Valley', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport27(): void {
        // Bug report #27: {{citation}} with publisher=University of Edinburgh School of Informatics
        // was having work=The University of Edinburgh added by Zotero (publicationTitle from ed.ac.uk).
        // The publisher was not removed in this case - work= was added alongside the existing publisher=.
        // The fix: work= is blocked whenever publisher= is set (Template.php publisher guard).
        $text = "{{citation|url=https://www.ed.ac.uk/informatics/news-events/stories/2016/goldwater-bcs-award|title=Dr Goldwater wins BCS Award|publisher=University of Edinburgh School of Informatics|date=21 June 2017|access-date=2022-07-28}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle = "The University of Edinburgh"
        $this->assertFalse($template->add_if_new('work', 'The University of Edinburgh'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('University of Edinburgh School of Informatics', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport28(): void {
        // Bug report #28: {{citation}} with publisher=The Combustion Institute
        // was having work=The Combustion Institute | Promoting and disseminating combustion science research
        // added by Zotero (publicationTitle from combustioninstitute.org).
        // After Zotero tagline stripping (' | ' separator removed), the value becomes "The Combustion Institute".
        // The fix: work= is blocked whenever publisher= is set (Template.php publisher guard).
        $text = "{{citation|url=https://www.combustioninstitute.org/resources/awards/fellows-of-the-combustion-institute/|publisher=The Combustion Institute|title=Fellows of The Combustion Institute|access-date=2022-09-12}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns after tagline stripping: publicationTitle = "The Combustion Institute"
        $this->assertFalse($template->add_if_new('work', 'The Combustion Institute'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('The Combustion Institute', $template->get2('publisher'));
    }

    public function testWorkNotAddedWhenPublisherPresentReport29(): void {
        // Bug report #29: {{citation}} with publisher=University of Guadalajara Department of Computational Sciences
        // was having work=Centro Universitario de Ciencias Exactas e Ingenierías added by Zotero
        // (publicationTitle from cucei.udg.mx).
        // The fix: work= is blocked whenever publisher= is set (Template.php publisher guard).
        $text = "{{citation|url=http://www.cucei.udg.mx/es/contenido/arana-daniel-nancy-guadalupe|title=Arana Daniel Nancy Guadalupe|date=8 May 2013|publisher=University of Guadalajara Department of Computational Sciences|access-date=2023-01-08}}";
        $template = $this->make_citation($text);
        // Simulate what Zotero returns: publicationTitle = "Centro Universitario de Ciencias Exactas e Ingenierías"
        $this->assertFalse($template->add_if_new('work', 'Centro Universitario de Ciencias Exactas e Ingenierías'));
        // work= must not have been added
        $this->assertNull($template->get2('work'));
        // publisher= must be preserved
        $this->assertSame('University of Guadalajara Department of Computational Sciences', $template->get2('publisher'));
    }

}
