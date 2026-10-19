<?php
/** Mirror published scenes into the local review database; never publish remote work. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { WP_CLI::error( 'Local review only.' ); }
$path = $args[0] ?? '';
if ( ! is_file( $path ) ) { WP_CLI::error( 'Published scene snapshot required.' ); }
$rows = json_decode( ltrim( file_get_contents( $path ), "\xEF\xBB\xBF" ), true, 512, JSON_THROW_ON_ERROR );
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
if ( ! post_type_exists( 'reycob_look' ) ) { register_post_type( 'reycob_look', array( 'public' => false ) ); }
global $wpdb;
foreach ( $rows as $row ) {
 $existing = get_posts( array( 'post_type' => 'reycob_look', 'post_status' => 'any', 'meta_key' => '_chidemoon_local_review_source_id', 'meta_value' => $row['source_id'], 'fields' => 'ids' ) );
 if ( $existing ) { WP_CLI::log( 'Existing local scene #' . $existing[0] ); continue; }
 $points = array();
 foreach ( $row['points'] as $point ) {
  $id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' AND post_title=%s LIMIT 1", $point['product_title'] ) );
  if ( ! $id ) { continue; }
  $points[] = array( 'product_id' => $id, 'x' => $point['x'], 'y' => $point['y'] );
 }
 // A scene without its referenced products cannot exercise the shopping flow.
 if ( ! $points ) { WP_CLI::log( 'Skipped scene without local product references.' ); continue; }
 $id = wp_insert_post( array( 'post_type' => 'reycob_look', 'post_status' => 'publish', 'post_title' => $row['title'] ), true );
 if ( is_wp_error( $id ) ) { WP_CLI::error( $id->get_error_message() ); }
 $image = media_sideload_image( $row['image'], $id, null, 'id' );
 if ( is_wp_error( $image ) ) { wp_delete_post( $id, true ); WP_CLI::error( $image->get_error_message() ); }
 update_post_meta( $id, '_reycob_stl_image_id', $image );
 update_post_meta( $id, '_reycob_stl_heading', $row['heading'] );
 update_post_meta( $id, '_reycob_stl_points', $points );
 update_post_meta( $id, '_chidemoon_local_review_source_id', $row['source_id'] );
 $terms = array();
 foreach ( $row['rooms'] as $slug ) { $term = get_term_by( 'slug', $slug, 'chidemoon_room' ); if ( $term ) { $terms[] = (int) $term->term_id; } }
 wp_set_object_terms( $id, $terms, 'chidemoon_room' );
 WP_CLI::success( 'Imported published scene for local review #' . $id );
}
