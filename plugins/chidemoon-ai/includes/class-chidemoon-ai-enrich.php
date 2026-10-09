<?php
/**
 * Product enrichment: local facts + free web evidence -> structured proposal.
 *
	 * Proposals only target editorial classification and verified measurements.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_Enrich {
	public const KIND = 'enrich_product';

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function local_context( int $product_id ): array|WP_Error {
		$post = get_post( $product_id );
		if ( ! $post || 'product' !== $post->post_type ) {
			return new WP_Error( 'chidemoon_ai_enrich_invalid', __( 'The selected product is unavailable.', 'chidemoon-ai' ), array( 'status' => 400 ) );
		}
		$context = array(
			'title'   => wp_strip_all_tags( get_the_title( $post ) ),
			'excerpt' => wp_strip_all_tags( $post->post_excerpt ),
			'content' => wp_strip_all_tags( $post->post_content ),
		);
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$context['price'] = (string) $product->get_price();
				$context['sku']   = (string) $product->get_sku();
				$context['url']   = (string) $product->get_meta( '_chidemoon_affiliate_url', true );
				if ( class_exists( 'Chidemoon_Core_Affiliate' ) ) {
					$context['merchant']   = (string) $product->get_meta( Chidemoon_Core_Affiliate::META_MERCHANT_NAME, true );
					$context['source_url'] = (string) $product->get_meta( Chidemoon_Core_Affiliate::META_SOURCE_URL, true );
					$context['facts']      = (string) $product->get_meta( Chidemoon_Core_Affiliate::META_FACTS, true );
					$context['source_description'] = wp_strip_all_tags( (string) $product->get_meta( '_chidemoon_source_description', true ) );
					$context['source_specifications'] = (string) $product->get_meta( '_chidemoon_source_specifications', true );
				}
			}
		}

		if ( class_exists( 'Chidemoon_Core_Enrichment' ) ) {
			$context['approved_fields'] = Chidemoon_Core_Enrichment::read( $product_id );
			foreach ( Chidemoon_Core_Enrichment::TAXONOMIES as $field => $taxonomy ) {
				$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 300 ) );
				$context['vocabulary'][ $field ] = is_wp_error( $terms ) ? array() : array_map( static fn( $term ) => array( 'id' => $term->term_id, 'label' => $term->name ), $terms );
			}
		}
		return $context;
	}

	/**
	 * JSON schema for the enrichment proposal. Strict: no extra keys.
	 *
	 * @param string[] $citation_ids
	 * @return array<string, mixed>
	 */
	public static function json_schema( array $citation_ids ): array {
		$dimensions = array_fill_keys( array( 'length', 'width', 'height', 'depth' ), array( 'type' => array( 'number', 'null' ) ) );
		$proposal = array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array(
			'field' => array( 'type' => 'string', 'enum' => array( 'category', 'style', 'room', 'color', 'material', 'dimensions', 'features', 'suitability', 'tags' ) ),
			'state' => array( 'type' => 'string', 'enum' => array( 'value', 'unknown', 'not_applicable' ) ),
			'termIds' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			'measurements' => array( 'type' => 'object', 'additionalProperties' => false, 'properties' => $dimensions, 'required' => array_keys( $dimensions ) ),
			'unit' => array( 'type' => 'string', 'enum' => array( 'cm', 'mm', 'm', 'in' ) ),
			'confidence' => array( 'type' => 'number', 'minimum' => 0, 'maximum' => 1 ),
			'evidence' => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'enum' => $citation_ids ) ),
			'judgment' => array( 'type' => 'string', 'enum' => array( 'factual', 'editorial' ) ),
		), 'required' => array( 'field', 'state', 'termIds', 'measurements', 'unit', 'confidence', 'evidence', 'judgment' ) );
		return array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array( 'fields' => array( 'type' => 'array', 'items' => $proposal ), 'citation_source_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'enum' => $citation_ids ) ) ), 'required' => array( 'fields', 'citation_source_ids' ) );
	}

	/**
	 * @param array<string, mixed> $result
	 * @param string[] $allowed_sources
	 * @return array<string, mixed>|WP_Error
	 */
	public static function validate_result( array $result, array $allowed_sources ): array|WP_Error {
		if ( ! class_exists( 'Chidemoon_Core_Enrichment' ) || array_diff( array_keys( $result ), array( 'fields', 'citation_source_ids', '_usage', 'approvedFields' ) ) || ! is_array( $result['fields'] ?? null ) || count( $result['fields'] ) > 9 || ! is_array( $result['citation_source_ids'] ?? null ) || array_diff( $result['citation_source_ids'], $allowed_sources ) ) { return new WP_Error( 'chidemoon_ai_enrich_invalid', __( 'The enrichment proposal requires the structured editorial schema.', 'chidemoon-ai' ) ); }
		$seen = array();
		foreach ( $result['fields'] as $proposal ) {
			try {
				if ( ! is_array( $proposal ) || array_diff( array_keys( $proposal ), array( 'field', 'state', 'termIds', 'measurements', 'unit', 'confidence', 'evidence', 'judgment' ) ) || isset( $seen[ $proposal['field'] ?? '' ] ) || ! is_array( $proposal['evidence'] ?? null ) || array_diff( $proposal['evidence'], $allowed_sources ) || ! is_numeric( $proposal['confidence'] ?? null ) || $proposal['confidence'] < 0 || $proposal['confidence'] > 1 ) { throw new InvalidArgumentException( 'Invalid field evidence or confidence' ); }
				$clean = self::proposal( $proposal );
				if ( 'value' === $clean['state'] && 'factual' === $clean['judgment'] && ! $clean['evidence'] ) { throw new InvalidArgumentException( 'Factual values require evidence' ); }
				if ( $proposal['judgment'] !== $clean['judgment'] ) { throw new InvalidArgumentException( 'Stylistic judgments must be labeled editorial' ); }
				$seen[ $proposal['field'] ] = true;
			} catch ( Throwable $exception ) { return new WP_Error( 'chidemoon_ai_enrich_invalid', $exception->getMessage() ); }
		}
		return array( 'fields' => $result['fields'], 'citation_source_ids' => array_values( array_unique( $result['citation_source_ids'] ) ) );
	}

	public static function proposal( array $value ): array {
		return Chidemoon_Core_Enrichment::validate_field( (string) ( $value['field'] ?? '' ), array( 'state' => $value['state'] ?? '', 'value' => 'dimensions' === ( $value['field'] ?? '' ) ? $value['measurements'] ?? null : $value['termIds'] ?? null, 'unit' => $value['unit'] ?? 'cm', 'measurementType' => 'product', 'evidence' => $value['evidence'] ?? array() ) ) + array( 'confidence' => $value['confidence'] ?? 0 );
	}

	/**
	 * Applies only selected approved fields against the captured editorial revision.
	 *
	 * @param array<string, mixed> $job
	 * @return int|WP_Error Product ID.
	 */
	public static function apply( array $job, int $actor_id, bool $confirm_affiliate_change = false ): int|WP_Error {
		global $wpdb;
		unset( $confirm_affiliate_change );
		$product_id = absint( $job['target_post_id'] ?? 0 );
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			return new WP_Error( 'chidemoon_ai_enrich_target', __( 'The enrichment target product is unavailable.', 'chidemoon-ai' ) );
		}
		if ( ! current_user_can( 'edit_post', $product_id ) || ! current_user_can( 'chidemoon_manage_affiliate' ) ) {
			return new WP_Error( 'chidemoon_ai_enrich_forbidden', __( 'You cannot edit this product.', 'chidemoon-ai' ), array( 'status' => 403 ) );
		}
		if ( ! Chidemoon_Core_Source_Importer::acquire_lock() ) { return new WP_Error( 'chidemoon_ai_enrich_busy', __( 'Source import is running. Retry this application.', 'chidemoon-ai' ), array( 'status' => 409 ) ); }
		try {
			$wpdb->query( 'START TRANSACTION' );
			$wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}chidemoon_ai_jobs WHERE id = %d FOR UPDATE", (int) $job['id'] ) );
			$job = Chidemoon_AI_Repository::find( (int) $job['id'] );
			if ( ! $job || Chidemoon_AI_State_Machine::APPROVED !== $job['state'] || empty( $job['result_payload']['approvedFields'] ) ) { throw new RuntimeException( 'Selective reviewer approval is required' ); }
			$result = $job['result_payload'];
			$validated = self::validate_result( $result, Chidemoon_AI_Evidence::source_ids( Chidemoon_AI_Evidence::for_job( (int) $job['id'] ) ) );
			if ( is_wp_error( $validated ) ) { throw new RuntimeException( $validated->get_error_message() ); }
			$fields = array();
			foreach ( $validated['fields'] as $proposal ) { if ( in_array( $proposal['field'], $result['approvedFields'], true ) ) { $fields[ $proposal['field'] ] = self::proposal( $proposal ); } }
			Chidemoon_Core_Enrichment::apply( $product_id, $fields, (string) ( $job['request_payload']['enrichment_revision'] ?? '' ), $actor_id, false );
			update_post_meta( $product_id, '_chidemoon_ai_last_job_id', (int) $job['id'] );
			update_post_meta( $product_id, '_chidemoon_ai_provenance', wp_json_encode( $job['provenance'] ?? array(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			if ( ! Chidemoon_AI_Repository::mark_applied( (int) $job['id'], $product_id ) ) { throw new RuntimeException( 'The enrichment job changed concurrently' ); }
			$wpdb->query( 'COMMIT' );
			return $product_id;
		} catch ( Throwable $exception ) { $wpdb->query( 'ROLLBACK' ); wp_cache_delete( $product_id, 'post_meta' ); clean_post_cache( $product_id ); wc_delete_product_transients( $product_id ); return new WP_Error( 'chidemoon_ai_enrich_rejected', $exception->getMessage(), array( 'status' => 409 ) ); }
		finally { Chidemoon_Core_Source_Importer::release_lock(); }
	}
}
