import { test as base, expect } from '@playwright/test';
import { installReadOnlyRequestGuard } from './request-guard.js';

export const test = base.extend({
  requestGuard: [
    async ({ page }, use, testInfo) => {
      const baseURL = String(testInfo.project.use.baseURL ?? '');
      if (!baseURL) throw new Error('A project baseURL is required for the UI request guard');
      const parsed = new URL(baseURL);
      const guard = await installReadOnlyRequestGuard(page, {
        origin: parsed.origin,
        basePath: parsed.pathname.replace(/\/$/, ''),
      });

      await use(guard);
      guard.assertClean();
    },
    { auto: true },
  ],
});

export { expect };
