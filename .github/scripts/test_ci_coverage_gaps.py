"""The coverage diagnostic must not copy absolute paths or source content."""

import tempfile
import unittest
from pathlib import Path

from ci_coverage_gaps import MAX_CLOVER_BYTES, coverage_gaps


class CoverageGapsTests(unittest.TestCase):
    def test_only_relative_php_paths_and_uncovered_statements(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            path = root / "clover.xml"
            path.write_text(
                '<coverage><project>'
                f'<file name="{root / "src" / "demo.php"}">'
                '<line num="7" type="stmt" count="0"/>'
                '<line num="8" type="stmt" count="3"/>'
                '</file>'
                '<file name="/outside/secret.php">'
                '<line num="1" type="stmt" count="0"/>'
                '</file>'
                '</project></coverage>',
                encoding="utf-8",
            )
            result = coverage_gaps(path, root)
            self.assertIn("`src/demo.php` | 1 | 2 | 7", result)
            self.assertNotIn(str(root), result)
            self.assertNotIn("secret.php", result)

    def test_missing_xml_is_diagnostic_not_exception(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            self.assertIn("not generated", coverage_gaps(root / "missing.xml", root))

    def test_internal_entity_dtd_is_rejected(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            source = root / "coverage.xml"
            source.write_text(
                '<!DOCTYPE coverage [<!ENTITY x "EXPANSION">]>'
                '<coverage><project>&x;</project></coverage>',
                encoding="utf-8",
            )
            self.assertIn("DTD is not permitted", coverage_gaps(source, root))

    def test_oversized_clover_is_rejected_without_parsing(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            source = root / "coverage.xml"
            with source.open("wb") as stream:
                stream.truncate(MAX_CLOVER_BYTES + 1)
            self.assertIn("size limit", coverage_gaps(source, root))


if __name__ == "__main__":
    unittest.main()
