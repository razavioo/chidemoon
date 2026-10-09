<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Core_Looks {
 public const POST_TYPE = 'chidemoon_look';
 public const PALETTE = '_chidemoon_look_palette';
 public static function register(): void {
  add_action( 'init', array( __CLASS__, 'register_model' ), 6 );
  add_filter( 'use_block_editor_for_post_type', static fn( $use, $type ) => self::POST_TYPE === $type ? false : $use, 10, 2 );
  add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'add_editor' ) );
  add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_editor' ), 20, 2 );
  add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
  add_filter( 'rest_pre_insert_' . self::POST_TYPE, static function( $prepared, $request ) {
   $meta = $request->get_param( 'meta' );
   if ( is_array( $meta ) && array_key_exists( self::PALETTE, $meta ) ) {
    try { self::palette( $meta[ self::PALETTE ] ); }
    catch ( Throwable $exception ) { return new WP_Error( 'invalid_look_palette', $exception->getMessage(), array( 'status' => 422 ) ); }
   }
   return $prepared;
  }, 10, 2 );
  add_action( 'admin_enqueue_scripts', static function(): void {
   if ( self::POST_TYPE !== ( get_current_screen()->post_type ?? '' ) ) { return; }
   wp_enqueue_script( 'chidemoon-look-editor', CHIDEMOON_CORE_URL . 'assets/js/look-editor.js', array( 'wp-api-fetch' ), CHIDEMOON_CORE_VERSION, true );
  } );
  add_action( 'admin_notices', static function(): void {
   $key = 'chidemoon_look_error_' . get_current_user_id(); $error = get_transient( $key );
   if ( $error ) { delete_transient( $key ); echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>'; }
  } );
  add_filter( 'the_content', array( __CLASS__, 'render_content' ), 25 );
  add_shortcode( 'chidemoon_product_looks', array( __CLASS__, 'product_looks_shortcode' ) );
  add_action( 'woocommerce_after_single_product_summary', array( __CLASS__, 'product_looks' ), 12 );
  add_filter( 'option_elementor_cpt_support', static fn( $types ) => array_values( array_unique( array_merge( $types, array( self::POST_TYPE ) ) ) ) );
 }
 public static function install(): void {
  global $wpdb;
  dbDelta( "CREATE TABLE {$wpdb->prefix}chidemoon_look_products (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   look_id bigint(20) unsigned NOT NULL,
   product_id bigint(20) unsigned DEFAULT NULL,
   source_key varchar(190) DEFAULT NULL,
   role varchar(20) DEFAULT NULL,
   position int unsigned NOT NULL DEFAULT 0,
   hotspot_x decimal(7,4) DEFAULT NULL,
   hotspot_y decimal(7,4) DEFAULT NULL,
   hotspot_approved tinyint(1) NOT NULL DEFAULT 0,
   PRIMARY KEY  (id),
   UNIQUE KEY look_product (look_id, product_id),
   KEY look_position (look_id, position),
   KEY product_look (product_id, look_id),
   KEY source_look (source_key, look_id)
  ) {$wpdb->get_charset_collate()};" );
 }
 public static function register_model(): void {
  $capabilities = array_fill_keys( array( 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ), 'chidemoon_manage_affiliate' );
  $capabilities['edit_post'] = 'edit_chidemoon_look'; $capabilities['read_post'] = 'read_chidemoon_look'; $capabilities['delete_post'] = 'delete_chidemoon_look';
  register_post_type( self::POST_TYPE, array( 'labels' => array( 'name' => __( 'Looks', 'chidemoon-core' ), 'singular_name' => __( 'Look', 'chidemoon-core' ) ), 'public' => true, 'show_in_rest' => true, 'has_archive' => true, 'rewrite' => array( 'slug' => 'looks' ), 'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields', 'author', 'elementor' ), 'taxonomies' => array( 'chidemoon_style', 'chidemoon_room' ), 'map_meta_cap' => true, 'capabilities' => $capabilities, 'menu_icon' => 'dashicons-layout' ) );
  register_taxonomy_for_object_type( 'chidemoon_room', self::POST_TYPE );
  register_post_meta( self::POST_TYPE, self::PALETTE, array( 'type' => 'array', 'single' => true, 'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'additionalProperties' => false, 'properties' => array( 'colorId' => array( 'type' => 'integer', 'minimum' => 1 ), 'hex' => array( 'type' => 'string', 'pattern' => '^#[a-fA-F0-9]{6}$' ), 'proportion' => array( 'type' => 'number', 'minimum' => 0, 'maximum' => 1 ) ), 'required' => array( 'colorId' ) ) ) ), 'sanitize_callback' => array( __CLASS__, 'palette' ), 'auth_callback' => static fn( $allowed, $key, $id ) => current_user_can( 'chidemoon_manage_affiliate' ) && current_user_can( 'edit_post', $id ) ) );
 }
 public static function palette( $value ): array {
  if ( ! is_array( $value ) || count( $value ) > 12 ) { throw new InvalidArgumentException( 'Invalid palette' ); }
  $colors = array(); $total = 0;
  foreach ( $value as $color ) {
   if ( ! is_array( $color ) || array_diff( array_keys( $color ), array( 'colorId', 'hex', 'proportion' ) ) || ! is_int( $color['colorId'] ?? null ) || ! term_exists( $color['colorId'], 'pa_color' ) ) { throw new InvalidArgumentException( 'Palette needs approved color references' ); }
   if ( isset( $color['hex'] ) && ! preg_match( '/^#[a-fA-F0-9]{6}$/D', $color['hex'] ) ) { throw new InvalidArgumentException( 'Invalid palette HEX' ); }
   if ( isset( $color['proportion'] ) ) { if ( ! is_numeric( $color['proportion'] ) || $color['proportion'] < 0 || $color['proportion'] > 1 ) { throw new InvalidArgumentException( 'Invalid palette proportion' ); } $total += (float) $color['proportion']; }
   $colors[] = $color;
  }
  if ( $total > 1.00001 ) { throw new InvalidArgumentException( 'Palette proportions exceed 100%' ); }
  return $colors;
 }
 public static function relationships( int $look_id ): array {
  global $wpdb;
  return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}chidemoon_look_products WHERE look_id = %d ORDER BY position, id", $look_id ), ARRAY_A ) ?: array();
 }
 public static function set_relationships( int $look_id, array $rows, string $expected_revision, bool $hotspots_confirmed = false, bool $manage_transaction = true ): void {
  global $wpdb;
  if ( self::POST_TYPE !== get_post_type( $look_id ) || ! current_user_can( 'edit_post', $look_id ) || ! current_user_can( 'chidemoon_manage_affiliate' ) || count( $rows ) > 50 ) { throw new RuntimeException( 'forbidden' ); }
  if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', 'ch_look_' . $look_id ) ) ) { throw new RuntimeException( 'revision_conflict' ); }
  try {
   wp_cache_delete( $look_id, 'post_meta' );
   if ( (string) (int) get_post_meta( $look_id, '_chidemoon_look_revision', true ) !== $expected_revision ) { throw new RuntimeException( 'revision_conflict' ); }
   $clean = array(); $seen = array();
   $previous = self::relationships( $look_id );
   foreach ( $rows as $index => $row ) {
    if ( ! is_array( $row ) || array_diff( array_keys( $row ), array( 'productId', 'sourceKey', 'role', 'x', 'y', 'position' ) ) ) { throw new InvalidArgumentException( 'Invalid Look relationship' ); }
    $product_id = (int) ( $row['productId'] ?? 0 ); $source_key = trim( (string) ( $row['sourceKey'] ?? '' ) );
    if ( $source_key && ! preg_match( '/^[A-Za-z0-9._:-]{1,190}$/D', $source_key ) ) { throw new InvalidArgumentException( 'Invalid source reference' ); }
    if ( ! $product_id && $source_key ) {
     if ( str_starts_with( $source_key, 'src:v2:' ) ) { $product_id = Chidemoon_Core_Source_Importer::resolve( $source_key ); }
     else { $matches = Chidemoon_Core_Source_Importer::find_reference_ids( $source_key ); if ( count( $matches ) > 1 ) { throw new RuntimeException( 'identity_collision' ); } $product_id = $matches[0] ?? 0; }
    }
    if ( ! $product_id && ! $source_key ) { throw new InvalidArgumentException( 'A product or unresolved source reference is required' ); }
    if ( $product_id && ( 'product' !== get_post_type( $product_id ) || isset( $seen[ $product_id ] ) ) ) { throw new InvalidArgumentException( 'Invalid or duplicate product reference' ); }
    if ( $product_id ) { $seen[ $product_id ] = true; }
    $role = $row['role'] ?? null;
    if ( '' === $role ) { $role = null; }
    if ( null !== $role && ! in_array( $role, array( 'main', 'secondary', 'accent', 'decorative' ), true ) ) { throw new InvalidArgumentException( 'Invalid role' ); }
    $x = $row['x'] ?? null; $y = $row['y'] ?? null;
    if ( ( null === $x ) !== ( null === $y ) || ( null !== $x && ( ! is_numeric( $x ) || ! is_numeric( $y ) || ! is_finite( (float) $x ) || ! is_finite( (float) $y ) || $x < 0 || $x > 100 || $y < 0 || $y > 100 ) ) ) { throw new InvalidArgumentException( 'Hotspot coordinates must be paired percentages from 0 to 100' ); }
    $position = $row['position'] ?? $index;
    if ( ! is_int( $position ) || $position < 0 || $position > 10000 ) { throw new InvalidArgumentException( 'Invalid product order' ); }
    $confirmed = $hotspots_confirmed;
    foreach ( $previous as $old ) {
     if ( (int) $old['product_id'] === $product_id && (string) $old['source_key'] === $source_key && null !== $x && null !== $old['hotspot_x'] && (float) $old['hotspot_x'] === (float) $x && (float) $old['hotspot_y'] === (float) $y && $old['hotspot_approved'] ) { $confirmed = true; }
    }
    $clean[] = array( 'look_id' => $look_id, 'product_id' => $product_id ?: null, 'source_key' => $source_key ?: null, 'role' => $role, 'position' => $position, 'hotspot_x' => $x, 'hotspot_y' => $y, 'hotspot_approved' => $confirmed && null !== $x ? 1 : 0 );
   }
   if ( $manage_transaction ) { $wpdb->query( 'START TRANSACTION' ); }
   try {
    if ( false === $wpdb->delete( $wpdb->prefix . 'chidemoon_look_products', array( 'look_id' => $look_id ), array( '%d' ) ) ) { throw new RuntimeException( 'Relationship save failed' ); }
    foreach ( $clean as $row ) { if ( false === $wpdb->insert( $wpdb->prefix . 'chidemoon_look_products', $row ) ) { throw new RuntimeException( 'Relationship save failed' ); } }
    update_post_meta( $look_id, '_chidemoon_look_revision', (int) $expected_revision + 1 );
    if ( $manage_transaction ) { $wpdb->query( 'COMMIT' ); }
   } catch ( Throwable $exception ) { if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); } wp_cache_delete( $look_id, 'post_meta' ); throw $exception; }
  } finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'ch_look_' . $look_id ) ); }
 }
 public static function render( int $id, string $extra_class = '' ): string {
  if ( self::POST_TYPE !== get_post_type( $id ) || ( 'publish' !== get_post_status( $id ) && ! current_user_can( 'edit_post', $id ) ) ) { return ''; }
  $spots = array(); $list = '<ul class="ch-look-products">';
  foreach ( self::relationships( $id ) as $row ) {
   $product = ! empty( $row['product_id'] ) ? wc_get_product( (int) $row['product_id'] ) : false;
   if ( ! $product instanceof WC_Product || ! Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) {
    if ( current_user_can( 'edit_post', $id ) ) { $list .= '<li>' . esc_html__( 'Product reference requires reconciliation', 'chidemoon-core' ) . ' ' . esc_html( (string) $row['source_key'] ) . '</li>'; }
    continue;
   }
   $list .= '<li><a href="' . esc_url( get_permalink( $product->get_id() ) ) . '">' . esc_html( $product->get_name() ) . '</a> ' . esc_html__( ucfirst( (string) $row['role'] ), 'chidemoon-core' ) . ' ' . $product->get_price_html();
   if ( ! Chidemoon_Core_Affiliate::is_offer_available( $product ) ) {
    $list .= '<span>' . esc_html__( 'Unavailable at merchant', 'chidemoon-core' ) . '</span>';
    if ( class_exists( 'Chidemoon_Core_Discovery' ) ) { $list .= Chidemoon_Core_Discovery::alternatives( $product->get_id() ); }
   }
   $list .= '</li>';
   if ( null !== $row['hotspot_x'] && null !== $row['hotspot_y'] && $row['hotspot_approved'] ) { $spots[] = array( 'productId' => $product->get_id(), 'x' => (float) $row['hotspot_x'], 'y' => (float) $row['hotspot_y'] ); }
  }
  $image = get_post_thumbnail_id( $id );
  $html = Chidemoon_Core_Shop_The_Look::render_look( array( 'imageId' => $image, 'caption' => wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $id ) ), 'hotspots' => $spots ), $extra_class );
  $html .= $list . '</ul><ul class="ch-look-palette" aria-label="' . esc_attr__( 'Approved palette', 'chidemoon-core' ) . '">';
  foreach ( (array) get_post_meta( $id, self::PALETTE, true ) as $color ) {
   $term = get_term( (int) ( $color['colorId'] ?? 0 ), 'pa_color' );
   if ( ! $term || is_wp_error( $term ) ) { continue; }
   $style = ! empty( $color['hex'] ) && preg_match( '/^#[a-fA-F0-9]{6}$/D', $color['hex'] ) ? ' style="background-color:' . esc_attr( $color['hex'] ) . '"' : '';
   $html .= '<li><span aria-hidden="true"' . $style . '></span>' . esc_html( $term->name ) . ( isset( $color['proportion'] ) ? ' ' . esc_html( (string) round( 100 * $color['proportion'] ) . '%' ) : '' ) . '</li>';
  }
  return $html . '</ul>';
 }
 public static function render_content( string $content ): string { if ( is_singular( self::POST_TYPE ) && in_the_loop() && is_main_query() && ! str_contains( $content, 'chidemoon-shop-the-look' ) ) { return $content . self::render( get_the_ID() ); } return $content; }
 public static function product_looks(): void { echo self::product_looks_shortcode(); }
 public static function product_looks_shortcode( array $attributes = array() ): string {
  global $wpdb;
  $id = absint( $attributes['product_id'] ?? get_the_ID() );
  $ids = $wpdb->get_col( $wpdb->prepare( "SELECT look_id FROM {$wpdb->prefix}chidemoon_look_products WHERE product_id = %d ORDER BY look_id", $id ) );
  $html = '';
  foreach ( $ids as $look_id ) { if ( 'publish' === get_post_status( $look_id ) ) { $html .= '<li><a href="' . esc_url( get_permalink( $look_id ) ) . '">' . esc_html( get_the_title( $look_id ) ) . '</a></li>'; } }
  return $html ? '<section class="ch-product-looks"><h2>' . esc_html__( 'Used in these Looks', 'chidemoon-core' ) . '</h2><ul>' . $html . '</ul></section>' : '';
 }
 public static function register_routes(): void {
  register_rest_route( 'chidemoon-core/v1', '/looks/product-search', array(
   'methods' => 'GET', 'permission_callback' => static fn() => current_user_can( 'chidemoon_manage_affiliate' ),
   'callback' => static function( $request ) {
    $query = new WP_Query( array( 'post_type' => 'product', 'post_status' => array( 'publish', 'draft', 'pending' ), 'posts_per_page' => 20, 's' => sanitize_text_field( (string) $request->get_param( 'search' ) ), 'tax_query' => array( array( 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => array( 'external' ) ) ) ) );
    $products = array();
    foreach ( $query->posts as $post ) { if ( current_user_can( 'edit_post', $post->ID ) ) { $products[] = array( 'id' => $post->ID, 'title' => $post->post_title, 'status' => $post->post_status ); } }
    return $products;
   },
  ) );
  register_rest_route( 'chidemoon-core/v1', '/looks/(?P<id>\d+)/preview', array(
   'methods' => 'GET', 'permission_callback' => static fn( $request ) => self::POST_TYPE === get_post_type( (int) $request['id'] ) && ( 'publish' === get_post_status( (int) $request['id'] ) || current_user_can( 'edit_post', (int) $request['id'] ) ),
   'callback' => static fn( $request ) => array( 'html' => self::render( (int) $request['id'] ), 'editUrl' => current_user_can( 'edit_post', (int) $request['id'] ) ? get_edit_post_link( (int) $request['id'], 'raw' ) : null ),
  ) );
  register_rest_route( 'chidemoon-core/v1', '/looks/(?P<id>\d+)/products', array(
   'methods' => 'PUT', 'permission_callback' => static fn( $request ) => current_user_can( 'chidemoon_manage_affiliate' ) && current_user_can( 'edit_post', (int) $request['id'] ),
   'callback' => static function( $request ) {
    try { self::set_relationships( (int) $request['id'], (array) $request->get_param( 'products' ), (string) $request->get_param( 'revision' ), true === $request->get_param( 'confirmHotspots' ) ); return array( 'revision' => (int) get_post_meta( (int) $request['id'], '_chidemoon_look_revision', true ), 'products' => self::relationships( (int) $request['id'] ) ); }
    catch ( Throwable $exception ) { return new WP_Error( 'look_save_rejected', $exception->getMessage(), array( 'status' => 'revision_conflict' === $exception->getMessage() ? 409 : 422 ) ); }
   },
  ) );
 }
 public static function add_editor(): void { add_meta_box( 'chidemoon-look-products', __( 'Look products, roles, hotspots and palette', 'chidemoon-core' ), array( __CLASS__, 'render_editor' ), self::POST_TYPE, 'normal', 'high' ); }
 public static function render_editor( WP_Post $post ): void {
  wp_nonce_field( 'chidemoon_look_' . $post->ID, 'chidemoon_look_nonce' );
  echo '<div class="ch-look-editor"><input type="hidden" name="chidemoon_look_revision" value="' . (int) get_post_meta( $post->ID, '_chidemoon_look_revision', true ) . '"><p>' . esc_html__( 'Products and coordinates belong to this Look. X/Y are paired percentages measured from the image top-left corner. Leave both blank for a list-only item.', 'chidemoon-core' ) . '</p><label>' . esc_html__( 'Find products', 'chidemoon-core' ) . ' <input class="ch-look-product-search" type="search" autocomplete="off"></label><ul class="ch-look-search-results" aria-live="polite"></ul>';
  $cover = get_post_thumbnail_id( $post->ID );
  if ( $cover ) { echo '<figure class="ch-look-editor-canvas" style="position:relative;max-width:900px;margin:16px 0">' . wp_get_attachment_image( $cover, 'large', false, array( 'style' => 'width:100%;height:auto;display:block;' ) ) . '<div class="ch-look-editor-points" style="position:absolute;inset:0;pointer-events:none"></div></figure>'; }
  echo '<div style="overflow-x:auto"><table class="widefat ch-look-editor-table"><thead><tr><th>' . esc_html__( 'Product ID / source key', 'chidemoon-core' ) . '</th><th>' . esc_html__( 'Role', 'chidemoon-core' ) . '</th><th>' . esc_html__( 'Order', 'chidemoon-core' ) . '</th><th>X</th><th>Y</th><th>' . esc_html__( 'Remove', 'chidemoon-core' ) . '</th></tr></thead><tbody>';
  $rows = self::relationships( $post->ID ); $rows[] = array(); $rows[] = array();
  foreach ( $rows as $index => $row ) {
   echo '<tr><td><input type="number" min="1" name="chidemoon_look_products[' . $index . '][productId]" value="' . esc_attr( (string) ( $row['product_id'] ?? '' ) ) . '" aria-label="' . esc_attr__( 'Product ID', 'chidemoon-core' ) . '"><input name="chidemoon_look_products[' . $index . '][sourceKey]" value="' . esc_attr( (string) ( $row['source_key'] ?? '' ) ) . '" aria-label="' . esc_attr__( 'Source key', 'chidemoon-core' ) . '"></td><td><select aria-label="' . esc_attr__( 'Role', 'chidemoon-core' ) . '" name="chidemoon_look_products[' . $index . '][role]">';
   foreach ( array( '', 'main', 'secondary', 'accent', 'decorative' ) as $role ) { echo '<option value="' . esc_attr( $role ) . '" ' . selected( $role, $row['role'] ?? '', false ) . '>' . esc_html__( '' === $role ? 'No role' : ucfirst( $role ), 'chidemoon-core' ) . '</option>'; }
   echo '</select></td>';
   echo '<td><input style="width:65px" type="number" min="0" max="10000" name="chidemoon_look_products[' . $index . '][position]" value="' . (int) ( $row['position'] ?? $index ) . '" aria-label="' . esc_attr__( 'Order', 'chidemoon-core' ) . '"></td>';
   foreach ( array( 'x', 'y' ) as $axis ) { echo '<td><input style="width:80px" type="number" min="0" max="100" step="0.0001" name="chidemoon_look_products[' . $index . '][' . $axis . ']" value="' . esc_attr( (string) ( $row['hotspot_' . $axis] ?? '' ) ) . '" aria-label="' . esc_attr( strtoupper( $axis ) ) . '"></td>'; }
   echo '<td><button type="button" class="button ch-look-remove">' . esc_html__( 'Remove', 'chidemoon-core' ) . '</button></td></tr>';
  }
  echo '</tbody></table></div><p><button type="button" class="button ch-look-add">' . esc_html__( 'Add product', 'chidemoon-core' ) . '</button></p><p><label><input type="checkbox" name="chidemoon_confirm_hotspots" value="1"> ' . esc_html__( 'I reviewed and confirm the displayed hotspot coordinates', 'chidemoon-core' ) . '</label></p><h3>' . esc_html__( 'Approved palette', 'chidemoon-core' ) . '</h3><p>' . esc_html__( 'Select approved colors. HEX and proportion are optional; proportions together cannot exceed 100%.', 'chidemoon-core' ) . '</p><table class="widefat ch-look-palette-editor"><thead><tr><th>' . esc_html__( 'Color', 'chidemoon-core' ) . '</th><th>HEX</th><th>' . esc_html__( 'Proportion (%)', 'chidemoon-core' ) . '</th><th>' . esc_html__( 'Remove', 'chidemoon-core' ) . '</th></tr></thead><tbody>';
  $palette = (array) get_post_meta( $post->ID, self::PALETTE, true ); $palette[] = array();
  $terms = get_terms( array( 'taxonomy' => 'pa_color', 'hide_empty' => false ) );
  foreach ( $palette as $index => $color ) {
   echo '<tr><td><select aria-label="' . esc_attr__( 'Color', 'chidemoon-core' ) . '" name="chidemoon_palette_rows[' . $index . '][colorId]"><option value="">—</option>';
   foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) { echo '<option value="' . (int) $term->term_id . '" ' . selected( $term->term_id, $color['colorId'] ?? '', false ) . '>' . esc_html( $term->name ) . '</option>'; }
   echo '</select></td><td><input name="chidemoon_palette_rows[' . $index . '][hex]" aria-label="HEX" pattern="#[a-fA-F0-9]{6}" placeholder="#aabbcc" value="' . esc_attr( $color['hex'] ?? '' ) . '"></td><td><input name="chidemoon_palette_rows[' . $index . '][proportion]" aria-label="' . esc_attr__( 'Proportion (%)', 'chidemoon-core' ) . '" type="number" min="0" max="100" step="0.01" value="' . esc_attr( isset( $color['proportion'] ) ? (string) ( 100 * $color['proportion'] ) : '' ) . '"></td><td><button type="button" class="button ch-look-palette-remove">' . esc_html__( 'Remove', 'chidemoon-core' ) . '</button></td></tr>';
  }
  echo '</tbody></table><p><button type="button" class="button ch-look-palette-add">' . esc_html__( 'Add color', 'chidemoon-core' ) . '</button></p></div>';
 }
 public static function save_editor( int $id, WP_Post $post ): void {
  if ( wp_is_post_revision( $id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! isset( $_POST['chidemoon_look_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['chidemoon_look_nonce'] ) ), 'chidemoon_look_' . $id ) ) { return; }
  try {
   $rows = array();
   foreach ( (array) ( $_POST['chidemoon_look_products'] ?? array() ) as $row ) {
    if ( ! is_array( $row ) || ( empty( $row['productId'] ) && empty( $row['sourceKey'] ) ) ) { continue; }
    $rows[] = array( 'productId' => (int) ( $row['productId'] ?? 0 ), 'sourceKey' => sanitize_text_field( wp_unslash( $row['sourceKey'] ?? '' ) ), 'role' => sanitize_key( $row['role'] ?? '' ), 'position' => (int) ( $row['position'] ?? count( $rows ) ), 'x' => '' === ( $row['x'] ?? '' ) ? null : (float) $row['x'], 'y' => '' === ( $row['y'] ?? '' ) ? null : (float) $row['y'] );
   }
   $palette_rows = array();
   foreach ( (array) ( $_POST['chidemoon_palette_rows'] ?? array() ) as $color ) {
    if ( ! is_array( $color ) || empty( $color['colorId'] ) ) { continue; }
    $row = array( 'colorId' => (int) $color['colorId'] );
    if ( '' !== ( $color['hex'] ?? '' ) ) { $row['hex'] = sanitize_text_field( wp_unslash( $color['hex'] ) ); }
    if ( '' !== ( $color['proportion'] ?? '' ) ) { if ( ! is_numeric( $color['proportion'] ) ) { throw new InvalidArgumentException( 'Invalid palette proportion' ); } $row['proportion'] = (float) $color['proportion'] / 100; }
    $palette_rows[] = $row;
   }
   $palette = self::palette( $palette_rows );
   self::set_relationships( $id, $rows, sanitize_text_field( wp_unslash( $_POST['chidemoon_look_revision'] ?? '' ) ), ! empty( $_POST['chidemoon_confirm_hotspots'] ) );
   update_post_meta( $id, self::PALETTE, $palette );
  } catch ( Throwable $exception ) { set_transient( 'chidemoon_look_error_' . get_current_user_id(), $exception->getMessage(), 60 ); }
 }
}
