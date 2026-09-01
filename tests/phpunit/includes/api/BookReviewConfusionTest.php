<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class BookReviewConfusionTest extends testBaseClass {
    public function testTurnbullBookNotMashedWithNatureReview(): void {
        $text = '{{citation|editor=Turnbull, H. W.|title=The James Gregory Tercentenary Memorial Volume|publication-place=London|year=1939}}';
        $template = $this->make_citation($text);
        $this->assertTrue($template->has('title'));
        $this->assertSame('The James Gregory Tercentenary Memorial Volume', $template->get('title'));
        // After fix, helper should recognise this as book-like
        $this->assertTrue(isBookCitationForReviewGuard($template), 'Turnbull citation should be treated as book for review confusion guard');
        $record = (object) [
            'title' => ['James Gregory Tercentenary Memorial Volume'],
            'author' => ['Plummer, H. C.'],
            'pub' => 'Nature',
            'volume' => '144',
            'issue' => '3661',
            'page' => ['1062'],
            'year' => '1939',
            'bibcode' => '1939Natur.144.1062P',
            'doi' => ['10.1038/1441062a0'],
            'doctype' => 'article',
        ];
        $this->assertTrue(titles_are_similar($template->get('title'), $record->title[0]));
        $this->assertTrue(isAdsBookReviewConfusion($template, $record), 'ADS review confusion should be detected');
        // Simulate that expand_by_adsabs would reject — template stays clean
        $this->assertTrue($template->blank(['journal','volume','issue','pages','page','doi','bibcode','bibcode_nosearch']));
    }

    public function testAdsRecordLooksLikeReviewForNature1939(): void {
        $record = (object) [
            'title' => ['James Gregory Tercentenary Memorial Volume'],
            'pub' => 'Nature',
            'volume' => '144',
            'page' => ['1062'],
            'year' => '1939',
            'bibcode' => '1939Natur.144.1062P',
            'doi' => ['10.1038/1441062a0'],
            'doctype' => 'article',
        ];
        $this->assertTrue(adsRecordLooksLikeReview($record), 'Nature 1939 review should be considered potential review');
    }

    public function testCitationLooksLikeBookForTurnbull(): void {
        $template = $this->make_citation('{{citation|editor=Turnbull, H. W.|title=The James Gregory Tercentenary Memorial Volume|publication-place=London|year=1939}}');
        $this->assertTrue(isBookCitationForReviewGuard($template), 'Turnbull citation should be treated as book for review confusion guard');
        // Original citationLooksLikeBook is false for publication-place only (score 1), so guard is needed
        $this->assertFalse(citationLooksLikeBook($template));
        $this->assertTrue(isBookCitationForReviewGuard($template));
    }

    public function testCrossRefDoesNotReturnNatureReviewForBook(): void {
        $template = $this->make_citation('{{citation|editor=Turnbull, H. W.|title=The James Gregory Tercentenary Memorial Volume|publication-place=London|year=1939}}');
        $crossRefMsg = (object) [
            'title' => ['James Gregory Tercentenary Memorial Volume'],
            'container-title' => ['Nature'],
            'volume' => '144',
            'page' => '1062-1063',
            'issued' => (object) ['date-parts' => [[1939]]],
            'DOI' => '10.1038/1441062a0',
            'type' => 'journal-article',
        ];
        $this->assertTrue(isCrossRefReviewConfusion($template, $crossRefMsg), 'CrossRef Nature hit for book title should be flagged as review');
    }

    public function testBooksReceivedLiteralNotFlaggedAsReview(): void {
        $record = (object) ['title' => ['Books Received'], 'pub' => 'Nature', 'doi' => ['10.1038/308567a0'], 'doctype' => 'article'];
        $this->assertFalse(adsRecordLooksLikeReview($record), 'Literal Books Received listing should not be review');
    }

    public function testLiteratureReviewNotBookReview(): void {
        $template = $this->make_citation('{{cite journal|title=Systematic literature review of X|journal=Nature|year=2020|volume=1}}');
        // Journal template is not book-like, so guard false
        $this->assertFalse(isBookCitationForReviewGuard($template));
        $record = (object) ['title' => ['Systematic literature review of X'], 'pub' => 'Nature', 'doctype' => 'article'];
        $this->assertFalse(adsRecordLooksLikeReview($record) && isBookCitationForReviewGuard($template));
        // Direct check: literature review title should not be considered book review
        $this->assertFalse(adsRecordLooksLikeReview((object)['title'=>['Systematic literature review of X'], 'doctype'=>'article', 'pub'=>'Nature']));
    }

    public function testLegitCiteJournalNotBlocked(): void {
        // Legit journal article with same title logic should not be blocked when template is journal
        $template = $this->make_citation('{{cite journal|title=James Gregory Tercentenary Memorial Volume|journal=Nature|year=1939|volume=144|page=1062}}');
        $this->assertFalse(isBookCitationForReviewGuard($template), 'Cite journal should not be book-like');
        $record = (object) [
            'title' => ['James Gregory Tercentenary Memorial Volume'],
            'pub' => 'Nature',
            'volume' => '144',
            'page' => ['1062'],
            'year' => '1939',
            'bibcode' => '1939Natur.144.1062P',
            'doctype' => 'article',
        ];
        $this->assertFalse(isAdsBookReviewConfusion($template, $record), 'Journal template should not trigger confusion guard');
    }

    public function testSylvesterBookNotMashedWithNatureReview(): void {
        // Real archive case: Matrix (mathematics) Sylvester 1904 book vs Nature 71:98 review
        // https://en.wikipedia.org/w/index.php?title=Matrix_(mathematics)&diff=next&oldid=1291900192
        $template = $this->make_citation('{{citation|first=J. J.|last=Sylvester|editor-first=H. F.|editor-last=Baker|title=The Collected Mathematical Papers of James Joseph Sylvester|location=Cambridge, England|publisher=Cambridge University Press|year=1904}}');
        $this->assertTrue(isBookCitationForReviewGuard($template));
        $record = (object) [
            'title' => ['The Collected Mathematical Papers of James Joseph Sylvester'],
            'pub' => 'Nature',
            'volume' => '71',
            'issue' => '1831',
            'page' => ['98'],
            'year' => '1904',
            'bibcode' => '1904Natur..71...98G',
            'doi' => ['10.1038/071098a0'],
            'doctype' => 'article',
        ];
        $this->assertTrue(titles_are_similar($template->get('title'), $record->title[0]));
        $this->assertTrue(isAdsBookReviewConfusion($template, $record));
        $this->assertTrue(adsRecordLooksLikeReview($record));
    }

    public function testPeresBookNotMashedWithAmJPhReview(): void {
        // Real archive case: Peres 1993 book vs AmJPh 1995 review (year diff 2 but still confused)
        // https://en.wikipedia.org/w/index.php?title=Matrix_(mathematics)&diff=next&oldid=1291912931
        $template = $this->make_citation('{{citation|last=Peres|first=Asher|title=Quantum Theory: Concepts and Methods|publisher=Kluwer|year=1993|isbn=978-0-7923-3632-7}}');
        $this->assertTrue(isBookCitationForReviewGuard($template));
        $record = (object) [
            'title' => ['Quantum Theory: Concepts and Methods'],
            'pub' => 'American Journal of Physics',
            'volume' => '63',
            'issue' => '3',
            'page' => ['285'],
            'year' => '1995',
            'bibcode' => '1995AmJPh..63..285P',
            'doi' => ['10.1119/1.17946'],
            'doctype' => 'article',
        ];
        $this->assertTrue(titles_are_similar($template->get('title'), $record->title[0]));
        // Short pagination + journal-like pub + same title should be flagged even with year diff 2
        $this->assertTrue(isAdsBookReviewConfusion($template, $record));
    }

}
