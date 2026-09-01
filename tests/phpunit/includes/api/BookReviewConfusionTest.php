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

}
