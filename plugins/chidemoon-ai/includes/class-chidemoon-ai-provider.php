<?php
/**
 * Provider contract and environment-only configuration factory.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Chidemoon_AI_Provider_Interface {
	public function name(): string;

	/**
	 * @param array<string, mixed> $job
	 * @param array<int, array<string, mixed>> $evidence
	 * @return array<string, mixed>|WP_Error
	 */
	public function generate_text( array $job, array $evidence ): array|WP_Error;

	/**
	 * @param array<string, mixed> $job
	 * @return array<string, mixed>|WP_Error
	 */
	public function generate_image( array $job ): array|WP_Error;

	public function text_model(): string;

	public function image_model(): string;
}

class Chidemoon_AI_Provider_Factory {
	/**
	 * A filter makes the provider testable without adding a WordPress option for
	 * credentials. Production configuration is always read from host env vars.
	 *
	 * @return Chidemoon_AI_Provider_Interface|WP_Error
	 */
	public static function create(): Chidemoon_AI_Provider_Interface|WP_Error {
		$override = apply_filters( 'chidemoon_ai_provider', null );
		if ( $override instanceof Chidemoon_AI_Provider_Interface ) {
			return $override;
		}

		$base_url = trim( (string) getenv( 'CHIDEMOON_AI_PROVIDER_BASE_URL' ) );
		$api_key  = trim( (string) getenv( 'CHIDEMOON_AI_API_KEY' ) );
		if ( '' === $base_url || '' === $api_key ) {
			return new WP_Error( 'chidemoon_ai_provider_not_configured', __( 'The Chidemoon AI provider is not configured on this host.', 'chidemoon-ai' ), array( 'status' => 503 ) );
		}

		if ( ! self::is_safe_provider_url( $base_url ) ) {
			return new WP_Error( 'chidemoon_ai_provider_url_invalid', __( 'The configured AI provider URL is not an approved public HTTPS URL.', 'chidemoon-ai' ), array( 'status' => 503 ) );
		}

		return new Chidemoon_AI_OpenAI_Compatible_Provider(
			rtrim( $base_url, '/' ),
			$api_key,
			self::environment_string( 'CHIDEMOON_AI_TEXT_MODEL', 'gpt-4.1-mini' ),
			self::environment_string( 'CHIDEMOON_AI_IMAGE_MODEL', 'gpt-image-2' ),
			max( 10, min( 120, self::environment_int( 'CHIDEMOON_AI_PROVIDER_TIMEOUT', 60 ) ) )
		);
	}

	public static function is_safe_provider_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}

		$host = strtolower( (string) $parts['host'] );
		if ( 'localhost' === $host || str_ends_with( $host, '.local' ) || filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		$addresses = gethostbynamel( $host );
		if ( false === $addresses || empty( $addresses ) ) {
			return false;
		}
		foreach ( $addresses as $address ) {
			if ( ! filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return false;
			}
		}

		return true;
	}

	private static function environment_string( string $name, string $default ): string {
		$value = trim( (string) getenv( $name ) );
		return '' === $value ? $default : substr( sanitize_text_field( $value ), 0, 128 );
	}

	private static function environment_int( string $name, int $default ): int {
		$value = getenv( $name );
		return false === $value || ! is_numeric( $value ) ? $default : (int) $value;
	}
}
