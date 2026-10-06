import { rmSync, mkdirSync } from 'node:fs';

for (const directory of ['coverage', 'test-results', 'playwright-report']) {
  rmSync(directory, { recursive: true, force: true });
}
mkdirSync('coverage/browser/raw', { recursive: true });
mkdirSync('coverage/php/raw', { recursive: true });
mkdirSync('test-results', { recursive: true });
