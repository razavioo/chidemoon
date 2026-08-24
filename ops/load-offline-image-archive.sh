#!/usr/bin/env bash
set -euo pipefail

# Loading is intentionally refused when an existing tag points at different
# content. That makes a host-side image collision visible instead of silently
# replacing an image before the release has passed its application checks.
image_bundle_path="${1:-}"
image_checksum_path="${2:-${image_bundle_path}.sha256}"
release_bundle_path="${3:-}"
release_checksum_path="${4:-${release_bundle_path}.sha256}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

fail() {
	printf 'Offline image load failed: %s\n' "$1" >&2
	exit 1
}

command -v docker >/dev/null 2>&1 || fail 'Docker is required.'
bash "$SCRIPT_DIR/verify-offline-image-archive.sh" \
	"$image_bundle_path" \
	"$image_checksum_path" \
	"$release_bundle_path" \
	"$release_checksum_path"
source "$SCRIPT_DIR/offline-images-lib.sh"

verification_dir="$(mktemp -d "${TMPDIR:-/tmp}/chidemoon-image-load.XXXXXX")"
cleanup() {
	rm -rf -- "$verification_dir"
}
trap cleanup EXIT

tar --no-same-owner --no-same-permissions -xzf "$image_bundle_path" -C "$verification_dir"
release_root="$(chidemoon_release_root "$release_bundle_path")" || fail 'The bound release root is invalid.'
release_sha="$(sha256sum "$release_bundle_path" | awk '{print $1}')"
lock_listing="$(chidemoon_read_image_lock "$verification_dir/offline-images.lock" "$release_sha" "$release_root")" || fail 'The image lock is invalid.'
mapfile -t locked_images <<< "$lock_listing"

missing_image=0
for record in "${locked_images[@]}"; do
	reference="${record%%$'\t'*}"
	expected_id="${record#*$'\t'}"
	existing_id="$(docker image inspect --format '{{.Id}}' "$reference" 2>/dev/null || true)"
	if [[ -n "$existing_id" && "$existing_id" != "$expected_id" ]]; then
		fail "Refusing to replace an existing image tag with different content: $reference"
	fi
	[[ "$existing_id" == "$expected_id" ]] || missing_image=1
done

if (( missing_image )); then
	docker image load --input "$verification_dir/docker-images.tar"
fi

for record in "${locked_images[@]}"; do
	reference="${record%%$'\t'*}"
	expected_id="${record#*$'\t'}"
	loaded_id="$(docker image inspect --format '{{.Id}}' "$reference" 2>/dev/null || true)"
	[[ "$loaded_id" == "$expected_id" ]] || fail "The required image was not loaded exactly: $reference"
done

printf 'Loaded verified offline Docker images for: %s\n' "$release_root"
