<?php

namespace Kalahamoon\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Kalahamoon_Disclosure::render — the shared disclosure markup used
 * by both the opt-in auto-insert and the standalone block.
 */
class DisclosureTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->alias( fn( string $t ) => $t );
		Functions\when( 'esc_html' )->alias( fn( string $t ) => $t );
		Functions\when( 'esc_attr__' )->alias( fn( string $t ) => $t );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_uses_explicit_text_when_provided(): void {
		$html = \Kalahamoon_Disclosure::render( 'Custom disclosure text' );
		$this->assertStringContainsString( 'Custom disclosure text', $html );
		$this->assertStringContainsString( 'kalahamoon-disclosure', $html );
		$this->assertStringContainsString( 'role="note"', $html );
	}

	public function test_falls_back_to_saved_option(): void {
		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) {
			return 'kalahamoon_disclosure_text' === $key ? 'Saved disclosure' : $default;
		} );

		$html = \Kalahamoon_Disclosure::render();
		$this->assertStringContainsString( 'Saved disclosure', $html );
	}

	public function test_falls_back_to_default_when_option_empty(): void {
		Functions\when( 'get_option' )->justReturn( '' );

		$html = \Kalahamoon_Disclosure::render();
		// Default Persian disclosure mentions affiliate links ("همکاری در فروش").
		$this->assertStringContainsString( 'همکاری در فروش', $html );
	}

	public function test_auto_disclosure_ignores_a_direct_product_link(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_singular' )->justReturn( true );

		$content = '<a class="kalahamoon-product-link" href="https://market.test/product">Product</a>';

		$this->assertSame( $content, \Kalahamoon_Disclosure::maybe_add_disclosure( $content ) );
	}

	public function test_auto_disclosure_includes_a_panel_issued_affiliate_link(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( '' );

		$html = \Kalahamoon_Disclosure::maybe_add_disclosure(
			'<a class="kalahamoon-product-link kalahamoon-affiliate-link" href="https://site.test/go/product">Product</a>'
		);

		$this->assertStringStartsWith( '<div class="kalahamoon-disclosure"', $html );
	}
}
