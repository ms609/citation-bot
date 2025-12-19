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
}
