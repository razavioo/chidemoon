<?php
/**
 * KalahamoonAuth — OAuth 2.0 client for WordPress plugin.
 *
 * Handles authorization URL generation, code exchange, token refresh,
 * and revocation. Uses client_secret_post authentication (confidential client).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Auth {

	const CLIENT_ID     = 'kalahamoon_wordpress';
	const STATE_TRANSIENT = 'kalahamoon_oauth_state';
	const STATE_TTL       = 900; // 15 minutes

	/**
	 * Projection consumers use a dedicated, least-privilege connector identity.
	 * The value remains configurable locally so the catalog API stays unaware of
	 * any individual WordPress publication.
	 */
	private static function client_id(): string {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			// A projection consumer must receive a connector provisioned for this
			// installation. Falling back to a memorable shared client ID would let
			// an unconfigured site attempt to impersonate another consumer.
			$default = defined( 'KALAHAMOON_CATALOG_CONNECTOR_CLIENT_ID' )
				? trim( (string) KALAHAMOON_CATALOG_CONNECTOR_CLIENT_ID )
				: '';
			return (string) apply_filters( 'kalahamoon_oauth_client_id', $default );
		}

		$default = self::CLIENT_ID;
		return (string) apply_filters( 'kalahamoon_oauth_client_id', $default );
	}

	private static function requested_scopes(): string {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			return 'catalog:read catalog:delivery:ack';
		}
		return 'profile products:read products:write analytics:read leads:write affiliate:read affiliate:write ai:product_compare ai:product_content ai:product_research ai:image_generate';
	}

	/**
	 * Get the Kalahamoon API base URL.
	 */
	private static function get_base_url(): string {
		$default_url = function_exists( 'kalahamoon_default_api_url' ) ? kalahamoon_default_api_url() : 'https://app.kalahamoon.com';
		return rtrim( get_option( 'kalahamoon_api_url', $default_url ), '/' );
	}

	/**
	 * Use the Docker service route for requests made by WordPress itself. The
	 * browser still needs the public URL from get_base_url() for authorization.
	 */
	private static function get_service_base_url(): string {
		$internal_url = defined( 'KALAHAMOON_INTERNAL_API_URL' ) ? trim( (string) KALAHAMOON_INTERNAL_API_URL ) : '';
		$parts        = '' === $internal_url ? false : wp_parse_url( $internal_url );
		if ( is_array( $parts ) && ! empty( $parts['host'] ) && in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
			return rtrim( $internal_url, '/' );
		}

		return self::get_base_url();
	}

	/**
	 * Get the client secret from wp-config.php constant or option.
	 */
	private static function get_client_secret(): string {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			if ( defined( 'KALAHAMOON_CATALOG_CONNECTOR_CLIENT_SECRET' ) ) {
				return (string) KALAHAMOON_CATALOG_CONNECTOR_CLIENT_SECRET;
			}

			// Connector credentials are host configuration, never WordPress option
			// data. A missing secret must fail visibly instead of preserving a
			// recoverable database copy or widening this site's authority.
			return '';
		}

		if ( defined( 'KALAHAMOON_CLIENT_SECRET' ) ) {
			return KALAHAMOON_CLIENT_SECRET;
		}
		return get_option( 'kalahamoon_oauth_client_secret', '' );
	}

	/**
	 * Get the OAuth callback URL for this WordPress site.
	 */
	public static function get_callback_url(): string {
		$callback_url = rest_url( 'kalahamoon/v1/oauth/callback' );
		if ( false !== strpos( $callback_url, '/index.php?rest_route=/' ) ) {
			$callback_url = str_replace( '/index.php?rest_route=/', '/?rest_route=/', $callback_url );
		}
		return $callback_url;
	}

	/**
	 * Connector mode fails visibly until both halves of its dedicated OAuth
	 * credential are installed. A normal WordPress integration keeps its
	 * historical public-client setup unchanged.
	 */
	public static function has_catalog_connector_configuration(): bool {
		if ( ! self::is_catalog_consumer() ) {
			return true;
		}

		return '' !== self::client_id() && '' !== self::get_client_secret();
	}

	/**
	 * Build the authorization URL and store the state transient.
	 */
	public static function get_authorization_url(): string {
		if ( self::is_catalog_consumer() && ! self::has_catalog_connector_configuration() ) {
			return '';
		}

		$state = wp_generate_password( 40, false );
		set_transient( self::STATE_TRANSIENT, $state, self::STATE_TTL );

		$callback_url = self::get_callback_url();
		if ( false !== strpos( $callback_url, '/index.php?rest_route=/' ) ) {
			$callback_url = str_replace( '/index.php?rest_route=/', '/?rest_route=/', $callback_url );
		}

		$params = array(
			'client_id'     => self::client_id(),
			'redirect_uri'  => $callback_url,
			'response_type' => 'code',
			'scope'         => self::requested_scopes(),
			'state'         => $state,
		);

		return self::get_base_url() . '/oauth/authorize?' . http_build_query( $params );
	}

	/**
	 * Handle the OAuth callback: verify state, exchange code for tokens.
	 */
	public static function handle_callback( string $code, string $state ): bool {
		// Verify state
		$stored_state = get_transient( self::STATE_TRANSIENT );
		delete_transient( self::STATE_TRANSIENT );

		if ( ! $stored_state || ! hash_equals( $stored_state, $state ) ) {
			return false;
		}

		// Exchange code for tokens
		$response = wp_remote_post( self::get_service_base_url() . '/api/oauth2/token', array(
			'timeout' => 15,
			'body'    => array(
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'redirect_uri'  => self::get_callback_url(),
				'client_id'     => self::client_id(),
				'client_secret' => self::get_client_secret(),
			),
		) );

		if ( is_wp_error( $response ) ) return false;

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 ) return false;

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) return false;

		// Store tokens
		$token_data = array(
			'access_token'  => $body['access_token'],
			'refresh_token' => $body['refresh_token'],
			'expires_at'    => time() + (int) $body['expires_in'],
			'scopes'        => $body['scope'] ?? '',
			'client_id'     => self::client_id(),
			'connected_at'  => gmdate( 'Y-m-d H:i:s' ),
		);
		if ( self::is_catalog_consumer() && ! self::has_catalog_connector_grant( $token_data ) ) {
			// Do not replace a limited connector with a historical panel token just
			// because the authorization server returned a successful exchange.
			return false;
		}

		// A catalog connector is intentionally not a panel session. Avoid a
		// profile/userinfo request that would require authority beyond its two
		// catalog scopes.
		$user_info = class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled()
			? null
			: self::fetch_user_info( $body['access_token'] );
		if ( $user_info ) {
			$token_data['user_email'] = $user_info['email'] ?? '';
			$token_data['org_name']   = $user_info['organization_name'] ?? '';
			$token_data['org_slug']   = $user_info['organization_slug'] ?? '';

			// Auto-set organization slug if not configured
			if ( ! empty( $user_info['organization_slug'] ) && empty( get_option( 'kalahamoon_organization_slug' ) ) ) {
				update_option( 'kalahamoon_organization_slug', $user_info['organization_slug'] );
			}
		}

		Kalahamoon_Token_Store::save( $token_data );
		update_option( 'kalahamoon_connected', true );
		do_action( 'kalahamoon_connection_state_changed' );

		return true;
	}

	/**
	 * Refresh the access token using the stored refresh token.
	 */
	public static function refresh_tokens(): bool {
		$data = Kalahamoon_Token_Store::get();
		if ( ! $data || empty( $data['refresh_token'] ) ) return false;

		$response = wp_remote_post( self::get_service_base_url() . '/api/oauth2/token', array(
			'timeout' => 15,
			'body'    => array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $data['refresh_token'],
				'client_id'     => self::client_id(),
				'client_secret' => self::get_client_secret(),
			),
		) );

		if ( is_wp_error( $response ) ) return false;

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 ) {
			// Refresh failed — clear tokens
			Kalahamoon_Token_Store::clear();
			update_option( 'kalahamoon_connected', false );
			do_action( 'kalahamoon_connection_state_changed' );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) return false;

		// Merge with existing data (preserve user info)
		$data['access_token']  = $body['access_token'];
		$data['refresh_token'] = $body['refresh_token'];
		$data['expires_at']    = time() + (int) $body['expires_in'];
		$data['scopes']        = $body['scope'] ?? $data['scopes'];
		if ( self::is_catalog_consumer() && ! self::has_catalog_connector_grant( $data ) ) {
			Kalahamoon_Token_Store::clear();
			update_option( 'kalahamoon_connected', false );
			do_action( 'kalahamoon_connection_state_changed' );
			return false;
		}

		Kalahamoon_Token_Store::save( $data );
		do_action( 'kalahamoon_connection_state_changed' );
		return true;
	}

	/**
	 * Revoke tokens on the server and clear local storage.
	 */
	public static function revoke_tokens(): void {
		$data = Kalahamoon_Token_Store::get();

		if ( $data && ! empty( $data['access_token'] ) ) {
			wp_remote_post( self::get_service_base_url() . '/api/oauth2/revoke', array(
				'timeout' => 10,
				'body'    => array(
					'token'     => $data['access_token'],
					'client_id' => self::client_id(),
				),
			) );
		}

		if ( $data && ! empty( $data['refresh_token'] ) ) {
			wp_remote_post( self::get_service_base_url() . '/api/oauth2/revoke', array(
				'timeout' => 10,
				'body'    => array(
					'token'           => $data['refresh_token'],
					'token_type_hint' => 'refresh_token',
					'client_id'       => self::client_id(),
				),
			) );
		}

		Kalahamoon_Token_Store::clear();
		update_option( 'kalahamoon_connected', false );
		do_action( 'kalahamoon_connection_state_changed' );
	}

	/**
	 * Check if we're connected via OAuth.
	 */
	public static function is_connected(): bool {
		if ( ! Kalahamoon_Token_Store::is_connected() ) {
			return false;
		}
		if ( ! self::is_catalog_consumer() ) {
			return true;
		}

		$data = Kalahamoon_Token_Store::get();
		// A stored grant alone is not deployment-ready: consumer sync needs a
		// usable bearer token, including a successful refresh near expiry.
		return is_array( $data )
			&& self::has_catalog_connector_grant( $data )
			&& null !== Kalahamoon_Token_Store::get_access_token();
	}

	private static function is_catalog_consumer(): bool {
		return class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled();
	}

	/**
	 * A token left behind by the former multi-purpose integration must never
	 * become a catalog credential merely because it has not expired yet.
	 */
	private static function has_catalog_connector_grant( array $data ): bool {
		$raw_scopes = $data['scopes'] ?? '';
		$scopes     = is_array( $raw_scopes ) ? $raw_scopes : preg_split( '/[\s,]+/', (string) $raw_scopes, -1, PREG_SPLIT_NO_EMPTY );
		$scopes     = is_array( $scopes ) ? array_map( 'strval', $scopes ) : array();
		$client_id  = (string) ( $data['client_id'] ?? '' );

		return hash_equals( self::client_id(), $client_id )
			&& 2 === count( $scopes )
			&& in_array( 'catalog:read', $scopes, true )
			&& in_array( 'catalog:delivery:ack', $scopes, true );
	}

	/**
	 * Fetch user info from the userinfo endpoint.
	 */
	private static function fetch_user_info( string $access_token ): ?array {
		$response = wp_remote_get( self::get_service_base_url() . '/api/oauth2/userinfo', array(
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
			),
		) );

		if ( is_wp_error( $response ) ) return null;
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) return null;

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
