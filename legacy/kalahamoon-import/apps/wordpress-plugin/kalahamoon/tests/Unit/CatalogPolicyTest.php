<?php

namespace Kalahamoon\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/core/class-kalahamoon-catalog-policy.php';
require_once dirname( __DIR__, 2 ) . '/includes/core/class-kalahamoon-product-cache.php';

final class CatalogPolicyTest extends TestCase {
	private const NOW = 1785888000;

	private function product( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'               => 'product-1',
				'title'            => 'Editorial chair',
				'imageUrl'         => 'https://merchant.example/chair.jpg',
				'listingUrl'       => 'https://merchant.example/chair',
				'price'            => 1250000,
				'publicationState' => 'VERIFIED',
				'status'           => 'active',
				'source'           => 'synced',
				'lastSynced'       => gmdate( 'Y-m-d H:i:s', self::NOW - 3600 ),
			),
			$overrides
		);
	}

	public function test_normalizes_catalog_authority_without_accepting_unknown_values(): void {
		$this->assertSame( 'hybrid', \Kalahamoon_Catalog_Policy::normalize_authority( '' ) );
		$this->assertSame( 'remote', \Kalahamoon_Catalog_Policy::normalize_authority( 'REMOTE' ) );
		$this->assertSame( 'local', \Kalahamoon_Catalog_Policy::normalize_authority( 'local' ) );
		$this->assertSame( 'hybrid', \Kalahamoon_Catalog_Policy::normalize_authority( 'unsafe' ) );
	}

	public function test_authority_controls_which_product_sources_can_be_public(): void {
		$this->assertTrue( \Kalahamoon_Catalog_Policy::source_allowed( 'synced', 'remote' ) );
		$this->assertFalse( \Kalahamoon_Catalog_Policy::source_allowed( 'manual', 'remote' ) );
		$this->assertTrue( \Kalahamoon_Catalog_Policy::source_allowed( 'manual', 'local' ) );
		$this->assertFalse( \Kalahamoon_Catalog_Policy::source_allowed( 'synced', 'local' ) );
		$this->assertTrue( \Kalahamoon_Catalog_Policy::source_allowed( 'manual', 'hybrid' ) );
		$this->assertTrue( \Kalahamoon_Catalog_Policy::source_allowed( 'synced', 'hybrid' ) );
	}

	public function test_kalahamoon_projection_does_not_repeat_legacy_source_or_freshness_gates(): void {
		$projection = array(
			'catalogProjection' => true,
			'publicReady'       => true,
			'priceVisible'      => false,
			'priceFreshness'    => 'hidden_stale',
			'price'             => null,
			// These deliberately conflict with old cache facts. The consumer must
			// trust Kalahamoon's published projection rather than re-decide it.
			'publicationState'  => 'CAPTURED',
			'status'            => 'inactive',
			'source'            => 'manual',
			'lastSynced'        => gmdate( 'c', self::NOW - 365 * 86400 ),
		);

		$result = \Kalahamoon_Catalog_Policy::evaluate( $projection, self::NOW, 'remote' );

		$this->assertTrue( $result['publicReady'] );
		$this->assertFalse( $result['priceVisible'] );
		$this->assertSame( 'hidden_stale', $result['freshness'] );
		$this->assertSame( array(), $result['readinessIssues'] );
	}

	public function test_price_freshness_boundaries_are_deterministic(): void {
		$fresh = \Kalahamoon_Catalog_Policy::evaluate(
			$this->product( array( 'lastSynced' => gmdate( 'Y-m-d H:i:s', self::NOW - 12 * 3600 ) ) ),
			self::NOW,
			'remote'
		);
		$stale = \Kalahamoon_Catalog_Policy::evaluate(
			$this->product( array( 'lastSynced' => gmdate( 'Y-m-d H:i:s', self::NOW - 12 * 3600 - 1 ) ) ),
			self::NOW,
			'remote'
		);
		$hidden_price = \Kalahamoon_Catalog_Policy::evaluate(
			$this->product( array( 'lastSynced' => gmdate( 'Y-m-d H:i:s', self::NOW - 24 * 3600 - 1 ) ) ),
			self::NOW,
			'remote'
		);
		$expired = \Kalahamoon_Catalog_Policy::evaluate(
			$this->product( array( 'lastSynced' => gmdate( 'Y-m-d H:i:s', self::NOW - 72 * 3600 - 1 ) ) ),
			self::NOW,
			'remote'
		);

		$this->assertSame( 'fresh', $fresh['freshness'] );
		$this->assertTrue( $fresh['priceVisible'] );
		$this->assertSame( 'stale', $stale['freshness'] );
		$this->assertTrue( $stale['priceVisible'] );
		$this->assertSame( 'price_hidden', $hidden_price['freshness'] );
		$this->assertFalse( $hidden_price['priceVisible'] );
		$this->assertTrue( $hidden_price['publicReady'] );
		$this->assertSame( 'expired', $expired['freshness'] );
		$this->assertFalse( $expired['publicReady'] );
	}

	public function test_public_readiness_rejects_incomplete_or_unreviewed_products(): void {
		foreach (
			array(
				array( 'id' => '' ),
				array( 'title' => '' ),
				array( 'imageUrl' => '' ),
				array( 'listingUrl' => '' ),
				array( 'publicationState' => 'DRAFT' ),
				array( 'status' => 'inactive' ),
				array( 'source' => 'manual' ),
			) as $invalid
		) {
			$result = \Kalahamoon_Catalog_Policy::evaluate( $this->product( $invalid ), self::NOW, 'remote' );
			$this->assertFalse( $result['publicReady'], (string) json_encode( $invalid ) );
			$this->assertNotEmpty( $result['readinessIssues'] );
		}
	}

	public function test_upstream_listing_review_issues_keep_a_product_out_of_the_public_catalog(): void {
		$result = \Kalahamoon_Catalog_Policy::evaluate(
			$this->product( array( 'publicationReadinessIssues' => array( 'PRICE_REQUIRED' ) ) ),
			self::NOW,
			'remote'
		);

		$this->assertFalse( $result['publicReady'] );
		$this->assertContains( 'listing_needs_review', $result['readinessIssues'] );
	}

	public function test_missing_or_future_sync_times_fail_closed(): void {
		$missing = \Kalahamoon_Catalog_Policy::evaluate( $this->product( array( 'lastSynced' => '' ) ), self::NOW, 'remote' );
		$future  = \Kalahamoon_Catalog_Policy::evaluate(
			$this->product( array( 'lastSynced' => gmdate( 'Y-m-d H:i:s', self::NOW + 3600 ) ) ),
			self::NOW,
			'remote'
		);

		$this->assertFalse( $missing['publicReady'] );
		$this->assertFalse( $future['publicReady'] );
	}

	public function test_public_product_and_image_links_require_https(): void {
		$insecure_image = \Kalahamoon_Catalog_Policy::evaluate(
			$this->product( array( 'imageUrl' => 'http://merchant.example/chair.jpg' ) ),
			self::NOW,
			'remote'
		);
		$insecure_listing = \Kalahamoon_Catalog_Policy::evaluate(
			$this->product( array( 'listingUrl' => 'http://merchant.example/chair' ) ),
			self::NOW,
			'remote'
		);

		$this->assertFalse( $insecure_image['publicReady'] );
		$this->assertContains( 'invalid_image_url', $insecure_image['readinessIssues'] );
		$this->assertFalse( $insecure_listing['publicReady'] );
		$this->assertContains( 'invalid_listing_url', $insecure_listing['readinessIssues'] );
	}

	public function test_invalid_or_future_upstream_timestamps_are_not_rewritten_as_fresh(): void {
		$method = new \ReflectionMethod( \Kalahamoon_Product_Cache::class, 'normalize_source_timestamp' );
		$valid  = gmdate( 'c', time() - 60 );

		$this->assertSame( 0, $method->invoke( null, '' ) );
		$this->assertSame( 0, $method->invoke( null, 'not-a-date' ) );
		$this->assertSame( 0, $method->invoke( null, gmdate( 'c', time() + 3600 ) ) );
		$this->assertSame( strtotime( $valid ), $method->invoke( null, $valid ) );
	}
}
