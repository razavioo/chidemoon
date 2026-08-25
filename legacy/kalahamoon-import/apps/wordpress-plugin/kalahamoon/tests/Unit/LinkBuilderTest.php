<?php

namespace Kalahamoon\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Kalahamoon_Link_Builder.
 *
 * Methods that are pure PHP (no WP function calls) are tested directly.
 * Methods requiring WP functions use Brain Monkey stubs.
 */
class LinkBuilderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ── build_bakalahamoon ──────────────────────────────────────────────────────

	public function test_build_bakalahamoon_encodes_destination_in_b64(): void {
		$destination   = 'https://bakalahamoon.com/products/12345';
		$tracking      = 'https://a.bslm.ir/api/v1/tracking/click/g/ABCDEF';
		$result        = \Kalahamoon_Link_Builder::build_bakalahamoon( $destination, $tracking );

		$this->assertStringStartsWith( 'https://a.bslm.ir/api/v1/tracking/click/g/ABCDEF?b64=', $result );

		// Verify the b64 parameter decodes back to the original URL
		parse_str( parse_url( $result, PHP_URL_QUERY ), $params );
		$encoded = $params['b64'];
		$padded  = $encoded . str_repeat( '=', ( 4 - strlen( $encoded ) % 4 ) % 4 );
		$this->assertSame( $destination, base64_decode( $padded, true ) );
	}

	public function test_build_bakalahamoon_strips_existing_tracking_query(): void {
		$destination = 'https://bakalahamoon.com/products/99';
		$tracking    = 'https://a.bslm.ir/api/v1/tracking/click/g/CODE?old=param';
		$result      = \Kalahamoon_Link_Builder::build_bakalahamoon( $destination, $tracking );

		// Base should be stripped of existing query
		$this->assertStringStartsWith( 'https://a.bslm.ir/api/v1/tracking/click/g/CODE?b64=', $result );
		$this->assertStringNotContainsString( 'old=param', $result );
	}

	public function test_build_bakalahamoon_returns_destination_when_tracking_empty(): void {
		$destination = 'https://bakalahamoon.com/products/42';
		$this->assertSame( $destination, \Kalahamoon_Link_Builder::build_bakalahamoon( $destination, '' ) );
	}

	public function test_build_bakalahamoon_returns_destination_when_destination_empty(): void {
		$this->assertSame( '', \Kalahamoon_Link_Builder::build_bakalahamoon( '', 'https://tracking.url/' ) );
	}

	public function test_build_bakalahamoon_via_build_dispatch(): void {
		$result = \Kalahamoon_Link_Builder::build(
			'https://bakalahamoon.com/products/1',
			'https://a.bslm.ir/api/v1/tracking/click/g/XYZ',
			'bakalahamoon'
		);
		$this->assertStringContainsString( 'b64=', $result );
	}

	// ── build_digikala ─────────────────────────────────────────────────────

	public function test_build_digikala_appends_url_param(): void {
		$destination = 'https://www.digikala.com/product/dkp-12345/';
		$tracking    = 'https://affilio.ir/click/abc';
		$result      = \Kalahamoon_Link_Builder::build_digikala( $destination, $tracking );

		$this->assertSame(
			'https://affilio.ir/click/abc?url=' . urlencode( $destination ),
			$result
		);
	}

	public function test_build_digikala_returns_destination_when_tracking_empty(): void {
		$destination = 'https://www.digikala.com/product/dkp-1/';
		$this->assertSame( $destination, \Kalahamoon_Link_Builder::build_digikala( $destination, '' ) );
	}

	public function test_build_digikala_via_build_dispatch(): void {
		$result = \Kalahamoon_Link_Builder::build(
			'https://www.digikala.com/product/dkp-1/',
			'https://affilio.ir/click/abc',
			'digikala'
		);
		$this->assertStringContainsString( 'url=', $result );
	}

	// ── extract_bakalahamoon_tracking_code ─────────────────────────────────────

	public function test_extract_tracking_code_from_standard_url(): void {
		$url  = 'https://a.bslm.ir/api/v1/tracking/click/g/MYCODE123';
		$code = \Kalahamoon_Link_Builder::extract_bakalahamoon_tracking_code( $url );
		$this->assertSame( 'MYCODE123', $code );
	}

	public function test_extract_tracking_code_ignores_query_string(): void {
		$url  = 'https://a.bslm.ir/api/v1/tracking/click/g/MYCODE?b64=something';
		$code = \Kalahamoon_Link_Builder::extract_bakalahamoon_tracking_code( $url );
		$this->assertSame( 'MYCODE', $code );
	}

	public function test_extract_tracking_code_returns_empty_for_non_matching_url(): void {
		$code = \Kalahamoon_Link_Builder::extract_bakalahamoon_tracking_code( 'https://bakalahamoon.com/products/1' );
		$this->assertSame( '', $code );
	}

	// ── decode_bakalahamoon_destination ─────────────────────────────────────────

	public function test_decode_bakalahamoon_destination(): void {
		// Stub wp_parse_url to behave like parse_url
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$original  = 'https://bakalahamoon.com/products/12345';
		$encoded   = rtrim( base64_encode( $original ), '=' );
		$affiliate = 'https://a.bslm.ir/api/v1/tracking/click/g/CODE?b64=' . urlencode( $encoded );

		$decoded = \Kalahamoon_Link_Builder::decode_bakalahamoon_destination( $affiliate );
		$this->assertSame( $original, $decoded );
	}

	public function test_decode_bakalahamoon_destination_returns_empty_without_b64(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$result = \Kalahamoon_Link_Builder::decode_bakalahamoon_destination( 'https://a.bslm.ir/api/v1/tracking/click/g/CODE' );
		$this->assertSame( '', $result );
	}

	// ── build_custom ──────────────────────────────────────────────────────

	public function test_build_custom_adds_utm_params(): void {
		Functions\when( 'get_option' )->justReturn( 'my-site' );
		Functions\when( 'add_query_arg' )->alias( function ( array $args, string $url ) {
			return $url . '?' . http_build_query( $args );
		} );

		$result = \Kalahamoon_Link_Builder::build_custom( 'https://example.com/product' );

		$this->assertStringContainsString( 'utm_source=kalahamoon', $result );
		$this->assertStringContainsString( 'utm_medium=wordpress', $result );
		$this->assertStringContainsString( 'utm_campaign=my-site', $result );
	}

	// ── is_clickable_url ──────────────────────────────────────────────────

	public function test_is_clickable_url_accepts_absolute_https(): void {
		$this->assertTrue( \Kalahamoon_Link_Builder::is_clickable_url( 'https://basalam.com/p/123' ) );
		$this->assertTrue( \Kalahamoon_Link_Builder::is_clickable_url( 'http://example.com/x' ) );
	}

	public function test_is_clickable_url_accepts_site_relative_path(): void {
		$this->assertTrue( \Kalahamoon_Link_Builder::is_clickable_url( '/go/some-slug' ) );
	}

	public function test_is_clickable_url_rejects_marketplace_reference(): void {
		// Internal references stored by the sync when no public URL exists.
		$this->assertFalse( \Kalahamoon_Link_Builder::is_clickable_url( 'basalam:123' ) );
		$this->assertFalse( \Kalahamoon_Link_Builder::is_clickable_url( 'digikala:456' ) );
	}

	public function test_is_clickable_url_rejects_empty_hash_and_protocol_relative(): void {
		$this->assertFalse( \Kalahamoon_Link_Builder::is_clickable_url( '' ) );
		$this->assertFalse( \Kalahamoon_Link_Builder::is_clickable_url( '#' ) );
		$this->assertFalse( \Kalahamoon_Link_Builder::is_clickable_url( '//evil.example' ) );
		$this->assertFalse( \Kalahamoon_Link_Builder::is_clickable_url( null ) );
	}

	// ── build dispatch — unknown provider falls back to custom ────────────

	public function test_build_unknown_provider_falls_back_to_custom(): void {
		Functions\when( 'get_option' )->justReturn( 'store' );
		Functions\when( 'add_query_arg' )->alias( function ( array $args, string $url ) {
			return $url . '?' . http_build_query( $args );
		} );

		$result = \Kalahamoon_Link_Builder::build(
			'https://example.com/product',
			'',
			'unknown-provider'
		);

		$this->assertStringContainsString( 'utm_source=kalahamoon', $result );
	}
}
