<?php
declare(strict_types=1);

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../../testBaseClass.php';

final class TemplatePart1Test extends testBaseClass {

    public function testLotsOfFloaters2(): void {
        $text_in = '{{cite journal|isssue 3 volumee 5 | tittle Love|journall Dog|series Not mine today|chapte cows|this is random stuff | zauthor Joe }}';
        $text_out= '{{cite journal| journal=L Dog | series=Not mine today |isssue 3 volumee 5 | tittle Love|chapte cows|this is random stuff | zauthor Joe }}';
        $prepared = $this->prepare_citation($text_in);
        $this->assertSame($text_out, $prepared->parsed_text());
    }

    public function testLotsOfFloaters3(): void {
        $text_in = "{{cite journal| 123-4567-890-123 }}";
        $prepared = $this->prepare_citation($text_in);
        $this->assertSame('123-4567-890-123', $prepared->get2('isbn'));
    }

    public function testLotsOfFloaters4(): void {
        $text_in = "{{cite journal| 123-4567-8901123 }}"; // 13 numbers
        $prepared = $this->prepare_citation($text_in);
        $this->assertSame($text_in, $prepared->parsed_text());
    }

    public function testLotsOfFloaters5(): void {
        $text_in = "{{cite journal| 12345678901 }}"; // 11 numbers
        $prepared = $this->prepare_citation($text_in);
        $this->assertSame($text_in, $prepared->parsed_text());
    }

    public function testLotsOfFloaters6(): void {
        $text_in = "{{cite journal| url=http://www.cnn.com | accessdate 24 Nov 2020}}";
        $prepared = $this->prepare_citation($text_in);
        $this->assertSame('24 November 2020', $prepared->get2('access-date'));
        $this->assertNull($prepared->get2('accessdate'));
    }

    public function testLotsMagazines(): void {
        $text_in = "{{cite journal| journal=The New Yorker}}";
        $prepared = $this->process_citation($text_in);
        $this->assertSame('{{cite magazine| magazine=The New Yorker}}', $prepared->parsed_text());
        $text_in = "{{cite journal| periodical=The Economist}}";
        $prepared = $this->process_citation($text_in);
        $this->assertSame('{{cite news| newspaper=The Economist}}', $prepared->parsed_text());
    }

    public function testParameterWithNoParameters(): void {
        $text = "{{Cite web | text without equals sign  }}";
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
        $text = "{{   No pipe }}";
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testDashedTemplate(): void {
        $text = "{{cite_news}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{cite news}}", $expanded->parsed_text());
    }

    public function testTemplateConvertComplex2a(): void {
        $text = "{{cite document}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{cite document}}", $expanded->parsed_text());

        $text = "{{cite document|doi=XXX/978-XXX}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("cite book", $expanded->wikiname());
        $this->assertNotNull($expanded->get2('doi-broken-date')); // This one gets "move perm.." from dx.doi.org, and is bogus
    }

    public function testDOIsMovedStillOkay(): void { // This one gets "move perm.." from dx.doi.org, and works
        $text = "{{cite journal|doi=10.1016/j.chaos.2004.07.021}}";
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi-broken-date'));
    }

    public function testHDLnotBroken() {
        $text = "{{cite document|doi=20.1000/100}}";
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi-broken-date'));
    }

    public function testTemplateConvertComplex2b(): void {
        $text = "{{cite document|journal=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("cite journal", $expanded->wikiname());

        $text = "{{cite document|newspaper=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("cite news", $expanded->wikiname());

        $text = "{{cite document|chapter=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("cite book", $expanded->wikiname());
    }

    public function testTemplateConvertComplex2c(): void {
        $text = "{{Cite document}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{Cite document}}", $expanded->parsed_text());

        $text = "{{Cite document|doi=XXX/978-XXX}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("cite book", $expanded->wikiname());

        $text = "{{Cite document|journal=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("cite journal", $expanded->wikiname());

        $text = "{{Cite document|newspaper=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("cite news", $expanded->wikiname());

        $text = "{{Cite document|chapter=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("cite book", $expanded->wikiname());
    }

    public function testPureGarbage1(): void {
        $text = "{{cite journal|title=Bloomberg - Are you a robot?}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{cite journal|title=}}", $expanded->parsed_text());
    }

    public function testPureGarbage2(): void {
        $text = "{{cite journal|title=Wayback Machine}}";
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testPureGarbage3(): void {
        $text = "{{cite journal|title=Wayback Machine|archive-url=XXX}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('', $expanded->get2('title'));
    }

    public function testNoGoonUTF8(): void {
        $text = "{{cite news |date=びっくり１位 白鴎|title=阪神びっくり１位 白鴎大・大山、鉄人魂の持ち主だ|journal=鉄人魂}}";
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testAddTitleSameAsWork(): void {
        $text = "{{Cite web|work=John Boy}}";
        $expanded = $this->make_citation($text);
        $this->assertFalse($expanded->add_if_new('title', 'John boy'));
        $this->assertNull($expanded->get2('title'));
    }

    public function testTitleOfNone1(): void {
        $text = "{{Cite web|title=none}}";// none is a magic flag
        $expanded = $this->make_citation($text);
        $this->assertFalse($expanded->add_if_new('url', 'https://www.apple.com/'));
        $this->assertNull($expanded->get2('url'));
    }
    public function testTitleOfNone2(): void {
        $text = "{{Cite web|title=None}}";
        $expanded = $this->make_citation($text); // None is not a magic flag
        $this->assertTrue($expanded->add_if_new('url', 'https://www.apple.com/'));
        $this->assertSame('https://www.apple.com/', $expanded->get2('url'));
    }

    public function testTitleLink(): void {
        $text = "{{Cite web|url=X}}";
        $expanded = $this->make_citation($text);
        $this->assertFalse($expanded->add_if_new('title-link', 'x'));
        $text = "{{Cite web}}";
        $expanded = $this->make_citation($text);
        $this->assertTrue($expanded->add_if_new('title-link', 'x'));
    }

    public function testAddAuthorAgain(): void {
        $text = "{{Cite web|last1=X}}";
        $expanded = $this->process_citation($text);
        $this->assertFalse($expanded->add_if_new('last1', 'Z'));
    }

    public function testAddAuthorAgainDiff1(): void {
        $text = "{{Cite web|last1=X}}";
        $expanded = $this->make_citation($text);
        $this->assertFalse($expanded->add_if_new('author1', 'Z'));
    }

    public function testAddAuthorAgainDiff2(): void {
        $text = "{{Cite web|author1=X}}";
        $expanded = $this->make_citation($text);
        $this->assertFalse($expanded->add_if_new('last1', 'Z'));
    }

    public function testAddS2CIDAgain(): void {
        $text = "{{Cite web|S2CID=X}}";
        $expanded = $this->process_citation($text);
        $this->assertFalse($expanded->add_if_new('s2cid', 'Z'));
    }

    public function testDotInVolumeIssue(): void {
        $text = "{{Cite web|issue=1234.|volume=2341.}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('1234', $expanded->get2('issue'));
        $this->assertSame('2341', $expanded->get2('volume'));
    }

    public function testBadPMID(): void {
        $text = "{{Cite web|url=https://www.ncbi.nlm.nih.gov/pubmed/?term=Sainis%20KB%5BAuthor%5D&cauthor=true&cauthor_uid=19447493}}";
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('pmid'));
        $this->assertNull($expanded->get2('pmc'));
        $this->assertSame('https://pubmed.ncbi.nlm.nih.gov/?term=Sainis%20KB%5BAuthor%5D&cauthor=true&cauthor_uid=19447493', $expanded->get2('url'));
    }

    public function testJournal2Web(): void {
        $text = "{{Cite journal|journal=www.cnn.com}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('www.cnn.com', $expanded->get2('website'));
    }

    public function testCleanUpTemplates1(): void {
        $text = "{{Citeweb}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{Cite web}}", $expanded->parsed_text());
        $text = "{{citeweb}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{cite web}}", $expanded->parsed_text());
        $text = "{{cite}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{citation}}", $expanded->parsed_text());
        $text = "{{Cite}}";
        $expanded = $this->process_citation($text);
    }

    public function testCleanUpTemplates2(): void {
        $text = "{{Citeweb|page=2}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{Cite web|page=2}}", $expanded->parsed_text());
        $text = "{{citeweb|page=2}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{cite web|page=2}}", $expanded->parsed_text());
        $text = "{{cite|page=2}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{citation|page=2}}", $expanded->parsed_text());
        $text = "{{Cite|page=2}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{Citation|page=2}}", $expanded->parsed_text());
    }

    public function testCleanUpTemplates3(): void {
        $text = "{{Citeweb |page=2}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{Cite web |page=2}}", $expanded->parsed_text());
        $text = "{{citeweb |page=2}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{cite web |page=2}}", $expanded->parsed_text());
        $text = "{{cite |page=2}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{citation |page=2}}", $expanded->parsed_text());
        $text = "{{Cite |page=2}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{Citation |page=2}}", $expanded->parsed_text());
    }

    public function testCleanUpTemplates4(): void {
        $text = "{{Cite   web |p=2}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{Cite web |page=2}}", $expanded->parsed_text());
        $text = "{{cite   web |p=2}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{cite web |page=2}}", $expanded->parsed_text());
    }

    public function testGetDoiFromCrossref(): void {
        $text = '{{Cite journal | last1 = Glaesemann | first1 = K. R. | last2 = Fried | first2 = L. E. | doi = | title = Improved wood–kirkwood detonation chemical kinetics | journal = Theoretical Chemistry Accounts | volume = 120 | pages = 37–43 | year = 2007 |issue=1–3}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('10.1007/s00214-007-0303-9', $expanded->get2('doi'));
        $this->assertNull($expanded->get2('pmid')); // do not want reference where pmid leads to doi
        $this->assertNull($expanded->get2('bibcode'));
        $this->assertNull($expanded->get2('pmc'));
    }

    public function testJstorExpansion3(): void {
        $text = "{{Cite web | url = http://www.jstor.org/stable/10.1017/s0022381613000030}}";
        $prepared = $this->prepare_citation($text);
        $this->assertSame('10.1017/s0022381613000030', $prepared->get2('jstor'));
    }


    public function testDropWeirdJunk(): void {
        $text = "{{cite web |title=Left Handed Incandescent Light Bulbs?|last=|first=|date=24 March 2011 |publisher=eLightBulbs |last1=Eisenbraun|first1=Blair|accessdate=27 July 2016}}";
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('last'));
        $this->assertNull($expanded->get2('first'));
        $this->assertSame('Blair', $expanded->get2('first1'));
        $this->assertSame('Eisenbraun', $expanded->get2('last1'));
    }

    public function testDropBadData(): void {
        $text = "{{cite journal|jstor=3073767|pages=null|page=null|volume=n/a|issue=0|title=[No title found]|coauthors=Duh|last1=Duh|first1=Dum|first=Hello|last=By|author=Yup|author1=Nope|year=2002}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('Are Helionitronium Trications Stable?', $expanded->get2('title'));
        $this->assertSame('99', $expanded->get2('volume'));
        $this->assertSame('24', $expanded->get2('issue'));
        $this->assertSame('Francisco', $expanded->get2('last2'));
        $this->assertSame('Eisfeld', $expanded->get2('last1'));
        $this->assertSame('Proceedings of the National Academy of Sciences of the United States of America', $expanded->get2('journal'));
        $this->assertSame('15303–15307', $expanded->get2('pages'));
        // JSTOR gives up these, but we do not add since we get journal title and URL is simply jstor stable
        $this->assertNull($expanded->get2('publisher'));
        $this->assertNull($expanded->get2('issn'));
        $this->assertNull($expanded->get2('url'));
    }

    public function testDropBadData2(): void {
        $text = "{{cite journal|author2=BAD|jstor=3073767|pages=null|page=null|volume=n/a|issue=0|title=[No title found]|coauthors=Duh|last1=Duh|first1=Dum|first=Hello|last=By|author=Yup|author1=Nope|year=2005}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('Are Helionitronium Trications Stable?', $expanded->get2('title'));
        $this->assertSame('99', $expanded->get2('volume'));
        $this->assertSame('24', $expanded->get2('issue'));
        $this->assertSame('Duh', $expanded->get2('last1')); // We have a bad author2, so no fixed them
        $this->assertSame('Proceedings of the National Academy of Sciences of the United States of America', $expanded->get2('journal'));
        $this->assertSame('15303–15307', $expanded->get2('pages'));
        // JSTOR gives up these, but we do not add since we get journal title and URL is simply jstor stable
        $this->assertNull($expanded->get2('publisher'));
        $this->assertNull($expanded->get2('issn'));
        $this->assertNull($expanded->get2('url'));
    }

    public function testDropBadData3(): void {
        $text = "{{cite journal|doi=10.1063/5.0088162|coauthors=HDU|title=dsfadsafdskfldslj;fdsj;klfkdljssfjkl;ad;fkjdsl;kjfsda|pmid=<!-- -->}}";
        $expanded = $this->process_citation($text);
        $expanded->forget('s2cid');
        $expanded->forget('hdl');
        $this->assertSame($text, $expanded->parsed_text()); // Bad title blocks cross-ref
    }

    public function testDropBadData4a(): void {
        $text = "{{citation|last=Archive|first2=Get author RSS}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{citation}}', $expanded->parsed_text());
    }

    public function testDropBadData4b(): void {
        $text = "{{citation|last=Archive|first2=Email the|last2=Author}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{citation}}', $expanded->parsed_text());
    }

    public function testDropBadData5(): void {
        $text = "{{citation|last=Published|first=Me}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{citation|author1=Me}}', $expanded->parsed_text());

        $text = "{{citation|last=Published|first1=Me}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{citation|author1=Me}}', $expanded->parsed_text());

        $text = "{{citation|last1=Published|first=Me}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{citation|author1=Me}}', $expanded->parsed_text());

        $text = "{{citation|last1=Published|first1=Me}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{citation|author1=Me}}', $expanded->parsed_text());
    }

    public function testBrokenDoiUrlRetention1(): void {
        $text = '{{cite journal|url=http://opil.ouplaw.com/view/10.1093/law:epil/9780199231690/law-9780199231690-e1301|title=Israel, Occupied Territories|publisher=|doi=10.1093/law:epil/9780199231690/law-9780199231690-e1301|doi-broken-date=2018-07-07}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi-broken-date'));
        $this->assertNull($expanded->get2('doi'));
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testBrokenDoiUrlRetention2(): void {
        // Newer code does not even add it
        $text = '{{cite journal|url=http://opil.ouplaw.com/view/10.1093/law:epil/9780199231690/law-9780199231690-e1301}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi'));
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testBrokenDoiUrlRetention3(): void {
        // valid 10.1098 DOI in contrast to evil ones
        $text = '{{cite journal|url=https://academic.oup.com/zoolinnean/advance-article-abstract/doi/10.1093/zoolinnean/zly047/5049994}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('10.1093/zoolinnean/zly047', $expanded->get2('doi'));
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testBrokenDoiUrlRetention4(): void {
        // This is an ISSN only doi: it is valid but we do not add those, but leave url too
        $text = '{{cite journal|url=http://onlinelibrary.wiley.com/journal/10.1111/(ISSN)1601-183X/issues }}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi'));
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testCrazyDoubleDOI(): void {
        $doi = '10.1126/science.10.1126/SCIENCE.291.5501.24';
        $text = '{{cite journal|doi=' . $doi . '}}';
        $expanded = $this->process_citation($text);
        $this->assertSame($doi, $expanded->get2('doi'));
    }

    public function testBrokenDoiUrlChanges1(): void {
        $text = '{{cite journal|url=http://dx.doi.org/10.1111/j.1471-0528.1995.tb09132.x|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=12-31-1999}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('10.1111/j.1471-0528.1995.tb09132.x', $expanded->get2('doi'));
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testBrokenDoiUrlChanges2(): void {
        // The following URL is "broken" since it is not escaped properly.  The cite template displays and links it wrong too.
        $text = '{{cite journal|doi=10.1175/1525-7541(2003)004<1147:TVGPCP>2.0.CO;2|url=https://dx.doi.org/10.1175/1525-7541(2003)004<1147:TVGPCP>2.0.CO;2}}';
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testBrokenDoiUrlChanges3(): void {
        $text = '{{cite journal|url=http://doi.org/10.14928/amstec.23.1_1|doi=10.14928/amstec.23.1_1}}';    // This also troublesome DOI
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testChangeNothing1(): void {
        $text = '{{cite journal|doi=10.1111/j.1471-0528.1995.tb09132.x|pages=<!-- -->|title=<!-- -->|journal=<!-- -->|volume=<!-- -->|issue=<!-- -->|year=<!-- -->|authors=<!-- -->|pmid=<!-- -->|url=<!-- -->|s2cid=<!-- -->}}';
        $expanded = $this->process_page($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testChangeNothing2(): void {
        $text = '{{cite journal | doi=10.0000/Rubbish_bot_failure_test | doi-broken-date = <!-- not broken and the bot is wrong --> }}';
        $expanded = $this->process_page($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testChangeNothing3(): void {
        $text = '{{cite journal |title=The tumbling rotational state of 1I/‘Oumuamua<!-- do not change odd punctuation--> |journal=Nature title without caps <!-- Deny Citation Bot-->    |pages=383-386 <!-- do not change the dash--> }}';
        $expanded = $this->process_page($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testNoLoseUrl(): void {
        $text = '{{cite book |last=Söderström |first=Ulrika |date=2015 |title=Sandby Borg: Unveiling the Sandby Borg Massacre |url= |location= |publisher=Kalmar lāns museum |isbn=9789198236620 |language=Swedish }}';
        $expanded = $this->process_page($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testDotsAndVia(): void {
        $text = '{{cite journal|pmid=4957203|via=Pubmed}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('M. M.', $expanded->get2('first3'));
        $this->assertNull($expanded->get2('via'));
    }

    public function testLoseViaDup1(): void {
        $text = '{{citation|work=Some Journal|via=Some Journal}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('via'));
    }
    public function testLoseViaDup2(): void {
        $text = '{{citation|publisher=Some Journal|via=Some Journal}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('via'));
    }
    public function testLoseViaDup3(): void {
        $text = '{{citation|newspaper=Some Journal|via=Some Journal}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('via'));
    }

    public function testJustBrackets1(): void {
        $text = '{{cite book|title=[[W|12px|alt=W]]}}';
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
    }
    public function testJustBrackets2(): void {
        $text = '{{cite book|title=[[File:Example.png|thumb|upright|alt=Example alt text|Example caption]]}}';
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testBadAuthor2(): void {
        $text = '{{cite journal|title=Guidelines for the management of adults with hospital-acquired, ventilator-associated, and healthcare-associated pneumonia |journal=Am. J. Respir. Crit. Care Med. |volume=171 |issue=4 |pages=388–416 |year=2005 |pmid=15699079 |doi=10.1164/rccm.200405-644ST}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('American Thoracic Society', $expanded->get2('author1'));
    }


    public function testRemoveASIN1(): void {
        $text = "{{Cite book | asin=B0002TV0K8 |isbn=}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('B0002TV0K8', $expanded->get2('asin'));
        $this->assertSame('', $expanded->get2('isbn')); // Empty, not non-existent
    }

    public function testRemoveASIN2(): void {
        $text = "{{Cite book | asin=0226845494 |isbn=0226845494}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('0226845494', $expanded->get2('isbn'));
        $this->assertNull($expanded->get2('asin'));
    }

    public function testAddASIN1(): void {
        $text = "{{Cite book |isbn=0226845494}}";
        $expanded = $this->make_citation($text);
        $this->assertFalse($expanded->add_if_new('asin', 'X'));
        $this->assertSame('0226845494', $expanded->get2('isbn'));
        $this->assertNull($expanded->get2('asin'));
    }

    public function testAddASIN2(): void {
        $text = "{{Cite book}}";
        $expanded = $this->make_citation($text);
        $this->assertTrue($expanded->add_if_new('asin', '630000000')); //63.... code
        $this->assertSame('630000000', $expanded->get2('asin'));
    }

    public function testAddASIN3(): void {
        $text = "{{Cite book}}";
        $expanded = $this->make_citation($text);
        $this->assertTrue($expanded->add_if_new('asin', 'BNXXXXXXXX')); // Not an ISBN at all
        $this->assertSame('BNXXXXXXXX', $expanded->get2('asin'));
    }

    public function testAddASIN4(): void {
        $text = "{{Cite book}}";
        $expanded = $this->make_citation($text);
        $this->assertTrue($expanded->add_if_new('asin', '0781765625'));
        $this->assertSame('0781765625', $expanded->get2('isbn'));
        $this->assertNull($expanded->get2('asin'));
    }

    public function testAddASIN5(): void {
        $text = "{{Cite book}}";
        $expanded = $this->make_citation($text);
        $this->assertTrue($expanded->add_if_new('asin', 'ABC'));
        $this->assertSame('ABC', $expanded->get2('asin'));
        $this->assertNull($expanded->get2('isbn'));
    }

    public function testAddASIN6(): void {
        $text = "{{Cite book|asin=xxxxxx}}";
        $expanded = $this->make_citation($text);
        $this->assertFalse($expanded->add_if_new('asin', 'ABC'));
        $this->assertSame('xxxxxx', $expanded->get2('asin'));
        $this->assertNull($expanded->get2('isbn'));
    }

    public function testAddASIN7(): void {
        $text = "{{Cite book}}";
        $expanded = $this->make_citation($text);
        $this->assertTrue($expanded->add_if_new('asin', '12345'));
        $this->assertSame('12345', $expanded->get2('asin'));
        $this->assertNull($expanded->get2('isbn'));
    }

    public function testTemplateRenaming(): void {
        $text = "{{cite web|url=https://books.google.com/books?id=ecrwrKCRr7YC&pg=PA85&lpg=PA85&dq=vestibular+testing+lab+gianoli&keywords=lab&text=vestibular+testing+lab+gianoli|title=Practical Management of the Dizzy Patient|first=Joel A.|last=Goebel|date=6 December 2017|publisher=Lippincott Williams & Wilkins|via=Google Books}}";
        // Should add ISBN and thus convert to Cite book
        $expanded = $this->process_citation($text);
        $this->assertSame('978-0-7817-6562-6', $expanded->get2('isbn'));
        $this->assertSame('cite book', $expanded->wikiname());
    }

    public function testTemplateRenamingURLConvert(): void {
        $text='{{Cite journal|url=http://www.sciencedirect.com/science/article/pii/B9780123864543000129|last=Roberts|first=L.|date=2014|publisher=Academic Press|isbn=978-0-12-386455-0|editor-last=Wexler|editor-first=Philip|location=Oxford|pages=993–995|doi=10.1016/b978-0-12-386454-3.00012-9}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('http://www.sciencedirect.com/science/article/pii/B9780123864543000129', $expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('url'));
    }

    public function testAddStuff(): void {
        $text = "{{cite book|publisher=exist}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('publisher', 'A new publisher to replace it'));

        $this->assertTrue($template->add_if_new('type', 'A description of this'));
        $this->assertSame('A description of this', $template->get2('type'));

        $this->assertTrue($template->add_if_new('id', 'A description of this thing'));
        $this->assertFalse($template->add_if_new('id', 'Another description of this'));
    }

    public function testHDLasDOIThing1(): void {
        $text='{{Cite journal | doi=20.1000/100|url=http://www.stuff.com/20.1000/100}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertSame('20.1000/100', $template->get2('doi'));
        $this->assertNotNull($template->get2('url'));
    }

    public function testHDLasDOIThing2(): void {
        $text='{{Cite journal | doi=20.1000/100|url=http://www.stuff.com/20.1000/100.pdf}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertSame('20.1000/100', $template->get2('doi'));
        $this->assertSame('http://www.stuff.com/20.1000/100.pdf', $template->get2('url'));
    }

    public function testSeriesIsJournal(): void {
        $text = '{{citation | series = Annals of the New York Academy of Sciences| doi = 10.1111/j.1749-6632.1979.tb32775.x}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('journal')); // Doi returns exact same name for journal as series
    }

    public function testEmptyCoauthor(): void {
        $text = '{{Cite journal|pages=2| coauthor= |coauthors= }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('{{Cite journal|pages=2}}', $prepared->parsed_text());
        $text = '{{Cite journal|pages=2| coauthor=}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('{{Cite journal|pages=2}}', $prepared->parsed_text());
        $text = '{{Cite journal|pages=2| coauthors=}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('{{Cite journal|pages=2}}', $prepared->parsed_text());
    }

    public function testAP_1(): void {
        $text = '{{cite web|author=Associated Press |url=https://www.theguardian.com/science/2018/feb/03/scientists-discover-ancient-mayan-city-hidden-under-guatemalan-jungle}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('author'));
        $this->assertNull($expanded->get2('publisher'));
        $this->assertSame('Associated Press', $expanded->get2('agency'));
    }

    public function testAP_2(): void {
        $text = '{{cite web|author1=Dog|author1-link=X|authorlink2=Z|author2=Associated Press |last3=M|first3=N |url=https://www.theguardian.com/science/2018/feb/03/scientists-discover-ancient-mayan-city-hidden-under-guatemalan-jungle}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('author2'));
        $this->assertNull($expanded->get2('publisher'));
        $this->assertNull($expanded->get2('authorlink2'));
        $this->assertSame('Dog', $expanded->get2('author1'));
        $this->assertSame('X', $expanded->get2('author1-link'));
        $this->assertSame('M', $expanded->get2('last2'));
        $this->assertSame('N', $expanded->get2('first2'));
        $this->assertNull($expanded->get2('last3'));
        $this->assertNull($expanded->get2('first3'));
        $this->assertSame('Associated Press', $expanded->get2('agency'));
    }

    public function test_doi_not_mark_bad(): void {
        $text = '{{cite web|doi=10.1093/acref/9780199545568.001.0001}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi-broken-date'));
    }

    public function testPublisherRemoval(): void {
        foreach (['Google News Archive', '[[Google]]', 'Google News', 'Google.com', '[[Google News]]'] as $publisher) {
            $text = "{{cite journal | publisher = $publisher|url=http://google/}}";
            $prepared = $this->prepare_citation($text);
            $this->assertNull($prepared->get2('publisher'));
        }
    }

    public function testPublisherCoversion(): void {
        $text = '{{cite web|publisher=New york TiMES}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('publisher'));
        $this->assertSame('New york TiMES', $expanded->get2('work'));
    }

    public function testRemoveWikilinks0(): void {
        $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil]]}}");
        $this->assertSame('[[Pure Evil]]', $expanded->get2('title'));
        $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil|Pure Evil]]}}");  // Bot bug made these for a while
        $this->assertSame('[[Pure Evil]]', $expanded->get2('title'));
    }
    public function testRemoveWikilinks1(): void {
        $expanded = $this->process_citation("{{Cite journal|author1=[[Pure Evil]]}}");
        $this->assertSame('[[Pure Evil]]', $expanded->get2('author1'));
        $this->assertNull($expanded->get2('author1-link')); // No longer needs to be done
    }
    public function testRemoveWikilinks1b(): void {
        $expanded = $this->process_citation("{{Cite journal|author1=[[Pure]] and [[Evil]]}}");
        $this->assertSame('[[Pure]] and [[Evil]]', $expanded->get2('author1'));
    }
    public function testRemoveWikilinks1c(): void {
        $expanded = $this->process_citation("{{Cite journal|author1=[[Pure Evil|Approximate Physics]]}}");
        $this->assertSame('[[Pure Evil|Approximate Physics]]', $expanded->get2('author1'));
        $this->assertNull($expanded->get2('author1-link'));
    }

    public function testRemoveWikilinks2(): void {
        $expanded = $this->process_citation("{{Cite journal|journal=[[Pure Evil]]}}");
        $this->assertSame('[[Pure Evil]]', $expanded->get2('journal')); // leave fully linked journals
    }
    public function testRemoveWikilinks2b(): void {
        $expanded = $this->process_citation("{{Cite journal|journal=[[Pure]] and [[Evil]]}}");
        $this->assertSame('Pure and Evil', $expanded->get2('journal'));
    }
    public function testRemoveWikilinks2c(): void {
        $expanded = $this->process_citation("{{Cite journal|journal=Dark Lord of the Sith [[Pure Evil]]}}");
        $this->assertSame('Dark Lord of the Sith Pure Evil', $expanded->get2('journal'));
    }
    public function testRemoveWikilinks2d(): void {
        $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil]]}}");
        $this->assertSame('[[Pure Evil]]', $expanded->get2('title'));
        $this->assertNull($expanded->get2('title-link'));
    }
    public function testRemoveWikilinks2e(): void {
        $expanded = $this->process_citation("{{cite journal |journal= Journal of the [[Royal Asiatic Society Hong Kong Branch]]}}");
        $this->assertSame('Journal of the [[Royal Asiatic Society Hong Kong Branch]]', $expanded->get2('journal'));
    }

    public function testRemoveWikilinks3(): void {
        $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil|Approximate Physics]]}}");
        $this->assertSame('[[Pure Evil|Approximate Physics]]', $expanded->get2('title'));
        $this->assertNull($expanded->get2('title-link'));
    }
    public function testRemoveWikilinks3b(): void {
        $expanded = $this->process_citation("{{Cite journal|title=[[Dark]] Lord of the [[Sith (Star Wars)|Sith]] [[Pure Evil]]}}");
        $this->assertSame('Dark Lord of the Sith Pure Evil', $expanded->get2('title'));
    }
    public function testRemoveWikilinks3c(): void {
        $expanded = $this->process_citation("{{Cite journal|title=Dark Lord of the [[Sith (Star Wars)|Sith]] Pure Evil}}");
        $this->assertSame('Dark Lord of the [[Sith (Star Wars)|Sith]] Pure Evil', $expanded->get2('title'));
        $this->assertNull($expanded->get2('title-link'));
    }
    public function testRemoveWikilinks3d(): void {
        $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil] }}");
        $this->assertSame('Pure Evil', $expanded->get2('title'));
        $this->assertNull($expanded->get2('title-link'));
    }

    public function testRemoveWikilinks4(): void {
        $expanded = $this->process_citation("{{Cite journal|title=[Pure Evil]]}}");
        $this->assertSame('Pure Evil', $expanded->get2('title'));
    }
    public function testRemoveWikilinks4b(): void {
        $expanded = $this->process_citation("{{Cite journal|title=Dark Lord of the [[Sith]] Pure Evil}}");
        $this->assertSame('Dark Lord of the [[Sith]] Pure Evil', $expanded->get2('title'));
        $this->assertNull($expanded->get2('title-link'));
    }
    public function testRemoveWikilinks4c(): void {
        $expanded = $this->process_citation("{{cite journal|journal=[[Bulletin du Muséum national d’Histoire naturelle, Paris]]}}");
        $this->assertSame("[[Bulletin du Muséum national d'Histoire naturelle, Paris]]", $expanded->get2('journal'));
    }
    public function testRemoveWikilinks4d(): void {
        $expanded = $this->process_citation("{{cite journal|journal=[[Bulletin du Muséum national d’Histoire naturelle, Paris|Hose]]}}");
        $this->assertSame("[[Bulletin du Muséum national d'Histoire naturelle, Paris|Hose]]", $expanded->get2('journal'));
    }

    public function testRemoveWikilinks5(): void {
        $expanded = $this->process_citation("{{Cite journal|last1=[[Pure Evil]]}}");
        $this->assertSame('Pure Evil', $expanded->get2('last1'));
        $this->assertSame('Pure Evil', $expanded->get2('author1-link'));
    }
    public function testRemoveWikilinks5b(): void {
        $expanded = $this->process_citation("{{Cite journal|last1=[[Pure Evil|Approximate Physics]]}}");
        $this->assertSame('Approximate Physics', $expanded->get2('last1'));
        $this->assertSame('Pure Evil', $expanded->get2('author1-link'));
    }

    public function testRemoveWikilinks6(): void {
        $expanded = $this->process_citation("{{Cite journal|last2=[[Pure Evil]]}}");
        $this->assertSame('Pure Evil', $expanded->get2('last2'));
        $this->assertSame('Pure Evil', $expanded->get2('author2-link'));
    }
    public function testRemoveWikilinks6b(): void {
        $expanded = $this->process_citation("{{Cite journal|last2=[[Pure Evil|Approximate Physics]]}}");
        $this->assertSame('Approximate Physics', $expanded->get2('last2'));
        $this->assertSame('Pure Evil', $expanded->get2('author2-link'));
        $this->assertFalse($expanded->add_if_new('author2-link', 'will not add'));
    }

    public function testRemoveWikilinks7(): void {
        $expanded = $this->process_citation("{{Cite journal|last1=[[Pure Evil]] and [[Hoser]]}}");
        $this->assertSame('[[Pure Evil]] and [[Hoser]]', $expanded->get2('last1'));
        $this->assertNull($expanded->get2('author1-link'));
    }
    public function testRemoveWikilinks7b(): void {
        $expanded = $this->process_citation("{{Cite journal|last1=[[Pure {{!}} Evil]]}}");
        $this->assertNull($expanded->get2('author1-link'));
        $this->assertSame('[[Pure {{!}} Evil]]', $expanded->get2('last1'));
    }
    public function testRemoveWikilinks7c(): void {
        $text = "{{Cite journal|last=[[Nelarine Cornelius|Cornelius]]|first= [[Nelarine Cornelius|Nelarine]]|last2= Todres|first2= Mathew|last3= Janjuha-Jivraj|first3= Shaheena|last4= Woods|first4= Adrian|last5= Wallace|first5= James|date= 2008|title= Corporate Social Responsibility and the Social Enterprise|jstor= 25482219|journal= Journal of Business Ethics|volume= 81|issue= 2|pages= 355–370|doi= 10.1007/s10551-007-9500-7|s2cid= 154580752|url = <!-- dsfasdfds -->}}";
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('last'));
        $this->assertNull($expanded->get2('first'));
        $this->assertSame('Cornelius', $expanded->get2('last1'));
        $this->assertSame('Nelarine', $expanded->get2('first1'));
        $this->assertSame('Nelarine Cornelius', $expanded->get2('author1-link'));
    }

    public function testRemoveWikilinks8(): void {
        $expanded = $this->process_citation("{{Cite journal|title=[[Pure Evil and Pure Evil and Pure Evil]] and Hoser}}");
        $this->assertSame('[[Pure Evil and Pure Evil and Pure Evil|Pure Evil and Pure Evil and Pure Evil and Hoser]]', $expanded->get2('title'));
        $this->assertNull($expanded->get2('title-link'));
    }

    public function testWebsiteAsJournal(): void {
        $text = '{{Cite journal | journal=www.foobar.com}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('www.foobar.com', $expanded->get2('website'));
        $this->assertNull($expanded->get2('journal'));
        $text = '{{Cite journal | journal=https://www.foobar.com}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('https://www.foobar.com', $expanded->get2('url'));
        $this->assertNull($expanded->get2('journal'));
        $text = '{{Cite journal | journal=[www.foobar.com]}}';
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testDropArchiveDotOrg(): void {
        $text = '{{Cite journal | publisher=archive.org}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('publisher'));

        $text = '{{Cite journal | website=archive.org|url=http://fake.url/NOT_REAL}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('http://fake.url/NOT_REAL', $expanded->get2('url'));
        $this->assertNull($expanded->get2('website'));
    }

    public function testPreferLinkedPublisher(): void {
        $text = "{{cite journal| journal=The History Teacher| publisher=''[[The History Teacher]]'' }}";
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('publisher'));
        $this->assertSame("[[The History Teacher]]", $expanded->get2('journal')); // Quotes do get dropped
    }

    public function testLeaveArchiveURL(): void {
        $text = '{{cite book |chapterurl=http://faculty.haas.berkeley.edu/shapiro/thicket.pdf|isbn=978-0-262-60041-5|archiveurl=https://web.archive.org/web/20070704074830/http://faculty.haas.berkeley.edu/shapiro/thicket.pdf }}';
        $expanded = $this->process_citation($text);
        $this->assertSame('https://web.archive.org/web/20070704074830/http://faculty.haas.berkeley.edu/shapiro/thicket.pdf', $expanded->get2('archiveurl'));
    }

    public function testScriptTitle(): void {
        $text = "{{cite book |author={{noitalic|{{lang|zh-hans|国务院人口普查办公室、国家统计局人口和社会科技统计司编}}}} |date=2012 |script-title=zh:中国2010年人口普查分县资料 |location=Beijing |publisher={{noitalic|{{lang|zh-hans|中国统计出版社}}}} [China Statistics Press] |page= |isbn=978-7-5037-6659-6 }}";
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('title')); // Already have script-title that matches what google books gives us
        $this->assertTrue($expanded->add_if_new('title', 'This English Only'));
        $this->assertSame('This English Only', $expanded->get2('title'));
    }

    public function testPageDuplication(): void {
        // Fake bibcoce otherwise we'll find a bibcode
        $text = '{{cite journal| p=546 |doi=10.1103/PhysRev.57.546|title=Nuclear Fission of Separated Uranium Isotopes |journal=Physical Review |volume=57 |issue=6 |year=1940 |last1=Nier |first1=Alfred O. |last2=Booth |first2=E. T. |last3=Dunning |first3=J. R. |last4=Grosse |first4=A. V. |bibcode=XXXXXXXXXXXXX}}';
        $expanded = $this->process_citation($text);
        $this->assertSame($text, str_replace([' page=546 ', '|s2cid=4106096 '], [' p=546 ', ''], $expanded->parsed_text()));
    }

    public function testLastVersusAuthor(): void {
        $text = "{{cite journal|pmid=12858711 }}";
        $expanded = $this->process_citation($text);
        $text = $expanded->parsed_text();
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('author1'));
        $this->assertSame('Lovallo', $expanded->get2('last1'));
    }

    public function testUnknownJournal(): void {
        $text = '{{cite journal }}';
        $expanded = $this->process_citation($text);
        $expanded->add_if_new('journal', 'Unknown');
        $this->assertTrue($expanded->blank('journal'));
    }

    public function testCiteArxivRecognition(): void {
        $text = '{{Cite web | eprint=1203.0149}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('cite arxiv', $expanded->wikiname());
    }

    public function testTwoUrls(): void {
        $text = '{{citation|url=http://jstor.org/stable/333111333|chapter-url=http://adsabs.harvard.edu/abs/2222NatSR...814768S}}'; // Both fake
        $expanded = $this->process_citation($text);
        $this->assertSame('333111333', $expanded->get2('jstor'));
        $this->assertSame('2222NatSR...814768S', $expanded->get2('bibcode'));
        $this->assertNotNull($expanded->get2('url'));
        $this->assertNotNull($expanded->get2('chapter-url'));
    }

    public function testCommentHandling(): void {
        $text = "{{cite book|pages=3333 <!-- yes --> }} {{cite book <!-- no --> | pages=3<nowiki>-</nowiki>6}} {{cite book | pages=3<pre>-</pre>6}} {{cite book | pages=3<math>-</math>6}} {{cite book | pages=3<score>-</score>6}} {{cite book | pages=3<chem>-</chem>6}}";
        $expanded_page = $this->process_page($text);
        $this->assertSame($text, $expanded_page->parsed_text());
    }

    public function testVoidHandling(): void {
        $text = "{{ Void | dsafadsfadsfdsa {{cite journal|pmid=4543532| quote={{cite journal|pmid=4543531}} }} fdsafsd  }}";
        $expanded_page = $this->process_page($text);
        $this->assertSame($text, $expanded_page->parsed_text());
    }

    public function testParameterAlias(): void {
        $text = '{{cite journal |author-last1=Knops |author-first1=J.M. |author-last2=Nash III |author-first2=T.H.
        |date=1991 |title=Mineral cycling and epiphytic lichens: Implications at the ecosystem level
        |journal=Lichenologist |volume=23 |pages=309–321 |doi=10.1017/S0024282991000452 |issue=3}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('last1'));
        $this->assertNull($expanded->get2('last2'));
        $this->assertNull($expanded->get2('first1'));
        $this->assertNull($expanded->get2('first2'));
    }

    public function testMisspeltParameters1(): void {
        $text = "{{Cite journal | ahtour=S.-X. HU, M.-Y. ZHU, F.-C. ZHAO, and M. STEINER|tutle=A crown group priapulid from the early Cambrian Guanshan Lagerstätte,|jrounal=Geol. Mag.|year= 2017.}}";
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('author')); ## Check: the parameter might be broken down into last1, first1 etc
        $this->assertNotNull($expanded->get2('title'));
        $this->assertNotNull($expanded->get2('journal'));
        $this->assertNotNull($this->getDateAndYear($expanded));
    }
    public function testMisspeltParameters2(): void {
        $text = "{{Cite journal | ahtour=S.-X. HU, M.-Y. ZHU, F.-C. ZHAO, and M. STEINER|tutel=A crown group priapulid from the early Cambrian Guanshan Lagerstätte,|jrounal=Geol. Mag.|year= 2017.}}";
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('author')); ## Check: the parameter might be broken down into last1, first1 etc
        $this->assertNotNull($expanded->get2('tutel'));
        $this->assertNotNull($expanded->get2('journal'));
        $this->assertNotNull($this->getDateAndYear($expanded));
    }

    public function testMisspeltParameters4(): void {
        $text = "{{cite book|authorlinux=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{cite book|authorlink=X}}', $expanded->parsed_text());
    }
    public function testMisspeltParameters5(): void {
        $text = "{{cite book|authorlinks33=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{cite book|authorlink33=X}}', $expanded->parsed_text());
    }

    public function testId2Param1(): void {
        $text = '{{cite book |id=ISBN 978-1234-9583-068, DOI 10.0000/Rubbish_bot_failure_test, {{arxiv|1234.5678}} {{oclc|12354|4567}} {{oclc|1234}} {{ol|12345}} }}';
        $expanded = $this->process_citation($text);
        $this->assertSame('978-1234-9583-068', $expanded->get2('isbn'));
        $this->assertSame('1234.5678', $expanded->get2('arxiv'));
        $this->assertSame('10.0000/Rubbish_bot_failure_test', $expanded->get2('doi'));
        $this->assertSame('1234', $expanded->get2('oclc'));
        $this->assertSame('12345', $expanded->get2('ol'));
        $this->assertNotNull($expanded->get2('doi-broken-date'));
        $this->assertSame(0, preg_match('~' . sprintf(Template::PLACEHOLDER_TEXT, '\d+') . '~i', $expanded->get('id')));
    }

    public function testId2Param2(): void {
        $text = '{{cite book | id={{arxiv|id=1234.5678}}}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('1234.5678', $expanded->get2('arxiv'));
    }

    public function testId2Param3(): void {
        $text = '{{cite book | id={{arxiv|astr.ph|1234.5678}} }}';
        $expanded = $this->process_citation($text);
        $this->assertSame('astr.ph/1234.5678', $expanded->get2('arxiv'));
    }

    public function testId2Param4(): void {
        $text = '{{cite book | id={{arxiv|astr.ph|1234.5678}} {{arxiv|astr.ph|1234.5678}} }}'; // Two of the same thing
        $expanded = $this->process_citation($text);
        $this->assertSame('astr.ph/1234.5678', $expanded->get2('arxiv'));
        $this->assertSame('{{cite book | arxiv=astr.ph/1234.5678 }}', $expanded->parsed_text());
    }

    public function testId2Param5(): void {
        $text = '{{cite book|pages=1–2|id={{arxiv|astr.ph|1234.5678}}}}{{cite book|pages=1–3|id={{arxiv|astr.ph|1234.5678}}}}'; // Two of the same sub-template, but in different tempalates
        $expanded = $this->process_page($text);
        $this->assertSame('{{cite book|pages=1–2|arxiv=astr.ph/1234.5678 }}{{cite book|pages=1–3|arxiv=astr.ph/1234.5678 }}', $expanded->parsed_text());
    }

    public function testNestedTemplates1(): void {
        $text = '{{cite book|pages=1-2| {{cnn|{{fox|{{msnbc}}|{{local}}|test}} | hello }} {{tester}} {{ random {{ inside {{tester}} }} | id={{cite book|pages=1-3| {{cnn|{{fox|{{msnbc}}|{{local}}|test}} | hello }} {{tester}} {{ random {{ inside {{tester}} }} }}  }} |  cool stuff | not cool}}}}';
        $expanded = $this->process_citation($text);
        $text = str_replace("-", "–", $text); // Should not change anything other than upgrade dashes
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testNestedTemplates2(): void {
        $text = '{{cite book|quote=See {{cite book|pages=1-2|quote=See {{cite book|pages=1-4}}}}|pages=1-3}}';
        $expanded = $this->process_citation($text);
        $text = str_replace("-", "–", $text); // Should not change anything other than upgrade dashes
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testDropPostscript1(): void {
        $text = '{{citation|postscript=}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame($text, $prepared->parsed_text());
    }
    public function testDropPostscript2(): void {
        $text = '{{citation|postscript=.}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame($text, $prepared->parsed_text());
    }
    public function testDropPostscript3(): void {
        $text = '{{cite journal|postscript=}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{cite journal}}', $prepared->parsed_text());
    }
    public function testDropPostscript4(): void {
        $text = '{{cite journal|postscript=.}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{cite journal}}', $prepared->parsed_text());
    }
    public function testDropPostscript5(): void {
        $text = '{{cite journal|postscript=none}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame($text, $prepared->parsed_text());
    }

    public function testChangeParameters1(): void {
        // publicationplace
        $text = '{{citation|publicationplace=Home}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{citation|publication-place=Home}}', $prepared->parsed_text());
    }

    public function testChangeParameters2(): void {
        $text = '{{citation|publication-place=Home|location=Away}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame($text, $prepared->parsed_text());
    }

    public function testChangeParameters3(): void {
        // publicationdate
        $text = '{{citation|publicationdate=2000}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{citation|publication-date=2000}}', $prepared->parsed_text());
    }

    public function testChangeParameters4(): void {
        $text = '{{citation|publicationdate=2000|date=1999}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{citation|publication-date=2000|date=1999}}', $prepared->parsed_text());
    }

    public function testChangeParameters5(): void {
        // origyear
        $text = '{{citation|origyear=2000}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{citation|orig-date=2000}}', $prepared->parsed_text());
    }

    public function testChangeParameters6(): void {
        $text = '{{citation|origyear=2000|date=1999}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{citation|orig-date=2000|date=1999}}', $prepared->parsed_text());
    }

    public function testDropDuplicates1(): void {
        $text = '{{citation|work=Work|journal=|magazine=|website=}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{citation|work=Work}}', $prepared->parsed_text());
    }

    public function testDropDuplicates2(): void {
        $text = '{{citation|work=Work|journal=Journal|magazine=Magazine|website=Website}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame($text, $prepared->parsed_text());
    }

    public function testDropDuplicates3(): void {
        $text = '{{citation|year=2000|year=}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
    }
    public function testDropDuplicates3aa1(): void {
        $text = '{{citation|year=|year=2000}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
    }
    public function testDropDuplicates3aa2(): void {
        $text = '{{citation|year= | year= |year=| year=|year=2000}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
    }
    public function testDropDuplicates3aa3(): void {
        $text = '{{citation|year=2000|year=2000}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
    }
    public function testDropDuplicates3aa4(): void {
        $text = '{{citation|year 2000|year=2000}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
    }
    public function testDropDuplicates3aa5(): void {
        $text = '{{citation|year=|year 2000|year=2000}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
    }
    public function testDropDuplicates3aa6(): void {
        $text = '{{citation|year 2000|year=|year=2000}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
    }
    public function testDropDuplicates3aa7(): void {
        $text = '{{citation|year=2000|year 2000}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
    }
    public function testDropDuplicates3aa8(): void {
        $text = '{{citation|year=2000|year=2000|year 2000|year=|year=2000}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
    }
    public function testDropDuplicates3aa9(): void {
        $text = '{{citation|year=2000|year=2001|year=2000|year=2001|year=2000}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('{{citation|DUPLICATE_year=2000|DUPLICATE_year=2001|DUPLICATE_year=2000|DUPLICATE_year=2001|year=2000}}', $prepared->parsed_text());
    }
    public function testDropDuplicates3aa10(): void {
        $text = "{{Cite web|year=|year=2000}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{Cite web|year=2000}}', $expanded->parsed_text());
    }
    public function testDropDuplicates3aa11(): void {
        $text = "{{Cite web|year=2000|year=2000}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{Cite web|year=2000}}', $expanded->parsed_text());
    }
    public function testDropDuplicates3aa12(): void {
        $text = "{{Cite web|year|year=2000}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{Cite web|year|year=2000}}', $expanded->parsed_text());
    }
    public function testDropDuplicates3aa13(): void {
        $text = "{{Cite web|year|year}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{Cite web|year|year}}', $expanded->parsed_text());
    }
    public function testDropDuplicates3aa14(): void {
        $text = "{{Cite web|year|year 2000}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{Cite web| date=2000 |year}}', $expanded->parsed_text());
    }
    public function testDropDuplicates3aa15(): void {
        $text = "{{Cite web|year 2000|year }}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{Cite web| date=2000 |year }}', $expanded->parsed_text());
    }
    public function testDropDuplicates3aa16(): void {
        $text = '{{citation|year=2000|year=||||||||||||||||||||||||||||||||||||||||}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('{{citation|year=2000}}', $prepared->parsed_text());
    }
    public function testDropDuplicates3aa17(): void {
        $text = "{{citation|year=|title=X|year=2000}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{citation|title=X|year=2000}}', $expanded->parsed_text());
    }
    public function testDropDuplicates3aa18(): void {
        $text = "{{citation|year=2000|title=X|year=}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{citation|year=2000|title=X}}', $expanded->parsed_text());
    }
    public function testDropDuplicates3aa19(): void {
        $text = '{{Cite web |title= | year=2003 | title= Ten}}'; // Something between the two but with blank first is different code path, and the item of interest is not year
        $expanded = $this->process_citation($text);
        $this->assertSame('{{Cite web | year=2003 | title= Ten}}', $expanded->parsed_text());
    }
    public function testDropDuplicates3aa20(): void {
        $text = '{{citation|title=2000|title=2000|title 2000|title=|title=2000}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('{{citation|title=2000}}', $prepared->parsed_text());
    }

    public function testFixCAPSJunk(): void {
        $text = '{{citation|URL=X}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('X', $prepared->get('url'));
        $this->assertNull($prepared->get2('URL'));
    }

    public function testFixCAPSJunk2(): void {
        $text = '{{cite news|URL=X}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('X', $prepared->get('url'));
        $this->assertNull($prepared->get2('URL'));
    }

    public function testFixCAPSJunk3(): void {
        $text = '{{cite news|URL=X|url=Y}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('Y', $prepared->get('url'));
        $this->assertSame('X', $prepared->get('URL'));
    }

    public function testFixCAPSJunk4(): void {
        $text = '{{cite journal|URL=X|url=Y}}';
        $prepared = $this->process_citation($text);
        $this->assertSame('Y', $prepared->get('url'));
        $this->assertNull($prepared->get2('URL'));
        $this->assertSame('X', $prepared->get('DUPLICATE_url'));
    }

    public function testBadPunctuation1(): void {
        $text = '{{citation|title=:: Huh ::}}';
        $prepared = $this->make_citation($text);
        $prepared->tidy_parameter('title');
        $this->assertSame(':: Huh ::', $prepared->get2('title'));
    }

    public function testBadPunctuation2(): void {
        $text = '{{citation|title=: Huh :}}';
        $prepared = $this->make_citation($text);
        $prepared->tidy_parameter('title');
        $this->assertSame('Huh', $prepared->get2('title'));
    }

    public function testBadPunctuation3(): void {
        $text = '{{citation|title=; Huh ;;}}';
        $prepared = $this->make_citation($text);
        $prepared->tidy_parameter('title');
        $this->assertSame('Huh ;;', $prepared->get2('title'));
    }

    public function testWorkParamter1(): void {
        $text = '{{citation|work=RUBBISH|title=Rubbish|chapter=Dog}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{citation|title=Rubbish|chapter=Dog}}', $prepared->parsed_text());
    }

    public function testWorkParamter2(): void {
        $text = '{{cite book|series=Keep Series, Lose Work|work=Keep Series, Lose Work}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{cite book|series=Keep Series, Lose Work}}', $prepared->parsed_text());
    }

    public function testWorkParamter3(): void {
        $text = '{{cite journal|chapter=A book chapter|work=A book chapter}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{cite book|chapter=A book chapter}}', $prepared->parsed_text());
    }

    public function testWorkParamter4(): void {
        $text = '{{citation|work=I Live}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame($text, $prepared->parsed_text());
    }

    public function testWorkParamter5(): void {
        $text = '{{not cite|work=xyz|chapter=xzy}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{not cite|work=xyz|chapter=xzy}}', $prepared->parsed_text());
    }

    public function testWorkParamter6(): void {
        $text = '{{citation|work=xyz|journal=xyz}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{citation|journal=Xyz}}', $prepared->parsed_text());
    }

    public function testWorkParamter7(): void {
        $text = '{{citation|work=|chapter=Keep work in Citation template}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{citation|work=|chapter=Keep work in Citation template}}', $prepared->parsed_text());
    }

    public function testWorkParamter8(): void {
        $text = '{{cite journal|work=work should become journal}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{cite journal|journal=Work Should Become Journal}}', $prepared->parsed_text());
    }

    public function testWorkParamter9(): void {
        $text = '{{cite magazine|work=abc}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{cite magazine|magazine=abc}}', $prepared->parsed_text());
    }

    public function testWorkParamter10(): void {
        $text = '{{cite journal|work=}}';
        $prepared = $this->prepare_citation($text);
        $prepared->final_tidy();
        $this->assertSame('{{cite journal|journal=}}', $prepared->parsed_text());
    }

    public function testOrigYearHandling(): void {
        $text = '{{cite book |year=2009 | origyear = 2000 }}';
        $prepared = $this->process_citation($text);
        $this->assertSame('2000', $prepared->get2('orig-date'));
        $this->assertNull($prepared->get2('orig-year'));
        $this->assertSame('2009', $this->getDateAndYear($prepared));
    }

    public function testDropAmazon(): void {
        $text = '{{Cite journal | publisher=amazon.com}}';
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('publisher'));
        $text = '{{Cite journal | publisher=amazon.com|url=https://www.amazon.com/stuff}}';
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('publisher'));
        $text = '{{Cite journal | publisher=amazon.com|url=https://www.amazon.com/dp/}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('publisher'));
    }


    public function testLongAuthorLists(): void {
        $text = '{{cite web | https://arxiv.org/PS_cache/arxiv/pdf/1003/1003.3124v2.pdf|doi=<!--Do not add-->}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('The ATLAS Collaboration', $expanded->first_author());
    }
    public function testLongAuthorLists2(): void {
        // Same paper as testLongAuthorLists(), but CrossRef records full list of authors instead of collaboration name
        $text = '{{cite web | 10.1016/j.physletb.2010.03.064}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('1', $expanded->get2('display-authors'));
        $this->assertSame('Aielli', $expanded->get2('last30'));
        $this->assertSame("Charged-particle multiplicities in pp interactions at <math>\sqrt{s}=900\\text{ GeV}</math> measured with the ATLAS detector at the LHC", $expanded->get2('title'));
        $this->assertNull($expanded->get2('last31'));
    }

    public function testInPress(): void {
        $this->flush(); // Flaky test - pubmed seems to be annoyed with us sometimes, so take a break
        sleep(5);
        $text = '{{Cite journal|pmid=9858586|date =in press}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('1999', $this->getDateAndYear($expanded));
    }

    public function testISODates(): void {
        $text = '{{cite book |author=Me |title=Title |year=2007-08-01 }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('2007-08-01', $prepared->get2('date'));
        $this->assertNull($prepared->get2('year'));
    }

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
        $this->assertSame('This Title', $prepared->get2('title'));;
        $this->assertSame('9999-9999', $prepared->get2('issn'));
        $this->assertNull($prepared->get2('doi'));

        $prepared = $this->process_citation($code_coverage2);
        $this->assertSame('This Title', $prepared->get2('title'));;
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

    public function testEtAlAsAuthor(): void {
        $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|author3 = et al. }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('etal', $prepared->get2('display-authors'));
        $this->assertNull($prepared->get2('author3'));
        $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|last3 = et al. }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('etal', $prepared->get2('display-authors'));
        $this->assertNull($prepared->get2('last3'));
        $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|author3 = etal. }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('etal', $prepared->get2('display-authors'));
        $this->assertNull($prepared->get2('author3'));
        $text = '{{cite book |author1=Alfred A Albertstein|author2= Bertie B Benchmark|last3 = etal }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('etal', $prepared->get2('display-authors'));
        $this->assertNull($prepared->get2('last3'));
        $text = '{{cite book|last1=etal}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('etal', $prepared->get2('display-authors'));
        $this->assertNull($prepared->get2('last1'));
        $text = '{{cite book|last1=et al}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('etal', $prepared->get2('display-authors'));
        $this->assertNull($prepared->get2('last1'));
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

    public function testInternalCaps(): void { // checks for title formating in tidy() not breaking things
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

    public function testExistingWikiText(): void { // checks for formating in tidy() not breaking things
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


    public function testZooKeys(): void {
        $text = '{{Cite journal|doi=10.3897/zookeys.445.7778}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('ZooKeys', $expanded->get2('journal'));
        $this->assertSame('445', $expanded->get2('issue'));
        $this->assertNull($expanded->get2('volume'));
        $text = '{{Cite journal|doi=10.3897/zookeys.445.7778|journal=[[Zookeys]]}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('445', $expanded->get2('issue'));
        $this->assertNull($expanded->get2('volume'));
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

    public function testTitleItalics() {
        $text = '{{cite journal|doi=10.1111/pala.12168}}';
        $expanded = $this->process_citation($text);
        $title = $expanded->get('title');
        $title = str_replace('‐', '-', $title); // Dashes vary
        $title = str_replace("'", "", $title);  // Sometimes there, sometime not
        $this->assertSame("The macro- and microfossil record of the Cambrian priapulid Ottoia", $title);
    }

    public function testSpeciesCaps(): void {
        $text = '{{Cite journal | doi = 10.1007%2Fs001140100225}}';
        $expanded = $this->process_citation($text);
        $this->assertSame(str_replace(' ', '', "Crypticmammalianspecies:Anewspeciesofwhiskeredbat(''Myotisalcathoe''n.sp.)inEurope"),
                                      str_replace(' ', '', $expanded->get('title')));
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
        $text='{{Cite journal| url=http://plants.jstor.org/stable/10.5555/al.ap.specimen.nsw225972 |title=Holotype of Persoonia terminalis L.A.S.Johnson & P.H.Weston [family PROTEACEAE]}}';
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

    public function testRenameToArxivWhenLoseUrl(): void {
        $text = "{{cite web|url=1|arxiv=2}}";
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertSame('cite arxiv', $template->wikiname());
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
        $text = '{{Cite journal| year = ' . date("Y") .   '| doi = 10.1017/jpa.2018.43|title = New well-preserved scleritomes of Chancelloriida from early Cambrian Guanshan Biota, eastern Yunnan, China|journal = Journal of Paleontology|volume = 92|issue = 6|pages = 1–17|last1 = Zhao|first1 = Jun|last2 = Li|first2 = Guo-Biao|last3 = Selden|first3 = Paul A}}';
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

    public function testSmallWords(): void {
        $text = '{{cite journal|journal=A Word in ny and n y About cow And Then boys the U S A and y and z}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('A Word in NY and N Y About Cow and then Boys the U S A and y and Z', $prepared->get2('journal'));
        $text = '{{cite journal|journal=Ann of Math}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('Ann of Math', $prepared->get2('journal'));
        $text = '{{cite journal|journal=Ann. of Math.}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('Ann. of Math.', $prepared->get2('journal'));
        $text = '{{cite journal|journal=Ann. of Math}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('Ann. of Math', $prepared->get2('journal'));
    }

    public function testDoNotAddYearIfDate(): void {
        $text = '{{cite journal|date=2002|doi=10.1635/0097-3157(2002)152[0215:HPOVBM]2.0.CO;2}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('year'));
    }

    public function testAccessDates(): void {
        $text = '{{cite book |date=March 12, 1913 |title=Session Laws of the State of Washington, 1913 |chapter=Chapter 65: Classifying Public Highways |page=221 |chapter-url=http://leg.wa.gov/CodeReviser/documents/sessionlaw/1913c65.pdf |publisher=Washington State Legislature |accessdate=August 30, 2018}}';
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('accessdate'));
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
                $this->assertTrue(true);
        } else {
                $this->assertTrue($title); // What did we get
        }
    }

    public function testDropGoogleWebsite(): void {
        $text = "{{Cite book|website=Google.Com|url=http://Invalid.url.not-real.com/}}"; // Include a fake URL so that we are not testing: if (no url) then drop website
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('website'));
    }

    public function testHornorificInTitle(): void { // compaints about this
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
        $this->assertTrue($text != $text_out); // Verify test is valid -- We want to make sure that the spaces in $text are not normal spaces
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

    public function testEditors(): void {
        $text = '{{cite journal|editor3=Set}}';
        $prepared = $this->prepare_citation($text);
        $prepared->add_if_new('editor-last3', 'SetItL');
        $prepared->add_if_new('editor-first3', 'SetItF');
        $prepared->add_if_new('editor3', 'SetItN');
        $this->assertSame('Set', $prepared->get2('editor3'));
        $this->assertNull($prepared->get2('editor-last3'));
        $this->assertNull($prepared->get2('editor-first3'));

        $text = '{{cite journal}}';
        $prepared = $this->prepare_citation($text);
        $prepared->add_if_new('editor-last3', 'SetItL');
        $prepared->add_if_new('editor-first3', 'SetItF');
        $prepared->add_if_new('editor3', 'SetItN'); // Should not get set
        $this->assertSame('SetItL', $prepared->get2('editor-last3'));
        $this->assertSame('SetItF', $prepared->get2('editor-first3'));
        $this->assertNull($prepared->get2('editor3'));

        $text = '{{cite journal}}';
        $prepared = $this->prepare_citation($text);
        $prepared->add_if_new('editor-last33', 'SetIt'); // Huge number
        $this->assertNull($prepared->get2('editor-last33'));
        $this->assertNull($prepared->get2('display-editors'));

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
        if ($template->get2('hdl') !==  '10125/20269') {
            sleep(15);
            $template->get_identifiers_from_url(); // This test is finicky sometimes
        }
        if ($template->get2('hdl') !=='10125/20269') {
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

    public function testISSN() {
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

    public function testURLS(): void {
        $text='{{cite journal|conference-url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('1234', $template->get2('mr'));
        $text='{{cite journal|conferenceurl=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('1234', $template->get2('mr'));
        $text='{{cite journal|contribution-url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('1234', $template->get2('mr'));
        $text='{{cite journal|contributionurl=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('1234', $template->get2('mr'));
        $text='{{cite journal|article-url=https://mathscinet.ams.org/mathscinet-getitem?mr=1234}}';
        $template = $this->prepare_citation($text);
        $this->assertSame('1234', $template->get2('mr'));
    }

    public function testlooksLikeBookReview(): void {
        $text='{{cite journal|journal=X|url=book}}';
        $template = $this->make_citation($text);
        $record = (object) null;
        $this->assertFalse(looksLikeBookReview($template, $record));

        $text='{{cite journal|journal=X|url=book|year=2002|isbn=x|location=x|oclc=x}}';
        $template = $this->make_citation($text);
        $record = (object) null;
        $record->year = '2000';
        $this->assertFalse(looksLikeBookReview($template, $record));

        $text='{{cite book|journal=X|url=book|year=2002|isbn=x|location=x|oclc=x}}';
        $template = $this->make_citation($text);
        $record = (object) null;
        $record->year = '2000';
        $this->assertTrue(looksLikeBookReview($template, $record));
    }

    public function testDropBadDq(): void {
        $text='{{Cite web | url=https://books.google.com/books?id=SjpSkzjIzfsC&dq=subject:HUH&pg=213}}';
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

        $text_in = "{{cite web| page=333–444}}";
        $template = $this->make_citation($text_in);
        $template->tidy_parameter('page');
        $this->assertSame('333–444', $template->get2('pages'));
        $this->assertSame('', $template->get('page'));

        $text_in = "{{cite web| page=1-444}}";
        $template = $this->make_citation($text_in);
        $template->tidy_parameter('page');
        $this->assertSame('1-444', $template->get2('page'));
        $this->assertNull($template->get2('pages'));

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
        $expected =       [ 'modifications' =>  [0 => 'title',  ],
                            'additions' =>  [0 => 'title',  ],
                            'deletions' =>  [0 => 'citation_bot_placeholder_bare_url', ],
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

        $text = '{{cite journal | url=http://pubs.rsc.org/XYZ/unauth}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://pubs.rsc.org/XYZ', $template->get2('url'));
    }

    public function testTidyPreferVolumes(): void {
        $text = '{{cite journal | journal=Illinois Classical Studies|issue=3|volume=3}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('volume');
        $this->assertNull($template->get2('issue'));

        $text = '{{cite journal | journal=Illinois Classical Studies|number=3|volume=3}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('volume');
        $this->assertNull($template->get2('number'));

        $text = '{{cite journal | journal=Illinois Classical Studies|issue=3|volume=3}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('issue');
        $this->assertNull($template->get2('issue'));

        $text = '{{cite journal | journal=Illinois Classical Studies|number=3|volume=3}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('number');
        $this->assertNull($template->get2('number'));
    }

    public function testTidyPreferIssues(): void {
        $text = '{{cite journal | journal=Mammalian Species|issue=3|volume=3}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('volume');
        $this->assertNull($template->get2('volume'));

        $text = '{{cite journal | journal=Mammalian Species|number=3|volume=3}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('volume');
        $this->assertNull($template->get2('volume'));

        $text = '{{cite journal | journal=Mammalian Species|issue=3|volume=3}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('issue');
        $this->assertNull($template->get2('volume'));

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
