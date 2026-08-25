<?php
/**
 * Affiliate link resolver.
 * All link construction happens on the kalahamoon panel.
 * This class only serves cached cloaked URLs from the local mirror table.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Link_Builder {
	/**
	 * Build an affiliate URL for a provider.
	 *
	 * @param string $destination_url Raw destination URL.
	 * @param string $tracking_url    Provider tracking URL.
	 * @param string $provider        Provider slug (e.g. bakalahamoon, digikala).
	 * @return string
	 */
	public static function build( string $destination_url, string $tracking_url = '', string $provider = '' ): string {
		$provider = strtolower( trim( $provider ) );

		if ( 'bakalahamoon' === $provider ) {
			return self::build_bakalahamoon( $destination_url, $tracking_url );
		}

		if ( 'digikala' === $provider ) {
			return self::build_digikala( $destination_url, $tracking_url );
		}

		return self::build_custom( $destination_url );
	}

	/**
	 * Build a Bakalahamoon tracking link.
	 *
	 * @param string $destination_url Raw destination URL.
	 * @param string $tracking_url    Bakalahamoon tracking URL.
	 * @return string
	 */
	public static function build_bakalahamoon( string $destination_url, string $tracking_url ): string {
		if ( '' === $destination_url ) {
			return '';
		}

		if ( '' === $tracking_url ) {
			return $destination_url;
		}

		$base_url = strtok( $tracking_url, '?' );
		$encoded  = rtrim( base64_encode( $destination_url ), '=' );

		return self::append_query_param( $base_url, 'b64', $encoded );
	}

	/**
	 * Build a Digikala tracking link.
	 *
	 * @param string $destination_url Raw destination URL.
	 * @param string $tracking_url    Digikala tracking URL.
	 * @return string
	 */
	public static function build_digikala( string $destination_url, string $tracking_url ): string {
		if ( '' === $destination_url ) {
			return '';
		}

		if ( '' === $tracking_url ) {
			return $destination_url;
		}

		return self::append_query_param( $tracking_url, 'url', $destination_url );
	}

	/**
	 * Append a single query parameter without requiring WordPress helpers.
	 *
	 * @param string $url   Base URL.
	 * @param string $key   Query key.
	 * @param string $value Query value.
	 * @return string
	 */
	private static function append_query_param( string $url, string $key, string $value ): string {
		$separator = ( false === strpos( $url, '?' ) ) ? '?' : '&';

		return $url . $separator . rawurlencode( $key ) . '=' . rawurlencode( $value );
	}

	/**
	 * Extract Bakalahamoon tracking code from URL.
	 *
	 * @param string $url Tracking URL.
	 * @return string
	 */
	public static function extract_bakalahamoon_tracking_code( string $url ): string {
		if ( preg_match( '#/tracking/click/g/([^/?]+)#', $url, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Decode the destination URL from a Bakalahamoon affiliate link.
	 *
	 * @param string $affiliate_url Bakalahamoon affiliate URL.
	 * @return string
	 */
	public static function decode_bakalahamoon_destination( string $affiliate_url ): string {
		$query = wp_parse_url( $affiliate_url, PHP_URL_QUERY );
		if ( ! is_string( $query ) || '' === $query ) {
			return '';
		}

		parse_str( $query, $params );
		$encoded = isset( $params['b64'] ) ? (string) $params['b64'] : '';
		if ( '' === $encoded ) {
			return '';
		}

		$encoded = rawurldecode( $encoded );
		$padded  = $encoded . str_repeat( '=', ( 4 - strlen( $encoded ) % 4 ) % 4 );
		$decoded = base64_decode( $padded, true );

		return is_string( $decoded ) ? $decoded : '';
	}

	/**
	 * Resolve the public destination for a product.
	 *
	 * The public presentation must look identical whether a provider can create
	 * a commissionable link or not. The `isAffiliate` flag is deliberately kept
	 * separate so renderers apply sponsored semantics and disclosures only when
	 * a panel-issued affiliate URL actually exists.
	 *
	 * @param array $product Product data array with 'id', optional 'listingUrl', 'listings'.
	 * @return array{url:string,isAffiliate:bool,linkId:string}
	 */
	public static function resolve_product_destination( array $product ): array {
		$empty = array(
			'url'         => '',
			'isAffiliate' => false,
			'linkId'      => '',
		);

		if ( ! empty( $product['catalogProjection'] ) ) {
			// The approved projection already contains the public destination.
			// Looking up a local affiliate mirror here would let WordPress replace
			// Kalahamoon's selected offer after the catalog has been published.
			$url = self::normalize_clickable_url( (string) ( $product['listingUrl'] ?? '' ) );
			return '' === $url ? $empty : array(
				'url'         => $url,
				'isAffiliate' => false,
				'linkId'      => '',
			);
		}

		global $wpdb;
		$table = $wpdb->prefix . 'kalahamoon_affiliate_links';

		$link = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, kalahamoon_short_url, slug FROM {$table} WHERE product_id = %s AND status = 'active' LIMIT 1",
			$product['id'] ?? ''
		), ARRAY_A );

		$short_url = $link ? (string) ( $link['kalahamoon_short_url'] ?? '' ) : '';
		if ( $link && self::is_clickable_url( $short_url ) && ! preg_match( '#^/go/#', $short_url ) ) {
			return array(
				'url'         => $short_url,
				'isAffiliate' => true,
				'linkId'      => (string) ( $link['id'] ?? '' ),
			);
		}

		if ( $link && ! empty( $link['slug'] ) ) {
			return array(
				'url'         => home_url( '/go/' . self::encode_local_go_slug( (string) $link['slug'] ) ),
				'isAffiliate' => true,
				'linkId'      => (string) ( $link['id'] ?? '' ),
			);
		}

		$fallback = $product['listingUrl'] ?? ( $product['listings'][0]['listingUrl'] ?? '' );
		$url      = self::normalize_clickable_url( $fallback );
		if ( '' === $url ) {
			return $empty;
		}

		return array(
			'url'         => $url,
			'isAffiliate' => false,
			'linkId'      => '',
		);
	}

	/**
	 * Get the public destination URL for a product.
	 *
	 * Kept for block and theme compatibility. New renderers should use
	 * resolve_product_destination() so affiliate-only metadata stays accurate.
 *
	 * @param array $product  Product data array with 'id', optional 'listingUrl', 'listings'.
	 * @return string
	 */
	public static function get_product_affiliate_url( array $product ): string {
		$destination = self::resolve_product_destination( $product );

		return $destination['url'];
	}

	/**
	 * Build stable public-link markup attributes from a resolved destination.
	 *
	 * @param array{url:string,isAffiliate:bool,linkId:string} $destination
	 * @return array{class:string,rel:string,linkId:string,kind:string}
	 */
	public static function public_link_attributes( array $destination ): array {
		$is_affiliate = ! empty( $destination['isAffiliate'] );

		return array(
			'class'  => $is_affiliate ? 'kalahamoon-product-link kalahamoon-affiliate-link' : 'kalahamoon-product-link',
			'rel'    => $is_affiliate ? 'sponsored nofollow noopener' : 'noopener',
			'linkId' => (string) ( $destination['linkId'] ?? '' ),
			'kind'   => $is_affiliate ? 'affiliate' : 'direct',
		);
	}

	/**
	 * Keep the default product action neutral and consistent across blocks.
	 *
	 * Authors can still provide a specific editorial label, while an empty
	 * default is never allowed to turn into an unexplained or store-specific
	 * call to action.
	 */
	public static function public_cta_label( $label ): string {
		$label = is_string( $label ) ? trim( $label ) : '';

		return '' === $label || 'View product' === $label
			? __( 'View product', 'kalahamoon' )
			: $label;
	}

	/**
	 * Normalize an explicit listing URL that has no corresponding link mirror.
	 *
	 * Multi-listing comparison rows must not inherit the primary product's
	 * affiliate state because they can lead to a different seller.
	 */
	public static function resolve_direct_destination( $url ): array {
		return array(
			'url'         => self::normalize_clickable_url( $url ),
			'isAffiliate' => false,
			'linkId'      => '',
		);
	}

	/**
	 * Encode a stored local /go/ slug exactly once.
	 */
	private static function encode_local_go_slug( string $slug ): string {
		$slug = trim( $slug, " \t\n\r\0\x0B/" );

		for ( $i = 0; $i < 3; $i++ ) {
			$decoded = rawurldecode( $slug );
			if ( $decoded === $slug ) {
				break;
			}
			$slug = $decoded;
		}

		return rawurlencode( $slug );
	}

	/**
	 * Whether a URL is a real, clickable destination (absolute http(s) or a
	 * site-root-relative path). Internal marketplace references such as
	 * "basalam:123", "digikala:456", "#", or empty strings are rejected.
	 *
	 * @param mixed $url
	 * @return bool
	 */
	public static function is_clickable_url( $url ): bool {
		return '' !== self::normalize_clickable_url( $url );
	}

	public static function normalize_clickable_url( $url ): string {
		if ( ! is_string( $url ) ) {
			return '';
		}

		$url = trim( $url );
		if ( '' === $url || '#' === $url ) {
			return '';
		}

		// Absolute http(s) URL.
		if ( preg_match( '#^https?://#i', $url ) ) {
			return self::normalize_absolute_http_url( $url );
		}

		// Site-root-relative path (e.g. /go/slug from the link cloaker).
		if ( '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
			return $url;
		}

		return '';
	}

	private static function normalize_absolute_http_url( string $url ): string {
		$parts = self::split_absolute_http_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}

		$host = (string) $parts['host'];
		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path = isset( $parts['path'] ) ? self::encode_url_path( (string) $parts['path'] ) : '';
		$query = '';
		if ( isset( $parts['query'] ) && '' !== (string) $parts['query'] ) {
			$query = '?' . self::encode_url_query( (string) $parts['query'] );
		}
		$fragment = isset( $parts['fragment'] ) ? '#' . rawurlencode( rawurldecode( (string) $parts['fragment'] ) ) : '';

		return "{$scheme}://{$host}{$port}{$path}{$query}{$fragment}";
	}

	private static function split_absolute_http_url( string $url ): array {
		$scheme_pos = strpos( $url, '://' );
		if ( false === $scheme_pos ) {
			return array();
		}

		$scheme = substr( $url, 0, $scheme_pos );
		$rest   = substr( $url, $scheme_pos + 3 );
		if ( '' === $rest ) {
			return array();
		}

		$host_end = strlen( $rest );
		foreach ( array( '/', '?', '#' ) as $marker ) {
			$pos = strpos( $rest, $marker );
			if ( false !== $pos && $pos < $host_end ) {
				$host_end = $pos;
			}
		}

		$authority = substr( $rest, 0, $host_end );
		$tail      = substr( $rest, $host_end );
		$host      = $authority;
		$port      = null;
		if ( false !== strpos( $authority, ':' ) ) {
			$authority_parts = explode( ':', $authority, 2 );
			$host            = $authority_parts[0];
			$port            = isset( $authority_parts[1] ) && is_numeric( $authority_parts[1] ) ? (int) $authority_parts[1] : null;
		}

		$fragment = null;
		$hash_pos = strpos( $tail, '#' );
		if ( false !== $hash_pos ) {
			$fragment = substr( $tail, $hash_pos + 1 );
			$tail     = substr( $tail, 0, $hash_pos );
		}

		$query = null;
		$query_pos = strpos( $tail, '?' );
		if ( false !== $query_pos ) {
			$query = substr( $tail, $query_pos + 1 );
			$path  = substr( $tail, 0, $query_pos );
		} else {
			$path = $tail;
		}

		return array(
			'scheme'   => $scheme,
			'host'     => $host,
			'port'     => $port,
			'path'     => $path,
			'query'    => $query,
			'fragment' => $fragment,
		);
	}

	private static function encode_url_path( string $path ): string {
		$segments = explode( '/', $path );
		$segments = array_map(
			static fn( string $segment ): string => rawurlencode( rawurldecode( $segment ) ),
			$segments
		);

		return implode( '/', $segments );
	}

	private static function encode_url_query( string $query ): string {
		parse_str( $query, $params );
		if ( empty( $params ) ) {
			return rawurlencode( rawurldecode( $query ) );
		}

		return http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Persist an affiliate link received from the panel into the local mirror table.
	 *
	 * @param string $product_id   Internal Kalahamoon product ID.
	 * @param string $cloaked_url  The panel's /go/:slug URL to embed in markup.
	 * @param string $kalahamoon_id     The AffiliateLink.id on the panel.
	 * @param string $provider     e.g. 'bakalahamoon'.
	 * @param array  $extra        Optional: slug, destination_url, base_tracking_url, campaign_title.
	 */
	public static function persist_panel_link(
		string $product_id,
		string $cloaked_url,
		string $kalahamoon_id,
		string $provider = 'bakalahamoon',
		array $extra = array()
	): void {
		global $wpdb;
		$table = $wpdb->prefix . 'kalahamoon_affiliate_links';

		$fields = array(
			'kalahamoon_short_url' => $cloaked_url,
			'kalahamoon_link_id'   => $kalahamoon_id,
			'provider'             => $provider,
			'status'               => 'active',
			'synced_at'            => current_time( 'mysql' ),
		);

		// Only persist optional columns when the panel supplied them so the
		// local /go/{slug} cloaker can resolve and rebuild links offline.
		foreach ( array( 'slug', 'destination_url', 'base_tracking_url', 'campaign_title' ) as $key ) {
			if ( isset( $extra[ $key ] ) && '' !== $extra[ $key ] ) {
				$fields[ $key ] = $extra[ $key ];
			}
		}

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE product_id = %s LIMIT 1",
			$product_id
		) );

		if ( $existing ) {
			$wpdb->update( $table, $fields, array( 'product_id' => $product_id ) );
		} else {
			$fields['product_id'] = $product_id;
			$wpdb->insert( $table, $fields );
		}
	}

	/**
	 * Fallback: add UTM parameters to a URL without any affiliate credentials.
	 * Used only when the product has no affiliate link provisioned and no panel connection.
	 *
	 * @param string $destination_url  Raw product URL.
	 * @return string
	 */
	public static function build_custom( string $destination_url ): string {
		return add_query_arg( array(
			'utm_source'   => 'kalahamoon',
			'utm_medium'   => 'wordpress',
			'utm_campaign' => get_option( 'kalahamoon_organization_slug', 'kalahamoon' ),
		), $destination_url );
	}
}
