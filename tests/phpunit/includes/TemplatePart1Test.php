<?php
declare(strict_types=1);

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../../testBaseClass.php';

final class TemplatePart1Test extends testBaseClass {

    public function testLotsOfFloaters2(): void {
        $text_in = '{{cite journal|isssue 3 volumee 5 | tittle Love|journall Dog|series Not mine today|chapte cows|this is random stuff | zauthor Joe }}';
        $text_out = '{{cite journal| journal=L Dog | series=Not mine today |isssue 3 volumee 5 | tittle Love|chapte cows|this is random stuff | zauthor Joe }}';
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

    public function testLotsMagazines_1(): void {
        $text_in = "{{cite journal| journal=The New Yorker}}";
        $prepared = $this->process_citation($text_in);
        $this->assertSame('{{cite magazine| magazine=The New Yorker}}', $prepared->parsed_text());
    }

    public function testLotsMagazines_2(): void {
        $text_in = "{{cite journal| periodical=The Economist}}";
        $prepared = $this->process_citation($text_in);
        $this->assertSame('{{cite news| newspaper=The Economist}}', $prepared->parsed_text());
    }

    public function testParameterWithNoParameters_1(): void {
        $text = "{{Cite web | text without equals sign  }}";
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testParameterWithNoParameters_2(): void {
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
    }

    public function testTemplateConvertComplex2aa(): void {
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

    public function testHDLnotBroken(): void {
        $text = "{{cite document|doi=20.1000/100}}";
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi-broken-date'));
    }

    public function testTemplateConvertComplex2b(): void {
        $text = "{{cite document|journal=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("cite journal", $expanded->wikiname());
    }

    public function testTemplateConvertComplex2bb(): void {
        $text = "{{cite document|newspaper=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("cite news", $expanded->wikiname());
    }

    public function testTemplateConvertComplex2bc(): void {
        $text = "{{cite document|chapter=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("cite book", $expanded->wikiname());
    }

    public function testTemplateConvertComplex2c(): void {
        $text = "{{Cite document}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{Cite document}}", $expanded->parsed_text());
    }

    public function testTemplateConvertComplex2cb(): void {
        $text = "{{Cite document|doi=XXX/978-XXX}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("cite book", $expanded->wikiname());
    }

    public function testTemplateConvertComplex2cc(): void {
        $text = "{{Cite document|journal=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("cite journal", $expanded->wikiname());
    }

    public function testTemplateConvertComplex2cd(): void {
        $text = "{{Cite document|newspaper=X}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("cite news", $expanded->wikiname());
    }

    public function testTemplateConvertComplex2ce(): void {
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
        $this->assertSame("{{Citation}}", $expanded->parsed_text());
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
    }

    public function testDropBadData5a(): void {
        $text = "{{citation|last=Published|first1=Me}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{citation|author1=Me}}', $expanded->parsed_text());
    }

    public function testDropBadData5b(): void {
        $text = "{{citation|last1=Published|first=Me}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('{{citation|author1=Me}}', $expanded->parsed_text());
    }

    public function testDropBadData5c(): void {
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
        $text = '{{Cite journal|url=http://www.sciencedirect.com/science/article/pii/B9780123864543000129|last=Roberts|first=L.|date=2014|publisher=Academic Press|isbn=978-0-12-386455-0|editor-last=Wexler|editor-first=Philip|location=Oxford|pages=993–995|doi=10.1016/b978-0-12-386454-3.00012-9}}';
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
        $text = '{{Cite journal | doi=20.1000/100|url=http://www.stuff.com/20.1000/100}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertSame('20.1000/100', $template->get2('doi'));
        $this->assertNotNull($template->get2('url'));
    }

    public function testHDLasDOIThing2(): void {
        $text = '{{Cite journal | doi=20.1000/100|url=http://www.stuff.com/20.1000/100.pdf}}';
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

    public function testEmptyCoauthor_1(): void {
        $text = '{{Cite journal|pages=2| coauthor= |coauthors= }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('{{Cite journal|pages=2}}', $prepared->parsed_text());
    }

    public function testEmptyCoauthor_2(): void {
        $text = '{{Cite journal|pages=2| coauthor=}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('{{Cite journal|pages=2}}', $prepared->parsed_text());
    }

    public function testEmptyCoauthor_3(): void {
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

    public function testWebsiteAsJournal_1(): void {
        $text = '{{Cite journal | journal=www.foobar.com}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('www.foobar.com', $expanded->get2('website'));
        $this->assertNull($expanded->get2('journal'));
    }

    public function testWebsiteAsJournal_2(): void {
        $text = '{{Cite journal | journal=https://www.foobar.com}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('https://www.foobar.com', $expanded->get2('url'));
        $this->assertNull($expanded->get2('journal'));
    }

    public function testWebsiteAsJournal_3(): void {
        $text = '{{Cite journal | journal=[www.foobar.com]}}';
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text());
    }

    public function testDropArchiveDotOrg_1(): void {
        $text = '{{Cite journal | publisher=archive.org}}';
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('publisher'));
    }

    public function testDropArchiveDotOrg_2(): void {
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

    public function testDropAmazon_1(): void {
        $text = '{{Cite journal | publisher=amazon.com}}';
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('publisher'));
    }

    public function testDropAmazon_2(): void {
        $text = '{{Cite journal | publisher=amazon.com|url=https://www.amazon.com/stuff}}';
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('publisher'));
    }

    public function testDropAmazon_3(): void {
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

    public function testAccessDateWithHyphenTypo(): void {
        // Test that access-date-2025-07-13 (with hyphen typo) is correctly parsed
        $text = '{{cite web |date=2025-05-01 |title=Test Title |url=https://example.com |access-date-2025-07-13 |website=example.com}}';
        $prepared = $this->prepare_citation($text);
        // The date should be parsed as 2025-07-13, NOT as -2025 (negative year)
        $access_date = $prepared->get2('access-date');
        $this->assertNotNull($access_date, 'access-date should not be null');
        // Check that the year is positive (no leading hyphen before year)
        $this->assertStringNotContainsString('-2025', $access_date);
        // Check that it's a valid date format (contains 2025 without leading hyphen)
        $this->assertStringContainsString('2025', $access_date);
    }
}
