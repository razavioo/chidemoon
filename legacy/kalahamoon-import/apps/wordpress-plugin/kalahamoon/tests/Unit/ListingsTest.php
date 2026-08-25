<?php

namespace Kalahamoon\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for Kalahamoon_Listings::normalize — the price-comparison buy-box
 * data transform. Pure PHP, no WordPress functions required.
 */
class ListingsTest extends TestCase {
	private const NOW = 1785888000;

	private function product( array $listings, string $currency = 'IRR' ): array {
		return array( 'currency' => $currency, 'listings' => $listings );
	}

	public function test_empty_listings_returns_empty(): void {
		$this->assertSame( array(), \Kalahamoon_Listings::normalize( array() ) );
		$this->assertSame( array(), \Kalahamoon_Listings::normalize( $this->product( array() ) ) );
	}

	public function test_drops_zero_and_negative_prices(): void {
		$rows = \Kalahamoon_Listings::normalize( $this->product( array(
			array( 'platform' => 'digikala', 'price' => 0 ),
			array( 'platform' => 'basalam', 'price' => -5 ),
			array( 'platform' => 'torob', 'price' => 100 ),
		) ) );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'torob', $rows[0]['platform'] );
	}

	public function test_sorts_cheapest_first_and_flags_cheapest(): void {
		$rows = \Kalahamoon_Listings::normalize( $this->product( array(
			array( 'platform' => 'digikala', 'price' => 300 ),
			array( 'platform' => 'basalam', 'price' => 100 ),
			array( 'platform' => 'torob', 'price' => 200 ),
		) ) );

		$this->assertCount( 3, $rows );
		$this->assertSame( 100.0, $rows[0]['price'] );
		$this->assertSame( 200.0, $rows[1]['price'] );
		$this->assertSame( 300.0, $rows[2]['price'] );

		$this->assertTrue( $rows[0]['cheapest'] );
		$this->assertFalse( $rows[1]['cheapest'] );
		$this->assertFalse( $rows[2]['cheapest'] );
	}

	public function test_flags_all_rows_sharing_minimum_price(): void {
		$rows = \Kalahamoon_Listings::normalize( $this->product( array(
			array( 'platform' => 'digikala', 'price' => 100 ),
			array( 'platform' => 'basalam', 'price' => 100 ),
			array( 'platform' => 'torob', 'price' => 250 ),
		) ) );

		$this->assertTrue( $rows[0]['cheapest'] );
		$this->assertTrue( $rows[1]['cheapest'] );
		$this->assertFalse( $rows[2]['cheapest'] );
	}

	public function test_currency_falls_back_to_product_currency(): void {
		$rows = \Kalahamoon_Listings::normalize( $this->product( array(
			array( 'platform' => 'digikala', 'price' => 100 ),
		), 'USD' ) );

		$this->assertSame( 'USD', $rows[0]['currency'] );
	}

	public function test_listing_currency_wins_over_product_currency(): void {
		$rows = \Kalahamoon_Listings::normalize( $this->product( array(
			array( 'platform' => 'digikala', 'price' => 100, 'currency' => 'EUR' ),
		), 'IRR' ) );

		$this->assertSame( 'EUR', $rows[0]['currency'] );
	}

	public function test_url_falls_back_between_listingUrl_and_url(): void {
		$rows = \Kalahamoon_Listings::normalize( $this->product( array(
			array( 'platform' => 'a', 'price' => 10, 'listingUrl' => 'https://x.test/a' ),
			array( 'platform' => 'b', 'price' => 20, 'url' => 'https://x.test/b' ),
		) ) );

		// Sorted cheapest-first, so 'a' (10) is index 0.
		$this->assertSame( 'https://x.test/a', $rows[0]['url'] );
		$this->assertSame( 'https://x.test/b', $rows[1]['url'] );
	}

	public function test_in_stock_flag_from_inventory(): void {
		$rows = \Kalahamoon_Listings::normalize( $this->product( array(
			array( 'platform' => 'a', 'price' => 10, 'inventory' => 5 ),
			array( 'platform' => 'b', 'price' => 20, 'inventory' => 0 ),
		) ) );

		$this->assertTrue( $rows[0]['inStock'] );
		$this->assertFalse( $rows[1]['inStock'] );
	}

	public function test_ignores_non_array_listing_entries(): void {
		$rows = \Kalahamoon_Listings::normalize( $this->product( array(
			'garbage',
			array( 'platform' => 'a', 'price' => 10 ),
		) ) );

		$this->assertCount( 1, $rows );
	}

	public function test_platform_is_lowercased(): void {
		$rows = \Kalahamoon_Listings::normalize( $this->product( array(
			array( 'platform' => 'DigiKala', 'price' => 10 ),
		) ) );

		$this->assertSame( 'digikala', $rows[0]['platform'] );
	}

	public function test_public_rows_require_verified_active_and_fresh_listing_data(): void {
		$fresh = gmdate( 'c', self::NOW - 60 );
		$stale = gmdate( 'c', self::NOW - 24 * 3600 - 1 );
		$base  = array(
			'platform'        => 'basalam',
			'price'           => 100,
			'listingUrl'      => 'https://merchant.example/item',
			'status'          => 'ACTIVE',
			'publicationState'=> 'VERIFIED',
			'lastSyncedAt'    => $fresh,
		);

		$rows = \Kalahamoon_Listings::normalize_public( $this->product( array(
			$base,
			array_merge( $base, array( 'platform' => 'draft', 'publicationState' => 'DRAFT' ) ),
			array_merge( $base, array( 'platform' => 'inactive', 'status' => 'INACTIVE' ) ),
			array_merge( $base, array( 'platform' => 'stale', 'lastSyncedAt' => $stale ) ),
			array_merge( $base, array( 'platform' => 'unsafe', 'listingUrl' => 'http://merchant.example/item' ) ),
		) ), self::NOW );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'basalam', $rows[0]['platform'] );
	}
}
