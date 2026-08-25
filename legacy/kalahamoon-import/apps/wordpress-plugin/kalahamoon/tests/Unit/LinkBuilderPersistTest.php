<?php

namespace Kalahamoon\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Recording fake for the global $wpdb used by persist_panel_link.
 */
class FakeWpdb {
	public string $prefix = 'wp_';
	public $get_var_return = null;
	public $get_row_return = null;
	public array $inserts = array();
	public array $updates = array();

	public function prepare( $query, ...$args ) {
		return $query;
	}

	public function get_var( $query ) {
		return $this->get_var_return;
	}

	public function get_row( $query, $output = null ) {
		return $this->get_row_return;
	}

	public function insert( $table, $data ) {
		$this->inserts[] = array( 'table' => $table, 'data' => $data );
		return 1;
	}

	public function update( $table, $data, $where ) {
		$this->updates[] = array( 'table' => $table, 'data' => $data, 'where' => $where );
		return 1;
	}
}

/**
 * Tests for Kalahamoon_Link_Builder::persist_panel_link — verifies the local
 * affiliate-link mirror is written with slug/destination_url so the /go/{slug}
 * cloaker can resolve panel-issued links.
 */
class LinkBuilderPersistTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}
		Functions\when( 'current_time' )->justReturn( '2026-01-01 00:00:00' );
		Functions\when( '__' )->alias( static fn( string $text ): string => $text );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_inserts_new_row_with_core_fields(): void {
		$wpdb = new FakeWpdb();
		$wpdb->get_var_return = null; // no existing row
		$GLOBALS['wpdb'] = $wpdb;

		\Kalahamoon_Link_Builder::persist_panel_link( 'prod-1', 'https://site.test/go/abc', 'link-9', 'digikala' );

		$this->assertCount( 1, $wpdb->inserts );
		$this->assertCount( 0, $wpdb->updates );

		$data = $wpdb->inserts[0]['data'];
		$this->assertSame( 'prod-1', $data['product_id'] );
		$this->assertSame( 'https://site.test/go/abc', $data['kalahamoon_short_url'] );
		$this->assertSame( 'link-9', $data['kalahamoon_link_id'] );
		$this->assertSame( 'digikala', $data['provider'] );
		$this->assertSame( 'active', $data['status'] );
	}

	public function test_persists_optional_slug_and_destination(): void {
		$wpdb = new FakeWpdb();
		$wpdb->get_var_return = null;
		$GLOBALS['wpdb'] = $wpdb;

		\Kalahamoon_Link_Builder::persist_panel_link(
			'prod-2',
			'https://site.test/go/xyz',
			'link-10',
			'bakalahamoon',
			array(
				'slug'            => 'xyz',
				'destination_url' => 'https://shop.test/p/2',
				'campaign_title'  => 'My Campaign',
			)
		);

		$data = $wpdb->inserts[0]['data'];
		$this->assertSame( 'xyz', $data['slug'] );
		$this->assertSame( 'https://shop.test/p/2', $data['destination_url'] );
		$this->assertSame( 'My Campaign', $data['campaign_title'] );
	}

	public function test_skips_empty_optional_fields(): void {
		$wpdb = new FakeWpdb();
		$wpdb->get_var_return = null;
		$GLOBALS['wpdb'] = $wpdb;

		\Kalahamoon_Link_Builder::persist_panel_link(
			'prod-3',
			'https://site.test/go/q',
			'link-11',
			'bakalahamoon',
			array( 'slug' => '', 'destination_url' => '' )
		);

		$data = $wpdb->inserts[0]['data'];
		$this->assertArrayNotHasKey( 'slug', $data );
		$this->assertArrayNotHasKey( 'destination_url', $data );
	}

	public function test_updates_existing_row_keyed_by_product(): void {
		$wpdb = new FakeWpdb();
		$wpdb->get_var_return = 42; // existing row id
		$GLOBALS['wpdb'] = $wpdb;

		\Kalahamoon_Link_Builder::persist_panel_link( 'prod-4', 'https://site.test/go/u', 'link-12', 'digikala' );

		$this->assertCount( 0, $wpdb->inserts );
		$this->assertCount( 1, $wpdb->updates );
		$this->assertSame( array( 'product_id' => 'prod-4' ), $wpdb->updates[0]['where'] );
		// On update we must NOT inject product_id into the SET clause.
		$this->assertArrayNotHasKey( 'product_id', $wpdb->updates[0]['data'] );
	}

	public function test_get_product_affiliate_url_returns_local_go_slug_when_no_panel_url(): void {
		$wpdb = new FakeWpdb();
		$wpdb->get_row_return = array(
			'kalahamoon_short_url' => '',
			'slug'                 => 'plate-link',
		);
		$GLOBALS['wpdb'] = $wpdb;
		Functions\when( 'home_url' )->alias( static fn( string $path = '' ): string => 'https://site.test' . $path );

		$url = \Kalahamoon_Link_Builder::get_product_affiliate_url( array(
			'id'         => 'prod-5',
			'listingUrl' => 'https://market.test/raw',
		) );

		$this->assertSame( 'https://site.test/go/plate-link', $url );
	}

	public function test_get_product_affiliate_url_does_not_double_encode_percent_encoded_slug(): void {
		$wpdb = new FakeWpdb();
		$wpdb->get_row_return = array(
			'kalahamoon_short_url' => '',
			'slug'                 => '%d9%85%d8%a8%d9%84',
		);
		$GLOBALS['wpdb'] = $wpdb;
		Functions\when( 'home_url' )->alias( static fn( string $path = '' ): string => 'https://site.test' . $path );

		$url = \Kalahamoon_Link_Builder::get_product_affiliate_url( array(
			'id'         => 'prod-persian',
			'listingUrl' => 'https://market.test/raw',
		) );

		$this->assertSame( 'https://site.test/go/%D9%85%D8%A8%D9%84', $url );
		$this->assertStringNotContainsString( '%25', $url );
	}

	public function test_get_product_affiliate_url_keeps_panel_short_url_when_available(): void {
		$wpdb = new FakeWpdb();
		$wpdb->get_row_return = array(
			'kalahamoon_short_url' => 'https://app.kalahamoon.com/go/panel-link',
			'slug'                 => 'local-link',
		);
		$GLOBALS['wpdb'] = $wpdb;

		$url = \Kalahamoon_Link_Builder::get_product_affiliate_url( array(
			'id'         => 'prod-6',
			'listingUrl' => 'https://market.test/raw',
		) );

		$this->assertSame( 'https://app.kalahamoon.com/go/panel-link', $url );
	}

	public function test_resolved_panel_link_is_affiliate_only_when_mirrored(): void {
		$wpdb = new FakeWpdb();
		$wpdb->get_row_return = array(
			'id'                   => 'link-27',
			'kalahamoon_short_url' => 'https://app.kalahamoon.com/go/panel-link',
			'slug'                 => 'panel-link',
		);
		$GLOBALS['wpdb'] = $wpdb;

		$destination = \Kalahamoon_Link_Builder::resolve_product_destination( array(
			'id'         => 'prod-7',
			'listingUrl' => 'https://market.test/raw',
		) );

		$this->assertSame( 'https://app.kalahamoon.com/go/panel-link', $destination['url'] );
		$this->assertTrue( $destination['isAffiliate'] );
		$this->assertSame( 'link-27', $destination['linkId'] );
	}

	public function test_resolved_raw_listing_remains_a_direct_product_link(): void {
		$wpdb = new FakeWpdb();
		$GLOBALS['wpdb'] = $wpdb;

		$destination = \Kalahamoon_Link_Builder::resolve_product_destination( array(
			'id'         => 'prod-8',
			'listingUrl' => 'https://market.test/raw',
		) );

		$this->assertSame( 'https://market.test/raw', $destination['url'] );
		$this->assertFalse( $destination['isAffiliate'] );
		$this->assertSame( '', $destination['linkId'] );
	}

	public function test_public_link_attributes_add_sponsored_only_for_affiliate_links(): void {
		$affiliate = \Kalahamoon_Link_Builder::public_link_attributes( array(
			'url'         => 'https://app.kalahamoon.com/go/panel-link',
			'isAffiliate' => true,
			'linkId'      => 'link-27',
		) );
		$direct = \Kalahamoon_Link_Builder::public_link_attributes( array(
			'url'         => 'https://market.test/raw',
			'isAffiliate' => false,
			'linkId'      => '',
		) );

		$this->assertSame( 'sponsored nofollow noopener', $affiliate['rel'] );
		$this->assertStringContainsString( 'kalahamoon-affiliate-link', $affiliate['class'] );
		$this->assertSame( 'noopener', $direct['rel'] );
		$this->assertSame( 'kalahamoon-product-link', $direct['class'] );
	}

	public function test_persian_marketplace_search_url_is_normalized_as_clickable(): void {
		$url = \Kalahamoon_Link_Builder::normalize_clickable_url( 'https://digikala.com/search/?q=میز+تحریر+چوبی' );

		$this->assertSame( 'https://digikala.com/search/?q=%D9%85%DB%8C%D8%B2%20%D8%AA%D8%AD%D8%B1%DB%8C%D8%B1%20%DA%86%D9%88%D8%A8%DB%8C', $url );
		$this->assertTrue( \Kalahamoon_Link_Builder::is_clickable_url( 'https://digikala.com/search/?q=میز+تحریر+چوبی' ) );
	}
}
