#!/bin/sh
set -eu

# WordPress cron is deliberately disabled for web traffic. This one-shot task
# is invoked by the host scheduler so delayed visitors cannot delay moderation,
# alert, or cleanup work. Each step has a distinct role:
#  1. action-scheduler run — primary AI job queue (Chidemoon_AI_Runner::enqueue).
#  2. cron event run --due-now — WP-Cron fallback queue + daily housekeeping
#     (chidemoon_core_daily_housekeeping) when Action Scheduler is unavailable.
#  3. heartbeat — liveness timestamp checked by the readiness report.
# Runs as www-data (see compose.yml); no --allow-root needed.
wp core is-installed

if wp action-scheduler run --batch-size=25 --batches=1; then
	:
fi

wp cron event run --due-now
wp eval "do_action( 'chidemoon_core_scheduler_heartbeat' );"
