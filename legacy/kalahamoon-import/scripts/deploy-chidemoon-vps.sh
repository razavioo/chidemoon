#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT=${PROJECT_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}

THEME_ASSET_REPAIR=${CHIDEMOON_THEME_ASSET_REPAIR:-false}
THEME_ASSET_SENTINEL='I_UNDERSTAND_WORDPRESS_THEME_ASSET_REPAIR_WITHOUT_DATA_MIGRATION'
RETIRED_CONTENT_ONLY_REPAIR=${CHIDEMOON_CONTENT_ONLY_REPAIR:-false}
CONSUMER_BOOTSTRAP=${CHIDEMOON_CONSUMER_BOOTSTRAP:-false}
CONSUMER_BOOTSTRAP_SENTINEL='I_UNDERSTAND_CATALOG_CONSUMER_BOOTSTRAP_IS_NOT_LAUNCH_READY'
COMPOSE_FILE=${COMPOSE_FILE:-compose.prod.yml}
COMPOSE_ENV_FILE=${COMPOSE_ENV_FILE:-.env}
CHIDEMOON_HEALTH_URL=${CHIDEMOON_HEALTH_URL:-https://chidemoon.com}
CHIDEMOON_STABILITY_WINDOW_SECONDS=${CHIDEMOON_STABILITY_WINDOW_SECONDS:-900}
CHIDEMOON_STABILITY_POLL_SECONDS=${CHIDEMOON_STABILITY_POLL_SECONDS:-30}
DRY_RUN=${CHIDEMOON_DEPLOY_DRY_RUN:-false}
REQUIRE_LAUNCH_READY=${CHIDEMOON_REQUIRE_LAUNCH_READY:-true}

cd "$PROJECT_ROOT"

log() {
  printf '\n==> %s\n' "$*"
}

fail() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

run() {
  if [[ "$DRY_RUN" == true ]]; then
    printf 'DRY RUN: %q' "$1" >&2
    shift
    for arg in "$@"; do
      printf ' %q' "$arg" >&2
    done
    printf '\n' >&2
    return 0
  fi

  "$@"
}

compose() {
  docker compose -f "$COMPOSE_FILE" --env-file "$COMPOSE_ENV_FILE" "$@"
}

check_public_route() {
  local health_host health_port
  if curl -fsSI --max-time 15 "$CHIDEMOON_HEALTH_URL" >/dev/null; then
    return 0
  fi

  health_host="${CHIDEMOON_HEALTH_URL#*://}"
  health_host="${health_host%%/*}"
  health_host="${health_host%%:*}"
  health_port=80
  [[ "$CHIDEMOON_HEALTH_URL" == https://* ]] && health_port=443
  curl -fsSI --max-time 15 --resolve "${health_host}:${health_port}:127.0.0.1" "$CHIDEMOON_HEALTH_URL" >/dev/null
}

usage() {
  cat <<'MSG'
Usage: ./scripts/deploy-chidemoon-vps.sh

Deploys prebuilt, checksummed Chidemoon WordPress assets from a sealed release.
It verifies the package, technical rendering readiness, and public-route
stability. Initial WordPress installation is a separate administrator action.

Environment:
  COMPOSE_FILE=compose.prod.yml
  COMPOSE_ENV_FILE=.env
  CHIDEMOON_HEALTH_URL=https://chidemoon.com
  CHIDEMOON_THEME_ASSET_REPAIR=<exact sentinel>  Replace only the sealed theme.
  CHIDEMOON_CONSUMER_BOOTSTRAP=<exact sentinel>  Install sealed consumer assets before connector provisioning.
  CHIDEMOON_STABILITY_WINDOW_SECONDS=900
  CHIDEMOON_REQUIRE_LAUNCH_READY=true  Block promotion when the theme or catalog consumer is unavailable.
  CHIDEMOON_DEPLOY_DRY_RUN=true  Print guarded commands without running them.

The explicit consumer bootstrap requires CHIDEMOON_REQUIRE_LAUNCH_READY=false,
all connector values absent, and a completed WordPress core installation. It
stops the catalog scheduler, verifies the sealed theme/plugin and public route,
then reports an intentionally non-ready consumer. Provision the connector and
run the normal sealed deployment afterward.
MSG
}

catalog_connector_values_present() {
  local connector_value
  for connector_value in \
    "${CHIDEMOON_CATALOG_CONNECTOR_CLIENT_ID:-}" \
    "${CHIDEMOON_CATALOG_CONNECTOR_CLIENT_SECRET:-}" \
    "${CHIDEMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE:-}"; do
    [[ -n "${connector_value//[[:space:]]/}" ]] || return 1
  done
  return 0
}

catalog_connector_values_absent() {
  local connector_value
  for connector_value in \
    "${CHIDEMOON_CATALOG_CONNECTOR_CLIENT_ID:-}" \
    "${CHIDEMOON_CATALOG_CONNECTOR_CLIENT_SECRET:-}" \
    "${CHIDEMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE:-}"; do
    [[ -z "${connector_value//[[:space:]]/}" ]] || return 1
  done
  return 0
}

require_catalog_connector_configuration() {
  catalog_connector_values_present || fail 'Catalog connector configuration is incomplete. Provision all three values, then run the normal sealed deployment. Use the explicit consumer bootstrap only before provisioning.'
}

require_consumer_bootstrap_configuration() {
  [[ "$REQUIRE_LAUNCH_READY" == false ]] || fail 'CHIDEMOON_CONSUMER_BOOTSTRAP requires CHIDEMOON_REQUIRE_LAUNCH_READY=false because bootstrap never grants launch readiness.'
  catalog_connector_values_absent || fail 'CHIDEMOON_CONSUMER_BOOTSTRAP is only for an unprovisioned consumer. Keep all connector values absent or run the normal sealed deployment.'
}

assert_services_healthy() {
  local include_catalog_scheduler=${1:-true}
  local service container_id state runtime_state health_state attempt healthy
  local -a services
  [[ "$include_catalog_scheduler" == true || "$include_catalog_scheduler" == false ]] || fail 'Invalid Chidemoon scheduler health requirement'
  services=(chidemoon-db chidemoon-wordpress)
  [[ "$include_catalog_scheduler" == true ]] && services+=(chidemoon-cron)

  if [[ "$DRY_RUN" == true ]]; then
    if [[ "$include_catalog_scheduler" == true ]]; then
      printf 'DRY RUN: require healthy Chidemoon database, WordPress, and catalog scheduler services\n'
    else
      printf 'DRY RUN: require healthy Chidemoon database and WordPress services\n'
    fi
    return 0
  fi

  # A removed MU file is visible to the next PHP process, while Docker's health
  # state may need one polling interval to reflect that recovery.
  for ((attempt = 0; attempt < 12; attempt++)); do
    healthy=true
    for service in "${services[@]}"; do
      container_id=$(compose ps -q "$service")
      if [[ -z "$container_id" ]]; then
        healthy=false
        break
      fi
      state=$(docker inspect --format '{{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$container_id")
      read -r runtime_state health_state <<< "$state"
      if [[ "$runtime_state" != running || "$health_state" != healthy ]]; then
        healthy=false
        break
      fi
    done
    [[ "$healthy" == true ]] && return 0
    sleep 5
  done

  fail "Required Chidemoon service is not healthy after compatibility cleanup: $service (${state:-unavailable})"
}

refresh_chidemoon_runtime() {
  local include_catalog_scheduler=${1:-true}
  [[ "$include_catalog_scheduler" == true || "$include_catalog_scheduler" == false ]] || fail 'Invalid Chidemoon scheduler refresh requirement'

  # The mounted deployment files are resolved when containers are created.
  # Recreate these two consumers so a sealed release cannot retain an old MU
  # transform or scheduler command from the prior release directory.
  if [[ "$include_catalog_scheduler" == true ]]; then
    run compose up -d --force-recreate chidemoon-wordpress chidemoon-cron
    return
  fi

  # A bootstrap has no confidential connector yet. Stop any old scheduler so
  # it cannot retry stale credentials while the public origin is being proved.
  run compose stop chidemoon-cron
  run compose up -d --force-recreate chidemoon-wordpress
}

remove_retired_chidemoon_mu_plugins() {
  local wordpress_container mounted_plugin_directory retired_plugin target_plugin
  local retired_plugins=(chidemoon-content-migration.php kalahamoon-matomo.php)

  if [[ "$DRY_RUN" == true ]]; then
    printf 'DRY RUN: remove only the retired Chidemoon MU content transform and public tracker from the active mount\n'
    return 0
  fi

  wordpress_container=$(compose ps -q chidemoon-wordpress)
  [[ -n "$wordpress_container" ]] || fail 'Required Chidemoon WordPress service is not running'
  mounted_plugin_directory=$(docker inspect --format '{{range .Mounts}}{{if eq .Destination "/var/www/html/wp-content/mu-plugins"}}{{.Source}}{{end}}{{end}}' "$wordpress_container")
  [[ "$mounted_plugin_directory" = /* && "$mounted_plugin_directory" != / && "$mounted_plugin_directory" != *$'\n'* && "${mounted_plugin_directory##*/}" == mu-plugins ]] || {
    fail 'Unable to identify the active Chidemoon MU-plugin mount'
  }

  for retired_plugin in "${retired_plugins[@]}"; do
    target_plugin="$mounted_plugin_directory/$retired_plugin"
    [[ ! -L "$target_plugin" ]] || fail "Refusing to remove a symlinked retired MU plugin: $retired_plugin"
    [[ ! -e "$target_plugin" ]] && continue
    [[ -f "$target_plugin" ]] || fail "Refusing to remove a non-file retired MU plugin: $retired_plugin"
    rm -f -- "$target_plugin"
    log "Removed retired MU plugin: $retired_plugin"
  done
}

check_wordpress_core() {
  if [[ "$DRY_RUN" == true ]]; then
    printf 'DRY RUN: require the separately installed WordPress core before sealed asset installation\n'
    return 0
  fi

  compose --profile tools run --rm --no-deps chidemoon-wpcli wp core is-installed --allow-root
}

check_wordpress_runtime() {
  if [[ "$DRY_RUN" == true ]]; then
    printf 'DRY RUN: require WordPress core, the generic catalog plugin, and the Chidemoon theme to be active\n'
    return 0
  fi

  compose --profile tools run --rm --no-deps chidemoon-wpcli wp core is-installed --allow-root
  compose --profile tools run --rm --no-deps chidemoon-wpcli wp plugin is-active kalahamoon --allow-root
  compose --profile tools run --rm --no-deps chidemoon-wpcli wp theme is-active chidemoon-theme --allow-root
  compose --profile tools run --rm --no-deps chidemoon-wpcli wp help chidemoon launch-readiness --allow-root >/dev/null
}

verify_theme_manifest() {
  run compose --profile tools run --rm --no-deps --user root chidemoon-wpcli sh -lc '
    cd /var/www/html
    theme_manifest=$(mktemp)
    grep " wp-content/themes/chidemoon-theme/" /packages/installed-files.sha256 > "$theme_manifest"
    test -s "$theme_manifest"
    if command -v sha256sum >/dev/null 2>&1; then
      sha256sum -c "$theme_manifest"
    else
      shasum -a 256 -c "$theme_manifest"
    fi
    rm -f "$theme_manifest"
  '
}

check_technical_readiness() {
  local readiness_args
  if [[ "$DRY_RUN" == true ]]; then
    printf 'DRY RUN: run chidemoon launch-readiness against only the active theme and catalog consumer\n'
    [[ "$REQUIRE_LAUNCH_READY" == true ]] && printf 'DRY RUN: block promotion when a technical readiness check fails\n'
    return 0
  fi

  readiness_args=(wp chidemoon launch-readiness --allow-root)
  [[ "$REQUIRE_LAUNCH_READY" == true ]] && readiness_args+=(--require-ready)
  compose --profile tools run --rm --no-deps chidemoon-wpcli "${readiness_args[@]}"
}

report_bootstrap_non_readiness() {
  if [[ "$DRY_RUN" == true ]]; then
    printf 'DRY RUN: report the intentionally non-ready catalog consumer without treating it as launch-ready\n'
    return 0
  fi

  compose --profile tools run --rm --no-deps chidemoon-wpcli wp eval '
    $report = Chidemoon_Launch_Readiness::report();
    WP_CLI::line( wp_json_encode( $report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
    exit( ! empty( $report["ready"] ) ? 1 : 0 );
  ' --allow-root || fail 'Consumer bootstrap did not produce the required non-ready report. Provisioning must use the normal sealed deployment.'
}

observe_stability() {
  local include_catalog_scheduler=${1:-true}
  local -a services restart_baselines
  local index service container_id state runtime_state health_state restart_count elapsed
  [[ "$include_catalog_scheduler" == true || "$include_catalog_scheduler" == false ]] || fail 'Invalid Chidemoon scheduler observation requirement'

  if [[ "$DRY_RUN" == true ]]; then
    if [[ "$include_catalog_scheduler" == true ]]; then
      printf 'DRY RUN: observe Chidemoon service health, restart counts, WordPress state, and public TLS route for %s seconds\n' "$CHIDEMOON_STABILITY_WINDOW_SECONDS"
    else
      printf 'DRY RUN: observe Chidemoon database, WordPress state, and public TLS route for %s seconds without the unprovisioned scheduler\n' "$CHIDEMOON_STABILITY_WINDOW_SECONDS"
    fi
    return 0
  fi

  services=(chidemoon-db chidemoon-wordpress)
  [[ "$include_catalog_scheduler" == true ]] && services+=(chidemoon-cron)
  restart_baselines=()
  for index in "${!services[@]}"; do
    container_id=$(compose ps -q "${services[$index]}")
    [[ -n "$container_id" ]] || fail "Required Chidemoon service disappeared before observation: ${services[$index]}"
    restart_baselines[$index]=$(docker inspect --format '{{.RestartCount}}' "$container_id")
  done

  elapsed=0
  while (( elapsed < CHIDEMOON_STABILITY_WINDOW_SECONDS )); do
    for index in "${!services[@]}"; do
      service=${services[$index]}
      container_id=$(compose ps -q "$service")
      [[ -n "$container_id" ]] || fail "Required Chidemoon service disappeared during observation: $service"
      state=$(docker inspect --format '{{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}} {{.RestartCount}}' "$container_id")
      read -r runtime_state health_state restart_count <<< "$state"
      [[ "$runtime_state" == running && "$health_state" == healthy ]] || fail "Required Chidemoon service is not healthy: $service ($state)"
      [[ "$restart_count" == "${restart_baselines[$index]}" ]] || fail "Chidemoon restart count increased during observation: $service"
    done
    compose --profile tools run --rm --no-deps chidemoon-wpcli wp plugin is-active kalahamoon --allow-root
    compose --profile tools run --rm --no-deps chidemoon-wpcli wp theme is-active chidemoon-theme --allow-root
    check_public_route || fail 'Chidemoon public TLS route did not remain available'
    sleep "$CHIDEMOON_STABILITY_POLL_SECONDS"
    elapsed=$((elapsed + CHIDEMOON_STABILITY_POLL_SECONDS))
  done
}

if [[ "${1:-}" == --help || "${1:-}" == -h ]]; then
  usage
  exit 0
fi

[[ "$RETIRED_CONTENT_ONLY_REPAIR" == false ]] || fail 'CHIDEMOON_CONTENT_ONLY_REPAIR is retired; deploy a sealed release or use the theme asset repair mode.'
[[ "$THEME_ASSET_REPAIR" == false || "$THEME_ASSET_REPAIR" == "$THEME_ASSET_SENTINEL" ]] || {
  fail 'CHIDEMOON_THEME_ASSET_REPAIR requires its exact confirmation sentinel'
}
[[ "$CONSUMER_BOOTSTRAP" == false || "$CONSUMER_BOOTSTRAP" == "$CONSUMER_BOOTSTRAP_SENTINEL" ]] || {
  fail 'CHIDEMOON_CONSUMER_BOOTSTRAP requires its exact confirmation sentinel'
}
[[ "$THEME_ASSET_REPAIR" == false || "$CONSUMER_BOOTSTRAP" == false ]] || {
  fail 'CHIDEMOON_THEME_ASSET_REPAIR and CHIDEMOON_CONSUMER_BOOTSTRAP cannot be combined'
}
[[ "$CHIDEMOON_STABILITY_WINDOW_SECONDS" =~ ^[0-9]+$ && "$CHIDEMOON_STABILITY_POLL_SECONDS" =~ ^[1-9][0-9]*$ ]] || {
  fail 'Invalid Chidemoon stability window configuration'
}
[[ "$REQUIRE_LAUNCH_READY" == true || "$REQUIRE_LAUNCH_READY" == false ]] || {
  fail 'CHIDEMOON_REQUIRE_LAUNCH_READY must be true or false'
}
[[ -f "$COMPOSE_FILE" ]] || fail "Compose file not found: $COMPOSE_FILE"
[[ -f "$COMPOSE_ENV_FILE" ]] || fail "$COMPOSE_ENV_FILE not found. Keep the host-managed production environment outside release bundles."

if [[ "$DRY_RUN" != true ]]; then
  command -v docker >/dev/null 2>&1 || fail 'Required command is missing: docker'
  command -v curl >/dev/null 2>&1 || fail 'Required command is missing: curl'
  docker compose version >/dev/null 2>&1 || fail 'Docker Compose plugin is not available'
else
  command -v docker >/dev/null 2>&1 || log 'DRY RUN: docker is not installed locally; skipping Docker availability check'
  command -v curl >/dev/null 2>&1 || log 'DRY RUN: curl is not installed locally; skipping curl availability check'
fi

log 'Validating sealed Chidemoon release packages'
[[ -f release.env && -f release-validation.status ]] || fail 'Chidemoon deployment must run from an extracted sealed release candidate'
"$PROJECT_ROOT/scripts/verify-release-bundle.sh" "$PROJECT_ROOT"

if [[ "$THEME_ASSET_REPAIR" == "$THEME_ASSET_SENTINEL" ]]; then
  grep -qx 'database_contract_changes=none' release-validation.status || {
    fail 'Theme asset repair requires a release with no Prisma contract changes'
  }
  [[ -f chidemoon/packages/chidemoon-theme.zip ]] || fail 'Theme asset repair requires the sealed Chidemoon theme package'
  [[ -f chidemoon/packages/installed-files.sha256 ]] || fail 'Theme asset repair requires the installed-file manifest'
fi

log 'Validating Compose configuration'
run compose config >/dev/null

if [[ "$THEME_ASSET_REPAIR" == "$THEME_ASSET_SENTINEL" ]]; then
  log 'Applying sealed Chidemoon theme asset repair without a WordPress data migration'
  require_catalog_connector_configuration
  assert_services_healthy false
  remove_retired_chidemoon_mu_plugins
  check_wordpress_core
  run compose --profile tools run --rm --no-deps --user root chidemoon-wpcli wp theme install /packages/chidemoon-theme.zip --force --activate --allow-root
  refresh_chidemoon_runtime
  assert_services_healthy
  verify_theme_manifest
  check_technical_readiness
  observe_stability
  log "Chidemoon theme asset repair complete after ${CHIDEMOON_STABILITY_WINDOW_SECONDS} seconds of stable service"
  exit 0
fi

if [[ "$CONSUMER_BOOTSTRAP" == "$CONSUMER_BOOTSTRAP_SENTINEL" ]]; then
  require_consumer_bootstrap_configuration

  log 'Bootstrapping sealed Chidemoon consumer assets before connector provisioning'
  assert_services_healthy false
  remove_retired_chidemoon_mu_plugins
  check_wordpress_core

  log 'Installing verified Chidemoon WordPress assets for the unprovisioned consumer'
  run compose --profile tools run --rm --no-deps --user root chidemoon-wpcli bash /install-chidemoon.sh

  log 'Refreshing the public consumer runtime without starting the catalog scheduler'
  refresh_chidemoon_runtime false
  assert_services_healthy false
  check_wordpress_runtime
  verify_theme_manifest

  log 'Reporting the intentionally non-ready consumer state'
  report_bootstrap_non_readiness

  log 'Observing the sealed consumer shell and public route before connector provisioning'
  observe_stability false
  log 'Consumer bootstrap completed and remains intentionally not launch-ready. Provision all connector values, then run the normal sealed deployment.'
  exit 0
fi

require_catalog_connector_configuration

log 'Checking the existing Chidemoon core service state'
assert_services_healthy false
remove_retired_chidemoon_mu_plugins
check_wordpress_core

log 'Installing verified Chidemoon WordPress assets'
run compose --profile tools run --rm --no-deps --user root chidemoon-wpcli bash /install-chidemoon.sh

log 'Refreshing Chidemoon runtime mounts and the catalog scheduler'
refresh_chidemoon_runtime
assert_services_healthy
check_wordpress_runtime

log 'Checking Chidemoon technical readiness before release promotion'
check_technical_readiness

log 'Observing Chidemoon service and public-route stability'
observe_stability

if [[ "$REQUIRE_LAUNCH_READY" == true ]]; then
  log "Chidemoon launch-ready deployment complete after ${CHIDEMOON_STABILITY_WINDOW_SECONDS} seconds of stable service"
else
  log "Chidemoon non-launch deployment complete after ${CHIDEMOON_STABILITY_WINDOW_SECONDS} seconds of stable service; launch readiness was not granted"
fi
