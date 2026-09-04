<?php
/**
 * Local request reservations and non-secret usage accounting.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_Usage {
	private const DEFAULT_DAILY_LIMIT       = 20;
	private const DEFAULT_MONTHLY_LIMIT     = 500;
	private const DEFAULT_MONTHLY_BUDGET    = 50.0;
	private const DEFAULT_TEXT_COST         = 0.02;
	private const DEFAULT_COMPARISON_COST   = 0.03;
	private const DEFAULT_IMAGE_COST        = 0.10;

	/**
	 * Reserves capacity before a background action is enqueued. A queued action
	 * is counted because a failed provider request can still incur provider cost.
	 *
	 * @return true|WP_Error
	 */
	public static function reserve( int $job_id, int $user_id, string $operation ): true|WP_Error {
		global $wpdb;

		$table       = self::table();
		$now         = current_time( 'mysql', true );
		$day_start   = gmdate( 'Y-m-d 00:00:00', current_time( 'timestamp', true ) );
		$month_start = gmdate( 'Y-m-01 00:00:00', current_time( 'timestamp', true ) );
		$daily_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND created_at >= %s",
				$user_id,
				$day_start
			)
		);

		if ( $daily_count >= self::daily_limit() ) {
			return new WP_Error( 'chidemoon_ai_daily_quota', __( 'The daily AI request limit has been reached.', 'chidemoon-ai' ), array( 'status' => 429 ) );
		}

		$monthly = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS requests, COALESCE(SUM(estimated_cost), 0) AS cost FROM {$table} WHERE created_at >= %s",
				$month_start
			),
			ARRAY_A
		);
		$monthly_requests = (int) ( $monthly['requests'] ?? 0 );
		$monthly_cost     = (float) ( $monthly['cost'] ?? 0 );
		$estimated_cost   = self::estimated_cost( $operation );

		if ( $monthly_requests >= self::monthly_limit() ) {
			return new WP_Error( 'chidemoon_ai_monthly_quota', __( 'The monthly AI request limit has been reached.', 'chidemoon-ai' ), array( 'status' => 429 ) );
		}

		if ( $monthly_cost + $estimated_cost > self::monthly_budget() ) {
			return new WP_Error( 'chidemoon_ai_monthly_budget', __( 'The monthly AI budget has been reserved.', 'chidemoon-ai' ), array( 'status' => 429 ) );
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'job_id'         => $job_id,
				'user_id'        => $user_id,
				'operation'      => $operation,
				'request_state'  => Chidemoon_AI_State_Machine::QUEUED,
				'estimated_cost' => $estimated_cost,
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%d', '%s', '%s', '%f', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'chidemoon_ai_usage_reservation_failed', __( 'The AI request could not reserve usage capacity.', 'chidemoon-ai' ), array( 'status' => 500 ) );
		}

		return true;
	}

	public static function complete( int $job_id, string $state, string $provider = '', string $model = '', ?int $input_units = null, ?int $output_units = null ): void {
		global $wpdb;

		$wpdb->update(
			self::table(),
			array_filter(
				array(
					'request_state' => $state,
					'provider'      => $provider ?: null,
					'model'         => $model ?: null,
					'input_units'   => $input_units,
					'output_units'  => $output_units,
					'updated_at'    => current_time( 'mysql', true ),
				),
				static fn( $value ): bool => null !== $value
			),
			array( 'job_id' => $job_id ),
			null,
			array( '%d' )
		);
	}

	/**
	 * @return array{today_requests: int, month_requests: int, month_cost: float, daily_limit: int, monthly_limit: int, monthly_budget: float}
	 */
	public static function summary(): array {
		global $wpdb;

		$table       = self::table();
		$day_start   = gmdate( 'Y-m-d 00:00:00', current_time( 'timestamp', true ) );
		$month_start = gmdate( 'Y-m-01 00:00:00', current_time( 'timestamp', true ) );
		$today       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $day_start ) );
		$month       = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS requests, COALESCE(SUM(estimated_cost), 0) AS cost FROM {$table} WHERE created_at >= %s", $month_start ), ARRAY_A );

		return array(
			'today_requests' => $today,
			'month_requests' => (int) ( $month['requests'] ?? 0 ),
			'month_cost'     => (float) ( $month['cost'] ?? 0 ),
			'daily_limit'    => self::daily_limit(),
			'monthly_limit'  => self::monthly_limit(),
			'monthly_budget' => self::monthly_budget(),
		);
	}

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'chidemoon_ai_usage';
	}

	private static function daily_limit(): int {
		if ( class_exists( 'Chidemoon_AI_Settings' ) ) {
			return Chidemoon_AI_Settings::get_int( 'daily_limit' );
		}

		return max( 1, min( 1000, self::environment_int( 'CHIDEMOON_AI_DAILY_REQUEST_LIMIT', self::DEFAULT_DAILY_LIMIT ) ) );
	}

	private static function monthly_limit(): int {
		if ( class_exists( 'Chidemoon_AI_Settings' ) ) {
			return Chidemoon_AI_Settings::get_int( 'monthly_limit' );
		}

		return max( 1, min( 100000, self::environment_int( 'CHIDEMOON_AI_MONTHLY_REQUEST_LIMIT', self::DEFAULT_MONTHLY_LIMIT ) ) );
	}

	private static function monthly_budget(): float {
		if ( class_exists( 'Chidemoon_AI_Settings' ) ) {
			return Chidemoon_AI_Settings::get_float( 'monthly_budget' );
		}

		return max( 0.01, min( 1000000, self::environment_float( 'CHIDEMOON_AI_MONTHLY_BUDGET', self::DEFAULT_MONTHLY_BUDGET ) ) );
	}

	private static function estimated_cost( string $operation ): float {
		$defaults = array(
			'text'       => self::DEFAULT_TEXT_COST,
			'comparison' => self::DEFAULT_COMPARISON_COST,
			'image'      => self::DEFAULT_IMAGE_COST,
			'look'       => 0.12,
			'enrich'     => 0.04,
		);
		$environment = array(
			'text'       => 'CHIDEMOON_AI_TEXT_ESTIMATED_COST',
			'comparison' => 'CHIDEMOON_AI_COMPARISON_ESTIMATED_COST',
			'image'      => 'CHIDEMOON_AI_IMAGE_ESTIMATED_COST',
			'look'       => 'CHIDEMOON_AI_LOOK_ESTIMATED_COST',
			'enrich'     => 'CHIDEMOON_AI_ENRICH_ESTIMATED_COST',
		);

		if ( class_exists( 'Chidemoon_AI_Settings' ) ) {
			$settings_key = array(
				'text'       => 'text_cost',
				'comparison' => 'comparison_cost',
				'image'      => 'image_cost',
				'look'       => 'look_cost',
				'enrich'     => 'enrich_cost',
			);
			if ( isset( $settings_key[ $operation ] ) ) {
				return max( 0, min( 10000, Chidemoon_AI_Settings::get_float( $settings_key[ $operation ] ) ) );
			}
		}

		return max( 0, min( 10000, self::environment_float( $environment[ $operation ] ?? '', $defaults[ $operation ] ?? self::DEFAULT_TEXT_COST ) ) );
	}

	private static function environment_int( string $name, int $default ): int {
		$value = '' !== $name ? getenv( $name ) : false;
		return false === $value || ! is_numeric( $value ) ? $default : (int) $value;
	}

	private static function environment_float( string $name, float $default ): float {
		$value = '' !== $name ? getenv( $name ) : false;
		return false === $value || ! is_numeric( $value ) ? $default : (float) $value;
	}
}
