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
creating a private replacement. This cannot detect an existing but incorrect
local directory at that pathname: validate mount identity and cross-host flock
semantics separately. Automatic directory creation is reserved for
`CITOID_ALLOW_LOCAL_FALLBACK=1` on a verified single-instance deployment.
The test-only directory override does not permit creation either.

`KUBERNETES_SERVICE_HOST` is deliberately not used to decide whether a local
fallback is safe. Some PHP-FPM setups remove inherited environment variables
from worker processes, and multi-instance deployments also exist outside
Kubernetes. The configuration reader checks local process variables first
(including `env.php` assignments via `putenv()`) and then SAPI variables;
this avoids FastCGI values masking an explicit local assignment. A locally
assigned **empty** directory still fails closed. Verify that whichever setting
you choose is visible to **the PHP worker**, not just the container or web
server process. Do not point different replicas at private directories merely
to make requests succeed.

The rate-limit check fails closed on invalid paths, non-regular/replaced lock
files, malformed or corrupt timestamps, or a lock held for more than 15 seconds.
A valid, checksummed `rate.state` timestamp ahead of the worker's wall clock
may indicate clock rollback: under the shared mutex, the worker waits one full
second measured with `hrtime()` and publishes a fresh record. An unchecksummed
future-dated legacy timestamp is still rejected. The monotonic wait is safe
against local clock adjustment, but cannot guarantee global pacing if different
hosts' clocks are out of sync. Synchronize clocks on all replicas. The timestamp
records admission time and does not guarantee that actual HTTP start times on
different workers are one second apart. Severe contention can skip enrichment.

**Manual recovery of damaged state:** stop or drain all writers, check file
ownership, permissions and whether any workers still hold the lock, inspect
the shared state, then correct it while all writers are stopped. Never unlink
or reset an active `rate.lock`: doing so splits the mutex into two independent
inodes. Resume workers only after confirming one shared inode.

The unit tests cover configuration selection, timeout, symlink and inode
substitution, but do not prove correct multi-replica behavior. Validate
shared-fs locking on the real deployment before merging or scaling out.

State publication is crash-consistent across normal exit, SIGTERM, SIGKILL,
SIGHUP termination, and SIGSEGV, provided the filesystem supplies atomic
same-directory rename and working flock semantics. The permanent `rate.lock`
inode is only the mutex: newer workers read a checksummed `rate.state` which
is published by creating, flushing and syncing a unique temporary file, then
renaming it while holding the mutex. A terminated writer can leave an ignored
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
