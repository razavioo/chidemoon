<?php
/** Crawlable catalogue routes rendered outside shared Elementor template caches. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Chidemoon_Core_Structural_Links {
 public static function register(): void { add_action( 'template_redirect', array( __CLASS__, 'start' ), 15 ); }
 private static function term_link( $term ): string {
  $url = get_term_link( $term );
  return is_wp_error( $url ) ? '' : '<li><a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a></li>';
 }
 public static function markup(): string {
  $links = ''; $extra = ''; $heading = '';
  if ( is_front_page() ) {
   $heading = 'محصولات را بر اساس دسته پیدا کن';
   $terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0 ) );
   foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) { $links .= self::term_link( $term ); }
   $links .= '<li><a href="' . esc_url( home_url( '/categories/' ) ) . '">همه دسته‌بندی‌ها</a></li><li><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">همه محصولات</a></li>';
  } elseif ( is_product_category() ) {
   $term = get_queried_object(); $heading = 'دسته‌های مرتبط';
   $links = '<li><a href="' . esc_url( home_url( '/categories/' ) ) . '">همه دسته‌بندی‌ها</a></li>';
   if ( $term->parent ) { $links .= self::term_link( get_term( $term->parent, 'product_cat' ) ); }
   $children = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => $term->term_id ) );
   if ( ! is_wp_error( $children ) && ! $children ) { $children = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => $term->parent, 'exclude' => array( $term->term_id ) ) ); }
   foreach ( is_wp_error( $children ) ? array() : $children as $child ) { $links .= self::term_link( $child ); }
  } elseif ( is_product() ) {
   $id = get_queried_object_id(); $heading = 'این محصول در دسته‌بندی‌ها';
   $terms = get_the_terms( $id, 'product_cat' ); $all = array();
   foreach ( is_array( $terms ) ? $terms : array() as $term ) {
    foreach ( array_reverse( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) ) as $ancestor ) { $all[$ancestor] = get_term( $ancestor, 'product_cat' ); }
    $all[$term->term_id] = $term;
   }
   foreach ( $all as $term ) { $links .= self::term_link( $term ); }
   $links .= '<li><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">همه محصولات</a></li>';
   $ids = wp_list_pluck( is_array( $terms ) ? $terms : array(), 'term_id' );
   if ( $ids ) {
    $products = wc_get_products( array( 'status' => 'publish', 'visibility' => 'visible', 'exclude' => array( $id ), 'limit' => 6, 'category' => wp_list_pluck( $terms, 'slug' ), 'orderby' => 'date', 'order' => 'DESC' ) );
    foreach ( $products as $product ) { $extra .= '<li><a href="' . esc_url( get_permalink( $product->get_id() ) ) . '">' . esc_html( $product->get_name() ) . '</a></li>'; }
   }
  } else { return ''; }
  if ( ! $links ) { return ''; }
  return '<section class="ch-structural-links" aria-label="مسیرهای مرتبط محصولات"><h2>' . esc_html( $heading ) . '</h2><ul>' . $links . '</ul>' . ( $extra ? '<h3>محصولات دیگر در همین دسته</h3><ul>' . $extra . '</ul>' : '' ) . '</section>';
 }
 public static function start(): void {
  if ( is_admin() || is_feed() || is_404() || ! ( is_front_page() || is_product() || is_product_category() ) ) { return; }
  $markup = self::markup();
  if ( ! $markup ) { return; }
  ob_start( static fn( string $html ): string => str_contains( $html, 'class="ch-structural-links"' ) ? $html : ( preg_replace_callback( '/<\/main>/', static fn() => $markup . '</main>', $html, 1 ) ?? $html ) );
 }
}
