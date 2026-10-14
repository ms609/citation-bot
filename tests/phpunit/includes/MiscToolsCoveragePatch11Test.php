<?php

declare(strict_types=1);

/** No-network coverage of the conference/metadata cleanup decision matrix. */
require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class MiscToolsCoveragePatch11Test extends testBaseClass {
    /**
     * These are independently parsed citations. The real DOI is treated as
     * already verified and queried so the cleanup cannot call CrossRef.
     *
     * @return array<string, array{string}>
     */
    public static function suspiciousMetadataProvider(): array {
        return [
            'bad journal and title cryptology' => ['|journal=Advances in Cryptology|title=Advances in Cryptology 2024'],
            'conference-labelled journal' => ['|journal=IEEE International Conference on Applied Computing|title=Original work'],
            'conference proceedings' => ['|journal=Proceedings of SPIE|title=Optical Measurements'],
            'ACM proceedings' => ['|journal=ACM Symposium on Computer Science|title=A Computing Paper'],
            'SIGGRAPH' => ['|journal=SIGGRAPH Proceedings|title=Graphics'],
            'design automation conference' => ['|journal=Design Automation Conference|title=Hardware Design'],
            'LNCS guard intentionally bypasses generic cleanup' => ['|journal=Lecture Notes in Computer Science|title=A Study'],
            'journal is a repository' => ['|journal=JSTOR|title=A Research Paper'],
            'journal is BioOne' => ['|journal=BioOne|title=A Research Paper'],
            'journal is PubMed' => ['|journal=PubMed|title=A Research Paper'],
            'journal is an online portal' => ['|journal=ScienceDirect|title=Research Results'],
            'journal only contains a series name' => ['|journal=Biochimica et Biophysica Acta|title=Biological Research'],
            'unusable page and pages' => ['|page=null|pages=online|title=Research'],
            'unusable volume and issue' => ['|volume=Online edition|issue=ahead-of-print|title=Research'],
            'unfinished volume and issue' => ['|volume=in press|issue=Forthcoming|title=Research'],
            'zero volume and issue' => ['|volume=0|issue=0|title=Research'],
            'all uppercase title' => ['|title=UPPERCASE ARTICLE TITLE FOR JOURNAL'],
            'all uppercase journal' => ['|journal=UPPERCASE JOURNAL NAME IN ENGLISH|title=Research'],
            'all uppercase chapter' => ['|chapter=UPPERCASE CHAPTER HEADING IN ENGLISH|title=Research'],
            'title is an authentication page' => ['|title=Shibboleth Authentication Request'],
            'title is a publisher' => ['|title=ScienceDirect'],
            'title is Pubmed Central' => ['|title=Pubmed Central'],
            'title is a site index' => ['|title=IEEE Xplore'],
            'title references JSTOR' => ['|title=Source Article on JSTOR'],
            'title begins IEEE Xplore' => ['|title=IEEE Xplore publication'],
            'title ends IEEE Xplore' => ['|title=Science of computers IEEE Xplore'],
            'title is truncated issue label' => ['|title=Research Volume 2 page 123'],
            'title contains arXiv' => ['|title=Research on arXiv'],
            'chapter is missing' => ['|chapter=_|title=An Article'],
            'title and journal identical' => ['|title=A complete observation|journal=A complete observation'],
            'title and chapter identical' => ['|title=A complete observation|chapter=A complete observation'],
            'title journal chapter identical' => [
                '|title=A complete observation|chapter=A complete observation|journal=A complete observation',
            ],
            'conflicting flattened and group authors' => ['|first=Alex|last=Smith|author=Smith|coauthors=Jones'],
            'conflicting numbered authors' => ['|first1=Alex|last1=Smith|author1=Smith|coauthors=Jones'],
            'year with dubious metadata' => ['|year=2020|issue=0|title=Research'],
            'chapter from IEEE record' => ['|chapter=Original Chapter|journal=IEEE Symposium on Computing|title=Research'],
        ];
    }

    #[DataProvider('suspiciousMetadataProvider')]
    public function testDamagedMetadataKeepsTrustedDoiWithoutCallingRemoteService(string $parameters): void {
        $doi = '10.1234/patch11-offline';
        $template = $this->make_citation('{{cite journal|doi=' . $doi . $parameters . '}}');
        $this->assertSame($doi, $template->get('doi'));

        // This is a narrow, isolated fixture seam: expand_by_doi() checks
        // verify_doi() and last_searched_doi before attempting transport.
        $verified = new ReflectionProperty(Template::class, 'doi_valid');
        $verified->setValue($template, true);
        $template->last_searched_doi = $doi;
        handleConferencePretendingToBeAJournal($template, $template->parsed_text());

        $this->assertSame($doi, $template->get('doi'), 'Cleanup must not discard a trusted DOI');
        $this->assertSame($doi, $template->last_searched_doi, 'Fixture must not trigger another DOI lookup');
        if (str_contains($parameters, 'Lecture Notes in Computer Science')) {
            $this->assertSame('Lecture Notes in Computer Science', $template->get('journal'));
        }
    }

    /**
     * Exercise conference DOI cases that lead to different book/journal
     * preservation decisions. Each DOI is pre-verified to prevent HTTP calls.
     *
     * @return array<string, array{string}>
     */
    public static function conferenceMetadataProvider(): array {
        return [
            'conference journal with ISBN' => ['|journal=Conference Proceedings|title=Study|isbn=9780306406157'],
            'proceedings in both title and journal' => [
                '|journal=Proceedings of Engineering|title=Proceedings of Engineering',
            ],
            'symposium in both' => ['|journal=Symposium on Safety|title=Symposium on Safety'],
            'conference in both' => ['|journal=Conference on Robotics|title=Conference on Robotics'],
            'colloquium in both' => ['|journal=Colloquium on Physics|title=Colloquium on Physics'],
            'workshop in journal' => ['|journal=Workshop on New Devices|title=An Investigation|isbn=9780306406157'],
            'IEEE standards in title without journal' => ['|title=IEEE Standard for Computing|isbn=9780306406157'],
            'SIGCOMM special exception' => ['|journal=SIGCOMM Computer Communication Review|title=Proceedings|isbn=9780306406157'],
            'conference DOI with issue and volume' => [
                '|journal=International Conference on Robotics|title=Robot Control|issue=0|volume=Online',
            ],
            'conference DOI with a chapter' => [
                '|journal=IEEE Symposium on Computing|title=Original Study|chapter=Introduction',
            ],
            'conference with descriptive publication' => [
                '|journal=Journal of Applied Technology|title=Proceedings in Embedded Computing',
            ],
        ];
    }

    #[DataProvider('conferenceMetadataProvider')]
    public function testConferenceDoiMetadataPreservedWithoutCrossrefRequests(string $parameters): void {
        $doi = '10.1145/patch11-offline';
        $template = $this->make_citation('{{cite journal|doi=' . $doi . $parameters . '}}');
        $verified = new ReflectionProperty(Template::class, 'doi_valid');
        $verified->setValue($template, true);
        $template->last_searched_doi = $doi;
        $this->assertTrue(conference_doi($doi));

        handleConferencePretendingToBeAJournal($template, $template->parsed_text());

        $this->assertSame($doi, $template->get('doi'));
        $this->assertSame($doi, $template->last_searched_doi);
    }

}
