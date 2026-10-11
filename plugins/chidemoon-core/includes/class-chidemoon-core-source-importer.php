<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Core_Source_Importer {
 public const META_REVISION = '_chidemoon_source_revision';
 public const META_CONTENT_REVISION = '_chidemoon_source_content_revision';
 public const META_IDENTITY = '_chidemoon_source_identity';
 public const META_DESCRIPTION = '_chidemoon_source_description';
 public const META_SPECS = '_chidemoon_source_specifications';
 public const META_OBSERVATIONS = '_chidemoon_source_observations';
 public const META_WITHDRAWN = '_chidemoon_source_withdrawn';
 public const META_IMPORTED = '_chidemoon_source_imported_at';

 public static function install(): void {
  global $wpdb;
  $collation = $wpdb->get_charset_collate();
  dbDelta( "CREATE TABLE {$wpdb->prefix}chidemoon_source_map (
   source_key varchar(71) NOT NULL,
   product_id bigint(20) unsigned NOT NULL,
   identity_json text NOT NULL,
   PRIMARY KEY  (source_key),
   UNIQUE KEY product_id (product_id)
  ) {$collation};" );
 }

 public static function acquire_lock(): bool {
  global $wpdb;
  return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', 'chidemoon_source_' . hash( 'sha256', $wpdb->prefix ) ) );
 }

 public static function release_lock(): void {
  global $wpdb;
  $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'chidemoon_source_' . hash( 'sha256', $wpdb->prefix ) ) );
 }

 public static function import_snapshot( array $snapshot, bool $dry_run, string $organization_slug ): array {
  $snapshot = Chidemoon_Core_Source_Contract::validate_snapshot( $snapshot, $organization_slug );
  if ( ! self::acquire_lock() ) { throw new RuntimeException( 'source_import_locked' ); }
  try {
   $report = array( 'version' => 'v2', 'dryRun' => $dry_run, 'failed' => 0, 'created' => 0, 'updated' => 0, 'records' => array() );
   foreach ( $snapshot['items'] as $record ) {
    try { $result = self::apply_record( $record, $dry_run ); }
    catch ( Throwable $exception ) { $result = array( 'status' => 'failed', 'issues' => array( in_array( $exception->getMessage(), array( 'identity_collision', 'stale_revision', 'invalid_record', 'currency_unsupported' ), true ) ? $exception->getMessage() : 'persistence_failed' ) ); }
    $report['failed'] += 'imported' === $result['status'] ? 0 : 1;
    if ( isset( $result['action'] ) && in_array( $result['action'], array( 'created', 'updated' ), true ) ) { $report[ $result['action'] ]++; }
    unset( $result['action'] );
    $report['records'][] = array_merge( array( 'sourceKey' => $record['sourceKey'], 'revision' => $record['revision'] ), $result );
   }
   foreach ( $snapshot['withdrawals'] as $withdrawal ) {
    try { $result = self::withdraw( $withdrawal, $dry_run ); }
    catch ( Throwable $exception ) { $result = array( 'status' => 'failed', 'issues' => array( 'persistence_failed' ) ); }
    $report['failed'] += 'imported' === $result['status'] ? 0 : 1;
    $report['records'][] = array_merge( $withdrawal, $result );
   }
   return $report;
  } finally { self::release_lock(); }
 }

 public static function resolve( string $source_key, array $aliases = array() ): int {
  global $wpdb;
  $mapped = (int) $wpdb->get_var( $wpdb->prepare( "SELECT product_id FROM {$wpdb->prefix}chidemoon_source_map WHERE source_key = %s", $source_key ) );
  $ids = $mapped ? array( $mapped ) : array();
  foreach ( array_unique( array_merge( array( $source_key ), $aliases ) ) as $key ) {
   $matches = Chidemoon_Core_Importer::find_product_ids( $key );
   $sku = 'km-src-' . substr( hash( 'sha256', $key ), 0, 48 );
   $woo_id = (int) wc_get_product_id_by_sku( $sku );
   if ( $woo_id ) { $matches[] = $woo_id; }
   foreach ( $matches as $match ) {
    $known = (string) get_post_meta( (int) $match, self::META_IDENTITY, true );
    if ( $key !== $source_key && $known ) {
     $identity = json_decode( $known, true );
     if ( is_array( $identity ) && Chidemoon_Core_Source_Contract::source_key( $identity ) !== $source_key ) { continue; }
    }
    $ids[] = (int) $match;
   }
  }
  $ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
  if ( count( $ids ) > 1 ) { throw new RuntimeException( 'identity_collision' ); }
  if ( $ids ) {
   $other = $wpdb->get_var( $wpdb->prepare( "SELECT source_key FROM {$wpdb->prefix}chidemoon_source_map WHERE product_id = %d", $ids[0] ) );
   if ( $other && $source_key !== $other ) { throw new RuntimeException( 'identity_collision' ); }
  }
  return $ids[0] ?? 0;
 }

 public static function find_reference_ids( string $key ): array {
  $ids = Chidemoon_Core_Importer::find_product_ids( $key );
  $candidates = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 100, 'meta_query' => array( array( 'key' => '_chidemoon_source_aliases', 'value' => '"' . $key . '"', 'compare' => 'LIKE' ) ) ) );
  foreach ( $candidates as $id ) { if ( in_array( $key, (array) get_post_meta( $id, '_chidemoon_source_aliases', true ), true ) ) { $ids[] = (int) $id; } }
  return array_values( array_unique( $ids ) );
 }

 public static function convert_price( string $amount, string $source_currency, string $destination_currency ): string {
  if ( ! Chidemoon_Core_Source_Contract::decimal( $amount ) ) { throw new InvalidArgumentException( 'invalid_record' ); }
  $source = strtoupper( $source_currency );
  $destination = strtoupper( $destination_currency );
  if ( $source === $destination ) { return $amount; }
  if ( ! in_array( $source, array( 'IRR', 'IRT' ), true ) || ! in_array( $destination, array( 'IRR', 'IRT' ), true ) ) { throw new InvalidArgumentException( 'currency_unsupported' ); }
  // Decimal shifting avoids binary floating point rounding in rial/toman conversion.
  $parts = explode( '.', $amount, 2 );
  $integer = $parts[0]; $fraction = $parts[1] ?? '';
  if ( 'IRT' === $source ) { $integer .= substr( $fraction . '0', 0, 1 ); $fraction = substr( $fraction, 1 ); }
  else { $fraction = substr( $integer, -1 ) . $fraction; $integer = substr( $integer, 0, -1 ) ?: '0'; }
  $integer = ltrim( $integer, '0' ) ?: '0'; $fraction = rtrim( $fraction, '0' );
  return $integer . ( '' !== $fraction ? '.' . $fraction : '' );
 }

 private static function apply_record( array $record, bool $dry_run ): array {
  global $wpdb;
  $record = Chidemoon_Core_Source_Contract::validate_record( $record );
  if ( $record['revision'] <= (int) get_option( 'chidemoon_source_withdrawal_' . $record['sourceKey'], 0 ) ) { throw new RuntimeException( 'stale_revision' ); }
  $id = self::resolve( $record['sourceKey'], $record['aliases'] );
  $product = $id ? wc_get_product( $id ) : new WC_Product_External();
  if ( ! $product instanceof WC_Product || ( $id && ! $product->is_type( 'external' ) ) ) { throw new RuntimeException( 'identity_collision' ); }
  $old_revision = (int) $product->get_meta( self::META_REVISION, true );
  $fingerprint = hash( 'sha256', Chidemoon_Core_Importer::canonical_json( json_decode( wp_json_encode( $record ) ) ) );
  if ( $record['revision'] < $old_revision ) { throw new RuntimeException( 'stale_revision' ); }
  $existing_identity = (string) $product->get_meta( self::META_IDENTITY, true );
  if ( $existing_identity && ! hash_equals( $existing_identity, wp_json_encode( $record['identity'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) ) { throw new RuntimeException( 'identity_collision' ); }
  if ( $record['contentRevision'] < (int) $product->get_meta( self::META_CONTENT_REVISION, true ) ) { throw new RuntimeException( 'stale_revision' ); }
  if ( $record['revision'] === $old_revision ) {
   $old_fingerprint = (string) $product->get_meta( '_chidemoon_source_fingerprint', true );
   if ( '1' === (string) $product->get_meta( self::META_WITHDRAWN, true ) || ( $old_fingerprint && ! hash_equals( $old_fingerprint, $fingerprint ) ) ) { throw new RuntimeException( 'stale_revision' ); }
   return array( 'status' => 'imported', 'issues' => array(), 'localProductId' => (string) $id );
  }
  if ( $id && ! $existing_identity ) {
   $existing_source = (string) $product->get_meta( Chidemoon_Core_Affiliate::META_SOURCE_URL, true );
   if ( $existing_source && ( strtolower( (string) parse_url( $existing_source, PHP_URL_HOST ) ) !== strtolower( (string) parse_url( $record['content']['sourceUrl'], PHP_URL_HOST ) ) || parse_url( $existing_source, PHP_URL_PATH ) !== parse_url( $record['content']['sourceUrl'], PHP_URL_PATH ) ) ) { throw new RuntimeException( 'identity_collision' ); }
  }
  if ( $dry_run ) { return array( 'status' => 'imported', 'issues' => array(), 'action' => $id ? 'updated' : 'created', ...( $id ? array( 'localProductId' => (string) $id ) : array() ) ); }
  $content = $record['content'];
  $issues = array();
  if ( ! $id ) { $product->set_status( 'draft' ); $product->set_catalog_visibility( 'hidden' ); $product->update_meta_data( Chidemoon_Core_Affiliate::META_REVIEW_STATE, 'draft' ); }
  $content_changed = $record['contentRevision'] !== (int) $product->get_meta( self::META_CONTENT_REVISION, true );
  if ( $id && ( $content_changed || in_array( 'description', $record['removals'], true ) ) ) {
   $prior_description = (string) $product->get_meta( self::META_DESCRIPTION, true );
   Chidemoon_Core_Reconciliation::prepare_source_description( $product, $prior_description ?: wp_kses_post( $content['description'] ?? '' ) );
  }
  if ( $content_changed ) {
   $product->update_meta_data( '_chidemoon_source_content', wp_json_encode( $content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
   $product->set_name( $content['title'] );
   // Existing post content is editorial. Source descriptions are rendered dynamically.
   if ( isset( $content['description'] ) && '' !== $content['description'] ) { $product->update_meta_data( self::META_DESCRIPTION, wp_kses_post( $content['description'] ) ); }
   if ( ! $id ) { $product->set_description( '' ); }
   $product->update_meta_data( Chidemoon_Core_Affiliate::META_SOURCE_URL, $content['sourceUrl'] );
   $product->update_meta_data( Chidemoon_Core_Affiliate::META_AFFILIATE_URL, $content['affiliateUrl'] );
   $product->set_product_url( $content['affiliateUrl'] );
   if ( ! empty( $content['specifications'] ) ) { $product->update_meta_data( self::META_SPECS, wp_json_encode( $content['specifications'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); }
   foreach ( array( 'seller' => Chidemoon_Core_Affiliate::META_MERCHANT_NAME, 'brand' => '_chidemoon_source_brand' ) as $field => $meta ) { if ( ! empty( $content[ $field ] ) ) { $product->update_meta_data( $meta, sanitize_text_field( $content[ $field ] ) ); } }
  }
  $observations = json_decode( (string) $product->get_meta( self::META_OBSERVATIONS, true ), true ) ?: array();
  foreach ( $record['observations'] as $field => $observation ) {
   if ( isset( $observations[ $field ]['observedAt'] ) && strtotime( $observation['observedAt'] ) < strtotime( $observations[ $field ]['observedAt'] ) ) { continue; }
   if ( 'price' === $field ) {
    try {
     $current = self::convert_price( $observation['current'], $observation['currency'], get_woocommerce_currency() );
     $previous = null !== $observation['previous'] ? self::convert_price( $observation['previous'], $observation['currency'], get_woocommerce_currency() ) : null;
     $product->set_regular_price( $previous ?? $current );
     $product->set_sale_price( $previous !== null ? $current : '' );
     $product->set_date_on_sale_from( null ); $product->set_date_on_sale_to( null );
     $product->set_price( $current );
     $product->update_meta_data( '_chidemoon_currency', get_woocommerce_currency() );
     $product->update_meta_data( Chidemoon_Core_Affiliate::META_SOURCE_CHECKED, $observation['observedAt'] );
     $product->update_meta_data( '_chidemoon_price_observed_unix', strtotime( $observation['observedAt'] ) );
    } catch ( InvalidArgumentException $exception ) { $issues[] = $exception->getMessage(); continue; }
   }
   $observations[ $field ] = $observation;
   if ( 'sourceState' === $field ) { $product->update_meta_data( '_chidemoon_source_status', $observation['status'] ); $product->update_meta_data( '_chidemoon_source_publication_state', $observation['publicationState'] ); if ( isset( $observation['productStatus'] ) ) { $product->update_meta_data( '_chidemoon_source_product_status', $observation['productStatus'] ); } }
   if ( 'availability' === $field ) { $product->update_meta_data( '_chidemoon_availability_status', $observation['status'] ); $product->update_meta_data( '_chidemoon_availability_observed_unix', strtotime( $observation['observedAt'] ) ); }
  }
  foreach ( $record['removals'] as $field ) {
   if ( 'price' === $field ) { $product->set_regular_price( '' ); $product->set_sale_price( '' ); $product->set_price( '' ); $product->delete_meta_data( '_chidemoon_price_observed_unix' ); unset( $observations['price'] ); }
   elseif ( 'rating' === $field ) { unset( $observations['rating'] ); }
   elseif ( 'images' === $field ) { $product->set_image_id( 0 ); $product->set_gallery_image_ids( array() ); }
   else { $meta = array( 'description' => self::META_DESCRIPTION, 'specifications' => self::META_SPECS, 'brand' => '_chidemoon_source_brand', 'seller' => Chidemoon_Core_Affiliate::META_MERCHANT_NAME ); $product->delete_meta_data( $meta[ $field ] ); }
  }
  $product->set_manage_stock( false );
  $product->update_meta_data( self::META_IDENTITY, wp_json_encode( $record['identity'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
  $product->update_meta_data( self::META_OBSERVATIONS, wp_json_encode( $observations, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
  $product->update_meta_data( Chidemoon_Core_Affiliate::META_SOURCE_KEY, $record['sourceKey'] );
  $product->update_meta_data( '_chidemoon_source_aliases', array_values( array_unique( array_merge( (array) $product->get_meta( '_chidemoon_source_aliases', true ), $record['aliases'] ) ) ) );
  $product->update_meta_data( self::META_WITHDRAWN, '0' );
  $new_id = $product->save();
  if ( ! $new_id ) { throw new RuntimeException( 'persistence_failed' ); }
  $mapped = $wpdb->get_var( $wpdb->prepare( "SELECT product_id FROM {$wpdb->prefix}chidemoon_source_map WHERE source_key = %s", $record['sourceKey'] ) );
  if ( ! $mapped && false === $wpdb->insert( $wpdb->prefix . 'chidemoon_source_map', array( 'source_key' => $record['sourceKey'], 'product_id' => $new_id, 'identity_json' => wp_json_encode( $record['identity'] ) ), array( '%s', '%d', '%s' ) ) ) { throw new RuntimeException( 'identity_collision' ); }
  if ( $content_changed && ! empty( $content['images'] ) && ! in_array( 'images', $record['removals'], true ) ) {
   $image = Chidemoon_Core_Importer::ensure_product_image( $new_id, array( 'url' => $content['images'][0], 'alt' => $content['title'] ) );
   $gallery_issues = Chidemoon_Core_Importer::ensure_gallery( $new_id, array_slice( $content['images'], 1 ), $content['title'] );
   if ( is_wp_error( $image ) || $gallery_issues ) { $issues[] = 'media_failed'; }
  }
  // Reload after media helpers save the WooCommerce object to avoid restoring stale attachment IDs.
  $product = wc_get_product( $new_id );
  if ( ! $issues ) {
   $product->update_meta_data( self::META_REVISION, $record['revision'] );
   $product->update_meta_data( self::META_CONTENT_REVISION, $record['contentRevision'] );
   $product->update_meta_data( self::META_IMPORTED, gmdate( 'c' ) );
   $product->update_meta_data( '_chidemoon_source_fingerprint', $fingerprint );
   $product->save();
  }
  return array( 'status' => $issues ? 'partial' : 'imported', 'issues' => array_values( array_unique( $issues ) ), 'localProductId' => (string) $new_id, 'action' => $id ? 'updated' : 'created' );
 }

 private static function withdraw( array $withdrawal, bool $dry_run ): array {
  $option = 'chidemoon_source_withdrawal_' . $withdrawal['sourceKey'];
  $known_revision = (int) get_option( $option, 0 );
  if ( $withdrawal['revision'] < $known_revision ) { return array( 'status' => 'failed', 'issues' => array( 'stale_revision' ) ); }
  $id = self::resolve( $withdrawal['sourceKey'] );
  $product = $id ? wc_get_product( $id ) : null;
  if ( $id && ! $product instanceof WC_Product ) { throw new RuntimeException( 'persistence_failed' ); }
  if ( $product instanceof WC_Product && ( $withdrawal['revision'] < (int) $product->get_meta( self::META_REVISION, true ) || ( $withdrawal['revision'] === (int) $product->get_meta( self::META_REVISION, true ) && '1' !== (string) $product->get_meta( self::META_WITHDRAWN, true ) ) ) ) { return array( 'status' => 'failed', 'issues' => array( 'stale_revision' ), 'localProductId' => (string) $id ); }
  if ( ! $dry_run ) {
   update_option( $option, $withdrawal['revision'], false );
   if ( (int) get_option( $option, 0 ) !== $withdrawal['revision'] ) { throw new RuntimeException( 'persistence_failed' ); }
  }
  if ( ! $id ) { return array( 'status' => 'imported', 'issues' => array() ); }
  if ( ! $dry_run ) { $product->update_meta_data( self::META_WITHDRAWN, '1' ); $product->update_meta_data( self::META_REVISION, $withdrawal['revision'] ); $product->save(); }
  return array( 'status' => 'imported', 'issues' => array(), 'localProductId' => (string) $id );
 }
}
