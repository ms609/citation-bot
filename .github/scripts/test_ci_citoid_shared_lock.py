"""Cross-process smoke for the offline flock probe; not a multi-host proof."""

import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
PROBE = ROOT / "tools" / "verify-citoid-shared-flock.php"


@unittest.skipUnless(os.name == "posix" and Path("/proc").exists(), "Linux required")
class CitoidSharedFlockSmokeTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix="citoid-flock-probe-")
        self.addCleanup(self.tmp.cleanup)
        self.directory = Path(self.tmp.name)
        self.directory.chmod(0o700)
        self.lock = self.directory / "rate.lock"
        self.lock.write_bytes(b"permanent-lock-content\n")
        self.lock.chmod(0o600)

    def run_probe(self, mode, *extra):
        return subprocess.run(
            ["php", str(PROBE), "--offline", mode, str(self.directory), *extra],
            text=True, capture_output=True, timeout=12, check=False,
        )

    def test_exclusion_and_release_across_independent_interpreters(self):
        original_inode = self.lock.stat().st_ino
        original_contents = self.lock.read_bytes()
        holder = subprocess.Popen(
            ["php", str(PROBE), "--offline", "--hold", str(self.directory), "2"],
            stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True,
        )
        try:
            self.assertEqual("LOCK_HELD", holder.stdout.readline().strip())
            busy = self.run_probe("--expect-busy")
            self.assertEqual(0, busy.returncode, busy.stderr)
            self.assertIn("PASS:", busy.stdout)
            remainder, stderr = holder.communicate(timeout=10)
            self.assertEqual(0, holder.returncode, stderr)
            self.assertIn("LOCK_RELEASED", remainder)
            free = self.run_probe("--expect-free")
            self.assertEqual(0, free.returncode, free.stderr)
            self.assertIn("PASS:", free.stdout)
            self.assertEqual(original_inode, self.lock.stat().st_ino)
            self.assertEqual(original_contents, self.lock.read_bytes())
        finally:
            if holder.poll() is None:
                holder.kill()
                holder.communicate(timeout=5)

    def test_rejects_unsafe_lock_symlink_and_directory(self):
        self.lock.chmod(0o666)
        self.assertEqual(2, self.run_probe("--expect-free").returncode)
        self.lock.chmod(0o600)
        self.lock.rename(self.directory / "target")
        self.lock.symlink_to("target")
        self.assertEqual(2, self.run_probe("--expect-free").returncode)
        self.lock.unlink()
        (self.directory / "target").rename(self.lock)
        self.directory.chmod(0o755)
        self.assertEqual(2, self.run_probe("--expect-free").returncode)
        self.directory.chmod(0o700)
        self.assertEqual(0, self.run_probe("--expect-free").returncode)

    def test_rejects_noncanonical_paths(self):
        result = subprocess.run(
            ["php", str(PROBE), "--offline", "--expect-free", str(self.directory) + "/../" + self.directory.name],
            capture_output=True, text=True, timeout=8, check=False,
        )
        self.assertEqual(2, result.returncode)


if __name__ == "__main__":
    unittest.main()
