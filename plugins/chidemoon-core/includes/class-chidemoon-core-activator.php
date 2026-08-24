<?php
/**
 * Schema and lifecycle management for Chidemoon Core.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Activator {
	public const DB_VERSION = '1';

	public static function activate(): void {
		if ( ! self::woocommerce_is_active() ) {
			self::deactivate_current_plugin();
			wp_die(
				esc_html__( 'Chidemoon Core requires WooCommerce. Activate WooCommerce before activating Chidemoon Core.', 'chidemoon-core' ),
				esc_html__( 'WooCommerce required', 'chidemoon-core' ),
				array( 'back_link' => true )
			);
		}

		self::create_tables();
		self::set_defaults();
		self::grant_capabilities();
		self::schedule_events();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'chidemoon_core_daily_housekeeping' );
		flush_rewrite_rules();
	}

	public static function maybe_upgrade(): void {
		if ( self::DB_VERSION !== (string) get_option( 'chidemoon_core_db_version', '' ) ) {
			self::create_tables();
		}

		self::set_defaults();
		self::grant_capabilities();
		self::schedule_events();
	}

	public static function woocommerce_is_active(): bool {
		if ( defined( 'WC_VERSION' ) || class_exists( 'WooCommerce' ) ) {
			return true;
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) ) {
			return true;
		}

		if ( ! is_multisite() ) {
			return false;
		}

		$network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
		return array_key_exists( 'woocommerce/woocommerce.php', $network_plugins );
	}

	private static function deactivate_current_plugin(): void {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( CHIDEMOON_CORE_BASENAME );
	}

	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$wpdb->prefix}chidemoon_clicks (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			merchant_host varchar(190) NOT NULL,
			visitor_hash char(64) DEFAULT NULL,
			referrer_host varchar(190) DEFAULT NULL,
			clicked_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY product_date (product_id, clicked_at),
			KEY clicked_at (clicked_at)
		) {$charset_collate};";
		dbDelta( $sql );

		$sql = "CREATE TABLE {$wpdb->prefix}chidemoon_leads (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(320) NOT NULL,
			name varchar(160) DEFAULT NULL,
			message longtext DEFAULT NULL,
			intent varchar(40) NOT NULL DEFAULT 'contact',
			consent_version varchar(40) NOT NULL,
			request_hash char(64) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'new',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status_created (status, created_at),
			KEY email_created (email, created_at)
		) {$charset_collate};";
		dbDelta( $sql );

		$sql = "CREATE TABLE {$wpdb->prefix}chidemoon_price_alerts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(320) NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			target_price decimal(18,2) DEFAULT NULL,
			subscription_key char(64) NOT NULL,
			consent_version varchar(40) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY subscription_key (subscription_key),
			KEY product_status (product_id, status),
			KEY email_status (email, status)
		) {$charset_collate};";
		dbDelta( $sql );

		update_option( 'chidemoon_core_db_version', self::DB_VERSION, false );
	}

	private static function set_defaults(): void {
		$defaults = array(
			'chidemoon_core_disclosure_text'       => __( 'This page may contain affiliate links. Chidemoon may earn a commission at no extra cost to you.', 'chidemoon-core' ),
			'chidemoon_core_freshness_days'        => 30,
			'chidemoon_core_click_retention_days'  => 90,
			'chidemoon_core_form_rate_limit'       => 5,
			'chidemoon_core_form_consent_version'  => '1',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key, false ) ) {
				add_option( $key, $value, '', false );
			}
		}
	}

	private static function grant_capabilities(): void {
		$administrator = get_role( 'administrator' );
		if ( $administrator instanceof WP_Role ) {
			$administrator->add_cap( 'chidemoon_manage_affiliate' );
			$administrator->add_cap( 'chidemoon_view_readiness' );
			$administrator->add_cap( 'chidemoon_manage_forms' );
		}

		$shop_manager = get_role( 'shop_manager' );
		if ( $shop_manager instanceof WP_Role ) {
			$shop_manager->add_cap( 'chidemoon_manage_affiliate' );
			$shop_manager->add_cap( 'chidemoon_view_readiness' );
		}
	}

	private static function schedule_events(): void {
		if ( ! wp_next_scheduled( 'chidemoon_core_daily_housekeeping' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'chidemoon_core_daily_housekeeping' );
		}
	}
}
