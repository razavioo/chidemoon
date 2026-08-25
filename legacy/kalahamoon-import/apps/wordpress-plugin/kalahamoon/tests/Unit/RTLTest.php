<?php

namespace Kalahamoon\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Kalahamoon_RTL — number formatting and price display.
 */
class RTLTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// Default: no persian numerals, IRR currency, TOMAN unit
		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) {
			$map = array(
				'kalahamoon_persian_numerals'  => false,
				'kalahamoon_display_currency'  => 'IRR',
				'kalahamoon_display_unit'      => 'TOMAN',
			);
			return $map[ $key ] ?? $default;
		} );
		// Translation function returns the string as-is
		Functions\when( '__' )->alias( function ( string $text ) { return $text; } );
		Functions\when( 'get_locale' )->justReturn( 'en_US' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ── format_number ─────────────────────────────────────────────────────

	public function test_format_number_adds_thousands_separator(): void {
		$result = \Kalahamoon_RTL::format_number( 1234567 );
		$this->assertSame( '1,234,567', $result );
	}

	public function test_format_number_integer_zero(): void {
		$this->assertSame( '0', \Kalahamoon_RTL::format_number( 0 ) );
	}

	public function test_format_number_with_persian_digits(): void {
		$result = \Kalahamoon_RTL::format_number( 1234, true );
		$this->assertStringNotContainsString( '1', $result );
		$this->assertStringNotContainsString( '2', $result );
		$this->assertStringContainsString( '۱', $result );
		$this->assertStringContainsString( '۲', $result );
	}

	public function test_format_number_respects_option_flag(): void {
		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) {
			if ( 'kalahamoon_persian_numerals' === $key ) return true;
			return $default;
		} );
		$result = \Kalahamoon_RTL::format_number( 42 );
		$this->assertStringContainsString( '۴', $result );
		$this->assertStringContainsString( '۲', $result );
	}

	// ── format_price ──────────────────────────────────────────────────────

	public function test_format_price_irr_converts_to_toman(): void {
		// 10,000 Rial = 1,000 Toman
		$result = \Kalahamoon_RTL::format_price( 10000, 'IRR' );
		$this->assertStringContainsString( '1,000', $result );
		$this->assertStringContainsString( 'تومان', $result );
	}

	public function test_format_price_irr_unit_rial(): void {
		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) {
			$map = array(
				'kalahamoon_persian_numerals' => false,
				'kalahamoon_display_currency' => 'IRR',
				'kalahamoon_display_unit'     => 'RIAL',
			);
			return $map[ $key ] ?? $default;
		} );
		$result = \Kalahamoon_RTL::format_price( 50000, 'IRR' );
		$this->assertStringContainsString( '50,000', $result );
		$this->assertStringContainsString( 'ریال', $result );
	}

	public function test_format_price_usd_uses_dollar_sign(): void {
		$result = \Kalahamoon_RTL::format_price( 19.99, 'USD' );
		$this->assertSame( '$19.99', $result );
	}

	public function test_format_price_eur_uses_euro_sign(): void {
		$result = \Kalahamoon_RTL::format_price( 9.50, 'EUR' );
		$this->assertSame( '€9.50', $result );
	}

	public function test_format_price_unknown_currency_appends_code(): void {
		$result = \Kalahamoon_RTL::format_price( 100, 'GBP' );
		$this->assertStringContainsString( 'GBP', $result );
		$this->assertStringContainsString( '100', $result );
	}

	public function test_format_price_uses_display_currency_option_when_empty(): void {
		// When no currency passed, reads kalahamoon_display_currency option (defaults to IRR → TOMAN in setUp)
		$result = \Kalahamoon_RTL::format_price( 20000 );
		$this->assertStringContainsString( '2,000', $result );
		$this->assertStringContainsString( 'تومان', $result );
	}

	public function test_format_price_zero_shows_zero_toman(): void {
		$result = \Kalahamoon_RTL::format_price( 0, 'IRR' );
		$this->assertStringContainsString( '0', $result );
		$this->assertStringContainsString( 'تومان', $result );
	}

	// ── direction override ────────────────────────────────────────────────

	public function test_direction_follows_rtl_locale(): void {
		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) {
			return 'kalahamoon_direction' === $key ? 'auto' : $default;
		} );
		Functions\when( 'is_rtl' )->justReturn( true );
		$this->assertSame( 'rtl', \Kalahamoon_RTL::direction() );
	}

	// ── needs_rtl_font ────────────────────────────────────────────────────

	public function test_needs_rtl_font_true_for_farsi_locale(): void {
		Functions\when( 'get_locale' )->justReturn( 'fa_IR' );
		$this->assertTrue( \Kalahamoon_RTL::needs_rtl_font() );
	}

	public function test_needs_rtl_font_true_for_arabic_locale(): void {
		Functions\when( 'get_locale' )->justReturn( 'ar' );
		$this->assertTrue( \Kalahamoon_RTL::needs_rtl_font() );
	}

	public function test_needs_rtl_font_false_for_english(): void {
		Functions\when( 'get_locale' )->justReturn( 'en_US' );
		$this->assertFalse( \Kalahamoon_RTL::needs_rtl_font() );
	}
}
