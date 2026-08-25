<?php

namespace Kalahamoon\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Kalahamoon_RTL::platform_label — the centralized marketplace
 * label map that replaced the duplicated per-template arrays.
 */
class PlatformLabelTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// apply_filters passthrough: return the value unchanged.
		Functions\when( 'apply_filters' )->alias( function ( string $hook, $value ) {
			return $value;
		} );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_known_platforms_resolve_to_labels(): void {
		$this->assertSame( 'دیجی‌کالا', \Kalahamoon_RTL::platform_label( 'digikala' ) );
		$this->assertSame( 'باسلام', \Kalahamoon_RTL::platform_label( 'basalam' ) );
		$this->assertSame( 'ترب', \Kalahamoon_RTL::platform_label( 'torob' ) );
		$this->assertSame( 'WooCommerce', \Kalahamoon_RTL::platform_label( 'woocommerce' ) );
	}

	public function test_is_case_insensitive_and_trims(): void {
		$this->assertSame( 'دیجی‌کالا', \Kalahamoon_RTL::platform_label( '  DigiKala ' ) );
	}

	public function test_unknown_platform_falls_back_to_slug(): void {
		$this->assertSame( 'amazon', \Kalahamoon_RTL::platform_label( 'amazon' ) );
	}

	public function test_empty_platform_returns_empty_string(): void {
		$this->assertSame( '', \Kalahamoon_RTL::platform_label( '' ) );
		$this->assertSame( '', \Kalahamoon_RTL::platform_label( '   ' ) );
	}

	public function test_filter_can_override_labels(): void {
		Functions\when( 'apply_filters' )->alias( function ( string $hook, $value ) {
			$value['digikala'] = 'Digikala FA';
			return $value;
		} );
		$this->assertSame( 'Digikala FA', \Kalahamoon_RTL::platform_label( 'digikala' ) );
	}
}
