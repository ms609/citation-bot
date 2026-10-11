"""Cross-process smoke for the offline flock probe; not a multi-host proof."""

import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
PROBE = ROOT / "tools" / "verify-citoid-shared-flock.php"
CLEANUP = ROOT / "tools" / "cleanup-citoid-pending.php"
PHP = ["php", "-d", "uopz.exit=1"]


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
            [*PHP, str(PROBE), "--offline", mode, str(self.directory), *extra],
            text=True, capture_output=True, timeout=12, check=False,
        )

    def run_cleanup(self, mode="--dry-run"):
        return subprocess.run(
            [*PHP, str(CLEANUP), "--offline", mode, str(self.directory)],
            text=True, capture_output=True, timeout=12, check=False,
        )

    def test_exclusion_and_release_across_independent_interpreters(self):
        original_inode = self.lock.stat().st_ino
        original_contents = self.lock.read_bytes()
        holder = subprocess.Popen(
            [*PHP, str(PROBE), "--offline", "--hold", str(self.directory), "2"],
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

    def test_existing_lock_may_be_publicly_readable_but_never_writable_by_others(self):
        # Production can create 0644 with a normal umask; tools must accept it.
        self.lock.chmod(0o644)
        original_inode = self.lock.stat().st_ino
        original_bytes = self.lock.read_bytes()
        self.assertEqual(0, self.run_probe("--expect-free").returncode)
        unheld = self.run_probe("--expect-busy")
        self.assertEqual(1, unheld.returncode, "An available mutex is not remote contention")
        self.assertNotIn("PASS:", unheld.stdout)
        self.assertEqual(0, self.run_cleanup().returncode)
        self.assertEqual(original_inode, self.lock.stat().st_ino)
        self.assertEqual(original_bytes, self.lock.read_bytes())

    def test_requires_owner_write_permission_and_does_not_create_missing_lock(self):
        original_inode = self.lock.stat().st_ino
        original_bytes = self.lock.read_bytes()
        self.lock.chmod(0o400)
        self.assertEqual(2, self.run_probe("--expect-free").returncode)
        self.assertEqual(2, self.run_cleanup().returncode)
        self.assertEqual(original_inode, self.lock.stat().st_ino)
        self.assertEqual(original_bytes, self.lock.read_bytes())
        self.lock.unlink()
        self.assertEqual(2, self.run_probe("--expect-free").returncode)
        self.assertEqual(2, self.run_cleanup().returncode)
        self.assertFalse(self.lock.exists(), "Neither utility may create a missing mutex")

    def test_cleanup_refuses_busy_lock_and_preserves_pending_files(self):
        old = self.directory / ("rate.pending." + "a" * 24)
        old.write_bytes(b"orphan-pending-record")
        old.chmod(0o600)
        os.utime(old, (1000000000, 1000000000))
        original_inode = self.lock.stat().st_ino
        original_bytes = self.lock.read_bytes()
        holder = subprocess.Popen(
            [*PHP, str(PROBE), "--offline", "--hold", str(self.directory), "2"],
            stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True,
        )
        try:
            self.assertEqual("LOCK_HELD", holder.stdout.readline().strip())
            denied = self.run_cleanup("--delete")
            self.assertEqual(2, denied.returncode)
            self.assertIn("Lock is held", denied.stderr)
            self.assertTrue(old.exists(), "Offline cleanup must refuse a held lock")
            _, stderr = holder.communicate(timeout=10)
            self.assertEqual(0, holder.returncode, stderr)
            self.assertEqual(0, self.run_cleanup().returncode)
            deleted = self.run_cleanup("--delete")
            self.assertEqual(0, deleted.returncode, deleted.stderr)
            self.assertFalse(old.exists())
            self.assertEqual(original_inode, self.lock.stat().st_ino)
            self.assertEqual(original_bytes, self.lock.read_bytes())
        finally:
            if holder.poll() is None:
                holder.kill()
                holder.communicate(timeout=5)

    def test_uopz_with_disabled_exit_cannot_report_false_success(self):
        # PHP's uopz defaults to disabling exit() and can hide a failed probe.
        enabled = subprocess.run(
            ["php", "-r", "echo (int) (extension_loaded('uopz') && ini_get('uopz.exit') !== '1');"],
            text=True, capture_output=True, timeout=8, check=False,
        )
        self.assertEqual(0, enabled.returncode, enabled.stderr)
        if enabled.stdout.strip() != "1":
            self.skipTest("uopz with disabled exit() is not enabled in this PHP environment")
        original_inode = self.lock.stat().st_ino
        original_bytes = self.lock.read_bytes()
        for command in [
            ["php", str(PROBE), "--offline", "--expect-free", str(self.directory)],
            ["php", str(CLEANUP), "--offline", "--dry-run", str(self.directory)],
        ]:
            with self.subTest(script=command[1]):
                result = subprocess.run(command, text=True, capture_output=True, timeout=8, check=False)
                self.assertNotEqual(0, result.returncode)
                self.assertIn("uopz.exit=1", result.stderr)
                self.assertNotIn("PASS:", result.stdout)
        self.assertEqual(original_inode, self.lock.stat().st_ino)
        self.assertEqual(original_bytes, self.lock.read_bytes())

    def test_rejects_noncanonical_paths(self):
        result = subprocess.run(
            [*PHP, str(PROBE), "--offline", "--expect-free", str(self.directory) + "/../" + self.directory.name],
            capture_output=True, text=True, timeout=8, check=False,
        )
        self.assertEqual(2, result.returncode)


if __name__ == "__main__":
    unittest.main()
