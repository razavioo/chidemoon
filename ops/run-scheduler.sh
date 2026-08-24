#!/bin/sh
set -eu

# WordPress cron is deliberately disabled for web traffic. This one-shot task
# is invoked by the host scheduler so delayed visitors cannot delay moderation,
# alert, or cleanup work.
wp core is-installed --allow-root

if wp action-scheduler run --batch-size=25 --batches=1 --allow-root; then
	:
fi

wp cron event run --due-now --allow-root
wp eval "do_action( 'chidemoon_core_scheduler_heartbeat' );" --allow-root
