<?php

namespace Kalahamoon\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/core/class-kalahamoon-image-policy.php';

final class ImagePolicyTest extends TestCase {
	private const PNG_1PX = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->alias( static fn( string $text ): string => $text );
		Functions\when( 'is_wp_error' )->alias( static fn( mixed $value ): bool => $value instanceof \WP_Error );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_accepts_a_small_real_raster_data_uri(): void {
		$result = \Kalahamoon_Image_Policy::decode_data_uri( self::PNG_1PX );

		$this->assertIsArray( $result );
		$this->assertSame( 'image/png', $result['mime'] );
		$this->assertSame( 'png', $result['extension'] );
		$this->assertSame( 1, $result['width'] );
		$this->assertSame( 1, $result['height'] );
	}

	public function test_rejects_non_raster_or_mismatched_data_uris(): void {
		$svg = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg"/>' );
		$mismatch = str_replace( 'image/png', 'image/jpeg', self::PNG_1PX );

		$this->assertInstanceOf( \WP_Error::class, \Kalahamoon_Image_Policy::decode_data_uri( $svg ) );
		$this->assertInstanceOf( \WP_Error::class, \Kalahamoon_Image_Policy::decode_data_uri( $mismatch ) );
	}

	public function test_remote_urls_require_https_and_reject_private_literal_addresses(): void {
		$this->assertNull( \Kalahamoon_Image_Policy::remote_url_issue( 'https://cdn.example.com/image.png' ) );
		$this->assertSame( 'https_required', \Kalahamoon_Image_Policy::remote_url_issue( 'http://cdn.example.com/image.png' ) );
		$this->assertSame( 'private_host', \Kalahamoon_Image_Policy::remote_url_issue( 'https://127.0.0.1/image.png' ) );
		$this->assertSame( 'private_host', \Kalahamoon_Image_Policy::remote_url_issue( 'https://[::1]/image.png' ) );
		$this->assertSame( 'private_host', \Kalahamoon_Image_Policy::remote_url_issue( 'https://localhost/image.png' ) );
	}
}
