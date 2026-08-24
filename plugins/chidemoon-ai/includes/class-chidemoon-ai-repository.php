<?php
/**
 * Durable job persistence with atomic state claims.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_Repository {
	/**
	 * @param array<string, mixed> $job
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create( array $job ): array|WP_Error {
		global $wpdb;

		$table            = self::table();
		$now              = current_time( 'mysql', true );
		$idempotency_key  = (string) ( $job['idempotency_key'] ?? '' );
		$existing         = self::find_by_idempotency_key( $idempotency_key );
		if ( $existing ) {
			return $existing;
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'job_key'         => wp_generate_uuid4(),
				'idempotency_key' => $idempotency_key,
				'job_type'        => (string) $job['job_type'],
				'state'           => Chidemoon_AI_State_Machine::QUEUED,
				'target_post_id'  => ! empty( $job['target_post_id'] ) ? (int) $job['target_post_id'] : null,
				'requested_by'    => (int) $job['requested_by'],
				'request_hash'    => (string) $job['request_hash'],
				'request_payload' => wp_json_encode( $job['request_payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				'provenance'      => wp_json_encode( $job['provenance'] ?? array(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			// A concurrent retry may have won the unique idempotency insert.
			$existing = self::find_by_idempotency_key( $idempotency_key );
			if ( $existing ) {
				return $existing;
			}

			return new WP_Error( 'chidemoon_ai_job_create_failed', __( 'The AI job could not be created.', 'chidemoon-ai' ), array( 'status' => 500 ) );
		}

		return self::find( (int) $wpdb->insert_id ) ?: new WP_Error( 'chidemoon_ai_job_missing', __( 'The AI job could not be loaded.', 'chidemoon-ai' ), array( 'status' => 500 ) );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A );
		return is_array( $row ) ? self::hydrate( $row ) : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function find_by_idempotency_key( string $key ): ?array {
		if ( '' === $key ) {
			return null;
		}

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE idempotency_key = %s', $key ), ARRAY_A );
		return is_array( $row ) ? self::hydrate( $row ) : null;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function list( string $state = '', int $limit = 50 ): array {
		global $wpdb;
		$limit = max( 1, min( 100, $limit ) );
		$table = self::table();
		$sql   = "SELECT * FROM {$table}";
		$args  = array();
		if ( '' !== $state ) {
			$sql   .= ' WHERE state = %s';
			$args[] = $state;
		}
		$sql .= ' ORDER BY created_at DESC LIMIT %d';
		$args[] = $limit;
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

		return array_map( array( __CLASS__, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Atomically claims only a queued job so duplicate scheduler deliveries do
	 * not make duplicate provider calls.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function claim( int $id ): ?array {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$sql = $wpdb->prepare(
			'UPDATE ' . self::table() . ' SET state = %s, started_at = %s, updated_at = %s, attempts = attempts + 1 WHERE id = %d AND state = %s',
			Chidemoon_AI_State_Machine::GENERATING,
			$now,
			$now,
			$id,
			Chidemoon_AI_State_Machine::QUEUED
		);
		$claimed = $wpdb->query( $sql );

		return 1 === $claimed ? self::find( $id ) : null;
	}

	/**
	 * @param array<string, mixed> $result
	 * @param array<string, mixed> $provenance
	 */
	public static function mark_review_required( int $id, array $result, array $provenance ): bool {
		return self::transition(
			$id,
			Chidemoon_AI_State_Machine::GENERATING,
			Chidemoon_AI_State_Machine::REVIEW_REQUIRED,
			array(
				'result_payload' => wp_json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				'provenance'     => wp_json_encode( $provenance, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				'completed_at'   => current_time( 'mysql', true ),
			)
		);
	}

	public static function mark_failed( int $id, string $from, string $code, string $message ): bool {
		return self::transition(
			$id,
			$from,
			Chidemoon_AI_State_Machine::FAILED,
			array(
				'error_code'    => sanitize_key( $code ),
				'error_message' => sanitize_text_field( $message ),
				'completed_at'  => current_time( 'mysql', true ),
			)
		);
	}

	public static function approve( int $id, int $reviewer_id ): bool {
		return self::transition(
			$id,
			Chidemoon_AI_State_Machine::REVIEW_REQUIRED,
			Chidemoon_AI_State_Machine::APPROVED,
			array(
				'reviewed_by' => $reviewer_id,
				'reviewed_at' => current_time( 'mysql', true ),
			)
		);
	}

	public static function reject( int $id, string $from, int $reviewer_id, string $reason = '' ): bool {
		return self::transition(
			$id,
			$from,
			Chidemoon_AI_State_Machine::REJECTED,
			array(
				'reviewed_by'    => $reviewer_id,
				'reviewed_at'    => current_time( 'mysql', true ),
				'error_code'     => 'rejected_by_reviewer',
				'error_message'  => sanitize_text_field( $reason ),
				'completed_at'   => current_time( 'mysql', true ),
			)
		);
	}

	public static function mark_applied( int $id, int $target_post_id ): bool {
		return self::transition(
			$id,
			Chidemoon_AI_State_Machine::APPROVED,
			Chidemoon_AI_State_Machine::APPLIED,
			array(
				'target_post_id' => $target_post_id,
				'completed_at'   => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Moderation outcomes are retained with job provenance even when the job is
	 * blocked or the moderation service is unavailable.
	 *
	 * @param array<string, mixed> $outcome
	 */
	public static function record_moderation( int $id, array $outcome ): void {
		$job = self::find( $id );
		if ( ! $job ) {
			return;
		}
		$provenance = is_array( $job['provenance'] ?? null ) ? $job['provenance'] : array();
		$history    = is_array( $provenance['moderation'] ?? null ) ? $provenance['moderation'] : array();
		$history[]  = $outcome;
		$provenance['moderation'] = $history;

		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'provenance' => wp_json_encode( $provenance, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @param array<string, mixed> $changes
	 */
	private static function transition( int $id, string $from, string $to, array $changes ): bool {
		if ( ! Chidemoon_AI_State_Machine::can_transition( $from, $to ) ) {
			return false;
		}

		global $wpdb;
		$allowed = array(
			'target_post_id' => '%d',
			'reviewed_by'    => '%d',
			'result_payload' => '%s',
			'provenance'     => '%s',
			'error_code'     => '%s',
			'error_message'  => '%s',
			'completed_at'   => '%s',
			'reviewed_at'    => '%s',
		);
		$assignments = array( 'state = %s', 'updated_at = %s' );
		$args        = array( $to, current_time( 'mysql', true ) );

		foreach ( $changes as $column => $value ) {
			if ( ! isset( $allowed[ $column ] ) ) {
				continue;
			}
			$assignments[] = $column . ' = ' . $allowed[ $column ];
			$args[]        = $value;
		}

		$args[] = $id;
		$args[] = $from;
		$sql    = 'UPDATE ' . self::table() . ' SET ' . implode( ', ', $assignments ) . ' WHERE id = %d AND state = %s';

		return 1 === $wpdb->query( $wpdb->prepare( $sql, $args ) );
	}

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'chidemoon_ai_jobs';
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private static function hydrate( array $row ): array {
		foreach ( array( 'request_payload', 'result_payload', 'provenance' ) as $field ) {
			if ( isset( $row[ $field ] ) && is_string( $row[ $field ] ) && '' !== $row[ $field ] ) {
				$decoded = json_decode( $row[ $field ], true );
				$row[ $field ] = is_array( $decoded ) ? $decoded : array();
			}
		}

		return $row;
	}
}
