<?php
/** Narrow, repeatable improvements to published templates. Never modifies revisions or drafts. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
$apply = in_array( 'apply', $args ?? array(), true );
$post = get_page_by_path( 'chidemoon-product-archive', OBJECT, 'elementor_library' );
if ( ! $post || 'publish' !== $post->post_status ) { WP_CLI::error( 'Published product archive not found.' ); }
$raw = get_post_meta( $post->ID, '_elementor_data', true );
$elements = json_decode( $raw, true );
if ( ! is_array( $elements ) ) { WP_CLI::error( 'Invalid archive document.' ); }
$has_facets = str_contains( $raw, 'chidemoon-search-facets' );
$walk = static function ( array &$nodes ) use ( &$walk, &$has_facets ): void {
 foreach ( $nodes as &$node ) {
  if ( 'text-editor' === ( $node['widgetType'] ?? '' ) && str_contains( $node['settings']['editor'] ?? '', 'پیشنهاد خرید فقط برای محصولات' ) ) {
   $node['settings']['editor'] = '<p>محصولات خانه را پیدا کن، ویژگی ها را مقایسه کن و متناسب با نیازت انتخاب کن.</p>';
  }
  if ( ! empty( $node['elements'] ) ) {
   $walk( $node['elements'] );
   if ( ! $has_facets ) {
    foreach ( $node['elements'] as $index => $child ) {
     if ( preg_match( '/(?:^|\s)ch-catalog-layout(?:\s|$)/', $child['settings']['css_classes'] ?? '' ) ) {
      array_splice( $node['elements'], $index, 0, array( array( 'id' => 'chaudfac', 'elType' => 'widget', 'widgetType' => 'chidemoon-search-facets', 'settings' => array(), 'elements' => array() ) ) );
      $has_facets = true; break;
     }
    }
   }
  }
 }
};
$walk( $elements );
if ( wp_json_encode( $elements ) === wp_json_encode( json_decode( $raw, true ) ) ) { WP_CLI::success( 'Archive already updated.' ); return; }
if ( $apply ) {
 if ( ! metadata_exists( 'post', $post->ID, '_chidemoon_before_audit_20261019' ) && ! add_post_meta( $post->ID, '_chidemoon_before_audit_20261019', wp_slash( $raw ), true ) ) { WP_CLI::error( 'Backup failed.' ); }
 $doc = \Elementor\Plugin::$instance->documents->get( $post->ID, false );
 if ( ! $doc || false === $doc->save( array( 'elements' => $elements ) ) ) { WP_CLI::error( 'Save failed.' ); }
 \Elementor\Plugin::$instance->files_manager->clear_cache();
}
WP_CLI::success( $apply ? 'Archive updated; drafts and revisions preserved.' : 'Would update archive.' );
