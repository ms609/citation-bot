# Citoid rate-limit coordination (PR #6120)

The Citoid limiter coordinates workers using an advisory `flock` on a timestamp
file (`rate.lock`). **This does not provide cluster-wide coordination unless
all PHP workers use the same directory on a filesystem that supports working
cross-host `flock` semantics.** Separate Kubernetes `/tmp` directories each
get their own independent allowance, violating the intended global budget.

Configure `CITOID_RATE_LIMIT_DIR=/absolute/shared/flock-capable/path` for each
replica. The directory must be owned by the PHP process UID and inaccessible to
group/other users (mode `0700`). All replicas need to have the same UID and see
**the same inode**. For single-instance deployments outside Kubernetes, the default remains
`sys_get_temp_dir()/citation-bot-citoid-rate-limit`. In Kubernetes, omitting
`CITOID_RATE_LIMIT_DIR` fails closed rather than silently choosing a per-pod
namespace. Do not point different replicas at private directories merely to
make requests succeed.

The rate-limit check fails closed on invalid paths, non-regular/replaced lock
files, corrupt/future-dated timestamps, or a lock held for more than 15 seconds.
This avoids endless worker stalls but may skip Citoid enrichment during severe
contention; the calling code already records such failures. The timestamp
records admission time and does not guarantee that actual HTTP start times on
different workers are one second apart.

**Manual recovery only:** stop or drain all writers, check file ownership,
permissions and whether any workers still hold the lock, inspect the shared
state, then correct the state or replace the file while all writers are stopped.
Never unlink or reset an active `rate.lock`: doing so splits the mutex into two
independent inodes. Resume workers only after confirming one shared inode.

The unit tests cover timeout, symlink and inode substitution, but do not prove
correct Kubernetes replica behavior. Validate shared-fs locking on the real
deployment before merging or scaling out.
