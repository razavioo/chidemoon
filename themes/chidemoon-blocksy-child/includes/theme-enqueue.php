<?php
/**
 * Chidemoon Blocksy child theme module.
 *
 * Loaded by functions.php; do not load directly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function chidemoon_blocksy_enqueue_styles(): void {
	$version = (string) wp_get_theme()->get( 'Version' );

	// Static assets change between sealed releases while the theme header
	// version may not; bust edge/browser caches with the file's own mtime.
	$asset_version = static function ( string $relative ) use ( $version ): string {
		$mtime = @filemtime( get_stylesheet_directory() . '/' . $relative );
		return $mtime ? $version . '.' . $mtime : $version;
	};

	wp_enqueue_style(
		'chidemoon-typography',
		get_stylesheet_directory_uri() . '/assets/css/typography.css',
		array(),
		$asset_version( 'assets/css/typography.css' )
	);
	wp_enqueue_style(
		'chidemoon-blocksy-child',
		get_stylesheet_uri(),
		array( 'chidemoon-typography' ),
		$version
	);
	wp_enqueue_style(
		'chidemoon-editorial-refresh',
		get_stylesheet_directory_uri() . '/assets/css/editorial-refresh.css',
		array( 'chidemoon-blocksy-child' ),
		$asset_version( 'assets/css/editorial-refresh.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'chidemoon_blocksy_enqueue_styles', 20 );

/**
 * Search forms render their submit control disabled until the visitor types
 * a phrase; this tiny companion script keeps the control in sync with the
 * field and blocks empty submissions (including Enter) as a hard stop.
 */
function chidemoon_blocksy_enqueue_scripts(): void {
	$relative = 'assets/js/search-form.js';
	$mtime    = @filemtime( get_stylesheet_directory() . '/' . $relative );

	wp_enqueue_script(
		'chidemoon-search-form',
		get_stylesheet_directory_uri() . '/' . $relative,
		array(),
		(string) wp_get_theme()->get( 'Version' ) . ( $mtime ? '.' . $mtime : '' ),
		array( 'strategy' => 'defer' )
	);
}
add_action( 'wp_enqueue_scripts', 'chidemoon_blocksy_enqueue_scripts', 20 );
