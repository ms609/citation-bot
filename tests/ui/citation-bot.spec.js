import { test, expect } from './coverage-fixture.js';
import AxeBuilder from '@axe-core/playwright';
import { readFileSync } from 'node:fs';
import {
  captureSubmission,
  fulfillSubmission,
  openHome,
  parseForm,
} from './helpers.js';

const submissionCases = [
  {
    name: 'single page',
    input: '#botPage',
    value: 'François & 東京=研究|Другая страница',
    button: '#PageSubmit',
    endpoint: 'process_page.php',
    field: 'page',
    wiki: 'simple',
    slow: true,
    spinner: '#PageSpinner',
    inactiveFields: ['cat', 'linkpage'],
  },
  {
    name: 'category',
    input: '#botCat',
    value: 'Category:Physique & société=科学',
    button: '#CatSubmit',
    endpoint: 'category.php',
    field: 'cat',
    wiki: 'ru',
    slow: false,
    spinner: '#CatSpinner',
    inactiveFields: ['page', 'linkpage'],
  },
  {
    name: 'linked pages',
    input: '#botLinked',
    value: 'User:例/Список & links=1',
    button: '#LinkedSubmit',
    endpoint: 'linked_pages.php',
    field: 'linkpage',
    wiki: 'sr',
    slow: true,
    spinner: '#LinkSpinner',
    inactiveFields: ['page', 'cat'],
  },
];

const allOperationValues = {
  page: 'Stale page & 東京=1',
  cat: 'Stale category & Москва=2',
  linkpage: 'User:Stale/リンク=3',
};

async function expectReadyForm(page) {
  await expect(page.locator('#PageSpinner')).toBeHidden();
  await expect(page.locator('#CatSpinner')).toBeHidden();
  await expect(page.locator('#LinkSpinner')).toBeHidden();
  await expect(page.locator('#botStatus')).toHaveText('');
  await expect(page.locator('#PageSubmit')).toBeEnabled();
  await expect(page.locator('#CatSubmit')).toBeEnabled();
  await expect(page.locator('#LinkedSubmit')).toBeEnabled();
  await expect(page.locator('#botPage')).toBeEnabled();
  await expect(page.locator('#botCat')).toBeEnabled();
  await expect(page.locator('#botLinked')).toBeEnabled();
}

async function preventNextSubmissionNavigation(page) {
  await page.locator('#botForm').evaluate((form) => {
    // InitializeForm registered ValidateForm first. This later listener lets
    // production validation/state changes run, then cancels only the native
    // navigation so the resulting DOM state remains observable.
    form.addEventListener('submit', (event) => event.preventDefault(), { once: true });
  });
}

test.describe('Citation Bot web interface', () => {
  test('loads the real PHP form, JavaScript, CSS, and images', async ({ page }) => {
    await openHome(page);

    await expect(page.getByRole('heading', { name: 'Wikipedia citation bot' })).toBeVisible();
    await expect(page.getByLabel('Single page:')).toBeVisible();
    await expect(page.getByLabel('Category:')).toBeVisible();
    await expect(page.getByLabel('Linked pages:')).toBeVisible();
    await expect(page.getByLabel('Wiki to run on:')).toHaveValue('en');
    await expect(page.getByLabel(/Thorough mode/)).toBeChecked();
    await expectReadyForm(page);

    const token = await page.locator('input[name="csrf_token"]').inputValue();
    expect(token).toMatch(/^[0-9a-f]{64}$/);

    expect(await page.evaluate(() => typeof InitializeForm === 'function')).toBe(true);
    expect(await page.locator('link[rel="stylesheet"]').evaluate((link) => Boolean(link.sheet))).toBe(true);
    expect(await page.locator('#PageSpinner').evaluate((image) => image.complete && image.naturalWidth > 0)).toBe(true);
    await expect(page.locator('#PageSpinner')).toHaveAttribute('alt', '');
    await expect(page.locator('#botForm #botStatus')).toHaveCount(0);
  });

  const validationCases = [
    {
      name: 'page',
      input: '#botPage',
      button: '#PageSubmit',
      error: '#botPage-error',
      errorId: 'botPage-error',
      helpId: 'botPage-help',
      message: 'Page name is required',
      validValue: 'Ada Lovelace',
    },
    {
      name: 'category',
      input: '#botCat',
      button: '#CatSubmit',
      error: '#botCat-error',
      errorId: 'botCat-error',
      helpId: null,
      message: 'Category name is required',
      validValue: 'Scientists',
    },
    {
      name: 'linked-page',
      input: '#botLinked',
      button: '#LinkedSubmit',
      error: '#botLinked-error',
      errorId: 'botLinked-error',
      helpId: 'botLinked-help',
      message: 'Initial page name is required',
      validValue: 'User:Example/Test',
    },
  ];

  for (const scenario of validationCases) {
    test(`${scenario.name} submission rejects blank input with accessible error state and recovers`, async ({ page }) => {
      await openHome(page);

      await page.locator(scenario.button).click();

      const errorDescription = [scenario.helpId, scenario.errorId].filter(Boolean).join(' ');
      await expect(page).toHaveURL('http://127.0.0.1:8080/src/');
      await expect(page.locator(scenario.input)).toHaveAttribute('aria-invalid', 'true');
      await expect(page.locator(scenario.input)).toHaveAttribute('aria-describedby', errorDescription);
      await expect(page.locator(scenario.error)).toHaveAttribute('role', 'alert');
      await expect(page.locator(scenario.error)).toHaveText(scenario.message);
      await expect(page.locator(scenario.button)).toBeEnabled();

      await page.locator(scenario.input).fill(scenario.validValue);

      await expect(page.locator(scenario.input)).not.toHaveAttribute('aria-invalid');
      if (scenario.helpId) {
        await expect(page.locator(scenario.input)).toHaveAttribute('aria-describedby', scenario.helpId);
      } else {
        await expect(page.locator(scenario.input)).not.toHaveAttribute('aria-describedby');
      }
      await expect(page.locator(scenario.error)).toHaveCount(0);
      await expect(page.locator(scenario.button)).toBeEnabled();
    });
  }

  test('submitting one operation clears validation residue from inactive operations', async ({ page }) => {
    await openHome(page);

    await page.locator('#CatSubmit').click();
    await expect(page.locator('#botCat-error')).toBeVisible();
    await page.locator('#botPage').fill('Ada Lovelace');

    await preventNextSubmissionNavigation(page);
    await page.locator('#PageSubmit').click();

    await expect(page.locator('#botCat-error')).toHaveCount(0);
    await expect(page.locator('#botCat')).toBeDisabled();
  });

  test('page button pluralizes for a pipe-separated page list', async ({ page }) => {
    await openHome(page);

    const input = page.locator('#botPage');
    const button = page.locator('#PageSubmit');

    await input.fill('Page one|Page two');
    await expect(button).toHaveText('Process pages');

    await input.fill('Page one');
    await expect(button).toHaveText('Process page');
  });

  for (const scenario of submissionCases) {
    test(`${scenario.name} uses a native local POST and excludes stale operation fields`, async ({ page }) => {
      await openHome(page);

      const csrf = await page.locator('input[name="csrf_token"]').inputValue();
      await page.locator('#botPage').fill(allOperationValues.page);
      await page.locator('#botCat').fill(allOperationValues.cat);
      await page.locator('#botLinked').fill(allOperationValues.linkpage);
      await page.locator(scenario.input).fill(scenario.value);
      await page.locator('#wiki_base').selectOption(scenario.wiki);

      const slow = page.locator('#slow');
      if (scenario.slow) {
        await slow.check();
      } else {
        await slow.uncheck();
      }

      const captured = await captureSubmission(page, scenario.endpoint);
      await page.locator(scenario.button).click({ noWaitAfter: true });
      await captured.requestSeen;

      const form = parseForm(captured.request());
      expect(form.get(scenario.field)).toBe(scenario.value);
      for (const inactiveField of scenario.inactiveFields) {
        expect(form.has(inactiveField), `${inactiveField} must not be serialized for ${scenario.name}`).toBe(false);
      }
      expect(form.get('edit')).toBe('webform');
      expect(form.get('wiki_base')).toBe(scenario.wiki);
      expect(form.get('csrf_token')).toBe(csrf);
      expect(form.has('slow')).toBe(scenario.slow);
      if (scenario.slow) {
        expect(form.get('slow')).toBe('on');
      }
    });

    test(`${scenario.name} pending state cannot be re-enabled by further typing`, async ({ page }) => {
      await openHome(page);
      await page.locator(scenario.input).fill(scenario.value);

      await preventNextSubmissionNavigation(page);
      await page.locator(scenario.button).click();

      for (const spinner of ['#PageSpinner', '#CatSpinner', '#LinkSpinner']) {
        if (spinner === scenario.spinner) {
          await expect(page.locator(spinner)).toBeVisible();
        } else {
          await expect(page.locator(spinner)).toBeHidden();
        }
      }
      await expect(page.locator('#botStatus')).toHaveText('Processing, please wait…');
      await expect(page.locator('#botForm')).toHaveAttribute('aria-busy', 'true');
      await expect(page.locator('#PageSubmit')).toBeDisabled();
      await expect(page.locator('#CatSubmit')).toBeDisabled();
      await expect(page.locator('#LinkedSubmit')).toBeDisabled();

      // The active operation input remains editable while submission state is
      // active, but changing it must not re-enable any action or allow a second
      // Enter-key submission.
      await expect(page.locator(scenario.input)).toBeEnabled();
      await page.locator(scenario.input).fill(`${scenario.value} revised`);
      await expect(page.locator('#PageSubmit')).toBeDisabled();
      await expect(page.locator('#CatSubmit')).toBeDisabled();
      await expect(page.locator('#LinkedSubmit')).toBeDisabled();
      await page.locator(scenario.input).press('Enter');
      await expect(page.locator('#botForm')).toHaveAttribute('aria-busy', 'true');
    });

    test(`Enter in ${scenario.name} submits that operation`, async ({ page }) => {
      await openHome(page);
      const input = page.locator(scenario.input);
      await input.fill(scenario.value);
      await input.focus();

      const captured = await captureSubmission(page, scenario.endpoint);
      await page.keyboard.press('Enter');
      await captured.requestSeen;

      const form = parseForm(captured.request());
      expect(form.get(scenario.field)).toBe(scenario.value);
    });
  }

  test('IME composition Enter does not submit, but Enter after composition does', async ({ page }) => {
    await openHome(page);
    const input = page.locator('#botCat');
    await input.fill('日本語のカテゴリ');
    await input.focus();

    await input.evaluate((element) => {
      element.dispatchEvent(new KeyboardEvent('keydown', {
        key: 'Enter',
        code: 'Enter',
        bubbles: true,
        cancelable: true,
        isComposing: true,
      }));
    });

    await expect(page.locator('#botStatus')).toHaveText('');
    await expect(page.locator('#CatSubmit')).toBeEnabled();
    await expect(page.locator('#CatSpinner')).toBeHidden();

    const captured = await captureSubmission(page, 'category.php');
    await page.keyboard.press('Enter');
    await captured.requestSeen;
    expect(parseForm(captured.request()).get('cat')).toBe('日本語のカテゴリ');
  });

  test('programmatic requestSubmit without a submitter fails closed', async ({ page }) => {
    await openHome(page);
    await page.locator('#botPage').fill('Ada Lovelace');

    await page.locator('#botForm').evaluate((form) => form.requestSubmit());

    await expect(page).toHaveURL('http://127.0.0.1:8080/src/');
    await expectReadyForm(page);
  });

  test('session reload preserves CSRF state and clearing the session rotates it', async ({ page }) => {
    await openHome(page);
    const firstToken = await page.locator('input[name="csrf_token"]').inputValue();

    await page.reload({ waitUntil: 'domcontentloaded' });
    const secondToken = await page.locator('input[name="csrf_token"]').inputValue();
    expect(secondToken).toBe(firstToken);

    const cookies = await page.context().cookies('http://127.0.0.1:8080/src/');
    const sessionCookies = cookies.filter((cookie) => cookie.httpOnly && cookie.sameSite === 'Lax');
    expect(sessionCookies).toHaveLength(1);
    const sessionCookie = sessionCookies[0];
    expect(sessionCookie.secure).toBe(false);

    await page.context().clearCookies({ name: sessionCookie.name });
    await page.reload({ waitUntil: 'domcontentloaded' });
    const thirdToken = await page.locator('input[name="csrf_token"]').inputValue();
    expect(thirdToken).toMatch(/^[0-9a-f]{64}$/);
    expect(thirdToken).not.toBe(firstToken);
  });

  test('browser Back clears transient state and keeps derived controls consistent', async ({ page }) => {
    await openHome(page);
    await page.locator('#botPage').fill('Page one|Page two');
    await expect(page.locator('#PageSubmit')).toHaveText('Process pages');

    const captured = await fulfillSubmission(page, 'process_page.php');
    await page.locator('#PageSubmit').click();
    await captured.requestSeen;
    captured.request();
    await expect(page.getByRole('heading', { name: 'UI harness response' })).toBeVisible();

    await page.goBack({ waitUntil: 'domcontentloaded' });
    await expect(page).toHaveTitle('Citation Bot');
    await expectReadyForm(page);

    // History restoration of user-edited form values is browser/cache-policy
    // dependent. Whatever value is restored, derived UI must agree with it.
    const restoredPage = await page.locator('#botPage').inputValue();
    await expect(page.locator('#PageSubmit')).toHaveText(
      restoredPage.includes('|') ? 'Process pages' : 'Process page',
    );
    await expect(page.locator('#botCat')).toHaveValue('');
    await expect(page.locator('#botLinked')).toHaveValue('');
    await expect(page.locator('#botForm')).not.toHaveAttribute('aria-busy');
  });

  test('pageshow independently clears stale busy state without erasing user choices', async ({ page }) => {
    await openHome(page);
    await page.locator('#botPage').fill('Page one|Page two');
    await page.locator('#botCat').fill('Saved category');
    await page.locator('#botLinked').fill('User:Saved/List');
    await page.locator('#wiki_base').selectOption('ru');
    await page.locator('#slow').uncheck();

    await page.evaluate(() => {
      document.getElementById('PageSubmit').disabled = true;
      document.getElementById('CatSubmit').disabled = true;
      document.getElementById('LinkedSubmit').disabled = true;
      document.getElementById('botCat').disabled = true;
      document.getElementById('PageSpinner').style.display = 'inline-block';
      document.getElementById('botStatus').textContent = 'Processing, please wait…';
      document.getElementById('botForm').setAttribute('aria-busy', 'true');
      window.dispatchEvent(new Event('pageshow'));
    });

    await expectReadyForm(page);
    await expect(page.locator('#PageSubmit')).toHaveText('Process pages');
    await expect(page.locator('#botPage')).toHaveValue('Page one|Page two');
    await expect(page.locator('#botCat')).toHaveValue('Saved category');
    await expect(page.locator('#botLinked')).toHaveValue('User:Saved/List');
    await expect(page.locator('#wiki_base')).toHaveValue('ru');
    await expect(page.locator('#slow')).not.toBeChecked();
    await expect(page.locator('#botForm')).not.toHaveAttribute('aria-busy');
  });

  test('reduced-motion users retain textual processing feedback without an animated spinner', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await openHome(page);
    await page.locator('#botPage').fill('Ada Lovelace');

    await preventNextSubmissionNavigation(page);
    await page.locator('#PageSubmit').click();

    await expect(page.locator('#PageSpinner')).toBeHidden();
    await expect(page.locator('#botStatus')).toHaveText('Processing, please wait…');
    await expect(page.locator('#botStatus')).toBeVisible();
    await expect(page.locator('#PageSubmit')).toBeDisabled();
  });

  test('core controls fit a narrow mobile viewport without horizontal overflow', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 667 });
    await openHome(page);

    await expect(page.getByLabel('Single page:')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Process page', exact: true })).toBeVisible();
    await expect(page.getByLabel('Category:')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Process pages in category' })).toBeVisible();
    await expect(page.getByLabel('Linked pages:')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Process pages linked from' })).toBeVisible();
    await expect(page.getByLabel('Wiki to run on:')).toBeVisible();

    const hasHorizontalOverflow = await page.evaluate(
      () => document.documentElement.scrollWidth > window.innerWidth + 1,
    );
    expect(hasHorizontalOverflow).toBe(false);
  });

  test('whitespace-only and non-breaking-space input is rejected', async ({ page }) => {
    await openHome(page);
    for (const [input, button, error] of [
      ['#botPage', '#PageSubmit', '#botPage-error'],
      ['#botCat', '#CatSubmit', '#botCat-error'],
      ['#botLinked', '#LinkedSubmit', '#botLinked-error'],
    ]) {
      await page.locator(input).fill(' \t\u00a0 ');
      await expect(page.locator(error)).toBeVisible();
      await expect(page.locator(button)).toBeDisabled();
      await expect(page).toHaveURL('http://127.0.0.1:8080/src/');
      await page.locator(input).fill('Recovered');
      await expect(page.locator(error)).toHaveCount(0);
    }
  });

  test('HTML-looking and delimiter-heavy page names remain plain form data', async ({ page }) => {
    await openHome(page);
    const value = `<script>alert("x")</script> 100% + A&B=C | trailing|`;
    const initialScriptCount = await page.locator('script').count();
    await page.locator('#botPage').fill(value);
    expect(await page.locator('script').count()).toBe(initialScriptCount);

    const captured = await captureSubmission(page, 'process_page.php');
    await page.locator('#PageSubmit').click({ noWaitAfter: true });
    await captured.requestSeen;
    expect(parseForm(captured.request()).get('page')).toBe(value);
  });

  test('public wiki choices remain the intentional subset of backend-supported wikis', async ({ page }) => {
    await openHome(page);
    const publicWikis = await page.locator('#wiki_base option').evaluateAll((options) => options.map((option) => option.value));
    expect(publicWikis).toEqual(['en', 'simple', 'mk', 'ru', 'sr']);

    const setup = readFileSync('src/includes/setup.php', 'utf8');
    const match = setup.match(/in_array\(\$wiki_base,\s*\[([^\]]+)\],\s*true\)/s);
    expect(match, 'setup.php supported-wiki allow-list should remain discoverable by the UI contract test').not.toBeNull();
    const backendWikis = [...match[1].matchAll(/'([^']+)'/g)].map((entry) => entry[1]);
    for (const wiki of publicWikis) expect(backendWikis).toContain(wiki);
    expect(backendWikis).toEqual(expect.arrayContaining(['mdwiki', 'vi']));
  });

  test('initial and validation-error states have no serious automated accessibility violations', async ({ page }) => {
    await openHome(page);
    await page.evaluate(() => {
      const fixture = document.createElement('div');
      fixture.id = 'progress-text-contrast-fixture';
      fixture.innerHTML = '<span class="boring">Routine progress</span> <span class="subsubitem">Additional detail</span>';
      document.getElementById('main-form').appendChild(fixture);
    });

    let results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze();
    expect(results.violations).toEqual([]);

    await page.locator('#PageSubmit').click();
    results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze();
    expect(results.violations).toEqual([]);
  });

  test('skip link, focus order, and interactive target sizes support keyboard and touch use', async ({ page }) => {
    await openHome(page);
    await page.keyboard.press('Tab');
    await expect(page.locator('.skip-link')).toBeFocused();
    await expect(page.locator('.skip-link')).toBeVisible();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/#main-form$/);

    await page.keyboard.press('Tab');
    await expect(page.locator('#slow')).toBeFocused();

    for (const selector of ['#slow', '#botPage', '#PageSubmit', '#botCat', '#CatSubmit', '#botLinked', '#LinkedSubmit', '#wiki_base']) {
      const box = await page.locator(selector).boundingBox();
      expect(box, `${selector} should have a layout box`).not.toBeNull();
      expect(box.height, `${selector} should be at least 24 CSS px high`).toBeGreaterThanOrEqual(24);
    }
  });

  test('new-tab footer links retain noopener and noreferrer', async ({ page }) => {
    await openHome(page);
    const links = page.locator('footer a[target="_blank"]');
    const count = await links.count();
    expect(count).toBeGreaterThan(0);
    for (let i = 0; i < count; i += 1) {
      const rel = (await links.nth(i).getAttribute('rel') ?? '').split(/\s+/);
      expect(rel).toContain('noopener');
      expect(rel).toContain('noreferrer');
    }
  });

});
