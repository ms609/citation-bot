<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OfflineEndpointTest extends TestCase {
    private static string $root = '';
    private static string $origin;
    /** @var resource|null */
    private static $server = null;

    #[Override]
    public static function setUpBeforeClass(): void {
        if (!function_exists('proc_open') || !function_exists('uopz_set_return') || PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Offline HTTP tests require proc_open, uopz, and Unix filesystem support');
        }
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        self::assertIsString($address);
        fclose($socket);

        self::$root = sys_get_temp_dir() . '/citation-endpoints-' . bin2hex(random_bytes(8));
        self::$origin = 'http://' . $address;
        self::assertTrue(mkdir(self::$root, 0700));
        self::assertTrue(mkdir(self::$root . '/src', 0700));
        self::assertTrue(mkdir(self::$root . '/sessions', 0700));
        self::assertTrue(mkdir(self::$root . '/citation-bot-rate-limit', 0700));

        $repo = dirname(__DIR__, 2);
        self::assertTrue(symlink($repo . '/src/includes', self::$root . '/src/includes'));
        self::assertTrue(symlink($repo . '/vendor', self::$root . '/vendor'));
        self::assertNotFalse(file_put_contents(self::$root . '/env.php', "<?php\n"));
        foreach ([
            'authenticate.php', 'category.php', 'gadgetapi.php', 'generate_template.php',
            'gitpull.php', 'linked_pages.php', 'process_page.php', 'reset_big_run_state.php', 'resume.php',
        ] as $endpoint) {
            self::assertTrue(copy($repo . '/src/' . $endpoint, self::$root . '/src/' . $endpoint));
        }

        $log = self::$root . '/server.log';
        self::$server = proc_open( // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
            [
                PHP_BINARY, '-d', 'uopz.exit=1', '-d', 'display_errors=0', '-d', 'allow_url_fopen=0',
                '-d', 'session.save_path=' . self::$root . '/sessions',
                '-S', $address, $repo . '/tests/fixtures/offlineEndpointRouter.php',
            ],
            [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            self::$root,
            array_replace(array_intersect_key(getenv(), array_flip([
                'PATH', 'LD_LIBRARY_PATH', 'PHPRC', 'PHP_INI_SCAN_DIR',
            ])), [
                'PHP_RATE_LIMIT_DIRECTORY' => self::$root,
                'CITATION_BOT_TEST_ROOT' => self::$root,
                'PUBLIC_BASE_URL' => self::$origin . '/tools',
                'ALLOWED_HOSTS' => $address,
                'ALLOWED_ORIGINS' => 'https://en.wikipedia.org',
                'PHP_WP_OAUTH_CONSUMER' => 'fixture-consumer',
                'PHP_WP_OAUTH_SECRET' => 'fixture-secret',
                'DEPLOY_TOKEN' => str_repeat('a', 64),
            ]),
        );
        self::assertIsResource(self::$server);
        fclose($pipes[0]);

        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $ready = @file_get_contents(self::$origin . '/tools/health');
            if ($ready === 'endpoint fixture ready') {
                return;
            }
            usleep(20_000);
        }
        self::fail('Offline PHP server failed to start: ' . (string) file_get_contents($log));
    }

    #[Override]
    public static function tearDownAfterClass(): void {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        if (self::$root === '') {
            return;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::$root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir(self::$root);
    }

    /**
     * @param string $path
     * @param array<string, string>|null $post
     * @param list<string> $headers
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function request(string $path, ?array $post = null, array $headers = []): array {
        $context = stream_context_create(['http' => [
            'method' => $post === null ? 'GET' : 'POST',
            'header' => [...$headers, 'Content-Type: application/x-www-form-urlencoded'],
            'content' => $post === null ? '' : http_build_query($post),
            'follow_location' => 0,
            'ignore_errors' => true,
            'timeout' => 5,
        ]]);
        $body = file_get_contents(self::$origin . $path, false, $context);
        $this->assertIsString($body);
        $response = http_get_last_response_headers();
        if ($response === null) {
            throw new RuntimeException('Local endpoint returned no HTTP headers');
        }
        $this->assertMatchesRegularExpression('~^HTTP/\S+ [0-9]{3} ~', $response[0]);
        $received = [];
        foreach (array_slice($response, 1) as $line) {
            [$name, $value] = explode(':', $line, 2);
            $received[mb_strtolower($name)] = mb_trim($value);
        }
        return ['status' => (int) explode(' ', $response[0])[1], 'headers' => $received, 'body' => $body];
    }

    /** @return array{id: string, resume: string} */
    private function seedSession(string $query = ''): array {
        $response = $this->request('/tools/seed-session' . $query);
        $this->assertSame(200, $response['status']);
        $session = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($session);
        $this->assertIsString($session['id']);
        $this->assertIsString($session['resume']);
        return ['id' => $session['id'], 'resume' => $session['resume']];
    }

    public function testOAuthCallbackRotatesSessionAndResumesOnceUnderSubdirectory(): void {
        ['id' => $old_session, 'resume' => $resume] = $this->seedSession();
        $callback = $this->request(
            '/tools/authenticate.php?' . http_build_query(['oauth_verifier' => 'accepted', 'return' => $resume]),
            null,
            ['Cookie: PHPSESSID=' . $old_session],
        );
        $this->assertSame(302, $callback['status']);
        $this->assertSame($resume, $callback['headers']['location']);
        $this->assertStringStartsWith('/tools/resume.php?request=', $resume);
        $this->assertMatchesRegularExpression('~^PHPSESSID=[^;]+;~', $callback['headers']['set-cookie']);
        $new_cookie = explode(';', $callback['headers']['set-cookie'])[0];
        $this->assertNotSame('PHPSESSID=' . $old_session, $new_cookie);

        $this->assertSame(400, $this->request($resume, null, ['Cookie: PHPSESSID=' . $old_session])['status']);
        $confirmation = $this->request($resume, null, ['Cookie: ' . $new_cookie]);
        $this->assertSame(200, $confirmation['status']);
        $this->assertStringContainsString('<form action="process_page.php" method="post">', $confirmation['body']);
        $this->assertStringContainsString('Example &lt;page&gt;', $confirmation['body']);
        $this->assertMatchesRegularExpression('~name="csrf_token" value="[a-f0-9]{64}"~', $confirmation['body']);
        $this->assertSame(400, $this->request($resume, null, ['Cookie: ' . $new_cookie])['status']);
    }

    public function testResumeRejectsExpiredRequestAndFailedOAuthExchange(): void {
        $expired = $this->seedSession('?authorized=1&expired=1');
        $this->assertSame(400, $this->request($expired['resume'], null, ['Cookie: PHPSESSID=' . $expired['id']])['status']);

        ['id' => $id, 'resume' => $resume] = $this->seedSession();
        $cookie = ['Cookie: PHPSESSID=' . $id];
        $callback = $this->request(
            '/tools/authenticate.php?' . http_build_query(['oauth_verifier' => 'denied', 'return' => $resume]),
            null,
            $cookie,
        );
        $this->assertArrayNotHasKey('location', $callback['headers']);
        $this->assertStringContainsString('Incoming authorization tokens did not work', $callback['body']);
        $this->assertSame(400, $this->request($resume, null, $cookie)['status']);
    }

    public function testFailedIdentityCheckSavesPostForReauthentication(): void {
        $session = $this->seedSession('?stale=1');
        $cookie = ['Cookie: PHPSESSID=' . $session['id']];
        $response = $this->request('/tools/process_page.php', [
            'page' => 'Another example',
            'csrf_token' => 'csrf-before-oauth',
        ], $cookie);

        $this->assertSame(302, $response['status']);
        $location = $response['headers']['location'];
        $this->assertSame('/tools/authenticate.php', parse_url($location, PHP_URL_PATH));
        $query = parse_url($location, PHP_URL_QUERY);
        $this->assertIsString($query);
        parse_str($query, $parameters);
        $this->assertIsString($parameters['return']);
        $this->assertStringStartsWith('/tools/resume.php?request=', $parameters['return']);
        $this->assertNotSame($session['resume'], $parameters['return']);
        $this->assertSame(400, $this->request($parameters['return'], null, $cookie)['status']);
    }

    #[DataProvider('editEndpoints')]
    public function testInvalidCsrfStopsBeforeAuthenticatingOrDiscovering(string $endpoint, string $field): void {
        $marker = self::$root . '/user-check';
        if (is_file($marker)) {
            unlink($marker);
        }
        $session = $this->seedSession('?authorized=1');
        $response = $this->request('/tools/' . $endpoint, [
            $field => 'User:Fixture/Example',
            'csrf_token' => 'invalid',
        ], ['Cookie: PHPSESSID=' . $session['id']]);

        $this->assertSame(403, $response['status']);
        $this->assertStringContainsString('CSRF token', $response['body']);
        $this->assertFileDoesNotExist($marker);
    }

    /** @return array<string, array{string, string}> */
    public static function editEndpoints(): array {
        return [
            'single page' => ['process_page.php', 'page'],
            'category' => ['category.php', 'cat'],
            'linked pages' => ['linked_pages.php', 'linkpage'],
        ];
    }

    public function testLinkedPageContinuationFailureReleasesLeaseBeforeRendering(): void {
        $session = $this->seedSession('?authorized=1');
        $response = $this->request('/tools/linked_pages.php', [
            'linkpage' => 'User:Fixture/Example',
            'csrf_token' => 'csrf-before-oauth',
        ], ['Cookie: PHPSESSID=' . $session['id']]);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Error getting page list', $response['body']);
        $this->assertStringNotContainsString('Done with page', $response['body']);
        $discovery = $this->readEntries(self::$root . '/discovery-state');
        $this->assertCount(1, $discovery);
        $this->assertSame('probe', array_values($discovery)[0]['phase']);
        $this->assertSame([], $this->readEntries(self::$root . '/state-after-discovery'));
    }

    /** @return array<string, array{phase: string}> */
    private function readEntries(string $path): array {
        $state = file_get_contents($path);
        $this->assertIsString($state);
        $decoded = json_decode($state, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertIsArray($decoded['entries']);
        return $decoded['entries'];
    }

    public function testUnauthorizedAdministrationLeavesStateUntouched(): void {
        $directory = self::$root . '/citation-bot-rate-limit';
        $state = $directory . '/big-run.json';
        $before = '{"tokens":10,"updated":0,"entries":{}}';
        $this->assertNotFalse(file_put_contents($state, $before));
        $headers = ['X-Deploy-Token: ' . str_repeat('b', 64)];

        $deploy = $this->request('/tools/gitpull.php', [], $headers);
        $this->assertSame(403, $deploy['status']);
        $this->assertStringContainsString('Deployment authorization failed', $deploy['body']);
        $reset = $this->request('/tools/reset_big_run_state.php', ['action' => 'reset', 'confirm_reset' => '1'], $headers);
        $this->assertSame(403, $reset['status']);
        $this->assertStringContainsString('Deployment authorization failed', $reset['body']);
        $this->assertSame($before, file_get_contents($state));
        $this->assertSame([], glob($state . '.recovery-*'));
        unlink($state);
    }

    public function testDisallowedHostReturnsBadRequestBeforeHandlingEndpoint(): void {
        $response = $this->request('/tools/authenticate.php', null, ['Host: unexpected.example']);
        $this->assertSame(400, $response['status']);
        $this->assertStringContainsString('Invalid request host or public URL configuration', $response['body']);
        $this->assertArrayNotHasKey('location', $response['headers']);
    }

    public function testThrottledEndpointsReturnRetryAfterBeforeExpansion(): void {
        $directory = self::$root . '/citation-bot-rate-limit';
        $gadget = fopen($directory . '/gadgetapi.json', 'c+');
        $generator = fopen($directory . '/generate-template.json', 'c+');
        $this->assertIsResource($gadget);
        $this->assertIsResource($generator);
        try {
            $this->assertTrue(flock($gadget, LOCK_EX | LOCK_NB));
            $this->assertTrue(flock($generator, LOCK_EX | LOCK_NB));
            $json = $this->request('/tools/gadgetapi.php', [
                'text' => '{{cite journal|title=An example citation}}',
                'summary' => '',
            ], ['Origin: https://en.wikipedia.org']);
            $this->assertSame(429, $json['status']);
            $this->assertSame('1', $json['headers']['retry-after']);
            $this->assertSame('no-store', $json['headers']['cache-control']);
            $this->assertStringContainsString('rate_limited', $json['body']);

            $html = $this->request('/tools/generate_template.php?jstor=373737');
            $this->assertSame(429, $html['status']);
            $this->assertSame('1', $html['headers']['retry-after']);
            $this->assertSame('no-store', $html['headers']['cache-control']);
            $this->assertStringContainsString('Too many requests', $html['body']);
            $this->assertStringContainsString('Terms&nbsp;of&nbsp;Use</a>', $html['body']);
            $this->assertStringContainsString('Privacy&nbsp;Policy</a></footer></body></html>', $html['body']);
        } finally {
            fclose($generator);
            fclose($gadget);
        }
    }
}
