<?php
/** Install approved direction 03 in native Elementor widgets. Preview or [apply]. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
if ( ! class_exists( 'Chidemoon_Core_Branding' ) || ! class_exists( '\Elementor\Plugin' ) ) { WP_CLI::error( 'Chidemoon Core and Elementor must be active.' ); }

$brand_apply = in_array( 'apply', $args ?? array(), true );
$brand_backup = '_chidemoon_before_brand_03_20261019';
if ( $brand_apply ) {
	add_option( 'chidemoon_before_brand_03_20261019', array( 'site_icon' => get_option( 'site_icon' ), 'custom_logo' => get_theme_mod( 'custom_logo' ) ), '', false );
}

// Import only these known, repository-owned assets. SVG uploads are not enabled.
$brand_media = static function ( string $file, string $title, int $width, int $height ) use ( $brand_apply ): int {
	$path = CHIDEMOON_CORE_DIR . 'assets/brand/' . $file;
	if ( ! is_readable( $path ) ) { WP_CLI::error( 'Missing brand asset: ' . $file ); }
	$digest = hash_file( 'sha256', $path );
	$existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_chidemoon_brand_digest', 'meta_value' => $digest ) );
	if ( $existing && is_file( get_attached_file( $existing[0] ) ) ) { return (int) $existing[0]; }
	WP_CLI::log( 'Import brand media: ' . $file );
	if ( ! $brand_apply ) { return 0; }
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$svg = str_ends_with( $file, '.svg' );
	if ( $svg ) {
		$directory = wp_upload_dir();
		if ( $directory['error'] ) { WP_CLI::error( $directory['error'] ); }
		$filename = wp_unique_filename( $directory['path'], $file );
		$target = $directory['path'] . '/' . $filename;
		if ( false === file_put_contents( $target, file_get_contents( $path ) ) ) { WP_CLI::error( 'Cannot copy trusted SVG.' ); }
		$upload = array( 'file' => $target, 'url' => $directory['url'] . '/' . $filename, 'error' => false );
	} else {
		$upload = wp_upload_bits( $file, null, file_get_contents( $path ) );
	}
	if ( ! empty( $upload['error'] ) ) { WP_CLI::error( $upload['error'] ); }
	$id = wp_insert_attachment( array( 'post_mime_type' => $svg ? 'image/svg+xml' : 'image/png', 'post_title' => $title, 'post_status' => 'inherit' ), $upload['file'], 0, true );
	if ( is_wp_error( $id ) ) { WP_CLI::error( $id->get_error_message() ); }
	$metadata = $svg ? array( 'width' => $width, 'height' => $height, 'file' => _wp_relative_upload_path( $upload['file'] ) ) : wp_generate_attachment_metadata( $id, $upload['file'] );
	wp_update_attachment_metadata( $id, $metadata );
	update_post_meta( $id, '_wp_attachment_image_alt', 'چیدمون' );
	update_post_meta( $id, '_chidemoon_brand_digest', $digest );
	return (int) $id;
};
$brand_logo_id = $brand_media( 'chidemoon-logo-fa.svg', 'چیدمون — لوگوی فارسی طرح ۳', 1147, 232 );
$brand_wordmark_id = $brand_media( 'chidemoon-wordmark-fa.svg', 'چیدمون — لوگوتایپ فارسی طرح ۳', 455, 119 );
$brand_symbol_id = $brand_media( 'chidemoon-symbol.svg', 'چیدمون — نشانهٔ مستقل طرح ۳', 292, 169 );
$brand_icon_id = $brand_media( 'chidemoon-icon-512.png', 'چیدمون — نشانهٔ سایت', 512, 512 );
$brand_changed = 0;

foreach ( array( 'site-header', 'site-footer' ) as $brand_slug ) {
	$brand_image_id = 'site-header' === $brand_slug ? $brand_wordmark_id : $brand_symbol_id;
	$brand_file = 'site-header' === $brand_slug ? 'chidemoon-wordmark-fa.svg' : 'chidemoon-symbol.svg';
	$brand_image_url = $brand_image_id ? wp_get_attachment_url( $brand_image_id ) : Chidemoon_Core_Branding::asset_url( $brand_file );
	$brand_post = get_page_by_path( 'chidemoon-' . $brand_slug, OBJECT, 'elementor_library' );
	if ( ! $brand_post ) { WP_CLI::warning( 'Template missing: ' . $brand_slug ); continue; }
	$brand_raw = get_post_meta( $brand_post->ID, '_elementor_data', true );
	$brand_nodes = is_string( $brand_raw ) ? json_decode( $brand_raw, true ) : null;
	if ( ! is_array( $brand_nodes ) ) { WP_CLI::error( 'Invalid template data: ' . $brand_slug ); }
	$brand_before = $brand_nodes;
	$brand_matches = 0;
	$brand_walk = static function ( array &$nodes, string $parent = '' ) use ( &$brand_walk, &$brand_matches, $brand_image_id, $brand_image_url ): void {
		foreach ( $nodes as &$node ) {
			$settings = $node['settings'] ?? array();
			$classes = $settings['_css_classes'] ?? '';
			$is_logo = preg_match( '/(?:^|\s)ch-brand-logo(?:\s|$)/', $classes );
			$is_header = str_contains( $parent, 'ch-brand-block' ) && 'theme-site-title' === ( $node['widgetType'] ?? '' );
			$is_footer = str_contains( $parent, 'ch-footer-about' ) && 'heading' === ( $node['widgetType'] ?? '' ) && 'چیدمون' === trim( $settings['title'] ?? '' );
			if ( $is_logo || $is_header || $is_footer ) {
				++$brand_matches;
				$node['widgetType'] = 'image';
				foreach ( array( 'title', 'header_size', 'title_color', '__dynamic__' ) as $key ) { unset( $settings[ $key ] ); }
				$classes = preg_replace( '/(?:^|\s)ch-brand(?=\s|$)/', ' ', $classes );
				$settings['_css_classes'] = trim( $classes . ( $is_logo ? '' : ' ch-brand-logo' ) );
				$settings['image'] = array( 'id' => $brand_image_id, 'url' => $brand_image_url );
				$settings['image_size'] = 'full';
				$settings['link_to'] = 'custom';
				$settings['link'] = array( 'url' => home_url( '/' ) );
				$settings['open_lightbox'] = 'no';
				$settings['align'] = 'right';
				$node['settings'] = $settings;
			}
			if ( ! empty( $node['elements'] ) ) { $brand_walk( $node['elements'], $settings['css_classes'] ?? '' ); }
		}
		unset( $node );
	};
	$brand_walk( $brand_nodes );
	if ( 1 !== $brand_matches ) { WP_CLI::error( 'Expected one brand slot in ' . $brand_slug . '; found ' . $brand_matches ); }
	if ( $brand_nodes === $brand_before ) { WP_CLI::log( $brand_slug . ': already uses direction 03.' ); continue; }
	++$brand_changed;
	WP_CLI::log( 'Update native logo widget: ' . $brand_slug . ' #' . $brand_post->ID );
	if ( $brand_apply ) {
		add_post_meta( $brand_post->ID, $brand_backup, wp_slash( $brand_raw ), true );
		add_post_meta( $brand_post->ID, '_chidemoon_before_brand_layout_20261019', wp_slash( $brand_raw ), true );
		$brand_doc = \Elementor\Plugin::$instance->documents->get( $brand_post->ID, false );
		if ( ! $brand_doc || false === $brand_doc->save( array( 'elements' => $brand_nodes ) ) ) { WP_CLI::error( 'Template save failed: ' . $brand_slug ); }
	}
}
if ( $brand_apply ) {
	set_theme_mod( 'custom_logo', $brand_logo_id );
	update_option( 'site_icon', $brand_icon_id );
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
WP_CLI::success( ( $brand_apply ? 'Applied' : 'Preview' ) . ': direction 03; ' . $brand_changed . ' templates, native custom logo and site icon.' );
