#!/bin/sh
set -eu

# The application volume is not copied wholesale because core and source-owned
# plugins can be recreated from an immutable release; editorial uploads cannot.
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
retention_days="${CHIDEMOON_BACKUP_RETENTION_DAYS:-14}"

case "$retention_days" in
	''|*[!0-9]*)
		echo "CHIDEMOON_BACKUP_RETENTION_DAYS must be a non-negative integer." >&2
		exit 64
		;;
esac

mkdir -p /backups
mariadb-dump \
	--host=database \
	--user="$MARIADB_USER" \
	--password="$MARIADB_PASSWORD" \
	--single-transaction \
	--routines \
	--events \
	"$MARIADB_DATABASE" | gzip -c > "/backups/database-${timestamp}.sql.gz"

tar -C /uploads -czf "/backups/uploads-${timestamp}.tar.gz" .
find /backups -type f -mtime "+${retention_days}" -delete
