<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class urlToolsTest extends testBaseClass {

    public function testFixupGoogle(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        $this->assertSame('https://www.google.com/search?x=cows', simplify_google_search('https://www.google.com/search?x=cows'));
        $this->assertSame('https://www.google.com/search/?q=cows', simplify_google_search('https://www.google.com/search/?q=cows'));
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
        $expanded = $this->prepare_citation($text);
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

    public function testNormalizeOxford(): void {
        $text = "{{cite web|url=http://latinamericanhistory.oxfordre.com/XYZ}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://oxfordre.com/latinamericanhistory/XYZ', $template->get2('url'));
    }

    public function testShortenOxford(): void {
        $text = "{{cite web|url=https://oxfordre.com/latinamericanhistory/latinamericanhistory/latinamericanhistory/XYZ}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://oxfordre.com/latinamericanhistory/XYZ', $template->get2('url'));
    }

    public function testAnonymizeOxford1(): void {
        $text = "{{cite web|url=https://www.oxforddnb.com/X;jsession?print}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.oxforddnb.com/X', $template->get2('url'));
    }
    public function testAnonymizeOxford2(): void {
        $text = "{{cite web|url=https://www.anb.org/x;jsession?print}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.anb.org/x', $template->get2('url'));
    }
    public function testAnonymizeOxford3(): void {
        $text = "{{cite web|url=https://www.oxfordartonline.com/Y;jsession?print}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.oxfordartonline.com/Y', $template->get2('url'));
    }
    public function testAnonymizeOxford4(): void {
        $text = "{{cite web|url=https://www.ukwhoswho.com/z;jsession?print}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.ukwhoswho.com/z', $template->get2('url'));
    }
    public function testAnonymizeOxfor5(): void {
        $text = "{{cite web|url=https://www.oxfordmusiconline.com/z;jsession?print}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.oxfordmusiconline.com/z', $template->get2('url'));
    }
    public function testAnonymizeOxford6(): void {
        $text = "{{cite web|url=https://oxfordre.com/z;jsession?print}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://oxfordre.com/z', $template->get2('url'));
    }
    public function testAnonymizeOxford7(): void {
        $text = "{{cite web|url=https://oxfordaasc.com/z;jsession?print}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://oxfordaasc.com/z', $template->get2('url'));
    }
    public function testAnonymizeOxford8(): void {
        $text = "{{cite web|url=https://oxfordreference.com/z;jsession?print}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://oxfordreference.com/z', $template->get2('url'));
    }
    public function testAnonymizeOxford9(): void {
        $text = "{{cite web|url=https://oxford.universitypressscholarship.com/z;jsession?print}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://oxford.universitypressscholarship.com/z', $template->get2('url'));
    }

    public function testOxforddnbDOIs1(): void {
        $text = "{{cite web|url=https://www.oxforddnb.com/view/10.1093/ref:odnb/9780198614128.001.0001/odnb-9780198614128-e-33369|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/ref:odnb/33369', $template->get2('doi'));
        $this->assertSame('978-0-19-861412-8', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/ref:odnb/33369', $template->get2('doi'));
    }

    public function testOxforddnbDOIs2(): void {
        $text = "{{cite web|url=https://www.oxforddnb.com/view/10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-108196|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y|title=Joe Blow - Oxford Dictionary of National Biography}}";
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/odnb/9780198614128.013.108196', $template->get2('doi'));
        $this->assertSame('978-0-19-861412-8', $template->get2('isbn'));
        $this->assertSame('Joe Blow', $template->get2('title'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/odnb/9780198614128.013.108196', $template->get2('doi'));
    }

    public function testANBDOIs(): void {
        $text = "{{cite web|url=https://www.anb.org/view/10.1093/anb/9780198606697.001.0001/anb-9780198606697-e-1800262|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/anb/9780198606697.article.1800262', $template->get2('doi'));
        $this->assertSame('978-0-19-860669-7', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/anb/9780198606697.article.1800262', $template->get2('doi'));
    }

    public function testArtDOIs(): void {
        $text = "{{cite web|url=https://www.oxfordartonline.com/benezit/view/10.1093/benz/9780199773787.001.0001/acref-9780199773787-e-00183827|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/benz/9780199773787.article.B00183827', $template->get2('doi'));
        $this->assertSame('978-0-19-977378-7', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/benz/9780199773787.article.B00183827', $template->get2('doi'));
    }

    public function testGroveDOIs(): void {
        $text = "{{cite web|url=https://www.oxfordartonline.com/groveart/view/10.1093/gao/9781884446054.001.0001/oao-9781884446054-e-7000082129|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/gao/9781884446054.article.T082129', $template->get2('doi'));
        $this->assertSame('978-1-884446-05-4', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/gao/9781884446054.article.T082129', $template->get2('doi'));
    }

    public function testGroveDOIs2(): void {
        $text = "{{cite web|url=https://www.oxfordartonline.com/groveart/view/10.1093/gao/9781884446054.001.0001/oao-9781884446054-e-7002085714|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/gao/9781884446054.article.T2085714', $template->get2('doi'));
        $this->assertSame('978-1-884446-05-4', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/gao/9781884446054.article.T2085714', $template->get2('doi'));
    }

    public function testAASCDOIs(): void {
        $text = "{{cite web|url=https://oxfordaasc.com/view/10.1093/acref/9780195301731.001.0001/acref-9780195301731-e-41463|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acref/9780195301731.013.41463', $template->get2('doi'));
        $this->assertSame('978-0-19-530173-1', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acref/9780195301731.013.41463', $template->get2('doi'));
    }

    public function testWhoWhoDOIs(): void {
        $text = "{{cite web|url=https://www.ukwhoswho.com/view/10.1093/ww/9780199540891.001.0001/ww-9780199540884-e-37305|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/ww/9780199540884.013.U37305', $template->get2('doi'));
        $this->assertSame('978-0-19-954089-1', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/ww/9780199540884.013.U37305', $template->get2('doi'));
    }

    public function testMusicDOIs(): void {
        $text = "{{cite web|url=https://www.oxfordmusiconline.com/grovemusic/view/10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-0000040055|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/gmo/9781561592630.article.40055', $template->get2('doi'));
        $this->assertSame('978-1-56159-263-0', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/gmo/9781561592630.article.40055', $template->get2('doi'));
    }

    public function testMusicDOIsA(): void {
        $text = "{{cite web|url=https://www.oxfordmusiconline.com/grovemusic/view/10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-1002242442|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/gmo/9781561592630.article.A2242442', $template->get2('doi'));
        $this->assertSame('978-1-56159-263-0', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/gmo/9781561592630.article.A2242442', $template->get2('doi'));
    }

    public function testMusicDOIsO(): void {
        $text = "{{cite web|url=https://www.oxfordmusiconline.com/grovemusic/view/10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-5000008391|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/gmo/9781561592630.article.O008391', $template->get2('doi'));
        $this->assertSame('978-1-56159-263-0', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/gmo/9781561592630.article.O008391', $template->get2('doi'));
    }

    public function testMusicDOIsL(): void {
        $text = "{{cite web|url=https://www.oxfordmusiconline.com/grovemusic/view/10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-4002232256|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/gmo/9781561592630.article.L2232256', $template->get2('doi'));
        $this->assertSame('978-1-56159-263-0', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/gmo/9781561592630.article.L2232256', $template->get2('doi'));
    }

    public function testMusicDOIsJ(): void {
        $text = "{{cite web|url=https://www.oxfordmusiconline.com/grovemusic/view/10.1093/gmo/9781561592630.001.0001/omo-9781561592630-e-2000095300|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/gmo/9781561592630.article.J095300', $template->get2('doi'));
        $this->assertSame('978-1-56159-263-0', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/gmo/9781561592630.article.J095300', $template->get2('doi'));
    }

    public function testLatinDOIs(): void {
        $text = "{{cite web|url=https://oxfordre.com/latinamericanhistory/view/10.1093/acrefore/9780199366439.001.0001/acrefore-9780199366439-e-2|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780199366439.013.2', $template->get2('doi'));
        $this->assertSame('978-0-19-936643-9', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780199366439.013.2', $template->get2('doi'));
    }

    public function testEnvDOIs(): void {
        $text = "{{cite web|url=https://oxfordre.com/environmentalscience/view/10.1093/acrefore/9780199389414.001.0001/acrefore-9780199389414-e-224|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780199389414.013.224', $template->get2('doi'));
        $this->assertSame('978-0-19-938941-4', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780199389414.013.224', $template->get2('doi'));
    }

    public function testAmHistDOIs(): void {
        $text = "{{cite web|url=https://oxfordre.com/view/10.1093/acrefore/9780199329175.001.0001/acrefore-9780199329175-e-17|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780199329175.013.17', $template->get2('doi'));
        $this->assertSame('978-0-19-932917-5', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780199329175.013.17', $template->get2('doi'));
    }

    public function testAfHistDOIs(): void {
        $text = "{{cite web|url=https://oxfordre.com/africanhistory/view/10.1093/acrefore/9780190277734.001.0001/acrefore-9780190277734-e-191|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780190277734.013.191', $template->get2('doi'));
        $this->assertSame('978-0-19-027773-4', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780190277734.013.191', $template->get2('doi'));
    }

    public function testIntStudDOIs(): void {
        $text = "{{cite web|url=https://oxfordre.com/view/10.1093/acrefore/9780190846626.001.0001/acrefore-9780190846626-e-39|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780190846626.013.39', $template->get2('doi'));
        $this->assertSame('978-0-19-084662-6', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780190846626.013.39', $template->get2('doi'));
    }

    public function testClimateDOIs(): void {
        $text = "{{cite web|url=https://oxfordre.com/climatescience/view/10.1093/acrefore/9780190228620.001.0001/acrefore-9780190228620-e-699|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780190228620.013.699', $template->get2('doi'));
        $this->assertSame('978-0-19-022862-0', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780190228620.013.699', $template->get2('doi'));
    }

    public function testReligionDOIs(): void {
        $text = "{{cite web|url=https://oxfordre.com/religion/view/10.1093/acrefore/9780199340378.001.0001/acrefore-9780199340378-e-568|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780199340378.013.568', $template->get2('doi'));
        $this->assertSame('978-0-19-934037-8', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780199340378.013.568', $template->get2('doi'));
    }

    public function testAnthroDOIs(): void {
        $text = "{{cite web|url=https://oxfordre.com/anthropology/view/10.1093/acrefore/9780190854584.001.0001/acrefore-9780190854584-e-45|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780190854584.013.45', $template->get2('doi'));
        $this->assertSame('978-0-19-085458-4', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780190854584.013.45', $template->get2('doi'));
    }

    public function testClassicsDOIs(): void {
        $text = "{{cite web|url=https://oxfordre.com/classics/view/10.1093/acrefore/9780199381135.001.0001/acrefore-9780199381135-e-7023|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780199381135.013.7023', $template->get2('doi'));
        $this->assertSame('978-0-19-938113-5', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780199381135.013.7023', $template->get2('doi'));
    }

    public function testPsychDOIs(): void {
        $text = "{{cite web|url=https://oxfordre.com/psychology/view/10.1093/acrefore/9780190236557.001.0001/acrefore-9780190236557-e-384|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780190236557.013.384', $template->get2('doi'));
        $this->assertSame('978-0-19-023655-7', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780190236557.013.384', $template->get2('doi'));
    }

    public function testPoliDOIs(): void {
        $text = "{{cite web|url=https://oxfordre.com/politics/view/10.1093/acrefore/9780190228637.001.0001/acrefore-9780190228637-e-181|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780190228637.013.181', $template->get2('doi'));
        $this->assertSame('978-0-19-022863-7', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acrefore/9780190228637.013.181', $template->get2('doi'));
    }

    public function testOxPressDOIs(): void {
        $text = "{{cite web|url=https://oxford.universitypressscholarship.com/view/10.1093/oso/9780190124786.001.0001/oso-9780190124786|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/oso/9780190124786.001.0001', $template->get2('doi'));
        $this->assertSame('978-0-19-012478-6', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/oso/9780190124786.001.0001', $template->get2('doi'));
    }

    public function testMedDOIs(): void {
        $text = "{{cite web|url=https://oxfordmedicine.com/view/10.1093/med/9780199592548.001.0001/med-9780199592548-chapter-199|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/med/9780199592548.003.0199', $template->get2('doi'));
        $this->assertSame('978-0-19-959254-8', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/med/9780199592548.003.0199', $template->get2('doi'));
    }

    public function testUnPressScholDOIs(): void {
        $text = "{{cite web|url=https://oxford.universitypressscholarship.com/view/10.1093/oso/9780198814122.001.0001/oso-9780198814122-chapter-5|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/oso/9780198814122.003.0005', $template->get2('doi'));
        $this->assertSame('978-0-19-881412-2', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/oso/9780198814122.003.0005', $template->get2('doi'));
    }

    public function testUnPressScholDOIsType2(): void {
        $text = "{{cite web|url=https://oxford.universitypressscholarship.com/view/10.1093/acprof:oso/9780199812295.001.0001/oso-9780199812295-chapter-7}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/acprof:oso/9780199812295.003.0007', $template->get2('doi'));
        $this->assertSame('978-0-19-981229-5', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
    }

    public function testOxHandbookDOIs(): void {
        $text = "{{cite web|url=https://www.oxfordhandbooks.com/view/10.1093/oxfordhb/9780198824633.001.0001/oxfordhb-9780198824633-e-1|doi=10.0000/Rubbish_bot_failure_test|doi-broken-date=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/oxfordhb/9780198824633.013.1', $template->get2('doi'));
        $this->assertSame('978-0-19-882463-3', $template->get2('isbn'));
        $this->assertNull($template->get2('doi-broken-date'));
        $template->forget('doi');
        $template->tidy_parameter('url');
        $this->assertSame('10.1093/oxfordhb/9780198824633.013.1', $template->get2('doi'));
    }

    public function testConversionOfURL6a(): void {
        $text = "{{cite web|url=http://search.proquest.com/docview/12341234|title=X}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertNotNull($template->get2('url'));
        $this->assertSame('{{ProQuest|12341234}}', $template->get2('id'));
    }
    public function testConversionOfURL6b(): void {
        $text = "{{cite web|url=http://search.proquest.com/docview/12341234}}";     // No title
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
    }
    public function testConversionOfURL6c(): void {
        $text = "{{cite web|url=http://search.proquest.com/docview/12341234|title=X|id=<!--- --->}}";       // Blocked by comment
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
    }

    public function testConversionOfURL7(): void {
        $text = "{{cite web|url=https://search.proquest.com/docview/12341234|id=CITATION_BOT_PLACEHOLDER_COMMENT|title=Xyz}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertSame('CITATION_BOT_PLACEHOLDER_COMMENT', $template->get2('id'));
        $this->assertSame('https://search.proquest.com/docview/12341234', $template->get2('url'));
    }

    public function testConversionOfURL8(): void {
        $text = "{{cite web|url=https://citeseerx.ist.psu.edu/viewdoc/summary?doi=10.1.1.483.8892|title=Xyz|pmc=341322|doi-access=free|doi=10.0000/Rubbish_bot_failure_test}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertNull($template->get2('url'));
    }

    public function testConversionOfURL9(): void {
        $text = "{{cite web|url=https://ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi?dfastool=sumsearch.org&&id=123456|title=Xyz|pmc=123456}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertNull($template->get2('url'));
    }

    public function testConversionOfURL10(): void {
        $text = "{{cite web|url=https://ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi?dfastool=sumsearch.org&&id=123456|title=Xyz|pmc=333333|doi=10.0000/Rubbish_bot_failure_test}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertNotNull($template->get2('url'));
    }

    public function testConversionOfURL10B(): void {
        $text = "{{cite web|url=https://ncbi.nlm.nih.gov/entrez/eutils/elink.fcgi?dfastool=sumsearch.org&&id=123456|pmid=123456|title=Xyz|pmc=333333|doi=10.0000/Rubbish_bot_failure_test}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertNull($template->get2('url'));
    }

    public function testConversionOfURL11(): void {
        $text = "{{cite web|url=https://zbmath.org/?q=an:7511.33034|title=Xyz|pmc=333333|doi=10.0000/Rubbish_bot_failure_test|doi-access=free}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertNull($template->get2('url'));
    }

    public function testConversionOfURL12(): void {
        $text = "{{cite web|url=https://www.osti.gov/biblio/1760327-generic-advanced-computing-framework-executing-windows-based-dynamic-contingency-analysis-tool-parallel-cluster-machines|title=Xyz|pmc=333333|doi=10.0000/Rubbish_bot_failure_test|doi-access=free}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertNull($template->get2('url'));
    }

    public function testConversionOfURL13(): void {
        $text = "{{cite web|url=https://zbmath.org/?q=an:75.1133.34|title=Xyz|pmc=333333|doi=10.0000/Rubbish_bot_failure_test|doi-access=free}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertNull($template->get2('url'));
    }

    public function testConversionOfURL14(): void {
        $text = "{{cite web|url=https://papers.ssrn.com/sol3/papers.cfm?abstract_id=1234231|title=Xyz|pmc=333333|doi=10.0000/Rubbish_bot_failure_test|doi-access=free}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertNull($template->get2('url'));
    }

    public function testConversionOfURL15(): void {
        $text = '{{cite web | url=https://www.osti.gov/energycitations/product.biblio.jsp?osti_id=2341|title=Xyz|pmc=333333|doi=10.0000/Rubbish_bot_failure_test|doi-access=free}}';
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertNull($template->get2('url'));
    }

    public function testConversionOfURL2(): void {
        $text = "{{cite web|url=http://worldcat.org/title/stuff/oclc/1234}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertSame('1234', $template->get2('oclc'));
    }

    public function testConversionOfURL2xyz(): void {
        $text = "{{cite web|url=http://worldcat.org/title/stuff/oclc/1234&referer=brief_results}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->get_identifiers_from_url());
        $this->assertSame('1234', $template->get2('oclc'));
        $this->assertNotNull($template->get2('url'));
        $this->assertSame('cite web', $template->wikiname());
    }

    public function testConversionOfURL2B(): void {
        $text = "{{cite web|url=http://worldcat.org/title/edition/oclc/1234}}"; // Edition
        $template = $this->make_citation($text);
        $this->assertFalse($template->get_identifiers_from_url());
        $this->assertNull($template->get2('oclc'));
        $this->assertSame('http://worldcat.org/title/edition/oclc/1234', $template->get2('url'));
        $this->assertSame('cite web', $template->wikiname());
    }

    public function testUrlConversions(): void {
        $text = '{{cite journal | url= https://mathscinet.ams.org/mathscinet-getitem?mr=0012343 }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('0012343', $prepared->get2('mr'));
        $this->assertNotNull($prepared->get2('url'));
    }
    public function testUrlConversionsA(): void {
        $text = '{{cite journal | url= https://papers.ssrn.com/sol3/papers.cfm?abstract_id=1234231}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('1234231', $prepared->get2('ssrn'));
        $this->assertNotNull($prepared->get2('url'));
    }
    public function testUrlConversionsB(): void {
        $text = '{{cite journal | url=https://www.osti.gov/biblio/2341}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('2341', $prepared->get2('osti'));
        $this->assertNotNull($prepared->get2('url'));
    }
    public function testUrlConversionsC(): void {
        $text = '{{cite journal | url=https://www.osti.gov/energycitations/product.biblio.jsp?osti_id=2341}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('2341', $prepared->get2('osti'));
        $this->assertNotNull($prepared->get2('url'));
    }
    public function testUrlConversionsD(): void {
        $text = '{{cite journal | url=https://zbmath.org/?format=complete&q=an:1111.22222}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('1111.22222', $prepared->get2('zbl'));
        $this->assertNotNull($prepared->get2('url'));
    }
    public function testUrlConversionsE(): void {
        $text = '{{cite journal | url=https://zbmath.org/?format=complete&q=an:11.2222.44}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('11.2222.44', $prepared->get2('jfm'));
        $this->assertNotNull($prepared->get2('url'));
    }
    public function testUrlConversionsF(): void {
        $text = '{{cite journal |url=http://citeseerx.ist.psu.edu/viewdoc/download?doi=10.1.1.923.345&rep=rep1&type=pdf}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('10.1.1.923.345', $prepared->get2('citeseerx'));
        $text = '{{cite journal |url=http://citeseerx.ist.psu.edu/viewdoc/summary?doi=10.1.1.923.345}}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('10.1.1.923.345', $prepared->get2('citeseerx'));
    }
    public function testUrlConversionsG(): void {
        $text = '{{cite journal | archiveurl= https://mathscinet.ams.org/mathscinet-getitem?mr=0012343 }}';
        $prepared = $this->prepare_citation($text);
        $this->assertSame('0012343', $prepared->get2('mr'));
        $this->assertNull($prepared->get2('archiveurl'));
    }

    public function testRemoveNoAccessMessageFromOUP(): void {
        $text = '{{cite journal|url=https://academic.oup.com/gji/article-abstract/230/1/50/6522179#no-access-message}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://academic.oup.com/gji/article-abstract/230/1/50/6522179', $template->get2('url'));
    }

}
