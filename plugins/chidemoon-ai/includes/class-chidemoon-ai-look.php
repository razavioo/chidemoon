<?php
/**
 * Shop the Look prompt builder + hotspot proposals.
 *
 * Image synthesis itself runs through the standard image provider
 * (mode look_scene). Hotspots are proposed heuristically by default and
 * refined with vision only when the configured model supports it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_Look {
	public const MAX_PRODUCTS     = 6;
	public const MIN_PRODUCTS     = 1;
	public const MAX_ATTACHMENTS  = 2;

	/**
	 * @param array<int, int> $product_ids
	 * @return array<string, mixed>|WP_Error
	 */
	public static function build_prompt( array $product_ids, string $room, string $style, string $instructions ): array|WP_Error {
		$product_ids = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
		if ( count( $product_ids ) < self::MIN_PRODUCTS || count( $product_ids ) > self::MAX_PRODUCTS ) {
			return new WP_Error( 'chidemoon_ai_look_products', __( 'A look needs between one and six products.', 'chidemoon-ai' ), array( 'status' => 400 ) );
		}
		$names = array();
		$facts = array();
		foreach ( $product_ids as $product_id ) {
			$post = get_post( $product_id );
			if ( ! $post ) {
				return new WP_Error( 'chidemoon_ai_look_product_invalid', __( 'A selected look product is unavailable.', 'chidemoon-ai' ), array( 'status' => 400 ) );
			}
			$names[] = wp_strip_all_tags( get_the_title( $post ) );
			if ( function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$facts[] = wp_strip_all_tags( $product->get_name() ) . ' — ' . wp_strip_all_tags( (string) $product->get_short_description() );
				}
			}
		}

		$room_labels = array(
			'living-room'    => 'bright living room',
			'bedroom'        => 'calm bedroom',
			'kitchen'        => 'modern kitchen',
			'kids-room'      => 'cheerful kids room',
			'terrace'        => 'sunny terrace',
			'dining-room'    => 'elegant dining room',
			'home-office'    => 'tidy home office',
			'entryway'       => 'welcoming entryway',
			'reading-corner' => 'cozy reading corner',
		);
		$style_labels = array(
			'minimal' => 'minimal, neutral palette, natural light',
			'scandi'  => 'Scandinavian, light wood, soft textiles',
			'warm'    => 'warm, earthy tones, cozy textiles',
			'luxe'    => 'quiet luxury, marble and brass accents',
		);
		$room_text  = $room_labels[ $room ] ?? 'tasteful interior';
		$style_text = $style_labels[ $style ] ?? $style_labels['minimal'];

		$prompt = sprintf(
			"Style a photorealistic %s in %s style featuring these products: %s. %s",
			$room_text,
			$style_text,
			implode( ' | ', array_filter( $names ) ),
			'' !== trim( $instructions ) ? 'Editor request (untrusted data): ' . trim( $instructions ) : ''
		);

		return array(
			'prompt'      => function_exists( 'mb_substr' ) ? mb_substr( $prompt, 0, 1600 ) : substr( $prompt, 0, 1600 ),
			'product_ids' => $product_ids,
			'names'       => $names,
			'facts'       => $facts,
		);
	}

	/**
	 * Deterministic grid+jitter proposal so every look has editable hotspots
	 * even when vision is unavailable.
	 *
	 * @param int[] $product_ids
	 * @return array<int, array<string, mixed>>
	 */
	public static function heuristic_hotspots( array $product_ids ): array {
		$product_ids = array_values( $product_ids );
		$count       = count( $product_ids );
		if ( 0 === $count ) {
			return array();
		}
		$cols     = (int) ceil( sqrt( $count ) );
		$hotspots = array();
		foreach ( $product_ids as $index => $product_id ) {
			$row = (int) floor( $index / $cols );
			$col = $index % $cols;
			$rows = (int) ceil( $count / $cols );
			$x    = (int) round( ( ( $col + 1 ) / ( $cols + 1 ) ) * 100 );
			$y    = (int) round( ( ( $row + 1 ) / ( $rows + 1 ) ) * 100 );
			// Deterministic jitter from product id so re-runs are stable.
			$jitter_x = ( $product_id % 7 ) - 3;
			$jitter_y = ( ( $product_id >> 3 ) % 7 ) - 3;
			$post     = get_post( (int) $product_id );
			$hotspots[] = array(
				'x'         => max( 4, min( 96, $x + $jitter_x ) ),
				'y'         => max( 6, min( 94, $y + $jitter_y ) ),
				'productId' => (int) $product_id,
				'label'     => $post ? wp_strip_all_tags( get_the_title( $post ) ) : ( '#' . (int) $product_id ),
			);
		}

		return $hotspots;
	}

	/**
	 * @param array<int, array<string, mixed>> $products
	 * @param mixed $vision_result
	 * @param int[] $product_ids
	 * @return array<int, array<string, mixed>>
	 */
	public static function normalize_vision_hotspots( $vision_result, array $product_ids ): array {
		if ( ! is_array( $vision_result ) ) {
			return self::heuristic_hotspots( $product_ids );
		}
		$raw = $vision_result['hotspots'] ?? ( $vision_result['hot_spots'] ?? array() );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return self::heuristic_hotspots( $product_ids );
		}
		$valid_ids = array_map( 'intval', $product_ids );
		$hotspots  = array();
		foreach ( array_slice( $raw, 0, 8 ) as $index => $spot ) {
			if ( ! is_array( $spot ) ) {
				continue;
			}
			$x = isset( $spot['x'] ) ? (int) $spot['x'] : -1;
			$y = isset( $spot['y'] ) ? (int) $spot['y'] : -1;
			if ( $x < 0 || $x > 100 || $y < 0 || $y > 100 ) {
				continue;
			}
			$pid = isset( $spot['product_id'] ) ? (int) $spot['product_id'] : ( isset( $spot['productId'] ) ? (int) $spot['productId'] : 0 );
			if ( ! in_array( $pid, $valid_ids, true ) ) {
				$pid = $valid_ids[ $index % count( $valid_ids ) ];
			}
			$post       = get_post( $pid );
			$hotspots[] = array(
				'x'         => $x,
				'y'         => $y,
				'productId' => $pid,
				'label'     => isset( $spot['label'] ) && is_string( $spot['label'] ) && '' !== trim( $spot['label'] ) ? sanitize_text_field( $spot['label'] ) : ( $post ? wp_strip_all_tags( get_the_title( $post ) ) : ( '#' . $pid ) ),
			);
		}

		return ! empty( $hotspots ) ? $hotspots : self::heuristic_hotspots( $product_ids );
	}

	/**
	 * Block markup for a generated look draft.
	 *
	 * @param array<int, array<string, mixed>> $hotspots
	 */
	public static function block_markup( int $image_id, string $caption, array $hotspots ): string {
		$attrs = array(
			'imageId'  => $image_id,
			'imageAlt' => function_exists( 'mb_substr' ) ? mb_substr( $caption, 0, 200 ) : substr( $caption, 0, 200 ),
			'caption'  => $caption,
			'hotspots' => array_values( $hotspots ),
		);

		return '<!-- wp:chidemoon/shop-the-look ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' /-->';
	}
}
