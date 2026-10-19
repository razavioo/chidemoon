<?php
/** Short-lived local authentication for browser verification; never valid for a public host. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
$host = wp_parse_url( home_url(), PHP_URL_HOST );
if ( ! in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) { WP_CLI::error( 'Local browser verification only.' ); }
$expires = time() + 1800;
$cookies = array();
foreach ( array( LOGGED_IN_COOKIE => 'logged_in', AUTH_COOKIE => 'auth' ) as $name => $scheme ) {
 $cookies[] = array( 'name' => $name, 'value' => wp_generate_auth_cookie( get_current_user_id(), $expires, $scheme ), 'domain' => $host, 'path' => '/', 'expires' => $expires, 'httpOnly' => true, 'secure' => false, 'sameSite' => 'Lax' );
}
WP_CLI::log( wp_json_encode( array( 'cookies' => $cookies, 'origins' => array() ) ) );
