<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Core_Commercial {
 public const FRESHNESS_SECONDS = 86400;
 public static function register(): void {
  add_action( 'rest_api_init', static function(): void {
   register_rest_route( 'chidemoon-core/v1', '/source-profile', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => static fn() => array( 'profile' => 'source-v2', 'publicationApproval' => 'editor', 'directProductWrites' => false ) ) );
  } );
  add_filter( 'woocommerce_rest_pre_insert_product_object', array( __CLASS__, 'protect_source_write' ), 100, 3 );
  add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'restore_source_content' ), 120 );
  add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html' ), 100, 2 );
  add_filter( 'woocommerce_product_get_product_url', array( __CLASS__, 'merchant_url' ), 100, 2 );
  add_filter( 'woocommerce_loop_add_to_cart_link', array( __CLASS__, 'loop_button' ), 100, 2 );
  add_filter( 'woocommerce_product_tabs', array( __CLASS__, 'tabs' ), 100 );
  add_filter( 'woocommerce_product_review_comment_form_args', array( __CLASS__, 'review_label' ) );
  add_filter( 'pre_comment_approved', array( __CLASS__, 'moderate_review' ), 100, 2 );
  add_filter( 'option_woocommerce_review_rating_verification_label', static function( $value ) { $product = wc_get_product( get_the_ID() ); return $product instanceof WC_Product && $product->is_type( 'external' ) ? 'no' : $value; } );
  add_filter( 'option_woocommerce_review_rating_verification_required', static function( $value ) { $product = wc_get_product( get_the_ID() ); return $product instanceof WC_Product && $product->is_type( 'external' ) ? 'no' : $value; } );
  add_shortcode( 'chidemoon_source_description', array( __CLASS__, 'source_description' ) );
  add_shortcode( 'chidemoon_offer_observations', array( __CLASS__, 'observations_shortcode' ) );
  add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_summary' ), 25 );
  remove_action( 'woocommerce_external_add_to_cart', 'woocommerce_external_add_to_cart', 30 );
  add_action( 'woocommerce_external_add_to_cart', array( __CLASS__, 'single_button' ), 30 );
 }
 public static function protect_source_write( $product, $request, bool $creating ) {
  if ( is_wp_error( $product ) ) { return $product; }
  $existing = ! $creating && $product->get_id() ? wc_get_product( $product->get_id() ) : null;
  if ( ! $product->is_type( 'external' ) && ! ( $existing && $existing->is_type( 'external' ) ) && ! $product->get_meta( Chidemoon_Core_Source_Importer::META_IDENTITY, true ) ) { return $product; }
  $fields = array( 'name', 'description', 'short_description', 'external_url', 'regular_price', 'sale_price', 'images', 'categories', 'tags', 'attributes', 'dimensions' );
  if ( $creating || array_intersect( $fields, array_keys( $request->get_params() ) ) ) { return new WP_Error( 'chidemoon_source_profile_required', __( 'Import approved source data through the versioned source profile. Editorial fields are reviewed in Chidemoon.', 'chidemoon-core' ), array( 'status' => 409 ) ); }
  foreach ( (array) $request->get_param( 'meta_data' ) as $meta ) {
   $key = $meta['key'] ?? '';
   if ( str_starts_with( $key, '_chidemoon_source' ) || in_array( $key, array( '_chidemoon_affiliate_url', '_chidemoon_merchant_name', '_chidemoon_review_state', '_product_url', '_price', '_sale_price', '_regular_price' ), true ) ) { return new WP_Error( 'chidemoon_source_field_protected', __( 'This source field is protected.', 'chidemoon-core' ), array( 'status' => 403 ) ); }
  }
  return $product;
 }
 public static function restore_source_content( WC_Product $product ): void {
  $content = json_decode( (string) $product->get_meta( '_chidemoon_source_content', true ), true );
  if ( ! is_array( $content ) ) { return; }
  $product->set_name( $content['title'] ); $product->set_product_url( $content['affiliateUrl'] );
  $product->update_meta_data( Chidemoon_Core_Affiliate::META_AFFILIATE_URL, $content['affiliateUrl'] );
  $product->update_meta_data( Chidemoon_Core_Affiliate::META_SOURCE_URL, $content['sourceUrl'] );
  $persisted = wc_get_product( $product->get_id() );
  if ( $persisted instanceof WC_Product ) {
   foreach ( array( Chidemoon_Core_Affiliate::META_MERCHANT_NAME, Chidemoon_Core_Affiliate::META_SOURCE_CHECKED ) as $key ) { $product->update_meta_data( $key, $persisted->get_meta( $key, true ) ); }
   $product->set_regular_price( $persisted->get_regular_price( 'edit' ) );
   $product->set_sale_price( $persisted->get_sale_price( 'edit' ) );
   $product->set_price( $persisted->get_price( 'edit' ) );
   $product->set_date_on_sale_from( $persisted->get_date_on_sale_from( 'edit' ) );
   $product->set_date_on_sale_to( $persisted->get_date_on_sale_to( 'edit' ) );
   $product->set_image_id( $persisted->get_image_id( 'edit' ) );
   $product->set_gallery_image_ids( $persisted->get_gallery_image_ids( 'edit' ) );
  }
  $approved = Chidemoon_Core_Enrichment::read( $product->get_id() );
  if ( isset( $approved['category'] ) ) { $product->set_category_ids( $approved['category']['value'] ?? array() ); }
  if ( isset( $approved['tags'] ) ) { $product->set_tag_ids( $approved['tags']['value'] ?? array() ); }
  $product->set_attributes( Chidemoon_Core_Enrichment::attributes( $product->get_attributes(), $approved ) );
 }
 public static function observations( WC_Product $product ): array { $value = json_decode( (string) $product->get_meta( Chidemoon_Core_Source_Importer::META_OBSERVATIONS, true ), true ); return is_array( $value ) ? $value : array(); }
 public static function is_fresh( ?string $observed_at ): bool { $timestamp = $observed_at ? strtotime( $observed_at ) : false; return false !== $timestamp && $timestamp <= time() + 300 && time() - $timestamp <= self::FRESHNESS_SECONDS; }
 public static function availability( WC_Product $product ): string {
  if ( '1' === (string) $product->get_meta( Chidemoon_Core_Source_Importer::META_WITHDRAWN, true ) ) { return 'withdrawn'; }
  $value = self::observations( $product )['availability'] ?? array();
  if ( 'out_of_stock' === ( $value['status'] ?? '' ) ) { return 'out_of_stock'; }
  return self::is_fresh( $value['observedAt'] ?? null ) && 'in_stock' === ( $value['status'] ?? '' ) ? 'in_stock' : 'unknown';
 }
 public static function availability_label( WC_Product $product ): string {
  $labels = array( 'in_stock' => 'Available at merchant', 'out_of_stock' => 'Unavailable at merchant', 'unknown' => 'Availability unconfirmed', 'withdrawn' => 'Offer withdrawn' );
  $value = self::observations( $product )['availability'] ?? array();
  if ( 'out_of_stock' === ( $value['status'] ?? '' ) && ! self::is_fresh( $value['observedAt'] ?? null ) && 'withdrawn' !== self::availability( $product ) ) { return __( 'Last observed unavailable; current availability unconfirmed', 'chidemoon-core' ); }
  return __( $labels[ self::availability( $product ) ], 'chidemoon-core' );
 }
 public static function price_html( string $html, WC_Product $product ): string {
  if ( ! $product->get_meta( Chidemoon_Core_Source_Importer::META_IDENTITY, true ) ) { return $html; }
  $observations = self::observations( $product );
  if ( ! self::is_fresh( $observations['price']['observedAt'] ?? null ) ) { return '<span class="ch-price-unknown">' . esc_html__( 'Current price unavailable', 'chidemoon-core' ) . '</span>'; }
  return $html;
 }
 public static function merchant_url( string $url, WC_Product $product ): string { return Chidemoon_Core_Affiliate::is_offer_available( $product ) ? Chidemoon_Core_Affiliate::tracking_url( $product->get_id() ) : get_permalink( $product->get_id() ); }
 public static function loop_button( string $html, WC_Product $product ): string { return Chidemoon_Core_Affiliate::is_offer_available( $product ) ? $html : '<span class="ch-offer-unavailable" aria-disabled="true">' . esc_html__( 'Offer unavailable', 'chidemoon-core' ) . '</span>'; }
 public static function single_button(): void { echo Chidemoon_Core_Affiliate::render_affiliate_cta(); }
 public static function source_description( array $attributes = array() ): string {
  $id = absint( $attributes['product_id'] ?? get_the_ID() );
  $product = wc_get_product( $id );
  if ( ! $product instanceof WC_Product || ! Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) { return ''; }
  $description = (string) $product->get_meta( Chidemoon_Core_Source_Importer::META_DESCRIPTION, true );
  return $description ? '<div class="ch-source-description">' . wp_kses_post( $description ) . '</div>' : '';
 }
 public static function tabs( array $tabs ): array {
  $product = wc_get_product( get_the_ID() );
  if ( ! $product instanceof WC_Product ) { return $tabs; }
  if ( $product->get_meta( Chidemoon_Core_Source_Importer::META_DESCRIPTION, true ) ) { $tabs['source_description'] = array( 'title' => __( 'Merchant description', 'chidemoon-core' ), 'priority' => 8, 'callback' => static function(): void { echo self::source_description(); } ); }
  if ( isset( $tabs['reviews'] ) ) { $tabs['reviews']['title'] = __( 'Chidemoon community reviews', 'chidemoon-core' ); }
  return $tabs;
 }
 public static function render_summary(): void { echo self::observations_shortcode(); }
 public static function observations_shortcode( array $attributes = array() ): string {
  $product = wc_get_product( absint( $attributes['product_id'] ?? get_the_ID() ) );
  if ( ! $product instanceof WC_Product || ! Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) || ! $product->get_meta( Chidemoon_Core_Source_Importer::META_IDENTITY, true ) ) { return ''; }
  $observations = self::observations( $product );
  $html = '<div class="ch-offer-observations"><p>' . esc_html( self::availability_label( $product ) );
  if ( isset( $observations['availability']['observedAt'] ) ) { $html .= ' <time datetime="' . esc_attr( $observations['availability']['observedAt'] ) . '">' . esc_html( wp_date( 'Y-m-d H:i', strtotime( $observations['availability']['observedAt'] ) ) ) . '</time>'; }
  $html .= '</p>';
  $price = $observations['price'] ?? array();
  if ( self::is_fresh( $price['observedAt'] ?? null ) && isset( $price['previous'], $price['current'] ) && (float) $price['previous'] > (float) $price['current'] ) { $html .= '<p>' . esc_html( sprintf( __( '%s%% discount', 'chidemoon-core' ), (string) round( ( 1 - (float) $price['current'] / (float) $price['previous'] ) * 100 ) ) ) . '</p>'; }
  if ( isset( $observations['price']['observedAt'] ) ) { $html .= '<p>' . esc_html__( 'Price observed:', 'chidemoon-core' ) . ' <time datetime="' . esc_attr( $observations['price']['observedAt'] ) . '">' . esc_html( wp_date( 'Y-m-d H:i', strtotime( $observations['price']['observedAt'] ) ) ) . '</time></p>'; }
  if ( isset( $observations['rating'] ) ) {
   $rating = $observations['rating'];
   $html .= '<p class="ch-source-rating">' . esc_html__( 'Merchant rating:', 'chidemoon-core' ) . ' ' . esc_html( $rating['value'] . '/' . $rating['scale'] . ' (' . $rating['count'] . ')' ) . ' <a href="' . esc_url( $rating['sourceUrl'] ) . '" target="_blank" rel="nofollow noopener">' . esc_html( $rating['provider'] ) . '</a> <time datetime="' . esc_attr( $rating['observedAt'] ) . '">' . esc_html( wp_date( 'Y-m-d H:i', strtotime( $rating['observedAt'] ) ) ) . '</time></p>';
  }
  return $html . '</div>';
 }
 public static function review_label( array $args ): array { $args['comment_notes_after'] = '<p>' . esc_html__( 'Community reviews are moderated. Purchases take place at the merchant; Chidemoon does not verify purchases.', 'chidemoon-core' ) . '</p>'; return $args; }
 public static function moderate_review( $approved, array $comment ) { return 'product' === get_post_type( (int) ( $comment['comment_post_ID'] ?? 0 ) ) && ! is_wp_error( $approved ) && 'spam' !== $approved && 'trash' !== $approved ? 0 : $approved; }
}
