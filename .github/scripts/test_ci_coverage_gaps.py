"""The coverage diagnostic must not copy absolute paths or source content."""

import tempfile
import unittest
from pathlib import Path

from ci_coverage_gaps import coverage_gaps


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


if __name__ == "__main__":
    unittest.main()
