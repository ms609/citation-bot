import {
  existsSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  writeFileSync,
} from 'node:fs';
import { resolve } from 'node:path';
import v8ToIstanbul from 'v8-to-istanbul';

const repoRoot = process.cwd();
const localOrigin = 'http://127.0.0.1:8080';
const browserRaw = resolve(repoRoot, 'coverage/browser/raw');
const phpRaw = resolve(repoRoot, 'coverage/php/raw');
const browserOut = resolve(repoRoot, 'coverage/browser');
const cssOut = resolve(repoRoot, 'coverage/css');
const phpOut = resolve(repoRoot, 'coverage/php');
mkdirSync(browserOut, { recursive: true });
mkdirSync(cssOut, { recursive: true });
mkdirSync(phpOut, { recursive: true });

function jsonFiles(directory) {
  if (!existsSync(directory)) return [];
  return readdirSync(directory)
    .filter((name) => name.endsWith('.json'))
    .sort()
    .map((name) => JSON.parse(readFileSync(resolve(directory, name), 'utf8')));
}

function requireCoverage(condition, message) {
  if (!condition) throw new Error(`UI coverage collection failed: ${message}`);
}

function percent(covered, total) {
  return total === 0 ? null : (covered * 100) / total;
}

function formatPercent(value) {
  return value === null ? 'n/a' : `${value.toFixed(1)}%`;
}

function enforceOptionalMinimum(envName, actual, label) {
  const configured = process.env[envName];
  if (configured === undefined || configured === '') return;
  const minimum = Number(configured);
  if (!Number.isFinite(minimum) || minimum < 0 || minimum > 100) {
    throw new Error(`${envName} must be a percentage from 0 through 100`);
  }
  requireCoverage(actual !== null && actual >= minimum, `${label} ${formatPercent(actual)} is below ${minimum.toFixed(1)}%`);
}

function repositoryPathFromUrl(url) {
  let parsed;
  try {
    parsed = new URL(url);
  } catch {
    return null;
  }
  if (parsed.origin !== localOrigin) return null;
  const pathname = decodeURIComponent(parsed.pathname).replace(/^\/+/, '');
  if (!pathname.startsWith('src/')) return null;
  return pathname;
}

function sourceFromEntry(entry) {
  const repositoryPath = repositoryPathFromUrl(entry.url ?? '');
  if (!repositoryPath) return '';
  if (typeof entry.source === 'string') return entry.source;
  if (typeof entry.text === 'string') return entry.text;
  try {
    return readFileSync(resolve(repoRoot, repositoryPath), 'utf8');
  } catch {
    return '';
  }
}

function createIstanbulAccumulator() {
  return {
    statements: new Map(),
    functions: new Map(),
    branches: new Map(),
  };
}

function mergeIstanbulCoverage(accumulator, fileCoverage) {
  for (const [id, definition] of Object.entries(fileCoverage.statementMap ?? {})) {
    const key = JSON.stringify(definition);
    const count = Number(fileCoverage.s?.[id] ?? 0);
    const previous = accumulator.statements.get(key);
    accumulator.statements.set(key, {
      definition,
      count: Math.max(previous?.count ?? 0, count),
    });
  }

  for (const [id, definition] of Object.entries(fileCoverage.fnMap ?? {})) {
    const key = JSON.stringify(definition);
    const count = Number(fileCoverage.f?.[id] ?? 0);
    const previous = accumulator.functions.get(key);
    accumulator.functions.set(key, {
      definition,
      count: Math.max(previous?.count ?? 0, count),
    });
  }

  for (const [id, definition] of Object.entries(fileCoverage.branchMap ?? {})) {
    const key = JSON.stringify(definition);
    const incoming = (fileCoverage.b?.[id] ?? []).map(Number);
    const previous = accumulator.branches.get(key);
    const counts = incoming.map((count, index) => Math.max(previous?.counts?.[index] ?? 0, count));
    accumulator.branches.set(key, { definition, counts });
  }
}

function finalizeIstanbulCoverage(repositoryPath, accumulator) {
  const statementMap = {};
  const s = {};
  [...accumulator.statements.values()].forEach(({ definition, count }, index) => {
    statementMap[index] = definition;
    s[index] = count;
  });

  const fnMap = {};
  const f = {};
  [...accumulator.functions.values()].forEach(({ definition, count }, index) => {
    fnMap[index] = definition;
    f[index] = count;
  });

  const branchMap = {};
  const b = {};
  [...accumulator.branches.values()].forEach(({ definition, counts }, index) => {
    branchMap[index] = definition;
    b[index] = counts;
  });

  return { path: repositoryPath, statementMap, s, fnMap, f, branchMap, b };
}

function lineCountsFromIstanbul(fileCoverage) {
  const counts = new Map();
  for (const [statementId, location] of Object.entries(fileCoverage.statementMap ?? {})) {
    const line = location?.start?.line;
    if (!Number.isInteger(line)) continue;
    const count = Number(fileCoverage.s?.[statementId] ?? 0);
    counts.set(line, Math.max(counts.get(line) ?? 0, count));
  }
  return counts;
}

function coverageCountSummary(values) {
  const counts = values.map(Number);
  const covered = counts.filter((count) => count > 0).length;
  return { covered, total: counts.length, percent: percent(covered, counts.length) };
}

function branchCountSummary(branches) {
  return coverageCountSummary(Object.values(branches ?? {}).flatMap((value) => value.map(Number)));
}

const browserRuns = jsonFiles(browserRaw);
requireCoverage(browserRuns.length > 0, 'no Playwright browser coverage files were produced');

const jsByPath = new Map();
const cssByPath = new Map();
for (const run of browserRuns) {
  for (const entry of run.js ?? []) {
    const repositoryPath = repositoryPathFromUrl(entry.url ?? '');
    if (!repositoryPath || !repositoryPath.endsWith('.js')) continue;
    const source = sourceFromEntry(entry);
    if (!source) continue;
    const previous = jsByPath.get(repositoryPath) ?? { source, repositoryPath, runs: [] };
    requireCoverage(previous.source === source, `JavaScript source changed within one coverage run for ${repositoryPath}`);
    previous.runs.push(entry.functions ?? []);
    jsByPath.set(repositoryPath, previous);
  }

  for (const entry of run.css ?? []) {
    const repositoryPath = repositoryPathFromUrl(entry.url ?? '');
    if (!repositoryPath || !repositoryPath.endsWith('.css')) continue;
    const source = sourceFromEntry(entry);
    if (!source) continue;
    const previous = cssByPath.get(repositoryPath) ?? {
      source,
      repositoryPath,
      mask: new Uint8Array(source.length),
    };
    requireCoverage(
      previous.source === source && previous.mask.length === source.length,
      `Landing-page CSS source changed within one coverage run for ${repositoryPath}`,
    );
    for (const range of entry.ranges ?? []) {
      for (let i = range.start; i < range.end && i < previous.mask.length; i += 1) {
        previous.mask[i] = 1;
      }
    }
    cssByPath.set(repositoryPath, previous);
  }
}

requireCoverage(
  [...jsByPath.values()].some((entry) => entry.repositoryPath === 'src/assets/index.js'),
  'src/assets/index.js was not present in Chromium JavaScript coverage',
);
requireCoverage(
  [...cssByPath.values()].some((entry) => entry.repositoryPath === 'src/assets/results.css'),
  'src/assets/results.css was not present in Chromium landing-page CSS usage',
);

let jsCoveredLines = 0;
let jsTotalLines = 0;
let jsCoveredFunctions = 0;
let jsTotalFunctions = 0;
let jsCoveredBranches = 0;
let jsTotalBranches = 0;
const jsFiles = [];
const istanbulCoverage = {};
let jsLcov = '';

for (const [, data] of [...jsByPath.entries()].sort((a, b) => a[1].repositoryPath.localeCompare(b[1].repositoryPath))) {
  const localPath = resolve(repoRoot, data.repositoryPath);
  const accumulator = createIstanbulAccumulator();

  // Convert each test's V8 data independently, then union the resulting
  // Istanbul locations using the highest observed hit count. This avoids
  // relying on V8 range shapes remaining identical across navigations/tests.
  for (const functions of data.runs) {
    const converter = v8ToIstanbul(localPath, 0, { source: data.source });
    await converter.load();
    converter.applyCoverage(functions);
    const converted = converter.toIstanbul();
    const fileCoverage = Object.values(converted)[0];
    if (fileCoverage) mergeIstanbulCoverage(accumulator, fileCoverage);
  }

  const fileCoverage = finalizeIstanbulCoverage(data.repositoryPath, accumulator);
  requireCoverage(
    Object.keys(fileCoverage.statementMap).length > 0,
    `v8-to-istanbul produced no statements for ${data.repositoryPath}`,
  );
  istanbulCoverage[data.repositoryPath] = fileCoverage;

  const lineCounts = lineCountsFromIstanbul(fileCoverage);
  const lines = coverageCountSummary([...lineCounts.values()]);
  const functions = coverageCountSummary(Object.values(fileCoverage.f));
  const branches = branchCountSummary(fileCoverage.b);

  jsCoveredLines += lines.covered;
  jsTotalLines += lines.total;
  jsCoveredFunctions += functions.covered;
  jsTotalFunctions += functions.total;
  jsCoveredBranches += branches.covered;
  jsTotalBranches += branches.total;
  jsFiles.push({ file: data.repositoryPath, lines, functions, branches });

  jsLcov += `TN:browser-ui\nSF:${data.repositoryPath}\n`;
  for (const [line, count] of [...lineCounts.entries()].sort((a, b) => a[0] - b[0])) {
    jsLcov += `DA:${line},${count}\n`;
  }
  for (const [functionId, definition] of Object.entries(fileCoverage.fnMap)) {
    const name = `${definition.name || '(anonymous)'}#${functionId}`;
    const line = definition.decl?.start?.line ?? definition.loc?.start?.line ?? 1;
    const count = Number(fileCoverage.f[functionId] ?? 0);
    jsLcov += `FN:${line},${name}\nFNDA:${count},${name}\n`;
  }
  let branchIndex = 0;
  for (const [branchId, definition] of Object.entries(fileCoverage.branchMap)) {
    const line = definition.loc?.start?.line ?? definition.locations?.[0]?.start?.line ?? 1;
    for (const count of fileCoverage.b[branchId] ?? []) {
      jsLcov += `BRDA:${line},${branchId},${branchIndex},${Number(count)}\n`;
      branchIndex += 1;
    }
  }
  jsLcov += `LF:${lines.total}\nLH:${lines.covered}\n`;
  jsLcov += `FNF:${functions.total}\nFNH:${functions.covered}\n`;
  jsLcov += `BRF:${branches.total}\nBRH:${branches.covered}\nend_of_record\n`;
}

requireCoverage(jsTotalLines > 0, 'JavaScript line coverage contained no executable lines');
const jsSummary = {
  files: jsFiles,
  totals: {
    lines: { covered: jsCoveredLines, total: jsTotalLines, percent: percent(jsCoveredLines, jsTotalLines) },
    functions: { covered: jsCoveredFunctions, total: jsTotalFunctions, percent: percent(jsCoveredFunctions, jsTotalFunctions) },
    branches: { covered: jsCoveredBranches, total: jsTotalBranches, percent: percent(jsCoveredBranches, jsTotalBranches) },
  },
};
enforceOptionalMinimum('UI_JS_MIN_LINES', jsSummary.totals.lines.percent, 'JavaScript line coverage');
enforceOptionalMinimum('UI_JS_MIN_FUNCTIONS', jsSummary.totals.functions.percent, 'JavaScript function coverage');
enforceOptionalMinimum('UI_JS_MIN_BRANCHES', jsSummary.totals.branches.percent, 'JavaScript branch coverage');
writeFileSync(resolve(browserOut, 'coverage-final.json'), JSON.stringify(istanbulCoverage, null, 2));
writeFileSync(resolve(browserOut, 'summary.json'), JSON.stringify(jsSummary, null, 2));
writeFileSync(resolve(browserOut, 'lcov.info'), jsLcov);

let cssCovered = 0;
let cssTotal = 0;
const cssFiles = [];
for (const [, data] of [...cssByPath.entries()].sort((a, b) => a[1].repositoryPath.localeCompare(b[1].repositoryPath))) {
  const covered = data.mask.reduce((sum, value) => sum + value, 0);
  cssCovered += covered;
  cssTotal += data.source.length;
  cssFiles.push({
    file: data.repositoryPath,
    sourceCharacters: { covered, total: data.source.length, percent: percent(covered, data.source.length) },
  });
}
requireCoverage(cssTotal > 0, 'Landing-page CSS usage contained no source data');
const cssSummary = {
  files: cssFiles,
  totals: {
    sourceCharacters: { covered: cssCovered, total: cssTotal, percent: percent(cssCovered, cssTotal) },
  },
};
writeFileSync(resolve(cssOut, 'summary.json'), JSON.stringify(cssSummary, null, 2));

const phpRuns = jsonFiles(phpRaw);
requireCoverage(phpRuns.length > 0, 'no Xdebug request coverage files were produced');
const phpByFile = new Map();
for (const run of phpRuns) {
  for (const [filename, lines] of Object.entries(run.files ?? {})) {
    const merged = phpByFile.get(filename) ?? new Map();
    for (const [lineText, rawState] of Object.entries(lines)) {
      const line = Number(lineText);
      const state = Number(rawState);
      const previous = merged.get(line);
      // Xdebug: >0 covered; -1 executable but not covered; -2 not executable.
      // Never include -2 in LCOV's line denominator.
      if (state > 0) {
        merged.set(line, 1);
      } else if (state === -1 && previous !== 1) {
        merged.set(line, 0);
      }
    }
    phpByFile.set(filename, merged);
  }
}

requireCoverage(phpByFile.has('src/index.php'), 'src/index.php was not present in Xdebug coverage');
let phpCovered = 0;
let phpTotal = 0;
const phpFiles = [];
let phpLcov = '';
for (const [filename, lines] of [...phpByFile.entries()].sort()) {
  const ordered = [...lines.entries()].sort((a, b) => a[0] - b[0]);
  if (ordered.length === 0) continue;
  const covered = ordered.filter(([, count]) => count > 0).length;
  const total = ordered.length;
  phpCovered += covered;
  phpTotal += total;
  phpFiles.push({ file: filename, lines: { covered, total, percent: percent(covered, total) } });
  phpLcov += `TN:browser-ui-php\nSF:${filename}\n`;
  for (const [line, count] of ordered) phpLcov += `DA:${line},${count}\n`;
  phpLcov += `LF:${total}\nLH:${covered}\nend_of_record\n`;
}
requireCoverage(phpTotal > 0, 'PHP coverage contained no executable lines');
const phpSummary = {
  files: phpFiles,
  totals: { lines: { covered: phpCovered, total: phpTotal, percent: percent(phpCovered, phpTotal) } },
};
writeFileSync(resolve(phpOut, 'summary.json'), JSON.stringify(phpSummary, null, 2));
writeFileSync(resolve(phpOut, 'lcov.info'), phpLcov);
writeFileSync(resolve(repoRoot, 'coverage/lcov.info'), `${phpLcov}${jsLcov}`);

const markdown = `# Citation Bot browser UI coverage\n\n| Coverage | Covered | Total | Percent |\n| --- | ---: | ---: | ---: |\n| PHP UI-request lines | ${phpCovered} | ${phpTotal} | ${formatPercent(phpSummary.totals.lines.percent)} |\n| Browser JavaScript lines | ${jsCoveredLines} | ${jsTotalLines} | ${formatPercent(jsSummary.totals.lines.percent)} |\n| Browser JavaScript functions | ${jsCoveredFunctions} | ${jsTotalFunctions} | ${formatPercent(jsSummary.totals.functions.percent)} |\n| Browser JavaScript branches | ${jsCoveredBranches} | ${jsTotalBranches} | ${formatPercent(jsSummary.totals.branches.percent)} |\n| Landing-page CSS source characters used | ${cssCovered} | ${cssTotal} | ${formatPercent(cssSummary.totals.sourceCharacters.percent)} |\n\nPHP coverage includes only PHP reached by the browser harness. Form destinations are intercepted before application processing, so this action does not contact Wikimedia or perform OAuth/edit operations. CSS is reported as landing-page usage, not as proof that unused rules are dead code.\n`;
writeFileSync(resolve(repoRoot, 'coverage/summary.md'), markdown);

const htmlEscape = (value) => String(value)
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;');
const rows = [
  ['PHP UI-request lines', phpCovered, phpTotal, formatPercent(phpSummary.totals.lines.percent)],
  ['Browser JavaScript lines', jsCoveredLines, jsTotalLines, formatPercent(jsSummary.totals.lines.percent)],
  ['Browser JavaScript functions', jsCoveredFunctions, jsTotalFunctions, formatPercent(jsSummary.totals.functions.percent)],
  ['Browser JavaScript branches', jsCoveredBranches, jsTotalBranches, formatPercent(jsSummary.totals.branches.percent)],
  ['Landing-page CSS source characters used', cssCovered, cssTotal, formatPercent(cssSummary.totals.sourceCharacters.percent)],
].map((row) => `<tr>${row.map((cell) => `<td>${htmlEscape(cell)}</td>`).join('')}</tr>`).join('\n');
writeFileSync(
  resolve(repoRoot, 'coverage/index.html'),
  `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Citation Bot UI coverage</title><style>body{font-family:system-ui,sans-serif;max-width:60rem;margin:2rem auto;padding:0 1rem}table{border-collapse:collapse}th,td{border:1px solid #bbb;padding:.45rem .7rem;text-align:right}th:first-child,td:first-child{text-align:left}</style></head><body><h1>Citation Bot browser UI coverage</h1><table><thead><tr><th>Coverage</th><th>Covered</th><th>Total</th><th>Percent</th></tr></thead><tbody>${rows}</tbody></table><p>JavaScript coverage is converted from Chromium V8 coverage with v8-to-istanbul. PHP coverage is limited to UI-request code reached before Playwright intercepts form destinations.</p></body></html>`,
);

process.stdout.write(markdown);
