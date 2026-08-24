<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;

final class MiscToolsTest extends testBaseClass {

    #[DoesNotPerformAssertions]
    public function testcheck_memory_usage(): void {
        new TestPage(); // Fill page name with test name for debugging
        check_memory_usage('testcheck_memory_usage');
    }

    #[DoesNotPerformAssertions]
    public function testThrottle(): void { // Just runs over the code and basically does nothing
        $do_it = run_type_mods(-1, 25, 25, 1, 1);
        for ($x = 0; $x <= $do_it; $x++) {
            throttle();
        }
    }

    public function testCovertUrl2Chapter1(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testCovertUrl2Chapter2(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/page/0}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testCovertUrl2Chapter3(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/page/1}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testCovertUrl2Chapter4(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/page}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNotNull($expanded->get2('url'));
    }

    public function testCovertUrl2Chapter5(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/page/232}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNotNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNull($expanded->get2('url'));
    }

    public function testCovertUrl2Chapter6(): void {
        $text = '{{Cite web|title=X|chapter=Y|url=http://archive.org/chapter/}}';
        $expanded = $this->make_citation($text);
        $expanded->change_name_to('cite book');
        $this->assertNotNull($expanded->get2('chapter-url'));
        $this->assertNull($expanded->get2('chapterurl'));
        $this->assertNull($expanded->get2('url'));
    }

    public function testCiteODNB1(): void {
        $text = '{{Cite ODNB|url=https://www.oxforddnb.com/view/10.1093/ref:odnb/9780198614128.001.0001/odnb-9780198614128-e-74876;jsession=XYZ|doi=10.1093/ref:odnb/wrong_stuff|id=74876}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/ref:odnb/wrong_stuff', $template->get2('doi'));
        $this->assertSame('74876', $template->get2('id'));
        $this->assertSame('https://www.oxforddnb.com/view/10.1093/ref:odnb/9780198614128.001.0001/odnb-9780198614128-e-74876', $template->get2('url'));
    }

    public function testCiteODNB2(): void {
        $text = '{{Cite ODNB|url=https://www.oxforddnb.com/view/10.1093/ref:odnb/9780198614128.001.0001/odnb-9780198614128-e-74876|doi=10.1093/odnb/74876|id=74876}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/ref:odnb/74876', $template->get2('doi'));
        $this->assertSame('74876', $template->get2('id'));
    }

    public function testCiteODNB3(): void {
        $text = '{{Cite ODNB|url=https://www.oxforddnb.com/view/10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-107316|doi=10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-107316}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/odnb/9780198614128.013.107316', $template->get2('doi'));
    }

    public function testCiteODNB4(): void {
        $text = '{{Cite ODNB|url=https://www.oxforddnb.com/view/10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-107316|id=107316}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/odnb/9780198614128.013.107316', $template->get2('doi'));
        $this->assertNull($template->get2('id'));
    }

    public function testCiteODNB5(): void {
        $text = '{{Cite ODNB|url=https://www.oxforddnb.com/view/10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-107316|id=107316|doi=10.0001/Rubbish_bot_failure_test}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/odnb/9780198614128.013.107316', $template->get2('doi'));
        $this->assertNull($template->get2('id'));
    }

    public function testCiteODNB6(): void {
        $text = '{{Cite ODNB|id=107316|doi=10.1093/odnb/9780198614128.013.107316}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1093/odnb/9780198614128.013.107316', $template->get2('doi'));
        $this->assertNull($template->get2('id'));
    }

    public function testCiteODNB7(): void { // Prefer given doi over ID, This is a contrived test
        $text = '{{Cite ODNB|id=107316|doi=10.1038/ncomms14879}}';
        $template = $this->process_citation($text);
        $this->assertSame('10.1038/ncomms14879', $template->get2('doi'));
        $this->assertNull($template->get2('id'));
    }

    public function testPriorParametersGroupF1(): void {
        $parameter = 'surname2';
        $list = [];
        $expected = ['first1', 'forename1', 'initials1', 'author1', 'contributor-given1', 'contributor-first1', 'contributor1-given', 'contributor1-first'];
        $this->assertSame($expected, prior_parameters($parameter, $list));
    }

    public function testPriorParametersGroupL1(): void {
        $parameter = 'first3';
        $list = [];
        $expected = ['last3', 'surname3', 'author2', 'contributor-last2', 'contributor-surname2', 'contributor2', 'contributor2-surname', 'contributor2-last'];
        $this->assertSame($expected, prior_parameters($parameter, $list));
    }

    public function testPriorParametersGroup1(): void {
        $parameter = 'author';
        $list = [];
        $expected = ['author'];
        $this->assertSame($expected, prior_parameters($parameter, $list));
    }

    public function testPriorParametersGroup2(): void {
        $parameter = 'others';
        $list = [];
        // Doesn't merge GROUP 1 for some reason. Interesting.
        $this->assertSame([...FLATTENED_AUTHOR_PARAMETERS, 'others'], prior_parameters($parameter, $list));
    }

    public function testPriorParametersGroup5(): void {
        $parameter = 'journal';
        $list = [];
        $this->assertSame([...FLATTENED_AUTHOR_PARAMETERS, ...GROUP2, ...GROUP3, ...GROUP4, 'journal'], prior_parameters($parameter, $list));
    }

    public function testPriorParametersGroup5_2(): void {
        $parameter = 'work';
        $list = [];
        $this->assertSame([...FLATTENED_AUTHOR_PARAMETERS, ...GROUP2, ...GROUP3, ...GROUP4, 'work'], prior_parameters($parameter, $list));
    }

    public function testPriorParametersGroup15(): void {
        $parameter = 'doi-access';
        $list = [];
        $this->assertSame([...FLATTENED_AUTHOR_PARAMETERS, ...GROUP2, ...GROUP3, ...GROUP4, ...GROUP5, ...GROUP6, ...GROUP7, ...GROUP8, ...GROUP9, ...GROUP10, ...GROUP11, ...GROUP12, ...GROUP13, ...GROUP14, 'doi-access'], prior_parameters($parameter, $list));
    }

    public function testPriorParametersGroup23(): void {
        $parameter = 'hdl';
        $list = [];
        $this->assertSame([...FLATTENED_AUTHOR_PARAMETERS, ...GROUP2, ...GROUP3, ...GROUP4, ...GROUP5, ...GROUP6, ...GROUP7, ...GROUP8, ...GROUP9, ...GROUP10, ...GROUP11, ...GROUP12, ...GROUP13, ...GROUP14, ...GROUP15, ...GROUP17, ...GROUP18, ...GROUP19, ...GROUP20, ...GROUP21, ...GROUP22, 'hdl'], prior_parameters($parameter, $list));
    }

    public function testPriorParametersGroup30(): void {
        $parameter = 'id';
        $list = [];
        $this->assertSame([...FLATTENED_AUTHOR_PARAMETERS, ...GROUP2, ...GROUP3, ...GROUP4, ...GROUP5, ...GROUP6, ...GROUP7, ...GROUP8, ...GROUP9, ...GROUP10, ...GROUP11, ...GROUP12, ...GROUP13, ...GROUP14, ...GROUP15, ...GROUP17, ...GROUP18, ...GROUP19, ...GROUP20, ...GROUP21, ...GROUP22, ...GROUP23, ...GROUP24, ...GROUP25, ...GROUP26, ...GROUP27, ...GROUP28, ...GROUP29, 'id'], prior_parameters($parameter, $list));
    }

    public function testPriorParametersCustomList(): void {
        $parameter = 'author';
        $list = ['url', 'id'];
        $expected = ['url', 'id', 'author'];
        $this->assertSame($expected, prior_parameters($parameter, $list));
    }

    public function testPriorParametersParameterNotInAnyGroup1(): void {
        $parameter = 'not-a-param';
        $list = [];
        $expected = ['not-a-param'];
        $this->assertSame($expected, prior_parameters($parameter, $list));
    }

    public function testPriorParametersParameterNotInAnyGroup2(): void {
        $parameter = 's2cid1';
        $list = [];
        $expected = ['s2cid1'];
        $this->assertSame($expected, prior_parameters($parameter, $list));
    }

    public function testPriorParametersBlankParameter(): void {
        $parameter = '';
        // some params from GROUP 3
        $list = ['title-link', 'titlelink'];
        $this->assertSame([...FLATTENED_AUTHOR_PARAMETERS, ...GROUP2, 'title-link', 'titlelink'], prior_parameters($parameter, $list));
    }

    public function testPriorParametersBlankParameter_2(): void {
        $parameter = '';
        // these params are not in any groups
        $list = ['testing', 'more-testing'];
        $expected = [];
        $this->assertSame($expected, prior_parameters($parameter, $list));
    }

    public function testPriorParametersBlankParameterBlankList(): void {
        $parameter = '';
        $list = [];
        $expected = [];
        $this->assertSame($expected, prior_parameters($parameter, $list));
    }

    public function testPriorParametersDefaultNumericBranch(): void {
        $parameter = 'publisher2';
        $list = [];
        $this->assertSame([...FLATTENED_AUTHOR_PARAMETERS, 'publisher1', 'publisher1-last', 'publisher1-first', 'publisher-last1', 'publisher-first1', 'publisher1-surname', 'publisher1-given', 'publisher-surname1', 'publisher-given1'], prior_parameters($parameter, $list));
    }

    public function testNoDuplicates1(): void {
        $test = [...GROUP_F1, ...GROUP_L1];
        $unique = array_unique($test);
        $duplicates = array_diff_assoc($test, $unique);
        if (!empty($duplicates)) {
            $this->flush();
            print_r($duplicates);
            $this->flush();
        }
        $this->assertEmpty($duplicates);
    }

    public function testNoDuplicates2(): void {
        $test = [...GROUP1, ...GROUP2, ...GROUP3, ...GROUP4, ...GROUP5, ...GROUP6,
                 ...GROUP7, ...GROUP8, ...GROUP9, ...GROUP10, ...GROUP11, ...GROUP12,
                 ...GROUP13, ...GROUP14, ...GROUP15, ...GROUP17, ...GROUP18,
                 ...GROUP19, ...GROUP20, ...GROUP21, ...GROUP22, ...GROUP23, ...GROUP24,
                 ...GROUP25, ...GROUP26, ...GROUP27, ...GROUP28, ...GROUP29, ...GROUP30];
        $unique = array_unique($test);
        $duplicates = array_diff_assoc($test, $unique);
        if (!empty($duplicates)) {
            $this->flush();
            print_r($duplicates);
            $this->flush();
        }
        $this->assertEmpty($duplicates);
    }

    public function testEveryThingIsOnTheList(): void {
        $bad = [];
        $everything = [];
        foreach (PARAMETER_LIST as $param) {
            $everything[] = str_replace('#', '4', $param);
        }
        $everything = [...$everything, ...LOTS_OF_EDITORS, ...FLATTENED_AUTHOR_PARAMETERS];
        foreach ($everything as $param) {
            $param = mb_strtolower($param);
            $prior = prior_parameters($param);
            if (empty($prior)) {
                $bad[] = $param;
            }
        }
        sort($bad);
        $bad = array_unique($bad);
        if (!empty($bad)) {
            $this->flush();
            print_r($bad);
            $this->flush();
        }
        $this->assertEmpty($bad);
    }

    public function testEquivalentParametersAuthor(): void {
        $this->assertSame(FLATTENED_AUTHOR_PARAMETERS, equivalent_parameters('author'));
    }

    public function testEquivalentParametersAuthors(): void {
        $this->assertSame(FLATTENED_AUTHOR_PARAMETERS, equivalent_parameters('authors'));
    }

    public function testEquivalentParametersAuthor1(): void {
        $this->assertSame(FLATTENED_AUTHOR_PARAMETERS, equivalent_parameters('author1'));
    }

    public function testEquivalentParametersLast1(): void {
        $this->assertSame(FLATTENED_AUTHOR_PARAMETERS, equivalent_parameters('last1'));
    }

    public function testEquivalentParametersPmid(): void {
        $this->assertSame(['pmc', 'pmid'], equivalent_parameters('pmid'));
    }

    public function testEquivalentParametersPmc(): void {
        $this->assertSame(['pmc', 'pmid'], equivalent_parameters('pmc'));
    }

    public function testEquivalentParametersPagesGroup(): void {
        $result = equivalent_parameters('pages');
        $this->assertContains('pages', $result);
        $this->assertContains('page', $result);
        $this->assertContains('page_range', $result);
    }

    public function testEquivalentParametersPageGroup(): void {
        $result = equivalent_parameters('page');
        $this->assertContains('page', $result);
        $this->assertContains('pages', $result);
    }

    public function testEquivalentParametersStartPage(): void {
        $result = equivalent_parameters('start_page');
        $this->assertContains('start_page', $result);
        $this->assertContains('end_page', $result);
    }

    public function testEquivalentParametersEndPage(): void {
        $result = equivalent_parameters('end_page');
        $this->assertContains('end_page', $result);
        $this->assertContains('pages', $result);
    }

    public function testEquivalentParametersPageRange(): void {
        $result = equivalent_parameters('page_range');
        $this->assertContains('page_range', $result);
        $this->assertContains('pages', $result);
    }

    public function testEquivalentParametersDefaultReturnsSelf(): void {
        $this->assertSame(['title'], equivalent_parameters('title'));
    }

    public function testEquivalentParametersDoiReturnsSelf(): void {
        $this->assertSame(['doi'], equivalent_parameters('doi'));
    }

    public function testStringIsBookSeriesJournalName(): void {
        $this->assertFalse(string_is_book_series('Nature'));
    }

    public function testStringIsBookSeriesEmpty(): void {
        $this->assertFalse(string_is_book_series(''));
    }

    public function testShouldUrl2ChapterHasChapterUrl(): void {
        $template = $this->make_citation('{{cite book |url=http://example.com |chapter=Test |chapter-url=http://example.com/ch1}}');
        $this->assertFalse(should_url2chapter($template, false));
    }

    public function testShouldUrl2ChapterHasChapterurlOld(): void {
        $template = $this->make_citation('{{cite book |url=http://example.com |chapter=Test |chapterurl=http://example.com/ch1}}');
        $this->assertFalse(should_url2chapter($template, false));
    }

    public function testShouldUrl2ChapterHasTransChapter(): void {
        $template = $this->make_citation('{{cite book |url=http://example.com |chapter=Test |trans-chapter=Test Trans}}');
        $this->assertFalse(should_url2chapter($template, false));
    }

    public function testShouldUrl2ChapterNoChapter(): void {
        $template = $this->make_citation('{{cite book |url=http://example.com}}');
        $this->assertFalse(should_url2chapter($template, false));
    }

    public function testShouldUrl2ChapterChapterHasBracket(): void {
        $template = $this->make_citation('{{cite book |url=http://example.com |chapter=[Test]}}');
        $this->assertFalse(should_url2chapter($template, false));
    }

    public function testShouldUrl2ChapterGoogleWithoutPg(): void {
        $template = $this->make_citation('{{cite book |url=http://books.google.com/books?id=abc |chapter=Test}}');
        $this->assertFalse(should_url2chapter($template, false));
    }

    public function testShouldUrl2ChapterArchiveIsbn(): void {
        $template = $this->make_citation('{{cite book |url=http://archive.org/details/isbn_123 |chapter=Test}}');
        $this->assertFalse(should_url2chapter($template, false));
    }

    public function testShouldUrl2ChapterPageIdZero(): void {
        $template = $this->make_citation('{{cite book |url=http://example.com?page_id=0 |chapter=Test}}');
        $this->assertFalse(should_url2chapter($template, false));
    }

    public function testShouldUrl2ChapterPA0(): void {
        $template = $this->make_citation('{{cite book |url=http://example.com/PA0test |chapter=Test}}');
        $this->assertFalse(should_url2chapter($template, false));
    }

    public function testShouldUrl2ChapterSpringerChapterUrl(): void {
        $template = $this->make_citation('{{cite book |url=http://link.springer.com/chapter/10.1007/test |chapter=Test}}');
        $this->assertTrue(should_url2chapter($template, false));
    }

    public function testShouldUrl2ChapterForcedTrue(): void {
        $template = $this->make_citation('{{cite book |url=http://example.com/page |chapter=Test}}');
        $this->assertTrue(should_url2chapter($template, true));
    }

    public function testShouldUrl2ChapterTaylorFrancis(): void {
        $text = '{{cite book|chapter=Test Chapter|url=https://www.taylorfrancis.com/chapters/edit/10.4324/9781003000000-1/test}}';
        $template = $this->make_citation($text);
        $template->tidy();
        $this->assertNull($template->get2('url'));
        $this->assertTrue($template->has('chapter-url'));
    }

    public function testShouldUrl2ChapterEmerald(): void {
        $template = $this->make_citation('{{cite book |chapter=Test |url=https://www.emerald.com/books/chapter-abstract/123}}');
        $this->assertTrue(should_url2chapter($template, false));
    }

    public function testShouldUrl2ChapterWileyChapter(): void {
        $template = $this->make_citation('{{cite book |chapter=Test |url=https://onlinelibrary.wiley.com/doi/10.1002/9781119999999.ch23}}');
        $this->assertTrue(should_url2chapter($template, false));
    }

    public function testShouldUrl2ChapterWileyJournal(): void {
        $template = $this->make_citation('{{cite book |chapter=Test |url=https://onlinelibrary.wiley.com/doi/10.1002/j.1460-244X.1963.tb00217.x}}');
        $this->assertFalse(should_url2chapter($template, false));
    }

    public function testShouldUrl2ChapterWileySubdomain(): void {
        $template = $this->make_citation('{{cite book |chapter=Test |url=https://onlinelibrary.wiley.com/doi/book/10.1002/0470845015.ch1}}');
        $this->assertTrue(should_url2chapter($template, false));
    }

    public function testRunTypeModsReturnsInt(): void {
        $result = run_type_mods(-1, 10, 20, 30, 40);
        $this->assertIsInt($result);
    }

    #[DataProvider('shouldUrl2ChapterProvider')]
    public function testShouldUrl2Chapter(
        string $citation,
        bool $force,
        bool $expected
    ): void {
        $template = $this->make_citation($citation);

        $this->assertSame(
            $expected,
            should_url2chapter($template, $force)
        );
    }

    /**
     * @return array<string, array{string, bool, bool}>
     */
    public static function shouldUrl2ChapterProvider(): array {
        return [
            // Existing chapter URL information prevents moving the URL.
            'chapterurl already present' => [
                '{{Cite book|chapter=X|chapterurl=https://example.com/chapter|url=https://example.com/book}}',
                false,
                false,
            ],
            'chapter-url already present' => [
                '{{Cite book|chapter=X|chapter-url=https://example.com/chapter|url=https://example.com/book}}',
                false,
                false,
            ],
            'translated chapter present' => [
                '{{Cite book|chapter=X|trans-chapter=Translated X|url=https://example.com/book}}',
                false,
                false,
            ],

            // Chapter-content checks.
            'blank chapter' => [
                '{{Cite book|chapter=|url=https://example.com/book}}',
                false,
                false,
            ],
            'linked chapter' => [
                '{{Cite book|chapter=[[Example chapter]]|url=https://example.com/book}}',
                false,
                false,
            ],

            // Google Books.
            'google book without page' => [
                '{{Cite book|chapter=X|url=https://books.google.com/books?id=ABC}}',
                false,
                false,
            ],
            'google book with real page' => [
                '{{Cite book|chapter=X|url=https://books.google.com/books?id=ABC&pg=PA12}}',
                false,
                true,
            ],
            'google PA1 is front matter' => [
                '{{Cite book|chapter=X|url=https://books.google.com/books?id=ABC&pg=PA1}}',
                false,
                false,
            ],
            'google PA0 is front matter' => [
                '{{Cite book|chapter=X|url=https://books.google.com/books?id=ABC&pg=PA0}}',
                false,
                false,
            ],
            'google PP1 is front matter' => [
                '{{Cite book|chapter=X|url=https://books.google.com/books?id=ABC&pg=PP1}}',
                false,
                false,
            ],
            'google PP12 is usable' => [
                '{{Cite book|chapter=X|url=https://books.google.com/books?id=ABC&pg=PP12}}',
                false,
                true,
            ],
            'google PP0 is front matter' => [
                '{{Cite book|chapter=X|url=https://books.google.com/books?id=ABC&pg=PP0}}',
                false,
                false,
            ],

            // Archive.org.
            'archive isbn landing page' => [
                '{{Cite book|chapter=X|url=https://archive.org/details/isbn_9780123456789/page/n30}}',
                false,
                false,
            ],
            'archive details landing page' => [
                '{{Cite book|chapter=X|url=https://archive.org/details/examplebook}}',
                false,
                false,
            ],
            'archive early page n15' => [
                '{{Cite book|chapter=X|url=https://archive.org/details/examplebook/page/n15}}',
                false,
                false,
            ],
            'archive page n16' => [
                '{{Cite book|chapter=X|url=https://archive.org/details/examplebook/page/n16}}',
                false,
                true,
            ],
            'archive ordinary page' => [
                '{{Cite book|chapter=X|url=https://archive.org/details/examplebook/page/n200}}',
                false,
                true,
            ],
            'archive chapter URL' => [
                '{{Cite book|chapter=X|url=https://archive.org/details/examplebook/chapter/4}}',
                false,
                true,
            ],

            // Generic page-zero/front-matter indicators.
            'wordpress page id zero' => [
                '{{Cite book|chapter=X|url=https://example.com/?page_id=0}}',
                false,
                false,
            ],
            'page zero parameter' => [
                '{{Cite book|chapter=X|url=https://example.com/?page=0}}',
                false,
                false,
            ],
            'underscore zero suffix' => [
                '{{Cite book|chapter=X|url=https://example.com/book_0}}',
                false,
                false,
            ],

            // WordPress heuristics.
            'wordpress chapter' => [
                '{{Cite book|chapter=X|url=https://example.com/wp-content/uploads/book-chapter-3.pdf}}',
                false,
                true,
            ],
            'wordpress section' => [
                '{{Cite book|chapter=X|url=https://example.com/wp-content/uploads/book-section-3.pdf}}',
                false,
                true,
            ],
            'wordpress later page range' => [
                '{{Cite book|chapter=X|url=https://example.com/wp-content/uploads/pages-22-35.pdf}}',
                false,
                true,
            ],
            'wordpress page range beginning at one' => [
                '{{Cite book|chapter=X|url=https://example.com/wp-content/uploads/pages-1-35.pdf}}',
                false,
                false,
            ],
            'wordpress unrelated file' => [
                '{{Cite book|chapter=X|url=https://example.com/wp-content/uploads/book.pdf}}',
                false,
                false,
            ],

            // Springer / Palgrave chapter URLs.
            'springer chapter URL' => [
                '{{Cite book|chapter=X|url=https://link.springer.com/chapter/10.1007/978-3-030-12345-6_7}}',
                false,
                true,
            ],
            'springer DOI-shaped URL' => [
                '{{Cite book|chapter=X|url=https://example.com/10.1007/978-3-030-12345-6_7}}',
                false,
                true,
            ],
            'palgrave DOI-shaped URL' => [
                '{{Cite book|chapter=X|url=https://example.com/10.1057/978-1-137-12345-6_7}}',
                false,
                true,
            ],

            // Other recognized publishers.
            'science direct article' => [
                '{{Cite book|chapter=X|url=https://www.sciencedirect.com/science/article/pii/S123456789}}',
                false,
                true,
            ],
            'taylor and francis chapter' => [
                '{{Cite book|chapter=X|url=https://www.taylorfrancis.com/chapters/edit/10.4324/example}}',
                false,
                true,
            ],
            'emerald chapter' => [
                '{{Cite book|chapter=X|url=https://www.emerald.com/insight/content/doi/10.1108/example/full/html/chapter-2}}',
                false,
                true,
            ],
            'emerald insight chapter' => [
                '{{Cite book|chapter=X|url=https://www.emeraldinsight.com/example/chapter-4}}',
                false,
                true,
            ],
            'wiley chapter suffix' => [
                '{{Cite book|chapter=X|url=https://onlinelibrary.wiley.com/doi/10.1002/9781111111111.ch12}}',
                false,
                true,
            ],

            // Generic fallback.
            'unknown site defaults false' => [
                '{{Cite book|chapter=X|url=https://example.com/book/chapter/4}}',
                false,
                false,
            ],
            'force overrides unknown site' => [
                '{{Cite book|chapter=X|url=https://example.com/book/chapter/4}}',
                true,
                true,
            ],
        ];
    }
}
