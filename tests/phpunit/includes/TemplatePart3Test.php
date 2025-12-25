<?php
declare(strict_types=1);

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../../testBaseClass.php';

final class TemplatePart3Test extends testBaseClass {
    public function testND(): void {  // n.d. is special case that template recognize.  Must protect final period.
        $text = '{{Cite journal|date =n.d.}}';
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());

        $text = '{{Cite journal|year=n.d.}}';
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testRIS(): void {
        $text = '{{Cite journal   | TY - JOUR
AU - Shannon, Claude E.
PY - 1948/07//
TI - A Mathematical Theory of Communication
T2 - Bell System Technical Journal
SP - 379
EP - 423
VL - 27
ER -  }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('A Mathematical Theory of Communication', $prepared->get2('title'));
        $this->assertSame('1948-07', $prepared->get2('date'));
        $this->assertSame('Bell System Technical Journal', $prepared->get2('journal'));
        $this->assertSame('Shannon, Claude E.', $prepared->first_author());
        $this->assertSame('Shannon', $prepared->get2('last1'));
        $this->assertSame('Claude E.', $prepared->get2('first1'));
        $this->assertSame('379–423', $prepared->get2('pages'));
        $this->assertSame('27', $prepared->get2('volume'));
        // This is the exact same reference, but with an invalid title, that flags this data to be rejected
        // We check everything is null, to verify that bad title stops everything from being added, not just title
        $text = '{{Cite journal   | TY - JOUR
AU - Shannon, Claude E.
PY - 1948/07//
TI - oup accepted manuscript
T2 - Bell System Technical Journal
SP - 379
EP - 423
VL - 27
ER -  }}';
        $prepared = $this->prepare_citation($text);
        $this->assertNull($prepared->get2('title'));
        $this->assertNull($prepared->get2('year'));
        $this->assertNull($prepared->get2('journal'));
        $this->assertSame('', $prepared->first_author());
        $this->assertNull($prepared->get2('last1'));
        $this->assertNull($prepared->get2('first1'));
        $this->assertNull($prepared->get2('pages'));
        $this->assertNull($prepared->get2('volume'));

        $text = '{{Cite journal   | TY - BOOK
Y1 - 1990
T1 - This will be a subtitle
T3 - This will be ignored}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('1990', $prepared->get2('date'));
        $this->assertNull($prepared->get2('title'));
        $this->assertNull($prepared->get2('chapter'));
        $this->assertNull($prepared->get2('journal'));
        $this->assertNull($prepared->get2('series'));

        $text = '{{Cite journal | TY - JOUR
Y1 - 1990
JF - This is the Journal
T1 - This is the Title }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('1990', $prepared->get2('date'));
        $this->assertSame('This is the Journal', $prepared->get2('journal'));
        $this->assertSame('This is the Title', $prepared->get2('title'));

        $text = '{{Cite journal | TY - JOUR
Y1 - 1990
JF - This is the Journal
T1 - This is the Title
SP - i
EP - 999 }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('1990', $prepared->get2('date'));
        $this->assertSame('This is the Journal', $prepared->get2('journal'));
        $this->assertSame('This is the Title', $prepared->get2('title'));
        $this->assertNull($prepared->get2('page'));
        $this->assertNull($prepared->get2('pages')); // Range is too big and starts with "i"
    }

    public function testEndNote(): void {
            $book = '{{Cite book |
%0 Book
%A Geoffrey Chaucer
%D 1957
%T The Works of Geoffrey Chaucer
%E F.
%I Houghton
%C Boston
%N 2nd
            }}'; // Not quite clear how %E and %N should be handled here. Needs an assertion.
            $article = '{{Cite journal |
%0 Journal Article
%A Herbert H. Clark
%D 1982
%T Hearers and Speech Acts
%B Language
%V 58
%P 332-373
            }}'; // Not sure how %B should be handled; needs an assertion.
            $thesis = '{{Citation |
%0 Thesis
%A Cantucci, Elena
%T Permian strata in South-East Asia
%D 1990
%I University of California, Berkeley
%R 10.1038/ntheses.01928
%@ Ignore
%9 Dissertation}}';
            $code_coverage1   = '{{Citation |
%0 Journal Article
%T This Title
%R NOT_A_DOI
%@ 9999-9999}}';

            $code_coverage2   = '{{Citation |
%0 Book
%T This Title
%@ 000-000-000-0X}}';

        $prepared = $this->prepare_citation($book);
        $this->assertSame('Chaucer, Geoffrey', $prepared->first_author());
        $this->assertSame('The Works of Geoffrey Chaucer', $prepared->get2('title'));
        $this->assertSame('1957', $this->getDateAndYear($prepared));
        $this->assertSame('Houghton', $prepared->get2('publisher'));
        $this->assertSame('Boston', $prepared->get2('location'));

        $prepared = $this->process_citation($article);
        $this->assertSame('Clark, Herbert H.', $prepared->first_author());
        $this->assertSame('1982', $this->getDateAndYear($prepared));
        $this->assertSame('Hearers and Speech Acts', $prepared->get2('title'));
        $this->assertSame('58', $prepared->get2('volume'));
        $this->assertSame('332–373', $prepared->get2('pages'));

        $prepared = $this->process_citation($thesis);
        $this->assertSame('Cantucci, Elena', $prepared->first_author());
        $this->assertSame('Permian strata in South-East Asia', $prepared->get2('title'));
        $this->assertSame('1990', $this->getDateAndYear($prepared));
        $this->assertSame('University of California, Berkeley', $prepared->get2('publisher'));
        $this->assertSame('10.1038/ntheses.01928', $prepared->get2('doi'));

        $prepared = $this->process_citation($code_coverage1);
        $this->assertSame('This Title', $prepared->get2('title'));
        $this->assertSame('9999-9999', $prepared->get2('issn'));
        $this->assertNull($prepared->get2('doi'));

        $prepared = $this->process_citation($code_coverage2);
        $this->assertSame('This Title', $prepared->get2('title'));
        $this->assertSame('000-000-000-0X', $prepared->get2('isbn'));
    }

    public function testConvertingISBN10Dashes(): void {
        $text = "{{cite book|isbn=|year=2000}}";
        $prepared = $this->prepare_citation($text);
        $prepared->add_if_new('isbn', '0974900907');
        $this->assertSame('0-9749009-0-7', $prepared->get2('isbn'));  // added with dashes
    }

    public function testConvertingISBN10DashesX(): void {
        $text = "{{cite book|isbn=|year=2000}}";
        $prepared = $this->prepare_citation($text);
        $prepared->add_if_new('isbn', '155404295X');
        $this->assertSame('1-55404-295-X', $prepared->get2('isbn'));  // added with dashes
    }

    public function testEtAl(): void {
        $text = '{{cite book |auths=Alfred A Albertstein, Bertie B Benchmark, Charlie C. Chapman et al. }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('Albertstein, Alfred A.', $prepared->first_author());
        $this->assertSame('Charlie C.', $prepared->get2('first3'));
        $this->assertSame('etal', $prepared->get2('display-authors'));
    }

    public function testEtAlAsAuthor_1(): void {
        $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|author3 = et al. }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('etal', $prepared->get2('display-authors'));
        $this->assertNull($prepared->get2('author3'));
    }

    public function testEtAlAsAuthor_2(): void {
        $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|last3 = et al. }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('etal', $prepared->get2('display-authors'));
    }

    public function testEtAlAsAuthor_3(): void {
        $this->assertNull($prepared->get2('last3'));
        $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|author3 = etal. }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('etal', $prepared->get2('display-authors'));
        $this->assertNull($prepared->get2('author3'));
    }

    public function testEtAlAsAuthor_4(): void {
        $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|last3 = etal }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('etal', $prepared->get2('display-authors'));
        $this->assertNull($prepared->get2('last3'));
    }

    public function testEtAlAsAuthor_5(): void {
        $text = '{{cite book|last1=etal}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('etal', $prepared->get2('display-authors'));
        $this->assertNull($prepared->get2('last1'));
    }

    public function testEtAlAsAuthor_6(): void {
        $text = '{{cite book|last1=et al}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('etal', $prepared->get2('display-authors'));
        $this->assertNull($prepared->get2('last1'));
    }

    public function testEtAlAsAuthor_7(): void {
        $text = '{{cite book|last1=et al}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('etal', $prepared->get2('display-authors'));
        $this->assertNull($prepared->get2('last1'));
    }

    public function testWebsite2Url1(): void {
        $text = '{{cite book |website=ttp://example.org }}';
        $prepared = $this->prepare_citation($text);
        $this->assertNull($prepared->get2('url'));
    }

    public function testWebsite2Url2(): void {
        $text = '{{cite book |website=example.org }}';
        $prepared = $this->prepare_citation($text);
        $this->assertNull($prepared->get2('url'));
    }

    public function testWebsite2Url3(): void {
        $text = '{{cite book |website=ttp://jstor.org/pdf/123456 | jstor=123456 }}';
        $prepared = $this->prepare_citation($text);
        $this->assertNotNull($prepared->get2('url'));
    }

    public function testWebsite2Url4(): void {
        $text = '{{cite book |website=ABC}}';
        $prepared = $this->prepare_citation($text);
        $this->assertNull($prepared->get2('url'));
        $this->assertSame('ABC', $prepared->get2('website'));
    }

    public function testWebsite2Url5(): void {
        $text = '{{cite book |website=ABC XYZ}}';
        $prepared = $this->prepare_citation($text);
        $this->assertNull($prepared->get2('url'));
        $this->assertSame('ABC XYZ', $prepared->get2('website'));
    }

    public function testWebsite2Url6(): void {
        $text = '{{cite book |website=http://ABC/ I have Spaces in Me}}';
        $prepared = $this->prepare_citation($text);
        $this->assertNull($prepared->get2('url'));
        $this->assertSame('http://ABC/ I have Spaces in Me', $prepared->get2('website'));
    }

    public function testHearst (): void {
        $text = '{{cite book|url=http://books.google.com/books?id=p-IDAAAAMBAJ&lpg=PA195&dq=Popular%20Science%201930%20plane%20%22Popular%20Mechanics%22&pg=PA194#v=onepage&q&f=true}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('Hearst Magazines', $expanded->get2('publisher'));
        $this->assertNull($expanded->get2('last1'));
        $this->assertNull($expanded->get2('last'));
        $this->assertNull($expanded->get2('author'));
        $this->assertNull($expanded->get2('author1'));
        $this->assertNull($expanded->get2('authors'));
        $this->assertSame('https://books.google.com/books?id=p-IDAAAAMBAJ&dq=Popular%20Science%201930%20plane%20%22Popular%20Mechanics%22&pg=PA194', $expanded->get2('url'));
    }

    public function testHearst2 (): void {
        $text = '{{cite book|url=http://books.google.com/books?id=p-IDAAAAMBAJ&lpg=PA195&dq=Popular%20Science%201930%20plane%20%22Popular%20Mechanics%22&pg=PA194#v=snippet&q&f=true}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('Hearst Magazines', $expanded->get2('publisher'));
        $this->assertNull($expanded->get2('last1'));
        $this->assertNull($expanded->get2('last'));
        $this->assertNull($expanded->get2('author'));
        $this->assertNull($expanded->get2('author1'));
        $this->assertNull($expanded->get2('authors'));
        $this->assertSame('https://books.google.com/books?id=p-IDAAAAMBAJ&dq=Popular%20Science%201930%20plane%20%22Popular%20Mechanics%22&pg=PA194', $expanded->get2('url'));
    }

    public function testInternalCaps(): void { // checks for title formatting in tidy() not breaking things
        $text = '{{cite journal|journal=ZooTimeKids}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('ZooTimeKids', $prepared->get2('journal'));
    }

    public function testCapsAfterColonAndPeriodJournalTidy(): void {
        $text = '{{Cite journal |journal=In Journal Titles: a word following punctuation needs capitals. Of course.}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('In Journal Titles: A Word Following Punctuation Needs Capitals. Of Course.',
                                      $prepared->get2('journal'));
    }

    public function testExistingWikiText(): void { // checks for formatting in tidy() not breaking things
        $text = '{{cite journal|title=[[Zootimeboys]] and Girls|journal=[[Zootimeboys]] and Girls}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('Zootimeboys and Girls', $prepared->get2('journal'));
        $this->assertSame('[[Zootimeboys]] and Girls', $prepared->get2('title'));
    }

    public function testNewWikiText(): void { // checks for new information that looks like wiki text and needs escaped
        $text = '{{Cite journal|doi=10.1021/jm00193a001}}';   // This has greek letters, [, ], (, and ).
        $expanded = $this->process_citation($text);
        $this->assertSame('Synthetic studies on β-lactam antibiotics. Part 10. Synthesis of 7β-&#91;2-carboxy-2-(4-hydroxyphenyl)acetamido&#93;-7.alpha.-methoxy-3-&#91;&#91;(1-methyl-1H-tetrazol-5-yl)thio&#93;methyl&#93;-1-oxa-1-dethia-3-cephem-4-carboxylic acid disodium salt (6059-S) and its related 1-oxacephems', $expanded->get2('title'));
    }

    public function testZooKeys_1(): void {
        $text = '{{Cite journal|doi=10.3897/zookeys.445.7778}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('ZooKeys', $expanded->get2('journal'));
        $this->assertSame('445', $expanded->get2('issue'));
        $this->assertNull($expanded->get2('volume'));
    }

    public function testZooKeys_2(): void {
        $text = '{{Cite journal|doi=10.3897/zookeys.445.7778|journal=[[Zookeys]]}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('445', $expanded->get2('issue'));
        $this->assertNull($expanded->get2('volume'));
    }

    public function testZooKeys_3(): void {
        $text = "{{cite journal|last1=Bharti|first1=H.|last2=Guénard|first2=B.|last3=Bharti|first3=M.|last4=Economo|first4=E.P.|title=An updated checklist of the ants of India with their specific distributions in Indian states (Hymenoptera, Formicidae)|journal=ZooKeys|date=2016|volume=551|pages=1–83|doi=10.3897/zookeys.551.6767|pmid=26877665|pmc=4741291}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('551', $expanded->get2('issue'));
        $this->assertNull($expanded->get2('volume'));
    }

    public function testZooKeysDoiTidy1(): void {
        $text = '{{Cite journal|doi=10.3897//zookeys.123.322222}}'; // Note extra slash for fun
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('doi');
        $this->assertNull($expanded->get2('journal'));
        $this->assertSame('123', $expanded->get2('issue'));
    }

    public function testZooKeysDoiTidy2(): void {
        $text = '{{Cite journal|doi=10.3897/zookeys.123.322222|issue=2323323}}';
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('doi');
        $this->assertNull($expanded->get2('journal'));
        $this->assertSame('123', $expanded->get2('issue'));
    }

    public function testZooKeysDoiTidy3(): void {
        $text = '{{Cite journal|doi=10.3897/zookeys.123.322222|number=2323323}}';
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('doi');
        $this->assertNull($expanded->get2('journal'));
        $this->assertSame('123', $expanded->get2('issue'));
    }

    public function testZooKeysDoiTidy4(): void {
        $text = '{{Cite journal|doi=10.3897/zookeys.123.322222X}}';
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('doi');
        $this->assertNull($expanded->get2('journal'));
        $this->assertNull($expanded->get2('issue'));
    }

    public function testOrthodontist(): void {
        $text = '{{Cite journal|doi=10.1043/0003-3219(BADBADBAD}}'; // These will never work
        $expanded = $this->make_citation($text);
        $expanded->tidy_parameter('doi');
        $this->assertNull($expanded->get2('doi'));
    }

    public function testZooKeysAddIssue(): void {
        $text = '{{Cite journal|journal=[[ZooKeys]]}}';
        $expanded = $this->make_citation($text);
        $this->assertTrue($expanded->add_if_new('volume', '33'));
        $this->assertNull($expanded->get2('volume'));
        $this->assertSame('33', $expanded->get2('issue'));
    }

    public function testTitleItalics(): void {
        $text = '{{cite journal|doi=10.1111/pala.12168}}';
        $expanded = $this->process_citation($text);
        $title = $expanded->get('title');
        $title = str_replace('‐', '-', $title); // Dashes vary
        $title = str_replace("'", "", $title);  // Sometimes there, sometime not
        $this->assertSame("The macro- and microfossil record of the Cambrian priapulid Ottoia", $title);
    }

    public function testSpeciesCaps_1(): void {
        $text = '{{Cite journal | doi = 10.1007%2Fs001140100225}}';
        $expanded = $this->process_citation($text);
        $this->assertSame(str_replace(' ', '', "Crypticmammalianspecies:Anewspeciesofwhiskeredbat(''Myotisalcathoe''n.sp.)inEurope"),
                                      str_replace(' ', '', $expanded->get('title')));
    }

    public function testSpeciesCaps_2(): void {
        $text = '{{Cite journal | url = http://onlinelibrary.wiley.com/doi/10.1111/j.1550-7408.2002.tb00224.x/full}}';
        // Should be able to drop /full from DOI in URL
        $expanded = $this->process_citation($text);
        $this->assertSame(str_replace(' ', '', "''Cryptosporidiumhominis''n.Sp.(Apicomplexa:Cryptosporidiidae)from''Homosapiens''"),
                                      str_replace(' ', '', $expanded->get('title'))); // Can't get Homo sapiens, can get nsp.
    }

    public function testSICI(): void {
        $url = "https://fake.url/sici?sici=9999-9999(196101/03)81:1<43:WLIMP>2.0.CO;2-9";
        $text = "{{Cite journal|url=$url}}";  // We use a rubbish ISSN and website so that this does not expand any more -- only test SICI code
        $expanded = $this->process_citation($text);
        $this->assertSame('1961', $expanded->get2('date'));
        $this->assertSame('81', $expanded->get2('volume'));
        $this->assertSame('1', $expanded->get2('issue'));
        $this->assertSame('43', $expanded->get2('page'));
    }

    public function testJstorSICI(): void {
        $url = "https://www.jstor.org/sici?sici=0003-0279(196101/03)81:1<43:WLIMP>2.0.CO;2-9";
        $text = "{{Cite journal|url=$url}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('594900', $expanded->get2('jstor'));
        $this->assertSame('1961', $expanded->get2('date'));
        $this->assertSame('81', $expanded->get2('volume'));
        $this->assertSame('1', $expanded->get2('issue'));
        $this->assertSame('43', mb_substr($expanded->get('pages') . $expanded->get('page'), 0, 2));  // The jstor expansion can add the page ending
    }

    public function testJstorSICIEncoded(): void {
        $text = '{{Cite journal|url=https://www.jstor.org/sici?sici=0003-0279(196101%2F03)81%3A1%3C43%3AWLIMP%3E2.0.CO%3B2-9}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('594900', $expanded->get2('jstor'));
    }

    public function testIgnoreJstorPlants(): void {
        $text = '{{Cite journal| url=http://plants.jstor.org/stable/10.5555/al.ap.specimen.nsw225972 |title=Holotype of Persoonia terminalis L.A.S.Johnson & P.H.Weston [family PROTEACEAE]}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('http://plants.jstor.org/stable/10.5555/al.ap.specimen.nsw225972', $expanded->get2('url'));
        $this->assertNull($expanded->get2('jstor'));
        $this->assertNull($expanded->get2('doi'));
    }

    public function testConvertJournalToBook(): void {
        $text = '{{Cite journal|doi=10.1007/978-3-540-74735-2_15}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('cite book', $expanded->wikiname());
    }

    public function testRenameToJournal(): void {
        $text = "{{cite arxiv | bibcode = 2013natur1305.7450M}}";
        $prepared = $this->prepare_citation($text);
        $this->assertSame('cite journal', $prepared->wikiname());
        $text = "{{cite arxiv | bibcode = 2013arXiv1305.7450M}}";
        $prepared = $this->prepare_citation($text);
        $this->assertSame('cite arxiv', $prepared->wikiname());
        $text = "{{cite arxiv | bibcode = 2013physics305.7450M}}";
        $prepared = $this->prepare_citation($text);
        $this->assertSame('cite arxiv', $prepared->wikiname());
    }

    public function testArxivDocumentBibcodeCode1(): void {
        $text = "{{cite arxiv| arxiv=1234|bibcode=abc}}";
        $template = $this->make_citation($text);
        $template->change_name_to('cite journal');
        $template->final_tidy();
        $this->assertSame('cite arxiv', $template->wikiname());
        $this->assertNull($template->get2('bibcode'));
    }

    public function testArxivDocumentBibcodeCode2(): void {
        $text = "{{cite journal}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame('cite journal', $template->wikiname());
    }

    public function testArxivDocumentBibcodeCode3(): void {
        $text = "{{cite web}}";
        $template = $this->make_citation($text);
        $template->change_name_to('cite journal');
        $template->final_tidy();
        $this->assertSame('cite web', $template->wikiname());
    }

    public function testArxivDocumentBibcodeCode4(): void {
        $text = "{{cite web|eprint=xxx}}";
        $template = $this->make_citation($text);
        $template->change_name_to('cite journal');
        $template->final_tidy();
        $this->assertSame('cite arxiv', $template->wikiname());
    }

    public function testArxivToJournalIfDoi(): void {
        $text = "{{cite arxiv| eprint=1234|doi=10.0000/Rubbish_bot_failure_test}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame('cite journal', $template->wikiname());
    }

    public function testChangeNameURL(): void {
        $text = "{{cite web|url=x|chapter-url=X|chapter=Z}}";
        $template = $this->process_citation($text);
        $this->assertSame('cite book', $template->wikiname());
        $this->assertSame('Z', $template->get2('chapter'));
        $this->assertSame('X', $template->get2('chapter-url'));
        $this->assertNull($template->get2('url')); // Remove since identical to chapter
    }

    public function testRenameToExisting(): void {
        $text = "{{cite journal|issue=1|volume=2|doi=3}}";
        $template = $this->make_citation($text);
        $this->assertSame('{{cite journal|issue=1|volume=2|doi=3}}', $template->parsed_text());
        $template->rename('doi', 'issue');
        $this->assertSame('{{cite journal|volume=2|issue=3}}', $template->parsed_text());
        $template->rename('volume', 'issue');
        $this->assertSame('{{cite journal|issue=2}}', $template->parsed_text());
        $template->forget('issue');
        $this->assertSame('{{cite journal}}', $template->parsed_text());
        $this->assertNull($template->get2('issue'));
        $this->assertNull($template->get2('doi'));
        $this->assertNull($template->get2('volume'));
    }

    public function testRenameToArxivWhenLoseUrl_1(): void {
        $text = "{{cite web|url=1|arxiv=2}}";
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertSame('cite arxiv', $template->wikiname());
    }

    public function testRenameToArxivWhenLoseUrl_2(): void {
        $text = "{{cite web|url=1|arxiv=2|chapter-url=XYX}}";
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertSame('cite web', $template->wikiname());
    }

    public function testDoiInline1(): void {
        $text = '{{citation | title = {{doi-inline|10.1038/nature10000|Funky Paper}} }}';
        $expanded = $this->process_citation($text);
        $this->assertSame('Nature', $expanded->get2('journal'));
        $this->assertSame('Funky Paper', $expanded->get2('title'));
        $this->assertSame('10.1038/nature10000', $expanded->get2('doi'));
    }

    public function testPagesDash1(): void {
        $text = '{{cite journal|pages=1-2|title=do change}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('1–2', $prepared->get2('pages'));
    }

    public function testPagesDash2(): void {
        $text = '{{cite journal|at=1-2|title=do not change}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('1-2', $prepared->get2('at'));
    }

    public function testPagesDash3(): void {
        $text = '{{cite journal|pages=[http://bogus.bogus/1–2/ 1–2]|title=do not change }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('[http://bogus.bogus/1–2/ 1–2]', $prepared->get2('pages'));
    }

    public function testPagesDash4(): void {
        $text = '{{Cite journal|pages=15|doi=10.1016/j.biocontrol.2014.06.004}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('15–22', $expanded->get2('pages')); // Converted should use long dashes
    }

    public function testPagesDash5(): void {
        $text = '{{Cite journal|doi=10.1007/s11746-998-0245-y|at=pp.425–439, see Table&nbsp;2 p.&nbsp;426 for tempering temperatures}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('pp.425–439, see Table&nbsp;2 p.&nbsp;426 for tempering temperatures', $expanded->get2('at')); // Leave complex at=
    }

    public function testPagesDash6(): void {
        $text = '{{cite book|pages=See [//books.google.com/books?id=-_rxBwAAQBAJ&pg=PA107 107]}}';
        $expanded = $this->process_citation($text); // Do not change this hidden URL
        $this->assertSame('See [//books.google.com/books?id=-_rxBwAAQBAJ&pg=PA107 107]', $expanded->get2('pages'));
    }

    public function testPagesDash7(): void {
        $text = '{{cite book|pages=[//books.google.com/books?id=-_rxBwAAQBAJ&pg=PA107 107]}}';
        $expanded = $this->process_citation($text); // Do not change dashes in this hidden URL, but upgrade URL to real one
        $this->assertSame('[https://books.google.com/books?id=-_rxBwAAQBAJ&pg=PA107 107]', $expanded->get2('pages'));
    }

    public function testPagesDash8(): void {
        $text = '{{cite journal|pages=AB-2|title=do change}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('AB-2', $prepared->get2('pages'));
    }

    public function testPagesDash9(): void {
        $text = '{{cite journal|page=1-2|title=do change}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('1-2', $prepared->get2('page')); // With no change, but will give warning to user
    }

    public function testBogusPageRanges(): void { // Fake year for code that updates page ranges that start with 1
        $text = '{{Cite journal| year = ' . date("Y") . '| doi = 10.1017/jpa.2018.43|title = New well-preserved scleritomes of Chancelloriida from early Cambrian Guanshan Biota, eastern Yunnan, China|journal = Journal of Paleontology|volume = 92|issue = 6|pages = 1–17|last1 = Zhao|first1 = Jun|last2 = Li|first2 = Guo-Biao|last3 = Selden|first3 = Paul A}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('955–971', $expanded->get2('pages')); // Converted should use long dashes
    }

    public function testBogusPageRanges2(): void {
        $text = '{{Cite journal| doi = 10.1017/jpa.2018.43|pages = 960}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('960', $expanded->get2('pages')); // Existing page number was within existing range
    }

    public function testCollapseRanges(): void {
        $text = '{{cite journal|pages=1233-1233|year=1999-1999}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('1233', $prepared->get2('pages'));
        $this->assertSame('1999', $prepared->get2('year'));
    }

    public function testSmallWords_1(): void {
        $text = '{{cite journal|journal=A Word in ny and n y About cow And Then boys the U S A and y and z}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('A Word in NY and N Y About Cow and then Boys the U S A and y and Z', $prepared->get2('journal'));
    }

    public function testSmallWords_2(): void {
        $text = '{{cite journal|journal=Ann of Math}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('Ann of Math', $prepared->get2('journal'));
    }

    public function testSmallWords_3(): void {
        $text = '{{cite journal|journal=Ann. of Math.}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('Ann. of Math.', $prepared->get2('journal'));
    }

    public function testSmallWords_4(): void {
        $text = '{{cite journal|journal=Ann. of Math}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('Ann. of Math', $prepared->get2('journal'));
    }

    public function testDoNotAddYearIfDate(): void {
        $text = '{{cite journal|date=2002|doi=10.1635/0097-3157(2002)152[0215:HPOVBM]2.0.CO;2}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('year'));
    }

    public function testAccessDates_1(): void {
        $text = '{{cite book |date=March 12, 1913 |title=Session Laws of the State of Washington, 1913 |chapter=Chapter 65: Classifying Public Highways |page=221 |chapter-url=http://leg.wa.gov/CodeReviser/documents/sessionlaw/1913c65.pdf |publisher=Washington State Legislature |accessdate=August 30, 2018}}';
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('accessdate'));
    }

    public function testAccessDates_2: void {
        $text = '{{cite book |date=March 12, 1913 |title=Session Laws of the State of Washington, 1913 |chapter=Chapter 65: Classifying Public Highways |page=221 |chapterurl=http://leg.wa.gov/CodeReviser/documents/sessionlaw/1913c65.pdf |publisher=Washington State Legislature |accessdate=August 30, 2018}}';
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('accessdate'));
    }

    public function testIgnoreUnkownCiteTemplates(): void {
        $text = "{{Cite imaginary source | http://google.com | title  I am a title | auhtor = Other, A. N. | issue- 9 | vol. 22 pp. 5-6|doi=10.0000/Rubbish_bot_failure_test }}";
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testJustAnISBN(): void {
        $text = '{{cite book |isbn=1452934800}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('stories from jonestown', mb_strtolower($expanded->get('title')));
        $this->assertNull($expanded->get2('url'));
    }

    public function testArxivPDf(): void {
        $text = '{{cite web|url=https://arxiv.org/ftp/arxiv/papers/1312/1312.7288.pdf}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('1312.7288', $expanded->get2('arxiv'));
    }

    public function testEmptyCitations(): void {
        $text = 'bad things like {{cite journal}}{{cite book|||}}{{cite arxiv}}{{cite web}} should not crash bot'; // bot removed pipes
        $expanded = $this->process_page($text);
        $this->assertSame('bad things like {{cite journal}}{{cite book}}{{cite arXiv}}{{cite web}} should not crash bot', $expanded->parsed_text());
    }

    public function testLatexMathInTitle(): void { // This contains Math stuff that should be z~10, but we just verify that we do not make it worse at this time.   See https://tex.stackexchange.com/questions/55701/how-do-i-write-sim-approximately-with-the-correct-spacing
        $text = "{{Cite arxiv|eprint=1801.03103}}";
        $expanded = $this->process_citation($text);
        $title = $expanded->get2('title');
        // For some reason we sometimes get the first one - probably just ARXIV
        $title1 = 'A Candidate $z\sim10$ Galaxy Strongly Lensed into a Spatially Resolved Arc';
        $title2 = "RELICS: A Candidate ''z'' ∼ 10 Galaxy Strongly Lensed into a Spatially Resolved Arc";
        $title3 = "RELICS: A Candidate z ∼ 10 Galaxy Strongly Lensed into a Spatially Resolved Arc";
        if (in_array($title, [$title1, $title2, $title3], true)) {
            $this->assertFaker();
        } else {
            $this->assertSame('Should not have got this', $title); // What did we get
        }
    }

    public function testDropGoogleWebsite(): void {
        $text = "{{Cite book|website=Google.Com|url=http://Invalid.url.not-real.com/}}"; // Include a fake URL so that we are not testing: if (no url) then drop website
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('website'));
    }

    public function testHornorificInTitle(): void { // complaints about this
        $text = "{{cite book|title=Letter from Sir Frederick Trench to the Viscount Duncannon on his proposal for a quay on the north bank of the Thames|url=https://books.google.com/books?id=oNBbAAAAQAAJ|year=1841}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('Trench', $expanded->get2('last1'));
        $this->assertSame('Frederick William', $expanded->get2('first1'));
    }

    public function testPageRange(): void {
        $text = '{{Citation|doi=10.3406/befeo.1954.5607}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('405–554', $expanded->get2('pages'));
    }

    public function testStripPDF(): void {
        $text = '{{cite journal |url=https://link.springer.com/content/pdf/10.1007/BF00428580.pdf}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('10.1007/BF00428580', $prepared->get2('doi'));
    }

    public function testRemoveQuotes(): void {
        $text = '{{cite journal|title="Strategic Acupuncture"}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('"Strategic Acupuncture"', $prepared->get2('title'));
    }

    public function testRemoveQuotes2(): void {
        $text = "{{cite journal|title='Strategic Acupuncture'}}";
        $prepared = $this->prepare_citation($text);
        $this->assertSame("'Strategic Acupuncture'", $prepared->get2('title'));
    }

    public function testRemoveQuotes3(): void {
        $text = "{{cite journal|title=''Strategic Acupuncture''}}";
        $prepared = $this->prepare_citation($text);
        $this->assertSame("''Strategic Acupuncture''", $prepared->get2('title'));
    }

    public function testRemoveQuotes4(): void {
        $text = "{{cite journal|title='''Strategic Acupuncture'''}}";
        $prepared = $this->prepare_citation($text);
        $this->assertSame("Strategic Acupuncture", $prepared->get2('title'));
    }

    public function testTrimResearchGate(): void {
        $want = 'https://www.researchgate.net/publication/320041870';
        $text = '{{cite journal|url=http://researchgate.net/publication/320041870_yup}}';
        $prepared = $this->prepare_citation($text);
    }

    public function testTrimResearchGate2(): void {
        $this->assertSame($want, $prepared->get2('url'));
        $text = '{{cite journal|url=https://www.researchgate.net/profile/hello_user-person/publication/320041870_EXTRA_STUFF_ON_EN}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame($want, $prepared->get2('url'));
    }

    public function testTrimAcedamiaEdu(): void {
        $text = '{{cite web|url=http://acADemia.EDU/123456/extra_stuff}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('https://www.academia.edu/123456', $prepared->get2('url'));
    }

    public function testTrimFigShare(): void {
        $text = '{{cite journal|url=http://figshare.com/articles/journal_contribution/Volcanic_Setting_of_the_Bajo_de_la_Alumbrera_Porphyry_Cu-Au_Deposit_Farallon_Negro_Volcanics_Northwest_Argentina/22859585}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('https://figshare.com/articles/journal_contribution/22859585', $prepared->get2('url'));
    }

    public function testTrimProquestEbook1(): void {
        $text = '{{cite web|url=https://ebookcentral.proquest.com/lib/claremont/detail.action?docID=123456}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('https://public.ebookcentral.proquest.com/choice/publicfullrecord.aspx?p=123456', $prepared->get2('url'));
    }

    public function testTrimProquestEbook2(): void {
        $text = '{{cite web|url=https://ebookcentral.proquest.com/lib/claremont/detail.action?docID=123456#}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('https://public.ebookcentral.proquest.com/choice/publicfullrecord.aspx?p=123456', $prepared->get2('url'));
    }

    public function testTrimProquestEbook3(): void {
        $text = '{{cite web|url=https://ebookcentral.proquest.com/lib/claremont/detail.action?docID=123456&query=&ppg=35#}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('https://public.ebookcentral.proquest.com/choice/publicfullrecord.aspx?p=123456&query=&ppg=35', $prepared->get2('url'));
    }

    public function testTrimProquestEbook4(): void {
        $text = '{{cite web|url=http://ebookcentral-proquest-com.libproxy.berkeley.edu/lib/claremont/detail.action?docID=123456#goto_toc}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('https://public.ebookcentral.proquest.com/choice/publicfullrecord.aspx?p=123456', $prepared->get2('url'));
    }

    public function testTrimProquestEbook5(): void {
        $text = '{{cite web|url=http://ebookcentral-proquest-com.libproxy.berkeley.edu/lib/claremont/detail.action?docID=123456#goto_toc}}';
        $page = $this->process_page($text);
        $this->assertSame('Altered url. URLs might have been anonymized. Added website. | [[:en:WP:UCB|Use this bot]]. [[:en:WP:DBUG|Report bugs]]. ', $page->edit_summary());
    }

    public function testTrimGoogleStuff(): void {
        $text = '{{cite web|url=https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&btnG=&oq=%22institute+for+sustainable+weight+loss%22&aqs=chrome..69i57j69i59.14823j0j7&sourceid=chrome&ie=UTF-8&as_occt=any&cf=all&as_epq=&as_scoring=YES&as_occt=BUG&cs=0&cf=DOG&as_epq=CAT&btnK=Google+Search&btnK=DOGS&cs=CATS&resnum=#The_hash#The_second_hash}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&as_scoring=YES&as_occt=BUG&cf=DOG&as_epq=CAT&btnK=DOGS&cs=CATS#The_hash#The_second_hash', $prepared->get2('url'));
    }

    public function testDOIExtraSlash(): void {
        $text = '{{cite web|doi=10.1109//PESGM41954.2020.9281477}}';
        $prepared = $this->make_citation($text);
        $prepared->tidy_parameter('doi');
        $this->assertSame('10.1109/PESGM41954.2020.9281477', $prepared->get2('doi'));
    }

    public function testCleanRGTitles(): void {
        $text = '{{cite journal|url=http://researchgate.net/publication/320041870_yup|title=Hello {{!}} Request PDF}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('Hello', $prepared->get2('title'));
        $text = '{{cite journal|url=http://researchgate.net/publication/320041870_yup|title=(PDF) Hello}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('Hello', $prepared->get2('title'));
    }

    public function testHTMLNotLost(): void {
        $text = '{{cite journal|last1=&ndash;|first1=&ndash;|title=&ndash;|journal=&ndash;|edition=&ndash;|pages=&ndash;}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame($text, $prepared->parsed_text());
    }

    public function testTidyBookEdition(): void {
        $text = '{{cite book|title=Joe Blow (First Edition)}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('title');
        $this->assertSame('First', $template->get2('edition'));
        $this->assertSame('Joe Blow', $template->get2('title'));
    }

    public function testDoiValidation(): void {
        $text = '{{cite web|last=Daintith|first=John|title=tar|url=http://www.oxfordreference.com/view/10.1093/acref/9780199204632.001.0001/acref-9780199204632-e-4022|work=Oxford University Press|publisher=A dictionary of chemistry|edition=6th|accessdate=14 March 2013}}';
        $prepared = $this->prepare_citation($text);
        $this->assertNull($prepared->get2('doi'));
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi'));
    }

    public function testVolumeIssueDemixing1(): void {
        $text = '{{cite journal|volume = 12(44)}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('44', $prepared->get2('issue'));
        $this->assertSame('12', $prepared->get2('volume'));
    }

    public function testVolumeIssueDemixing2(): void {
        $text = '{{cite journal|volume = 12(44-33)}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('44–33', $prepared->get2('issue'));
        $this->assertSame('12', $prepared->get2('volume'));
    }

    public function testVolumeIssueDemixing3(): void {
        $text = '{{cite journal|volume = 12(44-33)| number=222}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('222', $prepared->get2('number'));
        $this->assertSame('12(44-33)', $prepared->get2('volume'));
    }

    public function testVolumeIssueDemixing4(): void {
        $text = '{{cite journal|volume = 12, no. 44-33}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('44–33', $prepared->get2('issue'));
        $this->assertSame('12', $prepared->get2('volume'));
    }

    public function testVolumeIssueDemixing5(): void {
        $text = '{{cite journal|volume = 12, no. 44-33| number=222}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('222', $prepared->get2('number'));
        $this->assertSame('12, no. 44-33', $prepared->get2('volume'));
    }

    public function testVolumeIssueDemixing6(): void {
        $text = '{{cite journal|volume = 12.33}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('33', $prepared->get2('issue'));
        $this->assertSame('12', $prepared->get2('volume'));
    }

    public function testVolumeIssueDemixing7(): void {
        $text = '{{cite journal|volume = 12.33| number=222}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('222', $prepared->get2('number'));
        $this->assertSame('12.33', $prepared->get2('volume'));
    }

    public function testVolumeIssueDemixing8(): void {
        $text = '{{cite journal|volume = Volume 12}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('12', $prepared->get2('volume'));
    }

    public function testVolumeIssueDemixing9(): void {
        $text = '{{cite book|volume = Volume 12}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('Volume 12', $prepared->get2('volume'));
    }

    public function testVolumeIssueDemixing10(): void {
        $text = '{{cite journal|volume = number 12}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('number 12', $prepared->get2('volume'));
        $this->assertNull($prepared->get2('issue'));
    }

    public function testVolumeIssueDemixing11(): void {
        $text = '{{cite journal|volume = number 12|doi=10.0000/Rubbish_bot_failure_test}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('12', $prepared->get2('issue'));
        $this->assertNull($prepared->get2('volume'));
    }

    public function testVolumeIssueDemixing12(): void {
        $text = '{{cite journal|volume = number 12|issue=12|doi=10.0000/Rubbish_bot_failure_test}}';
        $prepared = $this->prepare_citation($text);
        $this->assertNull($prepared->get2('volume'));
        $this->assertSame('12', $prepared->get2('issue'));
    }

    public function testVolumeIssueDemixing13(): void {
        $text = '{{cite journal|volume = number 12|issue=12|doi=10.0000/Rubbish_bot_failure_test}}';
        $prepared = $this->prepare_citation($text);
        $this->assertNull($prepared->get2('volume'));
        $this->assertSame('12', $prepared->get2('issue'));
    }

    public function testVolumeIssueDemixing14(): void {
        $text = '{{cite journal|issue = number 12}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('12', $prepared->get2('issue'));
    }

    public function testVolumeIssueDemixing15(): void {
        $text = '{{cite journal|volume = v. 12}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('12', $prepared->get2('volume'));
    }

    public function testVolumeIssueDemixing16(): void {
        $text = '{{cite journal|issue =(12)}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('12', $prepared->get2('issue'));
    }

    public function testVolumeIssueDemixing17(): void {
        $text = '{{cite journal|issue = volume 8, issue 7}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('7', $prepared->get2('issue'));
        $this->assertSame('8', $prepared->get2('volume'));
    }

    public function testVolumeIssueDemixing18(): void {
        $text = '{{cite journal|issue = volume 8, issue 7|volume=8}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('7', $prepared->get2('issue'));
        $this->assertSame('8', $prepared->get2('volume'));
    }

    public function testVolumeIssueDemixing19(): void {
        $text = '{{cite journal|issue = volume 8, issue 7|volume=9}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('volume 8, issue 7', $prepared->get2('issue'));
        $this->assertSame('9', $prepared->get2('volume'));
    }

    public function testVolumeIssueDemixing20(): void {
        $text = '{{cite journal|issue = number 333XV }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('333XV', $prepared->get2('issue'));
        $this->assertNull($prepared->get2('volume'));
    }

    public function testCleanUpPages(): void {
        $text = '{{cite journal|pages=p.p. 20-23}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('20–23', $prepared->get2('pages')); // Drop p.p. and upgraded dashes
    }

    public function testSpaces(): void {
        // None of the "spaces" in $text are normal spaces.   They are U+2000 to U+200A
        $text = "{{cite book|title=X X X X X X X X X X X X}}";
        $text_out = '{{cite book|title=X X X X X X X X X X X X}}';
        $expanded = $this->process_citation($text);
        $this->assertSame($text_out, $expanded->parsed_text());
        $this->assertTrue($text !== $text_out); // Verify test is valid -- We want to make sure that the spaces in $text are not normal spaces
    }

    public function testMultipleYears(): void {
        $text = '{{cite journal|doi=10.1080/1323238x.2006.11910818}}'; // Crossref has <year media_type="online">2017</year><year media_type="print">2006</year>
        $expanded = $this->process_citation($text);
        $this->assertSame('2006', $expanded->get2('date'));
    }

    public function testDuplicateParametersFlagging(): void {
        $text = '{{cite web|year=2010|year=2011}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('2011', $expanded->get2('year'));
        $this->assertSame('2010', $expanded->get2('DUPLICATE_year'));
        $text = '{{cite web|year=|year=2011}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('2011', $expanded->get2('year'));
        $this->assertNull($expanded->get2('DUPLICATE_year'));
        $text = '{{cite web|year=2011|year=}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('2011', $expanded->get2('year'));
        $this->assertNull($expanded->get2('DUPLICATE_year'));
        $text = '{{cite web|year=|year=|year=2011|year=|year=}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('2011', $expanded->get2('year'));
        $this->assertNull($expanded->get2('DUPLICATE_year'));
        $text = '{{cite web|year=|year=|year=|year=|year=}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('{{cite web|year=}}', $expanded->parsed_text());
    }

    public function testBadPMIDSearch(): void { // Matches a PMID search
        $text = '{{cite journal |author=Fleming L |title=Ciguatera Fish Poisoning}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('pmid'));
        $this->assertNull($expanded->get2('doi'));
    }

    public function testDoiThatIsJustAnISSN(): void {
        $text = '{{cite web |url=http://onlinelibrary.wiley.com/journal/10.1002/(ISSN)1099-0739/homepage/EditorialBoard.html}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi'));
        $this->assertSame('http://onlinelibrary.wiley.com/journal/10.1002/(ISSN)1099-0739/homepage/EditorialBoard.html', $expanded->get2('url'));
        $this->assertSame('cite web', $expanded->wikiname());
    }

    public function testEditors_1(): void {
        $text = '{{cite journal|editor3=Set}}';
        $prepared = $this->prepare_citation($text);
        $prepared->add_if_new('editor-last3', 'SetItL');
        $prepared->add_if_new('editor-first3', 'SetItF');
        $prepared->add_if_new('editor3', 'SetItN');
        $this->assertSame('Set', $prepared->get2('editor3'));
        $this->assertNull($prepared->get2('editor-last3'));
        $this->assertNull($prepared->get2('editor-first3'));
    }

    public function testEditors_2(): void {
        $text = '{{cite journal}}';
        $prepared = $this->prepare_citation($text);
        $prepared->add_if_new('editor-last3', 'SetItL');
        $prepared->add_if_new('editor-first3', 'SetItF');
        $prepared->add_if_new('editor3', 'SetItN'); // Should not get set
        $this->assertSame('SetItL', $prepared->get2('editor-last3'));
        $this->assertSame('SetItF', $prepared->get2('editor-first3'));
        $this->assertNull($prepared->get2('editor3'));
    }

    public function testEditors_3(): void {
        $text = '{{cite journal}}';
        $prepared = $this->prepare_citation($text);
        $prepared->add_if_new('editor-last33', 'SetIt'); // Huge number
        $this->assertNull($prepared->get2('editor-last33'));
        $this->assertNull($prepared->get2('display-editors'));
    }

    public function testEditors_4(): void {
        $text = '{{cite journal|editor29=dfasddsfadsd}}';
        $prepared = $this->prepare_citation($text);
        $prepared->add_if_new('editor-last33', 'SetIt');
        $this->assertNull($prepared->get2('editor-last33'));
        $this->assertSame('1', $prepared->get2('display-editors'));
    }

    public function testAddPages(): void {
        $text = '{{Cite journal|pages=1234-9}}';
        $prepared = $this->prepare_citation($text);
        $prepared->add_if_new('pages', '1230-1240');
        $this->assertSame('1234–9', $prepared->get2('pages'));
    }

    public function testAddPages2(): void {
        $text = '{{Cite journal|pages=1234-44}}';
        $prepared = $this->prepare_citation($text);
        $prepared->add_if_new('pages', '1230-1270');
        $this->assertSame('1234–44', $prepared->get2('pages'));
    }

    public function testAddPages3(): void {
        $text = '{{Cite journal|page=1234}}';
        $prepared = $this->prepare_citation($text);
        $prepared->add_if_new('pages', '1230-1270');
        $this->assertSame('1234', $prepared->get2('page'));
    }

    public function testAddPages4(): void {
        $text = '{{Cite journal|page=1234}}';
        $prepared = $this->prepare_citation($text);
        $prepared->add_if_new('pages', '1230-70');
        $this->assertSame('1234', $prepared->get2('page'));
    }

    public function testAddPages5(): void {
        $text = '{{Cite journal|page=1234}}';
        $prepared = $this->prepare_citation($text);
        $prepared->add_if_new('pages', '1230-9');
        $this->assertSame('1234', $prepared->get2('page'));
    }

    public function testAddBibcode(): void {
        $text = '{{Cite journal|bibcode=1arxiv1}}';
        $prepared = $this->make_citation($text);
        $prepared->add_if_new('bibcode_nosearch', '1234567890123456789');
        $this->assertSame('1234567890123456789', $prepared->get2('bibcode'));
    }

    public function testAddBibcode2(): void {
        $text = '{{Cite journal}}';
        $prepared = $this->make_citation($text);
        $prepared->add_if_new('bibcode_nosearch', '1arxiv1');
        $this->assertNull($prepared->get2('bibcode'));
    }

    public function testEdition(): void {
        $text = '{{Cite journal}}';
        $prepared = $this->prepare_citation($text);
        $this->assertTrue($prepared->add_if_new('edition', '1'));
        $this->assertSame('1', $prepared->get2('edition'));
        $this->assertFalse($prepared->add_if_new('edition', '2'));
        $this->assertSame('1', $prepared->get2('edition'));
    }

    public function testFixRubbishVolumeWithDoi(): void {
        $text = '{{Cite journal|doi= 10.1136/bmj.2.3798.759-a |volume=3798 |issue=3798}}';
        $template = $this->prepare_citation($text);
        $template->final_tidy();
        $this->assertSame('3798', $template->get2('issue'));
        $this->assertSame('2', $template->get2('volume'));
    }

    public function testHandles1(): void {
        $template = $this->make_citation('{{Cite web|url=http://hdl.handle.net/10125/20269////;jsessionid=dfasddsa|journal=X}}');
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertSame('10125/20269', $template->get2('hdl'));
        $this->assertSame('cite web', $template->wikiname());
        $this->assertNotNull($template->get2('url'));
    }

    public function testHandles2(): void {
        $template = $this->make_citation('{{Cite web|url=https://hdl.handle.net/handle////10125/20269}}');
        $template->get_identifiers_from_url();
        if ($template->get2('hdl') !== '10125/20269') {
            sleep(15);
            $template->get_identifiers_from_url(); // This test is finicky sometimes
        }
        if ($template->get2('hdl') !== '10125/20269') {
            sleep(15);
            $template->get_identifiers_from_url(); // This test is finicky sometimes
        }
        $this->assertSame('cite web', $template->wikiname());
        $this->assertSame('10125/20269', $template->get2('hdl'));
        $this->assertNotNull($template->get2('url'));
    }

    public function testHandles3(): void {
        $template = $this->make_citation('{{Cite journal|url=http://hdl.handle.net/handle/10125/dfsjladsflhdsfaewfsdfjhasjdfhldsaflkdshkafjhsdjkfhdaskljfhdsjklfahsdafjkldashafldsfhjdsa_TEST_DATA_FOR_BOT_TO_FAIL_ON}}');
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertSame('http://hdl.handle.net/handle/10125/dfsjladsflhdsfaewfsdfjhasjdfhldsaflkdshkafjhsdjkfhdaskljfhdsjklfahsdafjkldashafldsfhjdsa_TEST_DATA_FOR_BOT_TO_FAIL_ON', $template->get2('url'));
        $this->assertNull($template->get2('hdl'));
    }

    public function testHandles4(): void {
        $template = $this->make_citation('{{Cite journal|url=https://scholarspace.manoa.hawaii.edu/handle/10125/20269}}');
        $template->get_identifiers_from_url();
        $this->assertSame('10125/20269', $template->get2('hdl'));
        $this->assertNotNull($template->get2('url'));
    }

    public function testHandles5(): void {
        $template = $this->make_citation('{{Cite journal|url=http://hdl.handle.net/2027/loc.ark:/13960/t6349vh5n?urlappend=%3Bseq=672}}');
        $template->get_identifiers_from_url();
        $this->assertSame('2027/loc.ark:/13960/t6349vh5n?urlappend=%3Bseq=672', $template->get2('hdl'));
        $this->assertNotNull($template->get2('url'));
    }

    public function testAuthorToLast(): void {
        $text = '{{Cite journal|author1=Last|first1=First}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('Last', $template->get2('last1'));
        $this->assertSame('First', $template->get2('first1'));
        $this->assertNull($template->get2('author1'));
    }

    public function testAuthorToLast2(): void {
        $text = '{{Cite journal|author1=Last|first2=First}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('Last', $template->get2('author1'));
        $this->assertSame('First', $template->get2('first2'));
        $this->assertNull($template->get2('last1'));
    }

    public function testAddArchiveDate(): void {
        $text = '{{Cite web|archive-url=https://web.archive.org/web/20190521084631/https://johncarlosbaez.wordpress.com/2018/09/20/patterns-that-eventually-fail/|archive-date=}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('21 May 2019', $template->get2('archive-date'));
    }

    public function testAddArchiveDate2(): void {
        $text = '{{Cite web|archive-url=https://wayback.archive-it.org/4554/20190521084631/https://johncarlosbaez.wordpress.com/2018/09/20/patterns-that-eventually-fail/|archive-date=}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('21 May 2019', $template->get2('archive-date'));
    }

    public function testAddWebCiteDate(): void {
        $text = '{{Cite web|archive-url=https://www.webcitation.org/6klgx4ZPE}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('24 September 2016', $template->get2('archive-date'));
    }

    public function testJunkData(): void {
        $text = "{{Cite web | title=JSTOR THIS IS A LONG TITLE IN ALL CAPPS AND IT IS BAD|journal=JSTOR|pmid=1974135}} " .
                        "{{Cite web | title=JSTOR This is bad data|journal=JSTOR This is bad data|jstor=1974136}}" .
                        "{{Cite web | title=JSTOR This is a title on JSTOR|pmc=1974137}}" .
                        "{{Cite web | title=JSTOR This is a title with IEEE Xplore Document|pmid=1974138}}" .
                        "{{Cite web | title=IEEE Xplore This is a title with Document|pmid=1974138}}" .
                        "{{Cite web | title=JSTOR This is a title document with Volume 3 and page 5|doi= 10.1021/jp101758y}}";
        $page = $this->process_page($text);
        if (mb_substr_count($page->parsed_text(), 'JSTOR') !== 0) {
            sleep(3);
            $text = $page->parsed_text();
            $page = $this->process_page($text);
        }
        $this->assertSame(0, mb_substr_count($page->parsed_text(), 'JSTOR'));
    }

    public function testJunkData2(): void {
        $text = "{{cite journal|doi=10.1016/j.bbagen.2019.129466|journal=Biochimica Et Biophysica Acta|title=Shibboleth Authentication Request}}";
        $template = $this->process_citation($text);
        $this->assertSame('Biochimica et Biophysica Acta (BBA) - General Subjects', $template->get2('journal'));
        $this->assertSame('Time-resolved studies of metalloproteins using X-ray free electron laser radiation at SACLA', $template->get2('title'));
    }

    public function testISSN(): void {
        $text = '{{Cite journal|journal=Yes}}';
        $template = $this->prepare_citation($text);
        $template->add_if_new('issn', '1111-2222');
        $this->assertNull($template->get2('issn'));
        $template->add_if_new('issn_force', '1111-2222');
        $this->assertSame('1111-2222', $template->get2('issn'));
        $text = '{{Cite journal|journal=Yes}}';
        $template = $this->prepare_citation($text);
        $template->add_if_new('issn_force', 'EEEE-3333'); // Won't happen
        $this->assertNull($template->get2('issn'));
    }

    public function testURLS_1(): void {
        $text = '{{cite journal|conference-url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('1234', $template->get2('mr'));
    }

    public function testURLS_2(): void {
        $text = '{{cite journal|conferenceurl=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('1234', $template->get2('mr'));
    }

    public function testURLS_3(): void {
        $text = '{{cite journal|contribution-url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('1234', $template->get2('mr'));
    }

    public function testURLS_4(): void {
        $text = '{{cite journal|contributionurl=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('1234', $template->get2('mr'));
    }

    public function testURLS_5(): void {
        $text = '{{cite journal|article-url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('1234', $template->get2('mr'));
    }

    public function testlooksLikeBookReview(): void {
        $text = '{{cite journal|journal=X|url=book}}';
        $template = $this->make_citation($text);
        $record = (object) null;
        $this->assertFalse(looksLikeBookReview($template, $record));
    }

    public function testlooksLikeBookReview2(): void {
        $text = '{{cite journal|journal=X|url=book|year=2002|isbn=x|location=x|oclc=x}}';
        $template = $this->make_citation($text);
        $record = (object) null;
        $record->year = '2000';
        $this->assertFalse(looksLikeBookReview($template, $record));
    }

    public function testlooksLikeBookReview3(): void {
        $text = '{{cite book|journal=X|url=book|year=2002|isbn=x|location=x|oclc=x}}';
        $template = $this->make_citation($text);
        $record = (object) null;
        $record->year = '2000';
        $this->assertTrue(looksLikeBookReview($template, $record));
    }

    public function testDropBadDq(): void {
        $text = '{{Cite web | url=https://books.google.com/books?id=SjpSkzjIzfsC&dq=subject:HUH&pg=213}}';
        $template = $this->process_citation($text);
        $this->assertSame('https://books.google.com/books?id=SjpSkzjIzfsC&pg=213', $template->get2('url'));
    }

    public function testBlankOtherThanComments(): void {
        $text_in = "{{cite journal| title= # # # CITATION_BOT_PLACEHOLDER_COMMENT 1 # # #   # # # CITATION_BOT_PLACEHOLDER_COMMENT 2 # # # | journal= | issue=3 # # # CITATION_BOT_PLACEHOLDER_COMMENT 3 # # #| volume=65 |lccn= # # # CITATION_BOT_PLACEHOLDER_COMMENT 4 # # # cow # # # CITATION_BOT_PLACEHOLDER_COMMENT 5 # # # }}";
        $template = $this->make_citation($text_in); // Have to explicitly set comments above since Page() encodes and then decodes them
        $this->assertTrue($template->blank_other_than_comments('isbn'));
        $this->assertTrue($template->blank_other_than_comments('title'));
        $this->assertTrue($template->blank_other_than_comments('journal'));
        $this->assertFalse($template->blank_other_than_comments('issue'));
        $this->assertFalse($template->blank_other_than_comments('volume'));
        $this->assertFalse($template->blank_other_than_comments('lccn'));
    }

    public function testCleanUpSomeURLS1(): void {
        $text_in = "{{cite web| url = https://www.youtube.com/watch%3Fv=9NHSOrUHE6c}}";
        $template = $this->make_citation($text_in);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.youtube.com/watch?v=9NHSOrUHE6c', $template->get2('url'));
    }

    public function testCleanUpSomeURLS2(): void {
        $text_in = "{{cite web| url = https://www.springer.com/abc#citeas}}";
        $template = $this->make_citation($text_in);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.springer.com/abc', $template->get2('url'));
    }

    public function testCleanUpSomeURLS3(): void {
        $text_in = "{{cite web| url = https://www.springer.com/abc#citeas}}";
        $template = $this->make_citation($text_in);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.springer.com/abc', $template->get2('url'));
    }

    public function testTidyPageRangeLookLikePage(): void {
        $text_in = "{{cite web| page=333-444}}";
        $template = $this->make_citation($text_in);
        $template->tidy_parameter('page');
        $this->assertSame('333-444', $template->get2('page'));
        $this->assertNull($template->get2('pages'));
    }

    public function testTidyPageRangeLookLikePage2(): void {
        $text_in = "{{cite web| page=333–444}}";
        $template = $this->make_citation($text_in);
        $template->tidy_parameter('page');
        $this->assertSame('333–444', $template->get2('pages'));
        $this->assertSame('', $template->get('page'));
    }

    public function testTidyPageRangeLookLikePage3(): void {
        $text_in = "{{cite web| page=1-444}}";
        $template = $this->make_citation($text_in);
        $template->tidy_parameter('page');
        $this->assertSame('1-444', $template->get2('page'));
        $this->assertNull($template->get2('pages'));
    }

    public function testTidyPageRangeLookLikePage4(): void {
        $text_in = "{{cite web| page=1–444}}";
        $template = $this->make_citation($text_in);
        $template->tidy_parameter('page');
        $this->assertSame('1–444', $template->get2('page'));
        $this->assertNull($template->get2('pages'));
    }

    public function testTidyGoofyFirsts1(): void {
        $text_in = "{{Citation | last1=[[Hose|Dude]]|first1=[[John|Girl]] }}";
        $template = $this->process_citation($text_in);
        $this->assertSame('{{Citation |author1-link=Hose | last1=Dude|first1=Girl }}', $template->parsed_text());
    }

    public function testTidyGoofyFirsts2(): void {
        $text_in = "{{Citation | last1=[[Hose|Dude]]|first1=[[John]] }}";
        $template = $this->process_citation($text_in);
        $this->assertSame('{{Citation |author1-link=Hose | last1=Dude|first1=John }}', $template->parsed_text());
    }

    public function testDashIsEquals(): void {
        $text_in = "{{cite journal|archive=url=https://xy.com }}";
        $template = $this->process_citation($text_in);
        $this->assertSame("https://xy.com", $template->get2('archive-url'));
        $this->assertNull($template->get2('archive'));
    }

    public function testDashIsEquals2(): void {
        $text_in = "{{cite news|archive=url=https://xy.com }}";
        $template = $this->process_citation($text_in);
        $this->assertSame("https://xy.com", $template->get2('archive-url'));
        $this->assertNull($template->get2('archive'));
    }

    public function testModsArray(): void {
        $text = '{{cite journal | citation_bot_placeholder_bare_url = XYX }}';
        $template = $this->make_citation($text);
        $template->add('title', 'Thus');
        $this->assertNotNull($template->get2('citation_bot_placeholder_bare_url'));
        $array = $template->modifications();
        $expected = [ 'modifications' => [0 => 'title',  ],
                            'additions' => [0 => 'title',  ],
                            'deletions' => [0 => 'citation_bot_placeholder_bare_url', ],
                            'changeonly' => [],
                            'dashes' => false,
                            'names' => false];
        $this->assertEqualsCanonicalizing($expected, $array);
        $this->assertNull($template->get2('citation_bot_placeholder_bare_url'));
    }

    public function testMistakesWeDoNotFix(): void {
        $text = '{{new cambridge medieval history|ed10=That Guy}}';
        $template = $this->prepare_citation($text);
        $array = $template->modifications();
        $expected = ['modifications' => [], 'additions' => [], 'deletions' => [], 'changeonly' => [], 'dashes' => false, 'names' => false];
        $this->assertEqualsCanonicalizing($expected, $array);
    }

    public function testChaptURLisDup(): void {
        $text = "{{cite book|url=https://www.cnn.com/ }}";
        $template = $this->make_citation($text);
        get_unpaywall_url($template, '10.1007/978-3-319-18111-0_47');
        $this->assertFalse($template->add_if_new('chapter-url', 'https://www.cnn.com/'));
        $this->assertNull($template->get2('chapter-url'));
    }

    public function testGoogleBadAuthor(): void {
        $text = "{{cite book|url=https://books.google.com/books?id=5wllAAAAcAAJ }}";
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('last1'));
        $this->assertNull($template->get2('last'));
        $this->assertNull($template->get2('author1'));
        $this->assertNull($template->get2('author'));
        $this->assertNull($template->get2('first1'));
        $this->assertNull($template->get2('first'));
        $this->assertNotNull($template->get2('title'));
    }

    public function testDoiHasNoLastFirstSplit(): void {
        $text = "{{cite journal|doi=10.11468/seikatsueisei1925.16.2_123}}";
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('last1'));
        $this->assertNull($template->get2('last'));
        $this->assertNull($template->get2('author'));
        $this->assertNull($template->get2('first1'));
        $this->assertNull($template->get2('first'));
        $this->assertSame("大阪市立衛生試験所", $template->get2('author1'));
    }

    public function testArxivHasDOIwithoutData(): void { // This doi is dead, so it takes different path in code
        $text = '{{citation|arxiv=2202.10024|title=TESS discovery of a sub-Neptune orbiting a mid-M dwarf TOI-2136}}';
        $template = $this->process_citation($text);
        $this->assertSame("''TESS'' discovery of a sub-Neptune orbiting a mid-M dwarf TOI-2136", $template->get2('title'));
        $this->assertSame('10.1093/mnras/stac1448', $template->get2('doi'));
    }

    public function testChapterCausesBookInFinal(): void {
        $text = '{{cite journal |last1=Délot |first1=Emmanuèle C |last2=Vilain |first2=Eric J |title=Nonsyndromic 46,XX Testicular Disorders of Sex Development |chapter=Nonsyndromic 46,XX Testicular Disorders/Differences of Sex Development |journal=GeneReviews |date=2003 |url=https://www.ncbi.nlm.nih.gov/books/NBK1416/ |access-date=6 December 2018 |archive-date=23 June 2020 |archive-url=https://web.archive.org/web/20200623171901/https://www.ncbi.nlm.nih.gov/books/NBK1416/ |url-status=live }}';
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame('cite book', $template->wikiname());
    }

    public function testACMConfWithDash(): void {
        $text = '{{cite journal |title=Proceedings of the 1964 19th ACM national conference on - }}';
        $template = $this->process_citation($text);
        $this->assertSame('Proceedings of the 1964 19th ACM national conference', $template->get2('title'));
    }

    public function testACMConfWithDash2(): void {
        $text = '{{cite conference |title= }}';
        $template = $this->make_citation($text);
        $template->add_if_new('title', 'Proceedings of the 1964 19th ACM national conference on -');
        $this->assertSame('Proceedings of the 1964 19th ACM national conference', $template->get2('title'));
    }

    public function testNullDOInoCrash(): void { // This DOI does not work, but CrossRef does have a record
        $text = '{{cite journal | doi=10.5604/01.3001.0012.8474 |doi-broken-date=<!-- --> }}';
        $template = $this->process_citation($text);
        $this->assertSame('{{cite journal |last1=Kofel |first1=Dominika |title=To Dye or Not to Dye: Bioarchaeological Studies of Hala Sultan Tekke Site, Cyprus |journal=Światowit |date=2019 |volume=56 |pages=89–98 | doi=10.5604/01.3001.0012.8474 |doi-broken-date=<!-- --> }}', $template->parsed_text());
    }

    public function testTidySomeStuff(): void {
        $text = '{{cite journal | url=http://pubs.rsc.org/XYZ#!divAbstract}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://pubs.rsc.org/XYZ', $template->get2('url'));
    }

    public function testTidySomeStuff2(): void {
        $text = '{{cite journal | url=http://pubs.rsc.org/XYZ/unauth}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://pubs.rsc.org/XYZ', $template->get2('url'));
    }

    public function testTidyPreferVolumes_1(): void {
        $text = '{{cite journal | journal=Illinois Classical Studies|issue=3|volume=3}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('volume');
        $this->assertNull($template->get2('issue'));
    }

    public function testTidyPreferVolumes_2(): void {
        $text = '{{cite journal | journal=Illinois Classical Studies|number=3|volume=3}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('volume');
        $this->assertNull($template->get2('number'));
    }

    public function testTidyPreferVolumes_3(): void {
        $text = '{{cite journal | journal=Illinois Classical Studies|issue=3|volume=3}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('issue');
        $this->assertNull($template->get2('issue'));
    }

    public function testTidyPreferVolumes_4(): void {
        $text = '{{cite journal | journal=Illinois Classical Studies|number=3|volume=3}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('number');
        $this->assertNull($template->get2('number'));
    }

    public function testTidyPreferIssues_1(): void {
        $text = '{{cite journal | journal=Mammalian Species|issue=3|volume=3}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('volume');
        $this->assertNull($template->get2('volume'));
    }

    public function testTidyPreferIssues_2(): void {
        $text = '{{cite journal | journal=Mammalian Species|number=3|volume=3}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('volume');
        $this->assertNull($template->get2('volume'));
    }

    public function testTidyPreferIssues_3(): void {
        $text = '{{cite journal | journal=Mammalian Species|issue=3|volume=3}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('issue');
        $this->assertNull($template->get2('volume'));
    }

    public function testTidyPreferIssues_4(): void {
        $text = '{{cite journal | journal=Mammalian Species|number=3|volume=3}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('number');
        $this->assertNull($template->get2('volume'));
    }

    public function testDoiInline2(): void {
        $text = '{{citation | title = {{doi-inline|10.1038/nphys806|A transient semimetallic layer in detonating nitromethane}} | doi=10.1038/nphys806 }}';
        $expanded = $this->process_citation($text);
        $this->assertSame('Nature Physics', $expanded->get2('journal'));
        $this->assertSame('A transient semimetallic layer in detonating nitromethane', $expanded->get2('title'));
        $this->assertSame('10.1038/nphys806', $expanded->get2('doi'));
    }

    public function testTidyBogusDOIs3316(): void {
        $text = '{{cite journal | doi=10.3316/informit.324214324123413412313|pmc=XXXXX}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertNull($template->get2('doi'));
    }

    public function testInvoke1(): void {
        $text = "{{#invoke:Cite web||S2CID=X}}";
        $expanded = $this->process_citation($text);
        $this->assertFalse($expanded->add_if_new('s2cid', 'Z')); // Do something random
        $this->assertSame("{{#invoke:Cite web||s2cid=X}}", $expanded->parsed_text());
    }

    public function testInvoke2(): void {
        $text = "{{#invoke:Cite web|| jstor=1701972 |s2cid= <!-- --> }}";
        $expanded = $this->process_citation($text);
        $this->assertSame('cite journal', $expanded->wikiname());
        $this->assertSame('{{#invoke:Cite journal|| jstor=1701972 |s2cid= <!-- --> | title=Early Insect Diversification: Evidence from a Lower Devonian Bristletail from Québec | last1=Labandeira | first1=Conrad C. | last2=Beall | first2=Bret S. | last3=Hueber | first3=Francis M. | journal=Science | date=1988 | volume=242 | issue=4880 | pages=913–916 | doi=10.1126/science.242.4880.913 }}', $expanded->parsed_text());
    }

    public function testInvoke3(): void {
        $text = "<ref>{{#invoke:cite||title=X}}{{#invoke:Cite book||title=X}}{{Cite book||title=X}}{{#invoke:Cite book||title=X}}{{#invoke:Cite book || title=X}}{{#invoke:Cite book ||title=X}}{{#invoke:Cite book|| title=X}}<ref>";
        $page = $this->process_page($text);
        $this->assertSame("<ref>{{#invoke:cite|title=X}}{{#invoke:Cite book||title=X}}{{Cite book|title=X}}{{#invoke:Cite book||title=X}}{{#invoke:Cite book || title=X}}{{#invoke:Cite book ||title=X}}{{#invoke:Cite book|| title=X}}<ref>", $page->parsed_text());
    }

    public function testInvoke4(): void {
        $text = "{{#invoke:Dummy|Y=X}}{{#invoke:Oddity|Y=X}}{{#invoke:Cite dummy||Y=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testInvoke5(): void {
        $text = "{{#invoke:Dummy\n\t\r | \n\r\t|\n\t\r Y=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testInvoke6(): void {
        $text = "{{#invoke:Oddity\n \r\t|\n \t\rY=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testInvoke7(): void {
        $text = "{{#invoke:Cite dummy\n\t\r | \n\r\t |\n\r\t Y=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testVADuplicate(): void {
        $text = "{{cs1 config|name-list-style=vanc}}<ref>https://pmc.ncbi.nlm.nih.gov/articles/PMC11503076/</ref>{{cs1 config|name-list-style=vanc}}";
        $page = $this->process_page($text);
        $this->assertSame("{{cs1 config|name-list-style=vanc}}<ref>{{cite journal | title=From fibrositis to fibromyalgia to nociplastic pain: How rheumatology helped get us here and where do we go from here? | journal=Annals of the Rheumatic Diseases | date=2024 | volume=83 | issue=11 | pages=1421–1427 | doi=10.1136/ard-2023-225327 | pmid=39107083 | pmc=11503076 | vauthors = Clauw DJ }}</ref>{{cs1 config|name-list-style=vanc}}", $page->parsed_text());
    }
}
