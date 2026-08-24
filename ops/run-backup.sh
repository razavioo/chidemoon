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
database_backup="/backups/database-${timestamp}.sql.gz"
uploads_backup="/backups/uploads-${timestamp}.tar.gz"

# A collision means two scheduled runs reached the same second. Refusing is
# safer than silently replacing the only restore point named by that timestamp.
if [ -e "$database_backup" ] || [ -e "$uploads_backup" ]; then
	echo "Refusing to overwrite an existing backup timestamp: $timestamp" >&2
	exit 1
fi

database_temporary="$(mktemp "/backups/.database-${timestamp}.XXXXXX")"
uploads_temporary="$(mktemp "/backups/.uploads-${timestamp}.XXXXXX")"
cleanup() {
	rm -f -- "$database_temporary" "$uploads_temporary"
}
trap cleanup EXIT

mariadb-dump \
	--host=database \
	--user="$MARIADB_USER" \
	--password="$MARIADB_PASSWORD" \
	--single-transaction \
	--routines \
	--events \
	"$MARIADB_DATABASE" | gzip -c > "$database_temporary"

tar -C /uploads -czf "$uploads_temporary" .
mv "$database_temporary" "$database_backup"
mv "$uploads_temporary" "$uploads_backup"
find /backups -type f -mtime "+${retention_days}" -delete
