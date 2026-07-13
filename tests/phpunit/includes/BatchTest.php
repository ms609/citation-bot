<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class BatchTest extends testBaseClass {

    public function testCategoryA_BareUrls(): void {
        $ids = [1032105, 1111624, 1381502, 1437163, 1699972, 1736283, 1795122, 1926431, 1972779, 1995109,
            2031979, 214569, 2160199, 2212100, 2296881, 2358110, 2456211, 2477899, 2484239, 2504067,
            2517967, 263049, 2641802, 2665730, 2685104, 2686095, 2767464, 2804554, 2818659, 2822729,
            2874598, 2917253, 2952292, 3044448, 3060080, 3173990, 3177484, 3270867, 3286175, 3383734,
            3467646, 3536663, 3556410, 3561618, 3665046, 3760833, 3814087, 3832015, 390109, 4188297,
            4419750, 4424363, 4647581, 4655594, 4704652, 470983, 4742235, 4776172, 4966326, 4971754,
        ];
        foreach ($ids as $id) {
            $text = "{{cite web|url=https://papers.ssrn.com/sol3/papers.cfm?abstract_id=$id}}";
            $template = $this->make_citation($text);
            $this->assertTrue($template->get_identifiers_from_url(), "get_identifiers_from_url: $id");
            $this->assertSame((string)$id, $template->get2('ssrn'), "ssrn: $id");
            $this->assertNull($template->get2('url'), "url not null: $id");
            $this->assertSame('cite ssrn', $template->wikiname(), "wikiname: $id");
        }
        $this->addToAssertionCount(count($ids) * 4);
    }

    public function testCategoryB_CiteSrrn(): void {
        $ids = [4989658, 5009171, 5014939, 506242, 5122872, 664506, 700297, 958933, 976277, 980361,
            1032105, 1111624, 1381502, 1437163, 1699972, 1736283, 1795122, 1926431, 1972779, 1995109,
        ];
        foreach ($ids as $id) {
            $text = "{{cite ssrn|ssrn=$id}}";
            $prepared = $this->prepare_citation($text);
            $this->assertSame((string)$id, $prepared->get2('ssrn'), "ssrn: $id");
            $this->assertSame('cite ssrn', $prepared->wikiname(), "wikiname: $id");
        }
        $this->addToAssertionCount(count($ids) * 2);
    }

    public function testCategoryC_Random(): void {
        $cases = [];
        $cases[] = ['j+ssrn', '{{cite journal|ssrn=936346|title=Test|journal=TJ}}', ['ssrn' => '936346', 'wikiname' => 'cite journal']];
        $cases[] = ['j+ssrn+pmc', '{{cite journal|ssrn=936346|pmc=123456|title=Test|journal=TJ}}', ['ssrn' => '936346', 'pmc' => '123456', 'wikiname' => 'cite journal']];
        $cases[] = ['j+ssrn+doi', '{{cite journal|ssrn=936346|doi=10.1000/test|title=Test|journal=TJ}}', ['ssrn' => '936346', 'wikiname' => 'cite journal']];
        $cases[] = ['j+ssrn+pmid', '{{cite journal|ssrn=936346|pmid=12345678|title=Test|journal=TJ}}', ['ssrn' => '936346', 'pmid' => '12345678', 'wikiname' => 'cite journal']];
        $cases[] = ['j+ssrn+jstor', '{{cite journal|ssrn=936346|jstor=12345|title=Test|journal=TJ}}', ['ssrn' => '936346', 'jstor' => '12345', 'wikiname' => 'cite journal']];
        $cases[] = ['j+ssrn+issn', '{{cite journal|ssrn=936346|issn=1234-5678|title=Test|journal=TJ}}', ['ssrn' => '936346', 'issn' => '1234-5678', 'wikiname' => 'cite journal']];
        $cases[] = ['j+ssrn+bibcode', '{{cite journal|ssrn=936346|bibcode=2024Tst....12.3456A|title=Test|journal=TJ}}', ['ssrn' => '936346', 'wikiname' => 'cite journal']];
        $cases[] = ['j+ssrn+vol', '{{cite journal|ssrn=936346|volume=123|issue=45|pages=1-10|title=Test|journal=TJ}}', ['ssrn' => '936346', 'wikiname' => 'cite journal']];
        $cases[] = ['j+ssrn+oclc', '{{cite journal|ssrn=936346|oclc=12345678|title=Test|journal=TJ}}', ['ssrn' => '936346', 'wikiname' => 'cite journal']];
        $cases[] = ['j+ssrn+doi+pmc', '{{cite journal|ssrn=936346|doi=10.1000/test|pmc=123456|title=Test|journal=TJ}}', ['ssrn' => '936346', 'pmc' => '123456', 'wikiname' => 'cite journal']];
        $cases[] = ['w+ssrn url', '{{cite web|url=https://papers.ssrn.com/abstract=936346}}', ['ssrn' => '936346', 'url' => null, 'wikiname' => 'cite ssrn']];
        $cases[] = ['w+ssrn url+pmc', '{{cite web|url=https://papers.ssrn.com/abstract=936347|pmc=123456}}', ['ssrn' => '936347', 'pmc' => '123456', 'url' => null, 'wikiname' => 'cite ssrn']];
        $cases[] = ['w+ssrn param', '{{cite web|ssrn=936346|title=Test}}', ['ssrn' => '936346', 'wikiname' => 'cite web']];
        $cases[] = ['w+ssrn+doi', '{{cite web|doi=10.1000/test|ssrn=936346|title=Test}}', ['ssrn' => '936346', 'wikiname' => 'cite web']];
        $cases[] = ['w+ssrn+pmc', '{{cite web|pmc=1234567|ssrn=936346|title=Test}}', ['ssrn' => '936346', 'wikiname' => 'cite journal']];
        $cases[] = ['w+ssrn url+doi', '{{cite web|url=https://papers.ssrn.com/abstract=936348|doi=10.1000/test}}', ['ssrn' => '936348', 'url' => null, 'wikiname' => 'cite ssrn']];
        $cases[] = ['w+www url', '{{cite web|url=https://www.papers.ssrn.com/sol3/papers.cfm?abstract_id=936357}}', ['ssrn' => '936357', 'url' => null, 'wikiname' => 'cite ssrn']];
        $cases[] = ['w+ssrn+pmid', '{{cite web|pmid=12345678|ssrn=936346|title=Test}}', ['ssrn' => '936346', 'wikiname' => 'cite journal']];
        $cases[] = ['j+ssrn url+pmc', '{{cite journal|url=https://papers.ssrn.com/abstract=936352|pmc=123456}}', ['ssrn' => '936352', 'pmc' => '123456', 'url' => null, 'wikiname' => 'cite journal']];
        $cases[] = ['j+ssrn url+pmid', '{{cite journal|url=https://papers.ssrn.com/abstract=936353|pmid=12345678}}', ['ssrn' => '936353', 'pmid' => '12345678', 'url' => null, 'wikiname' => 'cite journal']];
        $cases[] = ['j+ssrn url', '{{cite journal|url=https://papers.ssrn.com/abstract=936354|title=Test|journal=TJ}}', ['ssrn' => '936354', 'url' => null, 'wikiname' => 'cite journal']];
        $cases[] = ['n+ssrn url', '{{cite news|url=https://papers.ssrn.com/abstract=936355}}', ['wikiname' => 'cite news']];
        $cases[] = ['b+ssrn url', '{{cite book|url=https://papers.ssrn.com/abstract=936356|title=T|isbn=978-0-19-1}}', ['ssrn' => '936356', 'url' => null, 'wikiname' => 'cite book']];
        $cases[] = ['w+ssrn url+jstor', '{{cite web|url=https://papers.ssrn.com/abstract=936359|jstor=12345}}', ['ssrn' => '936359', 'jstor' => '12345', 'url' => null, 'wikiname' => 'cite ssrn']];
        $cases[] = ['w+isbn+ssrn', '{{cite web|isbn=978-0-19-1|ssrn=936360|title=Test}}', ['ssrn' => '936360', 'wikiname' => 'cite book']];
        $cases[] = ['w+doi', '{{cite web|doi=10.1000/test|title=Test}}', ['wikiname' => 'cite web']];
        $cases[] = ['w+pmid', '{{cite web|pmid=12345678|title=Test}}', ['wikiname' => 'cite journal']];
        $cases[] = ['w+pmc', '{{cite web|pmc=1234567|title=Test}}', ['wikiname' => 'cite journal']];
        $cases[] = ['w+arxiv url', '{{cite web|url=https://arxiv.org/abs/1234.5678}}', ['arxiv' => '1234.5678', 'wikiname' => 'cite arxiv']];
        $cases[] = ['w+osti', '{{cite web|url=https://www.osti.gov/biblio/2341}}', ['osti' => '2341', 'wikiname' => 'cite web']];
        $cases[] = ['w+jstor', '{{cite web|url=https://www.jstor.org/stable/12345}}', ['jstor' => '12345', 'wikiname' => 'cite journal']];
        $cases[] = ['w+mr', '{{cite web|url=https://mathscinet.ams.org/mathscinet-getitem?mr=0012343}}', ['mr' => '0012343', 'wikiname' => 'cite web']];
        $cases[] = ['w+zbl', '{{cite web|url=https://zbmath.org/?q=an:1234.56789}}', ['zbl' => '1234.56789', 'wikiname' => 'cite web']];
        $cases[] = ['w+lccn', '{{cite web|url=https://lccn.loc.gov/12345678}}', ['lccn' => '12345678', 'wikiname' => 'cite book']];
        $cases[] = ['w+ol', '{{cite web|url=https://openlibrary.org/books/OL12345M}}', ['ol' => '12345M', 'wikiname' => 'cite book']];
        $cases[] = ['w+oclc', '{{cite web|url=http://worldcat.org/title/stuff/oclc/1234}}', ['oclc' => '1234', 'wikiname' => 'cite web']];
        $cases[] = ['w+citeseerx', '{{cite web|url=https://citeseerx.ist.psu.edu/viewdoc/summary?doi=10.1.1.923.345}}', ['citeseerx' => '10.1.1.923.345', 'wikiname' => 'cite web']];
        $cases[] = ['w+isbn', '{{cite web|isbn=978-0-19-1|title=Test}}', ['wikiname' => 'cite book']];
        $cases[] = ['b+isbn', '{{cite book|isbn=978-0-19-1|title=Test}}', ['wikiname' => 'cite book']];
        $cases[] = ['b+doi', '{{cite book|doi=10.1000/test|title=Test}}', ['wikiname' => 'cite book']];
        $cases[] = ['b+oclc', '{{cite book|oclc=12345678|title=Test}}', ['wikiname' => 'cite book']];
        $cases[] = ['b+lccn', '{{cite book|lccn=12345678|title=Test}}', ['wikiname' => 'cite book']];
        $cases[] = ['b+ol', '{{cite book|ol=OL12345M|title=Test}}', ['wikiname' => 'cite book']];
        $cases[] = ['b+chapter', '{{cite book|chapter=TC|title=T|isbn=978-0-19-1}}', ['wikiname' => 'cite book']];
        $cases[] = ['n+doi', '{{cite news|doi=10.1000/test|title=Test}}', ['wikiname' => 'cite news']];
        $cases[] = ['n+pmid', '{{cite news|pmid=12345678|title=Test}}', ['wikiname' => 'cite news']];
        $cases[] = ['n+pmc', '{{cite news|pmc=1234567|title=Test}}', ['wikiname' => 'cite news']];
        $cases[] = ['j+doi', '{{cite journal|doi=10.1000/test|title=Test|journal=TJ}}', ['wikiname' => 'cite journal']];
        $cases[] = ['j+pmid', '{{cite journal|pmid=12345678|title=Test|journal=TJ}}', ['wikiname' => 'cite journal']];
        $cases[] = ['j+pmc', '{{cite journal|pmc=1234567|title=Test|journal=TJ}}', ['wikiname' => 'cite journal']];
        $cases[] = ['j+jstor', '{{cite journal|jstor=12345|title=Test|journal=TJ}}', ['wikiname' => 'cite journal']];
        $cases[] = ['j+bibcode', '{{cite journal|bibcode=2024Tst....12.3456A|title=Test|journal=TJ}}', ['wikiname' => 'cite journal']];
        $cases[] = ['j+issn', '{{cite journal|issn=1234-5678|title=Test|journal=TJ}}', ['wikiname' => 'cite journal']];
        $cases[] = ['j+vol', '{{cite journal|volume=123|issue=45|pages=1-10|doi=10.1000/test|title=Test|journal=TJ}}', ['wikiname' => 'cite journal']];
        $cases[] = ['arxiv+doi', '{{cite arxiv|arxiv=1234.5678|doi=10.1000/test|title=Test}}', ['arxiv' => '1234.5678', 'wikiname' => 'cite journal']];
        $cases[] = ['arxiv', '{{cite arxiv|arxiv=1234.5678|title=Test}}', ['arxiv' => '1234.5678', 'wikiname' => 'cite arxiv']];
        $cases[] = ['biorxiv+doi', '{{cite biorxiv|biorxiv=10.1101/123456|doi=10.1000/test|title=Test}}', ['biorxiv' => '10.1101/123456', 'wikiname' => 'cite biorxiv']];
        $cases[] = ['medrxiv+doi', '{{cite medrxiv|medrxiv=10.1101/123456|doi=10.1000/test|title=Test}}', ['medrxiv' => '10.1101/123456', 'wikiname' => 'cite medrxiv']];
        $cases[] = ['c+doi', '{{citation|doi=10.1000/test}}', ['wikiname' => 'citation']];
        $cases[] = ['c+pmid', '{{citation|pmid=12345678}}', ['wikiname' => 'citation']];
        $cases[] = ['c+pmc', '{{citation|pmc=1234567}}', ['wikiname' => 'citation']];
        $cases[] = ['c+isbn', '{{citation|isbn=978-0-19-1}}', ['wikiname' => 'citation']];
        $cases[] = ['c+ssrn', '{{citation|ssrn=936346|title=Test}}', ['ssrn' => '936346', 'wikiname' => 'citation']];
        $cases[] = ['w+wikilink', '{{cite web|url=http://example.com/|title=Test}}', ['wikiname' => 'cite web']];
        $cases[] = ['j+wikilink', '{{cite journal|title=Test|journal=Testing J}}', ['wikiname' => 'cite journal']];
        $cases[] = ['w+ssrn url+oclc', '{{cite web|url=https://papers.ssrn.com/abstract=936349|oclc=12345678}}', ['ssrn' => '936349', 'url' => null, 'wikiname' => 'cite ssrn']];
        $cases[] = ['w+ssrn url+lccn', '{{cite web|url=https://papers.ssrn.com/abstract=936350|lccn=12345678}}', ['ssrn' => '936350', 'url' => null, 'wikiname' => 'cite ssrn']];
        foreach ($cases as $c) {
            $prepared = $this->prepare_citation($c[1]);
            foreach ($c[2] as $param => $expected) {
                $actual = ($param === 'wikiname') ? $prepared->wikiname() : $prepared->get2($param);
                if ($expected === '_NOT_NULL_') {
                    $this->assertNotNull($actual, "$param not null: $c[0]");
                } elseif ($expected === '_NULL_') {
                    $this->assertNull($actual, "$param null: $c[0]");
                } else {
                    $this->assertSame($expected, $actual, "$param: $c[0]");
                }
            }
        }
        $this->addToAssertionCount(count($cases) * 2);
    }

    public function testLiveZotero(): void {
        $this->requires_zotero(function () {
            $ids = ['936346', '1000038', '4345678', '4861626',
                '1032105', '1111624', '1381502', '1437163', '1699972', '1736283',
            ];
            $tested = 0;
            $found = 0;
            $commaFound = 0;
            $incompleteDoi = 0;
            foreach ($ids as $ssrnId) {
                $tested++;
                $url = 'https://papers.ssrn.com/sol3/papers.cfm?abstract_id=' . $ssrnId;
                $apiUrl = 'https://en.wikipedia.org/api/rest_v1/data/citation/zotero/' . urlencode($url);
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $apiUrl, CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => 'CitationBotTest/1.0',
                    CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_HTTPHEADER => ['Accept: application/json'],
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode !== 200 || empty($response)) {
                    if ($httpCode === 429) {
                        echo "\n  Rate limited on $ssrnId, sleeping 60s...";
                        sleep(60);
                    }
                    continue;
                }
                $found++;
                $data = json_decode($response);
                if (!$data || !isset($data[0])) {
                    continue;
                }
                $item = $data[0];

                if (isset($item->title)) {
                    echo "\n  SSRN $ssrnId: itemType=$item->itemType, title=" . mb_substr($item->title, 0, 60);
                }

                if (isset($item->creators)) {
                    $count = count($item->creators);
                    echo ", creators=$count";
                    foreach ($item->creators as $c) {
                        if (isset($c->firstName) && mb_strpos($c->firstName, ',') !== false) {
                            $commaFound++;
                            $fullName = mb_trim($c->firstName) . ' ' . mb_trim($c->lastName ?? '');
                            $formatted = format_author($fullName);
                            echo ", name='$c->firstName $c->lastName' → '$formatted'";
                            break;
                        }
                    }
                }

                if (isset($item->DOI)) {
                    echo ", DOI='$item->DOI'";
                    if ($item->DOI === '10.2139/') {
                        $incompleteDoi++;
                    }
                }

                echo "\n  Sleeping 60s between Zotero calls to avoid rate limiting...";
                sleep(60);
            }
            echo "\n\nZotero results: $found/$tested found, $commaFound with comma in firstName, $incompleteDoi with incomplete DOI\n";
            $this->addToAssertionCount(1);
        });
    }
}
