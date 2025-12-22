<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

/**
 * Some of these are unit tests that poke specific functions that do not require actually connecting to adsabs
 */
final class bibcodeTest extends testBaseClass {
    public function testAdsabsApi(): void {
        $this->requires_bibcode(function (): void {
            $bibcodes = [
                '2017NatCo...814879F', // 0
                '1974JPal...48..524M', // 1
                '1996GSAB..108..195R', // 2
                '1966Natur.211..116M', // 3
                '1995Sci...267...77R', // 4
                '1995Geo....23..967E', // 5
                '2003....book.......', // 6 - book - bogus to test year only code
                '2000A&A...361..952H', // 7 - & symbol
                '1995astro.ph..8159B', // 8 - arxiv
                '1932Natur.129Q..18.', // 9 - dot end
                '2019arXiv190502552Q', // 10 - new arxiv
                '2020bisy.book..211G', // 11 - book
            ];
            $text = '{{Cite journal | bibcode = ' . implode('}}{{Cite journal | bibcode = ', $bibcodes) . '}}';
            $page = new TestPage();
            $page->parse_text($text);
            $templates = $page->extract_object('Template');
            $page->expand_templates_from_identifier('bibcode', $templates);
            $this->assertSame('14879', $templates[0]->get2('pages') . $templates[0]->get2('page'));
            $this->assertSame('Journal of Paleontology', $templates[1]->get2('journal'));
            $this->assertSame('Geological Society of America Bulletin', $templates[2]->get2('journal'));
            $this->assertSame('Nature', $templates[3]->get2('journal'));
            $this->assertSame('Science', $templates[4]->get2('journal'));
            $this->assertSame('Geology', $templates[5]->get2('journal'));
            $this->assertNull($templates[6]->get2('journal'));
            $this->assertNull($templates[6]->get2('title'));
            $this->assertSame('2003', $templates[6]->get2('date'));
            $this->assertSame('Astronomy and Astrophysics', $templates[7]->get2('journal'));
            $this->assertNull($templates[8]->get2('pages'));
            $this->assertNull($templates[8]->get2('page'));
            $this->assertNull($templates[8]->get2('class'));
            $this->assertSame('astro-ph/9508159', $templates[8]->get2('arxiv'));
            $this->assertSame('Nature', $templates[9]->get2('journal'));
            $this->assertSame('1905.02552', $templates[10]->get2('arxiv'));
            $this->assertNull($templates[10]->get2('journal'));
            $this->assertNotNull($templates[11]->get2('title'));
        });

        // Mostly just for code coverage, make sure code does not seg fault.
        $text = "fafa3faewf34af";
        $this->assertSame($text, bibcode_link($text));

        // Now verify that lack of requires_bibcode() blocks API in tests
        $bibcodes = [
            '2017NatCo...814879F', // 0
            '1974JPal...48..524M', // 1
            '1996GSAB..108..195R', // 2
            '1966Natur.211..116M', // 3
            '1995Sci...267...77R', // 4
            '1995Geo....23..967E', // 5
            '2003hoe..book.....K', // 6 - book
            '2000A&A...361..952H', // 7 - & symbol
            '1995astro.ph..8159B', // 8 - arxiv
            '1932Natur.129Q..18.', // 9 - dot end
            '2019arXiv190502552Q', // 10 - new arxiv
            '2020bisy.book..211G', // 11 - book
        ];
        $text = '{{Cite journal | bibcode = ' . implode('}}{{Cite journal | bibcode = ', $bibcodes) . '}}';
        $page = new TestPage();
        $page->parse_text($text);
        $this->assertSame($text, $page->parsed_text());
    }

    public function testBibcodeData1(): void {
        $text = "{{Cite book | bibcode = 2017NatCo...814879F}}";
        $template = $this->make_citation($text);
        $results = (object) [
            'bibcode' => '2017NatCo...814879F',
            'author' => [
                0 => 'Fredin, Ola',
                1 => 'Viola, Giulio',
                2 => 'Zwingmann, Horst',
                3 => 'Sørlie, Ronald',
                4 => 'Brönner, Marco',
                5 => 'Lie, Jan-Erik',
                6 => 'Grandal, Else Margrethe',
                7 => 'Müller, Axel',
                8 => 'Margreth, Annina',
                9 => 'Vogt, Christoph',
                10 => 'Knies, Jochen'
            ],
            'doctype' => 'article',
            'doi' => [0 => '10.1038/ncomms14879'],
            'identifier' => [0 => '2017NatCo...814879F', 1 => '10.1038/ncomms14879'],
            'page' => [0 => '14879'],
            'pub' => 'Nature Communications',
            'pubdate' => '2017-04-00',
            'title' => [0 => 'The inheritance of a Mesozoic landscape in western Scandinavia'],
            'volume' => '8',
            'year' => '2017',
        ];
        process_bibcode_data($template, $results);
        $this->assertSame('Nature Communications', $template->get2('journal'));
        $this->assertSame('10.1038/ncomms14879',  $template->get2('doi'));
    }

    public function testBibcodeData2(): void {
        $text = "{{Cite book | bibcode = 1996GSAB..108..195R}}";
        $template = $this->make_citation($text);
        $results = (object) [
            'bibcode' => '1996GSAB..108..195R',
            'author' =>
            [
                0 => 'Retallack, Gregory J.',
                1 => 'Veevers, John J.',
                2 => 'Morante, Ric',
            ],
            'doctype' => 'article',
            'doi' => [0 => '10.1130/0016-7606(1996)108<0195:GCGBPT>2.3.CO;2'],
            'identifier' =>
            [
                0 => '10.1130/0016-7606(1996)108<0195:GCGBPT>2.3.CO;2',
                1 => '1996GSAB..108..195R',
            ],
            'issue' => '2',
            'page' => [0 => '195DUMMY'],
            'pub' => 'Geological Society of America Bulletin',
            'pubdate' => '1996-02-00',
            'title' => [0 => 'Global coal gap between Permian-Triassic extinction and Middle Triassic recovery of peat-forming plants'],
            'volume' => '108',
            'year' => '1996',
        ];
        process_bibcode_data($template, $results);
        $this->assertSame('Geological Society of America Bulletin', $template->get2('journal'));
        $this->assertSame('10.1130/0016-7606(1996)108<0195:GCGBPT>2.3.CO;2', $template->get2('doi'));
        $this->assertNull($template->get2('page'));
        $this->assertNull($template->get2('pages')); // Added letters
    }

    public function testBibcodeData3(): void {
        $text = "{{Cite book | bibcode = 2000A&A...361..952H}}";
        $template = $this->make_citation($text);
        $results = (object) [
            'bibcode' => '2000A&A...361..952H',
            'author' =>
            [
                0 => 'Hessman, F. V.',
                1 => 'Gänsicke, B. T.',
                2 => 'Mattei, J. A.',
            ],
            'doctype' => 'article',
            'identifier' => [0 => '2000A&A...361..952H'],
            'page' => [0 => '952'],
            'pub' => 'Astronomy and Astrophysics',
            'pubdate' => '2000-09-00',
            'title' => [0 => 'The history and source of mass-transfer variations in AM Herculis'],
            'volume' => '361',
            'year' => '2000',
         ];
         process_bibcode_data($template, $results);
         $this->assertSame('Astronomy and Astrophysics', $template->get2('journal'));
         $this->assertNull($template->get2('doi'));
         $this->assertSame('952', $template->get2('page') . $template->get2('pages'));
    }

    public function testBibcodeData4(): void {
        $text = "{{Cite book | bibcode = 1995Sci...267...77R}}";
        $template = $this->make_citation($text);
        $results = (object) [
            'bibcode' => '1995Sci...267...77R',
            'author' => [0 => 'Retallack, G. J.'],
            'doctype' => 'article',
            'doi' => [0 => '10.1126/science.267.5194.77'],
            'identifier' => [
                0 => '1995Sci...267...77R',
                1 => '10.1126/science.267.5194.77',
            ],
            'issue' => '5194',
            'page' => [0 => '77'],
            'pub' => 'Science',
            'pubdate' => '1995-01-00',
            'title' => [0 => 'Permain-Triassic Life Crisis on Land'],
            'volume' => '267',
            'year' => '1995',
        ];
        process_bibcode_data($template, $results);
        $this->assertSame('Science', $template->get2('journal'));
        $this->assertSame('10.1126/science.267.5194.77',  $template->get2('doi'));
    }

    public function testBibcodeData5(): void {
        $text = "{{Cite book | bibcode = 1995Geo....23..967E}}";
        $template = $this->make_citation($text);
        $results = (object) [
            'bibcode' => '1995Geo....23..967E',
            'author' =>
            [
                0 => 'Eshet, Yoram',
                1 => 'Rampino, Michael R.',
                2 => 'Visscher, Henk',
            ],
            'doctype' => 'article',
            'doi' => [0 => '10.1130/0091-7613(1995)023<0967:FEAPRO>2.3.CO;2'],
            'identifier' =>
            [
                0 => '1995Geo....23..967E',
                1 => '10.1130/0091-7613(1995)023<0967:FEAPRO>2.3.CO;2',
            ],
            'issue' => '11',
            'page' => [0 => '967'],
            'pub' => 'Geology',
            'pubdate' => '1995-11-00',
            'title' => [0 => 'Fungal event and palynological record of ecological crisis and recovery across the Permian-Triassic boundary'],
            'volume' => '23',
            'year' => '1995',
        ];
        process_bibcode_data($template, $results);
        $this->assertSame('Geology', $template->get2('journal'));
        $this->assertSame('10.1130/0091-7613(1995)023<0967:FEAPRO>2.3.CO;2',  $template->get2('doi'));
    }

    public function testBibcodeData6(): void {
        $text = "{{Cite book | bibcode = 1974JPal...48..524M}}";
        $template = $this->make_citation($text);
        $results = (object) [
            'bibcode' => '1974JPal...48..524M',
            'author' => [0 => 'Moorman, M.'],
            'doctype' => 'article',
            'identifier' => [0 => '1974JPal...48..524M'],
            'page' => [0 => '524'],
            'pub' => 'Journal of Paleontology',
            'pubdate' => '1974-05-00',
            'title' => [0 => 'Microbiota of the late Proterozoic Hector Formation, Southwestern Alberta, Canada'],
            'volume' => '48',
            'year' => '1974',
        ];
        process_bibcode_data($template, $results);
        $this->assertSame('Journal of Paleontology', $template->get2('journal'));
        $this->assertSame('1974',  $template->get2('date'));
    }

    public function testBibcodeData7(): void {
        $text = "{{Cite book | bibcode = 1966Natur.211..116M}}";
        $template = $this->make_citation($text);
        $results = (object) [
            'bibcode' => '1966Natur.211..116M',
            'author' => [0 => 'Melville, R.'],
            'doctype' => 'article',
            'doi' => [0 => '10.1038/211116a0'],
            'identifier' =>
            [
                0 => '1966Natur.211..116M',
                1 => '10.1038/211116a0',
            ],
            'issue' => '5045',
            'page' => [0 => '116'],
            'pub' => 'Nature',
            'pubdate' => '1966-07-00',
            'title' => [0 => 'Continental Drift, Mesozoic Continents and the Migrations of the Angiosperms'],
            'volume' => '211',
            'year' => '1966',
        ];
        process_bibcode_data($template, $results);
        $this->assertSame('Nature', $template->get2('journal'));
        $this->assertSame('10.1038/211116a0',  $template->get2('doi'));
    }

    public function testBibcodeData8(): void {
        $text = "{{Cite book | bibcode = 1995astro.ph..8159B}}";
        $template = $this->make_citation($text);
        $results = (object) [
            'bibcode' => '1995astro.ph..8159B',
            'arxiv_class' =>
            [
                0 => 'astro-ph',
                1 => 'hep-ph',
            ],
            'author' => [0 => 'Brandenberger, Robert H.'],
            'doctype' => 'eprint',
            'identifier' =>
            [
                0 => 'arXiv:astro-ph/9508159',
                1 => '1995astro.ph..8159B',
            ],
            'page' => [0 => 'astro-ph/9508159'],
            'pub' => 'arXiv e-prints',
            'pubdate' => '1995-09-00',
            'title' => [0 => 'Formation of Structure in the Universe'],
            'year' => '1995',
        ];
        process_bibcode_data($template, $results);
        $this->assertSame('1995', $template->get2('date'));
        $this->assertSame('astro-ph/9508159',  $template->get2('arxiv') . $template->get2('eprint'));
    }

    public function testBibcodeData9(): void {
        $text = "{{Cite book | bibcode = 1932Natur.129Q..18.}}";
        $template = $this->make_citation($text);
        $results = (object) [
        'bibcode' => '1932Natur.129Q..18.',
        'doctype' => 'article',
        'doi' => [0 => '10.1038/129018a0'],
        'identifier' =>
        [
            0 => '1932Natur.129Q..18.',
            1 => '10.1038/129018a0',
        ],
        'issue' => '3244',
        'page' => [0 => '18'],
        'pub' => 'Nature',
        'pubdate' => '1932-01-00',
        'title' => [0 => 'Electric Equipment of the Dolomites Railway.'],
        'volume' => '129',
        'year' => '1932',
        ];
        process_bibcode_data($template, $results);
        $this->assertSame('Nature', $template->get2('journal'));
        $this->assertSame('10.1038/129018a0',  $template->get2('doi'));
    }

    public function testBibcodeData10(): void {
        $text = "{{Cite book | bibcode = 2019arXiv190502552Q}}";
        $template = $this->make_citation($text);
        $results = (object) [
            'bibcode' => '2019arXiv190502552Q',
            'arxiv_class' => [0 => 'q-bio.QM'],
            'author' =>
            [
                0 => 'Qin, Yang',
                1 => 'Freebairn, Louise',
                2 => 'Atkinson, Jo-An',
                3 => 'Qian, Weicheng',
                4 => 'Safarishahrbijari, Anahita',
                5 => 'Osgood, Nathaniel D',
            ],
            'doctype' => 'eprint',
            'identifier' =>
            [
                0 => '2019arXiv190502552Q',
                1 => 'arXiv:1905.02552',
            ],
            'page' => [0 => 'arXiv:1905.02552'],
            'pub' => 'arXiv e-prints',
            'pubdate' => '2019-05-00',
            'title' => [0 => 'Multi-Scale Simulation Modeling for Prevention and Public Health Management of Diabetes in Pregnancy and Sequelae'],
            'year' => '2019',
        ];
        process_bibcode_data($template, $results);
        $this->assertSame('2019', $template->get2('date'));
        $this->assertSame('1905.02552',  $template->get2('arxiv') . $template->get2('eprint'));
    }

    public function testBibcodeData11(): void {
        $text = "{{Cite book | bibcode = 2003....book.......}}";
        $template = $this->make_citation($text);
        $results = (object) [];
        expand_book_adsabs($template, $results);
        $this->assertNull($template->get2('date'));
        $this->assertNull($template->get2('year'));
    }

    public function testBibcodeData12(): void {
        $text = "{{Cite book | bibcode = 1958ses..book.....S}}";
        $template = $this->make_citation($text);
        $results = (object) [
            'numFound' => 1,
            'start' => 0,
            'docs' =>
            [
                0 =>
                (object) [
                    'bibcode' => '1958ses..book.....S',
                    'author' => [0 => 'Schwarzschild, Martin'],
                    'doctype' => 'book',
                    'identifier' => [0 => '1958ses..book.....S'],
                    'pub' => 'Princeton',
                    'pubdate' => '1958-00-00',
                    'title' => [0 => 'Structure and evolution of the stars.'],
                    'year' => '1958',
                ],
            ],
        ];
        expand_book_adsabs($template, $results->docs[0]);
        $this->assertSame('1958', $template->get2('date'));
        $this->assertSame('structure and evolution of the stars', mb_strtolower($template->get2('title')));
    }

    public function testBibCodeCache(): void {
        AdsAbsControl::add_doi_map('2014ApPhA.116..403G', '10.1007/s00339-014-8468-2');

        $text = "{{cite journal| bibcode=2014ApPhA.116..403G}}";
        $prepared = $this->process_citation($text);
        $this->assertSame('10.1007/s00339-014-8468-2', $prepared->get2('doi'));

        $text = "{{cite journal| bibcode=2014ApPhA.116..403G}}";
        $prepared = $this->make_citation($text);
        expand_by_adsabs($prepared);
        $this->assertSame('10.1007/s00339-014-8468-2', $prepared->get2('doi'));

        $text = "{{cite journal| doi=10.1007/s00339-014-8468-2}}";
        $prepared = $this->process_citation($text);
        $this->assertSame('2014ApPhA.116..403G', $prepared->get2('bibcode'));

        $text = "{{cite journal| doi=10.1007/s00339-014-8468-2}}";
        $prepared = $this->make_citation($text);
        expand_by_adsabs($prepared);
        $this->assertSame('2014ApPhA.116..403G', $prepared->get2('bibcode'));
    }

    public function testBibCodeCache2(): void {
        AdsAbsControl::add_doi_map('2000AAS...19713707B', 'X');

        $text = "{{cite journal| bibcode=2000AAS...19713707B}}";
        $prepared = $this->process_citation($text);
        $this->assertSame($text, $prepared->parsed_text());

        $text = "{{cite journal| bibcode=2000AAS...19713707B}}";
        $prepared = $this->make_citation($text);
        expand_by_adsabs($prepared);
        $this->assertSame($text, $prepared->parsed_text());

        $text = "{{cite journal| doi=X}}";
        $prepared = $this->process_citation($text);
        $this->assertNull($prepared->get2('bibcode'));

        $text = "{{cite journal| doi=X}}";
        $prepared = $this->make_citation($text);
        expand_by_adsabs($prepared);
        $this->assertNull($prepared->get2('bibcode'));
    }

    public function testDontDoIt(): void { // "complete" already
        $text = '{{cite journal|title=X|journal=X|issue=X|volume=X|pages=12-34|year=1980|last2=Him|doi=10.0000/Rubbish_bot_failure_test|bibcode=X|last1=X|first1=X}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->incomplete());
        $text = '{{cite journal|title=X|periodical=X|issue=X|volume=X|pages=12-34|year=1980|last2=Him|doi=10.0000/Rubbish_bot_failure_test|bibcode=X|last1=X|first1=X}}';
        $template = $this->make_citation($text);
        $this->assertFalse($template->incomplete());

        $text = '{{citation     |title=X|work=X             |issue=X|volume=X|pages=12-34|year=1980|last2=Him|doi=10.0000/Rubbish_bot_failure_test|bibcode=X|last1=X|first1=X}}';
        $template = $this->make_citation($text);
        $this->assertTrue($template->incomplete());

        $this->requires_bibcode(function (): void {
            $text = '{{cite journal|title=X|journal=X|issue=X|volume=X|pages=12-34|year=1980|last2=Him|doi=10.0000/Rubbish_bot_failure_test|bibcode=X|last1=X|first1=X}}';
            $template = $this->make_citation($text);
            $template_array = [$template];
            $bibcode_array = [$template->get('bibcode')];
            query_bibcode_api($bibcode_array, $template_array);
            $this->assertSame('X', $template->get2('bibcode'));
        });
    }

    public function testBibcodeRemap(): void {
        $this->requires_bibcode(function (): void {
            $text='{{cite journal|bibcode=2018MNRAS.tmp.2192I}}';
            $expanded = $this->process_citation($text);
            $this->assertSame('2018MNRAS.481..703I', $expanded->get2('bibcode'));
        });
    }

    public function testBibcodeDotEnding(): void {
        $this->requires_bibcode(function (): void {
            $text='{{cite journal|title=Electric Equipment of the Dolomites Railway|journal=Nature|date=2 January 1932|volume=129|issue=3244|page=18|doi=10.1038/129018a0}}';
            $expanded = $this->process_citation($text);
            $this->assertSame('1932Natur.129Q..18.', $expanded->get2('bibcode'));
        });
    }

    public function testBibcodesBooks(): void {
        $this->requires_bibcode(function (): void {
            $text = "{{Cite book|bibcode=1982mcts.book.....H}}";
            $expanded = $this->process_citation($text);
            $this->assertSame('1982', $expanded->get2('date'));
            $this->assertSame('Houk', $expanded->get2('last1'));
            $this->assertSame('N.', $expanded->get2('first1'));
            $this->assertNotNull($expanded->get2('title'));
        });
        $text = "{{Cite book|bibcode=1982mcts.book.....H}}";    // Verify requires_bibcode() works
        $expanded = $this->process_citation($text);
        $this->assertNull($expanded->get2('title'));
        $this->assertNull($expanded->get2('year'));
        $this->assertNull($expanded->get2('date'));
    }

    public function testBibcodesFindBooks(): void {
        $this->requires_bibcode(function (): void {
            $text = "{{cite book|title=Enhancement of Electrochemical Activity in Bioelectrochemical Systems by Using Bacterial Anodes: An Overview|year=2020|last1=Gandu|first1=Bharath|last2=Rozenfeld|first2=Shmuel|last3=Ouaknin Hirsch|first3=Lea|last4=Schechter|first4=Alex|last5=Cahan|first5=Rivka|bibcode= }}";
            $expanded = $this->process_citation($text);
            $this->assertSame('2020bisy.book..211G', $expanded->get2('bibcode'));
        });
    }

    public function testBadBibcodeARXIVPages(): void {
        $this->requires_bibcode(function (): void {
            $text = "{{cite journal|bibcode=1995astro.ph..8159B|pages=8159}}"; // Pages from bibcode have slash in it astro-ph/8159B
            $expanded = $this->process_citation($text);
            $pages = (string) $expanded->get2('pages');
            $this->assertFalse(mb_stripos($pages, 'astro'));
            $this->assertNull($expanded->get2('journal'));  // if we get a journal, the data is updated and test probably no longer gets bad data
        });
    }

    public function testNoBibcodesForArxiv(): void {
        $this->requires_bibcode(function (): void {
            $text = "{{Cite arXiv|last1=Sussillo|first1=David|last2=Abbott|first2=L. F.|date=2014-12-19|title=Random Walk Initialization for Training Very Deep Feedforward Networks|eprint=1412.6558 |class=cs.NE}}";
            $expanded = $this->process_citation($text);
            $this->assertNull($expanded->get2('bibcode'));  // If this eventually gets a journal, we will have to change the test
        });
    }

    public function testNoBibcodesForBookReview(): void {
        $this->requires_bibcode(function (): void {      // don't add isbn. It causes early exit
            $text = "{{cite book |title=Churchill's Bomb: How the United States Overtook Britain in the First Nuclear Arms Race |publisher=X|location=X|lccn=X|oclc=X}}";
            $expanded = $this->make_citation($text);
            expand_by_adsabs($expanded); // Won't expand because of bookish stuff
            $this->assertNull($expanded->get2('bibcode'));
        });
    }

    public function testFindBibcodeNoTitle(): void {
        $this->requires_bibcode(function (): void {
            $text = "{{Cite journal | last1 = Glaesemann | first1 = K. R. | last2 = Gordon | first2 = M. S. | last3 = Nakano | first3 = H. | journal = Physical Chemistry Chemical Physics | volume = 1 | issue = 6 | pages = 967–975| year = 1999 |issn = 1463-9076}}";
            $expanded = $this->make_citation($text);
            expand_by_adsabs($expanded);
            $this->assertSame('1999PCCP....1..967G', $expanded->get2('bibcode'));
        });
    }

    public function testFindBibcodeForBook(): void {
        $this->requires_bibcode(function (): void {
            $text = "{{Cite journal | doi=10.2277/0521815363}}";
            $expanded = $this->make_citation($text);
            expand_by_adsabs($expanded);
            $this->assertSame('2003hoe..book.....K', $expanded->get2('bibcode'));
        });
    }

}
