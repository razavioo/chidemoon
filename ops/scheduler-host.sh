#!/usr/bin/env bash
set -euo pipefail

# Install this script in the host's scheduler (for example every five minutes)
# instead of enabling visitor-driven WordPress cron.
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
docker compose --env-file "$ROOT_DIR/.env" -f "$ROOT_DIR/compose.yml" run --rm --no-deps --pull never scheduler
