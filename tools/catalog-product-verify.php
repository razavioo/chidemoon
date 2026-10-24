<?php
if ( ! defined( 'ABSPATH' ) || ! current_user_can( 'manage_woocommerce' ) ) { exit(1); }
$ids = get_posts( array( 'post_type' => 'product', 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => -1, 'fields' => 'ids' ) );
$status = get_option( 'chidemoon_catalog_status', array() );
$report = $status['report'] ?? array();
$synced_ids = array_fill_keys( array_map( 'intval', array_column( $report['records'] ?? array(), 'localProductId' ) ), true );
$mapped = 0; $synced = 0; $legacy = array(); $mirrors = array(); $issues = array();
foreach ( $ids as $id ) {
 $id = (int) $id;
 $identity = json_decode( (string) get_post_meta( $id, Chidemoon_Core_Source_Importer::META_IDENTITY, true ), true );
 if ( ! is_array( $identity ) ) { continue; }
 $content = json_decode( (string) get_post_meta( $id, '_chidemoon_source_content', true ), true ) ?: array();
 if ( Chidemoon_Core_Source_Importer::is_local_source( array( 'content' => $content ), home_url( '/' ) ) ) { $mirrors[] = $id; continue; }
 $mapped++;
 try { if ( $id !== Chidemoon_Core_Source_Importer::resolve( Chidemoon_Core_Source_Contract::source_key( $identity ) ) ) { $issues[] = array( 'id' => $id, 'issue' => 'mapping' ); } }
 catch ( Throwable $error ) { $issues[] = array( 'id' => $id, 'issue' => $error->getMessage() ); }
 if ( isset( $synced_ids[ $id ] ) ) {
  $synced++;
  if ( ! get_post_meta( $id, Chidemoon_Core_Source_Importer::META_IMPORTED, true ) ) { $issues[] = array( 'id' => $id, 'issue' => 'incomplete_import' ); }
 } else { $legacy[] = $id; }
 $product = wc_get_product( $id );
 if ( ! empty( $content['images'] ) ) {
  $image_ids = array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() );
  if ( count( $image_ids ) < count( $content['images'] ) ) { $issues[] = array( 'id' => $id, 'issue' => 'incomplete_gallery' ); }
  foreach ( $image_ids as $image_id ) { if ( ! $image_id || ! is_file( (string) get_attached_file( $image_id ) ) ) { $issues[] = array( 'id' => $id, 'issue' => 'missing_image_file', 'attachmentId' => $image_id ); } }
 }
}
echo wp_json_encode( array( 'counts' => wp_count_posts( 'product' ), 'mappedExternalProducts' => $mapped, 'syncedExternalProducts' => $synced, 'legacySourceProductsOutsideCurrentSnapshot' => $legacy, 'nonTrashedDestinationMirrors' => $mirrors, 'issues' => $issues, 'lastSuccessAt' => $status['lastSuccessAt'] ?? null, 'syncError' => $status['error'] ?? null, 'report' => array_diff_key( $report, array( 'records' => true ) ), 'pendingReceipts' => count( get_option( 'chidemoon_catalog_outbox', array() ) ) ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . PHP_EOL;
