<?php
if ( ! defined( 'DB_NAME' ) || 'catalog_test' !== DB_NAME || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Isolated catalog database required' ); }
function identity_check( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$fixture = get_option( 'chidemoon_catalog_fixture' );
$id = (int) $fixture['productId'];
$product = wc_get_product( $id );
$key = $product->get_meta( Chidemoon_Core_Affiliate::META_SOURCE_KEY, true );
$identity = json_decode( $product->get_meta( Chidemoon_Core_Source_Importer::META_IDENTITY, true ), true );
$editorial = $product->get_description();
if ( ! class_exists( 'WC_Admin_Duplicate_Product' ) ) { require_once WC_ABSPATH . 'includes/admin/class-wc-admin-duplicate-product.php'; }
$duplicate = ( new WC_Admin_Duplicate_Product() )->product_duplicate( $product );
identity_check( $duplicate instanceof WC_Product, 'WooCommerce duplication failed' );
foreach ( $duplicate->get_meta_data() as $meta ) {
 $name = $meta->get_data()['key'];
 identity_check( ! str_starts_with( $name, '_chidemoon_source_' ), 'WooCommerce copied source ownership: ' . $name );
}
identity_check( $editorial === $duplicate->get_description(), 'Duplication lost editorial content' );
identity_check( $id === Chidemoon_Core_Source_Importer::resolve( $key ), 'Fresh copy displaced the original' );
// Reproduce copies created before the duplication filter existed.
foreach ( $product->get_meta_data() as $meta ) {
 $data = $meta->get_data();
 if ( str_starts_with( $data['key'], '_chidemoon_source_' ) ) { $duplicate->update_meta_data( $data['key'], $data['value'] ); }
}
$duplicate->save();
$copy_before = get_post_meta( $duplicate->get_id() );
identity_check( $id === Chidemoon_Core_Source_Importer::resolve( $key ), 'Legacy copied metadata blocked canonical mapping' );
$record = array(
 'sourceKey' => $key, 'identity' => $identity, 'aliases' => array(),
 'revision' => (int) $product->get_meta( Chidemoon_Core_Source_Importer::META_REVISION, true ) + 1,
 'contentRevision' => (int) $product->get_meta( Chidemoon_Core_Source_Importer::META_CONTENT_REVISION, true ),
 'content' => json_decode( $product->get_meta( '_chidemoon_source_content', true ), true ),
 'observations' => array( 'price' => array( 'current' => '1300000', 'previous' => null, 'currency' => 'IRR', 'observedAt' => gmdate( 'c' ) ), 'availability' => array( 'status' => 'in_stock', 'observedAt' => gmdate( 'c' ) ) ), 'removals' => array(),
);
$snapshot = array( 'version' => 'v2', 'organization' => array( 'id' => 'fixture-org', 'slug' => 'chidemoon' ), 'snapshot' => array( 'id' => wp_generate_uuid4(), 'revision' => str_repeat( 'b', 64 ), 'generatedAt' => gmdate( 'c' ) ), 'items' => array( $record ), 'withdrawals' => array() );
$before_count = (int) wp_count_posts( 'product' )->publish + (int) wp_count_posts( 'product' )->draft;
for ( $attempt = 0; $attempt < 2; $attempt++ ) {
 $result = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
 identity_check( 'imported' === $result['records'][0]['status'] && (string) $id === $result['records'][0]['localProductId'], 'Original did not recover idempotently' );
}
identity_check( '130000' === wc_get_product( $id )->get_price(), 'Original commercial data was not updated' );
identity_check( 'publish' === get_post_status( $id ) && $editorial === wc_get_product( $id )->get_description(), 'Recovery changed publication or editorial content' );
identity_check( $copy_before === get_post_meta( $duplicate->get_id() ), 'Recovery mutated the legacy copy' );
identity_check( $before_count === (int) wp_count_posts( 'product' )->publish + (int) wp_count_posts( 'product' )->draft, 'Replay created an extra product' );
global $wpdb;
$wpdb->delete( $wpdb->prefix . 'chidemoon_source_map', array( 'source_key' => $key ) );
$result = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
identity_check( 'failed' === $result['records'][0]['status'] && in_array( 'identity_collision', $result['records'][0]['issues'], true ), 'Unmapped ambiguity selected an arbitrary product' );
$wpdb->insert( $wpdb->prefix . 'chidemoon_source_map', array( 'source_key' => $key, 'product_id' => $id, 'identity_json' => wp_json_encode( $identity ) ) );
echo "PASS: real WooCommerce duplication, legacy-copy recovery, idempotency, editorial preservation and ambiguity protection\n";
