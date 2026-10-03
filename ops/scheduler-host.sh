#!/usr/bin/env bash
set -euo pipefail

# Install this script in the host's scheduler (for example every five minutes)
# instead of enabling visitor-driven WordPress cron.
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
environment_file="${CHIDEMOON_ENV_FILE:-$ROOT_DIR/.env}"
if [[ -z "${CHIDEMOON_ENV_FILE:-}" && ! -f "$environment_file" && "$ROOT_DIR" = */releases/* ]]; then
	# Immutable releases keep secrets in the deployment root, outside the bundle.
	environment_file="${ROOT_DIR%/releases/*}/.env"
fi
docker compose --env-file "$environment_file" -f "$ROOT_DIR/compose.yml" run --rm --no-deps --pull never scheduler
