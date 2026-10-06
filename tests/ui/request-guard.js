import { expect } from '@playwright/test';

export function sanitizedUrl(value) {
  try {
    const url = new URL(value);
    return `${url.protocol}//${url.host}${url.pathname}`;
  } catch {
    return '<invalid URL>';
  }
}

export function expectedUiPaths(basePath) {
  const normalizedBase = basePath === '/' ? '' : basePath.replace(/\/$/, '');
  return {
    documents: new Set([
      normalizedBase === '' ? '/' : `${normalizedBase}/`,
      `${normalizedBase}/index.php`,
    ]),
    staticAssets: new Set([
      `${normalizedBase}/assets/index.js`,
      `${normalizedBase}/assets/results.css`,
      `${normalizedBase}/assets/spinner_18_18.gif`,
      `${normalizedBase}/assets/CiteBot.png`,
    ]),
  };
}

export async function installReadOnlyRequestGuard(page, { origin, basePath }) {
  const { documents, staticAssets } = expectedUiPaths(basePath);
  const unexpectedRequests = [];
  const badResponses = [];
  const failedExpectedRequests = [];
  const webSocketAttempts = [];
  const pageErrors = [];
  const consoleErrors = [];
  const dialogs = [];
  const popups = [];
  const downloads = [];

  function isAllowedGet(requestUrl, method) {
    if (method !== 'GET' || requestUrl.origin !== origin) return false;
    if (documents.has(requestUrl.pathname)) return requestUrl.search === '';
    // Static assets may legitimately gain cache-busting query strings later.
    return staticAssets.has(requestUrl.pathname);
  }

  await page.context().route('**/*', async (route) => {
    const request = route.request();
    const requestUrl = new URL(request.url());

    if (
      request.method() === 'GET' &&
      requestUrl.origin === origin &&
      requestUrl.pathname.endsWith('/favicon.ico')
    ) {
      // Browsers may probe for a favicon even though Citation Bot does not
      // declare one. Fulfill it locally so this harmless probe cannot create a
      // requestfailed/console error while still guaranteeing no server access.
      await route.fulfill({ status: 204, body: '' });
      return;
    }

    if (isAllowedGet(requestUrl, request.method())) {
      await route.continue();
      return;
    }

    unexpectedRequests.push(`${request.method()} ${sanitizedUrl(request.url())}`);
    await route.abort('blockedbyclient');
  });

  await page.context().routeWebSocket(/^wss?:\/\//, async (socket) => {
    webSocketAttempts.push(sanitizedUrl(socket.url()));
    await socket.close({ code: 1008, reason: 'Blocked by Citation Bot UI test harness' });
  });

  page.on('response', (response) => {
    const request = response.request();
    const responseUrl = new URL(response.url());
    if (isAllowedGet(responseUrl, request.method()) && response.status() >= 400) {
      badResponses.push(`${response.status()} ${responseUrl.pathname}`);
    }
  });

  page.on('requestfailed', (request) => {
    const requestUrl = new URL(request.url());
    if (isAllowedGet(requestUrl, request.method())) {
      const failure = request.failure()?.errorText ?? 'unknown failure';
      failedExpectedRequests.push(`${requestUrl.pathname}: ${failure}`);
    }
  });

  page.on('pageerror', (error) => {
    pageErrors.push(error instanceof Error ? error.stack ?? error.message : String(error));
  });

  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });

  page.on('dialog', async (dialog) => {
    dialogs.push(`${dialog.type()}: ${dialog.message()}`);
    await dialog.dismiss().catch(() => {});
  });

  page.on('popup', async (popup) => {
    popups.push(sanitizedUrl(popup.url()));
    await popup.close().catch(() => {});
  });

  page.on('download', (download) => {
    downloads.push(download.suggestedFilename());
  });

  return {
    assertClean() {
      const failures = [];
      if (unexpectedRequests.length > 0) {
        failures.push(`Unexpected browser requests were attempted:\n${unexpectedRequests.join('\n')}`);
      }
      if (webSocketAttempts.length > 0) {
        failures.push(`WebSocket connections were attempted:\n${webSocketAttempts.join('\n')}`);
      }
      if (badResponses.length > 0) {
        failures.push(`Expected UI resources returned HTTP errors:\n${badResponses.join('\n')}`);
      }
      if (failedExpectedRequests.length > 0) {
        failures.push(`Expected UI resources failed to load:\n${failedExpectedRequests.join('\n')}`);
      }
      if (pageErrors.length > 0) {
        failures.push(`Uncaught browser errors:\n${pageErrors.join('\n')}`);
      }
      if (consoleErrors.length > 0) {
        failures.push(`Unexpected console.error output:\n${consoleErrors.join('\n')}`);
      }
      if (dialogs.length > 0) {
        failures.push(`Unexpected browser dialogs appeared:\n${dialogs.join('\n')}`);
      }
      if (popups.length > 0) {
        failures.push(`Unexpected popup windows opened:\n${popups.join('\n')}`);
      }
      if (downloads.length > 0) {
        failures.push(`Unexpected downloads started:\n${downloads.join('\n')}`);
      }
      expect(failures, failures.join('\n\n')).toEqual([]);
    },
  };
}
