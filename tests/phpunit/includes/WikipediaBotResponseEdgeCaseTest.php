<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 2) . '/testBaseClass.php';

/**
 * Network-free edge cases for the helpers that classify and unpack MediaWiki
 * API responses. Every response here is a hand-built fixture.
 */
final class WikipediaBotResponseEdgeCaseTest extends PHPUnit\Framework\TestCase {

    private static function error(mixed $code, mixed $info): stdClass {
        return (object) ['error' => (object) ['code' => $code, 'info' => $info]];
    }

    // ---- retry classification --------------------------------------------------

    /** @return array<string, array{mixed, bool}> */
    public static function retryProvider(): array {
        return [
            'null response' => [null, true],
            'string response' => ['<html>gateway timeout</html>', true],
            'array response' => [['error' => 'x'], true],
            'false response' => [false, true],
            'successful response' => [(object) ['query' => (object) []], false],
            'empty object' => [(object) [], false],
            'error that is not an object' => [(object) ['error' => 'boom'], true],
            'error code that is not scalar' => [self::error([], 'info'), true],
            'error info that is not scalar' => [self::error('code', (object) []), true],
            'maxlag' => [self::error('maxlag', 'Waiting for a database server: 5 seconds lagged'), true],
            'ratelimited' => [self::error('ratelimited', 'You are doing that too often'), true],
            'readonly' => [self::error('readonly', 'The wiki is currently in read-only mode'), true],
            'locked database named in the text' => [self::error('internal_api_error_DBQueryError', 'The database has been automatically locked while the replica catches up'), true],
            'nonce reuse' => [self::error('mwoauth-invalid-authorization', 'Nonce already used: abc'), true],
            'blocked user is permanent' => [self::error('blocked', 'You have been blocked from editing'), false],
            'protected page is permanent' => [self::error('protectedpage', 'This page has been protected'), false],
            'edit conflict is permanent' => [self::error('editconflict', 'Edit conflict detected'), false],
            'numeric code' => [self::error(503, 'Service unavailable'), false],
            'missing code and info' => [(object) ['error' => (object) []], false],
        ];
    }

    #[DataProvider('retryProvider')]
    public function testResponsesAreRetriedOnlyWhenTheFailureIsTransient(mixed $response, bool $expected): void {
        $this->assertSame($expected, WikipediaBot::fetch_response_is_retryable($response));
    }

    // ---- error field extraction ------------------------------------------------

    /** @return array<string, array{mixed, array{string, string}|null}> */
    public static function errorFieldProvider(): array {
        return [
            'not an object' => ['text', null],
            'null' => [null, null],
            'array' => [['code' => 'x'], null],
            'both strings' => [(object) ['code' => 'c', 'info' => 'i'], ['c', 'i']],
            'missing fields default to empty strings' => [(object) [], ['', '']],
            'numbers are cast' => [(object) ['code' => 12, 'info' => 1.5], ['12', '1.5']],
            'booleans are cast' => [(object) ['code' => true, 'info' => false], ['1', '']],
            'null fields count as empty' => [(object) ['code' => null, 'info' => null], ['', '']],
            'array code' => [(object) ['code' => ['x'], 'info' => 'i'], null],
            'object info' => [(object) ['code' => 'c', 'info' => (object) []], null],
        ];
    }

    /**
     * @param mixed $error
     * @param array{string, string}|null $expected
     */
    #[DataProvider('errorFieldProvider')]
    public function testErrorFieldExtraction(mixed $error, ?array $expected): void {
        $this->assertSame($expected, WikipediaBot::mediawiki_error_fields($error));
    }

    // ---- page extraction -------------------------------------------------------

    /** @return array<string, array{mixed}> */
    public static function unusablePageResponseProvider(): array {
        return [
            'not an object' => ['x'],
            'no query' => [(object) []],
            'query is not an object' => [(object) ['query' => 'x']],
            'no pages' => [(object) ['query' => (object) []]],
            'pages is a list' => [(object) ['query' => (object) ['pages' => []]]],
            'two pages' => [(object) ['query' => (object) ['pages' => (object) ['1' => (object) ['title' => 'A'], '2' => (object) ['title' => 'B']]]]],
            'no pages at all' => [(object) ['query' => (object) ['pages' => (object) []]]],
            'page is not an object' => [(object) ['query' => (object) ['pages' => (object) ['1' => 'A']]]],
        ];
    }

    #[DataProvider('unusablePageResponseProvider')]
    public function testFirstPageRejectsUnusableResponses(mixed $response): void {
        $this->assertNull(WikipediaBot::first_page_from_response($response));
    }

    public function testFirstPageIsReturnedAsAPlainObject(): void {
        $page = WikipediaBot::first_page_from_response(
            (object) ['query' => (object) ['pages' => (object) ['-1' => (object) ['title' => 'Missing', 'missing' => '']]]]
        );
        $this->assertInstanceOf(stdClass::class, $page);
        $this->assertSame('Missing', $page->title);
        $this->assertTrue(isset($page->missing));
    }

    // ---- redirect extraction ---------------------------------------------------

    /** @return array<string, array{mixed}> */
    public static function noRedirectProvider(): array {
        $with = static fn (mixed $redirects): stdClass => (object) ['query' => (object) ['redirects' => $redirects]];
        return [
            'not an object' => [null],
            'no query' => [(object) []],
            'query is not an object' => [(object) ['query' => []]],
            'no redirects' => [(object) ['query' => (object) []]],
            'redirects is an object' => [$with((object) [])],
            'empty redirect list' => [$with([])],
            'first redirect is a string' => [$with(['Target'])],
            'first redirect has no target' => [$with([(object) ['from' => 'A']])],
            'target is not a string' => [$with([(object) ['to' => 12]])],
            'target is empty' => [$with([(object) ['to' => '']])],
        ];
    }

    #[DataProvider('noRedirectProvider')]
    public function testRedirectTargetRejectsUnusableResponses(mixed $response): void {
        $this->assertNull(WikipediaBot::redirect_target_from_response($response));
    }

    public function testRedirectTargetUsesTheFirstRedirect(): void {
        $response = (object) ['query' => (object) ['redirects' => [
            (object) ['from' => 'A', 'to' => 'First target'],
            (object) ['from' => 'B', 'to' => 'Second target'],
        ]]];
        $this->assertSame('First target', WikipediaBot::redirect_target_from_response($response));
    }

    // ---- category members ------------------------------------------------------

    private static function members(mixed ...$members): stdClass {
        return (object) ['query' => (object) ['categorymembers' => $members]];
    }

    /** @return array<string, array{mixed}> */
    public static function badCategoryResponseProvider(): array {
        return [
            'not an object' => [''],
            'no query' => [(object) []],
            'query is not an object' => [(object) ['query' => 1]],
            'no members' => [(object) ['query' => (object) []]],
            'members is an object' => [(object) ['query' => (object) ['categorymembers' => (object) []]]],
        ];
    }

    #[DataProvider('badCategoryResponseProvider')]
    public function testCategoryResponseWithoutMembersIsAnError(mixed $response): void {
        $this->assertNull(WikipediaBot::category_member_titles_from_response($response));
    }

    public function testEmptyCategoryIsNotAnError(): void {
        $this->assertSame([], WikipediaBot::category_member_titles_from_response(self::members()));
    }

    public function testCategoryMembersAreFilteredByNamespaceAndShape(): void {
        $response = self::members(
            (object) ['ns' => 0, 'title' => 'Article'],
            (object) ['ns' => 118, 'title' => 'Draft:Wanted'],
            (object) ['ns' => 1, 'title' => 'Talk:Article'],
            (object) ['ns' => 2, 'title' => 'User:Someone'],
            (object) ['ns' => 4, 'title' => 'Wikipedia:Project'],
            (object) ['ns' => 10, 'title' => 'Template:Cite'],
            (object) ['ns' => 14, 'title' => 'Category:Child'],
            (object) ['ns' => 828, 'title' => 'Module:Lua'],
            (object) ['ns' => 0, 'title' => 'Article/doc'],
            (object) ['ns' => 0, 'title' => 'Article/DOC'],
            (object) ['ns' => -1, 'title' => 'Special:Page'],
            (object) ['ns' => 0, 'title' => ''],
            (object) ['ns' => '0', 'title' => 'Stringly typed namespace'],
            (object) ['ns' => 0, 'title' => 12],
            (object) ['ns' => 0],
            (object) ['title' => 'No namespace'],
            'not an object',
            null,
            (object) ['ns' => 0, 'title' => 'Another article'],
        );
        $this->assertSame(
            ['Article', 'Draft:Wanted', 'User:Someone', 'Another article'],
            WikipediaBot::category_member_titles_from_response($response)
        );
    }
}
