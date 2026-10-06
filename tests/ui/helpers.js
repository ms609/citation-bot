import { expect } from '@playwright/test';

export const LOCAL_ORIGIN = 'http://127.0.0.1:8080';
export const BASE_PATH = '/src';
export const MOCK_RESPONSE = '<!doctype html><html><body><main><h1>UI harness response</h1></main></body></html>';

const SUBMISSION_WAIT_MS = 3000;

function deferredSubmission(endpoint) {
  let resolvePromise;
  let rejectPromise;
  let settled = false;
  let timer;
  const promise = new Promise((resolve, reject) => {
    resolvePromise = resolve;
    rejectPromise = reject;
    timer = setTimeout(() => {
      if (settled) return;
      settled = true;
      reject(new Error(`Timed out waiting ${SUBMISSION_WAIT_MS}ms for browser submission to ${endpoint}`));
    }, SUBMISSION_WAIT_MS);
  });

  return {
    promise,
    resolve(value) {
      if (settled) return;
      settled = true;
      clearTimeout(timer);
      resolvePromise(value);
    },
    reject(error) {
      if (settled) return;
      settled = true;
      clearTimeout(timer);
      rejectPromise(error instanceof Error ? error : new Error(String(error)));
    },
  };
}

function endpointUrl(endpoint) {
  return `${LOCAL_ORIGIN}${BASE_PATH}/${endpoint}`;
}

export function assertLocalEndpointRequest(request, endpoint) {
  const url = new URL(request.url());
  expect(url.origin).toBe(LOCAL_ORIGIN);
  expect(url.pathname).toBe(`${BASE_PATH}/${endpoint}`);
  expect(url.search).toBe('');
  expect(request.isNavigationRequest()).toBe(true);
  expect(request.resourceType()).toBe('document');
  expect(request.redirectedFrom()).toBeNull();
}

export function parseForm(request) {
  expect(request.method()).toBe('POST');
  const contentType = request.headers()['content-type'] ?? '';
  expect(contentType).toContain('application/x-www-form-urlencoded');
  const data = request.postData();
  expect(data).not.toBeNull();
  return new URLSearchParams(data ?? '');
}

export async function captureSubmission(page, endpoint) {
  let capturedRequest;
  const seen = deferredSubmission(endpoint);

  await page.route(endpointUrl(endpoint), async (route) => {
    try {
      capturedRequest = route.request();
      assertLocalEndpointRequest(capturedRequest, endpoint);
      seen.resolve(capturedRequest);
      // Abort rather than navigate so V8 coverage remains on the Citation Bot
      // document and no synthetic response can obscure the tested UI state.
      await route.abort('blockedbyclient');
    } catch (error) {
      seen.reject(error);
      await route.abort('blockedbyclient').catch(() => {});
    }
  }, { times: 1 });

  return {
    requestSeen: seen.promise,
    request: () => {
      expect(capturedRequest, `Expected a local request to ${endpoint}`).toBeTruthy();
      return capturedRequest;
    },
  };
}

export async function fulfillSubmission(page, endpoint) {
  let capturedRequest;
  const seen = deferredSubmission(endpoint);

  await page.route(endpointUrl(endpoint), async (route) => {
    try {
      capturedRequest = route.request();
      assertLocalEndpointRequest(capturedRequest, endpoint);
      seen.resolve(capturedRequest);
      await route.fulfill({
        status: 200,
        contentType: 'text/html; charset=utf-8',
        body: MOCK_RESPONSE,
      });
    } catch (error) {
      seen.reject(error);
      await route.abort('blockedbyclient').catch(() => {});
    }
  }, { times: 1 });

  return {
    requestSeen: seen.promise,
    request: () => {
      expect(capturedRequest, `Expected a local request to ${endpoint}`).toBeTruthy();
      return capturedRequest;
    },
  };
}

export async function holdSubmission(page, endpoint) {
  let capturedRequest;
  let releaseResolve;
  const seen = deferredSubmission(endpoint);
  const released = new Promise((resolve) => {
    releaseResolve = resolve;
  });

  await page.route(endpointUrl(endpoint), async (route) => {
    try {
      capturedRequest = route.request();
      assertLocalEndpointRequest(capturedRequest, endpoint);
      seen.resolve(capturedRequest);
      await released;
      await route.abort('blockedbyclient');
    } catch (error) {
      seen.reject(error);
      await route.abort('blockedbyclient').catch(() => {});
    }
  }, { times: 1 });

  return {
    requestSeen: seen.promise,
    request: () => capturedRequest,
    release: () => releaseResolve(),
  };
}

export async function openHome(page) {
  const response = await page.goto('./');
  expect(response).not.toBeNull();
  expect(response?.status()).toBe(200);
  await expect(page).toHaveURL(`${LOCAL_ORIGIN}${BASE_PATH}/`);
  await expect(page).toHaveTitle('Citation Bot');
}
