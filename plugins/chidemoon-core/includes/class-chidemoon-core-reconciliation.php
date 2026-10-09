<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Core_Reconciliation {
 public const BACKUP = '_chidemoon_source_look_migration_backup_v2';
 public static function register(): void {
  if ( defined( 'WP_CLI' ) && WP_CLI ) { WP_CLI::add_command( 'chidemoon reconcile-catalog', array( __CLASS__, 'command' ) ); }
  if ( defined( 'WP_CLI' ) && WP_CLI ) { WP_CLI::add_command( 'chidemoon reconcile-identities', array( __CLASS__, 'identities_command' ) ); }
  if ( defined( 'WP_CLI' ) && WP_CLI ) { WP_CLI::add_command( 'chidemoon setup-discovery', array( __CLASS__, 'discovery_command' ) ); }
 }
 public static function discovery_command( array $args, array $options ): void {
  if ( ! current_user_can( 'chidemoon_manage_affiliate' ) || ! current_user_can( 'publish_pages' ) ) { WP_CLI::error( 'Run with an authorized --user.' ); }
  switch_to_locale( get_option( 'WPLANG' ) ?: 'en_US' );
  $page = get_page_by_path( 'product-chooser' );
  if ( $page ) { WP_CLI::log( wp_json_encode( array( 'action' => 'preserve_existing_page', 'id' => $page->ID, 'url' => get_permalink( $page ) ) ) ); return; }
  $result = array( 'action' => 'create_guided_chooser', 'slug' => 'product-chooser', 'dryRun' => ! isset( $options['apply'] ) );
  if ( isset( $options['apply'] ) ) {
   $id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'product-chooser', 'post_title' => __( 'Guided chooser', 'chidemoon-core' ), 'post_content' => '[chidemoon_guided_chooser]' ) ), true );
   if ( is_wp_error( $id ) ) { WP_CLI::error( $id->get_error_message() ); }
   try {
    self::save_elements( $id, array( array( 'id' => 'chchoose', 'elType' => 'container', 'settings' => array( 'content_width' => 'boxed', 'flex_direction' => 'column' ), 'elements' => array( array( 'id' => 'chtitle', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => __( 'Guided chooser', 'chidemoon-core' ), 'header_size' => 'h1' ), 'elements' => array() ), array( 'id' => 'chform', 'elType' => 'widget', 'widgetType' => 'chidemoon-discovery', 'settings' => array( 'mode' => 'chooser' ), 'elements' => array() ) ) ) ) );
    update_post_meta( $id, '_elementor_edit_mode', 'builder' );
    update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
   } catch ( Throwable $exception ) { wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) ); WP_CLI::error( $exception->getMessage() ); }
   $result['id'] = $id; $result['url'] = get_permalink( $id );
  }
  WP_CLI::log( wp_json_encode( $result ) );
 }
 public static function command( array $args, array $options ): void {
  if ( ! get_current_user_id() || ! current_user_can( 'chidemoon_manage_affiliate' ) ) { WP_CLI::error( 'Run with an authorized --user; reconciliation is editorial work.' ); }
  $apply = isset( $options['apply'] );
  $report = array( 'dryRun' => ! $apply, 'products' => array(), 'scenes' => array(), 'categories' => array(), 'errors' => array() );
  foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) as $term ) { $report['categories'][] = array( 'id' => $term->term_id, 'name' => $term->name, 'parent' => $term->parent ); }
  foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
   if ( isset( $options['rollback'] ) ) { self::rollback( $id ); continue; }
   $key = (string) get_post_meta( $id, Chidemoon_Core_Affiliate::META_SOURCE_KEY, true );
   $aliases = (array) get_post_meta( $id, '_chidemoon_source_aliases', true );
   $issues = array();
   if ( ! get_post_meta( $id, Chidemoon_Core_Source_Importer::META_IDENTITY, true ) ) { $issues[] = 'identity_requires_v2_reconciliation'; }
   if ( count( Chidemoon_Core_Importer::find_product_ids( $key ) ) > 1 ) { $issues[] = 'identity_collision'; }
   $facts = get_post_meta( $id, Chidemoon_Core_Affiliate::META_FACTS, true );
   $source_description = (string) get_post_meta( $id, Chidemoon_Core_Source_Importer::META_DESCRIPTION, true );
   if ( $source_description && trim( get_post_field( 'post_content', $id ) ) === trim( $source_description ) ) { $issues[] = 'copied_source_description'; }
   $post = get_post( $id );
   $elementor = json_decode( (string) get_post_meta( $id, '_elementor_data', true ), true );
   $updated_elements = is_array( $elementor ) ? self::source_elements( $elementor, $source_description, $issues ) : $elementor;
   if ( $apply && ( in_array( 'copied_source_description', $issues, true ) || $updated_elements !== $elementor ) ) {
    try {
     self::backup( $post );
     if ( is_array( $updated_elements ) && $updated_elements !== $elementor ) { self::save_elements( $id, $updated_elements ); }
     if ( in_array( 'copied_source_description', $issues, true ) ) { $saved = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => '[chidemoon_source_description]' ) ), true ); if ( is_wp_error( $saved ) ) { throw new RuntimeException( 'Source description migration failed' ); } }
    } catch ( Throwable $exception ) { self::rollback( $id ); $report['errors'][] = array( 'postId' => $id, 'message' => $exception->getMessage() ); }
   }
   $product = wc_get_product( $id );
   if ( $product && ! $product->is_type( 'external' ) ) { $issues[] = 'external_product_conversion_requires_review'; }
   $source_content = json_decode( (string) get_post_meta( $id, '_chidemoon_source_content', true ), true );
   if ( $product && ! empty( $source_content['images'][0] ) && get_post_meta( $product->get_image_id(), '_chidemoon_import_image_url', true ) !== $source_content['images'][0] ) { $issues[] = 'source_cover_image_requires_review'; }
   $report['products'][] = array( 'id' => $id, 'title' => get_the_title( $id ), 'type' => $product ? $product->get_type() : null, 'sourceKey' => $key, 'sourceUrl' => get_post_meta( $id, Chidemoon_Core_Affiliate::META_SOURCE_URL, true ), 'aliases' => $aliases, 'editorialFacts' => $facts, 'enrichment' => Chidemoon_Core_Enrichment::read( $id ), 'categories' => wp_get_object_terms( $id, 'product_cat', array( 'fields' => 'ids' ) ), 'imageId' => $product ? $product->get_image_id() : null, 'issues' => $issues );
  }
  foreach ( get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'any', 'posts_per_page' => -1 ) ) as $post ) {
   try {
    if ( isset( $options['rollback'] ) ) { self::rollback( $post->ID ); continue; }
    $data = json_decode( (string) get_post_meta( $post->ID, '_elementor_data', true ), true );
    $content = $post->post_content;
    $scenes = array();
    $elements = is_array( $data ) ? self::walk_elements( $data, $post, $apply, $scenes ) : $data;
    $blocks = parse_blocks( $content );
    $updated_blocks = self::walk_blocks( $blocks, $post, $apply, $scenes );
    $updated_content = self::walk_shortcodes( serialize_blocks( $updated_blocks ), $post, $apply, $scenes );
    if ( ! $scenes && get_post_meta( $post->ID, '_chidemoon_native_look', true ) ) {
     $id = self::scene( $post, 'native-cover', array( 'imageId' => get_post_thumbnail_id( $post->ID ), 'hotspots' => array() ), $apply, $scenes );
     if ( $apply && $id ) { $updated_content .= "\n" . '[chidemoon_shop_the_look look_id="' . $id . '"]'; }
    }
    if ( $scenes && $apply ) {
     self::backup( $post );
     if ( is_array( $elements ) && $elements !== $data ) {
      if ( class_exists( '\Elementor\Plugin' ) ) {
       self::save_elements( $post->ID, $elements );
      } else { throw new RuntimeException( 'Elementor must be active to migrate its documents' ); }
     }
     if ( $updated_content !== $content ) { $updated = wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_content' => $updated_content ) ), true ); if ( is_wp_error( $updated ) ) { throw new RuntimeException( $updated->get_error_message() ); } }
    }
    $report['scenes'] = array_merge( $report['scenes'], $scenes );
   } catch ( Throwable $exception ) { if ( $apply ) { self::rollback( $post->ID ); } $report['errors'][] = array( 'postId' => $post->ID, 'message' => $exception->getMessage() ); }
  }
  WP_CLI::log( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
  if ( $report['errors'] ) { WP_CLI::warning( 'Reconciliation requires review. Original pages and migration backups are retained.' ); }
 }
 public static function identities_command( array $args, array $options ): void {
  if ( ! current_user_can( 'chidemoon_manage_affiliate' ) || empty( $options['file'] ) ) { WP_CLI::error( 'An authorized --user and reviewed --file are required.' ); }
  try {
   $map = json_decode( file_get_contents( $options['file'] ), true, 512, JSON_THROW_ON_ERROR );
   if ( ! is_array( $map ) || 'source-v2-identities' !== ( $map['profile'] ?? '' ) || ! is_array( $map['mappings'] ?? null ) || count( $map['mappings'] ) > 1000 ) { throw new InvalidArgumentException( 'Invalid identity reconciliation profile' ); }
   if ( ! Chidemoon_Core_Source_Importer::acquire_lock() ) { throw new RuntimeException( 'source_import_locked' ); }
   try {
    $report = array();
    $seen_ids = array(); $seen_keys = array();
    foreach ( $map['mappings'] as $mapping ) {
     $id = $mapping['productId'] ?? 0; $key = Chidemoon_Core_Source_Contract::source_key( $mapping['identity'] ?? array() );
     if ( isset( $seen_ids[ $id ] ) || isset( $seen_keys[ $key ] ) ) { throw new RuntimeException( 'Duplicate identity mapping' ); }
     $seen_ids[ $id ] = true; $seen_keys[ $key ] = true;
    }
    foreach ( $map['mappings'] as $mapping ) { if ( isset( $options['rollback'] ) ) { self::rollback_identity( (int) ( $mapping['productId'] ?? 0 ), false ); } else { self::reconcile_identity( $mapping, false ); if ( isset( $options['apply'] ) && true !== ( $mapping['approved'] ?? false ) ) { throw new RuntimeException( 'Identity mapping requires explicit human approval' ); } } }
    foreach ( $map['mappings'] as $mapping ) { $report[] = isset( $options['rollback'] ) ? self::rollback_identity( (int) ( $mapping['productId'] ?? 0 ), isset( $options['apply'] ) ) : self::reconcile_identity( $mapping, isset( $options['apply'] ) ); }
    WP_CLI::log( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
   } finally { Chidemoon_Core_Source_Importer::release_lock(); }
  } catch ( Throwable $exception ) { WP_CLI::error( $exception->getMessage() ); }
 }
 public static function reconcile_identity( array $mapping, bool $apply ): array {
  global $wpdb;
  $id = $mapping['productId'] ?? null;
  if ( ! is_int( $id ) || ! current_user_can( 'chidemoon_manage_affiliate' ) || ! current_user_can( 'edit_post', $id ) ) { throw new RuntimeException( 'forbidden' ); }
  $identity = Chidemoon_Core_Source_Contract::identity( $mapping['identity'] ?? array() );
  $key = Chidemoon_Core_Source_Contract::source_key( $identity );
  $url = $mapping['sourceUrl'] ?? '';
  if ( ! is_string( $url ) || ! Chidemoon_Core_Source_Contract::https_url( $url ) ) { throw new InvalidArgumentException( 'Invalid identity evidence URL' ); }
  $product = wc_get_product( $id );
  if ( ! $product || ! in_array( $product->get_type(), array( 'simple', 'external' ), true ) ) { throw new RuntimeException( 'Unsupported legacy product type' ); }
  $known = get_post_meta( $id, Chidemoon_Core_Source_Importer::META_IDENTITY, true );
  $encoded = wp_json_encode( $identity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
  if ( $known && $known !== $encoded ) { throw new RuntimeException( 'identity_collision' ); }
  $resolved = Chidemoon_Core_Source_Importer::resolve( $key );
  if ( $resolved && $resolved !== $id ) { throw new RuntimeException( 'identity_collision' ); }
  $old_url = (string) get_post_meta( $id, Chidemoon_Core_Affiliate::META_SOURCE_URL, true );
  if ( $old_url && ( strtolower( (string) parse_url( $old_url, PHP_URL_HOST ) ) !== strtolower( (string) parse_url( $url, PHP_URL_HOST ) ) || parse_url( $old_url, PHP_URL_PATH ) !== parse_url( $url, PHP_URL_PATH ) ) ) { throw new RuntimeException( 'Identity evidence disagrees with the existing source' ); }
  $modified = get_post_field( 'post_modified_gmt', $id );
  if ( ( $mapping['expectedModifiedAt'] ?? '' ) !== $modified ) { throw new RuntimeException( 'revision_conflict' ); }
  $result = array( 'productId' => $id, 'sourceKey' => $key, 'identity' => $identity, 'sourceUrl' => $url, 'action' => $product->is_type( 'external' ) ? 'map_identity' : 'convert_to_external_draft', 'approved' => true === ( $mapping['approved'] ?? false ) );
  if ( ! $apply ) { return $result; }
  if ( ! $result['approved'] ) { throw new RuntimeException( 'Identity mapping requires explicit human approval' ); }
  $backup_key = '_chidemoon_identity_migration_backup_v2';
  $keys = array( Chidemoon_Core_Source_Importer::META_IDENTITY, Chidemoon_Core_Affiliate::META_SOURCE_KEY, Chidemoon_Core_Affiliate::META_SOURCE_URL, '_chidemoon_source_aliases', Chidemoon_Core_Affiliate::META_REVIEW_STATE, '_manage_stock', '_backorders', '_stock_status', '_stock', '_product_url', '_button_text' );
  $backup = array( 'type' => $product->get_type(), 'status' => $product->get_status(), 'meta' => array(), 'approvedBy' => get_current_user_id(), 'approvedAt' => gmdate( 'c' ) );
  foreach ( $keys as $meta_key ) { $backup['meta'][ $meta_key ] = array( 'exists' => metadata_exists( 'post', $id, $meta_key ), 'value' => get_post_meta( $id, $meta_key, true ) ); }
  if ( ! metadata_exists( 'post', $id, $backup_key ) && ! add_post_meta( $id, $backup_key, wp_slash( $backup ), true ) ) { throw new RuntimeException( 'Identity migration backup failed' ); }
  $wpdb->query( 'START TRANSACTION' );
  try {
   $other = $wpdb->get_var( $wpdb->prepare( "SELECT source_key FROM {$wpdb->prefix}chidemoon_source_map WHERE product_id = %d", $id ) );
   if ( $other && $other !== $key ) { throw new RuntimeException( 'identity_collision' ); }
   if ( ! $other && false === $wpdb->insert( $wpdb->prefix . 'chidemoon_source_map', array( 'source_key' => $key, 'product_id' => $id, 'identity_json' => $encoded ) ) ) { throw new RuntimeException( 'identity_collision' ); }
   $aliases = (array) get_post_meta( $id, '_chidemoon_source_aliases', true );
   $legacy = (string) get_post_meta( $id, Chidemoon_Core_Affiliate::META_SOURCE_KEY, true );
   if ( $legacy && $legacy !== $key ) { $aliases[] = $legacy; }
   update_post_meta( $id, Chidemoon_Core_Source_Importer::META_IDENTITY, wp_slash( $encoded ) );
   update_post_meta( $id, Chidemoon_Core_Affiliate::META_SOURCE_KEY, $key );
   update_post_meta( $id, '_chidemoon_source_aliases', array_values( array_unique( array_filter( $aliases ) ) ) );
   update_post_meta( $id, Chidemoon_Core_Affiliate::META_SOURCE_URL, $url );
   if ( ! $product->is_type( 'external' ) ) {
    update_post_meta( $id, Chidemoon_Core_Affiliate::META_REVIEW_STATE, 'draft' );
    $external = new WC_Product_External( $id );
    $external->set_status( 'draft' );
    if ( ! $external->save() ) { throw new RuntimeException( 'Product conversion failed' ); }
   }
   if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'Identity migration failed' ); }
  } catch ( Throwable $exception ) { $wpdb->query( 'ROLLBACK' ); throw $exception; }
  finally { clean_post_cache( $id ); clean_object_term_cache( $id, 'product' ); wc_delete_product_transients( $id ); WC_Cache_Helper::invalidate_cache_group( 'product_' . $id ); wc_get_container()->get( \Automattic\WooCommerce\Internal\Caches\ProductCache::class )->remove( $id ); }
  return $result;
 }
 public static function rollback_identity( int $id, bool $apply ): array {
  global $wpdb;
  if ( ! current_user_can( 'chidemoon_manage_affiliate' ) || ! current_user_can( 'edit_post', $id ) ) { throw new RuntimeException( 'forbidden' ); }
  $backup = get_post_meta( $id, '_chidemoon_identity_migration_backup_v2', true );
  if ( ! is_array( $backup ) || (int) get_post_meta( $id, Chidemoon_Core_Source_Importer::META_REVISION, true ) > 0 ) { throw new RuntimeException( 'Identity rollback requires an untouched mapping backup' ); }
  if ( $apply ) {
   $wpdb->query( 'START TRANSACTION' );
   try {
    if ( false === $wpdb->delete( $wpdb->prefix . 'chidemoon_source_map', array( 'product_id' => $id ), array( '%d' ) ) ) { throw new RuntimeException( 'Identity rollback failed' ); }
    foreach ( $backup['meta'] as $key => $prior ) { if ( $prior['exists'] ) { update_post_meta( $id, $key, wp_slash( $prior['value'] ) ); } else { delete_post_meta( $id, $key ); } }
    $updated = wp_update_post( array( 'ID' => $id, 'post_status' => $backup['status'] ), true );
    if ( is_wp_error( $updated ) ) { throw new RuntimeException( 'Product rollback failed' ); }
    wp_set_object_terms( $id, $backup['type'], 'product_type' );
    foreach ( $backup['meta'] as $key => $prior ) { if ( $prior['exists'] ) { update_post_meta( $id, $key, wp_slash( $prior['value'] ) ); } else { delete_post_meta( $id, $key ); } }
    if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'Identity rollback failed' ); }
   } catch ( Throwable $exception ) { $wpdb->query( 'ROLLBACK' ); throw $exception; }
   finally { clean_post_cache( $id ); clean_object_term_cache( $id, 'product' ); wc_delete_product_transients( $id ); WC_Cache_Helper::invalidate_cache_group( 'product_' . $id ); wc_get_container()->get( \Automattic\WooCommerce\Internal\Caches\ProductCache::class )->remove( $id ); }
  }
  return array( 'productId' => $id, 'action' => 'restore_legacy_identity' );
 }
 private static function backup( WP_Post $post ): void {
  if ( ! metadata_exists( 'post', $post->ID, self::BACKUP ) && ! add_post_meta( $post->ID, self::BACKUP, wp_slash( array( 'content' => $post->post_content, 'elementor' => get_post_meta( $post->ID, '_elementor_data', true ), 'createdAt' => gmdate( 'c' ) ) ), true ) ) { throw new RuntimeException( 'Migration backup failed' ); }
 }
 private static function save_elements( int $id, array $elements ): void {
  if ( ! class_exists( '\\Elementor\\Plugin' ) ) { throw new RuntimeException( 'Elementor must be active to migrate its documents' ); }
  $document = \Elementor\Plugin::$instance->documents->get( $id );
  if ( ! $document || false === $document->save( array( 'elements' => $elements ) ) ) { throw new RuntimeException( 'Elementor rejected the migrated document' ); }
 }
 private static function source_elements( array $elements, string $description, array &$issues ): array {
  foreach ( $elements as &$element ) {
   if ( $description && 'text-editor' === ( $element['widgetType'] ?? '' ) && trim( (string) ( $element['settings']['editor'] ?? '' ) ) === trim( $description ) ) { $element['settings']['editor'] = '[chidemoon_source_description]'; $issues[] = 'copied_elementor_source_description'; }
   if ( ! empty( $element['elements'] ) ) { $element['elements'] = self::source_elements( $element['elements'], $description, $issues ); }
  }
  return $elements;
 }
 private static function walk_shortcodes( string $content, WP_Post $post, bool $apply, array &$scenes ): string {
  $index = 0;
  return preg_replace_callback( '/' . get_shortcode_regex( array( 'chidemoon_shop_the_look' ) ) . '/s', static function( $match ) use ( $post, $apply, &$scenes, &$index ) {
   if ( '[' === $match[1] ) { return $match[0]; }
   $attributes = shortcode_parse_atts( $match[3] );
   if ( ! is_array( $attributes ) || ! empty( $attributes['look_id'] ) ) { return $match[0]; }
   $raw = $attributes['hotspots'] ?? '[]'; $spots = json_decode( $raw, true );
   if ( ! is_array( $spots ) ) { $spots = json_decode( base64_decode( $raw, true ) ?: '', true ); }
   if ( ! is_array( $spots ) ) { throw new RuntimeException( 'Shortcode hotspots require reconciliation' ); }
   $id = self::scene( $post, 'shortcode-' . $index++, array( 'imageId' => (int) ( $attributes['image_id'] ?? $attributes['imageid'] ?? 0 ), 'caption' => $attributes['caption'] ?? '', 'hotspots' => $spots ), $apply, $scenes );
   return $apply && $id ? '[chidemoon_shop_the_look look_id="' . $id . '"]' : $match[0];
  }, $content );
 }
 private static function scene( WP_Post $post, string $scene_id, array $settings, bool $apply, array &$scenes ): int {
  $existing = get_posts( array( 'post_type' => Chidemoon_Core_Looks::POST_TYPE, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 2, 'meta_key' => '_chidemoon_scene_key', 'meta_value' => $post->ID . ':' . $scene_id ) );
  if ( count( $existing ) > 1 ) { throw new RuntimeException( 'Duplicate scene mapping' ); }
  $image_id = (int) ( $settings['image']['id'] ?? $settings['imageId'] ?? 0 );
  $hotspots = $settings['hotspots'] ?? array();
  $rows = array(); $unresolved = array();
  foreach ( $hotspots as $index => $spot ) {
   $product_id = (int) ( $spot['product_id'] ?? $spot['productId'] ?? 0 );
   $source_key = (string) ( $spot['product_source_key'] ?? $spot['productSourceKey'] ?? '' );
   if ( ! $product_id && $source_key ) { $matches = Chidemoon_Core_Source_Importer::find_reference_ids( $source_key ); if ( count( $matches ) === 1 ) { $product_id = $matches[0]; } }
   if ( ! $product_id ) { $unresolved[] = $source_key ?: 'hotspot:' . $index; }
   if ( ! $product_id && ! $source_key ) { $source_key = 'unresolved:' . $post->ID . ':' . $scene_id . ':' . $index; }
   $x = $spot['x'] ?? null; $y = $spot['y'] ?? null;
   $rows[] = array( 'productId' => $product_id, 'sourceKey' => $source_key, 'role' => $spot['role'] ?? null, 'position' => $index, 'x' => is_array( $x ) ? $x['size'] ?? null : $x, 'y' => is_array( $y ) ? $y['size'] ?? null : $y );
  }
  $look_id = (int) ( $existing[0] ?? 0 );
  $report_index = count( $scenes );
  $scenes[] = array( 'postId' => $post->ID, 'originalUrl' => get_permalink( $post ), 'sceneId' => $scene_id, 'lookId' => $look_id, 'imageId' => $image_id, 'unresolved' => $unresolved, 'products' => $rows, 'action' => $look_id ? 'mapped' : 'create_look' );
  if ( ! $apply || $look_id ) { return $look_id; }
  if ( ! $image_id ) { throw new RuntimeException( 'Scene has no resolved cover image' ); }
  self::backup( $post );
  $look_id = wp_insert_post( wp_slash( array( 'post_type' => Chidemoon_Core_Looks::POST_TYPE, 'post_status' => 'draft', 'post_title' => $post->post_title, 'post_excerpt' => sanitize_text_field( $settings['caption'] ?? '' ), 'post_content' => '', 'meta_input' => array( '_chidemoon_scene_key' => $post->ID . ':' . $scene_id, '_chidemoon_scene_original_post_id' => $post->ID ) ) ), true );
  if ( is_wp_error( $look_id ) || ! $look_id ) { throw new RuntimeException( 'Look creation failed' ); }
  set_post_thumbnail( $look_id, $image_id );
  Chidemoon_Core_Looks::set_relationships( $look_id, $rows, '0', true );
  foreach ( array( 'chidemoon_room', 'chidemoon_style' ) as $taxonomy ) { $terms = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) ); if ( ! is_wp_error( $terms ) ) { wp_set_object_terms( $look_id, $terms, $taxonomy, false ); } }
  if ( 'publish' === $post->post_status && ! $unresolved ) { wp_update_post( array( 'ID' => $look_id, 'post_status' => 'publish' ) ); }
  $scenes[ $report_index ]['lookId'] = $look_id;
  return $look_id;
 }
 private static function walk_elements( array $elements, WP_Post $post, bool $apply, array &$scenes ): array {
  foreach ( $elements as &$element ) {
   if ( 'chidemoon-shop-the-look' === ( $element['widgetType'] ?? '' ) && empty( $element['settings']['look_id'] ) ) {
    $id = self::scene( $post, 'elementor-' . ( $element['id'] ?? '' ), $element['settings'] ?? array(), $apply, $scenes );
    if ( $apply && $id ) { $element['settings']['look_id'] = (string) $id; unset( $element['settings']['hotspots'], $element['settings']['image'], $element['settings']['caption'], $element['settings']['image_alt'] ); }
   }
   if ( ! empty( $element['elements'] ) ) { $element['elements'] = self::walk_elements( $element['elements'], $post, $apply, $scenes ); }
  }
  return $elements;
 }
 private static function walk_blocks( array $blocks, WP_Post $post, bool $apply, array &$scenes, string $prefix = '' ): array {
  foreach ( $blocks as $index => &$block ) {
   if ( 'chidemoon/shop-the-look' === ( $block['blockName'] ?? '' ) && empty( $block['attrs']['lookId'] ) ) {
    $id = self::scene( $post, 'block-' . $prefix . $index, $block['attrs'] ?? array(), $apply, $scenes );
    if ( $apply && $id ) { $block['attrs'] = array( 'lookId' => $id ); }
   }
   if ( ! empty( $block['innerBlocks'] ) ) { $block['innerBlocks'] = self::walk_blocks( $block['innerBlocks'], $post, $apply, $scenes, $prefix . $index . '-' ); }
  }
  return $blocks;
 }
 private static function rollback( int $id ): void {
  $backup = get_post_meta( $id, self::BACKUP, true );
  if ( ! is_array( $backup ) ) { return; }
  if ( $backup['elementor'] && class_exists( '\Elementor\Plugin' ) ) { $document = \Elementor\Plugin::$instance->documents->get( $id ); if ( ! $document || false === $document->save( array( 'elements' => json_decode( $backup['elementor'], true ) ) ) ) { throw new RuntimeException( 'Elementor rollback failed' ); } }
  $updated = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $backup['content'] ) ), true );
  if ( is_wp_error( $updated ) ) { throw new RuntimeException( 'Content rollback failed' ); }
 }
}
