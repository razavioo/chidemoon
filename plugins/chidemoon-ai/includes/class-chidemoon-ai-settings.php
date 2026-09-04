<?php
/**
 * Non-secret AI configuration with env > option > default precedence.
 *
 * Secrets (provider base URL / API keys / search keys) are NEVER stored in
 * WordPress options and NEVER rendered into admin HTML. This class only
 * manages non-secret controls; secrets remain host environment variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_Settings {
	public const OPTION_PREFIX = 'chidemoon_ai_';

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields(): array {
		return array(
			'text_model'         => array(
				'default'   => 'gpt-4.1-mini',
				'maxlength' => 128,
				'sanitize'  => 'text',
			),
			'vision_model'       => array(
				'default'   => '',
				'maxlength' => 128,
				'sanitize'  => 'text',
				'help'      => 'Empty means reuse the text model for vision.',
			),
			'image_model'        => array(
				'default'   => 'gpt-image-2',
				'maxlength' => 128,
				'sanitize'  => 'text',
			),
			'image_size'         => array(
				'default'   => '1024x1024',
				'maxlength' => 16,
				'sanitize'  => 'size',
			),
			'image_quality'      => array(
				'default'   => 'medium',
				'maxlength' => 16,
				'sanitize'  => 'quality',
			),
			'provider_timeout'   => array(
				'default'   => 60,
				'maxlength' => 0,
				'sanitize'  => 'int_10_120',
			),
			'daily_limit'        => array(
				'default'   => 25,
				'maxlength' => 0,
				'sanitize'  => 'int_1_1000',
			),
			'monthly_limit'      => array(
				'default'   => 500,
				'maxlength' => 0,
				'sanitize'  => 'int_1_100000',
			),
			'monthly_budget'     => array(
				'default'   => 25.0,
				'maxlength' => 0,
				'sanitize'  => 'float',
			),
			'text_cost'          => array(
				'default'   => 0.02,
				'maxlength' => 0,
				'sanitize'  => 'float',
			),
			'comparison_cost'    => array(
				'default'   => 0.04,
				'maxlength' => 0,
				'sanitize'  => 'float',
			),
			'image_cost'         => array(
				'default'   => 0.06,
				'maxlength' => 0,
				'sanitize'  => 'float',
			),
			'look_cost'          => array(
				'default'   => 0.12,
				'maxlength' => 0,
				'sanitize'  => 'float',
			),
			'enrich_cost'        => array(
				'default'   => 0.04,
				'maxlength' => 0,
				'sanitize'  => 'float',
			),
			'evidence_max_age'   => array(
				'default'   => 90,
				'maxlength' => 0,
				'sanitize'  => 'int_1_3650',
			),
			'moderation_timeout' => array(
				'default'   => 30,
				'maxlength' => 0,
				'sanitize'  => 'int_10_120',
			),
			'search_mode'        => array(
				'default'   => 'free_only',
				'maxlength' => 24,
				'sanitize'  => 'search_mode',
			),
			'search_cache_hours' => array(
				'default'   => 24,
				'maxlength' => 0,
				'sanitize'  => 'int_1_168',
			),
			'search_max_results' => array(
				'default'   => 5,
				'maxlength' => 0,
				'sanitize'  => 'int_1_8',
			),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function env_map(): array {
		return array(
			'text_model'         => 'CHIDEMOON_AI_TEXT_MODEL',
			'vision_model'       => 'CHIDEMOON_AI_VISION_MODEL',
			'image_model'        => 'CHIDEMOON_AI_IMAGE_MODEL',
			'image_size'         => 'CHIDEMOON_AI_IMAGE_SIZE',
			'image_quality'      => 'CHIDEMOON_AI_IMAGE_QUALITY',
			'provider_timeout'   => 'CHIDEMOON_AI_PROVIDER_TIMEOUT',
			'daily_limit'        => 'CHIDEMOON_AI_DAILY_REQUEST_LIMIT',
			'monthly_limit'      => 'CHIDEMOON_AI_MONTHLY_REQUEST_LIMIT',
			'monthly_budget'     => 'CHIDEMOON_AI_MONTHLY_BUDGET',
			'text_cost'          => 'CHIDEMOON_AI_TEXT_ESTIMATED_COST',
			'comparison_cost'    => 'CHIDEMOON_AI_COMPARISON_ESTIMATED_COST',
			'image_cost'         => 'CHIDEMOON_AI_IMAGE_ESTIMATED_COST',
			'look_cost'          => 'CHIDEMOON_AI_LOOK_ESTIMATED_COST',
			'enrich_cost'        => 'CHIDEMOON_AI_ENRICH_ESTIMATED_COST',
			'evidence_max_age'   => 'CHIDEMOON_AI_EVIDENCE_MAX_AGE_DAYS',
			'moderation_timeout' => 'CHIDEMOON_AI_MODERATION_TIMEOUT',
			'search_mode'        => 'CHIDEMOON_AI_SEARCH_MODE',
			'search_cache_hours' => 'CHIDEMOON_AI_SEARCH_CACHE_HOURS',
			'search_max_results' => 'CHIDEMOON_AI_SEARCH_MAX_RESULTS',
		);
	}

	/**
	 * @param mixed $default
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$fields = self::fields();
		if ( ! isset( $fields[ $key ] ) ) {
			return $default;
		}
		$field_default = $fields[ $key ]['default'];

		$env_map = self::env_map();
		if ( isset( $env_map[ $key ] ) ) {
			$env_value = getenv( $env_map[ $key ] );
			if ( false !== $env_value && '' !== trim( (string) $env_value ) ) {
				$sanitized = self::sanitize( $key, (string) $env_value );
				if ( null !== $sanitized ) {
					return $sanitized;
				}
			}
		}

		$option_value = get_option( self::OPTION_PREFIX . $key, null );
		if ( null !== $option_value && '' !== $option_value ) {
			$sanitized = self::sanitize( $key, $option_value );
			if ( null !== $sanitized ) {
				return $sanitized;
			}
		}

		return null !== $default ? $default : $field_default;
	}

	public static function get_string( string $key ): string {
		return (string) self::get( $key, '' );
	}

	public static function get_int( string $key ): int {
		return (int) self::get( $key, 0 );
	}

	public static function get_float( string $key ): float {
		return (float) self::get( $key, 0.0 );
	}

	public static function option_name( string $key ): string {
		return self::OPTION_PREFIX . $key;
	}

	/**
	 * @return string[]
	 */
	public static function option_names(): array {
		return array_map( array( __CLASS__, 'option_name' ), array_keys( self::fields() ) );
	}

	public static function effective_vision_model(): string {
		$vision = trim( self::get_string( 'vision_model' ) );
		if ( '' !== $vision ) {
			return $vision;
		}

		return trim( self::get_string( 'text_model' ) );
	}

	public static function is_search_enabled(): bool {
		return 'off' !== self::get_string( 'search_mode' );
	}

	public static function search_has_key(): bool {
		$key = trim( (string) getenv( 'CHIDEMOON_AI_SEARCH_KEY' ) );
		if ( '' === $key ) {
			$key = trim( (string) getenv( 'TAVILY_API_KEY' ) );
		}
		if ( '' === $key ) {
			$key = trim( (string) getenv( 'BRAVE_SEARCH_KEY' ) );
		}

		return '' !== $key;
	}

	/**
	 * Only reports presence, never the value.
	 *
	 * @return array<string, bool|string>
	 */
	public static function secret_status(): array {
		$base = trim( (string) getenv( 'CHIDEMOON_AI_PROVIDER_BASE_URL' ) );
		$key  = trim( (string) getenv( 'CHIDEMOON_AI_API_KEY' ) );

		return array(
			'provider_configured'   => '' !== $base && '' !== $key,
			'base_host'             => '' !== $base ? (string) ( wp_parse_url( $base, PHP_URL_HOST ) ?: '' ) : '',
			'moderation_configured' => '' !== trim( (string) getenv( 'CHIDEMOON_AI_MODERATION_MODEL' ) ) && '' !== $base && '' !== $key,
			'search_key_present'    => self::search_has_key(),
		);
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	public static function sanitize( string $key, $value ) {
		$fields = self::fields();
		if ( ! isset( $fields[ $key ] ) ) {
			return null;
		}
		$rule = (string) $fields[ $key ]['sanitize'];

		switch ( $rule ) {
			case 'text':
				$text = sanitize_text_field( (string) $value );
				$text = substr( $text, 0, (int) $fields[ $key ]['maxlength'] );
				return '' !== $text ? $text : null;
			case 'size':
				$text    = sanitize_text_field( (string) $value );
				$allowed = array( '1024x1024', '1536x1024', '1024x1536', '1792x1024', '1024x1792' );
				return in_array( $text, $allowed, true ) ? $text : null;
			case 'quality':
				$text    = strtolower( sanitize_key( (string) $value ) );
				$allowed = array( 'low', 'medium', 'high', 'auto' );
				return in_array( $text, $allowed, true ) ? $text : null;
			case 'search_mode':
				$text    = strtolower( sanitize_key( (string) $value ) );
				$allowed = array( 'off', 'free_only', 'free_plus_key', 'model_native' );
				return in_array( $text, $allowed, true ) ? $text : null;
			case 'int_10_120':
				return is_numeric( $value ) ? max( 10, min( 120, (int) $value ) ) : null;
			case 'int_1_1000':
				return is_numeric( $value ) ? max( 1, min( 1000, (int) $value ) ) : null;
			case 'int_1_100000':
				return is_numeric( $value ) ? max( 1, min( 100000, (int) $value ) ) : null;
			case 'int_1_3650':
				return is_numeric( $value ) ? max( 1, min( 3650, (int) $value ) ) : null;
			case 'int_1_168':
				return is_numeric( $value ) ? max( 1, min( 168, (int) $value ) ) : null;
			case 'int_1_8':
				return is_numeric( $value ) ? max( 1, min( 8, (int) $value ) ) : null;
			case 'float':
				return is_numeric( $value ) ? max( 0, min( 10000, (float) $value ) ) : null;
		}

		return null;
	}

	public static function register(): void {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function register_settings(): void {
		foreach ( self::fields() as $key => $field ) {
			register_setting(
				'chidemoon_ai_settings',
				self::OPTION_PREFIX . $key,
				array(
					'type'              => in_array( $field['sanitize'], array( 'float' ), true ) ? 'number' : ( 0 === strpos( (string) $field['sanitize'], 'int_' ) ? 'integer' : 'string' ),
					'sanitize_callback' => static function ( $value ) use ( $key ) {
						$sanitized = Chidemoon_AI_Settings::sanitize( $key, $value );
						if ( null === $sanitized ) {
							$fields = Chidemoon_AI_Settings::fields();
							return $fields[ $key ]['default'];
						}

						return $sanitized;
					},
					'default'           => $field['default'],
				)
			);
		}
	}
}
