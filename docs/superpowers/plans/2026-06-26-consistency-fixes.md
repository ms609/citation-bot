# Codebase Consistency Fixes — Implementation Plan (Executed)

> **Status:** All tasks complete across 47 files.

**Goal:** Fix inconsistencies across the citation-bot codebase spanning documentation, code typos, duplicate code, naming normalization, and phpcs configuration.

**Architecture:** Four workstreams, executed sequentially: (A) documentation fixes, (B) simple code fixes, (C) WikipediaBot method naming, (D) class naming normalization + phpcs cleanup.

**Tech Stack:** PHP 8.4, Markdown, YAML (GitHub Actions), XML (phpcs)

---

### Workstream A: Documentation Fixes

**Files:**
- `AGENTS.md`
- `docs/README.md`
- `.github/workflows/YamlJson.yml`

- [x] **Task A1:** Add 8 missing API integrations to AGENTS.md external API table (BibCode, Google Books, IEEE, ISSN, PII, Semantic Scholar, SICI, Unpaywall, Archive.org)

- [x] **Task A2:** Rename "Key Classes" → "Key Files" in AGENTS.md; note URLtools.php, NameTools.php, MathTools.php are function-only files

- [x] **Task A3:** Add `linked_pages.php`, `kill_big_job.php`, `gitpull.php` to AGENTS.md file tree (all entry points now listed)

- [x] **Task A4:** Fix "Tool Labs" → "Toolforge" in docs/README.md

- [x] **Task A5:** Add `category.php` and `linked_pages.php` to docs/README.md entry point listing

- [x] **Task A6:** Document `/dev/shm/` Linux dependency in docs/README.md

- [x] **Task A7:** Fix YamlJson.yml workflow name from "Validate JSON, XML, and MD" → "Validate JSON, YAML, and MD"

---

### Workstream B: Simple Code Fixes

**Files:**
- `src/includes/setup.php`
- `src/includes/URLtools.php`
- `src/includes/Template.php`
- `.phpcs.xml`

- [x] **Task B1:** Remove duplicate `require_once __DIR__ . '/constants.php'` at setup.php line 183 (line 59 kept — essential for `BOT_USER_AGENT` at line 61)

- [x] **Task B2:** Fix `find_indentifiers_in_urls` → `find_identifiers_in_urls` typo (2 function definitions + 1 internal call + 1 call in Template.php)

- [x] **Task B3:** Remove duplicate `mb_ereg_search_getpos` entry from .phpcs.xml (line 89)

---

### Workstream C: WikipediaBot Naming Normalization

**Files:**
- `src/includes/WikipediaBot.php`
- `src/includes/Page.php`
- `src/includes/setup.php`
- `tests/phpunit/includes/ConstantsTest.php`
- `tests/phpunit/includes/wikipediaBotTest.php`

- [x] **Task C1:** Rename 5 PascalCase methods to snake_case in WikipediaBot.php (definitions + all internal `self::` calls):
  - `QueryAPI()` → `query_api()` (10 internal calls)
  - `ReadDetails()` → `read_details()` (1 external caller)
  - `GetAPage()` → `get_a_page()` (3 external callers)
  - `NonStandardMode()` → `non_standard_mode()` (1 test caller)
  - `GetLastUser()` → `get_last_user()` (2 callers in setup.php)

- [x] **Task C2:** Update callers in Page.php (ReadDetails, GetAPage)

- [x] **Task C3:** Update callers in setup.php (GetLastUser × 2)

- [x] **Task C4:** Update callers in ConstantsTest.php (GetAPage × 2)

- [x] **Task C5:** Update caller in wikipediaBotTest.php (NonStandardMode)

---

### Workstream D: Class Naming + phpcs Cleanup

- [x] **Task D1: HandleCache → DoiTools rename** — Rename class across 4 files (32 call sites): `doiTools.php`, `miscTools.php` (1), `TemplatePart3Test.php` (4), `zzzLastTest.php` (1)

- [x] **Task D2: testBaseClass → TestBaseClass** — Rename class definition in `testBaseClass.php` + all 35 `extends testBaseClass` references across test files

- [x] **Task D3: Rename 21 lowercase test classes to UpperCamelCase** — One per test file, matching PHP convention:
  `gadgetapiTest`, `generate_templateTest`, `bigJobTest`, `mathToolsTest`, `nameToolsTest`, `pageTest`, `parameterTest`, `textToolsTest`, `wikipediaBotTest`, `archiveTest`, `arxivTest`, `bibcodeTest`, `googleBooksTest`, `ieeeTest`, `issnTest`, `piiAPITest`, `pubmedTest`, `siciTest`, `unpaywallApiTest`, `zoteroTest`, `zzzLastTest`

- [x] **Task D4: Update .phpcs.xml** — Remove global `Squiz.Classes.ValidClassName.NotCamelCaps` exclusion; add per-file exemption for `templatePart4Test.php` only (intentional lowercase `t` for test ordering)

---

### Items Explicitly Kept

- Dead code removal skipped (query_*_api dynamically called from Page.php; test-only class methods kept)
- TextTools.php include cleanup skipped
- API file pattern normalization skipped (bare function wrappers required by Page.php dynamic dispatch)
- phpcs exclusions kept: `AssignmentInControlStructures` (intentional style), `OneObjectStructurePerFile` (deferred), `ClassMatchesFilename` (partial fix, residual violations remain)
- docs/README.md generate_template identifiers enumeration skipped

### Verification

```bash
# PHP syntax lint — all 47 modified files pass
php -l src/includes/setup.php
php -l src/includes/URLtools.php
php -l src/includes/WikipediaBot.php
php -l src/includes/Page.php
php -l src/includes/Template.php
php -l src/includes/doiTools.php
php -l src/includes/miscTools.php
php -l tests/testBaseClass.php
# Plus all renamed test files

# Focused test suites — pre-existing failures only (encoding, network timeout)
php vendor/bin/phpunit --no-configuration --bootstrap tests/testBaseClass.php tests/phpunit/includes/parameterTest.php
php vendor/bin/phpunit --no-configuration --bootstrap tests/testBaseClass.php tests/phpunit/includes/BotCurlTest.php

# Clean old/references: no stale PascalCase methods, no HandleCache, no testBaseClass, no indentifiers typo remain
```
