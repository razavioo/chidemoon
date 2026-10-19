<?php
/** Local copies of already-public production products for visual regression review. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { WP_CLI::error( 'Local review only.' ); }
$path = $args[0] ?? '';
if ( ! is_file( $path ) ) { WP_CLI::error( 'Public snapshot required.' ); }
$rows = json_decode( ltrim( file_get_contents( $path ), "\xEF\xBB\xBF" ), true, 512, JSON_THROW_ON_ERROR );
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
foreach ( $rows as $row ) {
 $existing = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'fields' => 'ids', 'meta_key' => '_chidemoon_local_review_source_id', 'meta_value' => $row['source_id'] ) );
 $product = $existing ? wc_get_product( $existing[0] ) : new WC_Product_External();
 $product->set_name( $row['title'] ); $product->set_slug( $row['slug'] );
 $product->set_description( $row['description'] ); $product->set_short_description( $row['short_description'] );
 $product->set_regular_price( $row['regular_price'] ); $product->set_sale_price( $row['sale_price'] ); $product->set_product_url( $row['url'] );
 $product->set_status( 'publish' );
 foreach ( Chidemoon_Core_Enrichment::TAXONOMIES as $field => $taxonomy ) {
  if ( empty( $row['meta']['_chidemoon_enrichment'][ $field ]['value'] ) ) { continue; }
  $mapped = array();
  foreach ( $row['meta']['_chidemoon_enrichment'][ $field ]['value'] as $term_id ) {
   $source = $row['term_map'][ $term_id ] ?? null;
   if ( ! $source ) { WP_CLI::error( 'Source term mapping is missing.' ); }
   $term = get_term_by( 'slug', $source['slug'], $taxonomy );
   $inserted = $term ? array( 'term_id' => $term->term_id ) : wp_insert_term( $source['name'], $taxonomy, array( 'slug' => $source['slug'] ) );
   if ( is_wp_error( $inserted ) ) { WP_CLI::error( 'Cannot map source term.' ); }
   $mapped[] = (int) $inserted['term_id'];
  }
  $row['meta']['_chidemoon_enrichment'][ $field ]['value'] = $mapped;
 }
 foreach ( $row['meta'] as $key => $value ) { $product->update_meta_data( $key, $value ); }
 $product->set_category_ids( $row['meta']['_chidemoon_enrichment']['category']['value'] ?? array() );
 $product->update_meta_data( '_chidemoon_local_review_source_id', $row['source_id'] );
 $product->save();
 if ( $existing ) { WP_CLI::log( 'Mapped local public review product #' . $existing[0] ); continue; }
 $images = array();
 foreach ( array_slice( $row['images'], 0, 6 ) as $url ) {
  $image = media_sideload_image( $url, $product->get_id(), null, 'id' );
  if ( is_wp_error( $image ) ) { WP_CLI::warning( 'Image unavailable: ' . $image->get_error_code() ); continue; }
  $images[] = $image;
 }
 if ( $images ) { $product->set_image_id( array_shift( $images ) ); $product->set_gallery_image_ids( $images ); $product->save(); }
 WP_CLI::success( 'Imported local public review product #' . $product->get_id() );
}
