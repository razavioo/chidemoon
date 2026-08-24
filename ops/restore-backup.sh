#!/usr/bin/env bash
set -euo pipefail

# Recovery is deliberately a two-key operation. A named pair prevents an
# accidental unnamed restore, and the explicit confirmation makes replacement
# of the live database and uploads a conscious operator action.
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
environment_file="$ROOT_DIR/.env"
timestamp="${1:-}"
confirmation="${2:-}"

fail() {
	printf 'Backup restore failed: %s\n' "$1" >&2
	exit 1
}

if [[ "$timestamp" == '--help' || "$timestamp" == '-h' ]]; then
	printf 'Usage: bash ops/restore-backup.sh YYYYMMDDTHHMMSSZ --confirm-restore\n'
	exit 0
fi

[[ "$timestamp" =~ ^[0-9]{8}T[0-9]{6}Z$ ]] || fail 'Pass the exact UTC backup timestamp, for example 20260825T120000Z.'
[[ "$confirmation" == '--confirm-restore' ]] || fail 'Restoration requires the explicit --confirm-restore flag.'
[[ -f "$environment_file" ]] || fail 'A host-managed .env file is required.'
command -v docker >/dev/null 2>&1 || fail 'Docker is required.'
command -v gzip >/dev/null 2>&1 || fail 'gzip is required.'
command -v tar >/dev/null 2>&1 || fail 'tar is required.'

database_backup="$ROOT_DIR/backups/database-${timestamp}.sql.gz"
uploads_backup="$ROOT_DIR/backups/uploads-${timestamp}.tar.gz"
[[ -f "$database_backup" ]] || fail "Database backup is missing: $(basename "$database_backup")"
[[ -f "$uploads_backup" ]] || fail "Uploads backup is missing: $(basename "$uploads_backup")"
gzip -t "$database_backup" || fail 'Database backup gzip validation failed.'

uploads_listing="$(mktemp "${TMPDIR:-/tmp}/chidemoon-uploads-listing.XXXXXX")"
wordpress_was_running=0
cleanup() {
	rm -f -- "$uploads_listing"
	if (( wordpress_was_running )); then
		docker compose --env-file "$environment_file" -f "$ROOT_DIR/compose.yml" up -d --wait --pull never wordpress || true
	fi
}
trap cleanup EXIT
tar -tzf "$uploads_backup" > "$uploads_listing" || fail 'Uploads backup validation failed.'
if awk '$0 ~ /^\// || $0 ~ /(^|\/)\.\.($|\/)/ { exit 1 }' "$uploads_listing"; then
	:
else
	fail 'Uploads backup contains an unsafe path.'
fi
if ! tar -tvzf "$uploads_backup" | awk 'substr($1, 1, 1) !~ /[-d]/ { exit 1 }'; then
	fail 'Uploads backup contains a link or special filesystem entry.'
fi

# Make a current recovery point before stopping writes. This does not modify
# existing backups and gives the operator a rollback source if import fails.
docker compose --env-file "$environment_file" -f "$ROOT_DIR/compose.yml" run --rm --no-deps --pull never backup

if [[ -n "$(docker compose --env-file "$environment_file" -f "$ROOT_DIR/compose.yml" ps --status running -q wordpress)" ]]; then
	wordpress_was_running=1
	docker compose --env-file "$environment_file" -f "$ROOT_DIR/compose.yml" stop wordpress
fi

docker compose --env-file "$environment_file" -f "$ROOT_DIR/compose.yml" run --rm --no-deps --pull never \
	-e "CHIDEMOON_RESTORE_TIMESTAMP=$timestamp" \
	--entrypoint sh \
	restore \
	-c 'set -eu; gzip -dc "/backups/database-${CHIDEMOON_RESTORE_TIMESTAMP}.sql.gz" | mariadb --host=database --user="$MARIADB_USER" --password="$MARIADB_PASSWORD" "$MARIADB_DATABASE"'

# The explicit confirmation above and the fresh rollback backup are required
# because uploads must be replaced, not overlaid, to restore deleted media too.
docker compose --env-file "$environment_file" -f "$ROOT_DIR/compose.yml" run --rm --no-deps --pull never \
	-e "CHIDEMOON_RESTORE_TIMESTAMP=$timestamp" \
	--entrypoint sh \
	restore \
	-c 'set -eu; find /uploads -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +; tar -xzf "/backups/uploads-${CHIDEMOON_RESTORE_TIMESTAMP}.tar.gz" -C /uploads'

printf 'Restored named Chidemoon backup: %s\n' "$timestamp"
