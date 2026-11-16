# Repository Instructions

## Git workflow

- Work directly on `main`.
- Do not create, switch to, or use feature branches unless the user explicitly requests it.
- Do not create worktrees unless the user explicitly requests one.

## Production storage

The owner requires zero server backups for all projects on the shared host. Follow `ops/storage-policy.md`: do not create persistent SQL dumps, upload archives, configuration snapshots or backup copies inside releases or containers. Keep live editorial media and database volumes. Backup-enabled recovery tests are restricted to explicitly isolated disposable environments.
