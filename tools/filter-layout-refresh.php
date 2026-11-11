<?php
/** Rename the Look page and its visible navigation labels without changing URLs. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
$page = get_page_by_path( 'shop-the-look' );
if ( ! $page ) { WP_CLI::error( 'Look page not found.' ); }
$title = 'ایده‌ی چیدمون';
$apply = in_array( 'apply', $args ?? array(), true );
$replace = static function ( $value ) use ( &$replace, $title ) {
 if ( is_array( $value ) ) { return array_map( $replace, $value ); }
 return is_string( $value ) ? str_replace( array( 'ایده‌های چیدمان', 'ایده های چیدمان', 'ایده‌ی چیدمان', 'ایدهٔ چیدمان' ), $title, $value ) : $value;
};
foreach ( get_posts( array( 'post_type' => array( 'page', 'elementor_library', 'nav_menu_item' ), 'post_status' => 'any', 'posts_per_page' => -1 ) ) as $post ) {
 $raw = get_post_meta( $post->ID, '_elementor_data', true );
 $nodes = $raw ? json_decode( $raw, true ) : null;
 $updated = $nodes ? $replace( $nodes ) : $nodes;
 $new_title = (int) $post->ID === (int) $page->ID ? $title : $replace( $post->post_title );
 if ( $new_title === $post->post_title && $updated === $nodes ) { continue; }
 WP_CLI::log( 'Update ' . $post->ID . ': ' . $new_title );
 if ( ! $apply ) { continue; }
 add_post_meta( $post->ID, '_chidemoon_before_filter_layout_20261103', array( 'title' => $post->post_title, 'elementor' => $raw ), true );
 if ( $new_title !== $post->post_title ) { wp_update_post( array( 'ID' => $post->ID, 'post_title' => $new_title ) ); }
 if ( $updated !== $nodes ) {
  $doc = \Elementor\Plugin::$instance->documents->get( $post->ID, false );
  if ( ! $doc || false === $doc->save( array( 'elements' => $updated ) ) ) { WP_CLI::error( 'Elementor save failed: ' . $post->ID ); }
 }
}
if ( $apply ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); wp_cache_flush(); }
WP_CLI::success( $apply ? 'Look page labels updated.' : 'Preview only; pass apply to save.' );
