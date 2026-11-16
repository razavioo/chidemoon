<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Core_Look_Views {
 public static function register(): void {
  add_action( 'init', static function() {
   if ( get_option( 'chidemoon_look_views_schema' ) === '1' ) { return; }
   global $wpdb;
   require_once ABSPATH . 'wp-admin/includes/upgrade.php';
   dbDelta( "CREATE TABLE {$wpdb->prefix}ch_look_views (
    look_id bigint(20) unsigned NOT NULL,
    visitor char(64) NOT NULL,
    viewed_day date NOT NULL,
    PRIMARY KEY  (look_id,visitor,viewed_day)
   ) {$wpdb->get_charset_collate()};" );
   if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'ch_look_views' ) ) ) { update_option( 'chidemoon_look_views_schema', '1', false ); }
  } );
  add_action( 'rest_api_init', static function() {
   register_rest_route( 'chidemoon-core/v1', '/looks/views', array(
    'methods' => 'POST', 'permission_callback' => '__return_true',
    'callback' => array( __CLASS__, 'record' ),
   ) );
   register_rest_route( 'chidemoon-core/v1', '/looks/views', array(
    'methods' => 'GET', 'permission_callback' => '__return_true',
    'callback' => array( __CLASS__, 'counts' ),
   ) );
  } );
  add_action( 'wp_enqueue_scripts', static function() {
   $file = CHIDEMOON_CORE_DIR . 'assets/js/look-views.js';
   wp_enqueue_script( 'chidemoon-look-views', CHIDEMOON_CORE_URL . 'assets/js/look-views.js', array(), filemtime( $file ), true );
   wp_add_inline_script( 'chidemoon-look-views', 'window.chLookViewsEndpoint=' . wp_json_encode( rest_url( 'chidemoon-core/v1/looks/views' ) ) . ';', 'before' );
  } );
 }
 private static function valid( int $id ): bool {
  return 'publish' === get_post_status( $id ) && in_array( get_post_type( $id ), array( 'reycob_look', 'chidemoon_look' ), true );
 }
 public static function counts( WP_REST_Request $request ) {
  global $wpdb;
  $ids = array_slice( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $request->get_param( 'ids' ) ) ), array( __CLASS__, 'valid' ) ) ), 0, 40 );
  $counts = array_fill_keys( $ids, 0 );
  if ( $ids ) {
   $rows = $wpdb->get_results( "SELECT look_id, COUNT(*) AS views FROM {$wpdb->prefix}ch_look_views WHERE look_id IN (" . implode( ',', $ids ) . ') GROUP BY look_id' );
   foreach ( $rows as $row ) { $counts[ (int) $row->look_id ] = (int) $row->views; }
  }
  $response = new WP_REST_Response( array( 'base' => 100, 'counts' => $counts ) );
  $response->header( 'Cache-Control', 'no-store' );
  return $response;
 }
 public static function record( WP_REST_Request $request ) {
  global $wpdb;
  $id = absint( $request->get_param( 'id' ) );
  $token = (string) $request->get_param( 'visitor' );
  if ( ! self::valid( $id ) || ! preg_match( '/^[a-zA-Z0-9-]{16,80}$/D', $token ) ) { return new WP_Error( 'invalid_view', 'Invalid view.', array( 'status' => 400 ) ); }
  $origin = $request->get_header( 'origin' );
  if ( $origin && wp_parse_url( $origin, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) { return new WP_Error( 'invalid_origin', 'Invalid origin.', array( 'status' => 403 ) ); }
  $visitor = hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
  $result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->prefix}ch_look_views (look_id,visitor,viewed_day) VALUES (%d,%s,%s)", $id, $visitor, current_time( 'Y-m-d' ) ) );
  if ( false === $result ) { return new WP_Error( 'view_write_failed', 'Could not record view.', array( 'status' => 500 ) ); }
  $query = new WP_REST_Request( 'GET' ); $query->set_param( 'ids', (string) $id );
  return self::counts( $query );
 }
}
