<?php
/** Explicit, bidirectional product/editorial references. No keyword-based associations. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Core_Content_Relations {
 const EDITORIAL = '_chidemoon_editorial_product_ids';
 const INDEX = '_chidemoon_content_product';
 const TYPES = array( 'post', 'page', 'chidemoon_look', 'reycob_look' );
 private static bool $indexing = false;

 public static function register(): void {
  add_shortcode( 'chidemoon_product_context', array( __CLASS__, 'render_context' ) );
  add_action( 'save_post', static function ( $id ): void { self::index( (int) $id ); }, 100 );
  foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
   add_action( $hook, static function ( $mid, $id, $key ): void {
    if ( in_array( $key, array( '_elementor_data', self::EDITORIAL, '_reycob_stl_points' ), true ) ) { self::index( (int) $id ); }
   }, 100, 3 );
  }
  add_filter( 'elementor/widget/render_content', array( __CLASS__, 'append_products' ), 90, 2 );
  add_action( 'add_meta_boxes', static function (): void {
   add_meta_box( 'chidemoon-content-products', 'محصولات این مطلب', array( __CLASS__, 'meta_box' ), array( 'post', 'page' ), 'side' );
  } );
  add_action( 'admin_enqueue_scripts', static function ( $hook ): void {
   if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && in_array( get_current_screen()->post_type ?? '', array( 'post', 'page' ), true ) ) {
    wp_enqueue_script( 'wc-enhanced-select' ); wp_enqueue_style( 'woocommerce_admin_styles' );
   }
  } );
  add_action( 'save_post', array( __CLASS__, 'save_editorial' ), 90 );
 }

 public static function meta_box( WP_Post $post ): void {
  wp_nonce_field( 'chidemoon_content_products', 'chidemoon_content_products_nonce' );
  echo '<p>محصولاتی را انتخاب کن که در این راهنما یا مقایسه معرفی شده‌اند.</p><select class="wc-product-search" multiple="multiple" style="width:100%" name="chidemoon_content_products[]" data-placeholder="جستجوی محصول…" data-action="woocommerce_json_search_products" data-allow_clear="true">';
  foreach ( (array) get_post_meta( $post->ID, self::EDITORIAL, true ) as $id ) {
   $product = wc_get_product( (int) $id );
   if ( $product ) { echo '<option selected value="' . (int) $id . '">' . esc_html( $product->get_name() ) . '</option>'; }
  }
  echo '</select>';
 }

 public static function save_editorial( int $id ): void {
  if ( ! isset( $_POST['chidemoon_content_products_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['chidemoon_content_products_nonce'] ) ), 'chidemoon_content_products' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) ) { return; }
  $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $_POST['chidemoon_content_products'] ?? array() ) ), static fn( $id ) => 'product' === get_post_type( $id ) ) ) );
  update_post_meta( $id, self::EDITORIAL, $ids );
 }

 /** Extract product IDs only from known product fields, shortcodes and actual product links. */
 public static function references( int $id ): array {
  $ids = array_map( 'absint', (array) get_post_meta( $id, self::EDITORIAL, true ) );
  if ( 'chidemoon_look' === get_post_type( $id ) ) {
   foreach ( Chidemoon_Core_Looks::relationships( $id ) as $row ) { $ids[] = (int) $row['product_id']; }
  }
  foreach ( (array) get_post_meta( $id, '_reycob_stl_points', true ) as $point ) { if ( is_array( $point ) ) { $ids[] = absint( $point['product_id'] ?? 0 ); } }
  $walk = static function ( array $data ) use ( &$walk, &$ids ): void {
   if ( 'chidemoon-compare-table' === ( $data['widgetType'] ?? '' ) ) {
    $override = $data['settings']['ids_text'] ?? '';
    foreach ( preg_split( '/[\s,]+/', (string) $override ) as $value ) { if ( ctype_digit( $value ) ) { $ids[] = (int) $value; } }
    if ( '' !== trim( (string) $override ) ) { unset( $data['settings']['products'] ); }
   }
   foreach ( $data as $key => $value ) {
    if ( in_array( $key, array( 'product_id', 'productId', 'product_ids', 'productIds' ), true ) ) {
     foreach ( is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value ) as $candidate ) { if ( is_scalar( $candidate ) && ctype_digit( (string) $candidate ) ) { $ids[] = absint( $candidate ); } }
    } elseif ( in_array( $key, array( 'product_source_key', 'productSourceKey' ), true ) && is_string( $value ) ) {
     $matches = Chidemoon_Core_Source_Importer::find_reference_ids( $value );
     if ( 1 === count( $matches ) ) { $ids[] = (int) $matches[0]; }
    }
    if ( is_array( $value ) ) { $walk( $value ); }
   }
  };
  $raw = (string) get_post_meta( $id, '_elementor_data', true );
  $data = json_decode( $raw, true );
  if ( is_array( $data ) ) { $walk( $data ); }
  $walk( parse_blocks( (string) get_post_field( 'post_content', $id ) ) );
  $text = (string) get_post_field( 'post_content', $id ) . '\n' . ( is_array( $data ) ? wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '' );
  if ( preg_match_all( '/\[chidemoon_(?:compare_table|compare_action|shop_the_look)\b([^\]]*)\]/', $text, $shortcodes ) ) {
   foreach ( $shortcodes[1] as $attributes ) {
    $atts = shortcode_parse_atts( str_replace( '\\"', '"', $attributes ) );
    if ( is_array( $atts ) ) { foreach ( array( 'products', 'ids', 'product_id' ) as $key ) { foreach ( preg_split( '/[\s,]+/', (string) ( $atts[$key] ?? '' ) ) as $value ) { if ( ctype_digit( $value ) ) { $ids[] = (int) $value; } } } }
   }
  }
  // Match URLs, not bare numbers: unrelated Elementor/template IDs cannot create relations.
  if ( preg_match_all( '~(?:https?://[^\s"<>\\\\]+)?/product/[^\s"<>\\\\]+~u', $text, $urls ) ) {
   foreach ( array_unique( $urls[0] ) as $url ) {
    $url = html_entity_decode( $url, ENT_QUOTES, 'UTF-8' );
    $host = wp_parse_url( $url, PHP_URL_HOST );
    if ( $host && $host !== wp_parse_url( home_url(), PHP_URL_HOST ) ) { continue; }
    $ids[] = url_to_postid( $host ? $url : home_url( $url ) );
   }
  }
  return array_values( array_unique( array_filter( $ids, static fn( $product_id ) => $product_id > 0 && 'product' === get_post_type( $product_id ) ) ) );
 }

 public static function index( int $id ): void {
  if ( self::$indexing || ! in_array( get_post_type( $id ), self::TYPES, true ) || wp_is_post_revision( $id ) ) { return; }
  self::$indexing = true;
  try {
   $ids = self::references( $id );
   $old = array_map( 'intval', get_post_meta( $id, self::INDEX, false ) ); sort( $ids ); sort( $old );
   if ( $ids === $old ) { return; }
   delete_post_meta( $id, self::INDEX );
   foreach ( $ids as $product_id ) { add_post_meta( $id, self::INDEX, $product_id ); }
  } finally { self::$indexing = false; }
 }

 public static function related_content( int $product_id ): array {
  global $wpdb;
  $ids = get_posts( array( 'post_type' => self::TYPES, 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => self::INDEX, 'meta_value' => $product_id ) );
  // Native relationships can change outside a post save (REST/reconciliation).
  $looks = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT look_id FROM {$wpdb->prefix}chidemoon_look_products WHERE product_id = %d", $product_id ) );
  $ids = array_values( array_unique( array_map( 'intval', array_merge( $ids, $looks ) ) ) );
  $items = array();
  foreach ( $ids as $id ) {
   if ( 'publish' !== get_post_status( $id ) || get_post_field( 'post_password', $id ) || ! in_array( get_post_type( $id ), self::TYPES, true ) || (int) get_option( 'page_on_front' ) === $id ) { continue; }
   $scene = (int) get_post_meta( $id, '_chidemoon_reycob_scene', true );
   if ( $scene && 'publish' === get_post_status( $scene ) && ! get_post_field( 'post_password', $scene ) ) { $id = $scene; }
   $items[$id] = $id;
  }
  return array_values( $items );
 }

 public static function render_context( $atts = array() ): string {
  $id = (int) get_queried_object_id();
  if ( 'product' !== get_post_type( $id ) ) { return ''; }
  $items = self::related_content( $id );
  if ( ! $items ) { return ''; }
  $heading_id = wp_unique_id( 'ch-context-' );
  $html = '<section class="ch-product-context" aria-labelledby="' . esc_attr( $heading_id ) . '"><p class="ch-context-eyebrow">از محصول تا ایده</p><h2 id="' . esc_attr( $heading_id ) . '">' . esc_html( $atts['heading'] ?? 'این محصول در چیدمون' ) . '</h2><div class="ch-context-grid">';
  foreach ( $items as $item ) {
   $type = get_post_type( $item );
   $look = in_array( $type, array( 'chidemoon_look', 'reycob_look' ), true ) || (bool) get_post_meta( $item, '_chidemoon_native_look', true );
   $url = 'reycob_look' === $type ? home_url( '/shop-the-look/#ch-scene-' . $item ) : get_permalink( $item );
   $image_id = 'reycob_look' === $type ? (int) get_post_meta( $item, '_reycob_stl_image_id', true ) : get_post_thumbnail_id( $item );
   $label = $look ? 'ایده چیدمان' : ( has_category( 'comparisons', $item ) || 'product-comparison' === get_post_field( 'post_name', $item ) ? 'مقایسه' : ( has_category( 'guides', $item ) ? 'راهنمای خرید' : 'مجله چیدمون' ) );
   $html .= '<a class="ch-context-card" href="' . esc_url( $url ) . '">' . ( $image_id ? wp_get_attachment_image( $image_id, 'medium_large', false, array( 'loading' => 'lazy' ) ) : '' ) . '<div><span>' . esc_html( $label ) . '</span><h3>' . esc_html( get_the_title( $item ) ) . '</h3><p>' . esc_html( $look ? 'دیدن چیدمان' : 'خواندن مطلب' ) . ' <span aria-hidden="true">←</span></p></div></a>';
  }
  return $html . '</div></section>';
 }

 public static function append_products( string $html, $widget ): string {
  if ( is_admin() || 'theme-post-content' !== $widget->get_name() || ! is_singular( 'post' ) || (int) get_the_ID() !== (int) get_queried_object_id() ) { return $html; }
  $ids = self::references( (int) get_queried_object_id() );
  $cards = '';
  foreach ( $ids as $id ) {
   $product = wc_get_product( $id );
   if ( ! $product || 'publish' !== $product->get_status() || ! $product->is_visible() ) { continue; }
   $cards .= '<a class="ch-context-card" href="' . esc_url( get_permalink( $id ) ) . '">' . $product->get_image( 'woocommerce_thumbnail' ) . '<div><h3>' . esc_html( $product->get_name() ) . '</h3><p>دیدن محصول <span aria-hidden="true">←</span></p></div></a>';
  }
  return $cards ? $html . '<section class="ch-product-context"><h2>محصولات این مطلب</h2><div class="ch-context-grid">' . $cards . '</div></section>' : $html;
 }
}
