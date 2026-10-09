"""Quick, offline regression tests for the GitHub Actions ParaTest watchdog."""

import subprocess
import sys
import unittest
from pathlib import Path

WATCHDOG = Path(__file__).with_name("ci_test_watchdog.py")


def launch(code: str, *, idle: str = "1", maximum: str = "3") -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        [sys.executable, str(WATCHDOG), "--idle-seconds", idle,
         "--max-seconds", maximum, "--warn-seconds", "0.15",
         "--grace-seconds", "0.2", "--", sys.executable, "-c", code],
        capture_output=True, text=True, timeout=10, check=False,
    )


class TestWatchdog(unittest.TestCase):
    def test_passes_through_output_and_success(self) -> None:
        result = launch("print('normal test run', flush=True)")
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn("normal test run", result.stdout)

    def test_preserves_failing_exit_code(self) -> None:
        result = launch("raise SystemExit(7)")
        self.assertEqual(7, result.returncode)

    def test_no_output_prints_last_active_test_and_exits_124(self) -> None:
        code = ("import time; print('Process 2 executing: "
                "tests/phpunit/includes/api/JstorTest.php', flush=True); time.sleep(5)")
        result = launch(code, idle="1.5", maximum="8")
        self.assertEqual(124, result.returncode, result.stderr)
        self.assertIn("tests/phpunit/includes/api/JstorTest.php", result.stderr)
        self.assertIn("no test output", result.stderr)

    def test_maximum_deadline_applies_even_with_continuous_output(self) -> None:
        code = ("import time; "
                "[(print('working', flush=True), time.sleep(0.1)) for _ in range(50)]")
        result = launch(code, idle="2", maximum="0.5")
        self.assertEqual(124, result.returncode, result.stderr)
        self.assertIn("maximum exceeded", result.stderr)


if __name__ == "__main__":
    unittest.main()
