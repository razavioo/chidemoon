<?php
/** Native Gemini transport for gateways exposing generateContent. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Chidemoon_AI_Gemini {
	public static function request( string $base, string $key, string $model, array $body, int $timeout ): array|WP_Error {
		$base = preg_replace( '~/v1(?:beta)?$~', '', rtrim( $base, '/' ) );
		$response = wp_safe_remote_post( $base . '/v1beta/models/' . rawurlencode( $model ) . ':generateContent', array(
			'timeout' => $timeout,
			'redirection' => 0,
			'limit_response_size' => 20 * 1024 * 1024,
			'headers' => array( 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ),
			'body' => wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'chidemoon_ai_provider_unavailable', __( 'The AI provider could not be reached.', 'chidemoon-ai' ) );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $data ) || empty( $data['candidates'][0]['content']['parts'] ) ) {
			return new WP_Error( 'chidemoon_ai_provider_request_failed', sprintf( __( 'The AI provider returned no usable result (HTTP %d).', 'chidemoon-ai' ), $status ) );
		}
		return $data;
	}

	public static function text( array $response ): string {
		$text = '';
		foreach ( $response['candidates'][0]['content']['parts'] ?? array() as $part ) {
			if ( empty( $part['thought'] ) ) { $text .= (string) ( $part['text'] ?? '' ); }
		}
		return $text;
	}

	public static function usage( array $response ): array {
		return array(
			'prompt_tokens' => (int) ( $response['usageMetadata']['promptTokenCount'] ?? 0 ),
			'completion_tokens' => (int) ( $response['usageMetadata']['candidatesTokenCount'] ?? 0 ),
		);
	}

	/** Only inline local image data is accepted; arbitrary remote URLs are never fetched. */
	public static function image_part( string $url ): array|WP_Error {
		if ( ! preg_match( '~^data:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/=\r\n]+)$~', $url, $matches ) ) {
			return new WP_Error( 'chidemoon_ai_source_image_invalid', __( 'A local source image is required.', 'chidemoon-ai' ) );
		}
		return array( 'inlineData' => array( 'mimeType' => $matches[1], 'data' => $matches[2] ) );
	}
}
