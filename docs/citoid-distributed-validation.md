# Citoid distributed deployment validation

The passing pull-request unit tests do **not** prove cross-host advisory locking,
shared-mount availability, or power-loss durability. Perform these checks on
actual deployment hosts before enabling live Citoid enrichment at scale.

## 1. Preflight: no active writers

Stop or drain **all Citoid writers on all hosts** before invoking the probe.
Account for PHP-FPM/web requests, CLI workers, queues, scheduled jobs, and
supervisors that may restart processes automatically. Prevent new Citoid work
and verify that all writers have stopped; the probe cannot establish this
for you. Running it while workers are active would compete for the
production mutex. Use the same **absolute,
canonical, private shared directory** on both hosts. Provision or inspect its
files in step 2: the permanent `rate.lock` and `rate.namespace` must exist
**before** running any mutex probe or starting a worker. Do **not** unlink,
replace, truncate, or reset an existing `rate.lock`.

For an existing installation, inspect the `rate.namespace` marker on both
hosts and verify that it matches the agreed `CITOID_RATE_LIMIT_NAMESPACE_ID`.
Both replicas must refer to the same mounted directory. Filesystem access must
be restricted to the correct runtime UID; directory mode `0700` is required.

## 2. Provision or inspect the shared directory while drained

On **every host**, confirm that `/absolute/shared/citoid-dir` is the intended
**mounted shared filesystem**, not a local directory exposed by a missing
mount. Its full pathname must be absolute and canonical (no `..` components or
symlinked parent directories). Verify the real mount/export with the
infrastructure's mount tooling; matching pathname strings or namespace marker
contents do **not** establish shared-storage identity. Confirm all PHP workers
use the correct numeric UID and can read/write this directory on the shared
mount. Protect the directory's parent path against replacement.

For a **first installation only**, verify the mount **before creating any
path**. The directory's parent must already exist on the intended shared
filesystem. If the directory does not exist, create it with mode `0700`; if
it already exists (for example, because it is the mount point), **inspect it**
instead of calling `mkdir` again. An existing lock, namespace marker, or
published state means this is **not** a fresh installation; stop and follow
the upgrade/partial-install procedure below. Run the following **once, on one
host**, as the **PHP worker UID**, after separately establishing shared-mount
identity. Do not initialize a different namespace on each replica:

```sh
set -eu
dir=/absolute/shared/citoid-dir
# Confirm the intended shared mount/export with deployment tooling FIRST.
if [ ! -d "$dir" ]; then
    mkdir -m 700 -- "$dir"
fi
test "$(realpath -- "$dir")" = "$dir"
test "$(stat -c '%u' -- "$dir")" = "$(id -u)"
test "$(stat -c '%a' -- "$dir")" = 700
for name in rate.lock rate.namespace rate.state; do
    if [ -e "$dir/$name" ] || [ -L "$dir/$name" ]; then
        printf 'STOP: existing %s; inspect rather than reinitialize\n' "$name" >&2
        exit 1
    fi
done
namespace_id=$(openssl rand -hex 16)
( umask 077; set -C; printf 'CBNS1|%s\n' "$namespace_id" > "$dir/rate.namespace" )
( umask 077; set -C; : > "$dir/rate.lock" )
printf 'Record this namespace ID for EVERY host: %s\n' "$namespace_id"
```

Record the generated ID in the deployment configuration; it is an identifier,
not a credential. Use this **same** value in the marker and on every host.
Do not regenerate it when copying the configuration to another worker. If
creation stops after only one file is written, treat the result as a **partial
installation**: inspect and repair it while the entire fleet remains drained,
not by rerunning the first-install commands.

**For upgrades or partially provisioned installations, do not rerun these
creation commands.** Inspect the existing directory, marker, mutex, and
`rate.state`. Preserve any existing `rate.lock` **inode** and `rate.state`
content. Create **only** a genuinely missing `rate.lock` or `rate.namespace`
while every writer remains stopped, using exclusive/no-clobber creation and an
agreed deployment ID. Never generate a new marker ID when a valid marker
already exists; never overwrite or unlink an existing mutex. See the
[first-install and upgrade rules](citoid-rate-limit-locking.md) before making
any repairs.

Check the directory and files **on every host**, as the PHP worker UID.
Replace the sample `namespace_id` with the ID saved at provisioning (never
generate another ID for this check):

```sh
set -eu
dir=/absolute/shared/citoid-dir
namespace_id=0123456789abcdef0123456789abcdef
stat -c '%u %a %h %i %s %F %n' -- "$dir" "$dir/rate.lock" "$dir/rate.namespace"
test "$(realpath -- "$dir")" = "$dir"
test "$(stat -c '%u' -- "$dir")" = "$(id -u)"
test "$(stat -c '%a' -- "$dir")" = 700
for name in rate.lock rate.namespace; do
    test -f "$dir/$name" && test ! -L "$dir/$name"
    test "$(stat -c '%u' -- "$dir/$name")" = "$(id -u)"
    test "$(stat -c '%h' -- "$dir/$name")" = 1
done
test "$(stat -c '%a' -- "$dir/rate.namespace")" = 600
test "$(stat -c '%s' -- "$dir/rate.namespace")" = 39
lock_mode=$(stat -c '%a' -- "$dir/rate.lock")
case "$lock_mode" in
    [0-7][0-7][0-7]) ;;  # Allow ordinary POSIX permission bits only.
    *) printf 'FAIL: unexpected lock permissions\n' >&2; exit 1 ;;
esac
if [ $((0$lock_mode & 0022)) -ne 0 ] || [ $((0$lock_mode & 0200)) -eq 0 ]; then
    printf 'FAIL: lock must be owner-writable and not writable by others\n' >&2
    exit 1
fi
if printf 'CBNS1|%s\n' "$namespace_id" | cmp -s - "$dir/rate.namespace"; then
    printf 'PASS: namespace marker matches the deployment ID\n'
else
    printf 'FAIL: namespace marker mismatch; keep writers stopped\n' >&2
    exit 1
fi
```

All commands must succeed. A failing `test` exits nonzero under `set -e`;
the `cmp` comparison displays an explicit pass/fail result rather than
silently relying on an exit status. Inspect the `stat` output too: the
directory and both files must be owned by the **numeric PHP worker UID**,
`rate.lock` must be a regular, singly linked, owner-writable file with **no
group/other write bits** (typically `0600`; `0644` is permitted), and
`rate.namespace` must be a regular, singly linked file of mode `0600` and
exactly 39 bytes. The example verifies those basic metadata properties and
rejects symlinks, but it cannot establish shared storage or protect against
concurrent external filesystem changes. Compare mount/export identity and
the permanent mutex identity across hosts using deployment tooling; matching
path strings, markers, or even coincident inode numbers do not prove a shared
mount. Do not replace either file during verification.

## 3. Update `env.php` on every PHP host

The application loads the deployed **repository-root** `env.php` (alongside
`env.php.example`) from `src/includes/setup.php`. This is an operator-owned
configuration file, **not** `env.php.example`; retain its existing OAuth keys
and other settings. Keep the file readable by the PHP worker and inaccessible
to unauthorized users. Do not replace it wholesale with the example. If
`env.php` does not exist, create it through the application's usual secure
configuration/deployment process or configure the actual worker environment;
**do not copy the example's placeholder credentials into production**.

On **every** host, update the existing `env.php`, adding the following
assignments outside conditional blocks such as
`if (!getenv('PHP_OAUTH_CONSUMER_TOKEN'))`, so the limiter is configured even
when OAuth credentials are already present:

```php
// Citoid shared coordination: identical on every PHP worker.
@putenv('CITOID_RATE_LIMIT_DIR=/absolute/shared/citoid-dir');
@putenv('CITOID_RATE_LIMIT_NAMESPACE_ID=0123456789abcdef0123456789abcdef');
```

Replace the path and sample ID with the **actual canonical shared directory**
and the **same namespace ID printed during provisioning**. Both are required
in a configured multi-replica deployment. Do **not** enable
`CITOID_ALLOW_LOCAL_FALLBACK=1`: it is safe only for a verified single-instance
installation. If that flag is already present, remove the assignment from
`env.php` and unset it from the worker environment. A nonempty configured
shared directory takes precedence over fallback, but an absent shared
configuration must never allow a multi-replica worker to use local storage.

Alternatively, provide the two settings in **each PHP worker's environment**
instead of `env.php`; in that case, leave the example `putenv()` statements
commented. The application's configuration reader gives process-local
`putenv()` values priority over SAPI environment values, even when the value
is empty. Avoid stale or conflicting definitions. PHP-FPM may filter inherited
environment variables, so verify values in the **actual PHP-FPM workers** and
in any CLI/queue processes capable of issuing Citoid requests. Checking only
a login shell is insufficient. Never print unrelated `env.php` credentials
while verifying the settings.

Check PHP syntax without executing the configuration:

```sh
php -l /path/to/citation-bot/env.php
```

**Syntax success is not a configuration check.** Verify, in the actual
PHP-FPM/HTTP runtime on each host, that the effective values of
`CITOID_RATE_LIMIT_DIR` and `CITOID_RATE_LIMIT_NAMESPACE_ID` equal the
provisioned canonical path and the saved ID, and that local fallback is not
enabled. Use an existing authenticated, operator-only diagnostic mechanism
or a temporary **non-public** staging probe executed by that same PHP SAPI;
remove any temporary probe afterward. The result must be an explicit
**PASS** or **FAIL** for each host. Do not expose the diagnostic publicly,
print OAuth credentials, or dump the full environment. Repeat the equivalent
checks for CLI, queue, and scheduled PHP workers that can issue Citoid
requests. A login-shell `getenv()` or a separate CLI check does **not** prove
what PHP-FPM workers actually received. If configuration is supplied only
through worker environment variables, the effective values must still be
verified from those workers rather than assumed from the service manager.

Update every host while writers are stopped. Ensure each new worker will
load the intended configuration; restart PHP-FPM/long-running workers during
the coordinated resume, after all prerequisites have passed. Treat a missing
or empty shared directory value, an invalid namespace, or a mismatch as a
**deployment failure**, never a reason to enable local fallback.

## 4. Demonstrate mutual exclusion across hosts

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

## 5. Controlled failure and recovery exercises

In a separate isolated deployment, verify these outcomes without live Citoid
HTTP requests:

* Unmount/unavailable configured directory or mismatched `rate.namespace`:
  every worker must deny admission and report a fixed failure category.
* Concurrent workers on the same shared mount: completed admissions must
  maintain the one-second mutex-held pacing interval. Include admitted HTTP
  attempts that subsequently fail and retry attempts that seek a **new**
  admission. A denied admission must not dispatch an HTTP request.
* Terminate a writer during a pending-record write: a partial `rate.pending.*`
  file must not become `rate.state`; the stable `rate.lock` inode must survive.
* Simulate delayed or missing state publication: an absent `rate.state`
  can recover only after a worker holds the permanent mutex for a full
  one-second cooldown and successfully publishes new state. Corrupt or unsafe
  published state, or unsuccessful publication, must deny admission rather
  than dispatch an unpaced request. Investigate unexpected state loss offline.
* Restore service only in the **isolated test deployment** after tests pass.
  Keep production writers drained until the coordinated rollout in section 6.

The two-host mutex probe proves **only mutual exclusion**. Separately test
same-directory atomic rename and successful file/directory `fsync` using
**disposable** files on the same underlying mount, never production
`rate.lock`, `rate.namespace`, or `rate.state`. Perform a filesystem-appropriate
controlled writer crash and, where safely possible, storage crash/restart or
power-loss recovery test. A successful `fsync()` return alone does not prove
power-loss durability; verify the persisted outcome after the applicable
failure/recovery event. If such testing cannot be performed, record that
uncertainty and **do not sign off on unverified durability guarantees**.
Record the filesystem/export, hosts, test method, expected and observed
results, and operator. Use the existing rate-limit regression and contention
tests for additional local fault injection, **not** as a substitute for live
shared-filesystem validation.

## 6. Coordinated rollout and rollback

After provisioning, configuration, and cross-host validation have passed,
deploy the updated code to **all** hosts while Citoid writers remain drained;
never run old and new Citoid versions concurrently. Confirm the same `env.php`
settings (or effective worker environment) and the intended shared mount
remain available on every host. Restart PHP-FPM and any long-running
CLI/queue workers only when the entire fleet is ready. Monitor sanitized
admission-denial categories and successful paced requests; do not interpret
missing enrichment as proof the limiter is working correctly.

For a rollback, drain **all** new-version writers before restoring or starting
any old-version worker. Preserve `rate.lock`, `rate.namespace`, and
`rate.state` for inspection; never unlink or rotate the mutex to bypass an
error. **Draining is necessary but does not make an older version safe for
multi-replica Citoid traffic**: it may interpret pacing state differently or
lack the new fail-closed checks. Do not resume multi-replica Citoid enrichment
with that older version unless its cross-host admission semantics have been
separately demonstrated safe. Otherwise keep those writers disabled until a
compatible version is restored or an independently approved safe deployment
mode is available. Consult the [rollout and rollback procedure](citoid-rate-limit-locking.md#multi-replica-rollout-and-rollback)
for compatibility requirements.

## 7. Deployment sign-off (manual, on the actual shared mount)

Do not release a multi-replica deployment until an operator has confirmed:

* [ ] Every Citoid-capable PHP-FPM/web worker, CLI process, queue, and
      scheduled task was drained, with automatic restarts suppressed.
* [ ] All hosts use the intended mount, the **saved** namespace ID, and the
      proper worker UID, directory permissions, and permanent lock inode.
      First-time provisioning and existing-directory inspection are distinguished.
* [ ] Each host's deployed repository-root `env.php` has the correct shared
      directory and namespace values, or the settings are supplied through
      the PHP worker environment; existing credentials are preserved.
* [ ] Actual PHP-FPM and CLI/queue workers see the intended settings,
      with an explicit pass/fail record for each applicable worker type;
      no multi-replica worker can opt into local fallback. All paths are canonical.
* [ ] First-install provisioning or existing-file inspection has been recorded;
      the shared marker and mutex have not been replaced during the rollout.
* [ ] Cross-host `flock` exclusion and release pass in **both directions**;
      locking errors must not count as contention.
* [ ] The deployment filesystem's same-directory rename, file/directory
      `fsync`, and crash/restart durability semantics were tested separately
      using disposable files on the real mount. The method, host, mount,
      results, limitations and operator sign-off are recorded. The two-host
      mutex test alone does not prove power-loss durability.
* [ ] Controlled failure exercises restored only the isolated test system.
      Workers stayed drained during production configuration and deployment;
      all required workers were restarted with the intended configuration,
      and no old/new mixed writers remain. Rollback safety was assessed
      independently; a full drain alone does not make older pacing safe.
* [ ] After restart, sanitized admission categories are monitored; a missing
      mount or invalid marker must fail closed, not fall back to private `/tmp`.

See [rollout and rollback](citoid-rate-limit-locking.md#multi-replica-rollout-and-rollback)
for the deployment sequence.

## 8. CI coverage and expected skips

A pull-request runner intentionally lacks production API secrets and configured
live Citoid coordination. Live API integration tests may therefore skip on PRs;
this is **not proof they passed**. Trusted push/scheduled jobs supply integration
configuration. PHP 8.5 and cross-browser jobs are scheduled; the Chromium UI
gate conditionally skips when relevant UI paths are unchanged. A green
workflow may therefore contain skipped live integration, PHP 8.5 or browser
test jobs; inspect the individual job and test results before claiming they ran.

CI now independently executes `tests/check_coverage.php` after the full suite
passes, in addition to the existing coverage check inside `tests/run_tests.php`.
Both must enforce the same **88.80% statement coverage floor**. A successful
PHPUnit run with permitted skips is not enough to override that floor. **Do not
lower the threshold merely to turn CI green.** Investigate uncovered branches,
new code, and effects of unavailable services, then add meaningful tests.
Coverage success does not validate cross-host locking or filesystem durability.
