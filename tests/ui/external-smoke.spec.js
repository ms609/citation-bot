import { test, expect } from './read-only-fixture.js';

test('read-only deployed UI canary validates assets, session security, and client-side behavior', async ({ page }, testInfo) => {
  const baseURL = String(testInfo.project.use.baseURL ?? '');
  if (!baseURL) throw new Error('UI_BASE_URL is required for the external smoke test');
  const parsedBase = new URL(baseURL);
  const basePath = parsedBase.pathname.replace(/\/$/, '');

  const response = await page.goto(baseURL);
  expect(response?.status()).toBe(200);
  expect(new URL(page.url()).origin).toBe(parsedBase.origin);
  expect(new URL(page.url()).pathname).toBe(basePath === '' ? '/' : `${basePath}/`);
  await expect(page).toHaveTitle('Citation Bot');
  await expect(page.getByRole('heading', { name: 'Wikipedia citation bot' })).toBeVisible();
  await expect(page.getByLabel('Single page:')).toBeVisible();
  expect(await page.evaluate(() => typeof InitializeForm === 'function')).toBe(true);
  expect(await page.locator('link[rel="stylesheet"]').evaluate((link) => Boolean(link.sheet))).toBe(true);
  expect(await page.locator('#PageSpinner').evaluate((image) => image.complete && image.naturalWidth > 0)).toBe(true);

  const firstToken = await page.locator('input[name="csrf_token"]').inputValue();
  expect(firstToken).toMatch(/^[0-9a-f]{64}$/);

  const cookies = await page.context().cookies(baseURL);
  const sessionCandidates = cookies.filter((cookie) => cookie.httpOnly && cookie.sameSite === 'Lax');
  expect(sessionCandidates.length).toBeGreaterThan(0);
  expect(sessionCandidates.some((cookie) => cookie.secure === (parsedBase.protocol === 'https:'))).toBe(true);

  // Exercise validation and dynamic button text only. These interactions must
  // not generate any network request; the read-only request guard would block
  // and report one if the deployed JavaScript regressed.
  await page.locator('#PageSubmit').click();
  await expect(page.locator('#botPage-error')).toHaveText('Page name is required');
  await page.locator('#botPage').fill('Canary one|Canary two');
  await expect(page.locator('#botPage-error')).toHaveCount(0);
  await expect(page.locator('#PageSubmit')).toHaveText('Process pages');

  await page.locator('#CatSubmit').click();
  await expect(page.locator('#botCat-error')).toHaveText('Category name is required');
  await page.locator('#botCat').fill('Canary category');
  await expect(page.locator('#botCat-error')).toHaveCount(0);

  await page.locator('#LinkedSubmit').click();
  await expect(page.locator('#botLinked-error')).toHaveText('Initial page name is required');
  await page.locator('#botLinked').fill('User:Canary/List');
  await expect(page.locator('#botLinked-error')).toHaveCount(0);

  await page.reload({ waitUntil: 'domcontentloaded' });
  const secondToken = await page.locator('input[name="csrf_token"]').inputValue();
  expect(secondToken).toBe(firstToken);

});
