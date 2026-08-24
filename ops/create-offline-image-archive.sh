#!/usr/bin/env bash
set -euo pipefail

# Docker image archives are deliberately separate from source bundles: image
# layers are large and platform-specific, while this lock binds them to exactly
# one sealed release without placing any registry credentials in the artifact.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
bundle_path="${1:-}"
bundle_checksum_path="${2:-${bundle_path}.sha256}"
artifact_dir="${CHIDEMOON_ARTIFACT_DIR:-$(dirname "$bundle_path")}"

fail() {
	printf 'Offline image archive build failed: %s\n' "$1" >&2
	exit 1
}

[[ -n "$bundle_path" && -f "$bundle_path" ]] || fail 'Pass an existing sealed release bundle.'
[[ -f "$bundle_checksum_path" ]] || fail 'The release checksum sidecar is missing.'
for command in docker tar sha256sum bash; do
	command -v "$command" >/dev/null 2>&1 || fail "Required command is unavailable: $command"
done

bash "$SCRIPT_DIR/verify-release-bundle.sh" "$bundle_path" "$bundle_checksum_path"
source "$SCRIPT_DIR/offline-images-lib.sh"

image_listing="$(chidemoon_release_compose_images "$bundle_path")" || fail 'Unable to read Compose image references from the sealed release.'
mapfile -t images <<< "$image_listing"
(( ${#images[@]} > 0 )) || fail 'The sealed Compose file declares no Docker images.'

declare -A image_ids=()
for image in "${images[@]}"; do
	image_id="$(docker image inspect --format '{{.Id}}' "$image" 2>/dev/null || true)"
	[[ "$image_id" =~ ^sha256:[a-f0-9]{64}$ ]] || fail "Required image is not available locally: $image"
	image_ids[$image]="$image_id"
done

release_root="$(chidemoon_release_root "$bundle_path")" || fail 'The release root is invalid.'
bundle_sha="$(sha256sum "$bundle_path" | awk '{print $1}')"
[[ "$bundle_sha" =~ ^[a-f0-9]{64}$ ]] || fail 'Unable to calculate the release checksum.'
bundle_stem="$(basename "${bundle_path%.tar.gz}")"
image_bundle="$artifact_dir/${bundle_stem}-images.tar.gz"
image_checksum_path="${image_bundle}.sha256"
[[ ! -e "$image_bundle" && ! -e "$image_checksum_path" ]] || fail "Offline image artifact already exists: $(basename "$image_bundle")"

mkdir -p "$artifact_dir"
staging_dir="$(mktemp -d "${TMPDIR:-/tmp}/chidemoon-images.XXXXXX")"
cleanup() {
	rm -rf -- "$staging_dir"
}
trap cleanup EXIT

docker image save --output "$staging_dir/docker-images.tar" "${images[@]}"

{
	printf 'format=1\n'
	printf 'release_bundle_sha256=%s\n' "$bundle_sha"
	printf 'release_root=%s\n' "$release_root"
	for image in "${images[@]}"; do
		printf 'image=%s id=%s\n' "$image" "${image_ids[$image]}"
	done
} > "$staging_dir/offline-images.lock"

(
	cd "$staging_dir"
	sha256sum docker-images.tar offline-images.lock > offline-images-files.sha256
)

tar \
	--sort=name \
	--owner=0 \
	--group=0 \
	--numeric-owner \
	-C "$staging_dir" \
	-czf "$image_bundle" \
	docker-images.tar \
	offline-images.lock \
	offline-images-files.sha256

(
	cd "$artifact_dir"
	sha256sum "$(basename "$image_bundle")" > "$(basename "$image_checksum_path")"
)

bash "$SCRIPT_DIR/verify-offline-image-archive.sh" \
	"$image_bundle" \
	"$image_checksum_path" \
	"$bundle_path" \
	"$bundle_checksum_path"

printf 'Created sealed offline Docker image archive: %s\n' "$image_bundle"
