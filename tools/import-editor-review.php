<?php
/** Import newer production autosaves as local drafts only; never replaces published content. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { WP_CLI::error( 'Review imports are local only.' ); }
$path = $args[0] ?? '';
if ( ! is_file( $path ) ) { WP_CLI::error( 'Snapshot file required.' ); }
$snapshot = json_decode( ltrim( file_get_contents( $path ), "\xEF\xBB\xBF" ), true, 512, JSON_THROW_ON_ERROR );
$remap = static function ( array &$nodes ) use ( &$remap, $snapshot ): void {
 foreach ( $nodes as &$node ) {
  $settings = &$node['settings'];
  if ( ! is_array( $settings ) ) { $settings = array(); }
  if ( ! empty( $settings['template_id'] ) ) {
   $slug = $snapshot['template_map'][ $settings['template_id'] ] ?? '';
   $target = $slug ? get_page_by_path( $slug, OBJECT, 'elementor_library' ) : null;
   if ( ! $target ) { WP_CLI::error( 'Referenced Loop Item is missing: ' . $slug ); }
   $settings['template_id'] = (string) $target->ID;
  }
  foreach ( $settings as $key => &$value ) {
   if ( str_ends_with( $key, '_include_term_ids' ) && is_array( $value ) ) {
    $mapped = array();
    foreach ( $value as $id ) {
     $source = $snapshot['term_map'][ $id ] ?? null;
     $term = $source ? get_term_by( 'slug', $source['slug'], $source['taxonomy'] ) : null;
     if ( ! $term ) { WP_CLI::error( 'Referenced term is missing.' ); }
     $mapped[] = $term->term_id;
    }
    $value = $mapped;
   }
   if ( is_array( $value ) && isset( $value['id'], $value['url'] ) && str_contains( $value['url'], '/wp-content/uploads/' ) ) {
    $local_url = preg_replace( '#^https://chidemoon\.com#', home_url(), $value['url'] );
    $local_id = attachment_url_to_postid( $local_url );
    $value['id'] = $local_id;
    if ( $local_id ) { $value['url'] = $local_url; }
   }
  }
  unset( $value );
  if ( ! empty( $node['elements'] ) ) { $remap( $node['elements'] ); }
 }
};
foreach ( $snapshot as $source_id => $record ) {
 if ( ! is_array( $record ) || 'revision' !== ( $record['post']['post_type'] ?? '' ) ) { continue; }
 $parent = $snapshot[ $record['post']['post_parent'] ] ?? null;
 if ( ! $parent || $record['post']['post_modified'] <= $parent['post']['post_modified'] ) { WP_CLI::log( 'Preserved older autosave #' . $source_id . ' in the snapshot; not restored.' ); continue; }
 $slug = $parent['post']['post_name'] . '-autosave-review';
 if ( get_page_by_path( $slug, OBJECT, $parent['post']['post_type'] ) ) { WP_CLI::log( 'Review draft already exists: ' . $slug ); continue; }
 $elements = json_decode( $record['meta']['_elementor_data'], true, 512, JSON_THROW_ON_ERROR );
 $remap( $elements );
 $id = wp_insert_post( array( 'post_type' => $parent['post']['post_type'], 'post_status' => 'draft', 'post_title' => $parent['post']['post_title'] . ' — بازبینی ذخیره خودکار', 'post_name' => $slug, 'post_content' => '' ), true );
 if ( is_wp_error( $id ) ) { WP_CLI::error( $id->get_error_message() ); }
 foreach ( array( '_elementor_page_settings', '_elementor_template_type' ) as $key ) { update_post_meta( $id, $key, $record['meta'][ $key ] ?: $parent['meta'][ $key ] ); }
 update_post_meta( $id, '_elementor_edit_mode', 'builder' );
 update_post_meta( $id, '_chidemoon_review_source', array( 'id' => $source_id, 'parent' => $record['post']['post_parent'], 'modified' => $record['post']['post_modified'] ) );
 $doc = \Elementor\Plugin::$instance->documents->get( $id, false );
 if ( ! $doc || false === $doc->save( array( 'elements' => $elements ) ) ) { WP_CLI::error( 'Unable to save review draft #' . $id ); }
 WP_CLI::success( 'Created local draft #' . $id . ': ' . $slug );
}
