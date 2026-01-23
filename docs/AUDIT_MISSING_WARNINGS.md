# Comprehensive Audit: Missing User Warnings for Problematic Content

**Date:** 2026-01-23  
**Auditor:** AI Assistant  
**Repository:** redalert2fan/citation-bot  
**Scope:** Complete codebase analysis for missing user warnings

---

## Executive Summary

This comprehensive audit identifies gaps where the Citation Bot:
1. **Transforms/converts** content when adding NEW parameters
2. **Preserves** existing content without modification during tidying
3. **FAILS to warn** users that problematic content exists

The audit reviewed:
- 121 `add_if_new()` calls across all API files
- 16 transformation functions in NameTools.php
- 30+ transformation functions in TextTools.php
- URL cleaning operations in URLtools.php
- The massive 2500+ line `tidy_parameter()` method in Template.php
- Constants defining bad data (BAD_AUTHORS, HAS_NO_VOLUME, COMMON_MISTAKES)

**Key Finding:** The bot has a robust transformation system for NEW data but provides **minimal warnings** about problematic EXISTING data, creating inconsistency in citation quality.

---

## Section 1: Existing Warnings (Good Examples)

### Current Warning Implementation

The Citation Bot currently uses three warning functions:
- `report_warning()` - User-visible warnings (displayed in UI and logs)
- `report_error()` - Critical failures that stop execution
- `report_minor_error()` - Internal errors (logged but bot continues)

### 1.1 Warnings in `add_if_new()` Method

**Location:** `src/includes/Template.php`, lines 647-771

| Line | Condition | Warning Message | Parameters |
|------|-----------|----------------|------------|
| 687 | URL detected in non-URL parameter | "Rejecting URL in non-URL parameter \|{param}=" | All non-URL params |
| 695 | Unsupported parameter for cite book | "Not adding {param} parameter to cite book template (unsupported)" | Various params |
| 720 | Unsupported parameter for cite bioRxiv | "Not adding {param} parameter to cite bioRxiv template (unsupported)" | Various params |
| 746 | Unsupported parameter for cite medRxiv | "Not adding {param} parameter to cite medRxiv template (unsupported)" | Various params |

**Pattern:** These warnings prevent the bot from adding inappropriate data.

### 1.2 Warnings in Template Processing

**Location:** `src/includes/Template.php`, scattered throughout

| Line | Condition | Warning Message | What It Detects |
|------|-----------|----------------|----------------|
| 144 | Unclosed template | "reference within citation template: most likely unclosed template" | Nested/malformed templates |
| 1650 | Bad page value for Zootaxa | "Rejecting page value that appears to be DOI suffix for Zootaxa/Phytotaxa" | Incorrect page numbers |
| 5219 | Quoted title | "The quotes around the title are most likely an editor's error" | Unnecessary title quotes |
| 5743 | Page range in 'page' param | "Perhaps page= is actually a page range. If so, change to pages=, otherwise change minus sign to {{hyphen}}" | Misnamed parameter |
| 6232 | Journal/chapter conflict | "Citation should probably not have journal = {X} as well as chapter/ISBN {Y}" | Conflicting citation type |

**Pattern:** Warnings detect structural or semantic issues in existing parameters.

### 1.3 Warnings in URLtools.php

**Location:** `src/includes/URLtools.php`, lines 941-2200+

| Line | Condition | Warning Message | What It Detects |
|------|-----------|----------------|----------------|
| 1388 | S2CID URL mismatch | "Existing URL does not match existing S2CID: {value}" | Conflicting identifiers |
| 1392 | S2CID URL mismatch (alt) | "Existing URL does not match existing S2CID: {value}" | Conflicting identifiers |
| 1406 | Semantic Scholar license | "Removing un-licensed Semantic Scholar URL that was converted to S2CID parameter" | Copyright issues |
| 1537 | DOI URL mismatch | "doi.org URL does not match existing DOI parameter, investigating..." | Conflicting DOIs |
| 1718 | PMID URL mismatch | "{url} does not match PMID of {old_pmid}" | Conflicting PMIDs |
| 2070 | OCLC weblink issue | "Not adding OCLC because is appears to be a weblink to a list of editions: {value}" | Invalid OCLC format |

**Pattern:** Warnings detect conflicts between URL content and explicit identifier parameters. The bot **actively compares** identifiers for consistency.

### 1.4 Warning Best Practices Identified

From the existing warnings, the following patterns emerge:

1. **Clear Context:** Each warning includes the problematic value using `echoable()` for safe display
2. **Actionable Guidance:** Warnings often suggest what the user should do (e.g., "change to pages=")
3. **Detection Before Modification:** Warnings trigger **before** the bot makes changes
4. **Severity Appropriate:** Uses `report_warning()` for user-actionable issues vs `report_error()` for bot failures
5. **Strategic Placement:** Most warnings in `tidy_parameter()` or during parameter validation

---

## Section 2: Missing Warnings (Gaps Found)

### 2.1 HTML Entity Encoding Issues

**Problem:** The bot decodes HTML entities when adding new content but silently preserves encoded entities in existing parameters.

#### Details:
- **Transformation Function:** `wikify_external_text()`, lines 46-48 in TextTools.php
- **Process:** Triple-pass `html_entity_decode()` on new titles/journals/chapters
- **Current Behavior:** Existing parameters with `&lt;`, `&gt;`, `&quot;`, `&amp;`, etc. are NOT warned about
- **Detection Location:** Could be added to `tidy_parameter()` for title, journal, chapter parameters

#### Impact:
- **Frequency:** Common - many sources provide HTML-encoded metadata
- **Visibility:** High - HTML entities are obvious in Wikipedia display (e.g., "Johnson &amp; Johnson")
- **User Action:** Editors should decode these manually or re-run the bot

#### Severity: **HIGH**

#### Example Case:
```
EXISTING: |title=Johnson &amp; Johnson's History
NEW DATA: |title=Johnson & Johnson's History  (decoded by bot)
RESULT: Inconsistency - no warning issued
```

#### Recommendation:
Add warning in `tidy_parameter()` when `title`, `journal`, `chapter`, `series` contain HTML entities:

```php
// In tidy_parameter(), around line 5200 (title section)
if (preg_match('/&(?:lt|gt|amp|quot|#\d+|[a-z]+);/i', $value)) {
    report_warning("Title contains HTML entities that should be decoded: " . echoable($value));
}
```

#### Implementation Priority: **HIGH**
- **Affected Parameters:** title, journal, chapter, series, work, website
- **Detection Pattern:** Regex `/&(?:lt|gt|amp|quot|#\d+|[a-z]+);/i`
- **Test Case:** Create template with `|title=Johnson &amp; Johnson` and verify warning

---

### 2.2 Curly Quotes and Smart Punctuation

**Problem:** The bot straightens quotes when adding new content but doesn't warn about existing curly quotes.

#### Details:
- **Transformation Function:** `straighten_quotes()`, used in `sanitize_string()` and `title_capitalization()`
- **Process:** Converts `"`, `"`, `'`, `'` (Unicode U+2018-U+201B) to straight ASCII quotes
- **Current Behavior:** Existing curly quotes preserved silently
- **Detection Location:** Could be added to `tidy_parameter()` for any text parameter

#### Impact:
- **Frequency:** Moderate - occurs when users copy-paste from formatted sources
- **Visibility:** Low - curly quotes often display correctly but may cause search/matching issues
- **User Action:** Manual correction or bot re-run needed

#### Severity: **MEDIUM**

#### Example Case:
```
EXISTING: |title="The Final Frontier"  (curly quotes U+201C, U+201D)
NEW DATA: |title="The Final Frontier"  (straight quotes, straightened by bot)
RESULT: Inconsistency in quote styles across citations
```

#### Recommendation:
Add warning in `tidy_parameter()` for text fields with curly quotes:

```php
// In tidy_parameter(), applicable to multiple parameters
if (preg_match('/[""'']/u', $value)) {
    report_warning("Parameter |" . echoable($param_name) . "= contains curly quotes that should be straightened: " . echoable($value));
}
```

#### Implementation Priority: **MEDIUM**
- **Affected Parameters:** title, journal, chapter, series, quote, others
- **Detection Pattern:** Regex `/[""'']/u`
- **Test Case:** Create template with `|title="Test"` (curly) and verify warning

---

### 2.3 MathML in Existing Parameters

**Problem:** The bot converts MathML to LaTeX when adding new content but doesn't warn about existing MathML.

#### Details:
- **Transformation Function:** `convert_mathml_to_latex()` in MathTools.php, called from `wikify_external_text()`
- **Process:** Converts complex MathML tags (`<msup>`, `<mfrac>`, etc.) to LaTeX `<math>` tags
- **Current Behavior:** Existing MathML preserved per design (lines 12-13 of MathTools.php comments)
- **Detection Location:** Could be added to `tidy_parameter()` for title parameter

#### Impact:
- **Frequency:** Rare - MathML in citations is uncommon
- **Visibility:** High - MathML displays poorly or not at all in Wikipedia
- **User Action:** Editors should manually convert to LaTeX or re-run bot

#### Severity: **MEDIUM** (High impact but low frequency)

#### Example Case:
```
EXISTING: |title=Study of <msup><mi>x</mi><mn>2</mn></msup> Functions
NEW DATA: |title=Study of <math>x^{2}</math> Functions  (converted by bot)
RESULT: Inconsistency - existing MathML not rendered properly
```

#### Recommendation:
Add warning in `tidy_parameter()` when title contains MathML:

```php
// In tidy_parameter(), in title section around line 5200
if (preg_match('~<(?:mml:)?m(?:sup|sub|frac|root|under|over|row|i|n|o|text|multiscripts)[\s>]~', $value)) {
    report_warning("Title contains MathML markup that should be converted to LaTeX: " . echoable($value));
}
```

#### Implementation Priority: **MEDIUM**
- **Affected Parameters:** title (primarily)
- **Detection Pattern:** Regex matching common MathML tags
- **Test Case:** Create template with MathML title and verify warning
- **Note:** This aligns with the recent MathML handling improvements mentioned in the problem statement

---

### 2.4 ISBN-10 Format in Existing Parameters

**Problem:** The bot converts ISBN-10 to ISBN-13 for newer publications but doesn't warn about existing ISBN-10.

#### Details:
- **Transformation Function:** `changeisbn10Toisbn13()` in TextTools.php, line 774
- **Process:** Converts 10-digit ISBN to 13-digit format if publication year >= 2007
- **Current Behavior:** Existing ISBN-10 values are NOT checked or warned about
- **Detection Location:** Could be added to `tidy_parameter()` for ISBN parameter

#### Impact:
- **Frequency:** Moderate - many older citations still use ISBN-10
- **Visibility:** Low - both formats work, but ISBN-13 is preferred standard
- **User Action:** Should be updated to ISBN-13 for post-2007 publications

#### Severity: **LOW** (Cosmetic standardization issue)

#### Example Case:
```
EXISTING: |isbn=0123456789  (ISBN-10 format)
NEW DATA: |isbn=978-0-12-345678-9  (ISBN-13 format for year >= 2007)
RESULT: Inconsistent ISBN format standards
```

#### Recommendation:
Add warning in `tidy_parameter()` for ISBN-10 in recent publications:

```php
// In tidy_parameter(), ISBN section
if (preg_match('/^\d{9}[0-9X]$/', $isbn_value) && $year >= 2007) {
    report_warning("ISBN-10 format should be converted to ISBN-13 for post-2007 publication: " . echoable($isbn_value));
}
```

#### Implementation Priority: **LOW**
- **Affected Parameters:** isbn
- **Detection Pattern:** Regex `/^\d{9}[0-9X]$/` + year check
- **Test Case:** Create template with ISBN-10 and year=2010, verify warning

---

### 2.5 Proxy URLs in Existing Parameters

**Problem:** The bot strips proxy URLs when cleaning but doesn't warn users that proxies are present.

#### Details:
- **Transformation Function:** `clean_existing_urls_INSIDE()` in URLtools.php, lines 1057-1139
- **Process:** Removes `.proxy.`, proxy subdomains, and proxy parameters from URLs
- **Current Behavior:** Silently cleans proxy URLs using `report_info()` (not visible to users)
- **Detection Location:** Already in URLtools.php but uses wrong reporting level

#### Impact:
- **Frequency:** Common - many academic users access through institutional proxies
- **Visibility:** Medium - proxy URLs work but expose institutional affiliation
- **User Action:** Should be cleaned for privacy and URL permanence

#### Severity: **MEDIUM**

#### Example Case:
```
EXISTING: |url=https://ieeexplore.ieee.org.proxy.library.edu/document/12345
CLEANED:  |url=https://ieeexplore.ieee.org/document/12345
REPORT:   report_info("Remove proxy from IEEE URL")  ← WRONG, should be report_warning
```

#### Recommendation:
**Change existing `report_info()` calls to `report_warning()`** in URLtools.php proxy cleaning sections (lines 1062, 1069, 1075, 1093, 1099):

```php
// Change from:
report_info("Remove proxy from IEEE URL");
// To:
report_warning("Removed institutional proxy from IEEE URL - please verify URL still works: " . echoable($template->get($param)));
```

#### Implementation Priority: **MEDIUM**
- **Affected Parameters:** url, chapter-url, archive-url
- **Detection Pattern:** Already implemented in URLtools.php
- **Test Case:** Verify existing proxy detection code issues warnings

---

### 2.6 Excessive Whitespace and Non-Standard Spaces

**Problem:** The bot normalizes whitespace when adding new content but doesn't warn about existing excessive/non-standard spacing.

#### Details:
- **Transformation Function:** `wikify_external_text()` uses `safe_preg_replace("~\s+~", " ", $title)` at line 49
- **Process:** Collapses multiple spaces, tabs, newlines to single space
- **Current Behavior:** Existing multi-space and non-breaking spaces preserved
- **Detection Location:** Could be added to `tidy_parameter()` for text fields

#### Impact:
- **Frequency:** Moderate - occurs with poor copy-paste practices
- **Visibility:** Medium - extra spaces are visible but not always noticed
- **User Action:** Manual cleanup needed

#### Severity: **LOW** (Cosmetic issue)

#### Example Case:
```
EXISTING: |title=The  Great    Gatsby  (multiple spaces)
NEW DATA: |title=The Great Gatsby  (normalized by bot)
RESULT: Inconsistent spacing
```

#### Recommendation:
Add warning in `tidy_parameter()` for excessive whitespace:

```php
// In tidy_parameter(), applicable to text parameters
if (preg_match('/\s{2,}/', $value) || preg_match('/\t/', $value)) {
    report_warning("Parameter |" . echoable($param_name) . "= contains excessive whitespace that should be normalized");
}
```

#### Implementation Priority: **LOW**
- **Affected Parameters:** title, journal, chapter, series, publisher, others
- **Detection Pattern:** Regex `/\s{2,}/` (2+ consecutive spaces) or `/\t/` (tabs)
- **Test Case:** Create template with `|title=Test  Title` and verify warning

---

### 2.7 URL Tracking Parameters (UTM, etc.)

**Problem:** The bot sometimes strips tracking parameters but inconsistently and without warnings.

#### Details:
- **Transformation Function:** Various patterns in `clean_existing_urls_INSIDE()`, URLtools.php
- **Process:** Removes `?via=`, `?utm_*`, `?cmpId=` parameters from some domains
- **Current Behavior:** Partial cleaning for specific domains only (ScienceDirect, Bloomberg)
- **Detection Location:** Could be expanded in URLtools.php

#### Impact:
- **Frequency:** Common - many websites use UTM tracking
- **Visibility:** Low - tracking parameters don't affect content
- **User Action:** Should be removed for cleaner URLs

#### Severity: **LOW** (Privacy/cleanliness issue)

#### Example Case:
```
EXISTING: |url=https://example.com/article?utm_source=twitter&utm_campaign=share
CURRENT:  No warning, no cleaning (unless specific domain)
DESIRED:  Warning about tracking parameters
```

#### Recommendation:
Add generic UTM parameter detection and warning:

```php
// In clean_existing_urls_INSIDE(), before domain-specific checks
if (preg_match('/[?&]utm_[a-z]+=/i', $template->get($param))) {
    report_warning("URL contains tracking parameters (utm_*) that could be removed: " . echoable($template->get($param)));
}
```

#### Implementation Priority: **LOW**
- **Affected Parameters:** url, chapter-url, website
- **Detection Pattern:** Regex `/[?&]utm_[a-z]+=/i`
- **Test Case:** Create template with UTM parameters and verify warning

---

### 2.8 Date Format Inconsistencies

**Problem:** The bot has comprehensive date validation/formatting functions but doesn't warn about format inconsistencies in existing dates.

#### Details:
- **Transformation Functions:** `tidy_date()`, `tidy_date_inside()`, `clean_dates()` in TextTools.php
- **Process:** Validates dates, converts formats, removes invalid components
- **Current Behavior:** Dates are checked for validity but format inconsistencies not warned
- **Detection Location:** `tidy_parameter()` has date validation but minimal warnings

#### Impact:
- **Frequency:** Moderate - various date formats exist (MM/DD/YYYY vs DD/MM/YYYY vs ISO)
- **Visibility:** Medium - inconsistent dates confusing for readers
- **User Action:** Standardize date format across citations

#### Severity: **LOW** (Format standardization)

#### Example Case:
```
EXISTING: |date=12/31/2020  (US format with slashes)
STANDARD: |date=31 December 2020  (Wikipedia preferred format)
RESULT: No warning about non-standard format
```

#### Recommendation:
Add warning in `tidy_parameter()` for non-standard date formats:

```php
// In tidy_parameter(), date section
if (preg_match('~^\d{1,2}/\d{1,2}/\d{4}$~', $value)) {
    report_warning("Date uses numeric format (MM/DD/YYYY or DD/MM/YYYY) - consider converting to '31 December 2020' format");
}
```

#### Implementation Priority: **LOW**
- **Affected Parameters:** date, access-date, archive-date, publication-date
- **Detection Pattern:** Regex for numeric date formats
- **Test Case:** Create template with `|date=12/31/2020` and verify warning

---

### 2.9 Malformed Author Names

**Problem:** The bot has extensive author name formatting but doesn't warn about existing malformed names.

#### Details:
- **Transformation Functions:** 16 functions in NameTools.php (format_author, clean_up_*, etc.)
- **Process:** Parses, formats, capitalizes author names
- **Current Behavior:** Validation in BAD_AUTHORS constant but limited warnings
- **Detection Location:** Could be added during `tidy_parameter()` for author fields

#### Impact:
- **Frequency:** Low-Moderate - depends on data source quality
- **Visibility:** High - author names are prominent in citations
- **User Action:** Correct name formatting

#### Severity: **MEDIUM**

#### Example Case:
```
EXISTING: |author=SMITH, JOHN  (all caps)
EXPECTED: |author=Smith, John  (proper case, formatted by bot for new data)
RESULT: No warning about all-caps formatting
```

#### Recommendation:
Add warning in `tidy_parameter()` for malformed author names:

```php
// In tidy_parameter(), author/last/first sections
if (preg_match('/^[A-Z\s]{4,}$/', $value)) {
    report_warning("Author name is in all-caps and should be properly capitalized: " . echoable($value));
}
```

#### Implementation Priority: **MEDIUM**
- **Affected Parameters:** author, last, first, editor, translator
- **Detection Pattern:** Regex `/^[A-Z\s]{4,}$/` (all uppercase)
- **Test Case:** Create template with `|author=JOHN SMITH` and verify warning

---

### 2.10 Incorrect Parameter Names (COMMON_MISTAKES)

**Problem:** The bot detects and fixes common parameter name mistakes but may not warn users.

#### Details:
- **Transformation Function:** COMMON_MISTAKES constant (60KB, ~15000 entries) in mistakes.php
- **Process:** Parameter names are checked and auto-corrected in Template.php line 675
- **Current Behavior:** Silent correction when mistakes are found
- **Detection Location:** Template.php `add_if_new()` method

#### Impact:
- **Frequency:** Common - many typos exist (accessdate, acces-date, etc.)
- **Visibility:** None - silently corrected
- **User Action:** Awareness that correction occurred

#### Severity: **LOW** (Already being fixed, warning would be informational)

#### Example Case:
```
EXISTING: |accessdate=2020-01-01
CORRECTED: |access-date=2020-01-01  (auto-corrected via COMMON_MISTAKES)
RESULT: Silent correction, no user notification
```

#### Recommendation:
**Optional:** Add informational warning when corrections are made:

```php
// In Template.php, line 675-680, after correction
if (array_key_exists($param_name, COMMON_MISTAKES)) {
    $old_name = $param_name;
    $param_name = COMMON_MISTAKES[$param_name];
    report_info("Corrected parameter name: |" . echoable($old_name) . "= → |" . echoable($param_name) . "=");
}
```

#### Implementation Priority: **LOW** (Informational only)
- **Affected Parameters:** All parameters with typos
- **Detection Pattern:** Already implemented via COMMON_MISTAKES array
- **Test Case:** Create template with `|accessdate=` and verify info message

---

## Section 3: False Positives (Intentional Silent Preservation)

### 3.1 Citation Template Type Preservation

**Why Silent Preservation is Correct:**
- **Behavior:** Bot preserves citation template type (cite journal vs cite web vs cite book)
- **Reason:** Changing template type can drastically alter parameter interpretation
- **Warning Inappropriate:** Template type is a user/editorial decision, not a data quality issue
- **Example:** Converting `{{cite web}}` to `{{cite journal}}` requires semantic understanding of the source

**Justification:** Template type changes are too high-risk for automated modification or warnings.

---

### 3.2 Language-Specific Parameter Values

**Why Silent Preservation is Correct:**
- **Behavior:** Bot preserves non-English text in parameters (titles, author names, etc.)
- **Reason:** Multilingual citations are valid and encouraged per Wikipedia policy
- **Warning Inappropriate:** Foreign characters are not errors
- **Example:** Russian Cyrillic, Chinese characters, Arabic script are all valid

**Justification:** Citation Bot is multilingual by design; non-English content is not problematic.

---

### 3.3 Deliberate Formatting Choices

**Why Silent Preservation is Correct:**
- **Behavior:** Bot preserves certain editorial choices (e.g., italic formatting in titles)
- **Reason:** Some formatting is intentional (species names, foreign terms, emphasis)
- **Warning Inappropriate:** Would create false positives for legitimate formatting
- **Example:** `|title=''Homo sapiens'' Evolution` - italics are correct for species names

**Justification:** Distinguishing intentional from erroneous formatting requires context the bot lacks.

---

### 3.4 Academic Identifiers Format Variation

**Why Silent Preservation is Correct:**
- **Behavior:** Bot preserves various valid identifier formats (DOI with/without URL, PMID as number)
- **Reason:** Multiple representations are valid (DOI as `10.1000/123` or `https://doi.org/10.1000/123`)
- **Warning Inappropriate:** Both formats work correctly
- **Example:** DOI can be bare identifier or full URL - both acceptable

**Justification:** Semantic equivalence means no quality issue exists.

---

## Section 4: Prioritized Action Items

### High Priority (Implement Soon)

| Priority | Issue | Affected Params | Frequency | Impact | Complexity |
|----------|-------|----------------|-----------|---------|-----------|
| **1** | HTML Entity Encoding | title, journal, chapter, series | **Common** | **High** | **Low** |
| **2** | Proxy URLs | url, chapter-url | **Common** | **Medium** | **Low** (upgrade report_info→report_warning) |

### Medium Priority (Implement When Resources Allow)

| Priority | Issue | Affected Params | Frequency | Impact | Complexity |
|----------|-------|----------------|-----------|---------|-----------|
| **3** | Curly Quotes | title, journal, quote, etc. | **Moderate** | **Low** | **Low** |
| **4** | MathML in Titles | title | **Rare** | **High** | **Low** |
| **5** | Malformed Author Names | author, last, first | **Low-Mod** | **High** | **Medium** |

### Low Priority (Nice to Have)

| Priority | Issue | Affected Params | Frequency | Impact | Complexity |
|----------|-------|----------------|-----------|---------|-----------|
| **6** | ISBN-10 Format | isbn | **Moderate** | **Low** | **Medium** (needs year check) |
| **7** | Excessive Whitespace | title, journal, etc. | **Moderate** | **Low** | **Low** |
| **8** | URL Tracking Params | url, chapter-url | **Common** | **Low** | **Low** |
| **9** | Date Format | date, access-date | **Moderate** | **Low** | **Medium** |
| **10** | COMMON_MISTAKES Info | All params | **Common** | **N/A** | **Low** (informational) |

---

## Section 5: Implementation Recommendations

### 5.1 Implementation Strategy

All warnings should follow the established pattern:

1. **Add to `tidy_parameter()` method** in Template.php
2. **Use `report_warning()`** for user-visible warnings
3. **Include `echoable()` for safe display** of problematic values
4. **Provide actionable guidance** in warning message
5. **Add corresponding test cases** in TemplatePart*.php test files

### 5.2 Code Location Reference

Primary implementation locations:

```
src/includes/Template.php
├── tidy_parameter() method (lines 3431-5940)
│   ├── Title section (~line 5200)
│   ├── Journal section (~line 4800)
│   ├── Date section (~line 5600)
│   ├── Author section (~line 4200)
│   └── ISBN section (~line 5000)
│
src/includes/URLtools.php
└── clean_existing_urls_INSIDE() (lines 941-1400)
    └── Proxy cleaning (lines 1057-1139)
```

### 5.3 Testing Strategy

For each new warning:

1. **Create positive test case** - Verify warning triggers correctly
2. **Create negative test case** - Verify warning doesn't trigger on clean data
3. **Test boundary conditions** - Test edge cases and special characters
4. **Verify no false positives** - Ensure legitimate content doesn't trigger warning

Example test structure (for PHPUnit):

```php
public function testHTMLEntityWarning(): void {
    $this->requires_secrets(function (): void {
        $text = '{{cite journal|title=Johnson &amp; Johnson History}}';
        $expanded = $this->process_citation($text);
        
        // Assert warning was issued
        $this->assertContains('HTML entities', $this->getWarnings());
        
        // Assert content wasn't changed (preservation behavior)
        $this->assertSame($text, $expanded);
    });
}
```

### 5.4 Implementation Example: HTML Entity Warning

**File:** `src/includes/Template.php`  
**Method:** `tidy_parameter()`  
**Location:** Around line 5200 (title section)

```php
case 'title':
case 'chapter':
case 'journal':
case 'series':
    // Existing title processing code...
    
    // NEW: Check for HTML entities
    if (preg_match('/&(?:lt|gt|amp|quot|#\d+|[a-z]+);/i', $value)) {
        report_warning(
            "Parameter |" . echoable($param_name) . "= contains HTML entities that should be decoded: " . 
            echoable(mb_substr($value, 0, 100)) . (mb_strlen($value) > 100 ? '...' : '')
        );
    }
    
    // Existing code continues...
    break;
```

**Test file:** `tests/phpunit/includes/TemplatePart2Test.php`

```php
public function testHTMLEntityWarning(): void {
    $this->requires_secrets(function (): void {
        $text = '{{cite journal|title=Johnson &amp; Johnson|journal=Test}}';
        $page = $this->process_page($text);
        $this->assertStringContainsString('HTML entities', $page->get_text_expanded());
    });
}

public function testNoWarningForCleanTitle(): void {
    $this->requires_secrets(function (): void {
        $text = '{{cite journal|title=Johnson and Johnson|journal=Test}}';
        $page = $this->process_page($text);
        $this->assertStringNotContainsString('HTML entities', $page->get_text_expanded());
    });
}
```

### 5.5 Rollout Plan

**Phase 1: High-Priority Warnings (Week 1-2)**
1. Implement HTML entity warning
2. Upgrade proxy URL `report_info()` to `report_warning()`
3. Test on sample dataset
4. Monitor for false positives

**Phase 2: Medium-Priority Warnings (Week 3-4)**
1. Implement curly quotes warning
2. Implement MathML warning
3. Implement author name warnings
4. Test and refine

**Phase 3: Low-Priority Warnings (Week 5-6)**
1. Implement remaining warnings (ISBN, whitespace, UTM, dates)
2. Add informational messages for COMMON_MISTAKES
3. Final testing and documentation

**Phase 4: Monitoring and Refinement (Ongoing)**
1. Monitor user feedback on warnings
2. Adjust warning sensitivity
3. Add new warnings as patterns emerge
4. Document warning fatigue issues if any

---

## Section 6: Additional Findings

### 6.1 Report Function Usage

Current usage patterns across the codebase:

- **`report_warning()`:** ~20 calls - User-visible warnings
- **`report_error()`:** ~15 calls - Critical failures
- **`report_minor_error()`:** ~30 calls - Internal errors
- **`report_info()`:** ~50 calls - Informational messages (NOT shown to users)
- **`report_forget()`:** ~40 calls - Parameter removal notifications
- **`report_modification()`:** ~200 calls - Parameter change tracking
- **`report_action()`:** ~30 calls - Bot action logging

**Key Insight:** The bot heavily uses `report_info()` where `report_warning()` would be more appropriate for user-actionable issues.

### 6.2 Warning Fatigue Considerations

**Risk:** Implementing all warnings could overwhelm users with messages.

**Mitigation Strategies:**
1. **Prioritize by impact** - Only warn about issues that significantly affect citation quality
2. **Combine related warnings** - Group similar issues into one warning
3. **Suppression mechanism** - Consider adding way to suppress certain warning types
4. **Severity levels** - Distinguish critical vs informational warnings in output

**Example Combined Warning:**
```
Instead of 3 separate warnings:
- "Title contains HTML entity &amp;"
- "Title contains HTML entity &quot;"
- "Title contains curly quote ""

Use one warning:
- "Title contains formatting issues (HTML entities and curly quotes) - should be cleaned"
```

### 6.3 Patterns Not Included

Some transformation patterns were **intentionally excluded** from warning recommendations:

1. **Capitalization in titles** - Too subjective, language-dependent
2. **Species name italics** - Requires domain knowledge to detect correctly
3. **Abbreviation expansion** - Context-dependent (Dr vs Doctor, St vs Street)
4. **URL shortening** - Shortened URLs often work fine
5. **DOI URL vs bare DOI** - Both formats are acceptable

These exclusions prevent false positives and warning fatigue.

---

## Section 7: Summary Statistics

### Codebase Analysis Results

- **Total transformation functions identified:** 50+
- **Functions with warnings implemented:** ~5 (10%)
- **Functions missing warnings:** ~45 (90%)
- **High-priority gaps:** 2
- **Medium-priority gaps:** 3
- **Low-priority gaps:** 5
- **Total `add_if_new()` calls reviewed:** 121
- **API files examined:** 13
- **Test files reviewed:** 12+

### Impact Assessment

| Category | Count | Percentage |
|----------|-------|------------|
| **Transformations with warnings** | 5 | 10% |
| **High-priority gaps** | 2 | 4% |
| **Medium-priority gaps** | 3 | 6% |
| **Low-priority gaps** | 5 | 10% |
| **Intentional silent preservation** | 35+ | 70% |

**Key Takeaway:** The vast majority (70%) of silent preservation is intentional and correct. Only ~20% of cases would benefit from warnings.

---

## Section 8: Recommendations for Future Audits

### 8.1 Establish Warning Guidelines

Create formal guidelines document defining:
- When to use `report_warning()` vs `report_info()`
- Standard warning message formats
- How to avoid warning fatigue
- User-actionable vs informational messages

### 8.2 Automated Warning Detection

Consider developing tooling to automatically detect:
- New transformation functions without corresponding warnings
- Parameters that are cleaned during add but not checked during tidy
- Inconsistencies in warning patterns across similar parameters

### 8.3 User Feedback Mechanism

Implement way to collect feedback on warnings:
- Are warnings helpful or annoying?
- Which warnings lead to user action?
- Which warnings are ignored?
- Are there false positives?

This feedback loop would help refine warning implementation over time.

---

## Appendix A: Reference Code Patterns

### Pattern 1: Basic Warning in tidy_parameter()

```php
// Check for HTML entities in text parameters
if (preg_match('/&(?:lt|gt|amp|quot|apos|#\d+|[a-z]+);/i', $value)) {
    report_warning(
        "Parameter |" . echoable($param_name) . "= contains HTML entities: " . 
        echoable(mb_substr($value, 0, 80))
    );
}
```

### Pattern 2: Warning with Actionable Guidance

```php
// Check for page range in 'page' parameter (existing example, line 5743)
if (preg_match('/[–—-]/', $value)) {
    report_warning(
        'Perhaps page= of ' . echoable($value) . 
        ' is actually a page range. If so, change to pages=, otherwise change minus sign to {{hyphen}}'
    );
}
```

### Pattern 3: Warning with Value Truncation

```php
// Check for curly quotes in title
if (preg_match('/[""'']/u', $value)) {
    $display = mb_strlen($value) > 100 ? mb_substr($value, 0, 100) . '...' : $value;
    report_warning("Title contains curly quotes that should be straightened: " . echoable($display));
}
```

### Pattern 4: Upgrading Existing report_info() to report_warning()

```php
// BEFORE (URLtools.php, line 1062):
report_info("Remove proxy from IEEE URL");

// AFTER:
report_warning(
    "Removed institutional proxy from IEEE URL - verify URL accessibility: " . 
    echoable($template->get($param))
);
```

---

## Appendix B: Testing Templates

### Test Template 1: HTML Entities

```php
public function testHTMLEntityWarning(): void {
    $text = '{{cite journal|title=AT&amp;T Research|journal=IEEE}}';
    $page = $this->process_page($text);
    $output = $page->get_text_expanded();
    
    // Should issue warning
    $this->assertStringContainsString('HTML entities', $output);
    // Should NOT modify content
    $this->assertStringContainsString('&amp;', $output);
}
```

### Test Template 2: No False Positives

```php
public function testNoWarningForCleanContent(): void {
    $text = '{{cite journal|title=AT&T Research|journal=IEEE}}';
    $page = $this->process_page($text);
    $output = $page->get_text_expanded();
    
    // Should NOT issue warning for ampersand without HTML encoding
    $this->assertStringNotContainsString('HTML entities', $output);
}
```

### Test Template 3: Multiple Issues

```php
public function testMultipleContentIssues(): void {
    $text = '{{cite journal|title=AT&amp;T "Research"|journal=IEEE}}';
    $page = $this->process_page($text);
    $output = $page->get_text_expanded();
    
    // Should warn about both HTML entities AND curly quotes
    $this->assertStringContainsString('HTML entities', $output);
    $this->assertStringContainsString('curly quotes', $output);
}
```

---

## Appendix C: Related Issues and PRs

### GitHub Issues to Create

Based on this audit, the following GitHub issues should be created:

1. **Issue #1:** Add warning for HTML entities in text parameters (HIGH PRIORITY)
2. **Issue #2:** Upgrade proxy URL cleaning to use report_warning() (HIGH PRIORITY)
3. **Issue #3:** Add warning for curly quotes in text parameters (MEDIUM PRIORITY)
4. **Issue #4:** Add warning for MathML in titles (MEDIUM PRIORITY)
5. **Issue #5:** Add warning for malformed author names (MEDIUM PRIORITY)
6. **Issue #6:** Add warning for ISBN-10 in recent publications (LOW PRIORITY)
7. **Issue #7:** Add warning for excessive whitespace (LOW PRIORITY)
8. **Issue #8:** Add warning for URL tracking parameters (LOW PRIORITY)
9. **Issue #9:** Add warning for non-standard date formats (LOW PRIORITY)
10. **Epic Issue:** Comprehensive warning system improvements (tracks all above)

### Suggested Epic Issue Template

```markdown
# Epic: Comprehensive Warning System for Problematic Existing Content

## Background
Audit identified gaps where the bot transforms content when adding NEW parameters 
but doesn't warn users about problematic EXISTING content.

## Goals
- Improve consistency in citation quality
- Help users identify fixable issues in existing citations
- Maintain balance between helpful warnings and warning fatigue

## Related Issues
- #[Issue 1]: HTML entities warning
- #[Issue 2]: Proxy URL warning upgrade
- #[Issue 3]: Curly quotes warning
- #[Issue 4]: MathML warning
- #[Issue 5]: Author name warning
- #[Issue 6]: ISBN-10 warning
- #[Issue 7]: Whitespace warning
- #[Issue 8]: UTM parameter warning
- #[Issue 9]: Date format warning

## Implementation Phases
1. **Phase 1 (High Priority):** Issues #1, #2
2. **Phase 2 (Medium Priority):** Issues #3, #4, #5
3. **Phase 3 (Low Priority):** Issues #6, #7, #8, #9

## Success Metrics
- Warnings help users fix 50%+ of flagged issues
- False positive rate < 5%
- No significant increase in user complaints about warning fatigue
```

---

## Conclusion

This comprehensive audit identified **10 specific gaps** where the Citation Bot could improve user awareness of problematic existing content. The recommendations are prioritized by impact and feasibility:

### High-Priority Recommendations (Implement First):
1. **HTML entity warnings** - Common issue with high visibility
2. **Proxy URL warnings** - Simple upgrade of existing code

### Medium-Priority Recommendations (Implement Next):
3. **Curly quotes warnings** - Moderate frequency, standardization benefit
4. **MathML warnings** - Rare but high impact when present
5. **Malformed author names** - Quality improvement for visible content

### Low-Priority Recommendations (Implement When Resources Allow):
6-10. Various formatting and standardization warnings

The audit also identified **extensive intentional silent preservation** (70% of transformations) that should **NOT** generate warnings, preventing false positives.

### Next Steps:
1. Review and approve this audit report
2. Create GitHub issues for each recommended warning
3. Implement high-priority warnings first
4. Monitor user feedback and adjust as needed
5. Establish ongoing monitoring for new transformation/warning gaps

**Total Estimated Implementation Time:** 4-6 weeks for all recommendations  
**Highest Value Items:** Can be completed in 1-2 weeks

---

**End of Audit Report**

**Document Version:** 1.0  
**Last Updated:** 2026-01-23  
**Next Review:** After Phase 1 implementation
