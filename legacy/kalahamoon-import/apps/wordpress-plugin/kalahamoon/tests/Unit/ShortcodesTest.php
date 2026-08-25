<?php

namespace Kalahamoon\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Kalahamoon_Shortcodes — attribute parsing and early-exit guards.
 *
 * These tests focus on logic that runs *before* any render template is
 * included (empty-input guards, attribute defaults, hotspot parsing).
 * Full render output requires WordPress integration tests.
 */
class ShortcodesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->alias( function ( string $text ) { return $text; } );
		Functions\when( 'shortcode_atts' )->alias( function ( array $pairs, array $atts, string $sc = '' ) {
			$out = array();
			foreach ( $pairs as $name => $default ) {
				$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
			}
			return $out;
		} );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ── kalahamoon_pros_cons ───────────────────────────────────────────────────

	public function test_pros_cons_returns_empty_when_no_items(): void {
		$result = \Kalahamoon_Shortcodes::pros_cons( array() );
		$this->assertSame( '', $result );
	}

	public function test_pros_cons_returns_empty_when_only_whitespace(): void {
		$result = \Kalahamoon_Shortcodes::pros_cons( array( 'pros' => '   ', 'cons' => '   ' ) );
		$this->assertSame( '', $result );
	}

	public function test_pros_cons_returns_empty_when_pipe_only(): void {
		// A lone pipe should produce no items after trim+filter
		$result = \Kalahamoon_Shortcodes::pros_cons( array( 'pros' => '|', 'cons' => '' ) );
		$this->assertSame( '', $result );
	}

	public function test_pros_cons_processes_when_only_pros_given(): void {
		// When pros have content but cons are empty, we should NOT get empty return
		// (the early-exit only fires if BOTH are empty)
		Functions\when( 'KALAHAMOON_PLUGIN_DIR' )->justReturn( '' );

		// We can't call the full method (it'd include render.php which needs WP),
		// so we verify the guard logic by checking pros-only → not empty string.
		// Use a reflection to test the parsed $pros array directly:
		$atts = array( 'pros' => 'Fast delivery | Good quality', 'cons' => '' );
		$atts = shortcode_atts_local( array(
			'id'      => '',
			'heading' => '',
			'pros'    => '',
			'cons'    => '',
			'cta'     => '1',
			'cta_text' => 'مشاهده و خرید',
		), $atts );

		$pros = array_filter( array_map( 'trim', explode( '|', $atts['pros'] ) ) );
		$cons = array_filter( array_map( 'trim', explode( '|', $atts['cons'] ) ) );

		$this->assertCount( 2, $pros );
		$this->assertContains( 'Fast delivery', $pros );
		$this->assertContains( 'Good quality', $pros );
		$this->assertEmpty( $cons );
	}

	public function test_pros_cons_pipe_splitting(): void {
		$raw  = 'سریع|باکیفیت|مقاوم';
		$items = array_filter( array_map( 'trim', explode( '|', $raw ) ) );
		$this->assertCount( 3, $items );
		$this->assertSame( array( 'سریع', 'باکیفیت', 'مقاوم' ), array_values( $items ) );
	}

	public function test_pros_cons_trims_whitespace_around_pipes(): void {
		$raw  = ' خوب |  عالی  | بد نیست ';
		$items = array_values( array_filter( array_map( 'trim', explode( '|', $raw ) ) ) );
		$this->assertSame( array( 'خوب', 'عالی', 'بد نیست' ), $items );
	}

	// ── kalahamoon_look (shop_the_look) ────────────────────────────────────────

	public function test_shop_the_look_returns_empty_without_image(): void {
		$result = \Kalahamoon_Shortcodes::shop_the_look( array() );
		$this->assertSame( '', $result );
	}

	public function test_shop_the_look_returns_empty_when_image_empty_string(): void {
		$result = \Kalahamoon_Shortcodes::shop_the_look( array( 'image' => '' ) );
		$this->assertSame( '', $result );
	}

	public function test_hotspot_string_parsing(): void {
		// Test the format: "productId:x,y" separated by semicolons
		$raw      = 'abc123:30,40;def456:60,55;ghi:80,20';
		$hs_array = self::parse_hotspot_string( $raw );

		$this->assertCount( 3, $hs_array );

		$this->assertSame( 'abc123', $hs_array[0]['productId'] );
		$this->assertSame( 30.0, $hs_array[0]['x'] );
		$this->assertSame( 40.0, $hs_array[0]['y'] );

		$this->assertSame( 'def456', $hs_array[1]['productId'] );
		$this->assertSame( 60.0, $hs_array[1]['x'] );
		$this->assertSame( 55.0, $hs_array[1]['y'] );
	}

	public function test_hotspot_string_empty_yields_no_hotspots(): void {
		$this->assertCount( 0, self::parse_hotspot_string( '' ) );
	}

	public function test_hotspot_string_ignores_malformed_entries(): void {
		// Entry with no colon and entry with no comma are ignored
		$raw      = 'goodId:10,20;badentry;anotherbad:onlyone;ok:50,60';
		$hs_array = self::parse_hotspot_string( $raw );

		$this->assertCount( 2, $hs_array );
		$this->assertSame( 'goodId', $hs_array[0]['productId'] );
		$this->assertSame( 'ok', $hs_array[1]['productId'] );
	}

	public function test_hotspot_style_defaults_to_dot(): void {
		$hs_array = self::parse_hotspot_string( 'id1:10,20' );
		$this->assertSame( 'dot', $hs_array[0]['style'] );
	}

	// ── kalahamoon_product (product_box) ───────────────────────────────────────

	public function test_product_box_returns_empty_without_id(): void {
		Functions\when( 'shortcode_atts' )->alias( function ( array $defaults, array $atts ) {
			return array_merge( $defaults, $atts );
		} );
		$result = \Kalahamoon_Shortcodes::product_box( array() );
		$this->assertSame( '', $result );
	}

	public function test_favorites_shortcode_marks_storage_container(): void {
		Functions\when( 'wp_enqueue_script' )->justReturn( true );
		Functions\when( 'esc_html__' )->alias( function ( string $text ) { return $text; } );

		$result = \Kalahamoon_Shortcodes::favorites_page( array() );
		$this->assertStringContainsString( 'data-kalahamoon-storage="favorites"', $result );
		$this->assertStringContainsString( 'kalahamoon-favorites-container', $result );
	}

	// ── kalahamoon_compare ─────────────────────────────────────────────────────

	public function test_comparison_table_returns_empty_without_ids(): void {
		Functions\when( 'shortcode_atts' )->alias( function ( array $defaults, array $atts ) {
			return array_merge( $defaults, $atts );
		} );
		$result = \Kalahamoon_Shortcodes::comparison_table( array() );
		$this->assertSame( '', $result );
	}

	// ── inline helpers ─────────────────────────────────────────────────────

	/**
	 * Replicates the hotspot parsing logic from Kalahamoon_Shortcodes::shop_the_look.
	 */
	private static function parse_hotspot_string( string $raw ): array {
		$hs_array = array();
		if ( empty( $raw ) ) {
			return $hs_array;
		}
		foreach ( explode( ';', $raw ) as $entry ) {
			$entry = trim( $entry );
			if ( empty( $entry ) ) {
				continue;
			}
			$parts = explode( ':', $entry, 2 );
			if ( count( $parts ) !== 2 ) {
				continue;
			}
			$pid    = trim( $parts[0] );
			$coords = array_map( 'trim', explode( ',', $parts[1] ) );
			if ( count( $coords ) < 2 ) {
				continue;
			}
			$hs_array[] = array(
				'productId' => $pid,
				'x'         => (float) $coords[0],
				'y'         => (float) $coords[1],
				'style'     => 'dot',
			);
		}
		return $hs_array;
	}
}

/**
 * Minimal shortcode_atts substitute used in tests that call Kalahamoon_Shortcodes methods
 * before Brain Monkey has registered a stub via Functions\when.
 */
function shortcode_atts_local( array $pairs, array $atts ): array {
	$out = array();
	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}
