#!/usr/bin/env bash
set -euo pipefail

image_bundle_path="${1:-}"
image_checksum_path="${2:-${image_bundle_path}.sha256}"
release_bundle_path="${3:-}"
release_checksum_path="${4:-${release_bundle_path}.sha256}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

fail() {
	printf 'Offline image archive verification failed: %s\n' "$1" >&2
	exit 1
}

[[ -n "$image_bundle_path" && -f "$image_bundle_path" ]] || fail 'Pass an existing offline image archive.'
[[ -f "$image_checksum_path" ]] || fail 'The image archive checksum sidecar is missing.'
[[ -n "$release_bundle_path" && -f "$release_bundle_path" ]] || fail 'Pass the release bundle bound to this image archive.'
for command in tar sha256sum bash; do
	command -v "$command" >/dev/null 2>&1 || fail "Required command is unavailable: $command"
done

bash "$SCRIPT_DIR/verify-release-bundle.sh" "$release_bundle_path" "$release_checksum_path"

image_bundle_dir="$(cd "$(dirname "$image_bundle_path")" && pwd)"
image_checksum_dir="$(cd "$(dirname "$image_checksum_path")" && pwd)"
[[ "$image_bundle_dir" == "$image_checksum_dir" ]] || fail 'Image archive and checksum must be in the same directory.'
(
	cd "$image_bundle_dir"
	sha256sum -c "$(basename "$image_checksum_path")"
) || fail 'Image archive checksum mismatch.'

archive_listing="$(mktemp "${TMPDIR:-/tmp}/chidemoon-image-listing.XXXXXX")"
verification_dir="$(mktemp -d "${TMPDIR:-/tmp}/chidemoon-image-verify.XXXXXX")"
cleanup() {
	rm -f -- "$archive_listing"
	rm -rf -- "$verification_dir"
}
trap cleanup EXIT

tar -tzf "$image_bundle_path" > "$archive_listing"
expected_entries=$'docker-images.tar\noffline-images-files.sha256\noffline-images.lock'
actual_entries="$(LC_ALL=C sort "$archive_listing")"
[[ "$actual_entries" == "$expected_entries" ]] || fail 'Image archive contains an unexpected path.'
if ! tar -tvzf "$image_bundle_path" | awk 'substr($1, 1, 1) != "-" { exit 1 }'; then
	fail 'Image archive contains a link or special filesystem entry.'
fi

tar --no-same-owner --no-same-permissions -xzf "$image_bundle_path" -C "$verification_dir"
(
	cd "$verification_dir"
	sha256sum -c offline-images-files.sha256
) || fail 'Extracted image archive checksum mismatch.'

source "$SCRIPT_DIR/offline-images-lib.sh"
release_root="$(chidemoon_release_root "$release_bundle_path")" || fail 'The bound release root is invalid.'
release_sha="$(sha256sum "$release_bundle_path" | awk '{print $1}')"
[[ "$release_sha" =~ ^[a-f0-9]{64}$ ]] || fail 'Unable to calculate the release checksum.'

image_listing="$(chidemoon_release_compose_images "$release_bundle_path")" || fail 'Unable to read Compose image references from the sealed release.'
lock_listing="$(chidemoon_read_image_lock "$verification_dir/offline-images.lock" "$release_sha" "$release_root")" || fail 'The image lock is invalid.'
mapfile -t expected_images <<< "$image_listing"
mapfile -t locked_images <<< "$lock_listing"
(( ${#expected_images[@]} > 0 )) || fail 'The sealed Compose file declares no Docker images.'
(( ${#expected_images[@]} == ${#locked_images[@]} )) || fail 'The image lock does not cover every Compose image.'

declare -A expected_by_reference=()
for image in "${expected_images[@]}"; do
	expected_by_reference[$image]=1
done
for record in "${locked_images[@]}"; do
	reference="${record%%$'\t'*}"
	[[ -n "${expected_by_reference[$reference]:-}" ]] || fail "The image lock contains a non-Compose image: $reference"
done

printf 'Verified sealed offline Docker image archive for: %s\n' "$release_root"
