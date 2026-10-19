<?php
/** Mirror already-published production pages into local review; preserve local and remote autosaves. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { WP_CLI::error( 'Local review only.' ); }
$path = $args[0] ?? '';
if ( ! is_file( $path ) ) { WP_CLI::error( 'Public snapshot required.' ); }
$snapshot = json_decode( ltrim( file_get_contents( $path ), "\xEF\xBB\xBF" ), true, 512, JSON_THROW_ON_ERROR );
foreach ( $snapshot as $record ) {
 if ( ! is_array( $record ) || 'page' !== ( $record['post']['post_type'] ?? '' ) || 'publish' !== $record['post']['post_status'] || ! in_array( $record['post']['post_name'], array( 'shop-the-look', 'product-chooser' ), true ) ) { continue; }
 $source = $record['post'];
 $page = get_page_by_path( $source['post_name'] );
 $id = $page ? $page->ID : wp_insert_post( array( 'post_title' => $source['post_title'], 'post_name' => $source['post_name'], 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => $source['post_content'] ), true );
 if ( is_wp_error( $id ) ) { WP_CLI::error( 'Unable to create local page.' ); }
 update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
 $raw = $record['meta']['_elementor_data'];
 if ( $raw && $raw !== get_post_meta( $id, '_elementor_data', true ) ) {
  add_post_meta( $id, '_chidemoon_before_local_public_review_20261019', get_post_meta( $id, '_elementor_data', true ), true );
  foreach ( array( '_elementor_edit_mode', '_elementor_page_settings', '_elementor_template_type' ) as $key ) { update_post_meta( $id, $key, $record['meta'][ $key ] ); }
  $elements = json_decode( $raw, true, 512, JSON_THROW_ON_ERROR );
  $doc = \Elementor\Plugin::$instance->documents->get( $id, false );
  if ( ! $doc || false === $doc->save( array( 'elements' => $elements ) ) ) { WP_CLI::error( 'Unable to save local page.' ); }
 }
 WP_CLI::success( 'Local public mirror #' . $id . ': ' . $source['post_name'] );
}
\Elementor\Plugin::$instance->files_manager->clear_cache();
