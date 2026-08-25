<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_API_Client {

	private string $base_url;
	private string $api_key;

	public function __construct() {
		$this->base_url = $this->resolve_api_base_url( $this->get_public_base_url() );
		$this->api_key  = get_option( 'kalahamoon_api_key', '' );
	}

	/**
	 * Browser-facing URLs remain public for OAuth and image links, while server
	 * requests may use the internal Compose service route.
	 */
	public function get_public_base_url(): string {
		$default_url = function_exists( 'kalahamoon_default_api_url' ) ? kalahamoon_default_api_url() : 'https://app.kalahamoon.com';
		return rtrim( (string) get_option( 'kalahamoon_api_url', $default_url ), '/' );
	}

	public function get_service_base_url(): string {
		return $this->base_url;
	}

	/**
	 * OAuth redirects must use the public panel URL, but a co-located WordPress
	 * install should use the private Docker route for server-to-server requests.
	 */
	private function resolve_api_base_url( string $default_url ): string {
		$internal_url = defined( 'KALAHAMOON_INTERNAL_API_URL' ) ? trim( (string) KALAHAMOON_INTERNAL_API_URL ) : '';
		$parts        = '' === $internal_url ? false : wp_parse_url( $internal_url );
		if ( is_array( $parts ) && ! empty( $parts['host'] ) && in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
			return rtrim( $internal_url, '/' );
		}

		return rtrim( (string) get_option( 'kalahamoon_api_url', $default_url ), '/' );
	}

	/**
	 * Check if the plugin is connected to Kalahamoon (via OAuth or legacy API key).
	 */
	public function is_connected(): bool {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			// The projection connector must never fall back to the old API-key
			// connection, because that credential may carry unrelated authority.
			return Kalahamoon_Auth::is_connected();
		}
		if ( Kalahamoon_Auth::is_connected() ) {
			return true;
		}
		$connected = ! empty( $this->api_key ) && (bool) get_option( 'kalahamoon_connected', false );
		return (bool) apply_filters( 'kalahamoon_api_client_is_connected', $connected, $this );
	}

	/**
	 * Make a GET request to the Kalahamoon API.
	 *
	 * @param string $endpoint  Path relative to base URL (e.g. "/api/public/products").
	 * @param array  $params    Query parameters.
	 * @param int    $cache_ttl Cache TTL in seconds (0 = no cache).
	 * @return array|WP_Error
	 */
	public function get( string $endpoint, array $params = array(), int $cache_ttl = 0 ) {
		$auth      = $this->auth_token( $endpoint );
		$cache_key = $this->cache_key( $endpoint, $params, $auth );

		if ( $cache_ttl > 0 ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$url = $this->base_url . $endpoint;
		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_get( $url, array(
			'headers' => $this->headers( $auth, $endpoint ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'kalahamoon_api_error',
				$body['message'] ?? "API returned status {$code}",
				array( 'status' => $code, 'body' => $body )
			);
		}

		if ( $cache_ttl > 0 && $body ) {
			set_transient( $cache_key, $body, $cache_ttl );
		}

		return $body;
	}

	/**
	 * Make a POST request to the Kalahamoon API.
	 *
	 * @param string $endpoint
	 * @param array  $data
	 * @return array|WP_Error
	 */
	public function post( string $endpoint, array $data = array(), array $extra_headers = array() ) {
		$body = wp_json_encode( $data );
		if ( ! is_string( $body ) || '' === $body ) {
			return new WP_Error( 'kalahamoon_api_request_invalid', __( 'The request could not be encoded.', 'kalahamoon' ) );
		}

		return $this->post_json( $endpoint, $body, $extra_headers );
	}

	/**
	 * Submit a JSON body that was serialized before signing. Keeping the body
	 * unchanged between HMAC calculation and transport lets the receiver verify
	 * proof of delivery against the precise bytes it received.
	 */
	private function post_json( string $endpoint, string $body, array $extra_headers = array(), ?string $auth = null ) {
		$response = wp_remote_post( $this->base_url . $endpoint, array(
			'headers' => array_merge( $this->headers( $auth, $endpoint ), array(
				'Content-Type' => 'application/json',
			), $extra_headers ),
			'body'    => $body,
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'kalahamoon_api_error',
				$body['message'] ?? "API returned status {$code}",
				array( 'status' => $code, 'body' => $body )
			);
		}

		return $body;
	}

	public function patch( string $endpoint, array $data = array() ) {
		$response = wp_remote_request( $this->base_url . $endpoint, array(
			'method'  => 'PATCH',
			'headers' => array_merge( $this->headers( null, $endpoint ), array( 'Content-Type' => 'application/json' ) ),
			'body'    => wp_json_encode( $data ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'kalahamoon_api_error', $body['message'] ?? "API returned status {$code}", array( 'status' => $code, 'body' => $body ) );
		}

		return $body;
	}

	/**
	 * Create a single affiliate link on the panel.
	 *
	 * @param array $payload  Keys: productUrl (required), productId, listingId, provider, platform, campaignTitle.
	 * @return array|WP_Error  Response data including cloakedUrl and slug.
	 */
	public function create_affiliate_link( array $payload ) {
		return $this->post( '/api/public/affiliate-links', $payload );
	}

	/**
	 * Batch-create affiliate links on the panel.
	 *
	 * @param array $items        Array of link payloads (same shape as create_affiliate_link).
	 * @param bool  $skip_existing Skip links that already exist (default true).
	 * @return array|WP_Error
	 */
	public function batch_create_affiliate_links( array $items, bool $skip_existing = true ) {
		return $this->post( '/api/public/affiliate-links/batch', array(
			'links'        => $items,
			'skipExisting' => $skip_existing,
		) );
	}

	/**
	 * List affiliate links from the panel.
	 *
	 * @param array $params  Optional filters: provider, platform, productId, status, limit, cursor.
	 * @return array|WP_Error
	 */
	public function list_affiliate_links( array $params = array() ) {
		return $this->get( '/api/public/affiliate-links', $params );
	}

	/**
	 * Fetch click/conversion/revenue metrics for a batch of panel link IDs.
	 *
	 * @param string[] $ids Panel AffiliateLink IDs (max 100).
	 * @return array|WP_Error  Response with a `metrics` array.
	 */
	public function get_affiliate_metrics( array $ids ) {
		$ids = array_values( array_filter( array_map( 'strval', $ids ) ) );
		if ( empty( $ids ) ) {
			return array( 'metrics' => array() );
		}
		return $this->get( '/api/public/affiliate-metrics', array(
			'ids' => implode( ',', array_slice( $ids, 0, 100 ) ),
		) );
	}

	/**
	 * Get a safe Digikala Open API capability snapshot from Kalahamoon.
	 *
	 * The Digikala seller token remains in Kalahamoon and is never sent to WordPress.
	 *
	 * @return array|WP_Error
	 */
	public function get_digikala_capabilities() {
		return $this->get( '/api/public/digikala/capabilities', array(), 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Generic catalog integration endpoints are intentionally separate from the
	 * legacy public-products API. A projection consumer receives only published
	 * catalog data and has no product-write capability.
	 */
	public function get_catalog_capabilities() {
		return $this->get( '/api/integrations/catalog/v1/capabilities', array(), 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * The v1 endpoint currently returns a complete accepted snapshot even when a
	 * cursor is supplied. The locally active identity is sent alongside it so
	 * Kalahamoon can authoritatively account for cards this consumer must remove
	 * even when a previous acknowledgement was interrupted.
	 */
	public function get_catalog_snapshot( string $cursor = '', array $active_snapshot = array() ) {
		$params = '' === trim( $cursor ) ? array() : array( 'cursor' => $cursor );
		$active_id = trim( (string) ( $active_snapshot['id'] ?? '' ) );
		$active_revision = trim( (string) ( $active_snapshot['revision'] ?? '' ) );
		if ( '' !== $active_id && '' !== $active_revision ) {
			$params['activeSnapshotId']       = $active_id;
			$params['activeSnapshotRevision'] = $active_revision;
		}
		return $this->get( '/api/integrations/catalog/v1/snapshot', $params );
	}

	/**
	 * Delivery acknowledgements prove a locally active revision. They cannot
	 * modify catalog items, offers, collections, or publication state.
	 */
	public function acknowledge_catalog_delivery( array $receipt ) {
		$endpoint = '/api/integrations/catalog/v1/delivery-receipts';
		$body     = wp_json_encode( $receipt );
		$token    = $this->auth_token( $endpoint );
		if ( ! is_string( $body ) || '' === $body ) {
			return new WP_Error( 'kalahamoon_catalog_receipt_invalid', __( 'The delivery receipt could not be encoded.', 'kalahamoon' ) );
		}
		if ( '' === trim( $token ) ) {
			return new WP_Error( 'kalahamoon_catalog_receipt_unauthenticated', __( 'The catalog connector has no active credential for delivery acknowledgement.', 'kalahamoon' ) );
		}

		return $this->post_json( $endpoint, $body, array(
			'X-Kalahamoon-Catalog-Signature' => 'sha256=' . hash_hmac( 'sha256', $body, $token ),
		), $token );
	}

	/**
	 * Report a bounded failure outcome for a snapshot this connector has already
	 * received. It uses the same acknowledgement scope and signature path, so a
	 * consumer cannot gain catalog-write authority by reporting an outage.
	 */
	public function report_catalog_delivery_failure( array $failure ) {
		$endpoint = '/api/integrations/catalog/v1/delivery-receipts';
		$body     = wp_json_encode( $failure );
		$token    = $this->auth_token( $endpoint );
		if ( ! is_string( $body ) || '' === $body ) {
			return new WP_Error( 'kalahamoon_catalog_failure_invalid', __( 'The catalog delivery failure could not be encoded.', 'kalahamoon' ) );
		}
		if ( '' === trim( $token ) ) {
			return new WP_Error( 'kalahamoon_catalog_failure_unauthenticated', __( 'The catalog connector has no active credential for delivery reporting.', 'kalahamoon' ) );
		}

		return $this->post_json( $endpoint, $body, array(
			'X-Kalahamoon-Catalog-Signature' => 'sha256=' . hash_hmac( 'sha256', $body, $token ),
		), $token );
	}

	/**
	 * Test the API connection.
	 *
	 * @return true|WP_Error
	 */
	public function test_connection() {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			$result = $this->get_catalog_capabilities();
			return is_wp_error( $result ) ? $result : true;
		}

		$endpoint = (string) apply_filters( 'kalahamoon_api_test_connection_endpoint', '/api/public/products', $this );
		$result = $this->get( $endpoint, array( 'limit' => '1' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	private function auth_token( string $endpoint = '' ): string {
		$token = Kalahamoon_Token_Store::get_access_token();
		if ( ! ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) ) {
			// Legacy installations can retain their API-key fallback. A catalog
			// receipt, however, must be tied to the dedicated Bearer credential.
			$token = $token ? $token : $this->api_key;
		}
		return (string) apply_filters( 'kalahamoon_api_client_auth_token', $token, $endpoint, $this );
	}

	/**
	 * Include an opaque credential fingerprint so a reconnect cannot reuse
	 * catalog data fetched for another organization.
	 */
	private function cache_key( string $endpoint, array $params, string $auth ): string {
		return 'kalahamoon_api_' . md5( $endpoint . wp_json_encode( $params ) . '|' . hash( 'sha256', $auth ) );
	}

	private function headers( ?string $auth = null, string $endpoint = '' ): array {
		$auth = null === $auth ? $this->auth_token( $endpoint ) : $auth;

		return array(
			'Authorization' => 'Bearer ' . $auth,
			'Accept'        => 'application/json',
			'User-Agent'    => 'KalahamoonWordPress/' . KALAHAMOON_VERSION,
		);
	}
}
