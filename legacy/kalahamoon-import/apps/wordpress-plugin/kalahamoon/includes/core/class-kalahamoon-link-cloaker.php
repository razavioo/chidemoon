<?php
/**
 * Pretty URL redirects: /go/{slug} → affiliate destination.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Link_Cloaker {

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_redirect' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
	}

	public static function add_rewrite_rules(): void {
		add_rewrite_rule( '^go/([^/]+)/?$', 'index.php?kalahamoon_go=$matches[1]', 'top' );
	}

	public static function add_query_vars( array $vars ): array {
		$vars[] = 'kalahamoon_go';
		return $vars;
	}

	public static function handle_redirect(): void {
		$slug = get_query_var( 'kalahamoon_go' );
		if ( empty( $slug ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'kalahamoon_affiliate_links';
		$slug_variants = array_values(
			array_unique(
				array_merge(
					self::lookup_slug_variants( (string) $slug ),
					self::lookup_slug_variants( self::slug_from_request_uri() )
				)
			)
		);
		$placeholders  = implode( ', ', array_fill( 0, count( $slug_variants ), '%s' ) );

		$link = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE slug IN ({$placeholders}) AND status = 'active' LIMIT 1",
				$slug_variants
			),
			ARRAY_A
		);

		if ( ! $link ) {
			status_header( 404 );
			nocache_headers();
			printf(
				'<h1 dir="%s" lang="%s">%s</h1>',
				esc_attr( Kalahamoon_RTL::direction() ),
				esc_attr( Kalahamoon_RTL::language() ),
				esc_html__( 'Link not found', 'kalahamoon' )
			);
			exit;
		}

		// Resolve the final affiliate URL. Prefer the panel-issued cloaked URL
		// (the panel performs provider tracking + click attribution); otherwise
		// rebuild locally from the destination + provider tracking URL.
		$panel_url = (string) ( $link['kalahamoon_short_url'] ?? '' );
		if ( Kalahamoon_Link_Builder::is_clickable_url( $panel_url ) && ! preg_match( '#^/go/#', $panel_url ) ) {
			$destination = $panel_url;
		} else {
			$destination = Kalahamoon_Link_Builder::build(
				(string) ( $link['destination_url'] ?? '' ),
				(string) ( $link['base_tracking_url'] ?? '' ),
				(string) ( $link['provider'] ?? '' )
			);
		}

		$destination = Kalahamoon_Link_Builder::normalize_clickable_url( $destination );
		if ( '' === $destination ) {
			status_header( 404 );
			nocache_headers();
			printf(
				'<h1 dir="%s" lang="%s">%s</h1>',
				esc_attr( Kalahamoon_RTL::direction() ),
				esc_attr( Kalahamoon_RTL::language() ),
				esc_html__( 'Link not found', 'kalahamoon' )
			);
			exit;
		}

		// Log click before redirect
		Kalahamoon_Click_Tracker::log_click( array(
			'link_id'    => (int) $link['id'],
			'product_id' => $link['product_id'],
		) );

		// Update click count
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET clicks = clicks + 1 WHERE id = %d",
			$link['id']
		) );

		$redirect_type = (int) get_option( 'kalahamoon_redirect_type', 301 );
		nocache_headers();
		wp_redirect( $destination, $redirect_type );
		exit;
	}

	/**
	 * Create a cloaked URL for a product/link.
	 */
	public static function create_slug( string $title ): string {
		$slug = sanitize_title( $title );
		// Ensure uniqueness
		global $wpdb;
		$table    = $wpdb->prefix . 'kalahamoon_affiliate_links';
		$original = $slug;
		$counter  = 1;

		while ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE slug = %s", $slug ) ) > 0 ) {
			$slug = $original . '-' . $counter;
			$counter++;
		}

		return $slug;
	}

	private static function lookup_slug_variants( string $slug ): array {
		$slug    = trim( $slug, " \t\n\r\0\x0B/" );
		$decoded = rawurldecode( $slug );

		return array_values(
			array_unique(
				array_filter(
					array(
						$slug,
						strtolower( $slug ),
						$decoded,
						rawurlencode( $decoded ),
						strtolower( rawurlencode( $decoded ) ),
						sanitize_title( $decoded ),
					),
					static fn( string $candidate ): bool => '' !== $candidate
				)
			)
		);
	}

	private static function slug_from_request_uri(): string {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || ! preg_match( '#/go/([^/]+)/?$#', $path, $matches ) ) {
			return '';
		}

		return (string) $matches[1];
	}
}
