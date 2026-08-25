<?php

namespace Kalahamoon\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validates every block.json file in the plugin.
 *
 * Checks:
 *  - File exists and is valid JSON.
 *  - Required WP block schema fields are present.
 *  - `name` follows the `kalahamoon/{slug}` convention.
 *  - Referenced render/script/style files exist on disk.
 */
class BlockJsonTest extends TestCase {

	private static string $blocksDir;

	/** @return array<string, array{0: string}> */
	public static function blockJsonProvider(): array {
		self::$blocksDir = dirname( __DIR__, 2 ) . '/blocks';
		$files           = glob( self::$blocksDir . '/*/block.json' );
		$cases           = array();
		foreach ( $files as $file ) {
			$slug           = basename( dirname( $file ) );
			$cases[ $slug ] = array( $file );
		}
		return $cases;
	}

	#[DataProvider('blockJsonProvider')]
	public function test_block_json_is_valid_json( string $path ): void {
		$raw = file_get_contents( $path );
		$this->assertNotFalse( $raw, "Cannot read $path" );

		$data = json_decode( $raw, true );
		$this->assertSame( JSON_ERROR_NONE, json_last_error(), "Invalid JSON in $path: " . json_last_error_msg() );
		$this->assertIsArray( $data );
	}

	#[DataProvider('blockJsonProvider')]
	public function test_block_has_required_schema_fields( string $path ): void {
		$data = json_decode( file_get_contents( $path ), true );

		$required = array( 'apiVersion', 'name', 'version', 'title', 'category', 'textdomain' );
		foreach ( $required as $field ) {
			$this->assertArrayHasKey( $field, $data, "Missing '$field' in $path" );
			$this->assertNotEmpty( $data[ $field ], "Empty '$field' in $path" );
		}
	}

	#[DataProvider('blockJsonProvider')]
	public function test_block_name_follows_kalahamoon_convention( string $path ): void {
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertStringStartsWith(
			'kalahamoon/',
			$data['name'],
			"Block name '{$data['name']}' must start with 'kalahamoon/' in $path"
		);
	}

	#[DataProvider('blockJsonProvider')]
	public function test_block_category_is_kalahamoon( string $path ): void {
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertSame(
			'kalahamoon',
			$data['category'],
			"Block category should be 'kalahamoon' in $path"
		);
	}

	#[DataProvider('blockJsonProvider')]
	public function test_referenced_render_file_exists( string $path ): void {
		$data = json_decode( file_get_contents( $path ), true );
		if ( empty( $data['render'] ) ) {
			$this->markTestSkipped( "No render file declared in $path" );
		}

		$render_ref  = $data['render'];
		$this->assertStringStartsWith( 'file:./', $render_ref, "render must use 'file:./' prefix in $path" );

		$render_file = dirname( $path ) . '/' . substr( $render_ref, 7 );
		$this->assertFileExists( $render_file, "render file '$render_file' referenced in $path does not exist" );
	}

	#[DataProvider('blockJsonProvider')]
	public function test_referenced_editor_script_exists( string $path ): void {
		$data = json_decode( file_get_contents( $path ), true );
		if ( empty( $data['editorScript'] ) ) {
			$this->markTestSkipped( "No editorScript declared in $path" );
		}

		$script_ref  = $data['editorScript'];
		$this->assertStringStartsWith( 'file:./', $script_ref );

		$script_file = dirname( $path ) . '/' . substr( $script_ref, 7 );
		$this->assertFileExists( $script_file, "editorScript '$script_file' referenced in $path does not exist" );
	}

	#[DataProvider('blockJsonProvider')]
	public function test_referenced_style_file_exists( string $path ): void {
		$data = json_decode( file_get_contents( $path ), true );
		if ( empty( $data['style'] ) ) {
			$this->markTestSkipped( "No style file declared in $path" );
		}

		$style_ref  = $data['style'];
		$this->assertStringStartsWith( 'file:./', $style_ref );

		$style_file = dirname( $path ) . '/' . substr( $style_ref, 7 );
		$this->assertFileExists( $style_file, "style '$style_file' referenced in $path does not exist" );
	}

	#[DataProvider('blockJsonProvider')]
	public function test_block_api_version_is_supported( string $path ): void {
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertSame(
			3,
			(int) $data['apiVersion'],
			"apiVersion must be 3 in $path"
		);
	}

	#[DataProvider('blockJsonProvider')]
	public function test_referenced_view_script_exists( string $path ): void {
		$data = json_decode( file_get_contents( $path ), true );
		if ( empty( $data['viewScript'] ) ) {
			$this->markTestSkipped( "No viewScript declared in $path" );
		}

		$script_ref = $data['viewScript'];
		$this->assertStringStartsWith( 'file:./', $script_ref );

		$script_file = dirname( $path ) . '/' . substr( $script_ref, 7 );
		$this->assertFileExists( $script_file, "viewScript '$script_file' referenced in $path does not exist" );
	}

	public function test_interactive_blocks_declare_view_scripts(): void {
		$dir = dirname( __DIR__, 2 ) . '/blocks';
		foreach ( array( 'product-catalog', 'shop-the-look' ) as $slug ) {
			$data = json_decode( (string) file_get_contents( "$dir/$slug/block.json" ), true );
			$this->assertSame( 'file:./view.js', $data['viewScript'] ?? null, "Interactive block '$slug' should load view.js only when rendered." );
			$this->assertFileExists( "$dir/$slug/view.asset.php", "Interactive block '$slug' should include view.asset.php metadata." );
		}
	}

	public function test_all_expected_blocks_are_present(): void {
		$dir        = dirname( __DIR__, 2 ) . '/blocks';
		$expected   = array(
			// Core merchant/product/review/affiliate publishing blocks.
			'product-box',
			'product-catalog',
			'product-comparison',
			'product-grid',
			'comparison-table',
			'cta-button',
			'pros-cons',
			'rating-box',
			'ai-compare',
			'faq',
			// Secondary merchant/editorial support blocks kept for compatibility.
			'shop-the-look',
			'testimonials',
			// New conversion/affiliate blocks.
			'price-comparison',
			'affiliate-disclosure',
			'lead-form',
			'price-alert',
		);
		foreach ( $expected as $slug ) {
			$this->assertFileExists(
				"$dir/$slug/block.json",
				"Expected block '$slug' is missing its block.json"
			);
		}
	}

	#[DataProvider('blockJsonProvider')]
	public function test_block_has_example_for_inserter_preview( string $path ): void {
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertArrayHasKey(
			'example',
			$data,
			"block.json $path should declare an 'example' so the Gutenberg inserter renders a preview"
		);
		$this->assertIsArray( $data['example'], "'example' must be an object in $path" );
	}
}
