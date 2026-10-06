import { test, expect } from './read-only-fixture.js';

const scenarios = [
  { name: 'page', input: '#botPage', button: '#PageSubmit', endpoint: 'process_page.php', field: 'page', value: 'Ada Lovelace' },
  { name: 'category', input: '#botCat', button: '#CatSubmit', endpoint: 'category.php', field: 'cat', value: 'Scientists' },
  { name: 'linked pages', input: '#botLinked', button: '#LinkedSubmit', endpoint: 'linked_pages.php', field: 'linkpage', value: 'User:Example/List' },
];

for (const scenario of scenarios) {
  test(`explicit ${scenario.name} button remains usable without JavaScript`, async ({ page }, testInfo) => {
    const baseURL = String(testInfo.project.use.baseURL ?? '');
    const parsed = new URL(baseURL);
    const basePath = parsed.pathname.replace(/\/$/, '');

    const response = await page.goto(baseURL);
    expect(response?.status()).toBe(200);
    await expect(page).toHaveTitle('Citation Bot');
    await expect(page.getByLabel('Single page:')).toBeVisible();
    await expect(page.getByLabel('Category:')).toBeVisible();
    await expect(page.getByLabel('Linked pages:')).toBeVisible();
    await expect(page.getByLabel('Wiki to run on:')).toBeVisible();
    await expect(page.getByLabel(/Thorough mode/)).toBeChecked();

    const action = await page.locator(scenario.button).getAttribute('formaction');
    expect(new URL(action, baseURL).pathname).toBe(`${basePath}/${scenario.endpoint}`);

    let capturedRequest;
    let requestResolve;
    let requestReject;
    const requestSeen = new Promise((resolve, reject) => {
      requestResolve = resolve;
      requestReject = reject;
    });
    const requestTimer = setTimeout(
      () => requestReject(new Error(`Timed out waiting 3000ms for no-JavaScript ${scenario.name} submission`)),
      3000,
    );
    await page.route(`${parsed.origin}${basePath}/${scenario.endpoint}`, async (route) => {
      try {
        capturedRequest = route.request();
        requestResolve();
        await route.abort('blockedbyclient');
      } catch (error) {
        requestReject(error);
        await route.abort('blockedbyclient').catch(() => {});
      }
    }, { times: 1 });

    await page.locator(scenario.input).fill(scenario.value);
    await page.locator(scenario.button).click({ noWaitAfter: true });
    try {
      await requestSeen;
    } finally {
      clearTimeout(requestTimer);
    }

    expect(capturedRequest.method()).toBe('POST');
    expect(capturedRequest.isNavigationRequest()).toBe(true);
    expect(capturedRequest.headers()['content-type'] ?? '').toContain('application/x-www-form-urlencoded');
    const form = new URLSearchParams(capturedRequest.postData() ?? '');
    expect(form.get(scenario.field)).toBe(scenario.value);
    expect(form.get('edit')).toBe('webform');
    expect(form.get('wiki_base')).toBe('en');
    expect(form.get('slow')).toBe('on');
    expect(form.get('csrf_token')).toMatch(/^[0-9a-f]{64}$/);

  });
}
