<?php
/**
 * Fail-closed text and image moderation for all provider-bound material.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_Moderation {
	/**
	 * @return true|WP_Error
	 */
	public static function validate_configuration(): true|WP_Error {
		$config = self::config( 'configuration' );
		return is_wp_error( $config ) ? $config : true;
	}

	/**
	 * @param array<string, mixed> $job
	 * @param array<int, array<string, mixed>> $evidence
	 * @return array<string, mixed>|WP_Error
	 */
	public static function review_input( array $job, array $evidence = array() ): array|WP_Error {
		$payload = is_array( $job['request_payload'] ?? null ) ? $job['request_payload'] : array();
		$text    = (string) ( $payload['instructions'] ?? $payload['prompt'] ?? '' );
		if ( ! empty( $evidence ) ) {
			$text .= "\n\n" . Chidemoon_AI_Evidence::prompt_context( $evidence );
		}

		return self::moderate_text( $text, 'input' );
	}

	/**
	 * @param array<string, mixed> $result
	 * @return array<string, mixed>|WP_Error
	 */
	public static function review_text_output( array $result ): array|WP_Error {
		$text = implode(
			"\n",
			array(
				(string) ( $result['title'] ?? '' ),
				(string) ( $result['excerpt'] ?? '' ),
				(string) ( $result['content'] ?? '' ),
				wp_json_encode( $result['facts_needing_review'] ?? array(), JSON_UNESCAPED_UNICODE ),
			)
		);

		return self::moderate_text( $text, 'output' );
	}

	/**
	 * @param array<string, mixed> $asset
	 * @return array<string, mixed>|WP_Error
	 */
	public static function review_image_output( array $asset ): array|WP_Error {
		$image_url = '';
		if ( ! empty( $asset['url'] ) ) {
			$image_url = (string) $asset['url'];
		} elseif ( ! empty( $asset['b64_json'] ) ) {
			$bytes = base64_decode( (string) $asset['b64_json'], true );
			if ( false === $bytes || strlen( $bytes ) > Chidemoon_AI_Media::MAX_BYTES ) {
				return self::failure( 'chidemoon_ai_moderation_image_invalid', __( 'The generated image could not be moderated safely.', 'chidemoon-ai' ), 'output-image' );
			}
			$details = @getimagesizefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! is_array( $details ) || ! in_array( $details['mime'] ?? '', Chidemoon_AI_Media::ALLOWED_MIME_TYPES, true ) ) {
				return self::failure( 'chidemoon_ai_moderation_image_invalid', __( 'The generated image could not be moderated safely.', 'chidemoon-ai' ), 'output-image' );
			}
			$image_url = 'data:' . $details['mime'] . ';base64,' . (string) $asset['b64_json'];
		}

		if ( '' === $image_url ) {
			return self::failure( 'chidemoon_ai_moderation_image_missing', __( 'The generated image could not be moderated safely.', 'chidemoon-ai' ), 'output-image' );
		}

		return self::moderate(
			array(
				array( 'type' => 'text', 'text' => (string) ( $asset['revised_prompt'] ?? 'Generated editorial image.' ) ),
				array( 'type' => 'image_url', 'image_url' => array( 'url' => $image_url ) ),
			),
			'output-image'
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function moderate_text( string $text, string $stage ): array|WP_Error {
		$text = trim( wp_strip_all_tags( $text ) );
		$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 24000 ) : substr( $text, 0, 24000 );
		if ( '' === $text ) {
			return self::failure( 'chidemoon_ai_moderation_input_empty', __( 'AI content must contain material that can be moderated.', 'chidemoon-ai' ), $stage );
		}

		return self::moderate( $text, $stage );
	}

	/**
	 * @param string|array<int, array<string, mixed>> $input
	 * @return array<string, mixed>|WP_Error
	 */
	private static function moderate( string|array $input, string $stage ): array|WP_Error {
		$config = self::config( $stage );
		if ( is_wp_error( $config ) ) {
			return $config;
		}

		$response = wp_safe_remote_post(
			$config['base_url'] . '/moderations',
			array(
				'timeout' => $config['timeout'],
				'headers' => array(
					'Authorization' => 'Bearer ' . $config['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode(
					array(
						'model' => $config['model'],
						'input' => $input,
					),
					JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return self::failure( 'chidemoon_ai_moderation_unavailable', __( 'The moderation service could not be reached. The job was not sent to an AI provider.', 'chidemoon-ai' ), $stage, $config['model'] );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$result = is_array( $body ) && is_array( $body['results'][0] ?? null ) ? $body['results'][0] : null;
		if ( $status < 200 || $status >= 300 || ! is_array( $result ) ) {
			return self::failure( 'chidemoon_ai_moderation_unavailable', __( 'The moderation service did not return a valid decision. The job was not sent to an AI provider.', 'chidemoon-ai' ), $stage, $config['model'] );
		}

		$categories = array();
		foreach ( is_array( $result['categories'] ?? null ) ? $result['categories'] : array() as $category => $flagged ) {
			$categories[ sanitize_key( (string) $category ) ] = (bool) $flagged;
		}
		$outcome = array(
			'stage'      => $stage,
			'status'     => ! empty( $result['flagged'] ) ? 'blocked' : 'passed',
			'model'      => $config['model'],
			'categories' => $categories,
			'checked_at' => current_time( 'mysql', true ),
		);
		if ( ! empty( $result['flagged'] ) ) {
			return new WP_Error( 'chidemoon_ai_moderation_blocked', __( 'The AI request or output was blocked by moderation.', 'chidemoon-ai' ), array( 'status' => 422, 'outcome' => $outcome ) );
		}

		return $outcome;
	}

	/**
	 * @return array{base_url: string, api_key: string, model: string, timeout: int}|WP_Error
	 */
	private static function config( string $stage ): array|WP_Error {
		$base_url = trim( (string) getenv( 'CHIDEMOON_AI_PROVIDER_BASE_URL' ) );
		$api_key  = trim( (string) getenv( 'CHIDEMOON_AI_API_KEY' ) );
		$model    = trim( (string) getenv( 'CHIDEMOON_AI_MODERATION_MODEL' ) );
		if ( '' === $base_url || '' === $api_key || '' === $model || ! Chidemoon_AI_Provider_Factory::is_safe_provider_url( $base_url ) ) {
			return self::failure( 'chidemoon_ai_moderation_not_configured', __( 'The mandatory AI moderation gate is not configured on this host.', 'chidemoon-ai' ), $stage );
		}

		$timeout = getenv( 'CHIDEMOON_AI_MODERATION_TIMEOUT' );
		$timeout = false === $timeout || ! is_numeric( $timeout ) ? 30 : (int) $timeout;
		return array(
			'base_url' => rtrim( $base_url, '/' ),
			'api_key'  => $api_key,
			'model'    => substr( sanitize_text_field( $model ), 0, 128 ),
			'timeout'  => max( 10, min( 120, $timeout ) ),
		);
	}

	/**
	 * @return WP_Error
	 */
	private static function failure( string $code, string $message, string $stage, string $model = '' ): WP_Error {
		return new WP_Error(
			$code,
			$message,
			array(
				'status'  => 503,
				'outcome' => array(
					'stage'      => $stage,
					'status'     => 'unavailable',
					'model'      => $model,
					'categories' => array(),
					'checked_at' => current_time( 'mysql', true ),
				),
			)
		);
	}
}
