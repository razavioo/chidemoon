#!/usr/bin/env bash
set -euo pipefail

# This helper changes only the current-release symlink and the WordPress
# container. Named database and upload volumes are never removed or recreated.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
bundle_path="${1:-}"
image_bundle_path="${2:-${CHIDEMOON_IMAGE_ARCHIVE:-}}"
deploy_root="${CHIDEMOON_DEPLOY_ROOT:-/opt/chidemoon}"
environment_file="${CHIDEMOON_ENV_FILE:-$deploy_root/.env}"

fail() {
	printf 'Release deployment failed: %s\n' "$1" >&2
	exit 1
}

[[ -n "$bundle_path" && -f "$bundle_path" ]] || fail 'Pass an existing release bundle.'
[[ -n "$image_bundle_path" && -f "$image_bundle_path" ]] || fail 'Pass the matching sealed offline image archive as the second argument, or set CHIDEMOON_IMAGE_ARCHIVE.'
[[ "$deploy_root" = /* ]] || fail 'CHIDEMOON_DEPLOY_ROOT must be an absolute path.'
[[ -f "$environment_file" ]] || fail 'Host-managed .env is required and must not be bundled.'
command -v docker >/dev/null 2>&1 || fail 'Docker is required.'

bash "$SCRIPT_DIR/verify-release-bundle.sh" "$bundle_path"
bash "$SCRIPT_DIR/load-offline-image-archive.sh" "$image_bundle_path" "${CHIDEMOON_IMAGE_ARCHIVE_CHECKSUM:-${image_bundle_path}.sha256}" "$bundle_path" "${bundle_path}.sha256"

mkdir -p "$deploy_root/releases"
deploy_root="$(cd "$deploy_root" && pwd)"
releases_dir="$deploy_root/releases"
current_link="$deploy_root/current"

if [[ -e "$current_link" && ! -L "$current_link" ]]; then
	fail 'The current release path exists but is not a symlink.'
fi

archive_root="$(tar -tzf "$bundle_path" | sed -n '1p')"
release_name="${archive_root%%/*}"
[[ "$release_name" =~ ^chidemoon-release-[A-Za-z0-9._-]+$ ]] || fail 'Release root name is invalid.'
release_dir="$releases_dir/$release_name"
[[ ! -e "$release_dir" ]] || fail 'This immutable release is already extracted on the host.'

previous_release=''
if [[ -L "$current_link" ]]; then
	previous_release="$(readlink -f "$current_link")"
	[[ -f "$previous_release/compose.yml" ]] || fail 'The current release does not contain compose.yml.'
	# Preserve database and editorial uploads before changing the code mount.
	docker compose --env-file "$environment_file" -f "$previous_release/compose.yml" run --rm --no-deps --pull never backup
fi

staging_dir="$(mktemp -d "$releases_dir/.staging.XXXXXX")"
cleanup() {
	if [[ -d "$staging_dir" ]]; then
		rm -rf -- "$staging_dir"
	fi
}
trap cleanup EXIT

tar --no-same-owner --no-same-permissions --strip-components=1 -xzf "$bundle_path" -C "$staging_dir"
[[ -f "$staging_dir/compose.yml" ]] || fail 'Staged release is incomplete.'
mv "$staging_dir" "$release_dir"
staging_dir=''

next_link="$deploy_root/.current-${release_name}.$$"
ln -s "$release_dir" "$next_link"
mv -Tf "$next_link" "$current_link"

rollback() {
	if [[ -z "$previous_release" ]]; then
		return
	fi

	rollback_link="$deploy_root/.current-rollback-$$"
	ln -s "$previous_release" "$rollback_link"
	mv -Tf "$rollback_link" "$current_link"
	docker compose --env-file "$environment_file" -f "$previous_release/compose.yml" up -d --wait --pull never --force-recreate wordpress || true
}

if ! docker compose --env-file "$environment_file" -f "$current_link/compose.yml" config -q \
	|| ! docker compose --env-file "$environment_file" -f "$current_link/compose.yml" up -d --wait --pull never database \
	|| ! docker compose --env-file "$environment_file" -f "$current_link/compose.yml" up -d --wait --pull never --force-recreate wordpress \
	|| ! docker compose --env-file "$environment_file" -f "$current_link/compose.yml" run --rm --no-deps --pull never wpcli core is-installed --allow-root \
	|| ! docker compose --env-file "$environment_file" -f "$current_link/compose.yml" run --rm --no-deps --pull never wpcli plugin is-active woocommerce --allow-root \
	|| ! docker compose --env-file "$environment_file" -f "$current_link/compose.yml" run --rm --no-deps --pull never wpcli plugin is-active chidemoon-core --allow-root \
	|| ! docker compose --env-file "$environment_file" -f "$current_link/compose.yml" exec -T wordpress php -r 'exit(@file_get_contents("http://localhost/wp-login.php") === false ? 1 : 0);'; then
	rollback
	fail 'The new release did not pass runtime checks; code was switched back when a previous release existed.'
fi

printf 'Deployed sealed Chidemoon release: %s\n' "$release_name"
