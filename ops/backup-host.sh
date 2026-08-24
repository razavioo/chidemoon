#!/usr/bin/env bash
set -euo pipefail

# A host scheduler runs this one-shot backup after the database healthcheck.
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
docker compose --env-file "$ROOT_DIR/.env" -f "$ROOT_DIR/compose.yml" run --rm --no-deps backup
