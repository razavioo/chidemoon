#!/usr/bin/env bash
set -euo pipefail

# AppleDouble sidecars can be created during macOS-to-Linux transfers and WordPress may parse them as plugins.
find /var/www/html/wp-content -type f -name '._*' -delete 2>/dev/null || true

wp core is-installed --allow-root || {
	printf 'WordPress is not installed yet. Complete initial WP installation first.\n' >&2
	exit 1
}

for package in /packages/kalahamoon.zip /packages/chidemoon-theme.zip; do
	if [ ! -f "$package" ]; then
		printf 'Missing package: %s\n' "$package" >&2
		exit 1
	fi
done

if [ ! -f /packages/installed-files.sha256 ]; then
	printf 'Missing installed-file checksum manifest. Rebuild the Chidemoon packages.\n' >&2
	exit 1
fi

if [ -f /packages/manifest.sha256 ]; then
	(
		cd /packages
		if command -v sha256sum >/dev/null 2>&1; then
			sha256sum -c manifest.sha256
		elif command -v shasum >/dev/null 2>&1; then
			shasum -a 256 -c manifest.sha256
		else
			printf 'No SHA-256 checksum utility is available.\n' >&2
			exit 1
		fi
	)
fi

wp plugin install /packages/kalahamoon.zip --force --activate --allow-root
if wp plugin is-installed chidemoon-helper --allow-root; then
	wp plugin deactivate chidemoon-helper --allow-root || true
	wp plugin delete chidemoon-helper --allow-root
fi
wp theme install /packages/chidemoon-theme.zip --force --activate --allow-root
wp config set DISABLE_WP_CRON true --raw --type=constant --allow-root

# Package checks prove the transfer; these checks prove the active WordPress
# files were actually replaced with the verified package contents.
(
	cd /var/www/html
	if command -v sha256sum >/dev/null 2>&1; then
		sha256sum -c /packages/installed-files.sha256
	else
		shasum -a 256 -c /packages/installed-files.sha256
	fi
)

wp rewrite flush --hard --allow-root

printf 'Chidemoon WordPress assets installed and activated.\n'
