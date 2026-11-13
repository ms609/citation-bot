<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

final class LocalizedWikiNamespaceTest extends testBaseClass {
    #[PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testNamespacesAreResolvedByTheSelectedWiki(): void {
        $category_requests = [];
        $this->assertTrue(uopz_set_return(WikipediaBot::class, 'query_api', static function (array $params) use (&$category_requests): string {
            if (isset($params['meta'])) {
                return '{"query":{"namespaces":{"14":{"*":"Категория"}},"namespacealiases":[{"id":14,"*":"Catégorie"},{"id":2,"*":"Участник"}]}}';
            }
            if (isset($params['cmtitle'])) {
                $category_requests[] = $params['cmtitle'];
                return '{"query":{"categorymembers":[]}}';
            }
            return match ($params['titles']) {
                'Участник:Иван' => '{"query":{"pages":{"1":{"ns":2}}}}',
                'Обсуждение участника:Иван' => '{"query":{"pages":{"1":{"ns":3}}}}',
                default => '{"error":{"code":"badtitle"}}',
            };
        }, true));
        try {
            $this->assertTrue(WikipediaBot::title_is_in_user_namespace('Участник:Иван'));
            $this->assertFalse(WikipediaBot::title_is_in_user_namespace('Обсуждение участника:Иван'));
            $this->assertFalse(WikipediaBot::title_is_in_user_namespace('User:A|User:B'));
            $this->assertFalse(WikipediaBot::title_is_in_user_namespace('Unknown'));
            $this->assertSame('Физика', WikipediaBot::bare_category_name('категория:Физика'));
            $this->assertSame('Physique', WikipediaBot::bare_category_name("Cate\u{0301}gorie:Physique"));
            $this->assertSame('CS1 errors: DOI', WikipediaBot::bare_category_name('CS1 errors: DOI'));
            $this->assertSame([], WikipediaBot::category_members_bounded('Категория:Физика', 10));
            $this->assertSame([], WikipediaBot::category_members('Catégorie:Physique'));
            $this->assertSame([], WikipediaBot::category_members_bounded('Категория:Категория:Физика', 10));
            $this->assertSame(['Category:Физика', 'Category:Physique', 'Category:Категория:Физика'], $category_requests);
        } finally {
            $this->assertTrue(uopz_unset_return(WikipediaBot::class, 'query_api'));
        }
    }
}
