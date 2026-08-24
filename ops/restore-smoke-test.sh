#!/usr/bin/env bash
set -euo pipefail

# This is intentionally opt-in because it creates a temporary database and
# volumes. It exercises the real backup and restore scripts without touching
# the Chidemoon project's named production/local volumes.
[[ "${1:-}" == '--confirm-smoke-test' ]] || {
	printf 'Usage: bash ops/restore-smoke-test.sh --confirm-smoke-test\n' >&2
	exit 64
}

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
for command in docker mktemp tar gzip; do
	command -v "$command" >/dev/null 2>&1 || {
		printf 'Restore smoke test failed: Required command is unavailable: %s\n' "$command" >&2
		exit 1
	}
done

project="chidemoon-restore-smoke-$(date -u +%Y%m%d%H%M%S)-$$"
scratch_dir="$(mktemp -d "${TMPDIR:-/tmp}/chidemoon-restore-smoke.XXXXXX")"

compose() {
	COMPOSE_PROJECT_NAME="$project" docker compose --env-file "$scratch_dir/.env" -f "$scratch_dir/compose.yml" "$@"
}

uploads_volume="${project}_chidemoon_uploads"
write_uploads() {
	local command="$1"
	local owner

	owner="$(docker volume inspect --format '{{ index .Labels "com.docker.compose.project" }}' "$uploads_volume" 2>/dev/null || true)"
	[[ "$owner" == "$project" ]] || {
		printf 'Restore smoke test failed: isolated uploads volume ownership could not be verified.\n' >&2
		return 1
	}
	MSYS_NO_PATHCONV=1 docker run --rm --pull=never --mount "type=volume,src=$uploads_volume,dst=/uploads" mariadb:11.4 sh -c "$command"
}

cleanup() {
	set +e
	while IFS= read -r container; do
		[[ -n "$container" ]] || continue
		owner="$(docker container inspect --format '{{ index .Config.Labels "com.docker.compose.project" }}' "$container" 2>/dev/null || true)"
		[[ "$owner" == "$project" ]] || continue
		docker container rm -f "$container" >/dev/null 2>&1 || true
	done < <(docker container ls --all --quiet --filter "label=com.docker.compose.project=$project")
	compose down --remove-orphans >/dev/null 2>&1 || true
	while IFS= read -r volume; do
		[[ -n "$volume" ]] || continue
		[[ "$volume" == "${project}_"* ]] || continue
		owner="$(docker volume inspect --format '{{ index .Labels "com.docker.compose.project" }}' "$volume" 2>/dev/null || true)"
		[[ "$owner" == "$project" ]] || continue
		docker volume rm "$volume" >/dev/null 2>&1 || true
	done < <(docker volume ls --quiet --filter "label=com.docker.compose.project=$project")
	rm -rf -- "$scratch_dir"
}
trap cleanup EXIT

mkdir -p "$scratch_dir/ops" "$scratch_dir/backups"
cp "$ROOT_DIR/compose.yml" "$scratch_dir/compose.yml"
cp "$ROOT_DIR/ops/run-backup.sh" "$scratch_dir/ops/run-backup.sh"
cp "$ROOT_DIR/ops/restore-backup.sh" "$scratch_dir/ops/restore-backup.sh"
cat > "$scratch_dir/.env" <<'EOF'
CHIDEMOON_HTTP_PORT=19098
CHIDEMOON_ENVIRONMENT=local
CHIDEMOON_DB_NAME=restore_smoke
CHIDEMOON_DB_USER=restore_smoke
CHIDEMOON_DB_PASSWORD=restore-smoke-password
CHIDEMOON_DB_ROOT_PASSWORD=restore-smoke-root-password
CHIDEMOON_BACKUP_RETENTION_DAYS=14
EOF

compose up -d --wait --pull never database
compose create --pull never restore >/dev/null
compose exec -T database mariadb --user=restore_smoke --password=restore-smoke-password restore_smoke \
	-e "CREATE TABLE smoke_restore (id INT PRIMARY KEY, value_text VARCHAR(32)); INSERT INTO smoke_restore VALUES (1, 'before');"
write_uploads 'set -eu; mkdir -p /uploads/smoke; printf before > /uploads/smoke/original.txt'
compose run --rm --no-deps --pull never backup

database_backup="$(find "$scratch_dir/backups" -maxdepth 1 -type f -name 'database-*.sql.gz' -print -quit)"
[[ -n "$database_backup" ]] || {
	printf 'Restore smoke test failed: backup did not create a database archive.\n' >&2
	exit 1
}
timestamp="${database_backup##*/database-}"
timestamp="${timestamp%.sql.gz}"
[[ -f "$scratch_dir/backups/uploads-${timestamp}.tar.gz" ]] || {
	printf 'Restore smoke test failed: backup did not create a matching uploads archive.\n' >&2
	exit 1
}

compose exec -T database mariadb --user=restore_smoke --password=restore-smoke-password restore_smoke \
	-e "UPDATE smoke_restore SET value_text = 'after' WHERE id = 1;"
write_uploads 'set -eu; rm -f /uploads/smoke/original.txt; printf after > /uploads/smoke/after.txt'

COMPOSE_PROJECT_NAME="$project" bash "$scratch_dir/ops/restore-backup.sh" "$timestamp" --confirm-restore

restored_value="$(compose exec -T database mariadb --skip-column-names --batch --user=restore_smoke --password=restore-smoke-password restore_smoke -e 'SELECT value_text FROM smoke_restore WHERE id = 1;')"
[[ "$restored_value" == 'before' ]] || {
	printf 'Restore smoke test failed: database value was not restored.\n' >&2
	exit 1
}
write_uploads 'test "$(cat /uploads/smoke/original.txt)" = before; test ! -e /uploads/smoke/after.txt'

printf 'Restore smoke test passed using isolated project: %s\n' "$project"
