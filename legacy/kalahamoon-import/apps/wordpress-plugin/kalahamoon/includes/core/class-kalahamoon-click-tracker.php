<?php
/**
 * Click tracking — logs affiliate link clicks for analytics.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Click_Tracker {

	public static function init(): void {
		// Purge old clicks on scheduled event
		add_action( 'kalahamoon_purge_clicks', array( __CLASS__, 'purge_old_clicks' ) );
	}

	/**
	 * Log a click event.
	 */
	public static function log_click( array $data ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'kalahamoon_clicks';

		$ip_hash = hash( 'sha256', self::get_client_ip() . wp_salt() );

		$wpdb->insert( $table, array(
			'link_id'    => $data['link_id'] ?? null,
			'product_id' => $data['product_id'] ?? null,
			'post_id'    => $data['post_id'] ?? null,
			'block_type' => $data['block_type'] ?? null,
			'ip_hash'    => $ip_hash,
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 512 ) : null,
			'referrer'   => wp_get_referer() ?: null,
			'country'    => self::detect_country(),
			'clicked_at' => current_time( 'mysql' ),
		), array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ) );
	}

	/**
	 * Purge clicks older than retention period.
	 */
	public static function purge_old_clicks(): void {
		global $wpdb;
		$table     = $wpdb->prefix . 'kalahamoon_clicks';
		$retention = (int) get_option( 'kalahamoon_clicks_retention', 90 );

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE clicked_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$retention
		) );
	}

	/**
	 * Get click stats for a given period.
	 */
	public static function get_stats( int $days = 30 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'kalahamoon_clicks';

		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE clicked_at > DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days
		) );

		$by_product = $wpdb->get_results( $wpdb->prepare(
			"SELECT product_id, COUNT(*) as clicks
			 FROM {$table}
			 WHERE clicked_at > DATE_SUB(NOW(), INTERVAL %d DAY) AND product_id IS NOT NULL
			 GROUP BY product_id ORDER BY clicks DESC LIMIT 20",
			$days
		), ARRAY_A );

		$by_day = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(clicked_at) as date, COUNT(*) as clicks
			 FROM {$table}
			 WHERE clicked_at > DATE_SUB(NOW(), INTERVAL %d DAY)
			 GROUP BY DATE(clicked_at) ORDER BY date ASC",
			$days
		), ARRAY_A );

		return array(
			'total'      => $total,
			'byProduct'  => $by_product,
			'byDay'      => $by_day,
		);
	}

	private static function get_client_ip(): string {
		// Check for Cloudflare
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
	}

	private static function detect_country(): ?string {
		// Cloudflare country header
		if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			return strtoupper( substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ), 0, 2 ) );
		}
		return null;
	}
}
