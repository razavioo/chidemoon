<?php
/**
 * Bounded image-import policy for editor-generated media.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Kalahamoon_Image_Policy {
	private const MAX_BYTES  = 8388608;
	private const MAX_EDGE   = 4096;
	private const MAX_PIXELS = 16777216;
	private const MIME_EXTENSIONS = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/webp' => 'webp',
	);

	public static function remote_url_issue( string $url ): ?string {
		if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return 'invalid_url';
		}
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) ) {
			return 'https_required';
		}
		if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
			return 'credentials_not_allowed';
		}
		if ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] ) {
			return 'port_not_allowed';
		}

		$host = strtolower( trim( (string) ( $parts['host'] ?? '' ), '[]' ) );
		if ( '' === $host || 'localhost' === $host || str_ends_with( $host, '.localhost' ) || str_ends_with( $host, '.local' ) ) {
			return 'private_host';
		}
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$is_public = false !== filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
			if ( ! $is_public ) {
				return 'private_host';
			}
		}

		return null;
	}

	/**
	 * @return array{tmp_name: string, mime: string, extension: string, width: int, height: int}|WP_Error
	 */
	public static function download_remote( string $url ) {
		$issue = self::remote_url_issue( $url );
		if ( null !== $issue ) {
			return new WP_Error( 'kalahamoon_image_' . $issue, __( 'The image URL is not allowed.', 'kalahamoon' ) );
		}

		return self::download( $url, true );
	}

	/**
	 * Import from an explicit internal Kalahamoon origin only after the caller
	 * has matched a generated-image public URL to its fixed local counterpart.
	 * This avoids relying on public DNS from the co-located WordPress container.
	 */
	public static function download_trusted_internal( string $url, string $expected_origin ) {
		$url_parts    = parse_url( $url );
		$origin_parts = parse_url( $expected_origin );
		if ( ! is_array( $url_parts ) || ! is_array( $origin_parts ) ) {
			return new WP_Error( 'kalahamoon_image_internal_url', __( 'The generated image URL is not allowed.', 'kalahamoon' ) );
		}
		if (
			empty( $url_parts['host'] )
			|| empty( $origin_parts['host'] )
			|| strtolower( (string) ( $url_parts['scheme'] ?? '' ) ) !== strtolower( (string) ( $origin_parts['scheme'] ?? '' ) )
			|| strtolower( (string) $url_parts['host'] ) !== strtolower( (string) $origin_parts['host'] )
			|| (int) ( $url_parts['port'] ?? 0 ) !== (int) ( $origin_parts['port'] ?? 0 )
			|| ! empty( $url_parts['user'] )
			|| ! empty( $url_parts['pass'] )
		) {
			return new WP_Error( 'kalahamoon_image_internal_url', __( 'The generated image URL is not allowed.', 'kalahamoon' ) );
		}

		return self::download( $url, false );
	}

	/**
	 * @return array{tmp_name: string, mime: string, extension: string, width: int, height: int}|WP_Error
	 */
	private static function download( string $url, bool $reject_unsafe_urls ) {

		$tmp = wp_tempnam( 'kalahamoon-ai-image' );
		if ( ! $tmp ) {
			return new WP_Error( 'kalahamoon_image_temp', __( 'Could not prepare a temporary image file.', 'kalahamoon' ) );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 15,
				'redirection'         => 3,
				'reject_unsafe_urls'  => $reject_unsafe_urls,
				'limit_response_size' => self::MAX_BYTES + 1,
				'stream'              => true,
				'filename'            => $tmp,
			)
		);
		if ( is_wp_error( $response ) ) {
			@unlink( $tmp );
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$size   = is_file( $tmp ) ? (int) filesize( $tmp ) : 0;
		if ( $status < 200 || $status >= 300 || $size < 1 || $size > self::MAX_BYTES ) {
			@unlink( $tmp );
			return new WP_Error( 'kalahamoon_image_download', __( 'The remote image could not be imported safely.', 'kalahamoon' ) );
		}

		$binary = file_get_contents( $tmp );
		$info   = false === $binary ? new WP_Error( 'kalahamoon_image_read', __( 'The downloaded image could not be read.', 'kalahamoon' ) ) : self::validate_binary( $binary );
		if ( is_wp_error( $info ) ) {
			@unlink( $tmp );
			return $info;
		}

		return array_merge( array( 'tmp_name' => $tmp ), $info );
	}

	/**
	 * @return array{binary: string, mime: string, extension: string, width: int, height: int}|WP_Error
	 */
	public static function decode_data_uri( string $data_uri ) {
		if ( ! preg_match( '#^data:image/(png|jpe?g|webp);base64,([a-zA-Z0-9+/=]+)$#', $data_uri, $matches ) ) {
			return new WP_Error( 'kalahamoon_bad_data_uri', __( 'Unsupported image data.', 'kalahamoon' ) );
		}
		if ( strlen( $matches[2] ) > (int) ceil( self::MAX_BYTES * 4 / 3 ) + 4 ) {
			return new WP_Error( 'kalahamoon_image_too_large', __( 'The image exceeds the upload limit.', 'kalahamoon' ) );
		}

		$binary = base64_decode( $matches[2], true );
		if ( false === $binary || '' === $binary || strlen( $binary ) > self::MAX_BYTES ) {
			return new WP_Error( 'kalahamoon_bad_base64', __( 'Could not decode image data.', 'kalahamoon' ) );
		}

		$info = self::validate_binary( $binary );
		if ( is_wp_error( $info ) ) {
			return $info;
		}
		$declared_mime = 'image/' . strtolower( $matches[1] );
		if ( 'image/jpg' === $declared_mime ) {
			$declared_mime = 'image/jpeg';
		}
		if ( $declared_mime !== $info['mime'] ) {
			return new WP_Error( 'kalahamoon_image_mime_mismatch', __( 'The declared image type does not match its contents.', 'kalahamoon' ) );
		}

		return array_merge( array( 'binary' => $binary ), $info );
	}

	/**
	 * @return array{mime: string, extension: string, width: int, height: int}|WP_Error
	 */
	private static function validate_binary( string $binary ) {
		$dimensions = @getimagesizefromstring( $binary );
		$mime       = is_array( $dimensions ) ? strtolower( (string) ( $dimensions['mime'] ?? '' ) ) : '';
		$width      = is_array( $dimensions ) ? (int) ( $dimensions[0] ?? 0 ) : 0;
		$height     = is_array( $dimensions ) ? (int) ( $dimensions[1] ?? 0 ) : 0;

		if ( ! isset( self::MIME_EXTENSIONS[ $mime ] ) || $width < 1 || $height < 1 ) {
			return new WP_Error( 'kalahamoon_invalid_image', __( 'The file is not a supported raster image.', 'kalahamoon' ) );
		}
		if ( $width > self::MAX_EDGE || $height > self::MAX_EDGE || $width * $height > self::MAX_PIXELS ) {
			return new WP_Error( 'kalahamoon_image_dimensions', __( 'The image dimensions exceed the safe limit.', 'kalahamoon' ) );
		}

		return array(
			'mime'      => $mime,
			'extension' => self::MIME_EXTENSIONS[ $mime ],
			'width'     => $width,
			'height'    => $height,
		);
	}
}
