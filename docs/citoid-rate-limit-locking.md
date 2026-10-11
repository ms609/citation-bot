# Citoid rate-limit coordination (PR #6120)

The Citoid limiter coordinates workers using an advisory `flock` on a timestamp
file (`rate.lock`). **This does not provide cluster-wide coordination unless
all PHP workers use the same directory on a filesystem that supports working
cross-host `flock` semantics.** Separate Kubernetes `/tmp` directories each
get their own independent allowance, violating the intended global budget.

**Choose an explicit configuration** in the deployed `env.php` (see
`env.php.example`) or in the PHP process environment:

- **Multiple instances / replicas:** set
  `CITOID_RATE_LIMIT_DIR=/absolute/shared/flock-capable/path` for every instance.
  The directory must be owned by the PHP process UID and inaccessible to
  group/other users (mode `0700`). All replicas must see **the same inode** on
  a filesystem that supports cross-host `flock`.
- **Exactly one instance:** set `CITOID_ALLOW_LOCAL_FALLBACK=1` to use
  `sys_get_temp_dir()/citation-bot-citoid-rate-limit`. The example
  `env.php.example` contains a commented opt-in assignment; uncomment it only
  for a verified single-instance installation. Never use this option when
  separate hosts or containers can issue concurrent requests.

If neither is configured, Citoid requests **fail closed** instead of silently
using potentially private storage. Neither setting is assigned a value by the
unchanged `env.php.example`; uncomment one option in the deployed `env.php` or
provide it through the PHP worker environment. `putenv()` examples are
unconditional overrides, so leave them commented when deployment already sets
the corresponding variable. An explicitly empty `CITOID_RATE_LIMIT_DIR`
is invalid and fails closed even if the local fallback flag is set. When both
variables are set, a nonempty `CITOID_RATE_LIMIT_DIR` takes precedence. Only the
literal value `1` enables local fallback; values such as `true` do not.

The explicitly configured `CITOID_RATE_LIMIT_DIR` must exist before workers
start. If the pathname is missing, the limiter fails closed rather than
creating a private replacement. Multi-instance deployments must also set
`CITOID_RATE_LIMIT_NAMESPACE_ID` to exactly 32 lowercase hexadecimal characters
and pre-provision `rate.namespace` inside the shared directory with the exact
content `CBNS1|<namespace-id>\n` (where `\n` is a single newline).
The file must be a regular, singly-linked file, owned by the PHP worker UID,
with permissions `0600`. The namespace ID is a deployment identifier, **not a
secret**. Create it once on the intended shared mount and use the same ID on
all replicas. Do not generate independent IDs for each replica. Never overwrite
or rotate an active marker; drain every worker before a deliberate change.

For example, before starting workers, run the following as the PHP worker UID
on the mounted shared filesystem (after the directory is provisioned with
mode `0700`):

```sh
id=$(openssl rand -hex 16)
printf 'CBNS1|%s\n' "$id" > /absolute/shared/flock-capable/path/rate.namespace
chmod 600 /absolute/shared/flock-capable/path/rate.namespace
# Set CITOID_RATE_LIMIT_NAMESPACE_ID=$id for every PHP worker.
```

A missing mount that reveals an existing local directory without this marker
will fail closed. **The marker is not proof of a shared filesystem**: a copied
marker, stale local mount, or an incorrect deployment can still defeat the
intended shared budget. Validate the actual mount identity and cross-host
`flock` semantics before serving traffic. No namespace marker is required for
an explicitly opted-in single-instance local fallback. Test-only directory
overrides bypass the deployment namespace requirement.

`KUBERNETES_SERVICE_HOST` is deliberately not used to decide whether a local
fallback is safe. Some PHP-FPM setups remove inherited environment variables
from worker processes, and multi-instance deployments also exist outside
Kubernetes. The configuration reader checks local process variables first
(including `env.php` assignments via `putenv()`) and then SAPI variables;
this avoids FastCGI values masking an explicit local assignment. A locally
assigned **empty** directory still fails closed. Verify that whichever setting
you choose is visible to **the PHP worker**, not just the container or web
server process. Do not point different replicas at private directories merely
to make requests succeed. Rolling out the namespace requirement is a breaking
configuration change: deploy the marker and environment variable to all
workers before upgrading. Without both, Citoid enrichment fails closed.

The rate-limit check fails closed on invalid paths, non-regular/replaced lock
files, malformed or corrupt timestamps, or a lock held for more than 15 seconds.
Every denied admission now reports a fixed category, without filesystem paths,
marker values, request URLs or stack traces. The admission categories are:

- `configuration-missing`: no explicit shared directory or single-instance opt-in.
- `configuration-invalid`, `timeout-invalid`: invalid paths or timeouts.
- `directory-unavailable`, `directory-unsafe`: absent or unsafe directory.
- `namespace-invalid`: missing, mismatched or unsafe shared namespace marker.
- `lock-unavailable`, `lock-timeout`, `lock-integrity`: missing/unsafe mutex,
  acquisition deadline, or changed lock identity.
- `state-invalid`: missing trusted format, bad checksum or corrupt timestamp.
- `publication-failed`: state write, sync, rename or integrity check failed.

Treat all categories as skipped enrichment, never as permission to send an
unpaced HTTP request. Both initial and retry dispatches use the same admission
gate. Investigate the category, stop or drain all writers before repairing a
shared directory, and never unlink an active lock file. Categories are local
observability hints, not claims that distributed filesystem locking is sound.
A valid, checksummed `rate.state` timestamp ahead of the worker's wall clock
may indicate clock rollback: under the shared mutex, the worker waits one full
second measured with `hrtime()` and publishes a fresh record. An unchecksummed
future-dated legacy timestamp is still rejected. The monotonic wait is safe
against local clock adjustment, but cannot guarantee global pacing if different
hosts' clocks are out of sync. Synchronize clocks on all replicas. The timestamp
records admission time and does not guarantee that actual HTTP start times on
different workers are one second apart. Severe contention can skip enrichment.

## Contention and bounded waiting

A worker makes a nonblocking attempt to acquire `rate.lock`, then polls using
monotonic `hrtime()` for up to **15 seconds by default** (60 seconds is the
maximum accepted by the test-only timeout parameter). A zero timeout makes one
attempt without sleeping. Poll sleeps are limited to the remaining deadline,
so a worker never deliberately sleeps a full polling interval beyond it.
After the lock is acquired, rate-budget pacing can add up to **one second**;
filesystem I/O and PHP scheduling introduce additional, unbounded overhead.
This is an **acquisition timeout**, not a hard wall-clock HTTP deadline.

At sustained demand greater than one admission per second, workers may time
out. They **fail closed** and skip Citoid enrichment rather than bypassing
pacing; the upstream error-handling path records a failure. Do not increase
worker concurrency or retry admission in a tight loop to evade this budget.
The mutex remains held during the pacing interval in this version. A future
reservation/queue implementation would need its own cancellation and crash
handling, so it is intentionally outside this change.

The `citoid-stress` test exercises ten fresh PHP processes by default. Repeat
with `CITOID_STRESS_WORKERS=20` and `CITOID_STRESS_WORKERS=50` to observe
bounded denials under heavier load. This tests local filesystem coordination,
not a real multi-host distributed mount.

**Manual recovery of damaged state:** stop or drain all writers, check file
ownership, permissions and whether any workers still hold the lock, inspect
the shared state, then correct it while all writers are stopped. Never unlink
or reset an active `rate.lock`: doing so splits the mutex into two independent
inodes. Resume workers only after confirming one shared inode.

The unit tests cover configuration selection, timeout, symlink and inode
substitution, but do not prove correct multi-replica behavior. Validate
shared-fs locking on the real deployment before merging or scaling out.

Process termination during a pending write cannot expose a partial published
record when the filesystem supplies atomic same-directory rename and working
flock semantics. Host crash / power-loss durability additionally requires
successful synchronization of the file **and its containing directory**.
The PHP worker must be able to open the private directory and `fsync()` its
file descriptor; unsupported or unsuccessful directory fsync fails closed.
After rename but before directory sync, a record may be visible even though
admission returns failure. Do not attempt to roll it back: later readers must
continue to honor that visible state. The deployment filesystem must guarantee
atomic rename, durable directory sync, and correct cross-host `flock` semantics.
Test those guarantees on the actual mount; local CI does not prove them.
The permanent `rate.lock`
inode is only the mutex: newer workers read a checksummed `rate.state` which
is published by creating a unique temporary file, checking complete writes,
flushing, syncing and closing the file, then renaming and syncing the containing
directory while holding the mutex. The code checks lock and directory inode
identity before publication and after directory synchronization. These
pathname checks cannot eliminate every race against a malicious actor who can
rename directory entries; keep the lock directory and its parents protected.
A terminated writer can leave an ignored
`rate.pending.*` file but cannot expose a partially written published timestamp.
When `rate.state` is absent, the old timestamp inside `rate.lock` is checked
for valid legacy syntax, but **always** triggers a full one-second cooldown.
This covers first use, upgrades, interrupted legacy truncation and unexpected
loss of a previously published state; a stale legacy timestamp cannot justify
an immediate request. Drain all old workers before deploying the new format:
mixed old/new workers do not share timing state even with the same mutex.
Corrupt published state fails closed and requires operator investigation.
The state record is
small (maximum 64 bytes) and detects malformed data with a checksum. A manual
restart should keep `rate.lock` and `rate.state`; do not unlink either while
workers may be active. The atomic rename guarantee does not apply to cross-
filesystem temporary files; temporary records are created beside the state.

## Offline cleanup of abandoned pending writes

Do not remove pending files from a live request path. **First stop or drain
all Citoid writers on every host** and verify that none can restart while
cleaning. Then, with the same UID that owns the private directory, run:

```sh
php tools/cleanup-citoid-pending.php --offline --dry-run /absolute/private/lock-dir
php tools/cleanup-citoid-pending.php --offline --delete /absolute/private/lock-dir
```

The tool refuses noncanonical paths, unsafe directory or lock ownership,
replaced inodes, and a lock held by another process. It never creates or
removes `rate.lock`, `rate.state`, or `rate.namespace`. It only examines
regular, single-link, owner-private `rate.pending.` entries with exactly 24
lowercase hexadecimal characters after the dot; it removes those older than
five minutes and leaves newer or unrecognized names untouched. A symlink or
otherwise unsafe matching entry aborts cleanup without changing that entry.
The dry run lists eligible files without removing them.

An advisory lock cannot prevent writers from starting **after** the utility
exits. The `--offline` argument is an explicit operator assertion, not proof
that all hosts are drained. Verify the namespace and storage mount separately.
