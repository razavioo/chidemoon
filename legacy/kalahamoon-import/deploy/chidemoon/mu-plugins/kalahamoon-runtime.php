<?php
/**
 * Runtime-only connection settings for the co-located Chidemoon deployment.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$internal_api_url = trim( (string) getenv( 'KALAHAMOON_INTERNAL_API_URL' ) );
if ( '' !== $internal_api_url && ! defined( 'KALAHAMOON_INTERNAL_API_URL' ) ) {
	define( 'KALAHAMOON_INTERNAL_API_URL', rtrim( $internal_api_url, '/' ) );
}

// Keep the confidential connector credential outside WordPress options. The
// deployment supplies it only to this read-only consumer and its scheduler.
$catalog_connector_secret = trim( (string) getenv( 'KALAHAMOON_CATALOG_CONNECTOR_CLIENT_SECRET' ) );
if ( '' !== $catalog_connector_secret && ! defined( 'KALAHAMOON_CATALOG_CONNECTOR_CLIENT_SECRET' ) ) {
	define( 'KALAHAMOON_CATALOG_CONNECTOR_CLIENT_SECRET', $catalog_connector_secret );
}

$catalog_connector_client_id = trim( (string) getenv( 'KALAHAMOON_CATALOG_CONNECTOR_CLIENT_ID' ) );
if ( '' !== $catalog_connector_client_id && ! defined( 'KALAHAMOON_CATALOG_CONNECTOR_CLIENT_ID' ) ) {
	define( 'KALAHAMOON_CATALOG_CONNECTOR_CLIENT_ID', $catalog_connector_client_id );
}

// This challenge is intentionally public at a fixed well-known route. It
// proves the configured origin without exposing the connector credential.
$catalog_origin_challenge = trim( (string) getenv( 'KALAHAMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE' ) );
if ( '' !== $catalog_origin_challenge && ! defined( 'KALAHAMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE' ) ) {
	define( 'KALAHAMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE', $catalog_origin_challenge );
}

// The reusable plugin deliberately defaults this mode off. This deployment
// opts in here so an ordinary WordPress installation never becomes a catalog
// consumer merely because it has the plugin installed.
if ( ! defined( 'KALAHAMOON_CATALOG_CONSUMER_MODE' ) ) {

	define( 'KALAHAMOON_CATALOG_CONSUMER_MODE', true );
}

// The connector reports operational metadata but does not own scheduling.
// Derive that metadata from the same host interval used by the WP-CLI loop.
add_filter( 'kalahamoon_catalog_refresh_interval_minutes', static function ( $minutes ): int {

	$seconds = (int) getenv( 'CHIDEMOON_CATALOG_SYNC_INTERVAL_SECONDS' );
	if ( $seconds < 60 || $seconds > 3600 ) {

		return (int) $minutes;
	}

	return (int) ceil( $seconds / 60 );
} );

// Chidemoon is a presentation surface for the tenant-scoped panel catalog.
// Local authoring remains available in the generic plugin but is never public
// on this site, preventing WordPress records from shadowing remote authority.
add_filter( 'pre_option_kalahamoon_catalog_authority', static fn() => 'remote' );
