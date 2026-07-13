# SSRN Options 2 & 3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prevent `cite journal` from being converted to `cite ssrn` when SSRN is present, and make SSRN URL forgetting unconditional (Option 2). Drop Option 3 (`ssrn-access=free`) as uncertain benefit.

**Architecture:** Two production files change (SSRN URL handler in URLtools.php, Zotero template type handler in APIzotero.php). Both use the same technique: capture `originalName` via `wikiname()` before any mutation, then check `originalName === 'cite web'` instead of current `wikiname()` which may have been altered by `tidy_parameter('pmc')`. Six existing tests updated, three new tests added.

**Tech Stack:** PHP 8.4, PHPUnit 12

---

### Task 1: URLtools.php — Remove has_good_free_copy gate, save originalName

**Files:**
- Modify: `src/includes/URLtools.php:2042-2054`

- [ ] **Step 1: Read current code**

Verify current state of lines 2042-2054.

- [ ] **Step 2: Apply change**

Replace the `has_good_free_copy()`-gated block with unconditional URL forgetting + originalName check:

```php
} elseif (preg_match("~^https?://papers\.ssrn\.com(?:/sol3/papers\.cfm\?abstract_id=|/abstract=)([0-9]+)~i", $url, $match)) {
    if ($template->blank('ssrn')) {
        report_modification("Converting URL to SSRN parameter");
    }
    if (!$url_sent) {
        $originalName = $template->wikiname();
        $template->forget($url_type);
        if ($originalName === 'cite web') {
            $template->change_name_to('cite ssrn');
        }
    }
    return $template->add_if_new('ssrn', $match[1]);
```

Changes from current:
- Remove `if ($template->has_good_free_copy()) {` wrapper
- Add `$originalName = $template->wikiname();` before `forget()`
- Change condition from `($template->wikiname() === 'cite web' || $template->wikiname() === 'cite journal')` to `($originalName === 'cite web')`

- [ ] **Step 3: Verify syntax**

Run: `php -l src/includes/URLtools.php`
Expected: "No syntax errors detected"

---

### Task 2: APIzotero.php — Save originalName in Zotero path

**Files:**
- Modify: `src/includes/api/APIzotero.php:974-977`

- [ ] **Step 1: Read current code**

Verify current state of lines 971-985.

- [ ] **Step 2: Apply change**

Replace the `cite journal` check with originalName pattern:

```php
                case 'journalArticle':
                case 'conferencePaper':
                case 'report': // ssrn uses this
                    $originalName = $template->wikiname();
                    if ($template->has('ssrn')) {
                        if ($originalName === 'cite web') {
                            $template->change_name_to('cite ssrn');
                        }
                    } elseif (($template->wikiname() === 'cite web') &&
                            (str_ireplace(NON_JOURNAL_WEBSITES, '', $url) === $url) &&
                            !$template->blank(WORK_ALIASES) &&
                            (str_ireplace('breakingnews', '', $url) === $url) &&
                            (str_ireplace('/blog/', '', $url) === $url)) {
                        $template->change_name_to('cite journal');
                    }
                    break;
```

Changes from current:
- Add `$originalName = $template->wikiname();` before the `if ($template->has('ssrn'))` block
- Change `$template->wikiname() === 'cite web' || $template->wikiname() === 'cite journal'` to `$originalName === 'cite web'`

- [ ] **Step 3: Verify syntax**

Run: `php -l src/includes/api/APIzotero.php`
Expected: "No syntax errors detected"

---

### Task 3: UrlToolsTest.php — Update existing tests

**Files:**
- Modify: `tests/phpunit/includes/UrlToolsTest.php:934-965`

- [ ] **Step 1: Update testSrrnUrlCiteWeb (line 934)**

Add URL null and wikiname assertions:

```php
/** SSRN URL in cite web extracts SSRN parameter and converts */
public function testSrrnUrlCiteWeb(): void {
    $text = '{{cite web | url= https://papers.ssrn.com/sol3/papers.cfm?abstract_id=936346 }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('936346', $prepared->get2('ssrn'));
    $this->assertNull($prepared->get2('url'));
    $this->assertSame('cite ssrn', $prepared->wikiname());
}
```

- [ ] **Step 2: Update testSrrnUrlAlternateFormat (line 941)**

Same additional assertions:

```php
/** SSRN URL alternate format extracts SSRN parameter and converts */
public function testSrrnUrlAlternateFormat(): void {
    $text = '{{cite web | url= https://papers.ssrn.com/abstract=936347 }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('936347', $prepared->get2('ssrn'));
    $this->assertNull($prepared->get2('url'));
    $this->assertSame('cite ssrn', $prepared->wikiname());
}
```

- [ ] **Step 3: Rename and update testSrrnUrlStaysWithoutGoodFreeCopy (line 957)**

Now tests unconditional conversion — rename and update assertions:

```php
/** SSRN URL without PMC still forgets URL and converts to cite ssrn */
public function testSrrnUrlConvertsWithoutGoodFreeCopy(): void {
    $text = '{{cite web | url= https://papers.ssrn.com/sol3/papers.cfm?abstract_id=1234232 }}';
    $template = $this->make_citation($text);
    $this->assertTrue($template->get_identifiers_from_url());
    $this->assertNull($template->get2('url'));
    $this->assertSame('1234232', $template->get2('ssrn'));
    $this->assertSame('cite ssrn', $template->wikiname());
}
```

- [ ] **Step 4: Run updated tests**

Run: `php vendor/bin/phpunit --filter="testSrrnUrlCiteWeb|testSrrnUrlAlternateFormat|testSrrnUrlConvertsWithoutGoodFreeCopy|testSrrnUrlForgottenWithGoodFreeCopy" --no-coverage`
Expected: OK (4 tests)

---

### Task 4: UrlToolsTest.php — Add 3 new tests

**Files:**
- Modify: `tests/phpunit/includes/UrlToolsTest.php` (append before final closing `}`)

- [ ] **Step 1: Add testSrrnCiteJournalStaysCiteJournal**

```php
/** cite journal with SSRN URL stays cite journal (per Wikipedia guidance) */
public function testSrrnCiteJournalStaysCiteJournal(): void {
    $text = '{{cite journal | url= https://papers.ssrn.com/sol3/papers.cfm?abstract_id=1234233 }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('1234233', $prepared->get2('ssrn'));
    $this->assertNull($prepared->get2('url'));
    $this->assertSame('cite journal', $prepared->wikiname());
}
```

- [ ] **Step 2: Add testSrrnCiteJournalWithPmcStaysCiteJournal**

```php
/** cite journal with SSRN URL + PMC stays cite journal (originalName survives PMC tidy) */
public function testSrrnCiteJournalWithPmcStaysCiteJournal(): void {
    $text = '{{cite journal | url= https://papers.ssrn.com/sol3/papers.cfm?abstract_id=1234234 | pmc=123456 }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('1234234', $prepared->get2('ssrn'));
    $this->assertSame('123456', $prepared->get2('pmc'));
    $this->assertNull($prepared->get2('url'));
    $this->assertSame('cite journal', $prepared->wikiname());
}
```

- [ ] **Step 3: Add testSrrnCiteNewsStaysCiteNews**

```php
/** cite news with SSRN URL stays cite news (non-web templates unaffected) */
public function testSrrnCiteNewsStaysCiteNews(): void {
    $text = '{{cite news | url= https://papers.ssrn.com/sol3/papers.cfm?abstract_id=1234235 }}';
    $prepared = $this->prepare_citation($text);
    $this->assertSame('1234235', $prepared->get2('ssrn'));
    $this->assertNull($prepared->get2('url'));
    $this->assertSame('cite news', $prepared->wikiname());
}
```

- [ ] **Step 4: Run new tests**

Run: `php vendor/bin/phpunit --filter="testSrrnCiteJournalStaysCiteJournal|testSrrnCiteJournalWithPmcStaysCiteJournal|testSrrnCiteNewsStaysCiteNews" --no-coverage`
Expected: OK (3 tests)

---

### Task 5: ComprehensiveSSRNTest.php — Update expectations

**Files:**
- Modify: `tests/phpunit/includes/ComprehensiveSSRNTest.php:14-54`

- [ ] **Step 1: Update testSrrnUrlExtraction URL expectations (lines 14-42)**

Change expected URL status from `true` to `false` and wikiname from `'cite web'` to `'cite ssrn'`:

```php
for ($i = 0; $i < 10; $i++) {
    $id = 1000000 + $i;
    $cases[] = ["cite_web_$i", "{{cite web|url=https://papers.ssrn.com/sol3/papers.cfm?abstract_id=$id}}", (string) $id, false, 'cite ssrn'];
}
$urls = [
    ['https://papers.ssrn.com/sol3/papers.cfm?abstract_id=936346', '936346'],
    ['https://papers.ssrn.com/abstract=936347', '936347'],
    ['http://papers.ssrn.com/sol3/papers.cfm?abstract_id=936349', '936349'],
    ['https://papers.ssrn.com/sol3/papers.cfm?abstract_id=936350/', '936350'],
];
foreach ($urls as $i => $u) {
    $cases[] = ["fmt_$i", "{{cite web|url=$u[0]}}", $u[1], false, 'cite ssrn'];
}
```

- [ ] **Step 2: Update url_no_pmc case in testSrrnPrepareFull (lines 53-54)**

```php
$cases[] = ['url_no_pmc', '{{cite web|url=https://papers.ssrn.com/sol3/papers.cfm?abstract_id=936346}}',
    ['ssrn' => '936346', 'url' => null, 'wikiname' => 'cite ssrn']];
```

- [ ] **Step 3: Run comprehensive tests**

Run: `php vendor/bin/phpunit --filter="ComprehensiveSSRNTest" --no-coverage`
Expected: OK (7 tests, all passing)

---

### Task 6: Review new code interactions against entire codebase

**Purpose:** Confirm that no existing code path interferes with the originalName pattern or converts `cite journal` to `cite ssrn`.

- [ ] **Step 1: Audit all `change_name_to('cite journal')` call sites**

Search for every call to `change_name_to('cite journal')` across `src/`. For each one, determine:
- Can it fire when `wikiname() === 'cite ssrn'`?
- If yes, is there already a guard (`wikiname() !== 'cite ssrn'`)?
- List any unguarded sites.

Run: `Select-String -Path src/includes/*.php,src/includes/api/*.php,src/includes/constants/*.php -Pattern "change_name_to\('cite journal'\)"`

Expected result: 20+ matches. All sites that could fire for `cite ssrn` are guarded by `$this->wikiname() !== 'cite ssrn'` or equivalent. Note any unguarded sites as findings.

- [ ] **Step 2: Verify no code path converts `cite journal` with `ssrn` to `cite ssrn`**

With the originalName change in URLtools.php and APIzotero.php, `cite journal` should NEVER convert to `cite ssrn`. Confirm by tracing:
- URLtools.php: originalName check only accepts `'cite web'` → `cite journal` excluded
- APIzotero.php: same originalName check → `cite journal` excluded
- All other rename-to-ssrn paths: none exist

Expected: Zero code paths that convert `cite journal` → `cite ssrn`.

- [ ] **Step 3: Verify the 7 tidy guards still correct**

Read each guard at `Template.php`:
- Line 4601: `tidy_parameter('journal')` — guard: `$this->wikiname() !== 'cite ssrn'`
- Line 4747: `tidy_parameter('jstor')` — guard: `$this->wikiname() !== 'cite ssrn'`
- Line 4817: `tidy_parameter('pmc')` — guard: `$this->wikiname() !== 'cite ssrn'`
- Line 4837: `tidy_parameter('pmid')` — guard: `$this->wikiname() !== 'cite ssrn'`
- Line 4601: `tidy_parameter('journal')` tidy — guard: `$this->wikiname() !== 'cite ssrn'`
- Line 6437: `final_tidy()` — guard: `$this->wikiname() !== 'cite ssrn'`
- Line 4276: `tidy_parameter('doi')` — guard: `$this->wikiname() !== 'cite ssrn'`
- Line 2664: `get_doi_from_text()` — guard: `$this->wikiname() !== 'cite ssrn'`

Expected: All 8 guards present and correct.

- [ ] **Step 4: Summarize findings**

Write a brief summary of findings from Steps 1-3. If any unguarded sites found, flag them for further investigation.

---

### Task 7: Review against Wikipedia documentation

**Purpose:** Confirm the implementation matches actual `Template:Cite SSRN` behavior on Wikipedia.

- [ ] **Step 1: Fetch current Template:Cite SSRN documentation**

Fetch: `https://en.wikipedia.org/wiki/Template:Cite_SSRN`
Review: Does the template auto-generate the SSRN link from the `ssrn=` parameter? (Required to safely forget the URL.)

Expected: Yes — the Wikipedia CS1 module for Cite SSRN generates the link `https://papers.ssrn.com/sol3/papers.cfm?abstract_id={id}` from the `ssrn=` parameter. Forgetting the URL is safe because the SSRN link is auto-generated.

- [ ] **Step 2: Verify parameter compatibility**

Check the template documentation for supported parameters:
- `|ssrn=` — required
- `|last1=|first1=` — author format
- `|title=`, `|date=` — metadata
- Does it support `|url=`? (If so, is forgetting it still correct?)

Expected: `cite SSRN` does not have a `|url=` parameter in CS1 — the link comes from `ssrn=`. URL is always redundant.

- [ ] **Step 3: Check the recommended usage note**

The documentation states: *"Once a paper is accepted in a peer-reviewed journal, it is recommended to use [cite journal], as the peer-reviewed status of the article is important, while preserving the SSRN link"*

Confirm our code respects this: `cite journal` with `ssrn=` stays `cite journal`. ✅

---

### Task 8: Investigate other language Wikipedias

**Purpose:** The bot supports multiple Wikipedia languages. Verify SSRN template handling for non-English wikis.

- [ ] **Step 1: Search for language-specific SSRN template names**

Search `src/includes/constants/` and `src/includes/Template.php` for non-English SSRN template mappings:

Run: `Select-String -Path src/includes/constants/*.php -Pattern "ssrn"`  
Read: `src/includes/constants/mistakes.php` lines around 1766-1767 (TEMPLATE_CONVERSIONS)

- [ ] **Step 2: Check language mapping in change_name_to**

Read `Template.php:3476-3486`:
```php
if (!in_array(WIKI_BASE, ENGLISH_WIKI)) {
    foreach (ALL_TEMPLATES_MAP as $map_array) {
        if (in_array(mb_strtolower($this->name), $map_array)) {
            foreach ($map_array as $map_in => $map_out) {
                if ($new_name === $map_out) {
                     $new_name_mapped = $map_in;
                }
            }
        }
    }
}
```

Expected: If a non-English wiki has a different SSRN template name, `ALL_TEMPLATES_MAP` maps it. The `str_replace('ssrn', 'SSRN')` runs on `$new_name_mapped` which is already mapped. No issues.

- [ ] **Step 3: Check ALL_TEMPLATES_MAP for SSRN**

Search for SSRN in ALL_TEMPLATES_MAP definition.

Run: `Select-String -Path src/includes/constants/parameters.php -Pattern "ALL_TEMPLATES_MAP"`
Read the definition to check if `'cite ssrn'` appears.

Expected: If SSRN is not yet in the map, non-English wikis may not correctly map the template name. This is a documentation finding — add to AGENTS.md if needed.

- [ ] **Step 4: Check TEMPLATE_CONVERSIONS for SSRN in other languages**

Read `src/includes/constants/mistakes.php` lines around 1766-1767 for SSRN template conversions.

Expected: Template conversions exist for `'cite ssrn'` → `'cite SSRN'`. These are English wiki conversions (gated by `in_array(WIKI_BASE, ENGLISH_WIKI)`). Non-English wikis may need their own conversions.

- [ ] **Step 5: Summarize cross-language findings**

Note any issues found. If non-English wikis need SSRN template name changes, flag for future work.

---

### Task 9: Review edit summary / user messages

**Purpose:** Verify that user-facing messages about SSRN are accurate after the changes.

- [ ] **Step 1: Find all SSRN-related user messages**

Search for all `report_modification`, `report_action`, `report_info` calls near SSRN code:

Run: `Select-String -Path src/includes/URLtools.php -Pattern "report_.*ssrn|ssrn" -SimpleMatch`

Expected: Only one message at line 2044: `report_modification("Converting URL to SSRN parameter")`. This message is accurate — the URL IS being converted to an SSRN parameter.

- [ ] **Step 2: Check URLtools.php:2044 message accuracy**

```php
if ($template->blank('ssrn')) {
    report_modification("Converting URL to SSRN parameter");
}
```

This message fires when the template doesn't already have an SSRN parameter AND the URL matches the SSRN pattern. It's accurate regardless of whether the URL is later forgotten. No change needed.

- [ ] **Step 3: Check for any new messages needed**

With unconditional URL forgetting, should we add a message like "Removing redundant SSRN URL"? Decision: **No** — the existing "Converting URL to SSRN parameter" already informs the user that the URL is being processed. The URL forgetting is an expected consequence.

- [ ] **Step 4: Verify no stale messages exist**

Check that the old `cite journal` → `cite ssrn` conversion message is not misleading. The original code at URLtools.php:2050 previously logged `change_name_to('cite journal')` (implicit — no explicit message). Our change only affects behavior, not messages. No stale messages.

---

### Task 10: Review documentation needed

**Purpose:** Check if AGENTS.md, inline comments, or other documentation need updating for the new behavior.

- [ ] **Step 1: Check AGENTS.md**

Read `AGENTS.md` for any SSRN-related documentation that may be outdated.

Run: `Select-String -Path AGENTS.md -Pattern "ssrn|SSRN" -SimpleMatch`

Expected: AGENTS.md mentions SSRN in the API table and Template Parameters table. No code-specific documentation to update.

- [ ] **Step 2: Check inline code comments for accuracy**

Review comments in changed code:
- `URLtools.php:2043-2044` — `"Converting URL to SSRN parameter"` — still accurate ✅
- `APIzotero.php:973` — `// ssrn uses this` (on `case 'report':`) — even though Zotero returns `journalArticle` for SSRN now, the comment is historical and harmless. Keep.
- `APIzotero.php:1100-1104` — SSRN author name fix comment — accurate ✅

- [ ] **Step 3: Check README.md or other docs**

Run: `Select-String -Path README.md,docs/*.md -Pattern "ssrn|SSRN" -SimpleMatch`

Expected: No SSRN-specific documentation that needs updating.

---

### Task 11: Full verification

- [ ] **Step 1: Run all SSRN-related tests**

Run: `php vendor/bin/phpunit --filter="testFormatAuthorSrrn|testSrrn|testCiteSrrn|ComprehensiveSSRNTest|testConversionOfURL14|testIDconvert7" --no-coverage`
Expected: OK (19+ tests)

- [ ] **Step 2: Run full non-network test suite**

Run: `php vendor/bin/phpunit --filter="testConversionOfURL|testUrlConversions|testIDconvert|nameToolsTest" --no-coverage`
Expected: OK (all passing)

- [ ] **Step 3: Run code review**

Dispatch a code reviewer subagent with the full diff from master to HEAD. Use the `requesting-code-review` skill:

```bash
BASE_SHA=$(git merge-base master HEAD)
HEAD_SHA=$(git rev-parse HEAD)
```

Dispatch subagent to review:
- All production code changes for correctness
- All test changes for adequacy
- Any remaining unguarded code paths
- Template interaction safety
- Regression risk

Expected: Reviewer identifies 0 Critical issues. Fix any found.

- [ ] **Step 4: Run all CI tests**

Run the full test suite to confirm no regressions:

```bash
php vendor/bin/phpunit --no-coverage
```

Expected: All tests pass (network-dependent tests may be skipped or fail — these are pre-existing and not caused by our changes).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "ssrn: preserve cite journal, forget URL unconditionally

- URLtools.php: remove has_good_free_copy() gate, save originalName
- APIzotero.php: save originalName, only convert cite web to cite ssrn
- UrlToolsTest.php: update 3 tests, add 3 new tests
- ComprehensiveSSRNTest.php: update expectations for unconditional URL forget
- Additional reviews: codebase interaction audit, Wikipedia docs check,
  cross-language wiki review, user messages review, docs review

cite journal with SSRN now stays cite journal (Wikipedia guidance).
cite web with SSRN URL always forgets URL and converts to cite ssrn.
Non-web templates (news, book, etc.) are unaffected."
```

- [ ] **Step 6: Push**

```bash
git push origin fix/ssrn-expansion
```

- [ ] **Step 4: Push**

Run: `git push origin fix/ssrn-expansion`
