<?php
/** Shared editorial breadcrumbs; WooCommerce keeps its native trail and schema. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Core_Breadcrumbs {
 public static function register(): void {
  // Run outside Elementor's shared template cache: each request needs its own trail.
  add_action( 'template_redirect', array( __CLASS__, 'start' ) );
 }

 public static function start(): void {
  $trail = self::trail();
  if ( count( $trail ) < 2 ) { return; }
  ob_start( static function ( string $html ) use ( $trail ): string {
   if ( str_contains( $html, 'class="ch-breadcrumb-shell"' ) ) { return $html; }
   $markup = self::render( $trail );
   return preg_replace_callback( '/<main\b[^>]*>/', static fn( $match ) => $match[0] . $markup, $html, 1 ) ?? $html;
  } );
 }

 /** @return array<int,array{0:string,1:string}> */
 public static function trail(): array {
  if ( is_admin() || is_front_page() || is_404() || is_feed() || is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) { return array(); }
  $trail = array( array( 'خانه', home_url( '/' ) ) );
  if ( is_singular( 'post' ) ) {
   $id = get_queried_object_id();
   if ( has_category( 'guides', $id ) ) { $trail[] = array( 'راهنمای خرید', home_url( '/guides/' ) ); }
   elseif ( has_category( 'comparisons', $id ) ) { $trail[] = array( 'مقایسه‌ها', home_url( '/comparisons/' ) ); }
   elseif ( get_post_meta( $id, '_chidemoon_native_look', true ) ) { $trail[] = array( 'چیدمان‌ها', home_url( '/shop-the-look/' ) ); }
   $trail[] = array( get_the_title( $id ), get_permalink( $id ) );
  } elseif ( is_singular( array( 'chidemoon_look', 'reycob_look' ) ) ) {
   $trail[] = array( 'چیدمان‌ها', home_url( '/shop-the-look/' ) );
   $trail[] = array( get_the_title( get_queried_object_id() ), get_permalink( get_queried_object_id() ) );
  } elseif ( is_page() ) {
   $id = get_queried_object_id();
   foreach ( array_reverse( get_post_ancestors( $id ) ) as $parent ) {
    if ( 'publish' === get_post_status( $parent ) ) { $trail[] = array( get_the_title( $parent ), get_permalink( $parent ) ); }
   }
   $trail[] = array( get_the_title( $id ), get_permalink( $id ) );
  } elseif ( is_category() || is_tag() || is_tax() ) {
   $term = get_queried_object();
   foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $parent ) {
    $ancestor = get_term( $parent, $term->taxonomy );
    $url = get_term_link( $ancestor );
    if ( ! is_wp_error( $url ) ) { $trail[] = array( $ancestor->name, $url ); }
   }
   $url = get_term_link( $term );
   if ( is_wp_error( $url ) ) { return array(); }
   $trail[] = array( $term->name, $url );
  } elseif ( is_search() ) {
   $trail[] = array( 'نتایج جست‌وجو برای «' . get_search_query() . '»', get_search_link() );
  } else { return array(); }
  $page = max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
  if ( $page > 1 ) { $trail[] = array( 'صفحه ' . number_format_i18n( $page ), get_pagenum_link( $page ) ); }
  return $trail;
 }

 private static function render( array $trail ): string {
  $schema = array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array() );
  $html = '<div class="ch-breadcrumb-shell"><nav class="ch-breadcrumb" aria-label="مسیر راهنما"><ol>';
  foreach ( $trail as $index => [$name, $url] ) {
   $name = wp_strip_all_tags( $name );
   $schema['itemListElement'][] = array( '@type' => 'ListItem', 'position' => $index + 1, 'name' => $name, 'item' => $url );
   $html .= '<li>';
   if ( $index === count( $trail ) - 1 ) { $html .= '<span aria-current="page">' . esc_html( $name ) . '</span>'; }
   else { $html .= '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>'; }
   $html .= '</li>';
  }
  return $html . '</ol></nav></div><script type="application/ld+json">' . wp_json_encode( $schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE ) . '</script>';
 }
}
