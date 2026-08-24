#!/usr/bin/env bash
set -euo pipefail

bundle_path="${1:-}"
checksum_path="${2:-${bundle_path}.sha256}"

fail() {
	printf 'Release verification failed: %s\n' "$1" >&2
	exit 1
}

[[ -n "$bundle_path" && -f "$bundle_path" ]] || fail 'Pass an existing release bundle.'
[[ -f "$checksum_path" ]] || fail 'The release checksum sidecar is missing.'
command -v tar >/dev/null 2>&1 || fail 'tar is required.'
command -v sha256sum >/dev/null 2>&1 || fail 'sha256sum is required.'

bundle_dir="$(cd "$(dirname "$bundle_path")" && pwd)"
bundle_name="$(basename "$bundle_path")"
checksum_dir="$(cd "$(dirname "$checksum_path")" && pwd)"
checksum_name="$(basename "$checksum_path")"
[[ "$bundle_dir" == "$checksum_dir" ]] || fail 'Bundle and checksum must be in the same directory.'

(
	cd "$bundle_dir"
	sha256sum -c "$checksum_name"
) || fail 'Bundle checksum mismatch.'

archive_listing="$(mktemp "${TMPDIR:-/tmp}/chidemoon-listing.XXXXXX")"
verification_dir="$(mktemp -d "${TMPDIR:-/tmp}/chidemoon-verify.XXXXXX")"
cleanup() {
	rm -f -- "$archive_listing"
	rm -rf -- "$verification_dir"
}
trap cleanup EXIT

tar -tzf "$bundle_path" > "$archive_listing"
first_entry="$(head -n 1 "$archive_listing")"
release_root="${first_entry%%/*}"
[[ "$release_root" =~ ^chidemoon-release-[A-Za-z0-9._-]+$ ]] || fail 'Archive root name is invalid.'

while IFS= read -r entry; do
	[[ -n "$entry" ]] || continue
	case "$entry" in
		"$release_root"|"$release_root"/*) ;;
		*) fail 'Archive contains more than one root or an unsafe path.' ;;
	esac
	relative_path="${entry#"$release_root"/}"
	case "$relative_path" in
		..|../*|*/../*|.env|.env/*|.git|.git/*|backups|backups/*) fail 'Archive contains a host-managed or forbidden path.' ;;
	esac
done < "$archive_listing"

# Links and special files would make a checksum-valid archive unsafe to unpack
# into a deployment root, so release bundles intentionally contain only files
# and directories.
if ! tar -tvzf "$bundle_path" | awk 'substr($1, 1, 1) !~ /[-d]/ { exit 1 }'; then
	fail 'Archive contains a link or special filesystem entry.'
fi

tar --no-same-owner --no-same-permissions -xzf "$bundle_path" -C "$verification_dir"
release_dir="$verification_dir/$release_root"
[[ -f "$release_dir/release-manifest.json" ]] || fail 'Release manifest is missing.'
[[ -f "$release_dir/release-files.sha256" ]] || fail 'Installed-file checksum manifest is missing.'
grep -q '"format": 1' "$release_dir/release-manifest.json" || fail 'Release manifest format is unsupported.'

(
	cd "$release_dir"
	sha256sum -c release-files.sha256
) || fail 'Extracted-file checksum mismatch.'

for required_path in compose.yml .env.example standalone-init.ps1 ops plugins themes vendor; do
	[[ -e "$release_dir/$required_path" ]] || fail "Release is missing required path: $required_path"
done

printf 'Verified sealed Chidemoon release: %s\n' "$release_root"
