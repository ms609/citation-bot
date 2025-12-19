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
}
