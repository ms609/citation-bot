<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class MiscToolsTest extends testBaseClass {
    public function testcheck_memory_usage(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        check_memory_usage('testcheck_memory_usage');
        $this->assertFaker();
    }

    public function testThrottle(): void { // Just runs over the code and basically does nothing
        $do_it = run_type_mods(-1, 25, 25, 1, 1);
        for ($x = 0; $x <= $do_it; $x++) {
            throttle();
        }
        $this->assertFaker();
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

}
