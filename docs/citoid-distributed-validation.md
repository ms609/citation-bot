# Citoid distributed deployment validation

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
php -d uopz.exit=1 tools/verify-citoid-shared-flock.php --offline --hold /absolute/shared/citoid-dir 20
```

When host A prints `LOCK_HELD`, immediately run on **host B**:

```sh
php -d uopz.exit=1 tools/verify-citoid-shared-flock.php --offline --expect-busy /absolute/shared/citoid-dir
```

Host B must print `PASS: remote holder excluded this host` and exit 0. If it
instead acquires the lock, the filesystem does not supply the required
cross-host `flock` exclusion: **do not deploy**.

After host A prints `LOCK_RELEASED` and exits, run on host B:

```sh
php -d uopz.exit=1 tools/verify-citoid-shared-flock.php --offline --expect-free /absolute/shared/citoid-dir
```

Host B must print `PASS: lock is available after the holder exits` and exit 0.
Repeat with host roles reversed. Compare the expected inode and marker identities
using deployment tooling; do not infer shared identity solely from matching
pathnames. Do not run the probe against a live tenant without draining writers.

Use `php -d uopz.exit=1` when invoking the offline utilities so a failed
verification cannot be reported as a successful exit under the `uopz` PHP
extension. The scripts refuse to run before filesystem access when `uopz` is
loaded with suppressed exit codes. The same flag applies to cleanup commands
in `docs/citoid-rate-limit-locking.md`.

The script opens the **existing** `rate.lock` read-write (`r+b`) because
Linux NFS requires write access for an exclusive `flock()`. This mode does **not**
create or truncate the lock, and the probe never writes to it. It does not
replace, unlink, or modify `rate.state`. Run it as the lock owner, with the
owner-write permission bit set; modes `0600` and `0644` are allowed, but
group/other write access is not. A missing file or write permission fails
closed. `--expect-busy` passes **only** when `flock()` reports genuine
contention; locking errors (including unsupported operations) fail the probe.
It also refuses noncanonical paths, symlinks, nonprivate directories and
unexpected inode changes. The local CI test only proves that two **processes
on one host** can exclude each other; it is not a substitute for the two-host
procedure above.

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

## 4. Deployment sign-off (manual, on the actual shared mount)

Do not release a multi-replica deployment until an operator has confirmed:

- [ ] All hosts use the intended mount, the same namespace ID, and the proper
      worker UID, directory permissions, and existing permanent lock inode.
- [ ] Cross-host `flock` exclusion and release pass in **both directions**;
      locking errors must not count as contention.
- [ ] The deployment filesystem's same-directory rename and directory `fsync`
      semantics have been validated separately; the two-host mutex test alone
      does not prove crash or power-loss durability.
- [ ] Workers have been drained for rollout, no old/new mixed writers remain,
      and rollback likewise requires a complete drain.
- [ ] After restart, sanitized admission categories are monitored; a missing
      mount or invalid marker must fail closed, not fall back to private `/tmp`.

See [rollout and rollback](citoid-rate-limit-locking.md#multi-replica-rollout-and-rollback)
for the deployment sequence.

## 5. CI coverage and expected skips

A pull-request runner intentionally lacks production API secrets and configured
live Citoid coordination. Live API integration tests may therefore skip on PRs;
this is **not proof they passed**. Trusted push/scheduled jobs supply integration
configuration. PHP 8.5 and cross-browser jobs are scheduled; the Chromium UI
gate conditionally skips when relevant UI paths are unchanged.

CI now independently executes `tests/check_coverage.php` after the full suite
passes, in addition to the existing coverage check inside `tests/run_tests.php`.
Both must enforce the same **88.80% statement coverage floor**. A successful
PHPUnit run with permitted skips is not enough to override that floor. **Do not
lower the threshold merely to turn CI green.** Investigate uncovered branches,
new code, and effects of unavailable services, then add meaningful tests.
Coverage success does not validate cross-host locking or filesystem durability.
