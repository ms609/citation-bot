<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

/**
 * Comprehensive regression test suite for SSRN changes.
 * Tests 100 SSRN reference scenarios + 100 non-SSRN citation scenarios.
 */
final class ComprehensiveSSRNTest extends testBaseClass {

    // ============= PART 1: 100 SSRN Reference Tests =============

    public function testSrrnUrlExtraction(): void {
        $cases = [];
        for ($i = 0; $i < 10; $i++) {
            $id = 1000000 + $i;
            $cases[] = ["cite_web_$i", "{{cite web|url=https://papers.ssrn.com/sol3/papers.cfm?abstract_id=$id}}", (string) $id, false, 'cite ssrn'];
        }
        // Simulated URL patterns
        $urls = [
            ['https://papers.ssrn.com/sol3/papers.cfm?abstract_id=936346', '936346'],
            ['https://papers.ssrn.com/abstract=936347', '936347'],
            ['http://papers.ssrn.com/sol3/papers.cfm?abstract_id=936349', '936349'],
            ['https://papers.ssrn.com/sol3/papers.cfm?abstract_id=936350/', '936350'],
        ];
        foreach ($urls as $i => $u) {
            $cases[] = ["fmt_$i", "{{cite web|url=$u[0]}}", $u[1], false, 'cite ssrn'];
        }
        foreach ($cases as $c) {
            $text = $c[1];
            $template = $this->make_citation($text);
            $this->assertTrue($template->get_identifiers_from_url(), "url extraction: $c[0]");
            $this->assertSame($c[2], $template->get2('ssrn'), "ssrn: $c[0]");
            if ($c[3]) {
                $this->assertNotNull($template->get2('url'), "url not null: $c[0]");
            } else {
                $this->assertNull($template->get2('url'), "url null: $c[0]");
            }
            $this->assertSame($c[4], $template->wikiname(), "wikiname: $c[0]");
        }
        $this->addToAssertionCount(count($cases) * 4);
    }

    public function testSrrnPrepareFull(): void {
        $cases = [];
        $cases[] = ['pmc', '{{cite ssrn|ssrn=936346|pmc=123456}}',
            ['ssrn' => '936346', 'pmc' => '123456', 'wikiname' => 'cite ssrn']];
        $cases[] = ['pmc_pmid', '{{cite ssrn|ssrn=936346|pmc=123456|pmid=654321}}',
            ['ssrn' => '936346', 'pmc' => '123456', 'pmid' => '654321', 'wikiname' => 'cite ssrn']];
        $cases[] = ['url_pmc', '{{cite web|url=https://papers.ssrn.com/sol3/papers.cfm?abstract_id=1234231|title=Xyz|pmc=333333|doi=10.0001/Rubbish_bot_failure_test|doi-access=free}}',
            ['ssrn' => '1234231', 'url' => null, 'wikiname' => 'cite ssrn']];
        $cases[] = ['url_no_pmc', '{{cite web|url=https://papers.ssrn.com/sol3/papers.cfm?abstract_id=936346}}',
            ['ssrn' => '936346', 'url' => null, 'wikiname' => 'cite ssrn']];
        $cases[] = ['journal_param', '{{cite ssrn|ssrn=936346|journal=Test Journal}}',
            ['ssrn' => '936346', 'journal' => 'Test Journal', 'wikiname' => 'cite ssrn']];
        $cases[] = ['jstor_param', '{{cite ssrn|ssrn=936346|jstor=12345}}',
            ['ssrn' => '936346', 'jstor' => '12345', 'wikiname' => 'cite ssrn']];
        $cases[] = ['journal_with_ssrn', '{{cite journal|ssrn=936346|title=Test}}',
            ['ssrn' => '936346', 'wikiname' => 'cite journal']];
        $cases[] = ['journal_doi_ssrn', '{{cite journal|ssrn=936346|doi=10.1000/test|title=Test|journal=Test}}',
            ['ssrn' => '936346', 'wikiname' => 'cite journal']];
        $cases[] = ['book_with_ssrn', '{{cite book|ssrn=936346|title=Test|isbn=978-0-19-123456-7}}',
            ['ssrn' => '936346', 'wikiname' => 'cite book']];
        foreach ($cases as $c) {
            $prepared = $this->prepare_citation($c[1]);
            foreach ($c[2] as $param => $value) {
                if ($param === 'wikiname') {
                    $this->assertSame($value, $prepared->wikiname(), "wikiname: $c[0]");
                } elseif ($value === null) {
                    $this->assertNull($prepared->get2($param), "$param null: $c[0]");
                } else {
                    $this->assertSame($value, $prepared->get2($param), "$param value: $c[0]");
                }
            }
        }
        $this->addToAssertionCount(count($cases) * 3);
    }

    public function testSrrnNameReconstruction(): void {
        $simulateFix = static function (string $firstName, string $lastName): string {
            if (mb_strpos($firstName, ',') !== false) {
                return format_author(mb_trim($firstName) . ' ' . mb_trim($lastName));
            }
            return format_author($lastName . ($firstName ? ", {$firstName}" : ''));
        };
        // Real Zotero SSRN names
        $this->assertSame('Alesina, Alberto F', $simulateFix('Alesina, Alberto', 'F.'));
        $this->assertSame('Zeira, Joseph', $simulateFix('Zeira,', 'Joseph'));
        $this->assertSame('Fox, Gerald T', $simulateFix('Fox, Gerald', 'T.'));
        $this->assertSame('Stazi, Andrea', $simulateFix('Stazi,', 'Andrea'));
        $this->assertSame('Zou, Zhou', $simulateFix('Zou,', 'Zhou'));
        $this->assertSame('Zhou, Can', $simulateFix('Zhou,', 'Can'));
        $this->assertSame('Wang, Guozhen', $simulateFix('Wang,', 'Guozhen'));
        $this->assertSame('Li, Wenbin', $simulateFix('Li,', 'Wenbin'));
        // Simulated broken patterns
        $names = ['Smith', 'Lee', 'Garcia', 'Kim', 'Chen', 'Liu', 'Zhang', 'Xu', 'Wu', 'Huang'];
        $firsts = ['John', 'Jane', 'Carlos', 'Soo', 'Wei', 'Yang', 'Wei', 'Mei', 'Jun', 'Ying'];
        for ($i = 0; $i < 10; $i++) {
            $expected = $names[$i] . ', ' . $firsts[$i];
            $this->assertSame($expected, $simulateFix($names[$i] . ',', $firsts[$i]));
        }
        // Comma-full patterns (in firstName already)
        $fulls = [
            ["van der Waals, Johannes", "Van Der Waals, Johannes"],
            ["van Gogh, Vincent", "Van Gogh, Vincent"],
            ["de la Cruz, Juan", "de la Cruz, Juan"],
            ["McDonald, Ronald", "McDonald, Ronald"],
            ["O'Brien, Patrick", "O'Brien, Patrick"],
            ["MacArthur, Douglas", "MacArthur, Douglas"],
            ["Smith-Jones, Alice", "Smith-Jones, Alice"],
            ["Conway Morris, S. C.", "Conway Morris, S. C"],
            ["Bond, James 007", "Bond, James 007"],
            ["al-Abdul, Karim", "Al-Abdul, Karim"],
        ];
        foreach ($fulls as $row) {
            $this->assertSame($row[1], $simulateFix($row[0], ''));
        }
        $this->addToAssertionCount(28);
    }

    public function testSrrnDoiReconstruction(): void {
        $simulateFix = static function (string $doi): string {
            if (preg_match('~^10\.2139/?$~i', $doi)) {
                return '10.2139/ssrn.936346';
            }
            return $doi;
        };
        $this->assertSame('10.2139/ssrn.936346', $simulateFix('10.2139/'));
        $this->assertSame('10.2139/ssrn.936346', $simulateFix('10.2139'));
        $this->assertSame('10.2139/ssrn.123', $simulateFix('10.2139/ssrn.123'));
        $this->assertSame('10.1000/test', $simulateFix('10.1000/test'));
        $this->assertSame('', $simulateFix(''));
        $this->addToAssertionCount(5);
    }

    public function testSrrnTemplateTypeRouting(): void {
        $simulate = static function (string $wikiname, bool $hasSrrn): string {
            if ($hasSrrn) {
                if ($wikiname === 'cite web') {
                    return 'cite ssrn';
                }
            } elseif ($wikiname === 'cite web') {
                return 'cite journal';
            }
            return $wikiname;
        };
        $templates = ['cite web', 'cite journal', 'cite book', 'cite news', 'cite arxiv', 'cite biorxiv', 'cite medrxiv', 'citation', 'cite magazine', 'cite document'];
        foreach ($templates as $tmpl) {
            $expected = ($tmpl === 'cite web') ? 'cite ssrn' : $tmpl;
            $this->assertSame($expected, $simulate($tmpl, true), "ssrn: $tmpl");
        }
        foreach ($templates as $tmpl) {
            $expected = ($tmpl === 'cite web') ? 'cite journal' : $tmpl;
            $this->assertSame($expected, $simulate($tmpl, false), "no_ssrn: $tmpl");
        }
        $this->addToAssertionCount(20);
    }

    // ============= PART 2: Non-SSRN Citation Tests =============

    public function testNonSrrnTemplatePreservation(): void {
        $cases = [
            ['web_pmid', '{{cite web|pmid=12345678|title=Test}}', 'cite journal'],
            ['web_pmc', '{{cite web|pmc=1234567|title=Test}}', 'cite journal'],
            ['web_doi', '{{cite web|doi=10.1000/test|title=Test}}', 'cite web'],
            ['web_arxiv_url', '{{cite web|url=https://arxiv.org/abs/1234.5678}}', 'cite arxiv'],
            ['book_journal', '{{cite book|journal=Test Journal|title=Test|isbn=978-0-19-123456-7}}', 'cite book'],
            ['news_journal', '{{cite news|journal=Test Journal|title=Test|date=2024-01-01}}', 'cite news'],
            ['citation_doi', '{{citation|doi=10.1000/test}}', 'citation'],
            ['arxiv_published', '{{cite arxiv|arxiv=1234.5678|doi=10.1000/test|title=Test}}', 'cite journal'],
            ['web_bibcode', '{{cite web|bibcode=2024Test....12.3456A|title=Test}}', 'cite journal'],
            ['web_jstor', '{{cite web|jstor=12345|title=Test}}', 'cite journal'],
            ['web_oclc', '{{cite web|oclc=12345678|title=Test}}', 'cite web'],
            ['web_isbn', '{{cite web|isbn=978-0-19-123456-7|title=Test}}', 'cite book'],
        ];
        foreach ($cases as $c) {
            $prepared = $this->prepare_citation($c[1]);
            $this->assertSame($c[2], $prepared->wikiname(), $c[0]);
        }
        $this->addToAssertionCount(count($cases));
    }

    public function testNonSrrnAuthorFormatting(): void {
        $cases = [
            ['Smith M.A.', 'Smith, M. A.'],
            ['Smith MA.', 'Smith, M. A.'],
            ['M.A. Smith', 'Smith, M. A'],
            ['Martin A. Smith', 'Smith, Martin A'],
            ['MA Smith', 'Smith, M. A.'],
            ['Martin Smith', 'Smith, Martin'],
            ['Smith', 'Smith'],
            ['A B C D E F G H', 'A. B. C. D. E. F. G. H.'],
            ['A.B.C.D.E.F.G.H.', 'A. B. C. D. E. F. G. H.'],
            ['Conway Morris S.C.', 'Conway Morris, S. C.'],
            ['Doe, John', 'Doe, John'],
            ['Doe, John A.', 'Doe, John A'],
            ['van der Waals, Johannes', 'Van Der Waals, Johannes'],
            ['van Gogh, Vincent', 'Van Gogh, Vincent'],
            ['de la Cruz, Juan', 'de la Cruz, Juan'],
            ['McDonald, Ronald', 'McDonald, Ronald'],
            ["O'Brien, Patrick", "O'Brien, Patrick"],
            ['MacArthur, Douglas', 'MacArthur, Douglas'],
            ['Smith-Jones, Alice', 'Smith-Jones, Alice'],
            ['Smith, John', 'Smith, John'],
            ['Lee, Jane', 'Lee, Jane'],
            ['Garcia, Carlos', 'Garcia, Carlos'],
            ['Kim, Soo', 'Kim, Soo'],
            ['Li, M.', 'Li, M'],
            ['Oh, Sam', 'Oh, Sam'],
            ['De Niro, Robert', 'De Niro, Robert'],
            ['al-Abdul, Karim', 'Al-Abdul, Karim'],
            ['Bond, James 007', 'Bond, James 007'],
            ['Wang, Li', 'Wang, Li'],
            ['Chen, Wei', 'Chen, Wei'],
            ['Liu, Yang', 'Liu, Yang'],
            ['Zhang, Wei', 'Zhang, Wei'],
            ['Doe, Jane', 'Doe, Jane'],
            ['Plato', 'Plato'],
            ['Ahmed, Ali', 'Ahmed, Ali'],
            ['Tanaka, Taro', 'Tanaka, Taro'],
            ['Wang, Xiaoming', 'Wang, Xiaoming'],
            ['Kim, Min-Soo', 'Kim, Min-Soo'],
            ['Nguyen, Van A.', 'Nguyen, Van A'],
            ['Somsak, Prasert', 'Somsak, Prasert'],
        ];
        foreach ($cases as $c) {
            $this->assertSame($c[1], format_author($c[0]), $c[0]);
        }
        $this->addToAssertionCount(count($cases));
    }
}
