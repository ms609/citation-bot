# Issue: Add Warning for HTML Entities in Text Parameters

## Priority: HIGH

## Problem

The Citation Bot decodes HTML entities (triple-pass `html_entity_decode()`) when adding NEW parameter values via `wikify_external_text()`, but does NOT warn users about existing parameters that contain HTML entities.

### Current Behavior

**When adding new data:**
```php
// In wikify_external_text() (TextTools.php, lines 46-48)
$title = html_entity_decode($title, ENT_COMPAT | ENT_HTML401, "UTF-8");
$title = html_entity_decode($title, ENT_COMPAT | ENT_HTML401, "UTF-8");
$title = html_entity_decode($title, ENT_COMPAT | ENT_HTML401, "UTF-8");
```

**When tidying existing data:**
- HTML entities are preserved silently
- No warning issued to user

### Example

```wiki
{{cite journal
|title=Johnson &amp; Johnson's History
|journal=IEEE &lt;Research&gt;
}}
```

**Display:** "Johnson & Johnson's History" in article (browsers decode entities)  
**Problem:** Inconsistent - bot would decode these if adding new, but doesn't warn about existing

## Impact

- **Frequency:** Common - many sources provide HTML-encoded metadata
- **Visibility:** High - HTML entities sometimes display incorrectly or look unprofessional
- **User Action:** Should be decoded for consistency and proper display

## Proposed Solution

Add warning in `tidy_parameter()` method when title, journal, chapter, or series contain HTML entities.

### Implementation

**File:** `src/includes/Template.php`  
**Method:** `tidy_parameter()`  
**Location:** Around line 5200 (title section), and similar sections for journal, chapter, series

```php
case 'title':
case 'chapter':
case 'journal':
case 'series':
    // Existing title processing code...
    
    // NEW: Check for HTML entities
    if (preg_match('/&(?:lt|gt|amp|quot|apos|#\d+|[a-z]+);/i', $value)) {
        report_warning(
            "Parameter |" . echoable($param_name) . 
            "= contains HTML entities that should be decoded: " . 
            echoable(mb_substr($value, 0, 100)) . 
            (mb_strlen($value) > 100 ? '...' : '')
        );
    }
    
    // Existing code continues...
    break;
```

### Detection Pattern

**Regex:** `/&(?:lt|gt|amp|quot|apos|#\d+|[a-z]+);/i`

**Matches:**
- `&lt;` (less than)
- `&gt;` (greater than)
- `&amp;` (ampersand)
- `&quot;` (quote)
- `&apos;` (apostrophe)
- `&#123;` (numeric entities)
- Any other named HTML entity

## Testing

### Test Case 1: Warning Triggers

```php
public function testHTMLEntityWarning(): void {
    $this->requires_secrets(function (): void {
        $text = '{{cite journal|title=AT&amp;T Research|journal=Test}}';
        $page = $this->process_page($text);
        $output = $page->get_text_expanded();
        
        // Should issue warning
        $this->assertStringContainsString('HTML entities', $output);
        
        // Should NOT modify content (preservation behavior)
        $this->assertStringContainsString('&amp;', $output);
    });
}
```

### Test Case 2: No False Positives

```php
public function testNoWarningForCleanTitle(): void {
    $this->requires_secrets(function (): void {
        $text = '{{cite journal|title=AT&T Research|journal=Test}}';
        $page = $this->process_page($text);
        $output = $page->get_text_expanded();
        
        // Should NOT issue warning (ampersand without encoding is fine)
        $this->assertStringNotContainsString('HTML entities', $output);
    });
}
```

### Test Case 3: Multiple Entities

```php
public function testMultipleHTMLEntities(): void {
    $this->requires_secrets(function (): void {
        $text = '{{cite journal|title=&lt;Research&gt; &amp; Development}}';
        $page = $this->process_page($text);
        $output = $page->get_text_expanded();
        
        // Should issue warning
        $this->assertStringContainsString('HTML entities', $output);
    });
}
```

## Related Issues

- Part of Epic: #[Epic Issue Number]
- Related to: Curly quotes warning, MathML warning (similar pattern)

## References

- **Audit Report:** `docs/AUDIT_MISSING_WARNINGS.md`, Section 2.1
- **Existing Warning Pattern:** See `tidy_parameter()` line 5743 for page range warning
- **Transformation Function:** `wikify_external_text()` in `TextTools.php`, lines 46-48

## Acceptance Criteria

- [ ] Warning triggers when HTML entities detected in title, journal, chapter, series
- [ ] Warning message includes parameter name and truncated value
- [ ] Existing content is NOT modified (preservation behavior maintained)
- [ ] Test cases added and passing
- [ ] No false positives on legitimate content
- [ ] Documentation updated if needed

## Estimated Effort

**Time:** 2-4 hours  
**Complexity:** Low  
**Files to Modify:** 
- `src/includes/Template.php` (add warning in `tidy_parameter()`)
- `tests/phpunit/includes/TemplatePart2Test.php` (add test cases)

---

**Labels:** enhancement, warning-system, high-priority  
**Milestone:** Phase 1 - High Priority Warnings  
**Related:** Epic Issue #[TBD]
