# Historical JSTOR RIS handling (attic)

This folder preserves the pre-Citoid JSTOR RIS implementation **for later use**.
The archive is outside the production source loader and PHPUnit discovery tree.

- `APIjstor_legacy.php` is an **unaltered historical copy** of the original JSTOR
  file, including its unused RIS classifiers and parser. The current active
  `src/includes/api/APIjstor.php` retains only Citoid/Zotero integration.
- `constants.php` preserves both RIS-specific type lists.
- `tests/` preserves the retired RIS-only PHPUnit classes and an unmodified
  copy of `TextToolsCoverageTest.php` (including its three RIS date methods).
- `fixtures/` preserves the original `.ris` files via Git renames.
- `tests/LegacyTemplatePart3RisTest.php` retains the former inline `testRIS()`
  cases, which are no longer executed in active PHPUnit runs.

The active `JstorTest` live integration tests remain in CI under updated names,
because they now test Citoid metadata and page expansion rather than RIS.

Restore the archived code and tests manually if a future RIS reader is needed;
test include paths under `attic/` are intentionally not wired into current CI.
