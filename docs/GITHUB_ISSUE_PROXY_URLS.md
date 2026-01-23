# Issue: Upgrade Proxy URL Cleaning to Use report_warning()

## Priority: HIGH

## Problem

The Citation Bot removes institutional proxy information from URLs during cleaning, but uses `report_info()` instead of `report_warning()`. This means users are NOT notified that proxy URLs were detected and cleaned.

### Current Behavior

**File:** `src/includes/URLtools.php`  
**Function:** `clean_existing_urls_INSIDE()`  
**Lines:** 1062, 1069, 1075, 1093, 1099

```php
// Line 1062
if (preg_match("~^https?://ieeexplore.ieee.org.+proxy.*/document/(.+)$~", ...)) {
    report_info("Remove proxy from IEEE URL");  // ❌ Not visible to users
    $template->set($param, 'https://ieeexplore.ieee.org/document/' . $matches[1]);
}

// Line 1069
} elseif (preg_match("~^https?://(?:www.|)oxfordhandbooks.com.+proxy.*/view/(.+)$~", ...)) {
    report_info("Remove proxy from Oxford Handbooks URL");  // ❌ Not visible to users
}

// Line 1075
} elseif (preg_match("~^https?://(?:www.|)oxfordartonline.com.+proxy.*/view/(.+)$~", ...)) {
    report_info("Remove proxy from Oxford Art URL");  // ❌ Not visible to users
}

// Line 1093
} elseif (preg_match("~^https?://(?:login\.|)(?:lib|)proxy\.[^\?\/]+\/login\?q?url=...~", ...)) {
    report_info("Remove proxy from URL");  // ❌ Not visible to users
}

// Line 1099
} elseif (preg_match("~^https?://(?:login\.|)(?:lib|)proxy\.[^\?\/]+\/login\?q?url=...~i", ...)) {
    report_info("Remove proxy from URL");  // ❌ Not visible to users
}
```

### Why This Matters

- **Privacy:** Proxy URLs expose institutional affiliation
- **Permanence:** Proxy URLs may break if institutional access changes
- **Consistency:** Other URL issues use `report_warning()` (see lines 1388, 1537, 1718)
- **User Awareness:** Editors should know proxies were detected and removed

### Example

```wiki
{{cite journal
|url=https://ieeexplore.ieee.org.proxy.library.edu/document/12345
|title=Test Article
}}
```

**Current:** Silently cleaned to `https://ieeexplore.ieee.org/document/12345`  
**User sees:** `report_info()` message in logs (not visible in UI)  
**Desired:** User-visible warning that proxy was detected and cleaned

## Impact

- **Frequency:** Common - many academic users access through institutional proxies
- **Visibility:** Medium - proxy URLs work but expose institutional affiliation
- **User Action:** Should verify cleaned URL still works; aware of the change

## Proposed Solution

Change `report_info()` calls to `report_warning()` with improved messaging.

### Implementation

**File:** `src/includes/URLtools.php`  
**Function:** `clean_existing_urls_INSIDE()`

#### Change 1: IEEE URLs (Line 1062)

```php
// BEFORE:
report_info("Remove proxy from IEEE URL");

// AFTER:
report_warning(
    "Removed institutional proxy from IEEE URL - verify URL still accessible: " . 
    echoable($template->get($param))
);
```

#### Change 2: Oxford Handbooks (Line 1069)

```php
// BEFORE:
report_info("Remove proxy from Oxford Handbooks URL");

// AFTER:
report_warning(
    "Removed institutional proxy from Oxford Handbooks URL - verify URL still accessible: " . 
    echoable($template->get($param))
);
```

#### Change 3: Oxford Art (Line 1075)

```php
// BEFORE:
report_info("Remove proxy from Oxford Art URL");

// AFTER:
report_warning(
    "Removed institutional proxy from Oxford Art URL - verify URL still accessible: " . 
    echoable($template->get($param))
);
```

#### Change 4: Generic Proxy (Line 1093)

```php
// BEFORE:
report_info("Remove proxy from URL");

// AFTER:
report_warning(
    "Removed institutional proxy from URL - verify URL still accessible: " . 
    echoable($template->get($param))
);
```

#### Change 5: Generic Proxy URL-encoded (Line 1099)

```php
// BEFORE:
report_info("Remove proxy from URL");

// AFTER:
report_warning(
    "Removed institutional proxy from URL - verify URL still accessible: " . 
    echoable($template->get($param))
);
```

### Additional Context

Note: Line 1080 (ScienceDirect) also has `report_info()` but may be better suited as `report_info()` since it's removing non-proxy junk from URLs. Consider reviewing case-by-case.

## Testing

### Test Case 1: IEEE Proxy Warning

```php
public function testIEEEProxyWarning(): void {
    $text = '{{cite journal|url=https://ieeexplore.ieee.org.proxy.lib.edu/document/12345}}';
    $page = $this->process_page($text);
    $output = $page->get_text_expanded();
    
    // Should issue warning
    $this->assertStringContainsString('institutional proxy', $output);
    $this->assertStringContainsString('IEEE', $output);
    
    // Should clean URL
    $this->assertStringContainsString('ieeexplore.ieee.org/document/12345', $output);
    $this->assertStringNotContainsString('proxy', $output);
}
```

### Test Case 2: Oxford Handbooks Proxy Warning

```php
public function testOxfordHandbooksProxyWarning(): void {
    $text = '{{cite journal|url=https://www.oxfordhandbooks.com.proxy.lib.edu/view/test}}';
    $page = $this->process_page($text);
    $output = $page->get_text_expanded();
    
    // Should issue warning
    $this->assertStringContainsString('institutional proxy', $output);
    $this->assertStringContainsString('Oxford', $output);
}
```

### Test Case 3: Generic Proxy Warning

```php
public function testGenericProxyWarning(): void {
    $text = '{{cite journal|url=https://proxy.library.edu/login?url=https://example.com/article}}';
    $page = $this->process_page($text);
    $output = $page->get_text_expanded();
    
    // Should issue warning
    $this->assertStringContainsString('institutional proxy', $output);
    
    // Should clean URL
    $this->assertStringContainsString('example.com/article', $output);
}
```

### Test Case 4: No False Positives

```php
public function testNoWarningForCleanURL(): void {
    $text = '{{cite journal|url=https://ieeexplore.ieee.org/document/12345}}';
    $page = $this->process_page($text);
    $output = $page->get_text_expanded();
    
    // Should NOT issue proxy warning
    $this->assertStringNotContainsString('proxy', $output);
}
```

## Related Issues

- Part of Epic: #[Epic Issue Number]
- Related to: URL cleaning operations, privacy improvements

## References

- **Audit Report:** `docs/AUDIT_MISSING_WARNINGS.md`, Section 2.5
- **Existing Warning Pattern:** See URLtools.php lines 1388, 1537, 1718 for similar warnings
- **Code Location:** `src/includes/URLtools.php`, `clean_existing_urls_INSIDE()`, lines 1057-1139

## Acceptance Criteria

- [ ] All `report_info()` calls for proxy cleaning changed to `report_warning()`
- [ ] Warning messages include context (which site) and cleaned URL
- [ ] URL cleaning behavior unchanged (only reporting level changes)
- [ ] Test cases added and passing
- [ ] No false positives on clean URLs
- [ ] Documentation updated if needed

## Estimated Effort

**Time:** 1-2 hours  
**Complexity:** Very Low (simple replacement of function calls)  
**Files to Modify:** 
- `src/includes/URLtools.php` (change 5 `report_info()` calls to `report_warning()`)
- `tests/phpunit/includes/URLtools*.php` or similar (add test cases if not already covered)

## Notes

This is an **upgrade of existing functionality**, not new feature development. The proxy detection code already works correctly; we're just making the notifications visible to users.

---

**Labels:** enhancement, warning-system, high-priority, quick-win  
**Milestone:** Phase 1 - High Priority Warnings  
**Related:** Epic Issue #[TBD]
