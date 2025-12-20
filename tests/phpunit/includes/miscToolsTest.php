<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class miscToolsTest extends testBaseClass {
    public function testcheck_memory_usage(): void {
        $pg = new TestPage(); unset($pg); // Fill page name with test name for debugging
        check_memory_usage('testcheck_memory_usage');
        $this->assertTrue(true);
    }

    public function testFixupGoogle(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
        $this->assertSame('https://www.google.com/search?x=cows', simplify_google_search('https://www.google.com/search?x=cows'));
        $this->assertSame('https://www.google.com/search/?q=cows', simplify_google_search('https://www.google.com/search/?q=cows'));
    }
 
    public function testThrottle(): void { // Just runs over the code and basically does nothing
        for ($x = 0; $x <= 25; $x++) {
            throttle();
        }
        $this->assertNull(null);
    }
    public function testOxLit(): void {
        $text="{{cite web|url=https://oxfordre.com/literature/view/10.1093/acrefore/9780190201098.001.0001/acrefore-9780190201098-e-1357|doi-broken-date=X|doi=10.3421/32412xxxxxxx}}";
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/acrefore/9780190201098.013.1357', $template->get2('doi'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780190201098.013.1357', $template->get2('doi'));
    }

    public function testOxComms(): void {
        $text="{{cite web|url=https://oxfordre.com/communication/communication/view/10.1093/acrefore/9780190228613.001.0001/acrefore-9780190228613-e-1195|doi-broken-date=X|doi=10.3421/32412xxxxxxx}}";
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/acrefore/9780190228613.013.1195', $template->get2('doi'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780190228613.013.1195', $template->get2('doi'));
    }

    public function testShortenMusic(): void {
        $text="{{cite web|url=https://oxfordmusiconline.com/X/X/2134/1/1/1/1}}";
        $template = $this->process_citation($text);
        $this->assertSame('https://oxfordmusiconline.com/X/2134/1/1/1/1', $template->get2('url'));
        $text="{{cite web|url=https://oxfordmusiconline.com/X/X/X/2134/1/1/1/1}}";
        $template = $this->process_citation($text);
        $this->assertSame('https://oxfordmusiconline.com/X/2134/1/1/1/1', $template->get2('url'));
    }

    public function testGroveMusic1(): void {
        $text = '{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity | website=Grove Music Online |publisher=Oxford University Press |date=2002 |access-date=November 20, 2018 |url-access=subscription|via=Grove Music Online}}';
        $template = $this->process_citation($text);
        $this->assertSame('{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity | website=Grove Music Online |publisher=Oxford University Press |date=2002 |doi=10.1093/gmo/9781561592630.article.J441700 |isbn=978-1-56159-263-0 |access-date=November 20, 2018 |url-access=subscription}}', $template->parsed_text());
    }

    public function testGroveMusic2(): void {
        $text = '{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity | website=Grove Music Online |publisher=Oxford University Press |date=2002|via=Grove Music Online}}';
        $template = $this->process_citation($text);
        $this->assertSame('{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity | website=Grove Music Online |publisher=Oxford University Press |date=2002|doi=10.1093/gmo/9781561592630.article.J441700 |isbn=978-1-56159-263-0 }}', $template->parsed_text());
    }

    public function testGroveMusic3(): void {
        $text = '{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity |publisher=Oxford University Press |date=2002|via=Grove Music Online}}';
        $template = $this->process_citation($text);
        $this->assertSame('{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity |publisher=Oxford University Press |date=2002|doi=10.1093/gmo/9781561592630.article.J441700 |isbn=978-1-56159-263-0 |via=Grove Music Online}}', $template->parsed_text());
    }

    public function testGroveMusic4(): void {
        $text = '{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity |publisher=Oxford University Press |date=2002|website=Grove Music Online}}';
        $template = $this->process_citation($text);
        $this->assertSame('{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity |publisher=Oxford University Press |date=2002|website=Grove Music Online |doi=10.1093/gmo/9781561592630.article.J441700 |isbn=978-1-56159-263-0 }}', $template->parsed_text());
    }

    public function testGroveMusic5(): void {
        $text = '{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity |publisher=Oxford University Press |date=2002|website=Grove Music Online|via=The Dog Farm}}';
        $template = $this->process_citation($text);
        $this->assertSame('{{cite web |url=https://doi.org/10.1093/gmo/9781561592630.article.J441700 |title=Tatum, Art(hur, Jr.) (jazz) |last1=Howlett |first1=Felicity |publisher=Oxford University Press |date=2002|website=Grove Music Online|doi=10.1093/gmo/9781561592630.article.J441700 |isbn=978-1-56159-263-0 |via=The Dog Farm}}', $template->parsed_text());
    }


    public function testAmazonExpansion1(): void {
        $text = "{{Cite web | url=http://www.amazon.com/On-Origin-Phyla-James-Valentine/dp/0226845494 | accessdate=2012-04-20 |isbn= |publisher=amazon}}";
        $expanded = $this->prepare_citation($text);
        $this->assertSame('cite book', $expanded->wikiname());
        $this->assertSame('0226845494', $expanded->get2('isbn'));
        $this->assertNull($expanded->get2('asin'));
        $this->assertNull($expanded->get2('publisher'));
        $this->assertNull($expanded->get2('url'));
    }

    public function testAmazonExpansion2(): void {
        $text = "{{Cite web | chapter-url=http://www.amazon.com/On-Origin-Phyla-James-Valentine/dp/0226845494 | accessdate=2012-04-20 |isbn= |publisher=amazon}}";
        $expanded = $this->prepare_citation($text);
        $this->assertSame('cite book', $expanded->wikiname());
        $this->assertSame('0226845494', $expanded->get2('isbn'));
        $this->assertNull($expanded->get2('asin'));
        $this->assertNull($expanded->get2('publisher'));
        $this->assertNull($expanded->get2('chapter-url'));
    }

    public function testAmazonExpansion3(): void {
        $text = "{{Cite web | url=https://www.amazon.com/Gold-Toe-Metropolitan-Dress-Three/dp/B0002TV0K8 | access-date=2012-04-20 | title=Gold Toe Men's Metropolitan Dress Sock (Pack of Three Pairs) at Amazon Men's Clothing store}}";
        $expanded = $this->process_citation($text);
        $this->assertSame("{{Cite web | url=https://www.amazon.com/Gold-Toe-Metropolitan-Dress-Three/dp/B0002TV0K8 | access-date=2012-04-20 | title=Gold Toe Men's Metropolitan Dress Sock (Pack of Three Pairs) at Amazon Men's Clothing store | website=Amazon }}", $expanded->parsed_text());   // We do not touch this kind of URL other than adding website
    }

    public function testAmazonExpansion4(): void {
        $text = "{{Cite web | chapter-url=http://www.amazon.eu/On-Origin-Phyla-James-Valentine/dp/0226845494 | accessdate=2012-04-20 |isbn= |publisher=amazon}}";
        $expanded = $this->prepare_citation($text);
        $this->assertSame('cite book', $expanded->wikiname());
        $this->assertSame('', $expanded->get2('isbn'));
        $this->assertNull($expanded->get2('asin'));
        $this->assertNull($expanded->get2('publisher'));
        $this->assertNull($expanded->get2('chapter-url'));
        $this->assertSame('{{ASIN|0226845494|country=eu}}', $expanded->get2('id'));
    }

    public function testAmazonExpansion5(): void {
        $text = "{{Cite book | chapter-url=http://www.amazon.eu/On-Origin-Phyla-James-Valentine/dp/0226845494 |isbn=exists}}";
        $expanded = $this->prepare_citation($text);;
        $this->assertNull($expanded->get2('asin'));
        $this->assertNull($expanded->get2('chapter-url'));
        $this->assertSame('exists', $expanded->get2('isbn'));
    }

    public function testSimpleIEEE(): void {
        $url = "http://ieeexplore.ieee.org/arnumber=123456789";
        $url = url_simplify($url);
        $this->assertSame('http:/ieeexplore.ieee.org/123456789', $url);
    }

    public function testURLCleanUp1(): void {
        $text = "{{cite book|url=ttps://junk}}";
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertSame('https://junk', $template->get2('url'));
    }

    public function testURLCleanUp2(): void {
        $text = "{{cite book|url=http://orbit.dtu.dk/en/publications/33333|doi=1234}}";
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertNotNull($template->get2('url'));

        $text = "{{cite book|url=http://orbit.dtu.dk/en/publications/33333|doi=1234|pmc=312432}}";
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertNull($template->get2('url'));

        $text = "{{cite book|url=http://orbit.dtu.dk/en/publications/33333|doi=1234|doi-access=free}}";
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertNull($template->get2('url'));
    }

    public function testURLCleanUp3(): void {
        $text = "{{cite book|url=https://ieeexplore.ieee.org/arnumber=1}}";
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertSame('https://ieeexplore.ieee.org/document/1', $template->get2('url'));
    }

    public function testURLCleanUp4(): void {
        $text = "{{cite book|url=https://ieeexplore.ieee.org/document/01}}";
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertSame('https://ieeexplore.ieee.org/document/1', $template->get2('url'));
    }

    public function testURLCleanUp5(): void {
        $text = "{{cite book|url=https://jstor.org/stuffy-Stuff/?refreqid=124}}";
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertSame('https://jstor.org/stuffy-Stuff/', $template->get2('url'));
    }

    public function testURLCleanUp6(): void {
        $text = "{{cite book|url=https://www-jstor-org.libezp.lib.lsu.edu/stable/10.7249/j.ctt4cgd90.10}}";
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertSame('10.7249/j.ctt4cgd90.10', $template->get2('jstor'));
        $this->assertNotNull($template->get2('url'));
    }

    public function testURLCleanUp7(): void {
        $text = "{{cite book|url=https://www.jstor.org.libezp.lib.lsu.edu/stable/10.7249/j.ctt4cgd90.10}}";
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertSame('10.7249/j.ctt4cgd90.10', $template->get2('jstor'));
        $this->assertNotNull($template->get2('url'));
    }

    public function testURLCleanUp8(): void {
        $text = "{{cite book|url=https://jstor.org/stable/pdfplus/12345.pdf|jstor=12345}}";
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertNotNull($template->get2('url'));
        $this->assertSame('12345', $template->get2('jstor'));
    }

    public function testURLCleanUp9(): void {
        $text = "{{cite book|url=https://jstor.org/discover/12345.pdf|jstor=12345}}";
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertNotNull($template->get2('url'));
        $this->assertSame('12345', $template->get2('jstor'));
    }

    public function testURLCleanUp10(): void {
        $text = "{{cite book|url=https://archive.org/detail/jstor-12345}}";
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertNotNull($template->get2('url'));
        $this->assertSame('12345', $template->get2('jstor'));
    }

    public function testURLCleanUp11(): void {
        $text = "{{cite book|url=https://jstor.org/stable/pdfplus/12345.pdf}}";
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertNotNull($template->get2('url'));
        $this->assertSame('12345', $template->get2('jstor'));
    }

    public function testURLCleanUp12(): void {
        $text = "{{cite journal|url=https://dx.doi.org/10.0000/Rubbish_bot_failure_test}}"; // Add bogus
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertNotNull($template->get2('url'));
        $this->assertSame('10.0000/Rubbish_bot_failure_test', $template->get2('doi'));
    }

    public function testURLCleanUp13(): void {
        $text = "{{cite journal|url=https://dx.doi.org/10.0000/Rubbish_bot_failure_test2|doi=10.0000/Rubbish_bot_failure_test}}"; // Fail to add bogus
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertSame('https://dx.doi.org/10.0000/Rubbish_bot_failure_test2', $template->get2('url'));
        $this->assertSame('10.0000/Rubbish_bot_failure_test', $template->get2('doi'));
    }

    public function testURLCleanUp14(): void {
        $text = "{{cite journal|url=https://dx.doi.org/10.1093/oi/authority.x}}"; // A particularly semi-valid DOI
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertNull($template->get2('doi'));
        $this->assertSame('https://dx.doi.org/10.1093/oi/authority.x', $template->get2('url'));
    }

    public function testURLCleanUp15(): void {
        $text = "{{cite journal|doi=10.5284/1000184|url=https://dx.doi.org/10.5284/1000184XXXXXXXXXX}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertNotNull($template->get2('url'));
        $this->assertSame('10.5284/1000184', $template->get2('doi'));
    }

    public function testURLCleanUp16(): void {
        $text = "{{cite journal|doi= 10.1093/oi/authority.x|url=https://dx.doi.org/10.1093/oi/authority.xXXXXXXXXXX.pdf}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertSame('https://dx.doi.org/10.1093/oi/authority.xXXXXXXXXXX.pdf', $template->get2('url'));
        $this->assertSame('10.1093/oi/authority.x', $template->get2('doi'));
    }

    public function testURLCleanUp17(): void {
        $text = "{{cite journal|url=https://SomeRandomWeb.com/10.5284/1000184}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertSame('https://SomeRandomWeb.com/10.5284/1000184', $template->get2('url'));
        $this->assertNull($template->get2('doi'));
    }

    public function testURLCleanUp18(): void {
        $text = "{{cite journal|url=https://www.jstor.org/stable/1986280?origin=324124324}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertSame('https://www.jstor.org/stable/1986280', $template->get2('url'));
    }

    public function testURLCleanUp19(): void {
        $text = "{{cite journal|url=https://dx.doi.org/10.0000/Rubbish_bot_failure_test|doi=10.0000/Rubbish_bot_failure_test|doi-access=free}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertNull($template->get2('url'));
    }

    public function testURLCleanUp20(): void {
        $text = "{{cite journal|url=https://doi.library.ubc.ca/10.7717/peerj.3486|pmc=5483034}}"; // Has good free copy
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertNull($template->get2('url'));
    }

    public function testURLCleanUp21(): void {
        $text = "{{cite journal|url=https://BlahBlah.com/10.7717/peerj.3486|doi=10.7717/peerj.3486|doi-access=free}}"; // Has good free copy
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url()); // Did not really add anything
        $this->assertNull($template->get2('url'));
    }

    public function testURLCleanUp22(): void {
        $text = "{{cite journal|url=https://BlahBlah.com/10.7717/peerj.3486#with_lotst_of_junk|doi-access=free|doi=10.7717/peerj.3486|pmc=23222}}"; // Has good free copy
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url()); // Did not really add anything
        $this->assertNull($template->get2('url'));
    }

    public function testURLCleanUp23(): void {
        $text = "{{cite journal|url=https://BlahBlah.com/10.7717/peerj.3486|pmc=342342|doi-access=free}}"; // Has good free copy
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertNull($template->get2('url'));
    }

    public function testURLCleanUp24(): void {
        $text = "{{cite journal|url=https://BlahBlah.com/25.10.2015/2137303/default.htm#/10.7717/peerj.3486#with_lotst_of_junk|doi-access=free|doi=10.7717/peerj.3486|pmc=23222}}"; // Has good free copy, and DOI is after first 10. in url
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url()); // Did not really add anything
        $this->assertNull($template->get2('url'));
    }


    public function testCovertUrl2Chapter1(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNotNull($expanded->get2('url'));
    }
    public function testCovertUrl2Chapter2(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/page/0}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNotNull($expanded->get2('url'));
    }
    public function testCovertUrl2Chapter3(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/page/1}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNotNull($expanded->get2('url'));
    }
    public function testCovertUrl2Chapter4(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/page}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testCovertUrl2Chapter5(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/page/232}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNotNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNull($expanded->get2('url'));
    }

    public function testCovertUrl2Chapter6(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/chapter/}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNotNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNull($expanded->get2('url'));
    }
}
