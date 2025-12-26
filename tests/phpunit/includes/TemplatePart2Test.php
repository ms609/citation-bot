<?php
declare(strict_types=1);

/*
 * Tests for Template.php
 */

require_once __DIR__ . '/../../testBaseClass.php';

final class TemplatePart2Test extends testBaseClass {

    public function testTidy1(): void {
        $text = '{{cite web|postscript = <!-- A comment only --> }}';
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('postscript'));
    }

    public function testTidy1a(): void {
        $text = '{{cite web|postscript = <!-- A comment only --> {{Some Template}} }}';
        $template = $this->process_citation($text);
        $this->assertNull($template->get2('postscript'));
    }

    public function testTidy2(): void {
        $text = '{{citation|issue="Something Special"}}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('issue');
        $this->assertSame('Something Special', $template->get2('issue'));
    }

    public function testTidy3(): void {
        $text = "{{citation|issue=Dog \t\n\r\0\x0B }}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('issue');
        $this->assertSame('Dog', $template->get2('issue'));
    }

    public function testTidy4(): void {
        $text = "{{citation|issue=Dog &nbsp;}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('issue');
        $this->assertSame('Dog', $template->get2('issue'));
    }

    public function testTidy5(): void {
        $text = '{{citation|issue=&nbsp; Dog }}';
        $template = $this->make_citation($text);
        $template->tidy_parameter('issue');
        $this->assertSame('Dog', $template->get2('issue'));
    }

    public function testTidy5b(): void {
        $text = "{{citation|agency=California Department of Public Health|publisher=California Tobacco Control Program}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('agency');
        $this->assertSame('California Department of Public Health', $template->get2('publisher'));
        $this->assertNull($template->get2('agency'));
    }

    public function testTidy6(): void {
        $text = "{{cite web|arxiv=xxxxxxxxxxxx}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('arxiv');
        $this->assertSame('cite arxiv', $template->wikiname());
    }

    public function testTidy6b(): void {
        $text = "{{cite web|author=X|authors=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('author');
        $this->assertSame('X', $template->get2('DUPLICATE_authors'));
    }

    public function testTidy7(): void {
        $text = "{{cite web|author1=[[Hoser|Yoser]]}}";     // No longer do this, COINS now fixed
        $template = $this->make_citation($text);
        $template->tidy_parameter('author1');
        $this->assertSame('[[Hoser|Yoser]]', $template->get2('author1'));
        $this->assertNull($template->get2('author1-link'));
    }

    public function testTidy8(): void {
        $text = "{{cite web|bibcode=abookthisis}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('bibcode');
        $this->assertSame('cite book', $template->wikiname());
    }

    public function testTidy9(): void {
        $text = "{{cite web|title=XXX|chapter=XXX}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('chapter');
        $this->assertNull($template->get2('chapter'));
    }

    public function testTidy10(): void {
        $text = "{{cite web|doi=10.1267/science.040579197}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertNull($template->get2('doi'));
    }

    public function testTidy11(): void {
        $text = "{{cite web|doi=10.5284/1000184}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertNull($template->get2('doi'));
    }

    public function testTidy12(): void {
        $text = "{{cite web|doi=10.5555/TEST_DATA}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertNull($template->get2('doi'));
        $this->assertNull($template->get2('url'));
    }

    public function testTidy13(): void {
        $text = "{{cite web|format=Accepted manuscript}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('format');
        $this->assertNull($template->get2('format'));
    }

    public function testTidy14(): void {
        $text = "{{cite web|format=Submitted manuscript}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('format');
        $this->assertNull($template->get2('format'));
    }

    public function testTidy15(): void {
        $text = "{{cite web|format=Full text}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('format');
        $this->assertNull($template->get2('format'));
    }

    public function testTidy16(): void {
        $text = "{{cite web|chapter-format=Accepted manuscript}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('chapter-format');
        $this->assertNull($template->get2('chapter-format'));
    }

    public function testTidy17(): void {
        $text = "{{cite web|chapter-format=Submitted manuscript}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('chapter-format');
        $this->assertNull($template->get2('chapter-format'));
    }

    public function testTidy18(): void {
        $text = "{{cite web|chapter-format=Full text}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('chapter-format');
        $this->assertNull($template->get2('chapter-format'));
    }

    public function testTidy19(): void {
        $text = "{{cite web|chapter-format=portable document format|chapter-url=http://www.x.com/stuff.pdf}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('chapter-format');
        $this->assertNull($template->get2('chapter-format'));
        $text = "{{cite web|format=portable document format|url=http://www.x.com/stuff.pdf}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('format');
        $this->assertNull($template->get2('format'));
    }

    public function testTidy20(): void {
        $text = "{{cite web|chapter-format=portable document format|chapterurl=http://www.x.com/stuff.pdf}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('chapter-format');
        $this->assertNull($template->get2('chapter-format'));
    }

    public function testTidy21(): void {
        $text = "{{cite web|chapter-format=portable document format}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('chapter-format');
        $this->assertNull($template->get2('chapter-format'));
    }

    public function testTidy22(): void {
        $text = "{{cite web|periodical=X,}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('periodical');
        $this->assertSame('X', $template->get2('periodical'));
    }

    public function testTidy23(): void {
        $text = "{{cite journal|magazine=Xyz}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('magazine');
        $this->assertSame('Xyz', $template->get2('journal'));
    }

    public function testTidy24(): void {
        $text = "{{cite journal|others=|day=|month=}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('others');
        $template->tidy_parameter('day');
        $template->tidy_parameter('month');
        $this->assertSame('{{cite journal}}', $template->parsed_text());
    }

    public function testTidy25(): void {
        $text = "{{cite journal|archivedate=X|archive-date=X}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('archivedate');
        $this->assertNull($template->get2('archivedate'));
    }

    public function testTidy26(): void {
        $text = "{{cite journal|newspaper=X|publisher=X}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
    }

    public function testTidy27(): void {
        $text = "{{cite journal|publisher=Proquest|thesisurl=proquest}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('ProQuest', $template->get2('via'));
    }

    public function testTidy28(): void {
        $text = "{{cite journal|url=stuff.maps.google.stuff|publisher=something from google land}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('Google Maps', $template->get2('publisher'));
    }

    public function testTidy29(): void {
        $text = "{{cite journal|journal=X|publisher=X}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
    }

    public function testTidy30(): void {
        $text = "{{cite journal|series=Methods of Molecular Biology|journal=biomaas}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('series');
        $this->assertSame('cite book', $template->wikiname());
        $this->assertSame('biomaas', $template->get2('journal'));
    }

    public function testTidy31(): void {
        $text = "{{cite journal|series=Methods of Molecular Biology|journal=Methods of Molecular Biology}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('series');
        $this->assertSame('cite book', $template->wikiname());
        $this->assertNull($template->get2('journal'));
    }

    public function testTidy32(): void {
        $text = "{{cite journal|title=A title (PDF)|pmc=1234}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('title');
        $this->assertSame('A title', $template->get2('title'));
    }

    public function testTidy34(): void {
        $text = "{{cite journal|archive-url=http://web.archive.org/web/save/some_website}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('archive-url');
        $this->assertNull($template->get2('archive-url'));
    }

    public function testTidy35(): void {
        $text = "{{cite journal|archive-url=XYZ|url=XYZ}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('archive-url');
        $this->assertNull($template->get2('archive-url'));
    }

    public function testTidy36(): void {
        $text = "{{cite journal|series=|periodical=Methods of Molecular Biology}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('periodical');
        $this->assertSame('cite book', $template->wikiname());
        $this->assertSame('Methods of Molecular Biology', $template->get2('series'));
    }

    public function testTidy37(): void {
        $text = "{{cite journal|series=Methods of Molecular Biology|periodical=Methods of Molecular Biology}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('periodical');
        $this->assertSame('cite book', $template->wikiname());
        $this->assertSame('Methods of Molecular Biology', $template->get2('series'));
        $this->assertNull($template->get2('periodical'));
    }

    public function testTidy38(): void {
        $text = "{{cite journal|archiveurl=http://researchgate.net/publication/1234_feasdfafdsfsd|title=(PDF) abc}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('archiveurl');
        $this->assertSame('https://www.researchgate.net/publication/1234', $template->get2('archiveurl'));
        $this->assertSame('abc', $template->get2('title'));
    }

    public function testTidy39(): void {
        $text = "{{cite journal|archiveurl=http://academia.edu/documents/1234_feasdfafdsfsd}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('archiveurl');
        $this->assertSame('https://www.academia.edu/1234', $template->get2('archiveurl'));
    }

    public function testTidy40(): void {
        $text = "{{cite journal|archiveurl=https://zenodo.org/record/1234/files/dsafsd}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('archiveurl');
        $this->assertSame('https://zenodo.org/record/1234', $template->get2('archiveurl'));
    }

    public function testTidy42(): void {
        $text = "{{cite journal|archiveurl=https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&btnG=Search&oq=%22institute+for+sustainable+weight+loss%22&aqs=chrome..69i57j69i59.14823j0j7&sourceid=chrome&ie=UTF-8&oe=Bogus&rct=ABC}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('archiveurl');
        $this->assertSame('https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&oe=Bogus&rct=ABC', $template->get2('archiveurl'));
    }

    public function testTidy43(): void {
        $text = "{{cite journal|archiveurl=https://sciencedirect.com/stuff_stuff?via=more_stuff}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('archiveurl');
        $this->assertSame('https://sciencedirect.com/stuff_stuff', $template->get2('archiveurl'));
    }

    public function testTidy44(): void {
        $text = "{{cite journal|archiveurl=https://bloomberg.com/stuff_stuff?utm_=more_stuff}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('archiveurl');
        $this->assertSame('https://bloomberg.com/stuff_stuff', $template->get2('archiveurl'));
    }

    public function testTidy45(): void {
        $text = "{{cite journal|url=http://researchgate.net/publication/1234_feasdfafdsfsd|title=(PDF) abc}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.researchgate.net/publication/1234', $template->get2('url'));
        $this->assertSame('abc', $template->get2('title'));
    }

    public function testTidy46(): void {
        $text = "{{cite journal|url=http://academia.edu/documents/1234_feasdfafdsfsd}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.academia.edu/1234', $template->get2('url'));
    }

    public function testTidy47(): void {
        $text = "{{cite journal|url=https://zenodo.org/record/1234/files/dfasd}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://zenodo.org/record/1234', $template->get2('url'));
    }

    public function testTidy48(): void {
        $text = "{{cite journal|url=https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&oq=%22institute+for+sustainable+weight+loss%22&aqs=chrome..69i57j69i59.14823j0j7&sourceid=chrome&ie=UTF-8&btnG=YUP&cshid=X}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.google.com/search?q=%22institute+for+sustainable+weight+loss%22&btnG=YUP&cshid=X', $template->get2('url'));
    }

    public function testTidy49(): void {
        $text = "{{cite journal|url=https://sciencedirect.com/stuff_stuff?via=more_stuff}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://sciencedirect.com/stuff_stuff', $template->get2('url'));
    }

    public function testTidy50(): void {
        $text = "{{cite journal|url=https://bloomberg.com/stuff_stuff?utm_=more_stuff}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://bloomberg.com/stuff_stuff', $template->get2('url'));
    }

    public function testTidy51(): void {
        $text = "{{cite journal|url=https://watermark.silverchair.com/rubbish}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertNull($template->get2('url'));
    }

    public function testTidy52(): void {
        $text = "{{cite journal|url=https://watermark.silverchair.com/rubbish|archiveurl=has_one}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://watermark.silverchair.com/rubbish', $template->get2('url'));
    }

    public function testTidy53(): void {
        $text = "{{cite journal|archiveurl=https://watermark.silverchair.com/rubbish}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('archiveurl');
        $this->assertNull($template->get2('archiveurl'));
    }

    public function testTidy53b(): void {
        $text = "{{cite journal|url=https://s3.amazonaws.com/academia.edu/stuff}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertNull($template->get2('url'));
    }

    public function testTidy53c(): void {
        $text = "{{cite journal|archiveurl=https://s3.amazonaws.com/academia.edu/stuff}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('archiveurl');
        $this->assertNull($template->get2('archiveurl'));
    }

    public function testTidy54(): void {
        $text = "{{cite journal|url=https://ieeexplore.ieee.org.proxy/document/1234}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://ieeexplore.ieee.org/document/1234', $template->get2('url'));
    }

    public function testTidy54b(): void {
        $text = "{{cite journal|url=https://ieeexplore.ieee.org.proxy/iel5/232/32/123456.pdf?yup}}";
        $template = $this->make_citation($text);
        $template->get_identifiers_from_url();
        $this->assertSame('https://ieeexplore.ieee.org/document/123456', $template->get2('url'));
    }

    public function testTidy55(): void {
        $text = "{{cite journal|url=https://www.oxfordhandbooks.com.proxy/view/1234|via=Library}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.oxfordhandbooks.com/view/1234', $template->get2('url'));
        $this->assertNull($template->get2('via'));
    }

    public function testTidy55B(): void { // Do not drop AU
        $text = "{{cite news|title=Misogynist rants from Young Libs|url=http://www.theage.com.au/victoria/misogynist-rants-from-young-libs-20140809-3dfhw.html|accessdate=10 August 2014|newspaper=[[The Age]]|date=10 August 2014|agency=[[Fairfax Media]]}}";
        $template = $this->process_citation($text);
        $this->assertSame('http://www.theage.com.au/victoria/misogynist-rants-from-young-libs-20140809-3dfhw.html', $template->get2('url'));
    }

    public function testTidy56(): void {
        $text = "{{cite journal|url=https://www.oxfordartonline.com.proxy/view/1234|via=me}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.oxfordartonline.com/view/1234', $template->get2('url'));
        $this->assertSame('me', $template->get2('via'));
    }

    public function testTidy57(): void {
        $text = "{{cite journal|url=https://sciencedirect.com.proxy/stuff_stuff|via=the via}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.sciencedirect.com/stuff_stuff', $template->get2('url'));
    }

    public function testTidy58(): void {
        $text = "{{cite journal|url=https://www.random.com.mutex.gmu/stuff_stuff|via=the via}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.random.com/stuff_stuff', $template->get2('url'));
        $this->assertNull($template->get2('via'));
    }

    public function testTidy59(): void {
        $text = "{{cite journal|url=https://www-random-com.mutex.gmu/stuff_stuff|via=the via}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.random.com/stuff_stuff', $template->get2('url'));
        $this->assertNull($template->get2('via'));
    }

    public function testTidy60(): void {
        $text = "{{cite journal|url=http://proxy/url=https://go.galegroup.com%2fpsSTUFF}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://go.galegroup.com/psSTUFF', $template->get2('url'));
    }

    public function testTidy61(): void {
        $text = "{{cite journal|url=http://proxy/url=https://go.galegroup.com/STUFF}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://go.galegroup.com/STUFF', $template->get2('url'));
    }

    public function testTidy62(): void {
        $text = "{{cite journal|url=http://proxy/url=https://link.galegroup.com%2fpsSTUFF}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://link.galegroup.com/psSTUFF', $template->get2('url'));
    }

    public function testTidy63(): void {
        $text = "{{cite journal|url=http://proxy/url=https://link.galegroup.com/STUFF}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://link.galegroup.com/STUFF', $template->get2('url'));
    }

    public function testTidy64(): void {
        $text = "{{cite journal|url=https://go.galegroup.com/STUFF&u=UNIV&date=1234}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://go.galegroup.com/STUFF&u=UNIV&date=1234', $template->get2('url'));
    }

    public function testTidy65(): void {
        $text = "{{cite journal|url=https://link.galegroup.com/STUFF&u=UNIV&date=1234}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://link.galegroup.com/STUFF&u=UNIV&date=1234', $template->get2('url'));
    }

    public function testTidy66(): void {
        $text = "{{cite journal|url=https://search.proquest.com/STUFF/docview/1234/STUFF}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.proquest.com/docview/1234', $template->get2('url'));
    }

    public function testTidy66b(): void {
        $text = "{{cite journal|url=http://host.com/login?url=https://search-proquest-com-stuff/STUFF/docview/1234/34123/342}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.proquest.com/docview/1234', $template->get2('url'));
    }

    public function testTidy66c(): void {
        $text = "{{cite journal|url=https://search.proquest.com/docview/1234abc}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.proquest.com/docview/1234abc', $template->get2('url'));
    }

    public function testTidy66d(): void {
        $text = "{{cite journal|url=https://search.proquest.com/openview/1234abc}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.proquest.com/openview/1234abc', $template->get2('url'));
    }

    public function testTidy66e(): void {
        $text = "{{cite journal|url=https://search.proquest.com/docview/1234abc/se-32413232}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.proquest.com/docview/1234abc', $template->get2('url'));
    }

    public function testTidy67(): void {
        $text = "{{cite journal|url=https://0-search-proquest-com.schoo.org/STUFF/docview/1234/2314/3214}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.proquest.com/docview/1234', $template->get2('url'));
    }

    public function testTidy67b(): void {
        $text = "{{cite journal|url=https://0-search-proquest-com.scoolaid.net/STUFF/docview/1234/2314/3214|via=library proquest}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.proquest.com/docview/1234', $template->get2('url'));
        $this->assertNull($template->get2('via'));
    }

    public function testTidy67c(): void {
        $text = "{{cite journal|url= http://host.com/login/?url=https://0-search-proquest-com.scoolaid.net/STUFF/docview/1234/2314/3214|via=dude}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.proquest.com/docview/1234', $template->get2('url'));
        $this->assertNull($template->get2('via'));
    }

    public function testTidy67d(): void {
        $text = "{{cite journal|url= http://host.com/login/?url=https://0-search-proquest.scoolaid.net/STUFF/docview/1234/2314/3214|via=dude}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.proquest.com/docview/1234', $template->get2('url'));
        $this->assertNull($template->get2('via'));
    }

    public function testTidy69(): void {
        $text = "{{cite journal|url=https://search.proquest.com/dissertations/docview/1234}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.proquest.com/dissertations/docview/1234', $template->get2('url'));
    }

    public function testTidy70(): void {
        $text = "{{cite journal|url=https://search.proquest.com/docview/1234/fulltext}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.proquest.com/docview/1234', $template->get2('url'));
    }

    public function testTidy70b(): void {
        $text = "{{cite journal|url=https://search.proquest.com/docview/1234?account=XXXXX}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.proquest.com/docview/1234', $template->get2('url'));
    }

    public function testTidy71(): void {
        $text = "{{cite journal|pmc = pMC12341234}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('pmc');
        $this->assertSame('12341234', $template->get2('pmc'));
    }

    public function testTidy72a(): void {
        $text = "{{cite journal|quotes=false}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('quotes');
        $this->assertNull($template->get2('quotes'));
    }

    public function testTidy72b(): void {
        $text = "{{cite journal|quotes=Y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('quotes');
        $this->assertNull($template->get2('quotes'));
    }

    public function testTidy72c(): void {
        $text = "{{cite journal|quotes=Hello There}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('quotes');
        $this->assertSame('Hello There', $template->get2('quotes'));
    }

    public function testTidy73a(): void {
        $text = "{{cite web|journal=www.cnn.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('journal');
        $this->assertSame(str_replace('journal', 'website', $text), $template->parsed_text());
    }

    public function testTidy73b(): void {
        $text = "{{cite web|journal=cnn.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('journal');
        $this->assertSame(str_replace('journal', 'website', $text), $template->parsed_text());
    }

    public function testTidy73c(): void {
        $text = "{{cite web|journal=www.x}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('journal');
        $this->assertSame(str_replace('journal', 'website', $text), $template->parsed_text());
    }

    public function testTidy73d(): void {
        $text = "{{cite web|journal=theweb.org}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('journal');
        $this->assertSame(str_replace('journal', 'website', $text), $template->parsed_text());
    }

    public function testTidy73e(): void {
        $text = "{{cite web|journal=theweb.net}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('journal');
        $this->assertSame(str_replace('journal', 'website', $text), $template->parsed_text());
    }

    public function testTidy73f(): void {
        $text = "{{cite web|journal=the web.net}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('journal');
        $this->assertNull($template->get2('website'));
    }

    public function testTidy74c(): void {
        $text = "{{cite web|journal=openid transaction in progress|isbn=1234|chapter=X|title=Y}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertNull($template->get2('journal'));
    }

    public function testTidy75a(): void {
        $text = "{{cite web|url=developers.google.com|publisher=the google hive mind}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('Google Inc.', $template->get2('publisher'));
    }

    public function testTidy75b(): void {
        $text = "{{cite web|url=support.google.com|publisher=the Google hive mind}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('Google Inc.', $template->get2('publisher'));
    }

    public function testTidy77(): void {
        $text = "{{cite journal |pages=Pages: 1-2 }}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('pages');
        $this->assertSame('1–2', $template->get2('pages'));
    }

    public function testTidy78(): void {
        $text = "{{cite journal |pages=p. 1-2 }}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('pages');
        $this->assertSame('1–2', $template->get2('pages'));
    }

    public function testTidy79(): void {
        $text = "{{cite arxiv|website=arXiv}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('website');
        $this->assertNull($template->get2('website'));
    }

    public function testTidy80(): void {
        $text = "{{cite web|url=https://www-rocksbackpages-com.wikipedialibrary.idm.oclc.org/Library/Article/camel-over-the-moon |via = wiki stuff }}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://www.rocksbackpages.com/Library/Article/camel-over-the-moon', $template->get2('url'));
        $this->assertNull($template->get2('via'));
    }

    public function testTidy81(): void {
        $text = "{{cite web|url=https://rocksbackpages-com.wikipedialibrary.idm.oclc.org/Library/Article/camel-over-the-moon |via=My Dog}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://rocksbackpages.com/Library/Article/camel-over-the-moon', $template->get2('url'));
        $this->assertSame('My Dog', $template->get2('via'));
    }

    public function testTidy82(): void {
        $text = "{{cite web|url=https://butte.idm.oclc.org/login?qurl=http://search.ebscohost.com/X}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('http://search.ebscohost.com/X', $template->get2('url'));
    }

    public function testTidy83(): void {
        $text = "{{cite web|url=https://butte.idm.oclc.org/login?url=http://search.ebscohost.com/X}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('http://search.ebscohost.com/X', $template->get2('url'));
    }

    public function testTidy84(): void {
        $text = "{{cite web|url=https://go.galegroup.com.ccclibrary.idm.oclc.org/X}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://go.galegroup.com/X', $template->get2('url'));
    }

    public function testTidy85(): void {
        $text = "{{cite web|url=https://butte.idm.oclc.org/login?url=http://search.ebscohost.com%2fX}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('http://search.ebscohost.com/X', $template->get2('url'));
    }

    public function testTidy86(): void {
        $text = "{{cite web|url=https://login.libproxy.union.edu/login?qurl=https://go.gale.com%2fps%2fretrieve.do%3ftabID%3dT002%26resultListType%3dRESULT_LIST%26searchResultsType%3dSingleTab%26searchType%3dBasicSearchForm%26currentPosition%3d1%26docId%3dGALE%257CA493733315%26docType%3dArticle%26sort%3dRelevance%26contentSegment%3dZGPP-MOD1%26prodId%3dITOF%26contentSet%3dGALE%257CA493733315%26searchId%3dR2%26userGroupName%3dnysl_ca_unionc%26inPS%3dtrue}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('https://go.gale.com/ps/retrieve.do?tabID=T002&resultListType=RESULT_LIST&searchResultsType=SingleTab&searchType=BasicSearchForm&currentPosition=1&docId=GALE%7CA493733315&docType=Article&sort=Relevance&contentSegment=ZGPP-MOD1&prodId=ITOF&contentSet=GALE%7CA493733315&searchId=R2&userGroupName=nysl_ca_unionc&inPS=true', $template->get2('url'));
    }

    public function testTidy87(): void {
        $text = "{{cite web|url=https://login.libproxy.union.edu/login?url=https://go.gale.com/ps/retrieve.do?tabID=T002&resultListType=RESULT_LIST&searchResultsType=SingleTab&searchType=BasicSearchForm&currentPosition=1&docId=GALE%7CA493733315&docType=Article&sort=Relevance&contentSegment=ZGPP-MOD1&prodId=ITOF&contentSet=GALE%7CA493733315&searchId=R2&userGroupName=nysl_ca_unionc&inPS=true}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame("https://go.gale.com/ps/retrieve.do?tabID=T002&resultListType=RESULT_LIST&searchResultsType=SingleTab&searchType=BasicSearchForm&currentPosition=1&docId=GALE%7CA493733315&docType=Article&sort=Relevance&contentSegment=ZGPP-MOD1&prodId=ITOF&contentSet=GALE%7CA493733315&searchId=R2&userGroupName=nysl_ca_unionc&inPS=true", $template->get2('url'));
    }

    public function testTidy88(): void {
        $text = "{{cite web|url=https://login.libproxy.union.edu/login?url=https%3A%2F%2Fgo.gale.com/ps/retrieve.do?tabID=T002&resultListType=RESULT_LIST&searchResultsType=SingleTab&searchType=BasicSearchForm&currentPosition=1&docId=GALE%7CA493733315&docType=Article&sort=Relevance&contentSegment=ZGPP-MOD1&prodId=ITOF&contentSet=GALE%7CA493733315&searchId=R2&userGroupName=nysl_ca_unionc&inPS=true}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame("https://go.gale.com/ps/retrieve.do?tabID=T002&resultListType=RESULT_LIST&searchResultsType=SingleTab&searchType=BasicSearchForm&currentPosition=1&docId=GALE|A493733315&docType=Article&sort=Relevance&contentSegment=ZGPP-MOD1&prodId=ITOF&contentSet=GALE|A493733315&searchId=R2&userGroupName=nysl_ca_unionc&inPS=true", $template->get2('url'));
    }

    public function testTidy89a(): void {
        $text = "{{cite web|asin=0671748750}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('asin');
        $this->assertSame("0671748750", $template->get2('isbn'));
        $this->assertNull($template->get2('asin'));
    }

    public function testTidy89b(): void {
        $text = "{{cite web|asin=6371748750}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('asin');
        $this->assertSame("6371748750", $template->get2('asin'));
        $this->assertNull($template->get2('isbn'));
    }

    public function testIncomplete1a(): void {
        $text = "{{cite book|url=http://perma-archives.org/pqd1234|isbn=Xxxx|title=xxx|issue=a|volume=x}}"; // Non-date website
        $template = $this->make_citation($text);
        $this->assertTrue($template->profoundly_incomplete());
        $this->assertTrue($template->profoundly_incomplete('http://perma-archives.org/pqd1234'));
    }

    public function testIncomplete1b(): void {
        $text = "{{cite book|url=http://a_perfectly_acceptable_website/pqd1234|isbn=Xxxx|issue=hh|volume=rrfff|title=xxx}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->profoundly_incomplete());
        $this->assertTrue($template->profoundly_incomplete('http://a_perfectly_acceptable_website/pqd1234'));
        $this->assertTrue($template->profoundly_incomplete('http://perma-archives.org/pqd1234'));
    }

    public function testIncomplete2(): void {
        $text = "{{cite book|url=http://perma-archives.org/pqd1234|isbn=Xxxx|title=xxx|issue=a|volume=x|author1=Yes}}"; // Non-date website
        $template = $this->make_citation($text);
        $this->assertTrue($template->profoundly_incomplete());
        $this->assertFalse($template->profoundly_incomplete('http://perma-archives.org/pqd1234'));
    }

    public function testIncomplete3(): void {
        $text = "{{cite web|url=http://foxnews.com/x|website=Fox|title=xxx|issue=a|year=2000}}"; // Non-journal website
        $template = $this->make_citation($text);
        $this->assertTrue($template->profoundly_incomplete());
        $this->assertFalse($template->profoundly_incomplete('http://foxnews.com/x'));
    }

    public function testIncomplete4(): void {
        $text = "{{cite web|url=http://foxnews.com/x|contribution=Fox|title=xxx|issue=a|year=2000}}"; // Non-journal website
        $template = $this->make_citation($text);
        $this->assertTrue($template->profoundly_incomplete());
        $this->assertFalse($template->profoundly_incomplete('http://foxnews.com/x'));
    }

    public function testIncomplete5(): void {
        $text = "{{cite web|url=http://foxnews.com/x|encyclopedia=Fox|title=xxx|issue=a|year=2000}}"; // Non-journal website
        $template = $this->make_citation($text);
        $this->assertTrue($template->profoundly_incomplete());
        $this->assertFalse($template->profoundly_incomplete('http://foxnews.com/x'));
    }

    public function testAddEditor_1(): void {
        $text = "{{cite journal}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('editor-last1', 'Phil'));
        $this->assertSame('Phil', $template->get2('editor-last1'));
    }

    public function testAddEditor_2(): void {
        $text = "{{cite journal|editor-last=Junk}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('editor-last1', 'Phil'));
    }

    public function testAddEditor_3(): void {
        $text = "{{cite journal}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('editor1', 'Phil'));
        $this->assertSame('Phil', $template->get2('editor1'));
    }

    public function testAddEditor_4(): void {
        $text = "{{cite journal|editor-last=Junk}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('editor1', 'Phil'));
    }

    public function testAddFirst_1(): void {
        $text = "{{cite journal}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('first1', 'X M'));
        $this->assertSame('X. M.', $template->get2('first1'));
    }

    public function testAddFirst_2(): void {
        $text = "{{cite journal}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('first2', 'X M'));
        $this->assertSame('X. M.', $template->get2('first2'));
    }

    public function testDisplayEd(): void {
        $text = "{{cite journal}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('display-editors', '3'));
        $this->assertSame('3', $template->get2('display-editors'));
    }

    public function testArchiveDate_1(): void {
        $text = "{{cite journal}}";
        $template = $this->make_citation($text);
        Template::$date_style = DateStyle::DATES_MDY;
        $this->assertTrue($template->add_if_new('archive-date', '20 JAN 2010'));
        $this->assertSame('January 20, 2010', $template->get2('archive-date'));
    }

    public function testArchiveDate_2(): void {
        $text = "{{cite journal}}";
        $template = $this->make_citation($text);
        Template::$date_style = DateStyle::DATES_DMY;
        $this->assertTrue($template->add_if_new('archive-date', '20 JAN 2010'));
        $this->assertSame('20 January 2010', $template->get2('archive-date'));
    }

    public function testArchiveDate_3(): void {
        $text = "{{cite journal}}";
        $template = $this->make_citation($text);
        Template::$date_style = DateStyle::DATES_WHATEVER;
        $this->assertTrue($template->add_if_new('archive-date', '20 JAN 2010'));
        $this->assertSame('20 January 2010', $template->get2('archive-date'));
    }

    public function testArchiveDate_4(): void {
        $text = "{{cite journal}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('archive-date', 'SDAFEWFEWW#F#WFWEFESFEFSDFDFD'));
        $this->assertNull($template->get2('archive-date'));
        Template::$date_style = DateStyle::DATES_ISO;
        $this->assertTrue($template->add_if_new('archive-date', '20 JAN 2010'));
        $this->assertSame('2010-01-20', $template->get2('archive-date'));
    }

    public function testIssuesOnly(): void {
        $text = "{{cite journal |last=Rauhut |first=O.W. |year=2004 |title=The interrelationships and evolution of basal theropod dinosaurs |journal=Special Papers in Palaeontology |volume=69 |pages=213}}";
        $template = $this->process_citation($text); // Does not drop year - terrible bug
        $this->assertSame($text, $template->parsed_text());
    }

    public function testAccessDate1(): void {
        $text = "{{cite journal|url=XXX}}";
        $template = $this->make_citation($text);
        Template::$date_style = DateStyle::DATES_MDY;
        $this->assertTrue($template->add_if_new('access-date', '20 JAN 2010'));
        $this->assertSame('January 20, 2010', $template->get2('access-date'));
    }

    public function testAccessDate2(): void {
        $text = "{{cite journal|url=XXX}}";
        $template = $this->make_citation($text);
        Template::$date_style = DateStyle::DATES_DMY;
        $this->assertTrue($template->add_if_new('access-date', '20 JAN 2010'));
        $this->assertSame('20 January 2010', $template->get2('access-date'));
    }

    public function testAccessDate3(): void {
        $text = "{{cite journal|url=XXX}}";
        $template = $this->make_citation($text);
        Template::$date_style = DateStyle::DATES_WHATEVER;
        $this->assertTrue($template->add_if_new('access-date', '20 JAN 2010'));
        $this->assertSame('20 January 2010', $template->get2('access-date'));
    }

    public function testAccessDate4(): void {
        $text = "{{cite journal|url=XXX}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('access-date', 'SDAFEWFEWW#F#WFWEFESFEFSDFDFD'));
        $this->assertNull($template->get2('access-date'));
    }

    public function testAccessDate5(): void {
        $text = "{{cite journal}}"; // NO url
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('access-date', '20 JAN 2010')); // Pretty bogus return value
        $this->assertNull($template->get2('access-date'));
    }

    public function testWorkStuff1(): void {
        $text = "{{cite journal|work=Yes Indeed}}";
        $template = $this->make_citation($text);
        $template->add_if_new('journal', 'Yes indeed');
        $this->assertSame('Yes Indeed', $template->get2('journal'));
        $this->assertNull($template->get2('work'));
    }

    public function testWorkStuff2(): void {
        $text = "{{cite journal|work=Yes Indeed}}";
        $template = $this->make_citation($text);
        $template->add_if_new('journal', 'No way sir');
        $this->assertSame('Yes Indeed', $template->get2('work'));
        $this->assertNull($template->get2('journal'));
    }

    public function testViaStuff(): void {
        $text = "{{cite journal|via=Yes Indeed}}";
        $template = $this->make_citation($text);
        $template->add_if_new('journal', 'Yes indeed');
        $this->assertSame('Yes Indeed', $template->get2('journal'));
        $this->assertNull($template->get2('via'));
    }

    public function testNewspaperJournal(): void {
        $text = "{{cite journal|publisher=news.bbc.co.uk}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('newspaper', 'news.bbc.co.uk'));
        $this->assertNull($template->get2('newspaper'));
    }

    public function testNewspaperJournalBBC(): void {
        $text = "{{cite journal|publisher=Bbc.com}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('newspaper', 'BBC News'));
        $this->assertNull($template->get2('newspaper'));
        $this->assertSame('BBC News', $template->get2('work'));
        $this->assertNull($template->get2('publisher'));
    }

    public function testNewspaperJournaXl(): void {
        $text = "{{cite journal|work=exists}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('newspaper', 'news.bbc.co.uk'));
        $this->assertNull($template->get2('newspaper'));
        $this->assertSame('exists', $template->get2('work'));
    }

    public function testNewspaperJournaXk(): void {
        $text = "{{cite journal|via=This is from the times}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('newspaper', 'Times'));
        $this->assertNull($template->get2('via'));
        $this->assertSame('Times', $template->get2('newspaper'));
    }

    public function testNewspaperJournal100(): void {
        $text = "{{cite journal|work=A work}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('newspaper', 'news.bbc.co.uk'));
        $this->assertNull($template->get2('newspaper'));
    }

    public function testNewspaperJournal101(): void {
        $text = "{{cite web|website=xyz}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('newspaper', 'news.bbc.co.uk'));
        $this->assertNull($template->get2('website'));
        $this->assertSame('News.BBC.co.uk', $template->get2('work'));
    }

    public function testNewspaperJournal102(): void {
        $text = "{{cite journal|website=xyz}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('newspaper', 'Junk and stuff'));
        $this->assertNull($template->get2('website'));
        $this->assertSame('Junk and Stuff', $template->get2('newspaper'));
    }

    public function testNewspaperJournal2(): void {
        $text = "{{cite journal|via=Something}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('newspaper', 'A newspaper'));
    }

    public function testNewspaperJournal2aa(): void {
        $text = "{{cite journal|via=Times}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('newspaper', 'The Times'));
        $this->assertNull($template->get2('via'));
        $this->assertSame('Times', $template->get2('newspaper'));
    }

    public function testNewspaperJournal2bb(): void {
        $text = "{{cite journal|via=A Post website}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('newspaper', 'The Sun Post'));
        $this->assertNull($template->get2('via'));
        $this->assertSame('The Sun Post', $template->get2('newspaper'));
    }

    public function testNewspaperJournal3(): void {
        $text = "{{cite journal|publisher=A Big Company}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('newspaper', 'A Big Company'));
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('A Big Company', $template->get2('newspaper'));
    }

    public function testNewspaperJournal4a(): void {
        $text = "{{cite journal|website=A Big Company}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('journal', 'A Big Company'));
        $this->assertSame('A Big Company', $template->get2('journal'));
        $this->assertNull($template->get2('website'));
    }

    public function testNewspaperJournal4b(): void {
        $text = "{{cite journal|website=A Big Company}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('journal', 'A Small Little Company'));
        $this->assertSame('A Small Little Company', $template->get2('journal'));
        $this->assertNull($template->get2('website'));
    }

    public function testNewspaperJournal4c(): void {
        $text = "{{cite journal|website=[[A Big Company]]}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('journal', 'A Small Little Company'));
        $this->assertSame('[[A Big Company]]', $template->get2('journal'));
        $this->assertNull($template->get2('website'));
    }

    public function testAddTwice(): void {
        $text = "{{cite journal}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('series', 'The Sun Post'));
        $this->assertFalse($template->add_if_new('series', 'The Dog'));
        $this->assertSame('The Sun Post', $template->get2('series'));
    }

    public function testExistingIsTitle1(): void {
        $text = "{{cite journal|encyclopedia=Existing Data}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('title', 'Existing Data'));
        $this->assertNull($template->get2('title'));
    }

    public function testExistingIsTitle2(): void {
        $text = "{{cite journal|dictionary=Existing Data}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('title', 'Existing Data'));
        $this->assertNull($template->get2('title'));
    }

    public function testExistingIsTitle3(): void {
        $text = "{{cite journal|journal=Existing Data}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('title', 'Existing Data'));
        $this->assertNull($template->get2('title'));
    }

    public function testUpdateIssue(): void {
        $text = "{{cite journal|issue=1|volume=}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('issue', '7'));
        $this->assertFalse($template->add_if_new('issue', '8'));
        $this->assertSame('7', $template->get2('issue'));
    }

    public function testExistingCustomPage(): void {
        $text = "{{cite journal|pages=footnote 7}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('pages', '3-22'));
        $this->assertSame('footnote 7', $template->get2('pages'));
    }

    public function testPagesIsArticle(): void {
        $text = "{{cite journal|pages=431234}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('pages', '1-34'));
        $this->assertSame('431234', $template->get2('pages'));
    }

    public function testExitingURL1(): void {
        $text = "{{cite journal|conferenceurl=http://XXXX-TEST.COM}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('url', 'http://XXXX-TEST.COM'));
        $this->assertNull($template->get2('url'));
    }

    public function testExitingURL2(): void {
        $text = "{{cite journal|url=xyz}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('title-link', 'abc'));
        $this->assertNull($template->get2('title-lin'));
    }

    public function testResearchGateDOI(): void {
        $text = "{{cite journal|doi=10.13140/RG.2.2.26099.32807}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('doi', '10.1002/jcc.21074'));       // Not the same article, random
        $this->assertSame('10.1002/jcc.21074', $template->get2('doi'));
    }

    public function testResearchJstorDOI(): void {
        $text = "{{cite journal|doi=10.2307/1974136}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('doi-broken-date', '1 DEC 2019'));
        $this->assertNull($template->get2('doi'));
    }

    public function testAddBrokenDateFormat1(): void {
        $text = "{{cite journal|doi=10.0000/Rubbish_bot_failure_test}}";
        $template = $this->make_citation($text);
        $this->assertTrue($template->add_if_new('doi-broken-date', '1 DEC 2019'));
        $this->assertSame('1 December 2019', $template->get2('doi-broken-date'));
    }

    public function testAddBrokenDateFormat2(): void {
        $text = "{{cite journal|doi=10.0000/Rubbish_bot_failure_test}}";
        $template = $this->make_citation($text);
        Template::$date_style = DateStyle::DATES_MDY;
        $this->assertTrue($template->add_if_new('doi-broken-date', '1 DEC 2019'));
        $this->assertSame('December 1, 2019', $template->get2('doi-broken-date'));
    }

    public function testAddBrokenDateFormat3(): void {
        $text = "{{cite journal|doi=10.0000/Rubbish_bot_failure_test}}";
        $template = $this->make_citation($text);
        Template::$date_style = DateStyle::DATES_DMY;
        $this->assertTrue($template->add_if_new('doi-broken-date', '1 DEC 2019'));
        $this->assertSame('1 December 2019', $template->get2('doi-broken-date'));
    }

    public function testNotBrokenDOI(): void {
        $text = "{{cite journal|doi-broken-date = # # # CITATION_BOT_PLACEHOLDER_COMMENT # # # }}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->add_if_new('doi-broken-date', '1 DEC 2019'));
    }

    public function testForgettersChangeType1(): void {
        $text = "{{cite web|id=x}}";
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertSame('cite web', $template->wikiname());
    }

    public function testForgettersChangeType2(): void {
        $text = "{{cite web|journal=X}}";
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertSame('cite journal', $template->wikiname());
    }

    public function testForgettersChangeType3(): void {
        $text = "{{cite web|newspaper=X}}";
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertSame('cite news', $template->wikiname());
    }

    public function testForgettersChangeType4(): void {
        $text = "{{cite web|chapter=X}}";
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertSame('cite book', $template->wikiname());
    }

    public function testForgettersChangeOtherURLS(): void {
        $text = "{{cite web|chapter-url=Y|chapter=X}}";
        $template = $this->make_citation($text);
        $template->forget('chapter');
        $this->assertSame('Y', $template->get2('url'));
    }

    public function testForgettersChangeOtherURLS_2(): void {
        $text = "{{cite web|chapterurl=Y|chapter=X}}";
        $template = $this->make_citation($text);
        $template->forget('chapter');
        $this->assertSame('Y', $template->get2('url'));
    }

    public function testForgettersChangeWWWWork(): void {
        $text = "{{cite web|url=X|work=www.apple.com}}";
        $template = $this->make_citation($text);
        $template->forget('url');
        $this->assertNull($template->get2('work'));
    }

    public function testCommentShields(): void {
        $text = "{{cite web|work = # # CITATION_BOT_PLACEHOLDER_COMMENT # #}}";
        $template = $this->make_citation($text);
        $this->assertFalse($template->set('work', 'new'));
        $this->assertSame('# # CITATION_BOT_PLACEHOLDER_COMMENT # #', $template->get2('work'));
    }

    public function testRenameSpecialCases1(): void {
        $text = "{{cite web|id=x}}";
        $template = $this->make_citation($text);
        $template->rename('work', 'work');
        $template->rename('work', 'work', 'new');
        $this->assertSame('new', $template->get2('work'));
    }

    public function testRenameSpecialCases2(): void {
        $text = "{{cite web|id=x}}";
        $template = $this->make_citation($text);
        $template->rename('work', 'journal');
        $template->rename('work', 'journal', 'new');
        $this->assertSame('New', $template->get2('journal'));
    }

    public function testRenameSpecialCases3(): void {
        $text = "{{cite web}}"; // param will be null
        $template = $this->make_citation($text);
        $template->rename('work', 'journal');
        $template->rename('work', 'journal', 'new');
        $this->assertNull($template->get2('journal'));
    }

    public function testRenameSpecialCases4(): void {
        $text = "{{cite web|title=X|url=Y}}";
        $template = $this->make_citation($text);
        $template->rename('title', 'chapter');
        $this->assertNull($template->get2('url'));
        $this->assertNull($template->get2('title'));
        $this->assertSame('X', $template->get2('chapter'));
        $this->assertSame('Y', $template->get2('chapter-url'));
    }

    public function testModificationsOdd(): void {
        $text = "{{cite web}}"; // param will be null to start
        $template = $this->make_citation($text);
        $this->assertTrue($template->add('work', 'The Journal'));
        $this->assertSame('The Journal', $template->get2('work'));
        $this->assertNull($template->get2('journal'));
        $ret = $template->modifications();
        $this->assertTrue(isset($ret['deletions']));
        $this->assertTrue(isset($ret['changeonly']));
        $this->assertTrue(isset($ret['additions']));
        $this->assertTrue(isset($ret['dashes']));
        $this->assertTrue(isset($ret['names']));
    }

    public function testTidyMR(): void {
        $text = "{{cite web|mr=mr2343}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('mr');
        $this->assertSame('2343', $template->get2('mr'));
    }

    public function testTidyAgency1(): void {
        $text = "{{cite web|agency=associated press|url=apnews.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('agency');
        $this->assertSame('associated press', $template->get2('work'));
        $this->assertNull($template->get2('agency'));
    }

    public function testTidyAgency2(): void {
        $text = "{{cite web|agency=Associated Press|url=apnews.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('agency');
        $this->assertSame('Associated Press News', $template->get2('work'));
        $this->assertNull($template->get2('agency'));
    }

    public function testTidyAgency3(): void {
        $text = "{{cite web|agency=AP|url=apnews.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('agency');
        $this->assertSame('AP News', $template->get2('work'));
        $this->assertNull($template->get2('agency'));
    }

    public function testTidyClass(): void {
        $text = "{{cite web|class=|series=X}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('class');
        $this->assertNull($template->get2('class'));
    }

    public function testTidyMonth1(): void {
        $text = "{{cite web|day=3|month=Dec|year=2000}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('month');
        $this->assertNull($template->get2('day'));
        $this->assertNull($template->get2('month'));
        $this->assertSame('3 Dec 2000', $template->get2('date'));
    }

    public function testTidyMonth2(): void {
        $text = "{{cite web|month=Dec|year=2000}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('month');
        $this->assertNull($template->get2('day'));
        $this->assertNull($template->get2('month'));
        $this->assertSame('Dec 2000', $template->get2('date'));
    }

    public function testGetYear1(): void {
        $text = "{{cite web|date=2000 Nov}}";
        $template = $this->make_citation($text);
        $this->assertSame('2000', $template->year());
    }

    public function testGetYear2(): void {
        $text = "{{cite web|date=Nov 2000}}";
        $template = $this->make_citation($text);
        $this->assertSame('2000', $template->year());
    }

    public function testGetYear3(): void {
        $text = "{{cite web|date=2000}}";
        $template = $this->make_citation($text);
        $this->assertSame('2000', $template->year());
    }

    public function testGetYear4(): void {
        $text = "{{cite web|year=2000}}";
        $template = $this->make_citation($text);
        $this->assertSame('2000', $template->year());
    }

    public function testTidyDeadurl1(): void {
        $text = "{{cite web|deadurl=y}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('deadurl');
        $this->assertNull($template->get2('deadurl'));
        $this->assertNull($template->get2('dead-url'));
        $this->assertSame('dead', $template->get2('url-status'));
    }

    public function testTidyDeadurl2(): void {
        $text = "{{cite web|dead-url=alive}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('dead-url');
        $this->assertNull($template->get2('deadurl'));
        $this->assertNull($template->get2('dead-url'));
        $this->assertSame('live', $template->get2('url-status'));
    }

    public function testTidyDeadurl3(): void {
        $text = "{{cite web|dead-url=unfit}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('dead-url');
        $this->assertNull($template->get2('deadurl'));
        $this->assertNull($template->get2('dead-url'));
        $this->assertSame('unfit', $template->get2('url-status'));
    }

    public function testTidyLastAmp1(): void {
        $text = "{{cite web|lastauthoramp=false}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('lastauthoramp');
        $this->assertNull($template->get2('last-author-amp'));
        $this->assertNull($template->get2('lastauthoramp'));
        $this->assertNull($template->get2('name-list-style'));
    }

    public function testTidyLastAmp2(): void {
        $text = "{{cite web|last-author-amp=yes}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('last-author-amp');
        $this->assertNull($template->get2('last-author-amp'));
        $this->assertNull($template->get2('lastauthoramp'));
        $this->assertSame('amp', $template->get2('name-list-style'));
    }

    public function testTidyOIDOI(): void {
        $text = "{{cite web|doi=10.1093/oi/authority.9876543210|url=http://oxfordreference.com/view/10.1093/oi/authority.9876543210}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertNull($template->get2('doi'));
    }

    public function testTidyEPILDOI(): void {
        $text = "{{cite web|url=http://opil.ouplaw.com/view/10.1093/law:epil/9780199231690/law-9780199231690-e1206|doi=10.1093/law:epil/9780199231690/law-9780199231690-e1206}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('doi');
        $this->assertNull($template->get2('doi'));
    }

    public function testTidyPeriodicalQuotes(): void {
        $text = "{{cite web|journal=‘X’}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('journal');
        $this->assertSame("'X'", $template->get2('journal'));
    }

    public function testTidyPublisherLinks(): void {
        $text = "{{cite web|publisher=[[XYZ|X]]|journal=X}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('[[XYZ|X]]', $template->get2('journal'));
        $this->assertNull($template->get2('publisher'));
    }

    public function testTidyPublisherGoogleSupport(): void {
        $text = "{{cite web|publisher=google hive mind|url=support.google.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('Google Inc.', $template->get2('publisher'));
    }

    public function testTidyPublisherGoogleBlog(): void {
        $text = "{{cite web|publisher=google land hive mind|url=http://github.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('google land hive mind', $template->get2('publisher'));
    }

    public function testTidyPublisherAndWebsite(): void {
        $text = "{{cite web|publisher=New York Times|website=www.newyorktimes.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('New York Times', $template->get2('work'));
        $this->assertNull($template->get2('publisher'));
        $this->assertNull($template->get2('website'));
    }

    public function testTidyPublisherAndWorks(): void {
        $text = "{{cite web|publisher=new york times digital archive|journal=new york times communications llc}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('new york times communications llc', $template->get2('journal'));
        $this->assertNull($template->get2('publisher'));
    }

    public function testTidyTimesArchive(): void {
        $text = "{{cite web|publisher=the times digital archive.}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('The Times Digital Archive', $template->get2('publisher'));
    }

    public function testTidyTimesArchiveAndWork_1(): void {
        $text = "{{cite web|publisher=the times digital archive|newspaper=the times}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
    }

    public function testTidyTimesArchiveAndWork_2(): void {
        $text = "{{cite web|publisher=the times digital archive|newspaper=times (london)}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
    }

    public function testTidyTimesArchiveAndWork_3(): void {
        $text = "{{cite web|publisher=the times digital archive|newspaper=times [london]}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
    }

    public function testTidyWPandLegacy(): void {
        $text = "{{cite web|publisher=the washington post – via legacy.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('Legacy.com', $template->get2('via'));
        $this->assertSame('[[The Washington Post]]', $template->get2('publisher'));
    }

    public function testTidyWPandWork_1(): void {
        $text = "{{cite web|publisher=the washington post websites|website=washingtonpost.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('[[The Washington Post]]', $template->get2('website'));
    }

    public function testTidyWPandWork_2(): void {
        $text = "{{cite web|publisher=the washington post websites|website=washington post}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('washington post', $template->get2('website'));
    }

    public function testTidyWPandDepartment(): void {
        $text = "{{cite web|publisher=the washington post|work=entertainment}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('entertainment', $template->get2('department'));
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('the washington post', $template->get2('newspaper'));
    }

    public function testTidySDandDepartment(): void {
        $text = "{{cite web|publisher=san diego union tribune|work=entertainment}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('entertainment', $template->get2('department'));
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('san diego union tribune', $template->get2('work'));
    }

    public function testTidyNYTandWorks1(): void {
        $text = "{{cite web|publisher=the new york times (subscription required)|website=new york times website}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('new york times website', $template->get2('website'));
    }

    public function testTidyNYTandWorks2(): void {
        $text = "{{cite web|publisher=the new york times|website=nytimes.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('[[The New York Times]]', $template->get2('website'));
    }

    public function testTidySJMNandWorks1(): void {
        $text = "{{cite web|publisher=san jose mercury news|website=mercury news}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('mercury news', $template->get2('website'));
    }

    public function testTidySJMNandWorks2(): void {
        $text = "{{cite web|publisher=san jose mercury-news|website=mercurynews.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('[[San Jose Mercury News]]', $template->get2('website'));
    }

    public function testTidySDllc(): void {
        $text = "{{cite web|publisher=the san diego union tribune, LLC}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('The San Diego Union-Tribune', $template->get2('publisher'));
    }

    public function testTidyForbes1(): void {
        $text = "{{cite web|publisher=forbes (forbes Media)}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('Forbes Media', $template->get2('publisher'));
    }

    public function testTidyForbes2(): void {
        $text = "{{cite web|publisher=Forbes, inc.}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('Forbes', $template->get2('publisher'));
    }

    public function testTidyForbes3(): void {
        $text = "{{cite web|publisher=forbes.com llc™}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('Forbes', $template->get2('publisher'));
    }

    public function testTidyForbes4(): void {
        $text = "{{cite web|publisher=forbes publishing company}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('Forbes Publishing', $template->get2('publisher'));
    }

    public function testTidyForbesandWorks1(): void {
        $text = "{{cite web|publisher=forbes publishing|website=forbes website}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('forbes website', $template->get2('website'));
    }

    public function testTidyForbesandWorks2(): void {
        $text = "{{cite web|publisher=forbes.com|website=from the website forbes.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('[[Forbes]]', $template->get2('website'));
    }

    public function testTidyForbesandAgency1(): void {
        $text = "{{cite web|publisher=forbes publishing|website=AFX News}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('Forbes Publishing', $template->get2('publisher'));
        $this->assertSame('AFX News', $template->get2('agency'));
    }

    public function testTidyForbesandAgency2(): void {
        $text = "{{cite web|publisher=forbes.com|website=Thomson Financial News}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('Forbes', $template->get2('publisher'));
        $this->assertSame('Thomson Financial News', $template->get2('agency'));
    }

    public function testTidyLATimes1(): void {
        $text = "{{cite web|publisher=the la times|work=This is a work that stays}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('Los Angeles Times', $template->get2('publisher'));
    }

    public function testTidyLATimes2(): void {
        $text = "{{cite web|publisher=[[the la times]]|work=This is a work that stays}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('[[Los Angeles Times]]', $template->get2('publisher'));
    }

    public function testTidyLAandWorks1(): void {
        $text = "{{cite web|publisher=Los Angeles Times|website=los angeles times and stuff}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('los angeles times and stuff', $template->get2('website'));
    }

    public function testTidyLAandWorks2(): void {
        $text = "{{cite web|publisher=Los Angeles Times|website=latimes.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('[[Los Angeles Times]]', $template->get2('website'));
    }

    public function testLAandVia(): void {
        $text = "{{cite web|publisher=Los Angeles Times|website=laweekly.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('Los Angeles Times', $template->get2('publisher'));
        $this->assertSame('laweekly.com', $template->get2('via'));
    }

    public function testNoPublisherNeeded(): void {
        $text = "{{cite web|publisher=This is Just Random Stuff|website=new york times magazine}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('new york times magazine', $template->get2('website'));
    }

    public function testTidyGaurdian1(): void {
        $text = "{{cite web|publisher=the guardian media group|work=This is a work that stays}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('This is a work that stays', $template->get2('work'));
        $this->assertSame('the guardian media group', $template->get2('publisher'));
    }

    public function testTidyGaurdian2(): void {
        $text = "{{cite web|publisher=the guardian media group|work=theguardian.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('[[The Guardian]]', $template->get2('work'));
    }

    public function testTidyEcon1(): void {
        $text = "{{cite web|publisher=the economist group|work=This is a work that stays}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('This is a work that stays', $template->get2('work'));
        $this->assertSame('the economist group', $template->get2('publisher'));
    }

    public function testTidyEcon2(): void {
        $text = "{{cite web|publisher=the economist group|work=economist.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('[[The Economist]]', $template->get2('work'));
    }

    public function testTidySD1(): void {
        $text = "{{cite web|publisher=the san diego union tribune|work=This is a work that stays}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertSame('This is a work that stays', $template->get2('work'));
        $this->assertSame('the san diego union tribune', $template->get2('publisher'));
    }

    public function testTidySD2(): void {
        $text = "{{cite web|publisher=the san diego union tribune|work=SignOnSanDiego.com}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('[[The San Diego Union-Tribune]]', $template->get2('work'));
    }

    public function testTidyNewsUk(): void {
        $text = "{{cite web|publisher=news UK|work=thetimes.co.uk}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('publisher');
        $this->assertNull($template->get2('publisher'));
        $this->assertSame('[[The Times]]', $template->get2('work'));
    }

    public function testRefComment(): void {
        $text = "{{cite web|ref=harv <!--       -->}}";
        $template = $this->process_citation($text);
        $this->assertSame('<!--       -->', $template->get2('ref'));
    }

    public function testCleanBloomArchives(): void {
        $text = "{{cite web|archive-url=https://web.archive.org/web/434132432/https://www.bloomberg.com/tosv2.html?1}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('archive-url');
        $this->assertNull($template->get2('archive-url'));
    }

    public function testCleanGoogleArchives(): void {
        $text = "{{cite web|archive-url=https://web.archive.org/web/434132432/https://apis.google.com/js/plusone.js}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('archive-url');
        $this->assertNull($template->get2('archive-url'));

        $text = "{{cite web|archive-url=https://web.archive.org/web/434132432/https://www.google-analytics.com/ga.js}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('archive-url');
        $this->assertNull($template->get2('archive-url'));
    }

    public function testCleanebscohost(): void {
        $text = "{{cite web|url=ebscohost.com?AN=1234}}";
        $template = $this->make_citation($text);
        $template->tidy_parameter('url');
        $this->assertSame('http://connection.ebscohost.com/c/articles/1234', $template->get2('url'));
    }

    public function testAuthors1(): void {
        $text = "{{cite web|title=X}}";
        $template = $this->make_citation($text);
        $template->set('author3', '[[Joe|Joes]]'); // Must use set
        $template->tidy_parameter('author3');
        $this->assertSame('[[Joe|Joes]]', $template->get2('author3'));
        $this->assertNull($template->get2('author3-link'));
    }

    public function testMoreEtAl(): void {
        $text = "{{cite web|authors=John, et al.}}";
        $template = $this->make_citation($text);
        $template->handle_et_al();
        $this->assertSame('John', $template->get2('author'));
        $this->assertSame('etal', $template->get2('display-authors'));
    }

    public function testAddingEtAl(): void {
        $text = "{{cite web}}";
        $template = $this->process_citation($text);
        $template->set('authors', 'et al');
        $template->tidy_parameter('authors');
        $this->assertNull($template->get2('authors'));
        $this->assertSame('etal', $template->get2('display-authors'));
        $this->assertNull($template->get2('author'));
    }

    public function testAddingEtAl2(): void {
        $text = "{{cite web}}";
        $template = $this->process_citation($text);
        $template->set('author', 'et al');
        $template->tidy_parameter('author');
        $this->assertNull($template->get2('author'));
        $this->assertNull($template->get2('authors'));
        $this->assertSame('etal', $template->get2('display-authors'));
    }

    public function testCiteTypeWarnings1(): void {
        $text = "{{cite web|journal=X|chapter=|publisher=}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame('cite journal', $template->wikiname());
        $this->assertNull($template->get2('chapter'));
        $this->assertNull($template->get2('publisher'));
    }

    public function testCiteTypeWarnings1b(): void {
        $text = "{{cite web|journal=X|chapter=Y|}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame('cite web', $template->wikiname());
        $this->assertSame('Y', $template->get2('title'));
    }

    public function testCiteTypeWarnings2(): void {
        $text = "{{cite arxiv|eprint=XYZ|bibcode=XXXX}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertNull($template->get2('bibcode'));
    }

    public function testTidyPublisher(): void {
        $text = "{{citation|publisher='''''''''X'''''''''}}";
        $template = $this->make_citation($text);
        $template->final_tidy();
        $this->assertSame('X', $template->get2('work'));
    }

}
