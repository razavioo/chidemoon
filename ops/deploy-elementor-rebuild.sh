#!/usr/bin/env bash
set -Eeuo pipefail

# First theme migration includes database recovery; later code releases use deploy-release-bundle.sh.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
bundle_path="${1:?Pass the sealed source bundle}"
image_bundle_path="${2:?Pass the matching offline image archive}"
deploy_root="${CHIDEMOON_DEPLOY_ROOT:-/opt/chidemoon}"
environment_file="${CHIDEMOON_ENV_FILE:-$deploy_root/.env}"
project="${CHIDEMOON_COMPOSE_PROJECT:-chidemoon}"
editor_id="${CHIDEMOON_EDITOR_ID:-1}"

fail() { printf 'Elementor deployment failed: %s\n' "$1" >&2; exit 1; }
[[ "$deploy_root" = /* && -f "$environment_file" ]] || fail 'An absolute deployment root and host-managed .env are required.'
[[ "$editor_id" =~ ^[1-9][0-9]*$ ]] || fail 'CHIDEMOON_EDITOR_ID must be an administrator ID.'
[[ -L "$deploy_root/current" ]] || fail 'An existing current release symlink is required.'
previous_release="$(readlink -f "$deploy_root/current")"
[[ -f "$previous_release/compose.yml" ]] || fail 'Previous release is incomplete.'

compose() { docker compose -p "$project" --env-file "$environment_file" -f "$1/compose.yml" "${@:2}"; }
wp() { compose "$deploy_root/current" run --rm --no-deps --pull never wpcli "$@"; }

bash "$SCRIPT_DIR/verify-release-bundle.sh" "$bundle_path"
bash "$SCRIPT_DIR/load-offline-image-archive.sh" "$image_bundle_path" "${image_bundle_path}.sha256" "$bundle_path" "${bundle_path}.sha256"
archive_root="$(tar -tzf "$bundle_path" | sed -n '1p')"
release_name="${archive_root%%/*}"
[[ "$release_name" =~ ^chidemoon-release-[A-Za-z0-9._-]+$ ]] || fail 'Invalid release root.'
release_dir="$deploy_root/releases/$release_name"
[[ ! -e "$release_dir" ]] || fail 'Immutable release already exists.'
mkdir -p "$deploy_root/releases"
tar --no-same-owner --no-same-permissions -xzf "$bundle_path" -C "$deploy_root/releases"
compose "$release_dir" config -q

backup_dir="$deploy_root/backups/elementor-$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -m 700 -p "$backup_dir"
compose "$previous_release" exec -T database sh -c 'exec mariadb-dump --single-transaction --quick --routines --triggers --user=root --password="$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' | gzip > "$backup_dir/database.sql.gz"
compose "$previous_release" exec -T wordpress tar -C /var/www/html/wp-content/uploads -czf - . > "$backup_dir/uploads.tar.gz"
printf '%s\n' "$previous_release" > "$backup_dir/previous-release.txt"
(cd "$backup_dir" && sha256sum database.sql.gz uploads.tar.gz > checksums.sha256)
printf 'Recovery point: %s\n' "$backup_dir"

rollback() {
	local result=$?
	trap - ERR
	set +e
	printf 'Restoring previous code and database after migration failure.\n' >&2
	ln -s "$previous_release" "$deploy_root/.rollback-$$"
	mv -Tf "$deploy_root/.rollback-$$" "$deploy_root/current"
	compose "$previous_release" up -d --pull never --force-recreate wordpress
	gzip -dc "$backup_dir/database.sql.gz" | compose "$previous_release" exec -T database sh -c 'exec mariadb --user=root --password="$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'
	compose "$previous_release" exec -T wordpress rm -f /var/www/html/.maintenance
	compose "$previous_release" up -d --wait --pull never wordpress
	printf 'Inspect recovery status; backup retained at %s\n' "$backup_dir" >&2
	exit "$result"
}
trap rollback ERR

wp maintenance-mode activate
ln -s "$release_dir" "$deploy_root/.next-$$"
mv -Tf "$deploy_root/.next-$$" "$deploy_root/current"
# Health is checked after Hello activation, since the previous child theme is no longer mounted.
compose "$release_dir" up -d --pull never --force-recreate wordpress
wp theme install /packages/hello-elementor.zip --force
wp --user="$editor_id" eval-file /tools/elementor-rebuild.php apply reset-demo
wp --user="$editor_id" eval-file /tools/rebuild-editorial.php
wp theme activate hello-elementor
wp rewrite flush --hard
wp --user="$editor_id" eval-file /tools/verify-elementor.php
wp maintenance-mode deactivate
compose "$release_dir" up -d --wait --pull never wordpress
trap - ERR
printf 'Deployed native Elementor rebuild: %s\nRecovery point: %s\n' "$release_name" "$backup_dir"
