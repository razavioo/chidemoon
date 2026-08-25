<?php

namespace Kalahamoon\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Audit every block render.php for unsafe output patterns.
 *
 * This is a static, best-effort grep — not a full parser. It catches the
 * most common injection footguns:
 *
 *  - Bare `echo $foo` of dynamic data without an escape helper.
 *  - `echo $foo['x']` of array values without esc_html/esc_attr/esc_url.
 *  - `<?= $foo ?>` short-echo of dynamic data.
 *
 * Authors can opt-out per line with `// phpcs:ignore Kalahamoon.Escape` when an
 * already-escaped helper (Kalahamoon_Placeholder::image, get_block_wrapper_attributes,
 * wp_json_encode, etc.) provides the safety.
 */
class RenderEscapeAuditTest extends TestCase {

	private const SAFE_HELPERS = array(
		'esc_html',
		'esc_attr',
		'esc_url',
		'esc_js',
		'esc_textarea',
		'wp_kses',
		'wp_kses_post',
		'wp_json_encode',
		'json_encode',
		'sanitize_html_class',
		'absint',
		'intval',
		'floatval',
		'wpautop',
		'do_blocks',
		'apply_filters',
		'get_block_wrapper_attributes',
		'Kalahamoon_Placeholder',
		'Kalahamoon_Link_Builder',
		'human_time_diff',
	);

	/** @return array<string, array{0: string}> */
	public static function renderFileProvider(): array {
		$dir   = dirname( __DIR__, 2 ) . '/blocks';
		$files = glob( $dir . '/*/render.php' );
		$cases = array();
		foreach ( $files as $file ) {
			$cases[ basename( dirname( $file ) ) ] = array( $file );
		}
		return $cases;
	}

	/**
	 * Variables assigned earlier in the file via a known-safe helper. The audit
	 * records these as it scans and treats subsequent `echo $name` as safe.
	 */
	private const SAFE_ASSIGNERS = array(
		'get_block_wrapper_attributes',
		'esc_html', 'esc_attr', 'esc_url', 'esc_js', 'esc_textarea',
		'wp_kses', 'wp_kses_post', 'wp_json_encode', 'json_encode',
		'sanitize_html_class', 'absint', 'intval', 'floatval', 'wpautop',
	);

	#[DataProvider('renderFileProvider')]
	public function test_render_escapes_dynamic_output( string $path ): void {
		$contents = file_get_contents( $path );
		$this->assertNotFalse( $contents );
		$lines = explode( "\n", $contents );

		// Pass 1: identify variables assigned from safe helpers.
		$safeVars = array();
		foreach ( $lines as $line ) {
			if ( ! preg_match( '/\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=/', $line, $m ) ) continue;
			$var = $m[1];
			foreach ( self::SAFE_ASSIGNERS as $helper ) {
				if ( strpos( $line, $helper . '(' ) !== false ) {
					$safeVars[ $var ] = true;
					break;
				}
			}
		}

		// Pass 2: flag suspicious echos.
		$offenders = array();
		foreach ( $lines as $i => $line ) {
			if ( preg_match( '/^\s*(?:\/\/|#|\*)/', $line ) ) continue;
			if ( strpos( $line, 'phpcs:ignore Kalahamoon.Escape' ) !== false ) continue;

			$bareEcho = preg_match( '/echo\s+\$([a-zA-Z_][a-zA-Z0-9_]*)(?:\[[^\]]+\])?\s*[;\.]/', $line, $m );
			$shortEcho = preg_match( '/<\?=\s*\$/', $line );

			if ( ! $bareEcho && ! $shortEcho ) continue;

			// echo $var where $var was assigned from a known-safe helper → safe.
			if ( $bareEcho && isset( $safeVars[ $m[1] ] ) ) continue;

			// Same-line safe helper usage (e.g. `echo esc_html( $x )`).
			$looksSafe = false;
			foreach ( self::SAFE_HELPERS as $helper ) {
				if ( strpos( $line, $helper . '(' ) !== false || strpos( $line, $helper . '::' ) !== false ) {
					$looksSafe = true;
					break;
				}
			}
			if ( $looksSafe ) continue;

			// Ternary that yields only literal strings (e.g. `echo $cond ? 'a' : ''`).
			if ( preg_match( '/echo\s+\$[a-zA-Z_][a-zA-Z0-9_]*\s*\?\s*[\'"][^\'"]*[\'"]\s*:\s*[\'"][^\'"]*[\'"]/', $line ) ) continue;

			$offenders[] = sprintf( '%s:%d  %s', basename( dirname( $path ) ) . '/' . basename( $path ), $i + 1, trim( $line ) );
		}

		$this->assertEmpty(
			$offenders,
			"Unescaped dynamic output detected:\n" . implode( "\n", $offenders )
		);
	}

	#[DataProvider('renderFileProvider')]
	public function test_render_uses_abspath_guard( string $path ): void {
		$contents = file_get_contents( $path );
		$this->assertStringContainsString(
			"defined( 'ABSPATH' )",
			$contents,
			"render.php $path is missing the ABSPATH guard"
		);
	}
}
