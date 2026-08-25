<?php
/**
 * JSON-LD structured data output for Product schema.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Schema_Output {

	public static function init(): void {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			// Block templates own their projection-backed schema in connector mode.
			// Avoid a second document-level pass emitting duplicate Product markup.
			return;
		}

		add_action( 'wp_head', array( __CLASS__, 'output_schema' ), 99 );
	}

	public static function output_schema(): void {
		if ( is_admin() || ! is_singular() ) {
			return;
		}

		$post    = get_post();
		$content = $post->post_content ?? '';

		// Only output if post contains Kalahamoon product blocks or shortcodes
		if ( ! has_block( 'kalahamoon/product-box', $content )
			&& ! has_block( 'kalahamoon/product-grid', $content )
			&& ! has_block( 'kalahamoon/comparison-table', $content )
			&& false === strpos( $content, '[kalahamoon_product' ) ) {
			return;
		}

		// Extract product IDs from blocks
		$product_ids = self::extract_product_ids( $content );
		if ( empty( $product_ids ) ) {
			return;
		}

		$schemas = array();
		foreach ( $product_ids as $pid ) {
			$product = Kalahamoon_Product_Cache::get_by_kalahamoon_id( $pid );
			if ( ! $product ) {
				continue;
			}

			$schema = array(
				'@context'    => 'https://schema.org',
				'@type'       => 'Product',
				'name'        => $product['title'],
				'description' => wp_strip_all_tags( $product['description'] ),
			);

			if ( ! empty( $product['imageUrl'] ) ) {
				$schema['image'] = $product['imageUrl'];
			}

			if ( ! empty( $product['brand'] ) ) {
				$schema['brand'] = array(
					'@type' => 'Brand',
					'name'  => $product['brand'],
				);
			}

			// Optional aggregate rating from synced marketplace metadata.
			$rating       = isset( $product['metadata']['rating'] ) ? (float) $product['metadata']['rating'] : 0.0;
			$rating_count = isset( $product['metadata']['ratingCount'] ) ? (int) $product['metadata']['ratingCount'] : 0;
			if ( $rating > 0 && $rating_count > 0 ) {
				$schema['aggregateRating'] = array(
					'@type'       => 'AggregateRating',
					'ratingValue' => $rating,
					'reviewCount' => $rating_count,
					'bestRating'  => isset( $product['metadata']['ratingMax'] ) ? (float) $product['metadata']['ratingMax'] : 5,
				);
			}

			if ( $product['price'] > 0 ) {
				$currency = ( ! empty( $product['currency'] ) ) ? (string) $product['currency'] : 'IRR';
				$schema['offers'] = array(
					'@type'         => 'Offer',
					'price'         => $product['price'],
					'priceCurrency' => $currency,
					'availability'  => $product['inventory'] > 0
						? 'https://schema.org/InStock'
						: 'https://schema.org/OutOfStock',
					'url'           => $product['listingUrl'] ?: get_permalink(),
				);
			}

			$schemas[] = $schema;
		}

		if ( empty( $schemas ) ) {
			return;
		}

		foreach ( $schemas as $schema ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
		}
	}

	private static function extract_product_ids( string $content ): array {
		$ids = array();

		// From Gutenberg blocks
		$blocks = parse_blocks( $content );
		foreach ( $blocks as $block ) {
			if ( isset( $block['attrs']['productId'] ) ) {
				$ids[] = $block['attrs']['productId'];
			}
			if ( isset( $block['attrs']['productIds'] ) && is_array( $block['attrs']['productIds'] ) ) {
				$ids = array_merge( $ids, $block['attrs']['productIds'] );
			}
		}

		// From shortcodes
		if ( preg_match_all( '/\[kalahamoon_product\s+id=["\']([^"\']+)["\']/', $content, $matches ) ) {
			$ids = array_merge( $ids, $matches[1] );
		}

		return array_unique( array_filter( $ids ) );
	}
}
