<?php
/**
 * Product enrichment: local facts + free web evidence -> structured proposal.
 *
 * Output is ALWAYS a review-gated proposal. Apply only writes to drafts and
 * never overwrites the affiliate destination without an explicit reviewer
 * confirmation.
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
				}
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
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'title'              => array( 'type' => 'string' ),
				'short_description'  => array( 'type' => 'string' ),
				'description'        => array( 'type' => 'string' ),
				'facts'              => array(
					'type'  => 'object',
					'additionalProperties' => array( 'type' => 'string' ),
				),
				'price_hint'         => array( 'type' => 'string' ),
				'price_currency'     => array( 'type' => 'string' ),
				'needs_price_check'  => array( 'type' => 'boolean' ),
				'facts_needing_review' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'citation_source_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'enum' => $citation_ids ) ),
			),
			'required'             => array( 'title', 'short_description', 'description', 'facts', 'facts_needing_review', 'citation_source_ids' ),
		);
	}

	/**
	 * @param array<string, mixed> $result
	 * @param string[] $allowed_sources
	 * @return array<string, mixed>|WP_Error
	 */
	public static function validate_result( array $result, array $allowed_sources ): array|WP_Error {
		$title = sanitize_text_field( (string) ( $result['title'] ?? '' ) );
		$short = sanitize_textarea_field( (string) ( $result['short_description'] ?? '' ) );
		$desc  = wp_kses_post( (string) ( $result['description'] ?? '' ) );
		if ( '' === $title || '' === trim( wp_strip_all_tags( $desc ) ) ) {
			return new WP_Error( 'chidemoon_ai_enrich_invalid', __( 'The enrichment output did not match the required format.', 'chidemoon-ai' ) );
		}
		$len = static function ( string $v ): int {
			return function_exists( 'mb_strlen' ) ? mb_strlen( $v ) : strlen( $v );
		};
		if ( $len( $title ) > 220 || $len( $short ) > 900 || $len( $desc ) > 12000 ) {
			return new WP_Error( 'chidemoon_ai_enrich_bounds', __( 'The enrichment output exceeded the safe bounds.', 'chidemoon-ai' ) );
		}
		$facts = $result['facts'] ?? null;
		if ( ! is_array( $facts ) || count( $facts ) > 30 ) {
			return new WP_Error( 'chidemoon_ai_enrich_invalid', __( 'The enrichment facts did not match the required format.', 'chidemoon-ai' ) );
		}
		$clean_facts = array();
		foreach ( $facts as $label => $value ) {
			$label = sanitize_text_field( (string) $label );
			$value = sanitize_text_field( (string) $value );
			if ( '' === $label || '' === $value || $len( $label ) > 120 || $len( $value ) > 500 ) {
				continue;
			}
			$clean_facts[ $label ] = $value;
		}
		$flags = $result['facts_needing_review'] ?? null;
		if ( ! is_array( $flags ) ) {
			return new WP_Error( 'chidemoon_ai_enrich_invalid', __( 'The enrichment output needs review flags.', 'chidemoon-ai' ) );
		}
		$clean_flags = array();
		foreach ( array_slice( $flags, 0, 20 ) as $flag ) {
			if ( ! is_string( $flag ) || $len( $flag ) > 500 ) {
				continue;
			}
			$clean_flags[] = sanitize_text_field( $flag );
		}
		$citations = $result['citation_source_ids'] ?? null;
		if ( ! is_array( $citations ) ) {
			return new WP_Error( 'chidemoon_ai_enrich_invalid', __( 'The enrichment output needs citations.', 'chidemoon-ai' ) );
		}
		$clean_citations = array();
		foreach ( $citations as $citation ) {
			$citation = sanitize_text_field( (string) $citation );
			if ( ! in_array( $citation, $allowed_sources, true ) ) {
				return new WP_Error( 'chidemoon_ai_enrich_citation', __( 'The enrichment output cited a source outside the reviewed evidence.', 'chidemoon-ai' ) );
			}
			$clean_citations[] = $citation;
		}

		return array(
			'title'                => $title,
			'short_description'    => $short,
			'description'          => $desc,
			'facts'                => $clean_facts,
			'price_hint'           => sanitize_text_field( (string) ( $result['price_hint'] ?? '' ) ),
			'price_currency'       => sanitize_text_field( (string) ( $result['price_currency'] ?? '' ) ),
			'needs_price_check'    => ! empty( $result['needs_price_check'] ),
			'facts_needing_review' => array_values( array_unique( $clean_flags ) ),
			'citation_source_ids'  => array_values( array_unique( $clean_citations ) ),
		);
	}

	/**
	 * Applies an APPROVED enrichment to a product draft. Never publishes,
	 * never touches the affiliate destination unless explicitly confirmed.
	 *
	 * @param array<string, mixed> $job
	 * @return int|WP_Error Product ID.
	 */
	public static function apply( array $job, int $actor_id, bool $confirm_affiliate_change = false ): int|WP_Error {
		$result = is_array( $job['result_payload'] ?? null ) ? $job['result_payload'] : array();
		$product_id = absint( $job['target_post_id'] ?? 0 );
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			return new WP_Error( 'chidemoon_ai_enrich_target', __( 'The enrichment target product is unavailable.', 'chidemoon-ai' ) );
		}
		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return new WP_Error( 'chidemoon_ai_enrich_forbidden', __( 'You cannot edit this product.', 'chidemoon-ai' ), array( 'status' => 403 ) );
		}
		unset( $actor_id );

		$validated = self::validate_result( $result, Chidemoon_AI_Evidence::source_ids( Chidemoon_AI_Evidence::for_job( (int) $job['id'] ) ) );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$post_data = array(
			'ID'              => $product_id,
			'post_title'      => $validated['title'],
			'post_content'    => $validated['description'],
			'post_excerpt'    => $validated['short_description'],
			'post_status'     => 'draft',
		);
		$updated = wp_update_post( wp_slash( $post_data ), true );
		if ( is_wp_error( $updated ) || ! $updated ) {
			return new WP_Error( 'chidemoon_ai_enrich_apply_failed', __( 'The enrichment could not be saved to the product draft.', 'chidemoon-ai' ) );
		}
		if ( class_exists( 'Chidemoon_Core_Affiliate' ) ) {
			update_post_meta( $product_id, Chidemoon_Core_Affiliate::META_FACTS, wp_json_encode( $validated['facts'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			update_post_meta( $product_id, Chidemoon_Core_Affiliate::META_SOURCE_CHECKED, gmdate( DATE_ATOM ) );
			unset( $confirm_affiliate_change );
		}
		update_post_meta( $product_id, '_chidemoon_ai_last_job_id', (int) $job['id'] );
		update_post_meta( $product_id, '_chidemoon_ai_provenance', wp_json_encode( $job['provenance'] ?? array(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

		if ( ! Chidemoon_AI_Repository::mark_applied( (int) $job['id'], $product_id ) ) {
			return new WP_Error( 'chidemoon_ai_apply_state_conflict', __( 'The enrichment job changed while it was being saved.', 'chidemoon-ai' ), array( 'status' => 409 ) );
		}

		return $product_id;
	}
}
