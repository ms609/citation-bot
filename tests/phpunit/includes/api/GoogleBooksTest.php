<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class GoogleBooksTest extends testBaseClass {
    public function testGoogleBookNormalize0(): void {
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
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
        $pg = new TestPage(); unset($pg);    // Fill page name with test name for debugging
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

        $text = "{{Cite web | http://books.google.co.uk/books/about/Wonderful_Life.html}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('cite web', $expanded->wikiname());
        $this->assertNull($expanded->get2('url'));

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
        sleep(1); // Give google a break, since next test often fails
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
        sleep(3); // Give google a break, since this often fails
        $text = "{{cite book|url=https://books.google.com/books?id=yN8DAAAAMBAJ&pg=PA253}}";
        $expanded = $this->process_citation($text);
        $this->assertSame('February 1935', $expanded->get2('date'));
    }
}
