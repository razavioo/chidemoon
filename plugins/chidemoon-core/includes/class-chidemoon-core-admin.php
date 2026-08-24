<?php
/**
 * Chidemoon administrator readiness and local operational health.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Admin {
	private const READINESS_ROUTE = 'chidemoon-core/v1/readiness';

	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_route' ) );
		add_action( 'chidemoon_core_daily_housekeeping', array( __CLASS__, 'run_housekeeping' ) );
		add_action( 'chidemoon_core_scheduler_heartbeat', array( __CLASS__, 'record_scheduler_heartbeat' ) );
	}

	public static function register_settings(): void {
		register_setting(
			'chidemoon_core_settings',
			'chidemoon_core_disclosure_text',
			array(
				'type'              => 'string',
				'sanitize_callback' => static function ( $value ): string {
					return substr( sanitize_text_field( (string) $value ), 0, 1000 );
				},
			)
		);
		foreach ( array( 'chidemoon_core_freshness_days', 'chidemoon_core_click_retention_days', 'chidemoon_core_form_rate_limit' ) as $option_name ) {
			register_setting(
				'chidemoon_core_settings',
				$option_name,
				array(
					'type'              => 'integer',
					'sanitize_callback' => static function ( $value ): int {
						return min( 730, max( 1, absint( $value ) ) );
					},
				)
			);
		}

		add_settings_section(
			'chidemoon_core_policy',
			__( 'Local affiliate policy', 'chidemoon-core' ),
			static function (): void {
				echo '<p>' . esc_html__( 'These values are stored only in this WordPress installation.', 'chidemoon-core' ) . '</p>';
			},
			'chidemoon-readiness'
		);
		add_settings_field(
			'chidemoon_core_disclosure_text',
			__( 'Default affiliate disclosure', 'chidemoon-core' ),
			array( __CLASS__, 'render_disclosure_field' ),
			'chidemoon-readiness',
			'chidemoon_core_policy'
		);
		add_settings_field(
			'chidemoon_core_freshness_days',
			__( 'Source freshness days', 'chidemoon-core' ),
			array( __CLASS__, 'render_number_field' ),
			'chidemoon-readiness',
			'chidemoon_core_policy',
			array( 'option' => 'chidemoon_core_freshness_days', 'maximum' => 365 )
		);
		add_settings_field(
			'chidemoon_core_click_retention_days',
			__( 'Click retention days', 'chidemoon-core' ),
			array( __CLASS__, 'render_number_field' ),
			'chidemoon-readiness',
			'chidemoon_core_policy',
			array( 'option' => 'chidemoon_core_click_retention_days', 'maximum' => 730 )
		);
		add_settings_field(
			'chidemoon_core_form_rate_limit',
			__( 'Public form limit per 15 minutes', 'chidemoon-core' ),
			array( __CLASS__, 'render_number_field' ),
			'chidemoon-readiness',
			'chidemoon_core_policy',
			array( 'option' => 'chidemoon_core_form_rate_limit', 'maximum' => 20 )
		);
	}

	public static function render_disclosure_field(): void {
		echo '<textarea class="large-text" rows="3" name="chidemoon_core_disclosure_text" maxlength="1000">' . esc_textarea( (string) get_option( 'chidemoon_core_disclosure_text', '' ) ) . '</textarea>';
	}

	/**
	 * @param array<string, mixed> $args Field configuration.
	 */
	public static function render_number_field( array $args ): void {
		$option  = isset( $args['option'] ) ? (string) $args['option'] : '';
		$maximum = isset( $args['maximum'] ) ? absint( $args['maximum'] ) : 730;
		echo '<input type="number" min="1" max="' . esc_attr( (string) $maximum ) . '" name="' . esc_attr( $option ) . '" value="' . esc_attr( (string) get_option( $option, 1 ) ) . '">';
	}

	public static function register_menu(): void {
		add_menu_page(
			__( 'Chidemoon readiness', 'chidemoon-core' ),
			__( 'Chidemoon', 'chidemoon-core' ),
			'chidemoon_view_readiness',
			'chidemoon-readiness',
			array( __CLASS__, 'render_readiness_page' ),
			'dashicons-admin-home',
			56
		);
	}

	public static function register_rest_route(): void {
		register_rest_route(
			'chidemoon-core/v1',
			'/readiness',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_readiness_response' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'chidemoon_view_readiness' );
				},
			)
		);
	}

	public static function render_readiness_page(): void {
		if ( ! current_user_can( 'chidemoon_view_readiness' ) ) {
			wp_die( esc_html__( 'You are not allowed to view Chidemoon readiness.', 'chidemoon-core' ) );
		}

		$report = self::readiness_report();
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Chidemoon readiness', 'chidemoon-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'This report checks local WooCommerce data and scheduled work. It never queries an external catalog or CRM.', 'chidemoon-core' ) . '</p>';
		echo '<p><strong>' . esc_html( $report['ready'] ? __( 'Ready for editorial review', 'chidemoon-core' ) : __( 'Launch blockers found', 'chidemoon-core' ) ) . '</strong></p>';

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Check', 'chidemoon-core' ) . '</th><th>' . esc_html__( 'Count', 'chidemoon-core' ) . '</th><th>' . esc_html__( 'Status', 'chidemoon-core' ) . '</th></tr></thead><tbody>';
		foreach ( $report['checks'] as $check ) {
			echo '<tr><td>' . esc_html( $check['label'] ) . '</td><td>' . esc_html( (string) $check['count'] ) . '</td><td>' . esc_html( $check['status'] ) . '</td></tr>';
		}
		echo '</tbody></table>';

		if ( ! empty( $report['blockers'] ) ) {
			echo '<h2>' . esc_html__( 'Launch blockers', 'chidemoon-core' ) . '</h2><ul>';
			foreach ( $report['blockers'] as $blocker ) {
				echo '<li>' . esc_html( $blocker ) . '</li>';
			}
			echo '</ul>';
		}

		echo '<h2>' . esc_html__( 'Local audience data', 'chidemoon-core' ) . '</h2>';
		echo '<p>' . esc_html(
			sprintf(
				/* translators: 1: pending alert count, 2: lead count. */
				__( '%1$d pending price alerts and %2$d captured leads are stored locally.', 'chidemoon-core' ),
				$report['localData']['pendingAlerts'],
				$report['localData']['leads']
			)
		) . '</p>';

		if ( current_user_can( 'manage_options' ) ) {
			echo '<form action="options.php" method="post">';
			settings_fields( 'chidemoon_core_settings' );
			do_settings_sections( 'chidemoon-readiness' );
			submit_button( __( 'Save local policy', 'chidemoon-core' ) );
			echo '</form>';
		}
		echo '</div>';
	}

	public static function get_readiness_response(): WP_REST_Response {
		return new WP_REST_Response( self::readiness_report(), 200 );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function readiness_report(): array {
		$product_ids   = get_posts(
			array(
				'post_type'           => 'product',
				'post_status'         => 'publish',
				'fields'              => 'ids',
				'posts_per_page'      => -1,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'suppress_filters'    => true,
			)
		);
		$counts        = array(
			'publishedProducts'   => count( $product_ids ),
			'missingAffiliateUrl' => 0,
			'missingImage'        => 0,
			'missingCategory'     => 0,
			'staleSource'         => 0,
			'unreviewed'          => 0,
			'internalProduct'     => 0,
		);
		$freshness_days = min( 365, max( 1, absint( get_option( 'chidemoon_core_freshness_days', 30 ) ) ) );
		$stale_before   = time() - ( $freshness_days * DAY_IN_SECONDS );

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( (int) $product_id );
			if ( ! $product instanceof WC_Product || ! $product->is_type( 'external' ) ) {
				$counts['internalProduct']++;
			}
			if ( ! $product instanceof WC_Product || '' === Chidemoon_Core_Affiliate::get_affiliate_url( $product ) ) {
				$counts['missingAffiliateUrl']++;
			}
			if ( ! has_post_thumbnail( (int) $product_id ) ) {
				$counts['missingImage']++;
			}
			if ( empty( wp_get_object_terms( (int) $product_id, 'product_cat', array( 'fields' => 'ids' ) ) ) ) {
				$counts['missingCategory']++;
			}
			if ( 'reviewed' !== (string) get_post_meta( (int) $product_id, Chidemoon_Core_Affiliate::META_REVIEW_STATE, true ) ) {
				$counts['unreviewed']++;
			}

			$source_checked_at = (string) get_post_meta( (int) $product_id, Chidemoon_Core_Affiliate::META_SOURCE_CHECKED, true );
			if ( '' === $source_checked_at || false === strtotime( $source_checked_at ) || strtotime( $source_checked_at ) < $stale_before ) {
				$counts['staleSource']++;
			}
		}

		$scheduler          = get_option( 'chidemoon_core_scheduler_last_run', array() );
		$last_scheduler_run = is_array( $scheduler ) ? (string) ( $scheduler['ranAt'] ?? '' ) : '';
		$next_event         = wp_next_scheduled( 'chidemoon_core_daily_housekeeping' );
		$scheduler_is_stale = '' === $last_scheduler_run || false === strtotime( $last_scheduler_run ) || strtotime( $last_scheduler_run ) < time() - ( 30 * MINUTE_IN_SECONDS );

		$checks = array(
			self::check( __( 'Published WooCommerce products', 'chidemoon-core' ), $counts['publishedProducts'], 0 === $counts['publishedProducts'] ? 'blocking' : 'ok' ),
			self::check( __( 'Products without affiliate destination', 'chidemoon-core' ), $counts['missingAffiliateUrl'], 0 === $counts['missingAffiliateUrl'] ? 'ok' : 'blocking' ),
			self::check( __( 'Products without featured image', 'chidemoon-core' ), $counts['missingImage'], 0 === $counts['missingImage'] ? 'ok' : 'blocking' ),
			self::check( __( 'Products without category', 'chidemoon-core' ), $counts['missingCategory'], 0 === $counts['missingCategory'] ? 'ok' : 'blocking' ),
			self::check( __( 'Products with stale source evidence', 'chidemoon-core' ), $counts['staleSource'], 0 === $counts['staleSource'] ? 'ok' : 'blocking' ),
			self::check( __( 'Published products not editorially reviewed', 'chidemoon-core' ), $counts['unreviewed'], 0 === $counts['unreviewed'] ? 'ok' : 'blocking' ),
			self::check( __( 'Published non-external products', 'chidemoon-core' ), $counts['internalProduct'], 0 === $counts['internalProduct'] ? 'ok' : 'blocking' ),
			self::check( __( 'Host scheduler heartbeat', 'chidemoon-core' ), $scheduler_is_stale ? 0 : 1, $scheduler_is_stale || false === $next_event ? 'blocking' : 'ok' ),
		);

		$blockers = array();
		foreach ( $checks as $check ) {
			if ( 'blocking' === $check['status'] ) {
				$blockers[] = $check['label'];
			}
		}

		return array(
			'ready'      => empty( $blockers ),
			'checks'     => $checks,
			'blockers'   => $blockers,
			'scheduler'  => array(
				'lastRun'   => $last_scheduler_run,
				'nextEvent' => false === $next_event ? null : gmdate( DATE_ATOM, (int) $next_event ),
			),
			'localData'  => self::local_data_counts(),
		);
	}

	public static function run_housekeeping(): void {
		global $wpdb;
		$retention_days = min( 730, max( 1, absint( get_option( 'chidemoon_core_click_retention_days', 90 ) ) ) );
		$before         = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}chidemoon_clicks WHERE clicked_at < %s",
				$before
			)
		);
		self::record_scheduler_heartbeat();
	}

	public static function record_scheduler_heartbeat(): void {
		update_option(
			'chidemoon_core_scheduler_last_run',
			array(
				'ranAt' => current_time( 'mysql', true ),
			),
			false
		);
	}

	/**
	 * @return array<string, int>
	 */
	private static function local_data_counts(): array {
		global $wpdb;

		return array(
			'pendingAlerts' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}chidemoon_price_alerts WHERE status = 'pending'" ),
			'leads'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}chidemoon_leads" ),
		);
	}

	/**
	 * @return array<string, int|string>
	 */
	private static function check( string $label, int $count, string $status ): array {
		return array(
			'label'  => $label,
			'count'  => $count,
			'status' => $status,
		);
	}
}
