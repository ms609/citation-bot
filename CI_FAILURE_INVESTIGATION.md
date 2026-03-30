# Bot Full Test Suite — CI Failure Investigation

**Analysis period:** 2026-02-07 to 2026-03-30  
**Runs analyzed:** 20  
**All 20 runs failed.**

---

## Summary Table

| Test | Failures | Rate | Root Cause Category |
|------|----------|------|---------------------|
| `pubmedTest::testPMCExpansion2` | 20/20 | 100% | PubMed API data change |
| `pageTest::testUrlReferencesAAAAA` | 20/20 | 100% | PubMed API format change |
| `S2apiTest::testSemanticscholar3` | 15/20 | 75% | Semantic Scholar data removed |
| `S2apiTest::testSemanticscholar2` | 15/20 | 75% | Semantic Scholar data changed |
| `unpaywallApiTest::testUnPaywall1` | 14/20 | 70% | Unpaywall data change |
| `S2apiTest::testS2CIDlicenseTrue2` | 11/20 | 55% | Semantic Scholar license change |
| `S2apiTest::testSemanticscholar5` | 10/20 | 50% | Semantic Scholar data removed |
| `S2apiTest::testSemanticscholar42` | 10/20 | 50% | Semantic Scholar behavior change |
| `DoiTest::testExpansion_doi_not_from_crossref_kisti_journal` | 9/20 | 45% | KISTI DOI resolver changed |
| `DoiTest::testExpansion_doi_not_from_crossref_fISTIC_Data` | 8/20 | 40% | mEDRA DOI resolver changed |
| `pubmedTest::testGetPMIDwitNoDOIorJournal` | 5/20 | 25% | PubMed search intermittent |
| `pubmedTest::testDoi2PMID` | 3/20 | 15% | PubMed intermittent |
| `TemplatePart3Test::testHandles1/2/4` | 2/20 | 10% | Handle.net intermittent |
| `arxivTest::testArxivExpansion` | 2/20 | 10% | arXiv API intermittent |
| `TemplatePart1Test::testLongAuthorLists` | 2/20 | 10% | API intermittent |
| `pubmedTest::testPMC2PMID` | 2/20 | 10% | PubMed intermittent |
| Others (each 1/20) | 1/20 | 5% | One-off API failures |

---

## Tier 1: Failed in Every Run (100%)

### 1. `pubmedTest::testPMCExpansion2`

- **File:** `tests/phpunit/includes/api/pubmedTest.php:39`
- **Test asserts:** `wikiname()` == `'cite web'` after processing a PMC PDF URL
- **Actual result:** `wikiname()` == `'cite journal'`
- **Input:** `{{Cite web | url = https://www.ncbi.nlm.nih.gov/pmc/articles/PMC2491514/pdf/annrcse01476-0076.pdf}}`
- **Root cause:** PubMed now returns richer metadata for PMC2491514 (DOI, journal, title, authors). The bot detects sufficient metadata to promote the template from `cite web` to `cite journal`, which is actually correct behavior. The test was written when PubMed returned no metadata for PDF-direct URLs and needs updating to reflect current PubMed API responses.

### 2. `pageTest::testUrlReferencesAAAAA`

- **File:** `tests/phpunit/includes/pageTest.php:275`
- **Test asserts:** `pages` == `'S256–S262'` (en-dash, full page range) for PMID 23858264
- **Actual result:** `pages` == `'S256-62'` (ASCII hyphen, abbreviated range)
- **Article:** "Indian religious concepts on sexuality and marriage", Indian Journal of Psychiatry, PMC3705692
- **Root cause:** PubMed changed its page number representation for this article from the full range `S256–S262` to the abbreviated form `S256-62`. This is a data change in the PubMed database, not a code change.

---

## Tier 2: Failed in 50–75% of Runs

### 3. `S2apiTest::testSemanticscholar3` (75%)

- **Test asserts:** DOI `10.1007/978-3-540-78646-7_75` is found for S2CID 1090322
- **Actual result:** DOI is `null`
- **Root cause:** Semantic Scholar API no longer returns a DOI for this book chapter. The record was updated or the paper's metadata was changed in the S2 database.

### 4. `S2apiTest::testSemanticscholar2` (75%)

- **Test asserts:** DOI `10.1046/j.1365-2699.1999.00329.x` is returned (one test variant), or URL is null (another)
- **Actual result:** DOI returned as `''` (empty string) and/or URL is not null
- **Root cause:** Semantic Scholar changed its response for the paper at URL `https://www.semanticscholar.org/paper/The-Holdridge.../406120529d...`. DOI data was removed from the record, and a URL is now being returned where the test expects none.

### 5. `unpaywallApiTest::testUnPaywall1` (70%)

- **File:** `tests/phpunit/includes/api/unpaywallApiTest.php:47`
- **Test asserts:** `get_unpaywall_url()` returns non-null for DOI `10.1206/0003-0082(2006)3508[1:EEALSF]2.0.CO;2`
- **Actual result:** Returns `null`
- **Root cause:** Unpaywall (Impactstory) database no longer lists a free-access URL for this Biodiversity Heritage Library (BHL) article. The Unpaywall entry for this DOI was removed or the link status was changed.

### 6. `S2apiTest::testS2CIDlicenseTrue2` (55%)

- **Test asserts:** `get_semanticscholar_license('73436496')` returns `true` (open access)
- **Actual result:** Returns `false`
- **Root cause:** Semantic Scholar changed the open-access/license status of S2CID 73436496. The paper is no longer reported as having an open-access license.

### 7. `S2apiTest::testSemanticscholar5` (50%)

- **Same root cause as #3** (same S2CID 1090322, different assertion approach)

### 8. `S2apiTest::testSemanticscholar42` (50%)

- **Test asserts:** URL is `null` after processing (URL should be removed when PMC=32414 is found)
- **Actual result:** URL is not null (URL is kept)
- **Root cause:** Semantic Scholar API behavior change — either the PMC/URL deduplication no longer triggers, or the API response structure changed so the URL is no longer recognized as a duplicate of the PMC record.

---

## Tier 3: DOI Resolver Changes (~40–45%)

### 9. `DoiTest::testExpansion_doi_not_from_crossref_kisti_journal` (9/20)

- **Affected runs:** Feb 10 – Mar 21 (runs 665–688); NOT in runs after Mar 27
- **Test asserts:** Korean journal metadata returned for DOI `10.4017/gt.2011.09.2.014.0`
- **Actual result:** Empty `{{Cite journal}}` — no metadata retrieved
- **Root cause:** The KISTI (Korea Institute of Science and Technology Information) DOI resolver stopped returning metadata for this specific article. The test expected data that the KISTI API was providing but has since stopped providing.

### 10. `DoiTest::testExpansion_doi_not_from_crossref_fISTIC_Data` (8/20)

- **Affected runs:** Mar 27 onwards (runs 711–767); NOT in earlier runs
- **Test asserts:** Empty `{{Cite journal}}` — no metadata for this Chinese data DOI
- **Actual result:** Chinese metadata IS returned (author, title, etc.)
- **DOI:** `10.3972/water973.0145.db` (Chinese scientific data DOI)
- **Root cause:** A DOI resolver (likely mEDRA or DataCite) started returning metadata for this data DOI around Mar 27. The test expected this DOI to return no data, but the Chinese data repository began providing CrossRef-compatible metadata.

---

## Tier 4: Intermittent Failures (<25%)

### 11. `pubmedTest::testGetPMIDwitNoDOIorJournal` (5/20, 25%)

- **Test asserts:** PMID `30741529` found via title-only search
- **Root cause:** PubMed's E-utilities search API intermittently fails to find the PMID for title-only searches (no DOI or journal filter). This is a flaky network/rate-limiting issue.

### 12. `pubmedTest::testDoi2PMID` (3/20, 15%)

- **Root cause:** PubMed intermittently fails to return PMC ID `58796` for DOI `10.1073/pnas.171325998`. API rate limiting or transient PubMed downtime.

### 13. `TemplatePart3Test::testHandles1/2/4` (2/20, 10% each)

- **Root cause:** Handle.net server (hdl.handle.net) intermittent unavailability or rate limiting. The tests look up HDL `10125/20269`.

### 14. `arxivTest::testArxivExpansion` (2/20, 10%)

- **Root cause:** arXiv API intermittent failures. The API was unreachable or rate-limited during those two runs.

### 15. Miscellaneous one-off failures (1/20 each)

- `gadgetapiTest::testGadget` — one-off gadget API network issue
- `pageTest::testUrlReferencesWithText5/16` — transient network issue
- `TemplatePart1Test::testLongAuthorLists` — S2/PubMed call failure
- `pubmedTest::testPMC2PMID` — PubMed transient
- `archiveTest::testUseArchive1` — Weimarpedia archive lookup failed
- `DoiTest::testExpansion_doi_not_from_crossref_mEDRA_Journal/Monograph` — mEDRA API changed

---

## Root Cause Categories

| Category | Tests Affected | Most Likely Fix |
|----------|---------------|-----------------|
| **PubMed API data change** (permanent) | `testPMCExpansion2`, `testUrlReferencesAAAAA` | Update test expected values |
| **Semantic Scholar API data change** (permanent) | `testSemanticscholar2/3/5/42`, `testS2CIDlicenseTrue2` | Update test expected values or use stable identifiers |
| **Unpaywall data change** (permanent) | `testUnPaywall1` | Update DOI used in test or mark as skip |
| **KISTI DOI resolver change** (permanent, fixed by ~Mar 27) | `testExpansion_doi_not_from_crossref_kisti_journal` | Investigate new KISTI behavior; update test |
| **mEDRA/DataCite DOI data change** (permanent, appeared ~Mar 27) | `testExpansion_doi_not_from_crossref_fISTIC_Data` | Update test to match new API behavior |
| **PubMed intermittent** | `testGetPMIDwitNoDOIorJournal`, `testDoi2PMID`, `testPMC2PMID` | Add retry logic or mark as skippable |
| **Other API intermittent** | `testHandles*`, `testArxivExpansion`, others | Inherent test fragility; consider mocking |

---

## Key Finding

The **most common and consistent** failures are caused by **external API data changes**, not by any code regression. The two tests failing in 100% of runs (`testPMCExpansion2` and `testUrlReferencesAAAAA`) are caused by PubMed API responses that have permanently changed since the tests were written. These two tests alone block CI on every single push.
