#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=${ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}
COMPOSE_FILE=${COMPOSE_FILE:-$ROOT_DIR/chidemoon/deploy/compose.discourse.yml}
ENV_FILE=${ENV_FILE:-$ROOT_DIR/.env}
IMAGE_ARCHIVE=${IMAGE_ARCHIVE:-$ROOT_DIR/chidemoon/packages/chidemoon-discourse-2026.1.5.tar.zst}
HEALTH_URL=${HEALTH_URL:-https://community.chidemoon.com/site.json}

command -v docker >/dev/null 2>&1 || { echo "Docker is required" >&2; exit 1; }
command -v zstd >/dev/null 2>&1 || { echo "zstd is required" >&2; exit 1; }
[[ -f "$ENV_FILE" ]] || { echo "Missing environment file: $ENV_FILE" >&2; exit 1; }

for variable in DISCOURSE_ADMIN_EMAIL DISCOURSE_DB_PASSWORD DISCOURSE_SMTP_ADDRESS DISCOURSE_SMTP_DOMAIN DISCOURSE_SMTP_USER_NAME DISCOURSE_SMTP_PASSWORD DISCOURSE_NOTIFICATION_EMAIL; do
  grep -Eq "^${variable}=.+" "$ENV_FILE" || { echo "Missing ${variable} in ${ENV_FILE}" >&2; exit 1; }
done

if ! docker image inspect discourse/discourse:2026.1.5 >/dev/null 2>&1; then
  [[ -f "$IMAGE_ARCHIVE" ]] || { echo "Missing prebuilt image archive: $IMAGE_ARCHIVE" >&2; exit 1; }
  zstd -dc "$IMAGE_ARCHIVE" | docker load
fi

docker network inspect proxy >/dev/null 2>&1 || docker network create proxy >/dev/null
docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" config >/dev/null
docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" up -d chidemoon-discourse

for _ in {1..60}; do
  if docker exec chidemoon-discourse curl -fsS http://127.0.0.1/site.json >/dev/null 2>&1; then
    break
  fi
  sleep 5
done

docker exec chidemoon-discourse curl -fsS http://127.0.0.1/site.json >/dev/null
curl -fsS "$HEALTH_URL" >/dev/null
docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" ps chidemoon-discourse
