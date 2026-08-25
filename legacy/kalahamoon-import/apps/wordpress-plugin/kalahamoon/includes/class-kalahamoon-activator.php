<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Activator {

	public static function activate(): void {
		self::create_tables();
		self::set_defaults();
		self::schedule_events();
		flush_rewrite_rules();
	}

	/**
	 * Run lightweight schema upgrades on version change. dbDelta is idempotent
	 * and adds any new columns (e.g. base_tracking_url) without data loss.
	 * Hooked from the plugin bootstrap so existing installs pick up new columns
	 * without a manual deactivate/reactivate cycle.
	 */
	public static function maybe_upgrade(): void {
		$installed = get_option( 'kalahamoon_db_version', '' );
		if ( KALAHAMOON_VERSION === $installed ) {
			// Connector mode can be switched independently of a plugin version.
			// Reconcile scheduled hooks so an old visitor-driven job cannot survive
			// that configuration change.
			self::schedule_events();
			return;
		}
		self::create_tables();
		self::schedule_events();
		if ( '' !== (string) $installed ) {
			update_option( 'kalahamoon_changelog_notice_version', KALAHAMOON_VERSION );
		}
	}

	private static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Click tracking log
		$sql = "CREATE TABLE {$wpdb->prefix}kalahamoon_clicks (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			link_id bigint(20) DEFAULT NULL,
			product_id varchar(64) DEFAULT NULL,
			post_id bigint(20) DEFAULT NULL,
			block_type varchar(64) DEFAULT NULL,
			ip_hash varchar(64) DEFAULT NULL,
			user_agent varchar(512) DEFAULT NULL,
			referrer text DEFAULT NULL,
			country varchar(2) DEFAULT NULL,
			clicked_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_link (link_id),
			KEY idx_product (product_id),
			KEY idx_date (clicked_at),
			KEY idx_post (post_id)
		) $charset;";
		dbDelta( $sql );

		// Affiliate links (local mirror)
		$sql = "CREATE TABLE {$wpdb->prefix}kalahamoon_affiliate_links (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			kalahamoon_link_id varchar(64) DEFAULT NULL,
			kalahamoon_short_url text DEFAULT NULL,
			product_id varchar(64) DEFAULT NULL,
			provider varchar(32) NOT NULL DEFAULT 'bakalahamoon',
			destination_url text DEFAULT NULL,
			base_tracking_url text DEFAULT NULL,
			slug varchar(255) DEFAULT NULL,
			campaign_title varchar(255) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			clicks int(11) NOT NULL DEFAULT 0,
			conversions int(11) NOT NULL DEFAULT 0,
			revenue decimal(12,2) NOT NULL DEFAULT 0,
			synced_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_slug (slug),
			KEY idx_provider (provider),
			KEY idx_product (product_id),
			KEY idx_kalahamoon_id (kalahamoon_link_id)
		) $charset;";
		dbDelta( $sql );

		// Price history
		$sql = "CREATE TABLE {$wpdb->prefix}kalahamoon_price_history (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			product_id varchar(64) NOT NULL,
			price decimal(12,2) NOT NULL,
			currency varchar(10) NOT NULL DEFAULT 'IRR',
			captured_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_product_date (product_id, captured_at)
		) $charset;";
		dbDelta( $sql );

		// Price drop alert subscriptions
		$sql = "CREATE TABLE {$wpdb->prefix}kalahamoon_price_alerts (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			email varchar(255) NOT NULL,
			product_id varchar(64) NOT NULL,
			subscription_key char(64) DEFAULT NULL,
			target_price decimal(12,2) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			confirm_token_hash varchar(64) DEFAULT NULL,
			confirmation_expires_at datetime DEFAULT NULL,
			confirmed_at datetime DEFAULT NULL,
			processing_at datetime DEFAULT NULL,
			consent_version varchar(40) DEFAULT NULL,
			consented_at datetime DEFAULT NULL,
			last_notified_price decimal(12,2) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			notified_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY idx_product (product_id),
			KEY idx_email (email),
			KEY idx_status (status),
			KEY idx_confirm_token (confirm_token_hash),
			UNIQUE KEY uniq_subscription (subscription_key)
		) $charset;";
		dbDelta( $sql );

		// Auto-link keyword mappings
		$sql = "CREATE TABLE {$wpdb->prefix}kalahamoon_auto_links (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			keyword varchar(255) NOT NULL,
			product_id varchar(64) DEFAULT NULL,
			link_id bigint(20) DEFAULT NULL,
			max_per_post int(11) NOT NULL DEFAULT 1,
			excluded_posts text DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			priority int(11) NOT NULL DEFAULT 10,
			PRIMARY KEY (id),
			KEY idx_keyword (keyword),
			KEY idx_active (is_active)
		) $charset;";
		dbDelta( $sql );

		update_option( 'kalahamoon_db_version', KALAHAMOON_VERSION );
	}

	private static function set_defaults(): void {
		$default_api_url = function_exists( 'kalahamoon_default_api_url' ) ? kalahamoon_default_api_url() : 'https://app.kalahamoon.com';

		$defaults = array(
			'kalahamoon_api_key'            => '',
			'kalahamoon_api_url'            => $default_api_url,
			'kalahamoon_sync_interval'      => 6, // hours
			'kalahamoon_catalog_authority'   => 'hybrid',
			'kalahamoon_display_currency'   => 'IRR',
			'kalahamoon_display_unit'       => 'TOMAN',
			'kalahamoon_persian_numerals'   => true,
			'kalahamoon_direction'          => 'auto',
			'kalahamoon_disclosure_text'    => '',
			'kalahamoon_auto_disclosure'    => false,
			'kalahamoon_affiliate_provision_page' => 1,
			'kalahamoon_redirect_type'      => '301',
			'kalahamoon_clicks_retention'   => 90, // days
			'kalahamoon_connected'          => false,
			'kalahamoon_organization_slug'  => '',
			'kalahamoon_webhook_secret'     => '',
			'kalahamoon_legacy_dark_mode'   => false,
			'kalahamoon_changelog_notice_version' => KALAHAMOON_VERSION,
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	private static function schedule_events(): void {
		$consumer = class_exists( 'Kalahamoon_Catalog_Consumer' )
			&& Kalahamoon_Catalog_Consumer::is_enabled();
		if ( $consumer ) {
			// Catalog delivery must not depend on a visitor loading WordPress. The
			// host scheduler invokes the existing hook through WP-CLI instead.
			wp_clear_scheduled_hook( 'kalahamoon_sync_products' );
			// These historic jobs support local tracking and price-alert workflows,
			// neither of which exists in a read-only projection installation.
			wp_clear_scheduled_hook( 'kalahamoon_purge_clicks' );
			wp_clear_scheduled_hook( 'kalahamoon_check_price_alerts' );
		} elseif ( ! wp_next_scheduled( 'kalahamoon_sync_products' ) ) {
			wp_schedule_event( time(), 'kalahamoon_sync_interval', 'kalahamoon_sync_products' );
		}
		if ( ! $consumer ) {
			if ( ! wp_next_scheduled( 'kalahamoon_purge_clicks' ) ) {
				wp_schedule_event( time(), 'daily', 'kalahamoon_purge_clicks' );
			}
			if ( ! wp_next_scheduled( 'kalahamoon_check_price_alerts' ) ) {
				wp_schedule_event( time(), 'daily', 'kalahamoon_check_price_alerts' );
			}
		}
	}
}
