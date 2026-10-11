"""Coverage gating must fail closed on insufficient or invalid Clover XML."""

from pathlib import Path
import subprocess
import tempfile
import unittest
from xml.etree import ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
CHECKER = ROOT / "tests" / "check_coverage.php"
# uopz disables exit() by default on CI. This option restores reliable status codes.
PHP_CHECKER = ["php", "-d", "uopz.exit=1"]


class CoverageGateTests(unittest.TestCase):
    def run_gate(self, covered, total=10000):
        with tempfile.TemporaryDirectory(prefix="citoid-coverage-gate-") as folder:
            path = Path(folder) / "clover.xml"
            project = ET.Element("project")
            ET.SubElement(
                project, "metrics", {
                    "files": "2", "statements": str(total),
                    "coveredstatements": str(covered),
                },
            )
            root = ET.Element("coverage")
            root.append(project)
            ET.ElementTree(root).write(path, encoding="utf-8", xml_declaration=True)
            return subprocess.run(
                [*PHP_CHECKER, str(CHECKER), str(path)],
                text=True, capture_output=True, timeout=8, check=False,
            )

    def test_php_exit_status_is_not_suppressed(self):
        result = subprocess.run(
            [*PHP_CHECKER, "-r", "exit(37);"],
            text=True, capture_output=True, timeout=8, check=False,
        )
        self.assertEqual(37, result.returncode, result.stderr)

    def test_below_floor_rejected(self):
        failure = self.run_gate(8762)
        self.assertNotEqual(0, failure.returncode)
        self.assertIn("below", failure.stderr)

    def test_boundary_passes(self):
        at_floor = self.run_gate(8880)
        self.assertEqual(0, at_floor.returncode, at_floor.stderr)

    def test_invalid_counts_fail_closed(self):
        self.assertNotEqual(0, self.run_gate(10001).returncode)
        self.assertNotEqual(0, self.run_gate(0, total=0).returncode)

    def test_missing_file_rejected(self):
        missing = subprocess.run(
            [*PHP_CHECKER, str(CHECKER), "/path/not/a/real/coverage.xml"],
            text=True, capture_output=True, timeout=8, check=False,
        )
        self.assertNotEqual(0, missing.returncode)


if __name__ == "__main__":
    unittest.main()
