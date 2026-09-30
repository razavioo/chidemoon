#!/usr/bin/env bash
set -Eeuo pipefail

# Upgrade an existing Hello/Elementor site without rebuilding editorial seeds.
# Every data change shares a verified database/uploads recovery point.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
bundle_path="${1:?Pass the sealed source bundle}"
image_bundle_path="${2:?Pass its matching sealed offline image archive}"
deploy_root="${CHIDEMOON_DEPLOY_ROOT:-/opt/chidemoon}"
environment_file="${CHIDEMOON_ENV_FILE:-$deploy_root/.env}"
project="${CHIDEMOON_COMPOSE_PROJECT:-chidemoon}"
editor_id="${CHIDEMOON_EDITOR_ID:-1}"
minimum_free_mib="${CHIDEMOON_MIN_FREE_MIB:-2048}"

# Return a failing status so ERR performs recovery even inside disk gates.
fail() { printf 'Elementor upgrade failed: %s\n' "$1" >&2; return 1; }
for command in docker tar gzip sha256sum flock df stat; do
	command -v "$command" >/dev/null 2>&1 || fail "Required command is unavailable: $command"
done
[[ "$deploy_root" = /* && -d "$deploy_root" && -f "$environment_file" ]] || fail 'An existing absolute deployment root and host-managed .env are required.'
[[ -f "$bundle_path" && -f "$image_bundle_path" ]] || fail 'Both sealed archives must exist.'
[[ "$editor_id" =~ ^[1-9][0-9]*$ && "$minimum_free_mib" =~ ^[1-9][0-9]*$ ]] || fail 'Administrator ID and disk threshold must be positive integers.'
[[ "$project" =~ ^[A-Za-z0-9][A-Za-z0-9_-]*$ ]] || fail 'Invalid Compose project name.'
[[ -L "$deploy_root/current" ]] || fail 'An existing immutable current release is required.'
previous_release="$(readlink -f "$deploy_root/current")"
[[ -f "$previous_release/compose.yml" ]] || fail 'The previous release is incomplete.'
exec 9>"$deploy_root/.elementor-upgrade.lock"
flock -n 9 || fail 'Another Elementor upgrade is running.'

compose() { docker compose -p "$project" --env-file "$environment_file" -f "$1/compose.yml" "${@:2}"; }
wp_at() { local release="$1"; shift; compose "$release" run --rm --no-deps --pull never wpcli "$@"; }
wp() { wp_at "$deploy_root/current" "$@"; }
enter_maintenance_at() {
	# WordPress expires a fixed $upgrading timestamp after ten minutes. Evaluate
	# time() on every request so backup and recovery remain protected throughout.
	compose "$1" exec -T wordpress php -r 'if (false === file_put_contents("/var/www/html/.maintenance", "<?php " . chr(36) . "upgrading = time();")) { exit(1); }'
}

database_kib="$(compose "$previous_release" exec -T database du -sk /var/lib/mysql | awk 'NR==1 {print $1}')"
uploads_kib="$(compose "$previous_release" exec -T wordpress du -sk /var/www/html/wp-content/uploads | awk 'NR==1 {print $1}')"
[[ "$database_kib" =~ ^[0-9]+$ && "$uploads_kib" =~ ^[0-9]+$ ]] || fail 'Could not measure database and uploads recovery space.'
artifact_bytes=$(( $(stat -c '%s' "$bundle_path") + $(stat -c '%s' "$image_bundle_path") ))
required_bytes=$(( minimum_free_mib * 1024 * 1024 + artifact_bytes * 4 + (database_kib + uploads_kib) * 1024 ))
check_disk() {
	local required="$1" available
	available="$(df -PB1 "$deploy_root" | awk 'NR==2 {print $4}')"
	[[ "$available" =~ ^[0-9]+$ ]] || fail 'Could not determine current disk headroom.'
	(( available >= required )) || fail "Insufficient disk headroom: need $required bytes; available $available."
	printf 'Disk gate passed: %s bytes free; %s bytes required.\n' "$available" "$required"
}
check_disk "$required_bytes"
bash "$SCRIPT_DIR/verify-release-bundle.sh" "$bundle_path"
bash "$SCRIPT_DIR/load-offline-image-archive.sh" "$image_bundle_path" "${image_bundle_path}.sha256" "$bundle_path" "${bundle_path}.sha256"
archive_root="$(tar -tzf "$bundle_path" | sed -n '1p')"
release_name="${archive_root%%/*}"
[[ "$release_name" =~ ^chidemoon-release-[A-Za-z0-9._-]+$ ]] || fail 'Invalid immutable release root.'
release_dir="$deploy_root/releases/$release_name"
[[ ! -e "$release_dir" ]] || fail 'The immutable release already exists; retain or quarantine it before retrying.'
mkdir -p "$deploy_root/releases"
staging_dir="$(mktemp -d "$deploy_root/releases/.upgrade-stage.XXXXXX")"
cleanup() { [[ ! -d "$staging_dir" ]] || rm -rf -- "$staging_dir"; }
trap cleanup EXIT
tar --no-same-owner --no-same-permissions --strip-components=1 -xzf "$bundle_path" -C "$staging_dir"
for tool in elementor-editability-upgrade.php editorial-elementor-upgrade.php verify-elementor.php; do
	[[ -f "$staging_dir/tools/$tool" ]] || fail "Selective migration tool is missing: $tool"
done
compose "$staging_dir" config -q
mv "$staging_dir" "$release_dir"
staging_dir=''

# Run PHP alone, before WordPress/plugin bootstrap can alter application data.
php_check='if (!extension_loaded("ionCube Loader") || !function_exists("ioncube_loader_iversion") || !extension_loaded("curl") || !extension_loaded("mysqli") || !extension_loaded("intl") || !extension_loaded("mbstring")) { fwrite(STDERR,"Persistent loader or original PHP extensions missing.\n"); exit(1); } printf("PHP %s; ionCube %s; original extensions present.\n",PHP_VERSION,phpversion("ionCube Loader"));'
for service in wordpress wpcli; do
	compose "$release_dir" run --rm --no-deps --pull never --entrypoint php "$service" -r "$php_check"
done
[[ "$(wp_at "$previous_release" --skip-plugins --skip-themes option get stylesheet)" = hello-elementor ]] || fail 'This selective upgrade requires the existing Hello Elementor theme.'
if compose "$previous_release" exec -T wordpress test -f /var/www/html/.maintenance; then
	fail 'The site is already in maintenance; another operation must finish first.'
fi

backup_dir="$deploy_root/backups/elementor-upgrade-$(date -u +%Y%m%dT%H%M%SZ)-$$"
mkdir -m 700 -p "$backup_dir"
printf '%s\n' "$previous_release" > "$backup_dir/previous-release.txt"
maintenance_created=0
backup_verified=0
code_switched=0

rollback() {
	local result="${1:-$?}" recovery_failed=0
	# ERR is inherited by pipeline functions and command substitutions. Their
	# parent must perform recovery once, after it observes the failing status.
	if (( BASH_SUBSHELL > 0 )); then exit "$result"; fi
	trap - ERR INT TERM
	set +e
	printf 'Upgrade failed. Restoring the recorded recovery point.\n' >&2
	if (( code_switched )); then
		# A final health check may fail after maintenance was removed. Re-enter it
		# before restoring data so public writes cannot race the recovery.
		enter_maintenance_at "$release_dir" || recovery_failed=1
		maintenance_created=1
		ln -s "$previous_release" "$deploy_root/.upgrade-rollback-$$" && mv -Tf "$deploy_root/.upgrade-rollback-$$" "$deploy_root/current" || recovery_failed=1
		compose "$previous_release" up -d --no-deps --pull never --force-recreate wordpress || recovery_failed=1
	fi
	if (( backup_verified && code_switched )); then
		(cd "$backup_dir" && sha256sum -c checksums.sha256) || recovery_failed=1
		if (( ! recovery_failed )); then
			gzip -dc "$backup_dir/database.sql.gz" | compose "$previous_release" exec -T database sh -c 'exec mariadb --user=root --password="$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' || recovery_failed=1
			# Maintenance prevents public writes while the exact uploads snapshot is restored.
			compose "$previous_release" exec -T wordpress sh -c 'find /var/www/html/wp-content/uploads -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +' || recovery_failed=1
			compose "$previous_release" exec -T wordpress tar -C /var/www/html/wp-content/uploads -xzf - < "$backup_dir/uploads.tar.gz" || recovery_failed=1
		fi
	fi
	if (( maintenance_created && ! recovery_failed )); then
		compose "$previous_release" exec -T wordpress rm -f /var/www/html/.maintenance || recovery_failed=1
		compose "$previous_release" up -d --wait --no-deps --pull never wordpress || recovery_failed=1
	fi
	if (( recovery_failed )); then
		printf 'Recovery needs operator attention; keep maintenance active. Backup: %s\n' "$backup_dir" >&2
	else
		printf 'Previous release and data restored. Recovery point retained: %s\n' "$backup_dir" >&2
	fi
	exit "$result"
}
trap rollback ERR
trap 'rollback 130' INT
trap 'rollback 143' TERM

maintenance_created=1
enter_maintenance_at "$previous_release"
compose "$previous_release" exec -T database sh -c 'exec mariadb-dump --single-transaction --quick --routines --triggers --events --user=root --password="$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' | gzip > "$backup_dir/database.sql.gz"
gzip -t "$backup_dir/database.sql.gz"
gzip -dc "$backup_dir/database.sql.gz" | awk '/^CREATE TABLE/ { tables++ } /^-- Dump completed/ { complete=1 } END { exit !(tables && complete) }'
compose "$previous_release" exec -T wordpress tar -C /var/www/html/wp-content/uploads -czf - . > "$backup_dir/uploads.tar.gz"
tar -tzf "$backup_dir/uploads.tar.gz" > "$backup_dir/uploads-files.txt"
tar -tvzf "$backup_dir/uploads.tar.gz" | awk 'substr($1,1,1) !~ /[-d]/ { exit 1 }'
(cd "$backup_dir" && sha256sum database.sql.gz uploads.tar.gz > checksums.sha256 && sha256sum -c checksums.sha256)
backup_verified=1
printf 'Verified recovery point: %s\n' "$backup_dir"
check_disk "$(( minimum_free_mib * 1024 * 1024 ))"

code_switched=1
ln -s "$release_dir" "$deploy_root/.upgrade-next-$$"
mv -Tf "$deploy_root/.upgrade-next-$$" "$deploy_root/current"
compose "$release_dir" up -d --no-deps --pull never --force-recreate wordpress
wp core is-installed
for plugin in woocommerce elementor elementor-pro chidemoon-core chidemoon-ai; do wp plugin is-active "$plugin"; done
wp --user="$editor_id" eval-file /tools/elementor-editability-upgrade.php apply
wp --user="$editor_id" eval-file /tools/editorial-elementor-upgrade.php apply products
wp --user="$editor_id" eval-file /tools/verify-elementor.php native
wp rewrite flush --hard
wp --skip-plugins --skip-themes maintenance-mode deactivate
maintenance_created=0
compose "$release_dir" up -d --wait --no-deps --pull never wordpress
compose "$release_dir" exec -T wordpress php -r '$body=@file_get_contents("http://localhost/"); if ($body === false || strlen($body)<100 || str_contains($body,"There has been a critical error")) { exit(1); }'
trap - ERR INT TERM
printf 'Deployed selective Elementor upgrade: %s\nRecovery point: %s\n' "$release_name" "$backup_dir"
