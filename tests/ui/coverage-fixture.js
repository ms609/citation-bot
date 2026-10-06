import { test as guardedTest, expect } from './read-only-fixture.js';
import { mkdirSync, writeFileSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import { join } from 'node:path';

const rawDirectory = 'coverage/browser/raw';

export const test = guardedTest.extend({
  browserCoverage: [
    async ({ page }, use, testInfo) => {
      if (testInfo.project.name !== 'chromium') {
        await use();
        return;
      }

      await page.coverage.startJSCoverage({
        resetOnNavigation: false,
        reportAnonymousScripts: false,
      });
      await page.coverage.startCSSCoverage({ resetOnNavigation: false });

      await use();

      const js = await page.coverage.stopJSCoverage();
      const css = await page.coverage.stopCSSCoverage();

      mkdirSync(rawDirectory, { recursive: true });
      const filename = `${testInfo.parallelIndex}-${testInfo.retry}-${randomUUID()}.json`;
      writeFileSync(
        join(rawDirectory, filename),
        JSON.stringify(
          {
            title: testInfo.titlePath,
            project: testInfo.project.name,
            js,
            css,
          },
          null,
          2,
        ),
      );
    },
    { auto: true },
  ],
});

export { expect };
