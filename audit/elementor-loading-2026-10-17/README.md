# Elementor loading latency — 2026-10-17

Reproduced editor loading screen on page 62 using a temporary authenticated diagnostic session, without content saves. Editor eventually attached after a large get_widgets_config request. Apache mod_deflate was active but omitted application/json, sending multi-megabyte Elementor bootstrap responses uncompressed.

Added ops/apache-json-compression.conf and a read-only WordPress Apache config mount in compose.yml. Deployed config to the running container, configtested, and gracefully reloaded Apache. Updated the production compose mount while preserving other production settings. Original compose backed up under /opt/chidemoon/backups/reycob-hotspots-20261017/compose.yml.before.

Authenticated browser reload: loader hidden in 12,671 ms; bootstrap response decoded 8,176,893 bytes, transferred 596,167 compressed bytes, duration 2,870 ms. Frontend preview and editor panel ready. External telemetry CORS errors did not prevent initialization. Apache syntax and docker compose validation passed. Temporary diagnostic session revoked; local credential artifacts cleared.
