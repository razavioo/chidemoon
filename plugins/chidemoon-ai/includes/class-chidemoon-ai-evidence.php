<?php
/**
 * Captures the exact local WordPress facts supplied to an AI job.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_Evidence {
	private const DEFAULT_MAX_AGE_DAYS = 90;

	/**
	 * @param array<int, int> $post_ids
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function capture_posts( int $job_id, array $post_ids ): array|WP_Error {
		$post_ids = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );
		if ( empty( $post_ids ) ) {
			return new WP_Error( 'chidemoon_ai_evidence_empty', __( 'Select at least one current WordPress source before requesting AI output.', 'chidemoon-ai' ), array( 'status' => 400 ) );
		}

		$evidence = array();
		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page', 'product' ), true ) ) {
				return new WP_Error( 'chidemoon_ai_evidence_invalid', __( 'One of the selected AI sources is unavailable.', 'chidemoon-ai' ), array( 'status' => 400 ) );
			}

			if ( ! self::is_fresh( $post ) ) {
				return new WP_Error( 'chidemoon_ai_evidence_stale', __( 'One of the selected AI sources is stale and must be reviewed first.', 'chidemoon-ai' ), array( 'status' => 409 ) );
			}

			$item = self::post_evidence( $post );
			self::persist( $job_id, $item );
			$evidence[] = $item;
		}

		return $evidence;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_job( int $job_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'chidemoon_ai_evidence';
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE job_id = %d ORDER BY id ASC", $job_id ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<int, array<string, mixed>> $evidence
	 * @return array<int, string>
	 */
	public static function source_ids( array $evidence ): array {
		return array_values( array_filter( array_map( static fn( array $item ): string => (string) ( $item['source_id'] ?? '' ), $evidence ) ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $evidence
	 */
	public static function prompt_context( array $evidence ): string {
		$context = array();
		foreach ( $evidence as $item ) {
			$context[] = sprintf(
				"[SOURCE id=%s type=%s freshness=%s]\n%s\n[/SOURCE]",
				(string) ( $item['source_id'] ?? '' ),
				(string) ( $item['source_type'] ?? '' ),
				(string) ( $item['freshness_at'] ?? '' ),
				self::truncate( (string) ( $item['source_excerpt'] ?? '' ), 6000 )
			);
		}

		return implode( "\n\n", $context );
	}

	private static function is_fresh( WP_Post $post ): bool {
		$modified = get_post_modified_time( 'U', true, $post );
		if ( ! $modified ) {
			return false;
		}

		$max_age = max( 1, min( 3650, self::environment_int( 'CHIDEMOON_AI_EVIDENCE_MAX_AGE_DAYS', self::DEFAULT_MAX_AGE_DAYS ) ) );
		return $modified >= current_time( 'timestamp', true ) - ( $max_age * DAY_IN_SECONDS );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function post_evidence( WP_Post $post ): array {
		$parts = array(
			'Title: ' . wp_strip_all_tags( get_the_title( $post ) ),
			'Excerpt: ' . wp_strip_all_tags( $post->post_excerpt ),
			'Content: ' . self::truncate( wp_strip_all_tags( $post->post_content ), 10000 ),
		);

		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$parts[] = 'Product type: ' . $product->get_type();
				$parts[] = 'Price: ' . (string) $product->get_price();
				$parts[] = 'SKU: ' . (string) $product->get_sku();
				foreach ( $product->get_attributes() as $attribute ) {
					if ( ! is_a( $attribute, 'WC_Product_Attribute' ) ) {
						continue;
					}
					$parts[] = sprintf( 'Attribute %s: %s', $attribute->get_name(), implode( ', ', $attribute->get_options() ) );
				}
			}
		}

		$excerpt = implode( "\n", array_filter( $parts ) );
		return array(
			'source_type'    => $post->post_type,
			'source_id'      => (string) $post->ID,
			'source_url'     => get_permalink( $post ),
			'source_excerpt' => self::truncate( $excerpt, 12000 ),
			'content_hash'   => hash( 'sha256', $excerpt ),
			'freshness_at'   => get_post_modified_time( 'Y-m-d H:i:s', true, $post ),
			'created_at'     => current_time( 'mysql', true ),
		);
	}

	/**
	 * @param array<string, mixed> $evidence
	 */
	private static function persist( int $job_id, array $evidence ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'chidemoon_ai_evidence',
			array(
				'job_id'         => $job_id,
				'source_type'    => (string) $evidence['source_type'],
				'source_id'      => (string) $evidence['source_id'],
				'source_url'     => (string) $evidence['source_url'],
				'source_excerpt' => (string) $evidence['source_excerpt'],
				'content_hash'   => (string) $evidence['content_hash'],
				'freshness_at'   => (string) $evidence['freshness_at'],
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	private static function truncate( string $value, int $limit ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $limit );
		}

		return substr( $value, 0, $limit );
	}

	private static function environment_int( string $name, int $default ): int {
		$value = getenv( $name );
		return false === $value || ! is_numeric( $value ) ? $default : (int) $value;
	}
}
