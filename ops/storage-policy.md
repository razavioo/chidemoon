# Production storage policy

The owner confirmed the shared host's zero-backup policy on 2026-11-16. Production must not create or retain database dumps, upload archives, configuration snapshots or copies inside release directories or container temporary storage. Live uploads, database volumes and running code are not backups and must be preserved.

`ops/backup-host.sh` skips backups by default. `ops/run-backup.sh` refuses to manufacture a successful recovery receipt while disabled. Code-only deployment defaults to the existing no-data-recovery lane. Data-restoring Elementor migration lanes refuse to run while backups are disabled; do not claim that code rollback restores database state. Isolated disposable restore drills explicitly set `CHIDEMOON_BACKUPS_ENABLED=true`; this is not a production setting.

Kalahamoon's host-installed storage-maintenance timer clears explicitly named backup directories under `/opt/chidemoon` without following symlinks or crossing mounts. It also removes the two identified leaked upload archives from the WordPress container's `/tmp`. Keep active editorial media and watermark originals. New maintenance tools must use bounded temporary directories and remove them on success and failure instead of persisting archives.
