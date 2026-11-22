<?php
// Run inside the WordPress container, passing a look ID or a JSON bundle path.
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

function look_transfer_error( $result ) {
 if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
 return $result;
}

function look_transfer_media( int $id ): ?array {
 if ( ! $id ) { return null; }
 $path = get_attached_file( $id );
 if ( ! $path || ! is_readable( $path ) ) { throw new RuntimeException( "Missing attachment $id" ); }
 return array( 'name' => basename( $path ), 'bytes' => base64_encode( file_get_contents( $path ) ) );
}

function look_transfer_attach( ?array $media ): int {
 if ( ! $media ) { return 0; }
 $bytes = base64_decode( $media['bytes'], true );
 if ( ! $bytes || ! getimagesizefromstring( $bytes ) ) { throw new RuntimeException( 'Invalid image in bundle' ); }
 $hash = hash( 'sha256', $bytes );
 $existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_ch_transfer_image_hash', 'meta_value' => $hash, 'fields' => 'ids', 'numberposts' => 1 ) );
 if ( $existing ) { return (int) $existing[0]; }
 $temp = wp_tempnam( $media['name'] );
 try {
  if ( file_put_contents( $temp, $bytes ) !== strlen( $bytes ) ) { throw new RuntimeException( 'Could not stage image' ); }
  $id = look_transfer_error( media_handle_sideload( array( 'name' => sanitize_file_name( $media['name'] ), 'tmp_name' => $temp ), 0 ) );
  update_post_meta( $id, '_ch_transfer_image_hash', $hash );
  return $id;
 } finally {
  if ( is_file( $temp ) ) { unlink( $temp ); }
 }
}

function look_transfer_export( int $id ): array {
 $post = get_post( $id, ARRAY_A );
 if ( ! $post || 'reycob_look' !== $post['post_type'] ) { throw new RuntimeException( 'Expected a Shop the Look gallery entry' ); }
 $meta = array();
 foreach ( get_post_meta( $id ) as $key => $values ) {
  if ( str_starts_with( $key, '_reycob_stl_' ) ) { $meta[$key] = maybe_unserialize( $values[0] ); }
 }
 $products = array();
 foreach ( $meta['_reycob_stl_points'] ?? array() as $point ) {
  $product = wc_get_product( $point['product_id'] );
  if ( ! $product instanceof WC_Product_External || ! $product->get_product_url() ) { throw new RuntimeException( 'A linked product has no merchant URL' ); }
  $products[$product->get_id()] = array_intersect_key( $product->get_data(), array_flip( array( 'name', 'description', 'short_description', 'sku', 'regular_price', 'sale_price', 'price', 'stock_status', 'product_url', 'button_text' ) ) );
  $products[$product->get_id()]['image'] = look_transfer_media( $product->get_image_id() );
  foreach ( array( '_chidemoon_review_state', '_chidemoon_affiliate_url', '_chidemoon_source_url', '_chidemoon_merchant_name', '_chidemoon_source_key', '_chidemoon_currency' ) as $key ) {
   $products[$product->get_id()]['meta'][$key] = $product->get_meta( $key, true );
  }
 }
 $terms = array();
 foreach ( array( 'chidemoon_room', 'chidemoon_style' ) as $taxonomy ) {
  $terms[$taxonomy] = look_transfer_error( wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'slugs' ) ) );
 }
 $image = look_transfer_media( (int) ( $meta['_reycob_stl_image_id'] ?? 0 ) );
 if ( ! $image ) { throw new RuntimeException( 'Look has no scene image' ); }
 unset( $meta['_reycob_stl_image_id'], $meta['_reycob_stl_loop_template'] );
 return array( 'post' => array_intersect_key( $post, array_flip( array( 'post_title', 'post_name', 'post_content', 'post_excerpt', 'post_status', 'post_date', 'comment_status' ) ) ), 'meta' => $meta, 'terms' => $terms, 'image' => $image, 'products' => $products );
}

function look_transfer_import( array $bundle, int $replace_id = 0 ): int {
 $post = $bundle['post'];
 $existing = get_page_by_path( $post['post_name'], OBJECT, 'reycob_look' );
 if ( (int) ( $existing->ID ?? 0 ) !== $replace_id ) { throw new RuntimeException( 'Destination look differs from expected ID' ); }
 if ( $replace_id ) { $post['ID'] = $replace_id; }
 $post['post_type'] = 'reycob_look';
 $status = $post['post_status'];
 $post['post_status'] = 'draft';
 $terms = array();
 foreach ( $bundle['terms'] as $taxonomy => $slugs ) {
  foreach ( $slugs as $slug ) {
   $term = get_term_by( 'slug', $slug, $taxonomy );
   if ( ! $term ) { throw new RuntimeException( "Missing term $taxonomy/$slug" ); }
   $terms[$taxonomy][] = (int) $term->term_id;
  }
 }
 $mapping = array();
 foreach ( $bundle['products'] as $source_id => $data ) {
  $matches = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'meta_key' => '_product_url', 'meta_value' => $data['product_url'], 'numberposts' => 2, 'fields' => 'ids' ) );
  if ( count( $matches ) > 1 ) { throw new RuntimeException( "Ambiguous product $source_id" ); }
  $product = $matches ? wc_get_product( $matches[0] ) : new WC_Product_External();
  if ( ! $product instanceof WC_Product_External ) { throw new RuntimeException( "Product type conflict for $source_id" ); }
  $image = $data['image'];
  $product_meta = $data['meta'] ?? array();
  unset( $data['image'], $data['meta'] );
  if ( ! $matches ) {
   if ( ! empty( $data['sku'] ) && wc_get_product_id_by_sku( $data['sku'] ) ) { throw new RuntimeException( "SKU conflict for product $source_id" ); }
   look_transfer_error( $product->set_props( $data ) );
   $product->set_status( 'publish' );
  }
  if ( ! is_readable( (string) get_attached_file( $product->get_image_id() ) ) ) {
   $product->set_image_id( look_transfer_attach( $image ) );
  }
  foreach ( $product_meta as $key => $value ) {
   if ( '' !== $value && '' === $product->get_meta( $key, true ) ) { $product->update_meta_data( $key, $value ); }
  }
  $mapping[$source_id] = $product->save();
 }
 $meta = $bundle['meta'];
 $meta['_reycob_stl_image_id'] = look_transfer_attach( $bundle['image'] );
 foreach ( $meta['_reycob_stl_points'] as &$point ) { $point['product_id'] = $mapping[$point['product_id']]; }
 unset( $point );
 $post['meta_input'] = $meta;
 $id = look_transfer_error( $replace_id ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true ) );
 foreach ( $terms as $taxonomy => $ids ) { look_transfer_error( wp_set_object_terms( $id, $ids, $taxonomy ) ); }
 look_transfer_error( wp_update_post( array( 'ID' => $id, 'post_status' => $status ), true ) );
 return $id;
}

try {
 $input = $argv[1] ?? '';
 if ( ctype_digit( $input ) ) {
  echo json_encode( look_transfer_export( (int) $input ), JSON_THROW_ON_ERROR ), PHP_EOL;
 } elseif ( is_file( $input ) ) {
  echo look_transfer_import( json_decode( file_get_contents( $input ), true, 512, JSON_THROW_ON_ERROR ), (int) ( $argv[2] ?? 0 ) ), PHP_EOL;
 } else {
  throw new RuntimeException( 'Pass a Look ID to export or a bundle path to import' );
 }
} catch ( Throwable $error ) {
 fwrite( STDERR, $error->getMessage() . PHP_EOL );
 exit( 1 );
}
