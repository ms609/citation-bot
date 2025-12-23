<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class miscToolsTest extends testBaseClass {
    public function testcheck_memory_usage(): void {
        $pg = new TestPage(); // Fill page name with test name for debugging
        unset($pg);
        check_memory_usage('testcheck_memory_usage');
        $this->assertFaker();
    }

    public function testThrottle(): void { // Just runs over the code and basically does nothing
        for ($x = 0; $x <= 25; $x++) {
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
        $text = '{{Cite ODNB|url=https://www.oxforddnb.com/view/10.1093/odnb/9780198614128.001.0001/odnb-9780198614128-e-107316|id=107316|doi=10.0000/Rubbish_bot_failure_test}}';
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
        $expected = array_merge(['surname'], FLATTENED_AUTHOR_PARAMETERS, ['others']);
        $this->assertSame($expected, prior_parameters($parameter, $list));
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

    public function testPriorParametersGroup5(): void {
        $parameter = 'journal';
        $list = [];
        // prior_parameters() outputs the first parameter twice for some reason. So for example, FLATTENED_AUTHOR_PARAMETERS is an array ['surname', 'forename', 'initials', etc. ]. And the output of prior_parameters() is ['surname, 'surname', 'forename', 'initials', etc. ]. The strings in the below list are these duplicates.
        // TODO: don't output the first parameter twice? seems unnecessary.
        $expected = array_merge(
            ['surname'], FLATTENED_AUTHOR_PARAMETERS, ['others'], GROUP2, ['title'], GROUP3, ['chapter'], GROUP4, ['journal']
        );
        $this->assertSame($expected, prior_parameters($parameter, $list));
    }

    public function testPriorParametersCustomList(): void {
        $parameter = 'author';
        $list = ['url', 'id'];
        $expected = ['author', 'url', 'id'];
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

    public function testPriorParametersGroup15(): void {
        $parameter = 'doi-access';
        $list = [];
        // doi-broken-date is in two GROUPs for some reason.
        // I tried writing a test for group 16 (one of the duplicate doi-broken-dates) and wasn't able to reach that code.
        // TODO: delete GROUP 16? or replace it with ['']?
        $expected = array_merge(
            ['surname'], FLATTENED_AUTHOR_PARAMETERS, ['others'], GROUP2, ['title'], GROUP3, ['chapter'], GROUP4, ['journal'], GROUP5, ['series'], GROUP6, ['year'], GROUP7, ['volume'], GROUP8, ['issue'], GROUP9, ['page'], GROUP10, ['article-number'], GROUP11, ['location'], GROUP12, ['doi'], GROUP13, ['doi-broken-date'], GROUP14, ['doi-access']
        );
        $this->assertSame($expected, prior_parameters($parameter, $list));
    }

    public function testPriorParametersGroup23(): void {
        $parameter = 'hdl';
        $list = [];
        // GROUPS 15 and 16 get skipped because of the duplicate doi-broken-date parameter.
        $expected = array_merge(
            ['surname'], FLATTENED_AUTHOR_PARAMETERS, ['others'], GROUP2, ['title'], GROUP3, ['chapter'], GROUP4, ['journal'], GROUP5, ['series'], GROUP6, ['year'], GROUP7, ['volume'], GROUP8, ['issue'], GROUP9, ['page'], GROUP10, ['article-number'], GROUP11, ['location'], GROUP12, ['doi'], GROUP13, ['doi-broken-date'], GROUP14, ['jstor'], GROUP17, ['pmid'], GROUP18, ['pmc'], GROUP19, ['pmc-embargo-date'], GROUP20, ['arxiv'], GROUP21, ['bibcode'], GROUP22, ['hdl']
        );
        $this->assertSame($expected, prior_parameters($parameter, $list));
    }

    public function testPriorParametersGroup30(): void {
        $parameter = 'id';
        $list = [];
        // GROUPS 15 and 16 get skipped because of the duplicate doi-broken-date parameter.
        $expected = array_merge(
            ['surname'], FLATTENED_AUTHOR_PARAMETERS, ['others'], GROUP2, ['title'], GROUP3, ['chapter'], GROUP4, ['journal'], GROUP5, ['series'], GROUP6, ['year'], GROUP7, ['volume'], GROUP8, ['issue'], GROUP9, ['page'], GROUP10, ['article-number'], GROUP11, ['location'], GROUP12, ['doi'], GROUP13, ['doi-broken-date'], GROUP14, ['jstor'], GROUP17, ['pmid'], GROUP18, ['pmc'], GROUP19, ['pmc-embargo-date'], GROUP20, ['arxiv'], GROUP21, ['bibcode'], GROUP22, ['hdl'], GROUP23, ['isbn'], GROUP24, ['lccn'], GROUP25, ['url'], GROUP26, ['chapter-url'], GROUP27, ['archive-url'], GROUP28, ['archive-date'], GROUP29, ['id']
        );
        $this->assertSame($expected, prior_parameters($parameter, $list));
    }

    public function testPriorParametersDefaultNumericBranch(): void {
        $parameter = 'publisher2';
        $list = [];
        $expected = array_merge(
            FLATTENED_AUTHOR_PARAMETERS,
            [
                'publisher1',
                'publisher1-last',
                'publisher1-first',
                'publisher-last1',
                'publisher-first1',
                'publisher1-surname',
                'publisher1-given',
                'publisher-surname1',
                'publisher-given1',
            ]
        );
        $this->assertSame($expected, prior_parameters($parameter, $list));
    }
}
