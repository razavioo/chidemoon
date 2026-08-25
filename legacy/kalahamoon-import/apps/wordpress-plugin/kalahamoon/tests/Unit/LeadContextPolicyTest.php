<?php

namespace Kalahamoon\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/rest/class-kalahamoon-rest-controller.php';

final class LeadContextPolicyTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->alias( static fn( string $text ): string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( string $value ): string => strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $value ) ?? '' )
		);
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $value ): string => trim( strip_tags( $value ) ) );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_accepts_at_most_twelve_scalar_context_fields(): void {
		$context = \Kalahamoon_REST_Controller::normalize_lead_context(
			array(
				'productId' => 'product-1',
				'pageType'  => 'guide',
				'quantity'  => 2,
				'consented' => true,
				'note'      => null,
			)
		);

		$this->assertIsArray( $context );
		$this->assertSame( 'product-1', $context['productid'] );
		$this->assertSame( 2, $context['quantity'] );
	}

	public function test_rejects_non_object_nested_oversized_or_non_finite_context(): void {
		$too_many = array_fill_keys( array_map( static fn( int $index ): string => 'key' . $index, range( 1, 13 ) ), 'value' );

		foreach (
			array(
				'not an object',
				array( 'nested' => array( 'unsafe' => true ) ),
				$too_many,
				array( 'long' => str_repeat( 'x', 501 ) ),
				array( 'infinite' => INF ),
			)
			as $invalid
		) {
			$this->assertInstanceOf( \WP_Error::class, \Kalahamoon_REST_Controller::normalize_lead_context( $invalid ) );
		}
	}
}
