<?php

namespace Kalahamoon\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validates every PHP pattern file in the plugin.
 *
 * Checks:
 *  - Each file declares the canonical pattern header fields (Title, Slug,
 *    Categories) — without them WordPress silently skips registration.
 *  - Slug follows the `kalahamoon/` namespace convention.
 *  - File has a non-empty body after the header (otherwise the pattern
 *    registers as blank in the inserter).
 *  - Body parses as valid block markup (every opening `<!-- wp:` has a
 *    matching closing tag).
 */
class PatternRegistrationTest extends TestCase {

	/** @return array<string, array{0: string}> */
	public static function patternFileProvider(): array {
		$dir   = dirname( __DIR__, 2 ) . '/patterns';
		$files = glob( $dir . '/*.php' );
		$cases = array();
		foreach ( $files as $file ) {
			$cases[ basename( $file, '.php' ) ] = array( $file );
		}
		return $cases;
	}

	#[DataProvider('patternFileProvider')]
	public function test_pattern_declares_required_headers( string $path ): void {
		$contents = file_get_contents( $path );
		$this->assertNotFalse( $contents, "Cannot read $path" );

		foreach ( array( 'Title', 'Slug', 'Categories' ) as $header ) {
			$this->assertMatchesRegularExpression(
				'/\*\s*' . preg_quote( $header, '/' ) . '\s*:\s*\S/',
				$contents,
				"Pattern $path is missing the '$header' header"
			);
		}
	}

	#[DataProvider('patternFileProvider')]
	public function test_pattern_slug_uses_kalahamoon_namespace( string $path ): void {
		$contents = file_get_contents( $path );
		if ( preg_match( '/\*\s*Slug\s*:\s*(\S+)/', $contents, $m ) ) {
			$this->assertStringStartsWith(
				'kalahamoon/',
				$m[1],
				"Pattern slug '{$m[1]}' must start with 'kalahamoon/' in $path"
			);
		} else {
			$this->fail( "Could not extract Slug header from $path" );
		}
	}

	#[DataProvider('patternFileProvider')]
	public function test_pattern_has_non_empty_body( string $path ): void {
		$contents = file_get_contents( $path );
		// Strip the leading PHP header block + guard line; what remains is the
		// pattern's HTML/block markup.
		$body = preg_replace( '/^.*\?>\s*/s', '', $contents, 1 );
		$this->assertNotEmpty( trim( (string) $body ), "Pattern $path renders an empty body" );
	}

	#[DataProvider('patternFileProvider')]
	public function test_pattern_block_tags_balance( string $path ): void {
		$contents = file_get_contents( $path );
		// Count opening `<!-- wp:foo -->` (non-self-closing) and matching `<!-- /wp:foo -->`.
		preg_match_all( '/<!--\s+wp:([a-z0-9-\/]+)(?![^>]*\/-->)[^>]*-->/i', $contents, $opens );
		preg_match_all( '/<!--\s+\/wp:([a-z0-9-\/]+)\s+-->/i', $contents, $closes );

		sort( $opens[1] );
		sort( $closes[1] );
		$this->assertSame(
			$opens[1],
			$closes[1],
			"Unbalanced block tags in $path (opens vs closes differ)"
		);
	}

	public function test_all_expected_publishing_patterns_are_present(): void {
		$dir      = dirname( __DIR__, 2 ) . '/patterns';
		$expected = array(
			'compare-side-by-side',
			'page-category',
			'page-product-review',
		);
		foreach ( $expected as $slug ) {
			$this->assertFileExists(
				"$dir/$slug.php",
				"Expected publishing pattern '$slug.php' is missing"
			);
		}
	}
}
