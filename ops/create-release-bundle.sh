#!/usr/bin/env bash
set -euo pipefail

# A release is built from a clean commit so its source can be reproduced and
# the bundle never accidentally contains host secrets or mutable uploads.
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ARTIFACT_DIR="${CHIDEMOON_ARTIFACT_DIR:-$ROOT_DIR/.deploy-artifacts}"

fail() {
	printf 'Release build failed: %s\n' "$1" >&2
	exit 1
}

for command in git tar sha256sum bash; do
	command -v "$command" >/dev/null 2>&1 || fail "Required command is unavailable: $command"
done

git -C "$ROOT_DIR" rev-parse --verify HEAD >/dev/null 2>&1 || fail 'A committed Git revision is required.'
if [[ -n "$(git -C "$ROOT_DIR" status --porcelain --untracked-files=all)" ]]; then
	fail 'Commit or explicitly discard all worktree changes before building a release.'
fi

for required_path in compose.yml .env.example standalone-init.ps1 ops plugins themes vendor; do
	[[ -e "$ROOT_DIR/$required_path" ]] || fail "Required release path is missing: $required_path"
done

# Production hosts have no package registry access. These reviewed archives are
# part of the artifact and must be checksum-verified before they are packaged.
for package in blocksy woocommerce; do
	[[ -f "$ROOT_DIR/vendor/${package}.zip" ]] || fail "Missing offline package: vendor/${package}.zip"
	[[ -f "$ROOT_DIR/vendor/${package}.sha256" ]] || fail "Missing offline package checksum: vendor/${package}.sha256"
	(
		cd "$ROOT_DIR/vendor"
		sha256sum -c "${package}.sha256"
	) || fail "Offline package checksum failed: ${package}"
done

# The complete Node/PHP contract suite is run before sealing a release. Keep
# this final artifact command POSIX-only so it remains usable on an operator
# host that has Docker but not a host-level Node runtime.
for script in "$ROOT_DIR"/ops/*.sh; do
	bash -n "$script"
done

revision="$(git -C "$ROOT_DIR" rev-parse HEAD)"
revision_short="${revision:0:12}"
commit_epoch="$(git -C "$ROOT_DIR" show -s --format=%ct HEAD)"
commit_timestamp="$(git -C "$ROOT_DIR" show -s --format=%cI HEAD)"
release_label="$(git -C "$ROOT_DIR" describe --tags --always --match 'v[0-9]*' HEAD | tr -cd 'A-Za-z0-9._-')"
release_root="chidemoon-release-${release_label}-${revision_short}"
bundle_name="${release_root}.tar.gz"

mkdir -p "$ARTIFACT_DIR"
bundle_path="$ARTIFACT_DIR/$bundle_name"
checksum_path="$bundle_path.sha256"
[[ ! -e "$bundle_path" && ! -e "$checksum_path" ]] || fail "Release artifact already exists: $bundle_name"

staging_dir="$(mktemp -d "${TMPDIR:-/tmp}/chidemoon-release.XXXXXX")"
cleanup() {
	rm -rf -- "$staging_dir"
}
trap cleanup EXIT

release_dir="$staging_dir/$release_root"
mkdir -p "$release_dir"

git -C "$ROOT_DIR" archive --format=tar "$revision" -- \
	compose.yml \
	.env.example \
	standalone-init.ps1 \
	ops \
	plugins \
	themes \
	vendor | tar -xf - -C "$release_dir"

cat > "$release_dir/release-manifest.json" <<EOF
{
  "format": 1,
  "revision": "$revision",
  "committedAt": "$commit_timestamp",
  "releaseRoot": "$release_root"
}
EOF

(
	cd "$release_dir"
	find . -type f ! -name 'release-files.sha256' -print0 \
		| LC_ALL=C sort -z \
		| xargs -0 sha256sum
) > "$release_dir/release-files.sha256"

tar \
	--sort=name \
	--mtime="@$commit_epoch" \
	--owner=0 \
	--group=0 \
	--numeric-owner \
	-C "$staging_dir" \
	-czf "$bundle_path" \
	"$release_root"

(
	cd "$ARTIFACT_DIR"
	sha256sum "$bundle_name" > "$(basename "$checksum_path")"
)

bash "$ROOT_DIR/ops/verify-release-bundle.sh" "$bundle_path" "$checksum_path"
printf 'Created sealed Chidemoon release: %s\n' "$bundle_path"
