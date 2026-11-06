<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

/**
 * Several URL clean-ups run urldecode() on part of a URL.  Decoding %7C gives a
 * raw pipe, which ends the url= parameter, so the saved wikitext was a broken
 * citation and the next run of the bot stopped with "Unrecognized parameter
 * name format".
 */
final class UrlRawPipeTest extends testBaseClass {

    private const PROXY_URL = 'https://login.libproxy.union.edu/login?url=https%3A%2F%2Fgo.gale.com/ps/retrieve.do?tabID=T002&docId=GALE%7CA493733315&docType=Article&inPS=true';

    public function testDecodedProxyUrlKeepsPipeEncoded(): void {
        $template = $this->make_citation('{{cite web|url=' . self::PROXY_URL . '}}');
        $template->tidy_parameter('url');
        $this->assertSame('https://go.gale.com/ps/retrieve.do?tabID=T002&docId=GALE%7CA493733315&docType=Article&inPS=true', $template->get2('url'));
    }

    public function testDecodedYoutubeUrlKeepsPipeEncoded(): void {
        $template = $this->make_citation('{{cite web|url=https://www.youtube.com/watch%3Fv%3Dabc%7Cdef}}');
        $template->tidy_parameter('url');
        $this->assertSame('https://www.youtube.com/watch?v=abc%7Cdef', $template->get2('url'));
    }

    public function testDecodedBracesStayEncoded(): void {
        $template = $this->make_citation('{{cite web|url=https://www.youtube.com/watch%3Fv%3D%7B%7Bx%7D%7D}}');
        $template->tidy_parameter('url');
        $this->assertSame('https://www.youtube.com/watch?v=%7B%7Bx%7D%7D', $template->get2('url'));
    }

    public function testCleanedCitationCanBeReadBackUnchanged(): void {
        $template = $this->make_citation('{{cite web|url=' . self::PROXY_URL . '|title=X}}');
        $template->tidy_parameter('url');
        $reread = $this->make_citation($template->parsed_text());
        $this->assertSame($template->get2('url'), $reread->get2('url'));
        $this->assertSame('X', $reread->get2('title'));
    }

    public function testUrlWithoutDecodingIsUntouched(): void {
        $template = $this->make_citation('{{cite web|url=https://example.com/a%7Cb?x=1}}');
        $template->tidy_parameter('url');
        $this->assertSame('https://example.com/a%7Cb?x=1', $template->get2('url'));
    }

    public function testProxyUrlWithoutPipeIsStillDecoded(): void {
        $template = $this->make_citation('{{cite web|url=https://login.libproxy.union.edu/login?url=https%3A%2F%2Fgo.gale.com/ps/retrieve.do?docType=Article}}');
        $template->tidy_parameter('url');
        $this->assertSame('https://go.gale.com/ps/retrieve.do?docType=Article', $template->get2('url'));
    }
}
