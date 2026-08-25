<?php
/**
 * KalahamoonAuth — Encrypted token storage for WordPress.
 *
 * Tokens are stored encrypted in wp_options using openssl_encrypt
 * with a key derived from AUTH_KEY + AUTH_SALT.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Token_Store {

	const OPTION_KEY = 'kalahamoon_oauth_tokens';
	const CIPHER     = 'aes-256-cbc';

	/**
	 * Get the encryption key (first 32 bytes of AUTH_KEY . AUTH_SALT).
	 */
	private static function get_key(): string {
		return substr( hash( 'sha256', AUTH_KEY . AUTH_SALT, true ), 0, 32 );
	}

	/**
	 * Encrypt a string.
	 */
	private static function encrypt( string $plaintext ): string {
		$iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::CIPHER ) );
		$encrypted = openssl_encrypt( $plaintext, self::CIPHER, self::get_key(), OPENSSL_RAW_DATA, $iv );
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt a string.
	 */
	private static function decrypt( string $ciphertext ): ?string {
		$raw = base64_decode( $ciphertext, true );
		if ( false === $raw ) return null;

		$iv_len = openssl_cipher_iv_length( self::CIPHER );
		$iv     = substr( $raw, 0, $iv_len );
		$data   = substr( $raw, $iv_len );

		$result = openssl_decrypt( $data, self::CIPHER, self::get_key(), OPENSSL_RAW_DATA, $iv );
		return false === $result ? null : $result;
	}

	/**
	 * Store token data.
	 */
	public static function save( array $data ): void {
		$json = wp_json_encode( $data );
		update_option( self::OPTION_KEY, self::encrypt( $json ) );
	}

	/**
	 * Get stored token data.
	 */
	public static function get(): ?array {
		$encrypted = get_option( self::OPTION_KEY, '' );
		if ( empty( $encrypted ) ) return null;

		$json = self::decrypt( $encrypted );
		if ( null === $json ) return null;

		$data = json_decode( $json, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Get a valid access token. Triggers refresh if expired.
	 */
	public static function get_access_token(): ?string {
		$data = self::get();
		if ( ! $data || empty( $data['access_token'] ) ) return null;

		// Check if token is still valid (with 5 min buffer)
		$expires_at = $data['expires_at'] ?? 0;
		if ( time() < ( $expires_at - 300 ) ) {
			return $data['access_token'];
		}

		// Try to refresh
		if ( ! empty( $data['refresh_token'] ) ) {
			$refreshed = Kalahamoon_Auth::refresh_tokens();
			if ( $refreshed ) {
				$new_data = self::get();
				return $new_data['access_token'] ?? null;
			}
		}

		return null;
	}

	/**
	 * Check if we have stored tokens.
	 */
	public static function is_connected(): bool {
		$data = self::get();
		return ! empty( $data['access_token'] );
	}

	/**
	 * Get stored user info (email, org name, etc.).
	 */
	public static function get_user_info(): ?array {
		$data = self::get();
		if ( ! $data ) return null;

		return array(
			'email'     => $data['user_email'] ?? '',
			'org_name'  => $data['org_name'] ?? '',
			'org_slug'  => $data['org_slug'] ?? '',
			'scopes'    => $data['scopes'] ?? '',
			'connected_at' => $data['connected_at'] ?? '',
		);
	}

	/**
	 * Clear all stored tokens.
	 */
	public static function clear(): void {
		delete_option( self::OPTION_KEY );
	}
}
