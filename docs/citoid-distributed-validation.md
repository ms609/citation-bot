# Citoid distributed deployment validation (Patch 07)

The passing pull-request unit tests do **not** prove cross-host advisory locking,
shared-mount availability, or power-loss durability. Perform these checks on
actual deployment hosts before enabling live Citoid enrichment at scale.

## 1. Preflight: no active writers

Stop or drain **all Citoid writers on all hosts** before invoking the probe.
It does not prove that writers are stopped, and running it while workers are
active would compete for the production mutex. Use the same **absolute,
canonical, private shared directory** on both hosts. The existing `rate.lock`
file must already exist. Do **not** unlink, replace, truncate, or reset it.

Inspect the `rate.namespace` marker on both hosts and verify that it matches
the provisioned `CITOID_RATE_LIMIT_NAMESPACE_ID`. Both replicas must refer to
the same mounted directory. Filesystem access must be restricted to the correct
runtime UID; directory mode 0700 is required.

## 2. Demonstrate mutual exclusion across hosts

On **host A**, start a bounded hold:

```sh
php tools/verify-citoid-shared-flock.php --offline --hold /absolute/shared/citoid-dir 20
```

When host A prints `LOCK_HELD`, immediately run on **host B**:

```sh
php tools/verify-citoid-shared-flock.php --offline --expect-busy /absolute/shared/citoid-dir
```

Host B must print `PASS: remote holder excluded this host` and exit 0. If it
instead acquires the lock, the filesystem does not supply the required
cross-host `flock` exclusion: **do not deploy**.

After host A prints `LOCK_RELEASED` and exits, run on host B:

```sh
php tools/verify-citoid-shared-flock.php --offline --expect-free /absolute/shared/citoid-dir
```

Host B must print `PASS: lock is available after the holder exits` and exit 0.
Repeat with host roles reversed. Compare the expected inode and marker identities
using deployment tooling; do not infer shared identity solely from matching
pathnames. Do not run the probe against a live tenant without draining writers.

The script only opens the **existing** `rate.lock` read-only; it does not create,
truncate, replace, unlink, or modify `rate.state`. It refuses noncanonical
paths, symlinks, nonprivate directories, unsafe lock permissions, and unexpected
inode changes. The local CI test only proves two **processes on one host** can
exclude each other; it is not a substitute for the two-host procedure above.

## 3. Controlled failure and recovery exercises

In a separate isolated deployment, verify these outcomes without live Citoid
HTTP requests:

* Unmount/unavailable configured directory or mismatched `rate.namespace`:
  every worker must deny admission and report a fixed failure category.
* Concurrent workers on the same shared mount: no two admissions within the
  one-second pacing interval, including failures and retries.
* Terminate a writer during a pending-record write: a partial `rate.pending.*`
  file must not become `rate.state`; the stable `rate.lock` inode must survive.
* Simulate a delayed/missing state publication: all callers fail closed rather
  than issuing an unpaced request. Recover offline; preserve published state.
* Restore service on all hosts, then monitor sanitized rate-limit diagnostics.

The first two-host check proves mutex exclusion **only**. Host crashes, power
loss, atomic rename, and directory `fsync()` durability must be exercised and
verified on the actual filesystem separately. Use the existing rate-limit
regression and contention tests for local fault injection.

## 4. CI coverage and expected skips

A pull-request runner intentionally lacks production API secrets and configured
live Citoid coordination. Live API integration tests may therefore skip on PRs;
this is **not proof they passed**. Trusted push/scheduled jobs supply integration
configuration. PHP 8.5 and cross-browser jobs are scheduled; the Chromium UI
gate conditionally skips when relevant UI paths are unchanged.

CI now independently executes `tests/check_coverage.php` after the full suite
passes, in addition to the existing coverage check inside `tests/run_tests.php`.
Both must enforce the same **88.80% statement coverage floor**. A successful
PHPUnit run with permitted skips is not enough to override that floor. The
October 11 PR run reported **87.62%** (15,213 / 17,362); **do not lower the
threshold merely to turn CI green**. Investigate actual uncovered branches,
new code, and effects of unavailable services, then add meaningful tests.
