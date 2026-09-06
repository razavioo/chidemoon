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

	/**
	 * Structured product-enrichment proposal (title, descriptions, facts).
	 *
	 * @param array<string, mixed> $job
	 * @param array<int, array<string, mixed>> $evidence
	 * @return array<string, mixed>|WP_Error
	 */
	public function generate_enrichment( array $job, array $evidence ): array|WP_Error;

	/**
	 * Vision analysis for hotspot proposals. Providers that cannot do vision
	 * must return a WP_Error with code chidemoon_ai_vision_unsupported so the
	 * caller can fall back to a deterministic heuristic layout.
	 *
	 * @param int[] $attachment_ids
	 * @param array<int, array<string, mixed>> $products Product context (id, name).
	 * @return array<string, mixed>|WP_Error
	 */
	public function analyze_image( array $attachment_ids, array $products, string $prompt ): array|WP_Error;

	public function supports_vision(): bool;

	public function text_model(): string;

	public function vision_model(): string;

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
			self::configured_text_model(),
			self::configured_image_model(),
			max( 10, min( 120, self::configured_timeout() ) ),
			array(
				'vision_model'  => self::configured_vision_model(),
				'image_size'    => self::configured_image_size(),
				'image_quality' => self::configured_image_quality(),
				'search_mode'   => self::configured_search_mode(),
			)
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

	private static function configured_text_model(): string {
		if ( class_exists( 'Chidemoon_AI_Settings' ) ) {
			$model = trim( Chidemoon_AI_Settings::get_string( 'text_model' ) );
			if ( '' !== $model ) {
				return $model;
			}
		}

		return self::environment_string( 'CHIDEMOON_AI_TEXT_MODEL', 'gpt-4.1-mini' );
	}

	private static function configured_vision_model(): string {
		if ( class_exists( 'Chidemoon_AI_Settings' ) ) {
			return Chidemoon_AI_Settings::effective_vision_model();
		}

		$vision = trim( (string) getenv( 'CHIDEMOON_AI_VISION_MODEL' ) );
		return '' !== $vision ? substr( sanitize_text_field( $vision ), 0, 128 ) : self::configured_text_model();
	}

	private static function configured_image_model(): string {
		if ( class_exists( 'Chidemoon_AI_Settings' ) ) {
			$model = trim( Chidemoon_AI_Settings::get_string( 'image_model' ) );
			if ( '' !== $model ) {
				return $model;
			}
		}

		return self::environment_string( 'CHIDEMOON_AI_IMAGE_MODEL', 'gpt-image-2' );
	}

	private static function configured_image_size(): string {
		if ( class_exists( 'Chidemoon_AI_Settings' ) ) {
			return Chidemoon_AI_Settings::get_string( 'image_size' );
		}

		return self::environment_string( 'CHIDEMOON_AI_IMAGE_SIZE', '1024x1024' );
	}

	private static function configured_image_quality(): string {
		if ( class_exists( 'Chidemoon_AI_Settings' ) ) {
			return Chidemoon_AI_Settings::get_string( 'image_quality' );
		}

		return self::environment_string( 'CHIDEMOON_AI_IMAGE_QUALITY', 'medium' );
	}

	private static function configured_search_mode(): string {
		if ( class_exists( 'Chidemoon_AI_Settings' ) ) {
			return Chidemoon_AI_Settings::get_string( 'search_mode' );
		}

		return self::environment_string( 'CHIDEMOON_AI_SEARCH_MODE', 'free_only' );
	}

	private static function configured_timeout(): int {
		if ( class_exists( 'Chidemoon_AI_Settings' ) ) {
			return Chidemoon_AI_Settings::get_int( 'provider_timeout' );
		}

		return self::environment_int( 'CHIDEMOON_AI_PROVIDER_TIMEOUT', 60 );
	}

	private static function environment_int( string $name, int $default ): int {
		$value = getenv( $name );
		return false === $value || ! is_numeric( $value ) ? $default : (int) $value;
	}
}
