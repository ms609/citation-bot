<?php
declare(strict_types=1);

require_once __DIR__ . '/../../testBaseClass.php';

final class MediaWikiResponseInteractionTest extends testBaseClass {
    private function page(string $title, int $revision): object {
        return (object) [
            'lastrevid' => $revision,
            'title' => $title,
            'revisions' => [
                (object) ['timestamp' => '2026-08-29T12:00:00Z'],
            ],
        ];
    }

    public function testSinglePageResponsePassesBothSelectionAndWriteValidation(): void {
        $page = $this->page('Example', 123);
        $response = (object) [
            'batchcomplete' => true,
            'query' => (object) [
                'pages' => (object) ['123' => $page],
                'tokens' => (object) ['csrftoken' => 'token'],
            ],
        ];

        $selected = WikipediaBot::first_page_from_response($response);
        $write_page = WikipediaBot::response2page($response);

        $this->assertNotNull($selected);
        $this->assertNotNull($write_page);
        $this->assertEquals($page, $selected);
        $this->assertEquals($page, $write_page);
    }

    public function testAmbiguousPageCollectionIsRejectedAtBothLayers(): void {
        $response = (object) [
            'batchcomplete' => true,
            'query' => (object) [
                'pages' => (object) [
                    '1' => $this->page('One', 1),
                    '2' => $this->page('Two', 2),
                ],
                'tokens' => (object) ['csrftoken' => 'token'],
            ],
        ];

        $this->assertNull(WikipediaBot::first_page_from_response($response));
        $this->assertNull(WikipediaBot::response2page($response));
    }

    public function testPageSelectionCanSucceedWhileWriteValidationRejectsBadToken(): void {
        $response = (object) [
            'batchcomplete' => true,
            'query' => (object) [
                'pages' => (object) ['123' => $this->page('Example', 123)],
                'tokens' => (object) ['csrftoken' => ['malformed']],
            ],
        ];

        $this->assertNotNull(WikipediaBot::first_page_from_response($response));
        $this->assertNull(WikipediaBot::response2page($response));
    }

    public function testPageDetailAndWriteParsersAgreeOnAmbiguousCollections(): void {
        $response = (object) [
            'curtimestamp' => '2026-08-29T12:00:00Z',
            'query' => (object) [
                'pages' => (object) [
                    '1' => $this->page('One', 1),
                    '2' => $this->page('Two', 2),
                ],
            ],
        ];

        $this->assertNull(page_details_from_api_response($response));
        $this->assertNull(WikipediaBot::first_page_from_response($response));
    }
}
