<?php
declare(strict_types=1);

/**
 * A URL that is passed in (rather than read from a parameter) has no
 * parameter to rewrite. The internal placeholder name must never be written
 * into the citation.
 */

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class AuditSentUrlSentinelTest extends testBaseClass {

    #[DataProvider('sentPubMedUrlProvider')]
    public function testSentPubMedUrlDoesNotCreateSentinelParameter(string $url): void {
        $template = $this->make_citation('{{cite journal|title=A title}}');
        $template->get_identifiers_from_url($url);
        $this->assertNull($template->get2('An invalid value'));
        $this->assertStringNotContainsString('An invalid value', $template->parsed_text());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sentPubMedUrlProvider(): array {
        return [
            'author search' => ['https://www.ncbi.nlm.nih.gov/pubmed/?term=Sainis%20KB%5BAuthor%5D&cauthor=true&cauthor_uid=19447493'],
            'numeric search term' => ['https://www.ncbi.nlm.nih.gov/pubmed/?term=19447493'],
            'text fragment link' => ['https://pubmed.ncbi.nlm.nih.gov/19447493/#:~:text=abstract'],
        ];
    }
}
