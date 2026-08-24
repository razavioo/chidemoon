<?php
/**
 * Safely persists only provider-produced images into the Media Library.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_Media {
	public const MAX_BYTES          = 10485760;
	public const MAX_IMAGE_DIMENSION = 8192;
	public const ALLOWED_MIME_TYPES = array( 'image/jpeg', 'image/png', 'image/webp' );

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function validate_source_attachment( int $attachment_id ): array|WP_Error {
		if ( 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new WP_Error( 'chidemoon_ai_source_forbidden', __( 'You cannot use one of the selected source images.', 'chidemoon-ai' ), array( 'status' => 403 ) );
		}

		$mime = (string) get_post_mime_type( $attachment_id );
		$file = get_attached_file( $attachment_id );
		if ( ! wp_attachment_is_image( $attachment_id ) || ! in_array( $mime, self::ALLOWED_MIME_TYPES, true ) || ! is_string( $file ) || ! is_readable( $file ) ) {
			return new WP_Error( 'chidemoon_ai_source_invalid', __( 'A source image must be a local JPEG, PNG, or WebP Media Library item.', 'chidemoon-ai' ), array( 'status' => 400 ) );
		}

		$size = filesize( $file );
		if ( false === $size || $size > self::MAX_BYTES || $size < 1 ) {
			return new WP_Error( 'chidemoon_ai_source_size', __( 'A source image exceeds the safe size limit.', 'chidemoon-ai' ), array( 'status' => 400 ) );
		}

		return array( 'id' => $attachment_id, 'mime' => $mime, 'file' => $file );
	}

	/**
	 * @param array<string, mixed> $asset
	 * @return int|WP_Error Attachment ID.
	 */
	public static function persist_generated_image( array $asset, int $job_id, int $author_id ): int|WP_Error {
		$bytes = '';
		if ( ! empty( $asset['b64_json'] ) ) {
			$bytes = base64_decode( (string) $asset['b64_json'], true );
			if ( false === $bytes ) {
				return new WP_Error( 'chidemoon_ai_image_decode', __( 'The AI provider returned an invalid image payload.', 'chidemoon-ai' ) );
			}
		} elseif ( ! empty( $asset['url'] ) ) {
			$downloaded = self::download_provider_image( (string) $asset['url'] );
			if ( is_wp_error( $downloaded ) ) {
				return $downloaded;
			}
			$bytes = $downloaded;
		}

		if ( '' === $bytes || strlen( $bytes ) > self::MAX_BYTES ) {
			return new WP_Error( 'chidemoon_ai_image_size', __( 'The generated image is empty or exceeds the safe size limit.', 'chidemoon-ai' ) );
		}

		$validated = self::validate_image_bytes( $bytes );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$extension = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		)[ $validated['mime'] ];
		$filename = sprintf( 'chidemoon-ai-%d-%s.%s', $job_id, wp_generate_password( 10, false, false ), $extension );
		$uploaded = wp_upload_bits( $filename, null, $bytes );
		if ( ! empty( $uploaded['error'] ) || empty( $uploaded['file'] ) ) {
			return new WP_Error( 'chidemoon_ai_image_store_failed', __( 'The generated image could not be stored in the Media Library.', 'chidemoon-ai' ) );
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $validated['mime'],
				'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_author'    => $author_id,
			),
			(string) $uploaded['file']
		);
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return new WP_Error( 'chidemoon_ai_image_attachment_failed', __( 'The generated image could not be registered in the Media Library.', 'chidemoon-ai' ) );
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, (string) $uploaded['file'] );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
		update_post_meta( $attachment_id, '_chidemoon_ai_job_id', $job_id );
		update_post_meta( $attachment_id, '_chidemoon_ai_generated', '1' );
		update_post_meta( $attachment_id, '_chidemoon_ai_review_state', Chidemoon_AI_State_Machine::REVIEW_REQUIRED );

		return (int) $attachment_id;
	}

	private static function download_provider_image( string $url ): string|WP_Error {
		if ( ! self::is_safe_remote_url( $url ) ) {
			return new WP_Error( 'chidemoon_ai_image_url_unsafe', __( 'The AI provider supplied an unsafe image URL.', 'chidemoon-ai' ) );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 30,
				'redirection'         => 0,
				'limit_response_size' => self::MAX_BYTES + 1,
				'reject_unsafe_urls'  => true,
			)
		);
		if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new WP_Error( 'chidemoon_ai_image_download_failed', __( 'The generated image could not be downloaded safely.', 'chidemoon-ai' ) );
		}

		$content_type = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $response, 'content-type' ) )[0] ) );
		if ( ! in_array( $content_type, self::ALLOWED_MIME_TYPES, true ) ) {
			return new WP_Error( 'chidemoon_ai_image_type', __( 'The generated image has an unsupported content type.', 'chidemoon-ai' ) );
		}

		return (string) wp_remote_retrieve_body( $response );
	}

	/**
	 * @return array{mime: string}|WP_Error
	 */
	private static function validate_image_bytes( string $bytes ): array|WP_Error {
		$details = @getimagesizefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $details ) || empty( $details['mime'] ) || ! in_array( $details['mime'], self::ALLOWED_MIME_TYPES, true ) ) {
			return new WP_Error( 'chidemoon_ai_image_signature', __( 'The generated file is not a supported image.', 'chidemoon-ai' ) );
		}
		if ( (int) ( $details[0] ?? 0 ) > self::MAX_IMAGE_DIMENSION || (int) ( $details[1] ?? 0 ) > self::MAX_IMAGE_DIMENSION ) {
			return new WP_Error( 'chidemoon_ai_image_dimensions', __( 'The generated image dimensions exceed the safe limit.', 'chidemoon-ai' ) );
		}

		return array( 'mime' => (string) $details['mime'] );
	}

	private static function is_safe_remote_url( string $url ): bool {
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
}
