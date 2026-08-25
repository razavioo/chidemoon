#!/usr/bin/env bash
# Compile every kalahamoon-*.po file in languages/ into a matching .mo next to it.
# Requires the `msgfmt` binary (installed with gettext on every major platform).
#
# Usage: bin/build-translations.sh

set -euo pipefail

DIR="$(cd "$(dirname "$0")/.." && pwd)/languages"

if ! command -v msgfmt >/dev/null 2>&1; then
	echo "msgfmt not found — install gettext (brew install gettext / apt-get install gettext)." >&2
	exit 1
fi

cd "$DIR"

shopt -s nullglob
for po in kalahamoon-*.po; do
	mo="${po%.po}.mo"
	echo "→ $po → $mo"
	msgfmt -o "$mo" "$po"
done

if command -v wp >/dev/null 2>&1; then
	echo "Generating JavaScript translation catalogs..."
	wp i18n make-json "$DIR" "$DIR" --no-purge
else
	echo "wp not found — skipping JavaScript translation JSON generation."
fi

echo "Translations compiled."
