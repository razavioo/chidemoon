<?php
/** Align old generated Loop card sizes with the responsive type roles. Pass [apply]. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
$apply = in_array( 'apply', $args ?? array(), true );
$walk = static function ( array &$nodes, string $kind = '' ) use ( &$walk ): void {
	foreach ( $nodes as &$node ) {
		$classes = preg_split( '/\s+/', trim( ( $node['settings']['css_classes'] ?? '' ) . ' ' . ( $node['settings']['_css_classes'] ?? '' ) ) );
		$card_kind = $kind;
		foreach ( array( 'post', 'search', 'product' ) as $candidate ) {
			if ( in_array( 'ch-native-card--' . $candidate, $classes, true ) ) { $card_kind = $candidate; }
		}
		$s = $node['settings'] ?? array();
		if ( $card_kind && in_array( 'ch-native-card-title', $classes, true )
			&& 'px' === ( $s['typography_font_size']['unit'] ?? '' )
			&& 20.0 === (float) ( $s['typography_font_size']['size'] ?? 0 )
			&& '700' === (string) ( $s['typography_font_weight'] ?? '' )
			&& empty( $s['typography_font_size_mobile']['size'] ) ) {
			$product = 'product' === $card_kind;
			$node['settings']['typography_font_size'] = array( 'unit' => 'px', 'size' => $product ? 15 : 18 );
			$node['settings']['typography_font_size_mobile'] = array( 'unit' => 'px', 'size' => $product ? 14 : 17 );
			$node['settings']['typography_line_height'] = array( 'unit' => 'em', 'size' => $product ? 1.75 : 1.65 );
			$node['settings']['typography_font_weight'] = $product ? '400' : '600';
		}
		if ( ! empty( $node['elements'] ) ) { $walk( $node['elements'], $card_kind ); }
	}
};
$count = 0;
$posts = get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'posts_per_page' => -1 ) );
foreach ( $posts as $post ) {
	if ( ! in_array( $post->post_name, array( 'chidemoon-article-card', 'chidemoon-product-card', 'chidemoon-search-card' ), true ) ) { continue; }
	$raw = get_post_meta( $post->ID, '_elementor_data', true );
	$nodes = is_string( $raw ) ? json_decode( $raw, true ) : null;
	if ( ! is_array( $nodes ) ) { continue; }
	$before = $nodes; $walk( $nodes );
	if ( $before === $nodes ) { continue; }
	++$count; WP_CLI::log( 'Update card type scale: ' . $post->ID . ' ' . $post->post_name );
	if ( $apply ) {
		add_post_meta( $post->ID, '_chidemoon_before_type_scale_20261019', $raw, true );
		$doc = \Elementor\Plugin::$instance->documents->get( $post->ID, false );
		if ( ! $doc || false === $doc->save( array( 'elements' => $nodes ) ) ) { WP_CLI::error( 'Native save failed: ' . $post->ID ); }
	}
}
if ( $apply && $count ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
WP_CLI::success( ( $apply ? 'Updated' : 'Would update' ) . ' ' . $count . ' managed Loop documents; custom sizes preserved.' );
