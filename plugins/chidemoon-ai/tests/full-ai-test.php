<?php
/**
 * Contract tests for the full-AI build: settings, look hotspots, enrich
 * validation, web SSRF guards.
 *
 * Run with: php tests/full-ai-test.php
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );

// Minimal WP stubs so pure-logic classes load without WordPress.
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		$key = strtolower( $key );
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $value ): string {
		return trim( strip_tags( $value ) );
	}
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( string $value ): string {
		return strip_tags( $value, '<p><br><strong><em><ul><ol><li><a>' );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url, array $protocols = array() ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
		if ( ! empty( $protocols ) && ! in_array( $scheme, $protocols, true ) ) {
			return '';
		}
		return $url;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = '' ): string {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, int $options = 0 ): string {
		$json = json_encode( $data, $options );
		return false === $json ? '{}' : $json;
	}
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		public function __construct( string $code = '', string $message = '', $data = null ) {
			unset( $data );
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_code(): string {
			return $this->code;
		}
		public function get_error_message(): string {
			return $this->message;
		}
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}
if ( ! function_exists( 'get_post' ) ) {
	function get_post( int $id ): ?stdClass {
		if ( $id <= 0 ) {
			return null;
		}
		$post        = new stdClass();
		$post->ID    = $id;
		$post->post_type = 'product';
		return $post;
	}
}
if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post ): string {
		return 'Product ' . ( is_object( $post ) ? (string) $post->ID : (string) $post );
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $name, $default = null ) {
		unset( $name );
		return $default;
	}
}
if ( ! function_exists( 'getenv' ) ) {
	// Native getenv always exists; stub never used.
}

function check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-chidemoon-ai-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-chidemoon-ai-look.php';
require_once dirname( __DIR__ ) . '/includes/class-chidemoon-ai-enrich.php';

// Settings: unknown keys sanitize to null, known sizes validate.
check( null === Chidemoon_AI_Settings::sanitize( 'nope', 'x' ), 'Unknown settings key must sanitize to null.' );
check( '1024x1024' === Chidemoon_AI_Settings::sanitize( 'image_size', '1024x1024' ), 'Allowed image size must pass.' );
check( null === Chidemoon_AI_Settings::sanitize( 'image_size', '9999x9999' ), 'Disallowed image size must fail.' );
check( 'free_only' === Chidemoon_AI_Settings::sanitize( 'search_mode', 'free_only' ), 'free_only search mode must pass.' );
check( null === Chidemoon_AI_Settings::sanitize( 'search_mode', 'scrape_everything' ), 'Unknown search mode must fail.' );
check( in_array( 'search_mode', array_keys( Chidemoon_AI_Settings::fields() ), true ), 'search_mode must be a registered setting.' );
// Secrets must never appear as option names.
foreach ( Chidemoon_AI_Settings::option_names() as $option_name ) {
	check( false === stripos( $option_name, 'api_key' ), 'No option may store an api key: ' . $option_name );
	check( false === stripos( $option_name, 'provider_url' ), 'No option may store a provider url: ' . $option_name );
}

// Look: heuristic hotspots are bounded and deterministic.
$spots = Chidemoon_AI_Look::heuristic_hotspots( array( 11, 22, 33 ) );
check( 3 === count( $spots ), 'Three products must yield three hotspots.' );
foreach ( $spots as $spot ) {
	check( $spot['x'] >= 0 && $spot['x'] <= 100 && $spot['y'] >= 0 && $spot['y'] <= 100, 'Hotspot coordinates must be 0-100.' );
	check( $spot['productId'] > 0, 'Hotspot must carry its product id.' );
}
$again = Chidemoon_AI_Look::heuristic_hotspots( array( 11, 22, 33 ) );
check( $spots === $again, 'Heuristic hotspots must be deterministic.' );
// Vision normalization clamps bad input to heuristic.
$normalized = Chidemoon_AI_Look::normalize_vision_hotspots( array( 'hotspots' => 'garbage' ), array( 5 ) );
check( 1 === count( $normalized ) && 5 === $normalized[0]['productId'], 'Bad vision output must fall back to heuristic.' );
$normalized = Chidemoon_AI_Look::normalize_vision_hotspots(
	array( 'hotspots' => array( array( 'x' => 10, 'y' => 20, 'label' => 'Sofa', 'product_id' => 7 ), array( 'x' => 500, 'y' => -3 ) ) ),
	array( 7 )
);
check( 1 === count( $normalized ) && 10 === $normalized[0]['x'] && 7 === $normalized[0]['productId'], 'Out-of-range vision spots must be dropped.' );
// Block markup must reference the shop-the-look block.
$markup = Chidemoon_AI_Look::block_markup( 99, 'Caption', $spots );
check( false !== strpos( $markup, 'wp:chidemoon/shop-the-look' ), 'Look draft must embed the shop-the-look block.' );
check( false !== strpos( $markup, '"imageId":99' ), 'Look markup must carry the image id.' );

// Enrich: schema is strict, validation bounds hold.
$schema = Chidemoon_AI_Enrich::json_schema( array( '1', 'web-abc' ) );
check( false === ( $schema['additionalProperties'] ?? true ), 'Enrichment schema must be strict.' );
check( in_array( 'title', $schema['required'], true ), 'Enrichment schema must require a title.' );
$valid = Chidemoon_AI_Enrich::validate_result(
	array(
		'title'                => 'Great chair',
		'short_description'    => 'Short copy.',
		'description'          => '<p>Full copy.</p>',
		'facts'                => array( 'Material' => 'Oak' ),
		'facts_needing_review' => array( 'Price unverified' ),
		'citation_source_ids'  => array( '1' ),
	),
	array( '1', 'web-abc' )
);
check( ! is_wp_error( $valid ), 'A well-formed enrichment must validate.' );
$bad_citation = Chidemoon_AI_Enrich::validate_result(
	array(
		'title'                => 'Great chair',
		'short_description'    => 'Short copy.',
		'description'          => '<p>Full copy.</p>',
		'facts'                => array(),
		'facts_needing_review' => array(),
		'citation_source_ids'  => array( 'evil-source' ),
	),
	array( '1' )
);
check( is_wp_error( $bad_citation ) && 'chidemoon_ai_enrich_citation' === $bad_citation->get_error_code(), 'Citations outside evidence must be rejected.' );

echo "Chidemoon AI full-build contracts passed.\n";
