<?php
/** Remove the editorial category block from shared category navigation. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }
$apply = in_array( 'apply', $args ?? array(), true );
$removed = 0;
$clean = function ( array $elements, bool $panel = false ) use ( &$clean, &$removed ): array {
 $result = array();
 foreach ( $elements as $element ) {
  $settings = $element['settings'] ?? array();
  $inside = $panel || str_contains( ($settings['css_classes'] ?? '') . ' ' . ($settings['_css_classes'] ?? ''), 'ch-category-panel' );
  $heading = 'heading' === ($element['widgetType'] ?? '') && 'مطالب و ایده‌ها' === ($settings['title'] ?? '');
  $categories = $inside && 'loop-grid' === ($element['widgetType'] ?? '') && 'post_taxonomy' === ($settings['_skin'] ?? '') && 'category' === ($settings['post_taxonomy_query_post_type'] ?? '');
  if ( $heading || $categories ) { ++$removed; continue; }
  if ( ! empty( $element['elements'] ) ) { $element['elements'] = $clean( $element['elements'], $inside ); }
  $result[] = $element;
 }
 return $result;
};
foreach ( get_posts( array( 'post_type' => array( 'elementor_library', 'page' ), 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1 ) ) as $post ) {
 $raw = get_post_meta( $post->ID, '_elementor_data', true );
 $data = json_decode( $raw, true );
 if ( ! is_array( $data ) ) { continue; }
 $before = $removed;
 $updated = $clean( $data );
 if ( $before === $removed ) { continue; }
 WP_CLI::log( ($apply ? 'Update: ' : 'Plan: ') . $post->ID . ' ' . $post->post_title );
 if ( $apply ) {
  if ( ! metadata_exists( 'post', $post->ID, '_chidemoon_before_editorial_navigation_removal' ) ) {
   update_post_meta( $post->ID, '_chidemoon_before_editorial_navigation_removal', wp_slash( $raw ) );
  }
  update_post_meta( $post->ID, '_elementor_data', wp_slash( wp_json_encode( $updated ) ) );
  delete_post_meta( $post->ID, '_elementor_css' );
 }
}
if ( $apply ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); wp_cache_flush(); }
WP_CLI::success( $removed . ' editorial navigation elements ' . ($apply ? 'removed.' : 'found.') );
