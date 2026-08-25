<?php
/**
 * Kalahamoon plugin uninstall — clean up all data.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom tables
$tables = array(
	$wpdb->prefix . 'kalahamoon_clicks',
	$wpdb->prefix . 'kalahamoon_affiliate_links',
	$wpdb->prefix . 'kalahamoon_price_history',
	$wpdb->prefix . 'kalahamoon_price_alerts',
	$wpdb->prefix . 'kalahamoon_auto_links',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

// Delete all kalahamoon_product posts
$posts = get_posts( array(
	'post_type'   => 'kalahamoon_product',
	'numberposts' => -1,
	'post_status' => 'any',
	'fields'      => 'ids',
) );

foreach ( $posts as $post_id ) {
	wp_delete_post( $post_id, true );
}

// Delete options
$options = array(
	'kalahamoon_api_key',
	'kalahamoon_api_url',
	'kalahamoon_sync_interval',
	'kalahamoon_catalog_authority',
	'kalahamoon_display_currency',
	'kalahamoon_display_unit',
	'kalahamoon_persian_numerals',
	'kalahamoon_direction',
	'kalahamoon_disclosure_text',
	'kalahamoon_auto_disclosure',
	'kalahamoon_affiliate_provision_page',
	'kalahamoon_redirect_type',
	'kalahamoon_clicks_retention',
	'kalahamoon_connected',
	'kalahamoon_organization_slug',
	'kalahamoon_webhook_secret',
	'kalahamoon_legacy_dark_mode',
	'kalahamoon_changelog_notice_version',
	'kalahamoon_db_version',
	'kalahamoon_last_sync',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Delete changelog user meta
$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'kalahamoon_changelog_seen_version' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'kalahamoon_changelog_dismissed_version' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Delete transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_kalahamoon_api_%' OR option_name LIKE '_transient_timeout_kalahamoon_api_%'" ); // phpcs:ignore

// Clear scheduled events
wp_clear_scheduled_hook( 'kalahamoon_sync_products' );
wp_clear_scheduled_hook( 'kalahamoon_purge_clicks' );
wp_clear_scheduled_hook( 'kalahamoon_check_price_alerts' );

// Flush rewrite rules
flush_rewrite_rules();
