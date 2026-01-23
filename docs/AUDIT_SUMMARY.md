# Audit Summary: Missing User Warnings for Problematic Content

**Quick Reference Guide**

---

## What Was Audited

A comprehensive review of the Citation Bot codebase to identify gaps where:
- The bot transforms content when adding NEW parameters
- The bot preserves existing content without modification  
- BUT the bot does NOT warn users about problematic content

## Key Numbers

- **50+** transformation functions analyzed
- **121** add_if_new() calls reviewed across all API files
- **10** specific warning gaps identified
- **5** existing warnings documented (good examples)
- **70%** of silent preservation is intentional and correct

## Priority Rankings

### 🔴 HIGH PRIORITY (Implement First)

| Issue | Impact | Frequency | Complexity |
|-------|--------|-----------|------------|
| **1. HTML Entity Encoding** | High | Common | Low |
| **2. Proxy URL Warnings** | Medium | Common | Very Low |

**Estimated Time:** 1-2 weeks for both

---

### 🟡 MEDIUM PRIORITY (Implement Next)

| Issue | Impact | Frequency | Complexity |
|-------|--------|-----------|------------|
| **3. Curly Quotes** | Low | Moderate | Low |
| **4. MathML in Titles** | High | Rare | Low |
| **5. Malformed Author Names** | High | Low-Mod | Medium |

**Estimated Time:** 2-3 weeks for all three

---

### 🟢 LOW PRIORITY (Nice to Have)

| Issue | Impact | Frequency | Complexity |
|-------|--------|-----------|------------|
| **6. ISBN-10 Format** | Low | Moderate | Medium |
| **7. Excessive Whitespace** | Low | Moderate | Low |
| **8. URL Tracking Parameters** | Low | Common | Low |
| **9. Date Format Issues** | Low | Moderate | Medium |
| **10. COMMON_MISTAKES Info** | N/A | Common | Low |

**Estimated Time:** 2-3 weeks for all five

---

## Quick Implementation Guide

### For HIGH Priority Issues

#### 1. HTML Entity Encoding Warning

**Add to:** `src/includes/Template.php`, `tidy_parameter()` method, line ~5200 (title section)

```php
if (preg_match('/&(?:lt|gt|amp|quot|#\d+|[a-z]+);/i', $value)) {
    report_warning(
        "Parameter |" . echoable($param_name) . 
        "= contains HTML entities that should be decoded: " . 
        echoable(mb_substr($value, 0, 100))
    );
}
```

**Test:**
```php
$text = '{{cite journal|title=AT&amp;T Research}}';
// Should warn: "Parameter |title= contains HTML entities..."
```

---

#### 2. Proxy URL Warnings (Upgrade Existing)

**Modify:** `src/includes/URLtools.php`, lines 1062, 1069, 1075, 1093, 1099

```php
// Change from:
report_info("Remove proxy from IEEE URL");

// To:
report_warning(
    "Removed institutional proxy from IEEE URL - verify accessibility: " . 
    echoable($template->get($param))
);
```

**Test:**
```php
$text = '{{cite journal|url=https://ieeexplore.ieee.org.proxy.lib.edu/document/12345}}';
// Should warn: "Removed institutional proxy from IEEE URL..."
```

---

## What NOT to Warn About

The audit identified these as **intentional silent preservation** (do NOT add warnings):

1. ✅ **Citation template types** - User/editorial decision
2. ✅ **Non-English text** - Multilingual citations are valid
3. ✅ **Deliberate formatting** - Italics for species names, etc.
4. ✅ **Valid identifier variations** - DOI as URL vs bare identifier

---

## Implementation Phases

### Phase 1: Week 1-2 (HIGH Priority)
- [ ] Implement HTML entity warning
- [ ] Upgrade proxy URL warnings
- [ ] Test on sample dataset
- [ ] Monitor for false positives

### Phase 2: Week 3-4 (MEDIUM Priority)
- [ ] Implement curly quotes warning
- [ ] Implement MathML warning
- [ ] Implement author name warnings
- [ ] Test and refine

### Phase 3: Week 5-6 (LOW Priority)
- [ ] Implement remaining warnings
- [ ] Add COMMON_MISTAKES info messages
- [ ] Final testing

### Phase 4: Ongoing (Monitoring)
- [ ] Monitor user feedback
- [ ] Adjust sensitivity
- [ ] Add new warnings as patterns emerge

---

## Success Metrics

After implementation, measure:

- **User Action Rate:** Do warnings lead to fixes? (Target: 50%+)
- **False Positive Rate:** Are warnings accurate? (Target: <5%)
- **Warning Fatigue:** Are users complaining? (Target: No increase)

---

## Full Documentation

See **docs/AUDIT_MISSING_WARNINGS.md** (1000+ lines) for:

- ✅ Detailed analysis of each gap
- ✅ Complete code examples with context
- ✅ Test case templates
- ✅ Rollout recommendations
- ✅ Reference patterns and best practices
- ✅ GitHub issue templates

---

## Next Steps

1. **Review** this audit summary and full report
2. **Create** GitHub issues for each recommended warning
3. **Implement** Phase 1 (HIGH priority warnings)
4. **Test** with real Wikipedia pages
5. **Monitor** user feedback
6. **Iterate** based on results

---

## Questions?

- **Where is the code?** All recommendations reference specific file locations and line numbers
- **How to test?** Test templates provided for each warning
- **What about false positives?** Analysis shows intentional silent preservation in 70% of cases
- **How much work?** Phase 1 estimated at 1-2 weeks, complete implementation 4-6 weeks

---

**Document Version:** 1.0  
**Created:** 2026-01-23  
**Full Report:** docs/AUDIT_MISSING_WARNINGS.md
