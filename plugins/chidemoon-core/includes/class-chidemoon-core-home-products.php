<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Core_Home_Products {
 private static ?string $mode = null;
 public static function register(): void {
  // Elementor's product skin hides custom query IDs; scope its Woo query
  // to this widget instead of altering other catalog or card queries.
  add_action( 'elementor/frontend/widget/before_render', static function( $widget ) {
   if ( 'loop-grid' !== $widget->get_name() ) { return; }
   $settings = $widget->get_data( 'settings' );
   $id = $settings['post_query_query_id'] ?? '';
   if ( 'chidemoon_home_trending' === $id ) { self::$mode = 'trending'; }
   if ( 'chidemoon_home_popular' === $id ) { self::$mode = 'popular'; }
  } );
  add_action( 'elementor/frontend/widget/after_render', static function( $widget ) {
   if ( 'loop-grid' === $widget->get_name() ) { self::$mode = null; }
  } );
  add_action( 'pre_get_posts', static function( WP_Query $query ) {
   if ( null !== self::$mode && in_array( 'product', (array) $query->get( 'post_type' ), true ) && ! $query->is_main_query() ) { self::configure( $query, self::$mode ); }
  }, 99 );
  add_action( 'elementor/query/chidemoon_home_trending', static function( WP_Query $query ) { self::configure( $query, 'trending' ); } );
  add_action( 'elementor/query/chidemoon_home_popular', static function( WP_Query $query ) { self::configure( $query, 'popular' ); } );
 }
 public static function ids( string $mode ): array {
  $cache_key = 'ch_home_products_' . $mode . '_v1';
  $cached = get_transient( $cache_key );
  if ( is_array( $cached ) ) { return $cached; }
  global $wpdb;
  $days = 'trending' === $mode ? 7 : 30;
  $since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
  $ranked = $wpdb->get_col( $wpdb->prepare(
   "SELECT c.product_id FROM {$wpdb->prefix}chidemoon_clicks c INNER JOIN {$wpdb->posts} p ON p.ID=c.product_id WHERE c.clicked_at >= %s AND p.post_type='product' AND p.post_status='publish' GROUP BY c.product_id ORDER BY COUNT(DISTINCT COALESCE(c.visitor_hash, CONCAT('click-',c.id))) DESC, MAX(c.clicked_at) DESC, c.product_id DESC LIMIT 200", $since
  ) );
  $excluded = 'popular' === $mode ? self::ids( 'trending' ) : array();
  $ids = array();
  foreach ( $ranked as $id ) {
   $id = (int) $id;
   if ( in_array( $id, $excluded, true ) ) { continue; }
   $product = wc_get_product( $id );
   if ( $product && Chidemoon_Core_Affiliate::is_offer_available( $product ) ) { $ids[] = $id; }
   if ( count( $ids ) >= 8 ) { break; }
  }
  // Fill a sparse trend feed with the latest available catalog entries.
  if ( 'trending' === $mode && count( $ids ) < 8 ) {
   foreach ( get_posts( array( 'post_type'=>'product', 'post_status'=>'publish', 'posts_per_page'=>100, 'fields'=>'ids', 'orderby'=>'date', 'order'=>'DESC', 'post__not_in'=>$ids, 'chidemoon_home_fallback'=>true ) ) as $id ) {
    $product = wc_get_product( $id );
    if ( $product && Chidemoon_Core_Affiliate::is_offer_available( $product ) ) { $ids[] = (int) $id; }
    if ( count( $ids ) >= 8 ) { break; }
   }
  }
  set_transient( $cache_key, $ids, 15 * MINUTE_IN_SECONDS );
  return $ids;
 }
 private static function configure( WP_Query $query, string $mode ): void {
  if ( $query->get( 'chidemoon_home_fallback' ) ) { return; }
  $query->set( 'post_type', 'product' );
  $query->set( 'post_status', 'publish' );
  $query->set( 'post__in', self::ids( $mode ) ?: array( 0 ) );
  $query->set( 'post__not_in', array() );
  $query->set( 'orderby', 'post__in' );
  $query->set( 'posts_per_page', 8 );
  $query->set( 'ignore_sticky_posts', true );
 }
}
