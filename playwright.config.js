import { defineConfig, devices } from '@playwright/test';

const srcOrigin = 'http://127.0.0.1:8080';
const srcBaseURL = `${srcOrigin}/src/`;
const rootOrigin = 'http://127.0.0.1:8082';
const rootBaseURL = `${rootOrigin}/`;
function normalizedExternalBaseURL(value) {
  if (!value) return undefined;
  const parsed = new URL(value);
  if (!['http:', 'https:'].includes(parsed.protocol) || parsed.username || parsed.password || parsed.search || parsed.hash) {
    throw new Error('UI_BASE_URL must be an HTTP(S) URL without credentials, query, or fragment');
  }
  return value.endsWith('/') ? value : `${value}/`;
}

const externalBaseURL = normalizedExternalBaseURL(process.env.UI_BASE_URL);
const crossBrowserOnly = process.env.UI_CROSS_BROWSER === '1';
const productionCanary = externalBaseURL ? new URL(externalBaseURL).origin === 'https://citations.toolforge.org' : false;

function localPhpServer({ origin, documentRoot, publicBaseURL, coverage, logName }) {
  const parsed = new URL(origin);
  const coverageOptions = coverage
    ? '-d xdebug.mode=coverage -d auto_prepend_file=tests/ui/php-coverage-prepend.php '
    : '-d xdebug.mode=off ';
  const command = [
    'mkdir -p test-results coverage/php/raw',
    '&& exec php',
    coverageOptions,
    '-d allow_url_fopen=0',
    '-d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,socket_sendto,socket_sendmsg',
    `-S ${parsed.hostname}:${parsed.port}`,
    `-t ${documentRoot}`,
    `>>test-results/${logName} 2>&1`,
  ].join(' ');

  return {
    command: `bash -lc '${command}'`,
    url: publicBaseURL.endsWith('/') ? publicBaseURL : `${publicBaseURL}/`,
    reuseExistingServer: false,
    timeout: 15_000,
    env: {
      ...process.env,
      CI: '0',
      PUBLIC_BASE_URL: publicBaseURL.replace(/\/$/, ''),
      ALLOWED_HOSTS: parsed.host,
      ALLOWED_ORIGINS: origin,
      UI_PHP_COVERAGE_DIR: coverage ? 'coverage/php/raw' : '',
    },
  };
}

let projects;
let webServer;

if (externalBaseURL) {
  projects = [
    {
      name: 'external',
      testMatch: /external-smoke\.spec\.js/,
      retries: productionCanary && process.env.CI ? 2 : 0,
      use: { ...devices['Desktop Chrome'], baseURL: externalBaseURL },
    },
  ];
  webServer = undefined;
} else if (crossBrowserOnly) {
  projects = [
    {
      name: 'firefox',
      testMatch: /deployment-layout\.spec\.js/,
      use: { ...devices['Desktop Firefox'], baseURL: srcBaseURL },
    },
    {
      name: 'webkit',
      testMatch: /deployment-layout\.spec\.js/,
      use: { ...devices['Desktop Safari'], baseURL: srcBaseURL },
    },
  ];
  webServer = localPhpServer({
    origin: srcOrigin,
    documentRoot: '.',
    publicBaseURL: `${srcOrigin}/src`,
    coverage: false,
    logName: 'php-server-cross-browser.log',
  });
} else {
  projects = [
    {
      name: 'chromium',
      testMatch: /citation-bot\.spec\.js/,
      use: { ...devices['Desktop Chrome'], baseURL: srcBaseURL },
    },
    {
      name: 'chromium-root',
      testMatch: /deployment-layout\.spec\.js/,
      use: { ...devices['Desktop Chrome'], baseURL: rootBaseURL },
    },
    {
      name: 'chromium-nojs',
      testMatch: /no-js\.spec\.js/,
      use: { ...devices['Desktop Chrome'], baseURL: srcBaseURL, javaScriptEnabled: false },
    },
  ];
  webServer = [
    localPhpServer({
      origin: srcOrigin,
      documentRoot: '.',
      publicBaseURL: `${srcOrigin}/src`,
      coverage: true,
      logName: 'php-server-src.log',
    }),
    localPhpServer({
      origin: rootOrigin,
      documentRoot: 'src',
      publicBaseURL: rootOrigin,
      coverage: false,
      logName: 'php-server-root.log',
    }),
  ];
}

export default defineConfig({
  testDir: './tests/ui',
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: 0,
  // PHP's built-in server is single-threaded; keep browser tests serial locally
  // as well as in CI so timing and coverage aggregation stay deterministic.
  workers: 1,
  reporter: process.env.CI
    ? [
        ['github'],
        ['html', { open: 'never', outputFolder: 'playwright-report' }],
      ]
    : 'list',
  timeout: 15_000,
  expect: { timeout: 5_000 },
  outputDir: 'test-results/artifacts',
  use: {
    serviceWorkers: 'block',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects,
  webServer,
});
