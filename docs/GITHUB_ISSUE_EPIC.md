# Epic: Comprehensive Warning System for Problematic Existing Content

## Background

A comprehensive audit (see `docs/AUDIT_MISSING_WARNINGS.md`) identified gaps where the Citation Bot:
1. Transforms content when adding NEW parameters via `add_if_new()`
2. Preserves existing content without modification during tidying operations
3. **Does NOT warn users** that problematic content exists in their citations

This creates inconsistency: the bot fixes issues in new data but silently ignores the same issues in existing data.

## Problem Statement

Currently, the bot:
- Decodes HTML entities when adding new titles → but doesn't warn about existing `&amp;`, `&lt;`, etc.
- Straightens curly quotes when adding new text → but doesn't warn about existing `""` quotes
- Converts MathML to LaTeX when adding new titles → but doesn't warn about existing `<msup>` tags
- Removes proxy URLs when cleaning → but uses `report_info()` instead of visible warnings
- Formats author names when adding new authors → but doesn't warn about existing all-caps names

**Result:** Citations contain a mix of clean and problematic content, reducing overall quality.

## Goals

1. **Improve citation consistency** - Help users identify and fix existing issues
2. **Balance warnings** - Avoid warning fatigue while providing actionable feedback
3. **Follow existing patterns** - Use established warning mechanisms
4. **Prioritize impact** - Implement high-frequency, high-impact warnings first

## Audit Statistics

- **50+** transformation functions analyzed
- **121** `add_if_new()` calls reviewed
- **10** specific warning gaps identified
- **5** existing warnings documented as good examples
- **70%** of silent preservation is intentional and correct (no action needed)

## Implementation Plan

### Phase 1: High-Priority Warnings (Week 1-2)
- [ ] #[TBD] Add warning for HTML entities in text parameters
- [ ] #[TBD] Upgrade proxy URL cleaning to use `report_warning()`

**Impact:** Common issues with high visibility  
**Effort:** 1-2 weeks  
**Complexity:** Low

### Phase 2: Medium-Priority Warnings (Week 3-4)
- [ ] #[TBD] Add warning for curly quotes in text parameters
- [ ] #[TBD] Add warning for MathML in titles
- [ ] #[TBD] Add warning for malformed author names (all-caps, etc.)

**Impact:** Moderate frequency or high impact when present  
**Effort:** 2-3 weeks  
**Complexity:** Low to Medium

### Phase 3: Low-Priority Warnings (Week 5-6)
- [ ] #[TBD] Add warning for ISBN-10 in recent publications
- [ ] #[TBD] Add warning for excessive whitespace
- [ ] #[TBD] Add warning for URL tracking parameters (UTM, etc.)
- [ ] #[TBD] Add warning for non-standard date formats
- [ ] #[TBD] Add informational messages for COMMON_MISTAKES corrections

**Impact:** Lower priority standardization issues  
**Effort:** 2-3 weeks  
**Complexity:** Low to Medium

## Success Criteria

After implementation:
- ✅ Warnings help users fix **50%+** of flagged issues
- ✅ False positive rate **< 5%**
- ✅ No significant increase in user complaints about warning fatigue
- ✅ All warnings follow established patterns and best practices
- ✅ Test coverage for each new warning

## Documentation

- **Full Audit Report:** `docs/AUDIT_MISSING_WARNINGS.md` (1000+ lines)
- **Quick Summary:** `docs/AUDIT_SUMMARY.md`
- **Implementation Examples:** Code snippets in audit report
- **Test Templates:** PHPUnit test examples in audit report

## Testing Strategy

For each warning:
1. ✅ Positive test - Warning triggers correctly
2. ✅ Negative test - Warning doesn't trigger on clean data
3. ✅ Boundary test - Edge cases handled properly
4. ✅ No false positives - Legitimate content doesn't trigger warning

## Monitoring Plan

After each phase:
1. Monitor user feedback on talk page
2. Track warning → fix conversion rate
3. Identify false positives and refine detection
4. Adjust warning messages for clarity

## Related Documentation

- Existing warning patterns: See Section 1 of `docs/AUDIT_MISSING_WARNINGS.md`
- Transformation functions: See Section 2 of audit report
- Best practices: See Section 5 (Implementation Recommendations)

## Timeline

- **Phase 1 (HIGH):** 1-2 weeks
- **Phase 2 (MEDIUM):** 2-3 weeks  
- **Phase 3 (LOW):** 2-3 weeks
- **Total:** 4-6 weeks for complete implementation

## Risk Assessment

**Low Risk:**
- All changes are additive (warnings only, no behavior changes)
- Following established warning patterns
- Comprehensive test coverage
- Gradual rollout by priority

**Mitigation:**
- Start with highest-priority items
- Monitor user feedback closely
- Easy to disable/adjust warnings if issues arise

## Next Steps

1. ✅ Review and approve this epic
2. Create individual issues for each warning (linked above)
3. Implement Phase 1 (HIGH priority)
4. Gather feedback and iterate
5. Continue with Phases 2 and 3

---

**Labels:** enhancement, documentation, quality-improvement, epic  
**Milestone:** Citation Quality Improvements  
**Priority:** High (Phase 1), Medium (Phase 2), Low (Phase 3)
