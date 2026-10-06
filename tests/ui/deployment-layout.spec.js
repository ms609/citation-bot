import { test, expect } from './read-only-fixture.js';

function baseInfo(testInfo) {
  const baseURL = String(testInfo.project.use.baseURL ?? '');
  const parsed = new URL(baseURL);
  return {
    baseURL,
    origin: parsed.origin,
    basePath: parsed.pathname.replace(/\/$/, ''),
  };
}

function expectedPath(basePath, suffix) {
  return `${basePath}${suffix}` || '/';
}

function parsePostedForm(request) {
  expect(request.method()).toBe('POST');
  expect(request.isNavigationRequest()).toBe(true);
  expect(request.resourceType()).toBe('document');
  expect(request.headers()['content-type'] ?? '').toContain('application/x-www-form-urlencoded');
  return new URLSearchParams(request.postData() ?? '');
}

test('deployment layout resolves assets/actions and preserves keyboard submission behavior', async ({ page }, testInfo) => {
  const { baseURL, origin, basePath } = baseInfo(testInfo);

  const response = await page.goto(baseURL);
  expect(response?.status()).toBe(200);
  expect(new URL(page.url()).origin).toBe(origin);
  expect(new URL(page.url()).pathname).toBe(basePath === '' ? '/' : `${basePath}/`);
  await expect(page).toHaveTitle('Citation Bot');
  await expect(page.getByRole('heading', { name: 'Wikipedia citation bot' })).toBeVisible();

  const stylesheet = page.locator('link[rel="stylesheet"]');
  const script = page.locator('script[src]');
  const spinner = page.locator('#PageSpinner');
  expect(new URL(await stylesheet.getAttribute('href'), baseURL).pathname).toBe(expectedPath(basePath, '/assets/results.css'));
  expect(new URL(await script.getAttribute('src'), baseURL).pathname).toBe(expectedPath(basePath, '/assets/index.js'));
  expect(new URL(await spinner.getAttribute('src'), baseURL).pathname).toBe(expectedPath(basePath, '/assets/spinner_18_18.gif'));

  expect(new URL(await page.locator('#botForm').getAttribute('action'), baseURL).pathname).toBe(expectedPath(basePath, '/process_page.php'));
  expect(new URL(await page.locator('#PageSubmit').getAttribute('formaction'), baseURL).pathname).toBe(expectedPath(basePath, '/process_page.php'));
  expect(new URL(await page.locator('#CatSubmit').getAttribute('formaction'), baseURL).pathname).toBe(expectedPath(basePath, '/category.php'));
  expect(new URL(await page.locator('#LinkedSubmit').getAttribute('formaction'), baseURL).pathname).toBe(expectedPath(basePath, '/linked_pages.php'));

  expect(await page.evaluate(() => typeof InitializeForm === 'function')).toBe(true);
  expect(await stylesheet.evaluate((link) => Boolean(link.sheet))).toBe(true);
  expect(await spinner.evaluate((image) => image.complete && image.naturalWidth > 0)).toBe(true);

  // Exercise a real native form navigation in every browser/layout, but
  // intercept it before PHP processing. This catches Enter-key/default-button
  // differences and verifies stale operation fields remain excluded.
  const target = `${origin}${expectedPath(basePath, '/category.php')}`;
  let submittedRequest;
  let submittedResolve;
  let submittedReject;
  const submitted = new Promise((resolve, reject) => {
    submittedResolve = resolve;
    submittedReject = reject;
  });
  const submittedTimer = setTimeout(
    () => submittedReject(new Error('Timed out waiting 3000ms for cross-browser category submission')),
    3000,
  );
  await page.route(target, async (route) => {
    try {
      submittedRequest = route.request();
      submittedResolve();
      await route.abort('blockedbyclient');
    } catch (error) {
      submittedReject(error);
      await route.abort('blockedbyclient').catch(() => {});
    }
  }, { times: 1 });

  await page.locator('#botPage').fill('Stale page value');
  await page.locator('#botCat').fill('Cross-browser category');
  await page.locator('#botCat').press('Enter');
  try {
    await submitted;
  } finally {
    clearTimeout(submittedTimer);
  }

  expect(new URL(submittedRequest.url()).origin).toBe(origin);
  expect(new URL(submittedRequest.url()).pathname).toBe(expectedPath(basePath, '/category.php'));
  const form = parsePostedForm(submittedRequest);
  expect(form.get('cat')).toBe('Cross-browser category');
  expect(form.has('page')).toBe(false);
  expect(form.has('linkpage')).toBe(false);

});
