#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
CHIDEMOON_DIR="$ROOT_DIR/chidemoon"
PACKAGES_DIR="$CHIDEMOON_DIR/packages"
KALAHAMOON_PLUGIN_DIR="$ROOT_DIR/apps/wordpress-plugin/kalahamoon"
SOURCE_REVISION="$(git -C "$ROOT_DIR" rev-parse --verify HEAD 2>/dev/null || printf 'unknown')"
SOURCE_TIMESTAMP="$(git -C "$ROOT_DIR" show -s --format=%cI HEAD 2>/dev/null || printf 'unknown')"
THEME_VERSION="$(sed -n 's/^Version:[[:space:]]*//p' "$CHIDEMOON_DIR/theme/chidemoon-theme/style.css" | head -n 1)"
KALAHAMOON_VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$KALAHAMOON_PLUGIN_DIR/kalahamoon.php" | head -n 1)"

mkdir -p "$PACKAGES_DIR"
rm -f "$PACKAGES_DIR/chidemoon-theme.zip" "$PACKAGES_DIR/chidemoon-helper.zip" "$PACKAGES_DIR/kalahamoon.zip" "$PACKAGES_DIR/manifest.json" "$PACKAGES_DIR/manifest.sha256" "$PACKAGES_DIR/installed-files.sha256"

if [ ! -d "$KALAHAMOON_PLUGIN_DIR" ]; then
	printf 'Missing Kalahamoon plugin directory: %s\n' "$KALAHAMOON_PLUGIN_DIR" >&2
	exit 1
fi

STAGING_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/chidemoon-packages.XXXXXX")"
cleanup() {
	rm -rf "$STAGING_ROOT"
}
trap cleanup EXIT

build_package() {
	local source_dir="$1"
	local archive_path="$2"
	local package_name
	package_name="$(basename "$source_dir")"

	cp -R "$source_dir" "$STAGING_ROOT/$package_name"
	find "$STAGING_ROOT/$package_name" -type f -name '._*' -delete

	if [ "$package_name" = 'kalahamoon' ]; then
		rm -rf \
			"$STAGING_ROOT/$package_name/.phpunit.result.cache" \
			"$STAGING_ROOT/$package_name/vendor" \
			"$STAGING_ROOT/$package_name/tests" \
			"$STAGING_ROOT/$package_name/phpunit.xml" \
			"$STAGING_ROOT/$package_name/composer.json" \
			"$STAGING_ROOT/$package_name/composer.lock"
	fi

	# ZIP records include modification times and input order, so normalize both
	# to make the signed artifact identity independent of the build machine.
	find "$STAGING_ROOT/$package_name" -exec touch -t 198001010000 {} +
	(
		cd "$STAGING_ROOT"
		find "$package_name" -print | LC_ALL=C sort | zip -Xq "$archive_path" -@
	)
	rm -rf "$STAGING_ROOT/$package_name"
}

write_installed_file_manifest() {
	local archive_path="$1"
	local archive_root="$2"
	local target_root="$3"
	local archive_entry
	local relative_path

	# The package checksum proves transport integrity. This manifest proves every
	# installed file was actually extracted, so a partial theme update cannot look healthy.
	unzip -tq "$archive_path" >/dev/null
	while IFS= read -r archive_entry; do
		case "$archive_entry" in
			"$archive_root/" | */) continue ;;
		esac

		relative_path="${archive_entry#"$archive_root/"}"
		if [ "$relative_path" = "$archive_entry" ] || [ -z "$relative_path" ]; then
			printf 'Unexpected entry in package %s: %s\n' "$archive_path" "$archive_entry" >&2
			exit 1
		fi

		printf '%s  %s/%s\n' \
			"$(unzip -p "$archive_path" "$archive_entry" | shasum -a 256 | awk '{print $1}')" \
			"$target_root" \
			"$relative_path"
	done < <(unzip -Z1 "$archive_path" | LC_ALL=C sort)
}

build_package "$CHIDEMOON_DIR/theme/chidemoon-theme" "$PACKAGES_DIR/chidemoon-theme.zip"
build_package "$KALAHAMOON_PLUGIN_DIR" "$PACKAGES_DIR/kalahamoon.zip"
(
	cd "$PACKAGES_DIR"
	for package in chidemoon-theme.zip kalahamoon.zip; do
		shasum -a 256 "$package"
	done
) > "$PACKAGES_DIR/manifest.sha256"

theme_sha256="$(awk '$2 == "chidemoon-theme.zip" { print $1 }' "$PACKAGES_DIR/manifest.sha256")"
kalahamoon_sha256="$(awk '$2 == "kalahamoon.zip" { print $1 }' "$PACKAGES_DIR/manifest.sha256")"

{
	write_installed_file_manifest \
		"$PACKAGES_DIR/chidemoon-theme.zip" \
		'chidemoon-theme' \
		'wp-content/themes/chidemoon-theme'
	write_installed_file_manifest \
		"$PACKAGES_DIR/kalahamoon.zip" \
		'kalahamoon' \
		'wp-content/plugins/kalahamoon'
} | LC_ALL=C sort > "$PACKAGES_DIR/installed-files.sha256"

cat > "$PACKAGES_DIR/manifest.json" <<JSON
{
  "generatedAt": "$SOURCE_TIMESTAMP",
  "sourceRevision": "$SOURCE_REVISION",
  "versions": {
    "theme": "$THEME_VERSION",
    "kalahamoonPlugin": "$KALAHAMOON_VERSION"
  },
  "packages": {
    "theme": { "file": "chidemoon-theme.zip", "sha256": "$theme_sha256" },
    "kalahamoonPlugin": { "file": "kalahamoon.zip", "sha256": "$kalahamoon_sha256" }
  },
  "installedFileChecksumManifest": "installed-files.sha256",
  "activationOrder": [
    "kalahamoon/kalahamoon.php",
    "chidemoon-theme"
  ]
}
JSON

printf 'Built packages in %s\n' "$PACKAGES_DIR"
