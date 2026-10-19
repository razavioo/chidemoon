<?php
/** Upgrade existing Elementor typography without rebuilding content. Pass [apply]. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
$apply = in_array( 'apply', $args ?? array(), true );
$backup_key = '_chidemoon_before_typography_20261019';
$legacy = array( 'Vazirmatn', 'Estedad' );
$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
$settings = get_post_meta( $kit->get_main_id(), '_elementor_page_settings', true );
$updated = is_array( $settings ) ? $settings : array();
// Only replace the project's old defaults; retain independently chosen families.
foreach ( $updated as $key => &$value ) {
	if ( str_ends_with( $key, '_font_family' ) && in_array( $value, $legacy, true ) ) { $value = 'YekanBakh'; }
}
unset( $value );
foreach ( $updated['system_typography'] ?? array() as $index => $type ) {
	if ( ! in_array( $type['typography_font_family'] ?? '', $legacy, true ) ) { continue; }
	$updated['system_typography'][ $index ]['typography_font_family'] = 'YekanBakh';
	if ( 'primary' === ( $type['_id'] ?? '' ) ) { $updated['system_typography'][ $index ]['typography_font_weight'] = '700'; }
	if ( 'secondary' === ( $type['_id'] ?? '' ) ) { $updated['system_typography'][ $index ]['typography_font_weight'] = '600'; }
}
if ( $updated !== $settings ) {
	WP_CLI::log( 'Update active kit typography.' );
	if ( $apply ) {
		add_post_meta( $kit->get_main_id(), $backup_key . '_kit', $settings, true );
		if ( false === $kit->save( array( 'settings' => $updated ) ) ) { WP_CLI::error( 'Kit save failed.' ); }
	}
}
$walk = static function ( array &$nodes ) use ( &$walk, $legacy ): void {
	foreach ( $nodes as &$node ) {
		foreach ( $node['settings'] ?? array() as $key => $value ) {
			if ( str_ends_with( $key, '_font_family' ) && in_array( $value, $legacy, true ) ) { $node['settings'][ $key ] = 'YekanBakh'; }
		}
		if ( ! empty( $node['elements'] ) ) { $walk( $node['elements'] ); }
	}
};
$ids = get_posts( array( 'post_type' => array( 'page', 'post', 'product', 'chidemoon_look', 'elementor_library' ), 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids' ) );
$count = 0;
foreach ( $ids as $id ) {
	$raw = get_post_meta( $id, '_elementor_data', true );
	$nodes = is_string( $raw ) ? json_decode( $raw, true ) : null;
	if ( ! is_array( $nodes ) ) { continue; }
	$before = $nodes; $walk( $nodes );
	if ( $nodes === $before ) { continue; }
	++$count;
	WP_CLI::log( 'Update document ' . $id . ': ' . get_the_title( $id ) );
	if ( $apply ) {
		add_post_meta( $id, $backup_key, $raw, true );
		$doc = \Elementor\Plugin::$instance->documents->get( $id, false );
		if ( ! $doc || false === $doc->save( array( 'elements' => $nodes ) ) ) { WP_CLI::error( 'Document save failed: ' . $id ); }
	}
}
if ( $apply ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
WP_CLI::success( ( $apply ? 'Applied' : 'Preview' ) . ': ' . $count . ' documents; custom families and other settings preserved.' );
