<?php
/** Standalone regression checks: click persistence, DNT, report bounds and access. */
declare(strict_types=1);
require __DIR__ . '/affiliate-cta.php';
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );
function sanitize_text_field( string $s ): string { return strip_tags( $s ); }
function wp_get_referer(): string { return 'https://chidemoon.example/product?private=value'; }
function wp_salt( string $s ): string { return 'fixture-salt'; }
function current_time( string $format, bool $gmt ): string { return gmdate( 'Y-m-d H:i:s' ); }
function current_user_can( string $cap ): bool { return false; }
function esc_html__( string $s, string $domain ): string { return $s; }
function wp_die( string $s ): never { throw new RuntimeException( 'denied' ); }
$wpdb = new class {
 public string $prefix = 'fixture_';
 public array $inserted = array();
 public array $queries = array();
 public bool $fail = false;
 public function insert( $table, $data, $formats ) { $this->inserted[] = compact( 'table', 'data', 'formats' ); return 1; }
 public function prepare( $sql, ...$args ) { $this->queries[] = compact( 'sql', 'args' ); return $sql; }
 public function get_var( $sql ) { return $this->fail ? null : '125'; }
 public function get_results( $sql, $format ) { return array( array( 'product_id' => 10, 'merchant_host' => 'seller.example', 'clicks' => 125, 'last_click' => '2026-11-12 00:00:00' ) ); }
};
$log = new ReflectionMethod( Chidemoon_Core_Affiliate::class, 'log_click' );
$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
$log->invoke( null, 10, 'https://seller.example/item?token=private' );
$event = $wpdb->inserted[0]['data'];
check( 10 === $event['product_id'] && 'seller.example' === $event['merchant_host'], 'Merchant click identity missing.' );
check( 'chidemoon.example' === $event['referrer_host'], 'Referrer must contain host only.' );
check( 64 === strlen( $event['visitor_hash'] ) && ! str_contains( json_encode( $event ), '203.0.113.5' ), 'Raw IP leaked.' );
check( ! str_contains( json_encode( $event ), 'private' ), 'Destination/referrer query leaked.' );
$_SERVER['HTTP_DNT'] = '1';
$log->invoke( null, 10, 'https://seller.example/item' );
check( 1 === count( $wpdb->inserted ), 'DNT must suppress persistence.' );
require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-admin.php';
$report = Chidemoon_Core_Admin::click_report( 36500 );
check( 30 === $report['days'] && 125 === $report['total'], 'Invalid range must fall back to 30 days.' );
check( 2 === count( $wpdb->queries[0]['args'] ), 'Both UTC bounds must be parameterized.' );
check( str_contains( $wpdb->queries[1]['sql'], 'LIMIT 100' ), 'Report must bound grouped results.' );
check( str_contains( $wpdb->queries[1]['sql'], 'GROUP BY product_id, merchant_host' ), 'Merchant changes must remain distinguishable.' );
$wpdb->fail = true;
check( Chidemoon_Core_Admin::click_report()['error'], 'Database error must not masquerade as empty data.' );
try { Chidemoon_Core_Admin::render_click_report(); throw new RuntimeException( 'access granted' ); }
catch ( RuntimeException $e ) { check( 'denied' === $e->getMessage(), 'Unauthorized report must be denied.' ); }
echo "Affiliate click persistence and reporting checks passed.\n";
