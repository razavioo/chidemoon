<?php
/** Reduce explicit native Elementor font sizes by 1px once. Pass apply to save. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
$apply = in_array( 'apply', $args ?? array(), true );
$marker = '_chidemoon_font_size_down_20261019';
$reduce = static function ( array &$values ) use ( &$reduce ): void {
	foreach ( $values as $key => &$value ) {
		if ( is_array( $value ) ) {
			if ( preg_match( '/font_size(?:_(?:mobile|tablet|laptop|widescreen|mobile_extra|tablet_extra))?$/', (string) $key ) && 'px' === ( $value['unit'] ?? '' ) && is_numeric( $value['size'] ?? null ) && $value['size'] > 1 ) {
				$value['size'] = $value['size'] - 1;
			} else { $reduce( $value ); }
		}
	}
};
$count = 0;
$ids = get_posts( array( 'post_type' => array( 'page', 'post', 'product', 'chidemoon_look', 'elementor_library' ), 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids' ) );
foreach ( $ids as $id ) {
	if ( get_post_meta( $id, $marker, true ) ) { continue; }
	$raw = get_post_meta( $id, '_elementor_data', true );
	$nodes = is_string( $raw ) ? json_decode( $raw, true ) : null;
	$settings = get_post_meta( $id, '_elementor_page_settings', true );
	$settings = is_array( $settings ) ? $settings : array();
	$before_nodes = $nodes; $before_settings = $settings;
	if ( is_array( $nodes ) ) { $reduce( $nodes ); }
	$reduce( $settings );
	if ( $nodes === $before_nodes && $settings === $before_settings ) { continue; }
	++$count;
	WP_CLI::log( 'Reduce font sizes: ' . $id );
	if ( $apply ) {
		$doc = \Elementor\Plugin::$instance->documents->get( $id, false );
		$payload = array( 'settings' => $settings );
		if ( is_array( $nodes ) ) { $payload['elements'] = $nodes; }
		if ( ! $doc || false === $doc->save( $payload ) ) { WP_CLI::error( 'Native save failed: ' . $id ); }
		add_post_meta( $id, $marker, array( 'elements' => $raw, 'settings' => $before_settings ), true );
	}
}
if ( $apply ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
WP_CLI::success( ( $apply ? 'Updated ' : 'Would update ' ) . $count . ' documents.' );
