<?php
declare(strict_types=1);

/*
 * Tests for Zotero.php - some of these work even when zotero fails because they check for the absence of bad data
 */

require_once __DIR__ . '/../testBaseClass.php';
final class zoteroTest extends testBaseClass {

    protected function setUp(): void {
        if (BAD_PAGE_API !== '') {
            $this->markTestSkipped();
        }
    }

    public function testFillCache(): void {
        $this->fill_cache();
        $this->assertTrue(true);
    }

    public function testZoteroExpansion_biorxiv1(): void {
        $text = '{{Cite journal| biorxiv=326363 }}';
        $expanded = $this->process_citation($text);
        $this->assertSame('Sunbeam: An extensible pipeline for analyzing metagenomic sequencing experiments', $expanded->get2('title'));
    }
    public function testZoteroExpansion_biorxiv2(): void {
        $text = '{{Cite journal| biorxiv=326363 |doi=10.0000/Rubbish_bot_failure_test}}';
        $expanded = $this->process_citation($text);
        $this->assertSame('Sunbeam: An extensible pipeline for analyzing metagenomic sequencing experiments', $expanded->get2('title'));
    }

    public function testDropUrlCode(): void {       // url is same as one doi points to
        $text = '{{cite journal |pmc=XYZ|url=https://pubs.rsc.org/en/Content/ArticleLanding/1999/CP/a808518h|doi=10.1039/A808518H|title=A study of FeCO+ with correlated wavefunctions|journal=Physical Chemistry Chemical Physics|volume=1|issue=6|pages=967–975|year=1999|last1=Glaesemann|first1=Kurt R.|last2=Gordon|first2=Mark S.|last3=Nakano|first3=Haruyuki|bibcode=1999PCCP....1..967G}}';
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('url'));
    }
    public function testDropUrlCode2(): void { // URL redirects to URL with the same DOI
        $text = '{{cite journal | last = De Vivo | first = B. | title = New constraints on the pyroclastic eruptive history of the Campanian volcanic Plain (Italy) | url = http://www.springerlink.com/content/8r046aa9t4lmjwxj/ | doi = 10.1007/s007100170010 }}';
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('url'));
    }
    public function testDropUrlCode3(): void { // url is same as one doi points to, except for http vs. https
        $text = "{{cite journal |pmc=XYZ| first = Luca | last = D'Auria | year = 2015 | title = Magma injection beneath the urban area of Naples | url = http://www.nature.com/articles/srep13100 | doi=10.1038/srep13100 }}";
        $expanded = $this->process_citation($text);
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testMR(): void {
        $text = "{{cite journal | mr = 22222 }}";
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('doi'));
    }

    public function testAccessDateAndDate(): void {
        $text = "{{cite journal | archive-date=2020 |accessdate=2020|title=X|journal=X|date=2020|issue=X|volume=X|chapter=X|pages=X|last1=X|first1=X|last2=X|first2=X }}";
        $template = $this->make_citation($text);        // Does not do anything other than touch code
        Zotero::expand_by_zotero($template);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testDropSomeProxies(): void {
        $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=proxy.libraries}}";
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNotNull($template->get2('url'));
    }

    public function testDropSomeProxiesA(): void {
        $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://www.sciencedirect.com/science/article/B1234-13241234-343242/}}";
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNotNull($template->get2('url'));
    }

    public function testDropSomeProxiesB(): void {
        $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://www.sciencedirect.com/science/article/pii/2222}}";
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNotNull($template->get2('url'));
    }

    public function testDropSomeProxiesC(): void {
        $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://www.springerlink.com/content/s}}";
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNotNull($template->get2('url'));
    }

    public function testDropSomeProxiesD(): void {
        $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://insights.ovid.com/pubmed|pmid=2222}}";
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNotNull($template->get2('url'));
    }

    public function testDropSomeProxiesE(): void {
        $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://cnn.com/|doi-access=free|url-status=dead|doi=10.0000/Rubbish_bot_failure_test}}";
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNull($template->get2('url'));
    }

    public function testDropSomeEquivURLS2(): void {
        $text = "{{cite journal|pmc=XYZ|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://iopscience.iop.org/324234}}";
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNotNull($template->get2('url'));
    }

    public function testDropSomeProxies3(): void {
        $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://journals.lww.com/3243243}}";
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNotNull($template->get2('url'));
    }

    public function testDropSomeProxies4(): void {
        $text = "{{cite journal|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://wkhealth.com/3243243}}";
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNotNull($template->get2('url'));
    }

    public function testDropSomeURLEquivs5(): void {
        $text = "{{cite journal|pmc=XYZ|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://bmj.com/cgi/pmidlookup/sss|pmid=333}}";
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNotNull($template->get2('url'));
    }

    public function testDropSomeURLEquivs6(): void {
        $text = "{{cite journal|pmc=XYZ|doi=X|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://bmj.com/cgi/pmidlookup/sss|pmid=333|pmc=123|doi-access=free}}";
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNull($template->get2('url'));
    }

    public function testDropSomeURLEquivs7(): void {
        $text = "{{cite journal|pmc=XYZ|doi=XDOI|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://xyz.serialssolutions.com/cgi/sss|pmid=333}}";
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertSame('https://dx.doi.org/XDOI', $template->get2('url'));
    }

    public function testDropSomeURLEquivs8(): void {
        $text = "{{cite journal|pmc=XYZ|journal=X|title=X|last1=X|first1=X|volume=X|issue=X|year=X|url=http://xyz.serialssolutions.com/cgi/sss|pmid=333}}";
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNull($template->get2('url'));
    }

    public function testDropSomeURLEquivs9(): void {
        $text = "{{cite journal|url=https://pubs.acs.org/doi/10.1021/acs.analchem.8b04567|doi=10.1021/acs.analchem.8b04567|doi-access=free|pmid=30741529|pmc=6526953|title=ISiCLE: A Quantum Chemistry Pipeline for Establishing in Silico Collision Cross Section Libraries|journal=Analytical Chemistry|volume=91|issue=7|pages=4346–4356|year=2019|last1=Colby|first1=Sean M.|last2=Thomas|first2=Dennis G.|last3=Nuñez|first3=Jamie R.|last4=Baxter|first4=Douglas J.|last5=Glaesemann|first5=Kurt R.|last6=Brown|first6=Joseph M.|last7=Pirrung|first7=Meg A.|last8=Govind|first8=Niranjan|last9=Teeguarden|first9=Justin G.|last10=Metz|first10=Thomas O.|last11=Renslow|first11=Ryan S.}}";
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNull($template->get2('url'));
    }

    public function testSimpleIEEE(): void {
        $url = "http://ieeexplore.ieee.org/arnumber=123456789";
        $url = Zotero::url_simplify($url);
        $this->assertSame('http:/ieeexplore.ieee.org/123456789', $url);
    }

    public function testIEEEdoi(): void {
        $url = "https://ieeexplore.ieee.org/document/4242344";
        $template = $this->process_citation('{{cite journal | url = ' . $url . ' }}');
        if ($template->get('doi') === "") {
            sleep(5);
            $template = $this->process_citation('{{cite journal | url = ' . $url . ' }}');
        }
        $this->assertSame('10.1109/ISSCC.2007.373373', $template->get2('doi'));
    }

    public function testIEEEdropBadURL(): void {
        $template = $this->process_citation('{{cite journal | url = https://ieeexplore.ieee.org/document/4242344341324324123412343214 |doi =10.1109/ISSCC.2007.373373 }}');
        $this->assertNull($template->get2('url'));
    }

    public function testZoteroResponse1(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_response = ' ';
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testZoteroResponse2(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_response = 'Remote page not found';
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testZoteroResponse3(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_response = 'Sorry, but 502 Bad Gateway was found';
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testZoteroResponse4(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_response = 'this will not be found to be valide JSON dude';
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testZoteroResponse5(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data = '';
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testZoteroResponse6(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data = 'Some stuff that should be encoded nicely';
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testZoteroResponse7(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data = (object) ['title' => 'not found'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testZoteroResponse8(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'NOT FOUND'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testZoteroResponse9(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'oup accepted manuscript', 'itemType' => 'webpage'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame($text, $template->parsed_text());
        $zotero_data[0] = (object) ['bookTitle' => 'oup accepted manuscript', 'itemType' => 'webpage', 'title'=> 'this is good stuff'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame($text, $template->parsed_text());
        $zotero_data[0] = (object) ['publicationTitle' => 'oup accepted manuscript', 'itemType' => 'webpage', 'title'=> 'this is good stuff'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testZoteroResponse10(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['bookTitle' => '(pdf) This is a Title (pdf)', 'publisher' => 'JoeJoe', 'title' => 'Billy', 'itemType' => 'bookSection'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('cite book', $template->wikiname());
        $this->assertSame('Billy', $template->get2('chapter'));
        $this->assertSame('JoeJoe', $template->get2('publisher'));
        $this->assertSame('This is a Title', $template->get2('title'));
    }

    public function testZoteroResponse11(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'journalArticle'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('cite web', $template->wikiname()); // Does not change because no work parameter is set
        $this->assertSame('Billy', $template->get2('title'));
        }

    public function testZoteroResponse12(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'magazineArticle'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('cite magazine', $template->wikiname());
        $this->assertSame('Billy', $template->get2('title'));
    }

    public function testZoteroResponse13(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'blogPost'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('cite web', $template->wikiname());
        $this->assertSame('Billy', $template->get2('title'));
    }

    public function testZoteroResponse14(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'film'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('cite web', $template->wikiname());
        $this->assertSame('Billy', $template->get2('title'));
    }

    public function testZoteroResponse15(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'thesis', 'university' => 'IowaIowa', 'thesisType' => 'M.S.'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('cite thesis', $template->wikiname());
        $this->assertSame('Billy', $template->get2('title'));
        $this->assertSame('IowaIowa', $template->get2('publisher'));
        $this->assertSame('MS', $template->get2('type'));
    }

    public function testZoteroResponse16(): void {
        $text = '{{cite news|id=|publisher=Associated Press|author=Associated Press}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = 'http://cnn.com/story';
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'webpage'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('cite news', $template->wikiname());
        $this->assertSame('Billy', $template->get2('title'));
        $this->assertSame('Associated Press', $template->get2('agency'));
        $this->assertNull($template->get2('author'));
        $this->assertNull($template->get2('publisher'));
    }

    public function testZoteroResponse17(): void {
        $text = '{{cite news|id=|publisher=Reuters|author=Reuters}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = 'http://cnn.com/story';
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'webpage'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('cite news', $template->wikiname());
        $this->assertSame('Billy', $template->get2('title'));
        $this->assertSame('Reuters', $template->get2('agency'));
        $this->assertNull($template->get2('author'));
        $this->assertNull($template->get2('publisher'));
    }

    public function testZoteroResponse18(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'webpage', 'extra' => 'PMID: 25408617 PMCID: PMC4233402'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('25408617', $template->get2('pmid'));
        $this->assertSame('4233402', $template->get2('pmc'));
    }

    public function testZoteroResponse19(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'webpage', 'extra' => 'PMID: 25408617, 25408617'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('25408617', $template->get2('pmid'));
    }

    public function testZoteroResponse20(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'webpage', 'extra' => 'IMDb ID: nm321432123'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('Billy', $template->get2('title'));
    }

    public function testZoteroResponse21(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = NO_DATE_WEBSITES[1];
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'webpage', 'date' => '2010'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertNull($template->get2('date'));
        $this->assertNull($template->get2('year'));
    }

    public function testZoteroResponse22(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'bookSection'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('cite book', $template->wikiname());
        $this->assertSame('Billy', $template->get2('title'));
    }

    public function testZoteroResponse23(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $author[0] = [0 => 'This is not a human author by any stretch of the imagination correspondent corporation', 1 => 'correspondent'];
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'webpage', 'author' => $author];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('Billy', $template->get2('title'));
        $this->assertTrue($template->blank(['author', 'author1', 'last1', 'first1', 'first', 'last']));
    }

    public function testZoteroResponse24(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'webpage', 'DOI' => 'http://dx.doi.org/10.1021/acs.analchem.8b04567' ];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('10.1021/acs.analchem.8b04567', $template->get2('doi'));
    }

    public function testZoteroResponse25(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $creators[0] = (object) ['creatorType' => 'editor', 'firstName' => "Joe", "lastName" => ""];
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'report', 'creators' => $creators];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('Billy', $template->get2('title'));
        $this->assertSame('Joe', $template->get2('editor1'));
    }

    public function testZoteroResponse26(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $creators[0] = (object) ['creatorType' => 'translator', 'firstName' => "Joe", "lastName" => ""];
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'report', 'creators' => $creators];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('Billy', $template->get2('title'));
        $this->assertSame('Joe', $template->get2('translator1'));
    }

    public function testZoteroResponse27(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => "������Junk�����������", 'itemType' => 'webpage'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertNull($template->get2('title'));
    }

    public function testZoteroResponse28(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'webpage', 'extra' => 'type: dataset'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('Billy', $template->get2('title'));
    }

    public function testZoteroResponse29(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $creators[0] = (object) ['creatorType' => 'author', 'firstName' => "Joe", "lastName" => ""];
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'report', 'creators' => $creators];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('Billy', $template->get2('title'));
        $this->assertSame('Joe', $template->get2('author1'));
    }

    public function testZoteroResponse30(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $author[0] = [0 => 'Smith', 1 => ''];
        $author[1] = [0 => 'Johnson', 1 => ''];
        $author[2] = [0 => 'Jackson', 1 => ''];
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'webpage', 'author' => $author];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('Billy', $template->get2('title'));
        $this->assertSame('Smith', $template->get2('author1'));
        $this->assertSame('Johnson', $template->get2('author2'));
        $this->assertSame('Jackson', $template->get2('author3'));
    }

    public function testZoteroResponse31(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_response = 'No items returned from any translator';
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testZoteroResponse32(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_response = 'An error occurred during translation. Please check translation with the Zotero client.';
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testZoteroResponse33(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $creators[0] = (object) ['creatorType' => 'author', 'firstName' => "Joe", "lastName" => ""];
        $zotero_data[0] = (object) ['title' => 'Central Authentication Service', 'itemType' => 'report', 'creators' => $creators];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testZoteroResponse34(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'webpage', 'extra' => 'DOI: 10.1038/546031a'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('10.1038/546031a', $template->get2('doi'));
    }

    public function testZoteroResponse35(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_response = 'Internal Server Error';
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testZoteroResponse36(): void {
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'journalArticle', 'publicationTitle' => "X"];
        $zotero_response = json_encode($zotero_data);
        $access_date = 0;
        $text = '{{cite web|id=}}';
        
        $template = $this->make_citation($text);
        $url = 'zaguan.unizar.es';
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('cite journal', $template->wikiname());
        $this->assertSame('Billy', $template->get2('title'));
        $this->assertSame('X', $template->get2('journal'));
        
        $template = $this->make_citation($text);
        $url = 'bmj.com/cgi/pmidlookup';
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('cite journal', $template->wikiname());
        $this->assertSame('Billy', $template->get2('title'));
        $this->assertSame('X', $template->get2('journal'));
        
        $template = $this->make_citation($text);
        $url = 'www.nsw.gov.au/sss';
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('cite web', $template->wikiname());
        $this->assertSame('Billy', $template->get2('title'));
        $this->assertSame('X', $template->get2('work'));

        $template = $this->make_citation($text);
        $url = 'X';
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('cite journal', $template->wikiname());
        $this->assertSame('Billy', $template->get2('title'));
        $this->assertSame('X', $template->get2('work'));
    }

    public function testZoteroResponse37(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'newspaperArticle', 'publicationTitle' => "X"];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('cite web', $template->wikiname());
        $this->assertSame('Billy', $template->get2('title'));
        $this->assertSame('X', $template->get2('work'));
    }

    public function testZoteroResponse38(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = strtotime('12 December 2010');
        $url = '';
        $zotero_data[0] = (object) ['date' => '12 December 2020', 'title' => 'Billy', 'itemType' => 'newspaperArticle', 'publicationTitle' => "X"];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame($text, $template->parsed_text());
    }

    public function testZoteroResponse39(): void {
        $text = '{{cite journal|url=https://www.sciencedirect.com/science/article/pii/S0024379512004405|title=Geometry of the Welch bounds|journal=Linear Algebra and Its Applications|volume=437|issue=10|pages=2455–2470|year=2012|last1=Datta|first1=S.|last2=Howard|first2=S.|last3=Cochran|first3=D.}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = 'https://www.sciencedirect.com/science/article/pii/S0024379512004405';
        $url_kind = 'url';
        $zotero_data[0] = (object) ['title' => 'Geometry of the Welch bounds', 'itemType' => 'journalArticle', 'DOI' => '10.1016/j.laa.2012.05.036'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertNotNull($template->get2('url')); // Used to drop when gets doi
        $this->assertSame('10.1016/j.laa.2012.05.036', $template->get2('doi'));
    }

    public function testZoteroResponse40(): void {
        $text = '{{cite journal}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'This', 'itemType' => 'journalArticle', 'publicationTitle' => 'nationalpost'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('National Post', $template->get2('journal'));
    }

    public function testZoteroResponse41(): void {
        $text = '{{cite journal}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'This', 'itemType' => 'journalArticle', 'publicationTitle' => 'financialpost'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('Financial Post', $template->get2('journal'));
    }

    public function testZoteroResponse42(): void {
        $text = '{{cite journal}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'Learn New Stuff | Hello theee| THE DAILY STAR', 'itemType' => 'journalArticle'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('The Daily Star', $template->get2('journal'));
        $this->assertSame('Learn New Stuff', $template->get2('title'));
    }

    public function testZoteroResponse43(): void {
        $text = '{{cite journal}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = 'www.edu.au';
        $zotero_data[0] = (object) ['title' => 'Cultural Advice', 'itemType' => 'journalArticle'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertNull($template->get2('journal'));
        $this->assertNull($template->get2('title'));
    }

    public function testZoteroResponse44(): void {
        $text = '{{cite web}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '222.sfdb.org';
        $zotero_data[0] = (object) ['title' => 'This', 'itemType' => 'webpage', 'pages' => '34-55'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('sfdb.org', $template->get2('website'));
        $this->assertSame('34–55', $template->get2('pages'));
    }

    public function testZoteroResponse45(): void {
        $text = '{{cite web}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'This', 'itemType' => 'webpage', 'extra' => 'ADS Bibcode: 1234asdfghjklqwerty'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('1234asdfghjklqwerty', $template->get2('bibcode'));
    }

    public function testZoteroResponse46(): void {
        $text = '{{cite web}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'This', 'itemType' => 'newspaperArticle', 'publicationTitle' => 'United States Census Bureau'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('United States Census Bureau', $template->get2('publisher'));
    }

    public function testZoteroResponse47(): void {
        $text = '{{cite web|work=DSfadsfsdf}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = 'http://nature.com/';
        $zotero_data[0] = (object) ['title' => 'This', 'itemType' => 'report'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('cite journal', $template->wikiname());

        $text = '{{cite web|work=DSfadsfsdf}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = 'http://nature.org/';
        $zotero_data[0] = (object) ['title' => 'This', 'itemType' => 'report'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('cite web', $template->wikiname());
    }

    public function testZoteroResponse48(): void {
        $text = '{{cite web|id=}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $creators[0] = (object) ['creatorType' => 'translator', 'firstName' => "JoeT", "lastName" => "SmithT"];
        $creators[1] = (object) ['creatorType' => 'editor', 'firstName' => "JoeE", "lastName" => "SmithE"];
        $creators[2] = (object) ['creatorType' => 'author', 'firstName' => "JoeA", "lastName" => "SmithA"];
        $creators[3] = (object) ['creatorType' => 'translator', 'firstName' => "JoeTX", "lastName" => "SmithTX"];
        $creators[4] = (object) ['creatorType' => 'editor', 'firstName' => "JoeEX", "lastName" => "SmithEX"];
        $creators[5] = (object) ['creatorType' => 'author', 'firstName' => "JoeAX", "lastName" => "SmithAX"];
        $zotero_data[0] = (object) ['title' => 'Billy', 'itemType' => 'report', 'creators' => $creators];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('{{cite web|id=|title=Billy|translator1=Smitht, Joet|editor1=Smithe, Joee|last1=Smitha|first1=Joea|last2=Smithax|first2=Joeax|editor2=Smithex, Joeex|translator2=Smithtx, Joetx}}', $template->parsed_text());
    }

    public function testZoteroResponse49(): void {
        $text = '{{cite web|title=X|chapter=Y}}'; // New data for chapter and title match exactly
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'X', 'chapter' => 'Y', 'year', 'pages' => '34-55'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('34–55', $template->get2('pages'));
    }

    public function testZoteroResponse50(): void {
        $text = '{{cite web|title=Y|chapter=X}}'; // New data for chapter and title match exactly, but reversed
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'X', 'chapter' => 'Y', 'year', 'pages' => '34-55'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('34–55', $template->get2('pages'));
    }

    public function testZoteroResponse51(): void {
        $text = '{{cite web|title=No one seems to understand how this work|chapter=This is the title of the chapter}}'; // New data for chapter and title match exactly, but reversed
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'This is the title of the chapter', 'year', 'pages' => '34-55'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('34–55', $template->get2('pages'));
    }

    public function testZoteroResponse52(): void {
        $text = '{{cite web}}';
        $template = $this->make_citation($text);
        $access_date = 0;
        $url = '';
        $zotero_data[0] = (object) ['title' => 'This is the title of the chapter', 'extra' => 'PMID: 12345 OCLC: 7777 Open Library ID: OL1234M'];
        $zotero_response = json_encode($zotero_data);
        Zotero::process_zotero_response($zotero_response, $template, $url, $access_date);
        $this->assertSame('12345', $template->get2('pmid'));
        $this->assertSame('7777', $template->get2('oclc'));
        $this->assertSame('1234M', $template->get2('ol'));
    }

    public function testRemoveURLthatRedirects(): void { // This URL is a redirect -- tests code that does that
        $text = '{{cite journal|doi-access=free|doi=10.1021/acs.analchem.8b04567|url=https://shortdoi.org/gf7sqt|pmid=30741529|pmc=6526953|title=ISiCLE: A Quantum Chemistry Pipeline for Establishing in Silico Collision Cross Section Libraries|journal=Analytical Chemistry|volume=91|issue=7|pages=4346–4356|year=2019|last1=Colby|first1=Sean M.|last2=Thomas|first2=Dennis G.|last3=Nuñez|first3=Jamie R.|last4=Baxter|first4=Douglas J.|last5=Glaesemann|first5=Kurt R.|last6=Brown|first6=Joseph M.|last7=Pirrung|first7=Meg A.|last8=Govind|first8=Niranjan|last9=Teeguarden|first9=Justin G.|last10=Metz|first10=Thomas O.|last11=Renslow|first11=Ryan S.}}';
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNull($template->get2('url'));
    }

    public function testRemoveURLthatRedirects2(): void {
        $text = '{{cite journal|doi=10.1021/acs.analchem.8b04567|url=https://shortdoi.org/gf7sqt|pmid=30741529|pmc=6526953|doi-access=free}}';
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNotNull($template->get2('url'));
    }

    public function testRemoveURLwithProxy1(): void { // PROXY_HOSTS_TO_DROP
        $text = '{{cite journal|doi=10.1021/acs.analchem.8b04567|url=http://delivery.acm.org|doi-access=free}}';
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNotNull($template->get2('url'));

        $text = '{{cite journal|doi=10.1021/acs.analchem.8b04567|url=http://delivery.acm.org|doi-access=free|issue=1|volume=1|pages=22-33|year=2022|journal=X|title=Y|author1=Y|author2=X}}';
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNull($template->get2('url'));
    }

    public function testRemoveURLwithProxy2(): void { // PROXY_HOSTS_TO_ALWAYS_DROP
        $text = '{{cite journal|doi=10.1021/acs.analchem.8b04567|url=http://journals.royalsociety.org|doi-access=free}}';
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNotNull($template->get2('url'));

        $text = '{{cite journal|doi=10.1021/acs.analchem.8b04567|url=http://journals.royalsociety.org|doi-access=free|issue=1|volume=1|pages=22-33|year=2022|journal=X|title=Y|author1=Y|author2=X}}';
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNull($template->get2('url'));
    }

    public function testRemoveURLwithProxy3a(): void { // CANONICAL_PUBLISHER_URLS
        $text = '{{cite journal|doi=10.1021/acs.analchem.8b04567|url=http://pubs.geoscienceworld.org|doi-access=free}}';
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNotNull($template->get2('url'));
    }
    public function testRemoveURLwithProxy3b(): void { // CANONICAL_PUBLISHER_URLS
        $text = '{{cite journal|doi=10.1021/acs.analchem.8b04567|url=http://pubs.geoscienceworld.org|doi-access=free|issue=1|volume=1|pages=22-33|year=2022|journal=X|title=Y|author1=Y|author2=X}}';
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        Zotero::drop_urls_that_match_dois($tmp_array);
        $this->assertNull($template->get2('url'));
    }

    public function testUseArchive1(): void {
        $text = '{{cite journal|archive-url=https://web.archive.org/web/20160418061734/http://www.weimarpedia.de/index.php?id=1&tx_wpj_pi1%5barticle%5d=104&tx_wpj_pi1%5baction%5d=show&tx_wpj_pi1%5bcontroller%5d=article&cHash=0fc8834241a91f8cb7d6f1c91bc93489}}';
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        expand_templates_from_archives($tmp_array);
        for ($x = 0; $x <= 10; $x++) {
            if ($template->get2('title') == null) {
                sleep(4); // Sometimes fails for no good reason
                expand_templates_from_archives($tmp_array);
            }
        }
        $this->assertSame('Goethe-Schiller-Denkmal - Weimarpedia', $template->get2('title'));
    }

    public function testUseArchive2(): void {
        $text = '{{cite journal|series=Xarchive-url=https://web.archive.org/web/20160418061734/http://www.weimarpedia.de/index.php?id=1&tx_wpj_pi1%5barticle%5d=104&tx_wpj_pi1%5baction%5d=show&tx_wpj_pi1%5bcontroller%5d=article&cHash=0fc8834241a91f8cb7d6f1c91bc93489}}';
        $template = $this->make_citation($text);
        $tmp_array = [$template];
        expand_templates_from_archives($tmp_array);
        $this->assertNull($template->get2('title'));
    }

    public function testZoteroExpansion_doi_not_from_crossref(): void {
        $text = '{{Cite journal|doi=.3233/PRM-140291}}';
        $expanded = $this->make_citation($text);
        $expanded->verify_doi();
        $this->assertSame('10.3233/PRM-140291', $expanded->get2('doi'));
        $this->requires_zotero(function(): void {
            $text = '{{Cite journal|doi=10.3233/PRM-140291}}'; // mEDRA DOI - they do not provide RIS information from dx.doi.org
            $expanded = $this->process_citation($text);
            $this->assertNotNull($expanded->get2('journal'));
            $this->assertTrue(strpos($expanded->get('journal'), 'Journal of Pediatric Rehabilitation Medicine') !== false);// Sometimes includes a journal of....
        });
    }

    public function testCitationTemplateWithoutJournalZotero(): void {
        $this->requires_zotero(function(): void {
            $text = '{{citation|url=http://www.word-detective.com/2011/03/mexican-standoff/|title=Mexican standoff|work=The Word Detective|accessdate=2013-03-21}}';
            $expanded = $this->process_citation($text);
            $this->assertNull($expanded->get2('isbn')); // This citation used to crash code in ISBN search
        });
    }

    public function testZoteroExpansionAccessDates(): void {
        $this->requires_zotero(function(): void {
            $text = '{{Cite journal|url=https://www.ncbi.nlm.nih.gov/books/NBK24663/|access-date=1978-12-12}}';     // Access date is too far in past, will not expand
            $expanded = $this->expand_via_zotero($text);
            $this->assertSame($text, $expanded->parsed_text());
        });
    }

    public function testZoteroExpansionCiteseerxSkipped(): void {
        $this->requires_zotero(function(): void {
            $text = '{{Cite journal|url=https://citeseerx.ist.psu.edu/viewdoc/summary?doi=10.1.1.604.9335}}';
            $expanded = $this->expand_via_zotero($text);
            $this->assertSame($text, $expanded->parsed_text());
        });
    }

    public function testZoteroExpansionBibcodeSkipped(): void {
        $this->requires_zotero(function(): void {
            $text = '{{Cite journal|url=https://ui.adsabs.harvard.edu/abs/2015ConPh..56...35D/abstract}}';
            $expanded = $this->expand_via_zotero($text);
            $this->assertSame($text, $expanded->parsed_text());
        });
    }

    public function testZoteroExpansionSumSearchSkipped(): void {
        $this->requires_zotero(function(): void {
            $text = '{{Cite journal|url=https://ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi?X=tool=sumsearch.org&id=342141323}}';
            $expanded = $this->expand_via_zotero($text);
            $this->assertSame($text, $expanded->parsed_text());
        });
    }

    public function testZoteroExpansionNRM(): void {
        $this->requires_zotero(function(): void {
            $text = '{{cite journal | url = http://www.nrm.se/download/18.4e32c81078a8d9249800021554/Bengtson2004ESF.pdf}}';
            $expanded = $this->process_page($text);
            $this->assertTrue(true); // Gives one fuzzy match.  For now we just check that this doesn't crash PHP.
        });
    }

    public function testNoneAdditionOfAuthor(): void {
        $this->requires_zotero(function(): void {
            // Rubbish author listed in page metadata; do not add.
            $text = "{{cite web |url=http://www.westminster-abbey.org/our-history/people/sir-isaac-newton}}";
            $expanded = $this->process_citation($text);
            $this->assertNull($expanded->get2('last1'));
        });
    }

    public function testDateTidiness(): void {
        $this->requires_zotero(function(): void {
            $text = "{{cite web|title= Gelada| website= nationalgeographic.com |url= http://animals.nationalgeographic.com/animals/mammals/gelada/ |publisher=[[National Geographic Society]]|accessdate=7 March 2012}}";
            $expanded = $this->expand_via_zotero($text);
            $date = $expanded->get('date');
            $date = str_replace('10 May 2011', '', $date); // Sometimes we get no date
            $this->assertSame('', $date);
        });
    }

    public function testZoteroBadVolumes(): void { // has ( and such in it
        $this->requires_zotero(function(): void {
            $text = '{{cite journal|chapterurl=https://biodiversitylibrary.org/page/32550604}}';
            $expanded = $this->expand_via_zotero($text);
            $this->assertTrue($expanded->get2('volume') === null || $expanded->get2('volume') === '4');
        });
    }

    public function testZoteroKoreanLanguage(): void {
        $this->requires_zotero(function(): void {
            $text = '{{cite journal|chapter-url=http://www.newsen.com/news_view.php?uid=201606131737570410}}';
            $expanded = $this->expand_via_zotero($text);
            if ($expanded->get2('title') === '큐브 측 "포미닛 사실상 해체, 팀 존속 어려워"') {
                $this->assertTrue(true);
            } else {
                $this->assertNull($expanded->get2('title'));
            }
        });
    }

    public function testZoteroExpansion_osti(): void {
        $this->requires_zotero(function(): void {
            $text = '{{Cite journal| osti=1406676 }}';
            $expanded = $this->process_citation($text);
            $this->assertSame('10.1016/j.ifacol.2017.08.010', $expanded->get2('doi'));
        });
        $text = '{{Cite journal| osti=1406676 }}';
        $expanded = $this->process_citation($text);
        $this->assertSame($text, $expanded->parsed_text()); // Verify that lack of requires_zotero() blocks zotero
    }

    public function testZoteroExpansion_rfc(): void {
        $this->requires_zotero(function(): void {
        $text = '{{Cite journal| rfc=6679 }}';
        $expanded = $this->process_citation($text);
        $this->assertTrue($expanded->get('title') != ''); // Zotero gives different titles from time to time
        });
    }

    public function testZoteroRespectDates(): void {
        $this->requires_zotero(function(): void {
            $text = '{{Use mdy dates}}{{cite web|url=https://pubmed.ncbi.nlm.nih.gov/20443582/ |pmid=<!-- -->|pmc=<!-- -->|doi=<!-- -->|bibcode=<!-- --> |arxiv=<!-- -->|s2cid=<!-- -->}}';
            $page = $this->process_page($text);
            $this->assertTrue((bool) strpos($page->parsed_text(), 'August 26, 2010'));
            $text = '{{Use dmy dates}}{{cite web|url=https://pubmed.ncbi.nlm.nih.gov/20443582/ |pmid=<!-- -->|pmc=<!-- -->|doi=<!-- -->|bibcode=<!-- --> |arxiv=<!-- -->|s2cid=<!-- -->}}';
            $page = $this->process_page($text);
            $this->assertTrue((bool) strpos($page->parsed_text(), '26 August 2010'));
        });
    }

    public function testZoteroExpansionPII(): void {
        $this->requires_zotero(function(): void {
            $text = '{{Cite journal|url = https://www.sciencedirect.com/science/article/pii/S0024379512004405}}';
            $expanded = $this->expand_via_zotero($text);
            $this->assertSame('10.1016/j.laa.2012.05.036', $expanded->get2('doi'));
        });
    }

    public function testZoteroExpansionNBK(): void {
        $this->requires_zotero(function(): void {
            $text = '{{Cite journal|url=https://www.ncbi.nlm.nih.gov/books/NBK24662/|access-date=2099-12-12}}';     // Date is before access-date so will expand
            $expanded = $this->expand_via_zotero($text);
            $this->assertSame('Science, Medicine, and Animals', $expanded->get2('title'));
            $this->assertSame('2004', $expanded->get2('date'));
            $this->assertSame('National Academies Press (US)', $expanded->get2('publisher'));
        });
    }

    public function testZoteroExpansion_hdl(): void {
        $this->requires_zotero(function(): void {
            $text = '{{Cite journal| hdl=10411/OF7UCA }}';
            $expanded = $this->process_citation($text);
            $this->assertSame('Replication Data for: Perceiving emotion in non-social targets: The effect of trait empathy on emotional through art', $expanded->get2('title'));
        });
    }

    public function testHDLSimpler1(): void {
        $text = '{{Cite web}}';
        $template = $this->make_citation($text);
        hdl_works('2027/mdp.39015064245429');
        hdl_works('2027/mdp.39015064245429?urlappend=%3Bseq=326');
        hdl_works('2027/mdp.39015064245429?urlappend=%3Bseq=326%3Bownerid=13510798900390116-358');
        $template->get_identifiers_from_url('https://hdl.handle.net/2027/mdp.39015064245429?urlappend=%3Bseq=326%3Bownerid=13510798900390116-358');
        $this->assertSame('2027/mdp.39015064245429?urlappend=%3Bseq=326', $template->get2('hdl'));
        $template->get_identifiers_from_url('https://hdl.handle.net/2027/mdp.39015064245429?urlappend=%3Bseq=326%3Bownerid=13510798900390116urlappend-358');
        $this->assertSame('2027/mdp.39015064245429?urlappend=%3Bseq=326', $template->get2('hdl'));
    }

    public function testHDLSimpler2a(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $this->assertIsString(hdl_works('20.1000/100'));
    }

    public function testHDLSimpler2b(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $this->assertFalse(hdl_works('20.1000/100?urlappend=%3Bseq=326'));
    }

    public function testHDLSimpler2c(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $this->assertFalse(hdl_works('20.1000/100?urlappend=%3Bseq=326%3Bownerid=13510798900390116-35'));
    }

    public function testHDLSimpler2d(): void {
        $text = '{{Cite web}}';
        $template = $this->make_citation($text);
        hdl_works('20.1000/100');
        hdl_works('20.1000/100?urlappend=%3Bseq=326');
        hdl_works('20.1000/100?urlappend=%3Bseq=326%3Bownerid=13510798900390116-35');
        $template->get_identifiers_from_url('https://hdl.handle.net/20.1000/100?urlappend=%3Bseq=326%3Bownerid=13510798900390116-35');
        $this->assertSame('20.1000/100', $template->get2('hdl'));
    }

    public function testPubMedTermStuff1(): void {
        $text = '{{Cite web|url=https://stuff.ncbi.nlm.nih.gov/pubmed/?term=dropper}}';
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertSame('https://pubmed.ncbi.nlm.nih.gov/?term=dropper', $template->get2('url'));
    }

    public function testPubMedTermStuff2(): void {
        $text = '{{Cite web|url=https://stuff.ncbi.nlm.nih.gov/pubmed/?term=dropper|pmid=21234}}';
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertNull($template->get2('url'));
    }

    public function testPubMedTermStuff3(): void {
        $text = '{{Cite web|url=https://stuff.ncbi.nlm.nih.gov/pubmed?term=21234|pmid=21234}}';
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertSame('https://pubmed.ncbi.nlm.nih.gov/21234/', $template->get2('url'));
    }

    public function testPubMedTermStuff4(): void {
        $text = '{{Cite web|url=https://stuff.ncbi.nlm.nih.gov/pubmed?term=12343214|pmid=32412}}';
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertSame('https://stuff.ncbi.nlm.nih.gov/pubmed?term=12343214', $template->get2('url'));
    }

    public function testPubMedTermStuff5(): void {
        $text = '{{Cite web|url=https://stuff.ncbi.nlm.nih.gov/pubmed/?term=21234|pmid=}}';
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertSame('https://pubmed.ncbi.nlm.nih.gov/21234/', $template->get2('url'));
        $this->assertSame('21234', $template->get2('pmid'));
    }

    public function testPII(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        $pii = 'S0960076019302699';
        $doi_expect = '10.1016/j.jsbmb.2019.105494';
        $doi = Zotero::get_doi_from_pii($pii);
        $this->assertSame($doi_expect, $doi);
    }
}
