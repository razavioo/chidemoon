<?php
/**
 * Auto-links keywords in post content to affiliate product URLs.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Auto_Linker {

	public static function init(): void {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			// Product links must come from the authored editorial markup or approved
			// catalog blocks, never a request-time content transformation.
			return;
		}

		add_filter( 'the_content', array( __CLASS__, 'process_content' ), 20 );
	}

	/**
	 * Replace keyword occurrences in post content with affiliate links.
	 */
	public static function process_content( string $content ): string {
		if ( ! is_singular() ) {
			return $content;
		}

		$mappings = self::get_active_mappings();
		if ( empty( $mappings ) ) {
			return $content;
		}

		foreach ( $mappings as $mapping ) {
			$content = self::apply_mapping( $content, $mapping );
		}

		return $content;
	}

	/**
	 * Apply a single keyword mapping to content.
	 */
	private static function apply_mapping( string $content, array $mapping ): string {
		$keyword     = $mapping['keyword'];
		$product_id  = $mapping['product_id'];
		$max         = (int) $mapping['max_per_post'];

		if ( ! $keyword || ! $product_id ) {
			return $content;
		}

		$destination = self::get_product_destination( $product_id );
		if ( '' === $destination['url'] ) {
			return $content;
		}
		$link_attrs = Kalahamoon_Link_Builder::public_link_attributes( $destination );

		$count      = 0;
		$in_tag     = false;
		$in_anchor  = false;
		$result     = '';
		$len        = strlen( $content );
		$i          = 0;
		$kw_lower   = mb_strtolower( $keyword );
		$kw_len     = mb_strlen( $keyword );

		// Walk through HTML character by character, only replacing text nodes
		// outside of any HTML tags and outside of <a> elements.
		while ( $i < $len ) {
			$char = $content[ $i ];

			// Entering a tag
			if ( $char === '<' ) {
				// Check if this is <a or <A (opening anchor)
				if ( preg_match( '/^<a[\s>]/i', substr( $content, $i, 3 ) ) ) {
					$in_anchor = true;
				}
				// Check if this is </a> (closing anchor)
				if ( preg_match( '/^<\/a>/i', substr( $content, $i, 4 ) ) ) {
					$in_anchor = false;
				}
				$in_tag = true;
			}

			if ( $in_tag ) {
				$result .= $char;
				if ( $char === '>' ) {
					$in_tag = false;
				}
				$i++;
				continue;
			}

			// We're in text — check if keyword starts here (case-insensitive)
			if ( ! $in_anchor && $count < $max ) {
				$remaining = substr( $content, $i );
				if ( mb_strtolower( mb_substr( $remaining, 0, $kw_len ) ) === $kw_lower ) {
					// Verify word boundary: character before and after must not be word chars
					$before = $i > 0 ? mb_substr( $content, $i - 1, 1 ) : ' ';
					$after  = mb_substr( $remaining, $kw_len, 1 );

					$before_ok = ! preg_match( '/\w/u', $before );
					$after_ok  = $after === '' || ! preg_match( '/\w/u', $after );

					if ( $before_ok && $after_ok ) {
						// Get the original-case version of the keyword from content
						$original = mb_substr( $content, $i, $kw_len );
						$result  .= '<a href="' . esc_url( $destination['url'] ) . '" class="kalahamoon-auto-link ' . esc_attr( $link_attrs['class'] ) . '" rel="' . esc_attr( $link_attrs['rel'] ) . '" data-product-id="' . esc_attr( $product_id ) . '" data-link-id="' . esc_attr( $link_attrs['linkId'] ) . '" data-link-kind="' . esc_attr( $link_attrs['kind'] ) . '">' . esc_html( $original ) . '</a>';
						$i       += $kw_len;
						$count++;
						continue;
					}
				}
			}

			$result .= $char;
			$i++;
		}

		return $result;
	}

	/**
	 * Get active keyword mappings ordered by priority.
	 */
	private static function get_active_mappings(): array {
		$cached = wp_cache_get( 'kalahamoon_auto_links', 'kalahamoon' );
		if ( $cached !== false ) {
			return $cached;
		}

		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}kalahamoon_auto_links WHERE is_active = 1 ORDER BY priority ASC, id ASC",
			ARRAY_A
		);

		$mappings = $rows ?: array();
		wp_cache_set( 'kalahamoon_auto_links', $mappings, 'kalahamoon', 300 );

		return $mappings;
	}

	/**
	 * Resolve a product destination while preserving its affiliate state.
	 */
	private static function get_product_destination( string $product_id ): array {
		$product = Kalahamoon_Product_Cache::get_by_kalahamoon_id( $product_id );
		if ( ! $product ) {
			return array( 'url' => '', 'isAffiliate' => false, 'linkId' => '' );
		}

		return Kalahamoon_Link_Builder::resolve_product_destination( $product );
	}
}
