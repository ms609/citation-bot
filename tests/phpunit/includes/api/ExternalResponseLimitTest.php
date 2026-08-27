<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../testBaseClass.php';

final class ExternalResponseLimitTest extends testBaseClass {
    /**
     * @return array<string, array{string, int}>
     */
    public static function configuredLimitProvider(): array {
        return [
            'semantic scholar' => ['APIS2.php', 4],
            'unpaywall' => ['APIunpaywall.php', 4],
            'arxiv' => ['APIarXiv.php', 32],
            'pubmed' => ['APIPubMed.php', 16],
            'crossref' => ['APIdoi.php', 16],
            'google books' => ['APIgoogle.php', 8],
            'zotero' => ['APIzotero.php', 8],
            'archives' => ['APIarchives.php', 16],
        ];
    }

    /**
     * @dataProvider configuredLimitProvider
     */
    public function testExternalClientsSetExplicitResponseLimits(string $file, int $megabytes): void {
        $source = file_get_contents(__DIR__ . '/../../../../src/includes/api/' . $file);
        $this->assertIsString($source);
        $this->assertStringContainsString(
            'bot_curl_set_max_response_bytes(',
            $source,
            $file . ' should configure a response-size limit'
        );
        $this->assertStringContainsString(
            $megabytes . ' * 1024 * 1024',
            $source
        );
    }
}
