<?php
declare(strict_types=1);

/**
 * CSL-JSON from doi.org carries the page range under "page".
 */

require_once dirname(__DIR__, 3) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditDoiJsonPageKeyTest extends testBaseClass {

    public function testPageKeyIsRead(): void {
        $template = $this->make_citation('{{cite journal|title=A title}}');
        process_doi_json($template, '10.1000/page-key-test', [
            'type' => 'journal-article',
            'title' => ['A title'],
            'page' => '177-209',
        ]);
        $this->assertSame('177–209', $template->get2('pages'));
    }

    public function testLegacyPagesKeyIsStillRead(): void {
        $template = $this->make_citation('{{cite journal|title=A title}}');
        process_doi_json($template, '10.1000/pages-key-test', [
            'type' => 'journal-article',
            'title' => ['A title'],
            'pages' => '177-209',
        ]);
        $this->assertSame('177–209', $template->get2('pages'));
    }

    /** @return array<string, array{string}> */
    public static function issueDesignations(): array {
        return [
            'reported edit' => ['No 1 (April 2026)'],
            'plain issue label' => ['No 1'],
            'No. with punctuation' => ['No. 1 (April 2026)'],
            'compact No.' => ['No.1'],
            'issue label' => ['Issue 1 (April 2026)'],
            'number label' => ['Number 1, 2026'],
            'volume and number' => ['Vol. 19, No. 1 (2026)'],
            'volume and issue' => ['Volume 19 Issue 1 (2026)'],
        ];
    }

    #[DataProvider('issueDesignations')]
    public function testIssueDesignationCannotReplacePagesOrBeAddedToBlankCitation(string $badPage): void {
        foreach (['page', 'pages'] as $parameter) {
            // This exactly reproduces the Lucid dream revision's existing range.
            // In a recent article the old logic would replace pages=1–13.
            $template = $this->make_citation(
                '{{cite journal|title=Server-Human Communication in Lucid Dreams|year=2026|pages=1–13}}'
            );
            $this->assertFalse($template->add_if_new($parameter, $badPage, 'dx'));
            $this->assertSame('1–13', $template->get2('pages'));
            $this->assertNull($template->get2('page'));

            // A missing page must stay missing rather than acquiring issue metadata.
            $empty = $this->make_citation('{{cite journal|title=A title}}');
            $this->assertFalse($empty->add_if_new($parameter, $badPage, 'dx'));
            $this->assertNull($empty->get2('pages'));
            $this->assertNull($empty->get2('page'));
        }
    }

    /** @return array<string, array{string}> */
    public static function doiPageKeys(): array {
        return ['CSL-JSON page' => ['page'], 'legacy pages' => ['pages']];
    }

    #[DataProvider('doiPageKeys')]
    public function testDoiJsonIssueDesignationPreservesActualPages(string $pageKey): void {
        $doi = '10.11588/ijodr.2026.1.107481';
        $template = $this->make_citation(
            '{{cite journal|title=Server-Human Communication in Lucid Dreams|year=2026|volume=19|pages=1–13}}'
        );
        process_doi_json($template, $doi, [
            'type' => 'journal-article',
            'title' => ['Server-Human Communication in Lucid Dreams'],
            'volume' => '19',
            'issue' => '1',
            $pageKey => 'No 1 (April 2026)',
        ]);
        $this->assertSame('1–13', $template->get2('pages'));
        $this->assertNull($template->get2('page'));
        $this->assertSame('1', $template->get2('issue'));
    }

    #[DataProvider('doiPageKeys')]
    public function testDoiJsonIssueDesignationDoesNotFillBlankPage(string $pageKey): void {
        $template = $this->make_citation('{{cite journal|title=A title}}');
        process_doi_json($template, '10.1000/issue-as-page', [
            'type' => 'journal-article',
            'title' => ['A title'],
            $pageKey => 'No 1 (April 2026)',
        ]);
        $this->assertNull($template->get2('pages'));
        $this->assertNull($template->get2('page'));
    }

    public function testRealFirstPageRangeStillAccepted(): void {
        $template = $this->make_citation('{{cite journal|title=A title|year=2026}}');
        process_doi_json($template, '10.1000/actual-pages', [
            'type' => 'journal-article',
            'title' => ['A title'],
            'page' => '1-13',
        ]);
        $this->assertSame('1–13', $template->get2('pages'));
    }

}
