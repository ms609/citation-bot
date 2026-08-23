<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class UrlToolsCoverageMoreTest extends testBaseClass {

    public function testSimplifyGoogleSearchPreservesNonDefaultParameters(): void {
        $url = 'https://www.google.com/search?resnum=12&gl=us&rllag=1&lsig=abc'
            . '&lpsid=def&as_q=cows&kponly=1&q=test';

        $this->assertSame($url, simplify_google_search($url));
    }

    public function testCleanExistingUrlNormalizesEssOpenArchive(): void {
        $template = $this->cleanUrl('https://www.essopenarchive.org/users/123/articles/456/files/test.pdf');

        $this->assertSame('https://essopenarchive.org/users/123/articles/456', $template->get2('url'));
    }

    public function testCleanExistingUrlDecodesPrivacyRedirect(): void {
        $url = 'https://myprivacy.dpgmedia.nl/consent?callbackUrl='
            . 'https%253A%252F%252Fwww.example.com%252Fprivacygate%253FredirectUri%253D%252Farticle';
        $template = $this->cleanUrl($url);

        $this->assertSame('https://www.example.com/article', $template->get2('url'));
    }

    public function testCleanExistingUrlDecodesOupGovernorPage(): void {
        $url = 'https://academic.oup.com/crawlprevention/governor?content='
            . '%2Fgji%2Farticle%2F230%2F1%2F50%2F6522179%3Flogin%3Dfalse';
        $template = $this->cleanUrl($url, [
            'title' => 'Validate User',
            'website' => 'academic.oup.com',
        ]);

        $this->assertSame(
            'https://academic.oup.com/gji/article/230/1/50/6522179',
            $template->get2('url')
        );
        $this->assertSame('', $template->get2('title'));
        $this->assertNull($template->get2('website'));
    }

    public function testCleanExistingUrlRemovesPublisherProxiesAndLibraryVia(): void {
        $cases = [
            'https://ieeexplore.ieee.org.example.proxy.edu/document/12345'
                => 'https://ieeexplore.ieee.org/document/12345',
            'https://www.oxfordartonline.com.example.proxy.edu/view/article'
                => 'https://www.oxfordartonline.com/view/article',
            'https://login.proxy.example.edu/login?url=https://example.com/article'
                => 'https://example.com/article',
            'https://login.proxy.example.edu/login?url=https%3A%2F%2Fexample.com%2Farticle'
                => 'https://example.com/article',
        ];

        foreach ($cases as $url => $expected) {
            $template = $this->cleanUrl($url, ['via' => 'University Library']);
            $this->assertSame($expected, $template->get2('url'));
            $this->assertNull($template->get2('via'));
        }
    }

    public function testCleanExistingUrlRemovesWikipediaLibraryWrapper(): void {
        $template = $this->cleanUrl(
            'https://wikipedialibrary.idm.oclc.org/login?auth=production&url=https://example.com/article'
        );

        $this->assertSame('https://example.com/article', $template->get2('url'));
    }

    public function testCleanExistingUrlNormalizesAncestryWrappers(): void {
        $view = $this->cleanUrl('https://www.ancestry.com/discoveryui-content/view/123:456?foo=bar');
        $join = $this->cleanUrl(
            'https://www.ancestry.com/cs/offers/join?url='
            . 'http%3A%2F%2Fexample.com%2Fsearch%3Fq%3DJohn%20Doe'
        );
        $return = $this->cleanUrl(
            'https://www.ancestry.com/account/create?returnurl='
            . 'http%3A%2F%2Fexample.com%2Fsearch%3Fq%3DJohn%20Doe'
        );

        $this->assertSame('https://www.ancestry.com/discoveryui-content/view/123:456', $view->get2('url'));
        $this->assertSame('http://example.com/search?q=John+Doe', $join->get2('url'));
        $this->assertSame('http://example.com/search?q=John+Doe', $return->get2('url'));
    }

    public function testCleanExistingUrlNormalizesNewspaperArchiveHosts(): void {
        $https = $this->cleanUrl('https://access.newspaperarchive.com/article');
        $http = $this->cleanUrl('http://access.newspaperarchive.com/article');

        $this->assertSame('https://www.newspaperarchive.com/article', $https->get2('url'));
        $this->assertSame('https://www.newspaperarchive.com/article', $http->get2('url'));
    }

    public function testCleanExistingUrlRemovesEveryGaleProxyForm(): void {
        $cases = [
            'https://go.galegroup.com%2Fps%2Fi.do%3Fid%3DGALE'
                => 'https://go.galegroup.com/ps/i.do?id=GALE',
            'http://gateway.example/login?url=https://go.galegroup.com/ps/i.do?id=GALE'
                => 'https://go.galegroup.com/ps/i.do?id=GALE',
            'https://link.galegroup.com%2Fps%2Fi.do%3Fid%3DGALE'
                => 'https://link.galegroup.com/ps/i.do?id=GALE',
            'http://gateway.example/login?url=https://link.galegroup.com/ps/i.do?id=GALE'
                => 'https://link.galegroup.com/ps/i.do?id=GALE',
        ];

        foreach ($cases as $url => $expected) {
            foreach (['University Library', 'Other source'] as $via) {
                $template = $this->cleanUrl($url, ['via' => $via]);
                $this->assertSame($expected, $template->get2('url'));
                $this->assertNull($template->get2('via'));
            }
        }
    }

    public function testCleanExistingUrlNormalizesProQuestVariants(): void {
        $schoolProxy = $this->cleanUrl(
            'https://www.proquest-com.example.scoolaid.net/docview/12345',
            ['via' => 'University Library']
        );
        $libraryProxy = $this->cleanUrl(
            'http://gateway.example/login?url=https://www.proquest.com/docview/12345',
            ['via' => 'University Library']
        );
        $otherProxy = $this->cleanUrl(
            'http://gateway.example/login?url=https://www.proquest.com/docview/12345',
            ['via' => 'Other source']
        );
        $trailingSlash = $this->cleanUrl('https://www.proquest.com/docview/12345/');
        $trailingQuestion = $this->cleanUrl('https://www.proquest.com/docview/12345?');

        foreach ([$schoolProxy, $libraryProxy, $otherProxy, $trailingSlash, $trailingQuestion] as $template) {
            $this->assertSame('https://www.proquest.com/docview/12345', $template->get2('url'));
        }
        $this->assertNull($schoolProxy->get2('via'));
        $this->assertNull($libraryProxy->get2('via'));
        $this->assertNull($otherProxy->get2('via'));
    }

    public function testCleanExistingUrlCorrectsCinemaExpressWork(): void {
        $template = $this->cleanUrl(
            'https://www.cinemaexpress.com/story',
            ['website' => 'The New Indian Express']
        );

        $this->assertSame('[[Cinema Express]]', $template->get2('website'));
    }

    /**
     * @param string $url
     * @param array<string, string> $parameters
     */
    private function cleanUrl(string $url, array $parameters = []): Template {
        $template = $this->make_citation('{{cite web|url=' . $url . '}}');
        foreach ($parameters as $name => $value) {
            $template->set($name, $value);
        }
        clean_existing_urls_INSIDE($template, 'url');
        return $template;
    }
}
