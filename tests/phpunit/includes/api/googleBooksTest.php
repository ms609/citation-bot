<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class googleBooksTest extends testBaseClass {
    public function testGoogleBookNormalize0(): void {
        new TestPage(); // Fill page name with test name for debugging
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
        new TestPage(); // Fill page name with test name for debugging
        $removed_redundant = 0;
        $removed_parts = '';
        $gid = [];
        $gid[1] = 'm8W2AgAAQBAJ';
        $url_in  = 'https://books.google.com/books?id=m8W2AgAAQBAJ#PR333,M1';
        $url_out = 'https://books.google.com/books?id=m8W2AgAAQBAJ&pg=PR333';
        normalize_google_books($url_in, $removed_redundant, $removed_parts, $gid); // Reference passed
        $this->assertSame($url_out, $url_in);
    }

    public function testGoogleBooksExpansion(): void {
        $text = "{{Cite web | http://books.google.co.uk/books/about/Wonderful_Life.html?id=SjpSkzjIzfsC&redir_esc=y}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('cite book', $expanded->wikiname());
        $this->assertSame('https://books.google.com/books?id=SjpSkzjIzfsC', $expanded->get2('url'));
        $this->assertSame('Wonderful Life: The Burgess Shale and the Nature of History', $expanded->get2('title'));
        $this->assertSame('978-0-393-30700-9', $expanded->get2('isbn')    );
        $this->assertSame('Gould', $expanded->get2('last1'));
        $this->assertSame('Stephen Jay', $expanded->get2('first1') );
        $this->assertSame('1989', $expanded->get2('date'));
        $this->assertNull($expanded->get2('pages')); // Do not expand pages.  Google might give total pages to us
    }

    public function testGoogleBooksExpansionA1(): void {
        $text = "{{Cite web | http://books.google.co.uk/books/about/Wonderful_Life.html}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('cite web', $expanded->wikiname());
        $this->assertNull($expanded->get2('url'));
    }

    public function testGoogleBooksExpansionA2(): void {
        $text = "{{Cite web | http://books.google.com/books?id&#61;SjpSkzjIzfsC&redir_esc&#61;y}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('cite book', $expanded->wikiname());
        $this->assertSame('https://books.google.com/books?id=SjpSkzjIzfsC', $expanded->get2('url'));
    }

    public function testGoogleBooksExpansion2(): void {
        $text = "{{Cite web | url=https://books.google.com/books?id=SjpSkzjIzfsC&printsec=frontcover#v=onepage}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('https://books.google.com/books?id=SjpSkzjIzfsC', $expanded->get2('url'));
    }

    public function testGoogleBooksExpansion3(): void {
        $text = "{{Cite web | url=https://books.google.com/books?id=SjpSkzjIzfsC&dq=HUH}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('https://books.google.com/books?id=SjpSkzjIzfsC&q=HUH', $expanded->get2('url'));
    }

    public function testGoogleBooksExpansion4(): void {
        $text = "{{Cite web | url=https://books.google.com/books?id=SjpSkzjIzfsC&q=HUH}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('https://books.google.com/books?id=SjpSkzjIzfsC&q=HUH', $expanded->get2('url'));
    }

    public function testGoogleBooksExpansion5(): void {
        $text = "{{Cite web | url=https://books.google.com/books?id=SjpSkzjIzfsC&dq=HUH&pg=213}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('https://books.google.com/books?id=SjpSkzjIzfsC&dq=HUH&pg=213', $expanded->get2('url'));
    }

    public function testGoogleBooksExpansion6(): void {
        $text = "{{Cite web | url=https://books.google.com/books?id=SjpSkzjIzfsC#&dq=HUH&pg=213}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('https://books.google.com/books?id=SjpSkzjIzfsC&q=%3DHUH', $expanded->get2('url'));
    }

    public function testGoogleBooksExpansion7(): void {
        $text = "{{Cite web | url=https://books.google.com/books?id=Sw4EAAAAMBAJ&pg=PT12&dq=%22The+Dennis+James+Carnival%22#v=onepage}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('https://books.google.com/books?id=Sw4EAAAAMBAJ&dq=%22The+Dennis+James+Carnival%22&pg=PT12', $expanded->get2('url'));
    }

    public function testGoogleBooksExpansion8(): void {
        $text = "{{Cite web | url=https://books.google.com/books?id=w8KztFy6QYwC&dq=%22philip+loeb+had+been+blacklisted%22+%22Goldbergs,+The+(Situation+Comedy)%22&pg=PA545#pgview=full}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('https://books.google.com/books?id=w8KztFy6QYwC&dq=%22philip+loeb+had+been+blacklisted%22+%22Goldbergs,+The+(Situation+Comedy)%22&pg=PA545', $expanded->get2('url'));
    }

    public function testGoogleBooksExpansionNEW(): void {
        sleep(run_type_mods(-1, 3, 2, 1, 2)); // Give google a break, since next test often fails
        $text = "{{Cite web | url=https://www.google.com/books/edition/_/SjpSkzjIzfsC?hl=en}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('cite book', $expanded->wikiname());
        $this->assertSame('https://books.google.com/books?id=SjpSkzjIzfsC', $expanded->get2('url'));
        $this->assertSame('Wonderful Life: The Burgess Shale and the Nature of History', $expanded->get2('title'));
        $this->assertSame('978-0-393-30700-9', $expanded->get2('isbn')    );
        $this->assertSame('Gould', $expanded->get2('last1'));
        $this->assertSame('Stephen Jay', $expanded->get2('first1') );
        $this->assertSame('1989', $expanded->get2('date'));
        $this->assertNull($expanded->get2('pages')); // Do not expand pages.  Google might give total pages to us
    }

    public function testGoogleDates(): void {
        sleep(run_type_mods(-1, 4, 3, 3, 3)); // Give google a break, since this often fails
        $text = "{{cite book|url=https://books.google.com/books?id=yN8DAAAAMBAJ&pg=PA253}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('February 1935', $expanded->get2('date'));
    }

    public function testGoogleBooksCleanup1(): void {
        $text = "{{cite books|url=https://books.google.com/booksid=12345}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345', $expanded->get2('url'));
    }

    public function testGoogleBooksCleanup2(): void {
        $text = "{{cite books|url=https://books.google.com/books?vid=12345}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?vid=12345', $expanded->get2('url'));
    }

    public function testGoogleBooksCleanup3(): void {
        $text = "{{cite books|url=https://books.google.com/books?qid=12345}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345', $expanded->get2('url'));
    }

    public function testGoogleBooksCleanup4(): void {
        $text = "{{cite books|url=https://books.google.com/?id=12345}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345', $expanded->get2('url'));
    }

    public function testGoogleBooksCleanup5(): void {
        $text = "{{cite books|url=https://books.google.uk.co/books?isbn=12345}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?isbn=12345', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup1(): void {
        $text = "{{cite books|url=https://books.google.com/books?id=12345&q=xyz#q=abc}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345&q=abc', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup2(): void {
        $text = "{{cite books|url=https://books.google.com/books?id=12345&dq=xyz#q=abc}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345&q=abc', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup3(): void {
        $text = "{{cite books|url=https://books.google.com/books?id=12345&dq=abc#q=abc}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345&q=abc', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup4(): void {
        $text = "{{cite books|url=https://books.google.com/books?id=12345#q=abc}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345&q=abc', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup5(): void {
        $text = "{{cite books|url=https://books.google.com/books?id=12345&vq=abc}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345&q=abc', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup6(): void {
        $text = "{{cite books|url=https://books.google.com/books?id=12345&vq=abc&pg=3214&q=xyz}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345&q=abc&pg=3214', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup7(): void {
        $text = "{{cite books|url=https://books.google.com/books?id=12345&lpg=1234}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345&pg=1234', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup8a(): void {
        $text = "{{cite books|url=https://books.google.com/books?id=12345&q=isbn}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345&q=isbn', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup8b(): void {
        $text = "{{cite books|url=https://books.google.com/books?id=12345&q=isbn1234}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup8c(): void {
        $text = "{{cite books|url=https://books.google.com/books?id=12345&q=inauthor:34123123}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup9a(): void {
        $text = "{{cite books|url=https://books.google.com/books?id=12345&dq=isbn}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345&q=isbn', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup9b(): void {
        $text = "{{cite books|url=https://books.google.com/books?id=12345&dq=isbn1234}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup9c(): void {
        $text = "{{cite books|url=https://books.google.com/books?id=12345&dq=inauthor:34123123}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup10(): void {
        $text = "{{cite books|url=https://books.google.com/books?id=12345&d=doggiesandcats&pg=3241&lpg=321&article_id=3241&sitesec=reviews}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345&sitesec=reviews', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup11a(): void {
        $text = "{{cite books|url=https://books.google.com/books?id=12345&article_id=3241}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345&article_id=3241', $expanded->get2('url'));
    }

    public function testGoogleBooksHashCleanup11b(): void {
        $text = "{{cite books|url=https://books.google.com/books?id=12345&article_id=3241&q=huh}}";
        $expanded = $this->process_citation($text);
        $this->AssertSame('https://books.google.com/books?id=12345&q=huh&article_id=3241#v=onepage', $expanded->get2('url'));
    }

}
