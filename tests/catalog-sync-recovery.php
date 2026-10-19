<?php
declare(strict_types=1);
define( 'ABSPATH', __DIR__ );
define( 'CHIDEMOON_CATALOG_CLIENT_SECRET', 'isolated-test-secret' );
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { if ( ( $GLOBALS['fail_option'] ?? '' ) === $key ) { return false; } $GLOBALS['options'][ $key ] = $value; return true; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_salt( $scheme ) { return 'isolated-' . $scheme; }
function wp_parse_url( $value ) { return parse_url( $value ); }
function wp_http_validate_url( $value ) { return filter_var( $value, FILTER_VALIDATE_URL ); }
function wp_next_scheduled( $hook, $args = array() ) { return $GLOBALS['scheduled'][ json_encode( $args ) ] ?? false; }
function wp_schedule_single_event( $time, $hook, $args ) { $GLOBALS['scheduled'][ json_encode( $args ) ] = $time; }
class WP_Error {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code( $value ) { return $value['response']['code']; }
function wp_remote_retrieve_body( $value ) { return $value['body']; }
function wp_safe_remote_request( $url, $args ) {
 $path = parse_url( $url, PHP_URL_PATH );
 $GLOBALS['calls'][] = array( $path, $args );
 check_sync( true === $args['sslverify'] && 0 === $args['redirection'], 'HTTP credential boundary changed' );
 if ( isset( $GLOBALS['http'] ) ) { return ( $GLOBALS['http'] )( $path, $args ); }
 return response_sync( str_ends_with( $path, 'source-receipts' ) ? array( 'imported' => true, 'publicationConfirmed' => false ) : $GLOBALS['snapshot'] );
}
class SyncDatabase {
 public string $prefix = 'wp_';
 public int $released = 0;
 public function prepare( $sql, ...$args ) { return $sql; }
 public function get_var( $sql ) { if ( str_contains( $sql, 'RELEASE_LOCK' ) ) { $this->released++; } return $GLOBALS['lock'] ? '1' : '0'; }
}
class Chidemoon_Core_Source_Importer {
 public static int $imports = 0;
 public static function import_snapshot( $snapshot, $dry, $slug ) { self::$imports++; return $GLOBALS['report']; }
}
require __DIR__ . '/../plugins/chidemoon-catalog-sync/includes/class-chidemoon-catalog-sync.php';
function check_sync( bool $value, string $message ): void { if ( ! $value ) { throw new RuntimeException( $message ); } }
function response_sync( $body, int $code = 200 ): array { return array( 'response' => array( 'code' => $code ), 'body' => is_string( $body ) ? $body : json_encode( $body ) ); }
function invoke_sync( string $name, ...$args ) { return ( new ReflectionMethod( Chidemoon_Catalog_Sync::class, $name ) )->invoke( null, ...$args ); }
function state_sync(): array { return get_option( 'chidemoon_catalog_status', array() ); }
function due_sync(): void { $s = state_sync(); $s['nextAttemptAt'] = time() - 1; update_option( 'chidemoon_catalog_status', $s ); }
function reset_sync(): void {
 $GLOBALS['options'] = array( 'chidemoon_catalog_settings' => array( 'origin' => 'https://example.com', 'clientId' => 'test-client', 'organizationId' => 'test-org', 'organizationSlug' => 'chidemoon', 'enabled' => true ) );
 $GLOBALS['calls'] = $GLOBALS['scheduled'] = array(); $GLOBALS['lock'] = true; unset( $GLOBALS['http'], $GLOBALS['fail_option'] );
 $GLOBALS['wpdb'] = new SyncDatabase(); Chidemoon_Core_Source_Importer::$imports = 0;
 $GLOBALS['snapshot'] = array( 'organization' => array( 'id' => 'test-org', 'slug' => 'chidemoon' ), 'snapshot' => array( 'id' => 'snapshot', 'revision' => str_repeat( 'a', 64 ) ) );
 $GLOBALS['report'] = array( 'failed' => 0, 'records' => array( array( 'sourceKey' => 'test-source', 'revision' => 1, 'status' => 'imported', 'localProductId' => '706', 'issues' => array() ) ) );
 invoke_sync( 'store_tokens', array( 'access_token' => 'test-access', 'refresh_token' => 'test-refresh', 'token_type' => 'Bearer', 'expires_in' => 3600, 'scope' => 'catalog:source:read catalog:source:ack' ) );
}
function case_sync( string $name, callable $run ): void { reset_sync(); $run(); echo "PASS: $name\n"; $GLOBALS['passed'] = ( $GLOBALS['passed'] ?? 0 ) + 1; }
function receipt_sync(): array { return array( 'snapshotId' => 'snapshot', 'snapshotRevision' => str_repeat( 'a', 64 ), 'records' => $GLOBALS['report']['records'] ); }

case_sync( 'successful source import acknowledges without claiming public publication', function() {
 Chidemoon_Catalog_Sync::poll();
 check_sync( ! empty( state_sync()['lastSuccessAt'] ) && false === state_sync()['blocked'], 'No confirmed completion' );
 check_sync( array() === get_option( 'chidemoon_catalog_outbox' ), 'Receipt not drained' );
 check_sync( 1 === $GLOBALS['wpdb']->released, 'Poll lock not released' );
 check_sync( ! str_contains( get_option( 'chidemoon_catalog_tokens' ), 'test-access' ), 'Plaintext token persisted' );
} );
case_sync( 'a product needing review does not block healthy products or the next poll', function() {
 $GLOBALS['report']['failed'] = 1;
 $GLOBALS['report']['records'][] = array( 'sourceKey' => 'broken', 'revision' => 1, 'status' => 'failed', 'issues' => array( 'identity_collision' ) );
 Chidemoon_Catalog_Sync::poll();
 check_sync( false === state_sync()['blocked'] && 'records_require_review' === state_sync()['error'], 'Product failure blocked connection' );
 check_sync( empty( state_sync()['lastSuccessAt'] ) && array() === get_option( 'chidemoon_catalog_outbox' ), 'Failure hidden or receipt not delivered' );
 check_sync( 2 === count( json_decode( $GLOBALS['calls'][1][1]['body'], true )['records'] ), 'Healthy receipt was lost' );
 due_sync(); Chidemoon_Catalog_Sync::poll( 'retry' );
 check_sync( 2 === Chidemoon_Core_Source_Importer::$imports, 'New snapshot was not processed' );
} );
case_sync( 'transient outages remain recoverable beyond six attempts with capped backoff', function() {
 $GLOBALS['http'] = fn() => response_sync( array(), 503 );
 for ( $i = 0; $i < 20; $i++ ) { due_sync(); Chidemoon_Catalog_Sync::poll(); }
 check_sync( false === state_sync()['blocked'] && 20 === state_sync()['attempts'], 'Transient outage permanently blocked connection' );
 check_sync( state_sync()['nextAttemptAt'] <= time() + 3600, 'Retry delay is unbounded' );
 unset( $GLOBALS['http'] ); due_sync(); Chidemoon_Catalog_Sync::poll();
 check_sync( 0 === state_sync()['attempts'] && ! isset( state_sync()['error'], state_sync()['nextAttemptAt'] ), 'Automatic recovery failed' );
} );
foreach ( array( 'upstream_rejected', 'records_require_review', 'records_require_retry', 'upstream_unavailable' ) as $error ) {
 case_sync( "legacy blocked state recovers automatically: $error", function() use ( $error ) {
  update_option( 'chidemoon_catalog_status', array( 'blocked' => true, 'error' => $error, 'attempts' => 7 ) );
  update_option( 'chidemoon_catalog_outbox', array( receipt_sync() ) );
  Chidemoon_Catalog_Sync::poll();
  check_sync( array( '/api/integrations/catalog/v2/source-receipts', '/api/integrations/catalog/v2/source-snapshot', '/api/integrations/catalog/v2/source-receipts' ) === array_column( $GLOBALS['calls'], 0 ), 'Pending receipt ordering violated' );
  check_sync( false === state_sync()['blocked'] && ! empty( state_sync()['lastSuccessAt'] ), 'Legacy blocked state did not recover' );
 } );
}
foreach ( array( 400, 409, 429, 500, 503, 0 ) as $code ) {
 case_sync( "failed acknowledgement is durable and retryable: HTTP $code", function() use ( $code ) {
  update_option( 'chidemoon_catalog_outbox', array( receipt_sync() ) );
  $GLOBALS['http'] = fn() => $code ? response_sync( array(), $code ) : new WP_Error();
  Chidemoon_Catalog_Sync::poll();
  check_sync( 1 === count( get_option( 'chidemoon_catalog_outbox' ) ) && 0 === Chidemoon_Core_Source_Importer::$imports, 'Pending receipt lost or next import ran prematurely' );
  check_sync( false === state_sync()['blocked'] && empty( state_sync()['lastSuccessAt'] ), 'Retryable failure reported permanent or successful' );
  check_sync( 1 === $GLOBALS['wpdb']->released, 'Failure leaked poll lock' );
 } );
}
foreach ( array( 401, 403 ) as $code ) {
 case_sync( "revoked credentials require authorization: HTTP $code", function() use ( $code ) {
  $GLOBALS['http'] = fn() => response_sync( array(), $code ); Chidemoon_Catalog_Sync::poll();
  check_sync( true === state_sync()['blocked'] && 'authorization_required' === state_sync()['error'], 'Revoked authorization retried' );
  Chidemoon_Catalog_Sync::poll(); check_sync( 1 === count( $GLOBALS['calls'] ), 'Blocked credential used again' );
 } );
}
case_sync( 'backoff prevents premature retry', function() {
 $GLOBALS['http'] = fn() => response_sync( array(), 503 ); Chidemoon_Catalog_Sync::poll(); Chidemoon_Catalog_Sync::poll( 'retry' );
 check_sync( 1 === count( $GLOBALS['calls'] ), 'Backoff ignored' );
} );
case_sync( 'disabled automatic sync also disables scheduled retries', function() {
 $c = get_option( 'chidemoon_catalog_settings' ); $c['enabled'] = false; update_option( 'chidemoon_catalog_settings', $c );
 Chidemoon_Catalog_Sync::poll(); Chidemoon_Catalog_Sync::poll( 'retry' ); check_sync( array() === $GLOBALS['calls'], 'Disabled connection still syncing' );
 Chidemoon_Catalog_Sync::poll( 'manual' ); check_sync( ! empty( state_sync()['lastSuccessAt'] ), 'Deliberate manual import blocked' );
} );
foreach ( array( 'id', 'slug' ) as $field ) {
 case_sync( "foreign organization $field never reaches importer", function() use ( $field ) {
  $GLOBALS['snapshot']['organization'][ $field ] = 'foreign'; Chidemoon_Catalog_Sync::poll();
  check_sync( 0 === Chidemoon_Core_Source_Importer::$imports && true === state_sync()['blocked'], 'Tenant boundary violated' );
 } );
}
case_sync( 'concurrent poll cannot import or rotate credentials', function() {
 $GLOBALS['lock'] = false; Chidemoon_Catalog_Sync::poll(); check_sync( array() === $GLOBALS['calls'] && 0 === Chidemoon_Core_Source_Importer::$imports, 'Concurrent work ran' );
} );
case_sync( 'an empty snapshot completes without sending an invalid empty receipt', function() {
 $GLOBALS['report'] = array( 'failed' => 0, 'records' => array() ); Chidemoon_Catalog_Sync::poll();
 check_sync( 1 === count( $GLOBALS['calls'] ) && ! empty( state_sync()['lastSuccessAt'] ), 'Empty snapshot did not finish' );
} );
foreach ( array( array( 'imported' => false, 'publicationConfirmed' => false ), array( 'imported' => true, 'publicationConfirmed' => true ) ) as $body ) {
 case_sync( 'unconfirmed or publication-confused acknowledgements retain receipts', function() use ( $body ) {
  update_option( 'chidemoon_catalog_outbox', array( receipt_sync() ) ); $GLOBALS['http'] = fn() => response_sync( $body ); Chidemoon_Catalog_Sync::poll();
  check_sync( 1 === count( get_option( 'chidemoon_catalog_outbox' ) ) && 'receipt_not_confirmed' === state_sync()['error'], 'Invalid confirmation discarded receipt' );
 } );
}
case_sync( 'receipt batches drain incrementally after partial network failure', function() {
 $r = $GLOBALS['report']['records'][0]; $GLOBALS['report']['records'] = array();
 for ( $index = 0; $index < 1001; $index++ ) { $GLOBALS['report']['records'][] = array_merge( $r, array( 'sourceKey' => 'src:v2:' . hash( 'sha256', (string) $index ), 'localProductId' => (string) ( 1000 + $index ) ) ); }
 $acks = 0; $GLOBALS['http'] = function( $path, $args ) use ( &$acks ) {
  if ( str_ends_with( $path, 'source-snapshot' ) ) { return response_sync( $GLOBALS['snapshot'] ); }
  $acks++; return response_sync( array( 'imported' => true, 'publicationConfirmed' => false ), 2 === $acks ? 503 : 200 );
 }; Chidemoon_Catalog_Sync::poll();
 check_sync( 1 === count( get_option( 'chidemoon_catalog_outbox' ) ) && 1 === count( get_option( 'chidemoon_catalog_outbox' )[0]['records'] ), 'Delivered batch repeated or undelivered batch lost' );
} );
case_sync( 'receipt persistence failure is visible and does not claim completion', function() {
 $GLOBALS['fail_option'] = 'chidemoon_catalog_outbox'; Chidemoon_Catalog_Sync::poll();
 check_sync( 'receipts_persistence_failed' === state_sync()['error'] && empty( state_sync()['lastSuccessAt'] ) && 1 === count( $GLOBALS['calls'] ), 'Receipt persistence failure hidden' );
} );
case_sync( 'acknowledged receipt remains replayable if removal cannot be persisted', function() {
 update_option( 'chidemoon_catalog_outbox', array( receipt_sync() ) ); $GLOBALS['fail_option'] = 'chidemoon_catalog_outbox'; Chidemoon_Catalog_Sync::poll();
 check_sync( 1 === count( get_option( 'chidemoon_catalog_outbox' ) ) && 0 === Chidemoon_Core_Source_Importer::$imports, 'Receipt removed despite storage failure' );
} );
foreach ( array( 'media_failed', 'persistence_failed' ) as $issue ) {
 case_sync( "per-product $issue retries without stopping connection", function() use ( $issue ) {
  $GLOBALS['report']['failed'] = 1; $GLOBALS['report']['records'][0]['status'] = 'partial'; $GLOBALS['report']['records'][0]['issues'] = array( $issue );
  Chidemoon_Catalog_Sync::poll(); check_sync( false === state_sync()['blocked'] && 'records_require_retry' === state_sync()['error'], 'Retryable product issue blocked connection' );
 } );
}
case_sync( 'expired tokens rotate once and persist both replacements encrypted', function() {
 $t = invoke_sync( 'unseal', get_option( 'chidemoon_catalog_tokens' ) ); $t['expiresAt'] = time() - 1; invoke_sync( 'persist_tokens', $t );
 $GLOBALS['http'] = function( $path, $args ) {
  if ( '/api/oauth2/token' === $path ) { return response_sync( array( 'access_token' => 'rotated-access', 'refresh_token' => 'rotated-refresh', 'token_type' => 'Bearer', 'expires_in' => 3600, 'scope' => 'catalog:source:read catalog:source:ack' ) ); }
  check_sync( 'Bearer rotated-access' === $args['headers']['Authorization'], 'Old access token reused' );
  return response_sync( str_ends_with( $path, 'source-receipts' ) ? array( 'imported' => true, 'publicationConfirmed' => false ) : $GLOBALS['snapshot'] );
 }; Chidemoon_Catalog_Sync::poll();
 $t = invoke_sync( 'unseal', get_option( 'chidemoon_catalog_tokens' ) );
 check_sync( 'rotated-refresh' === $t['refresh_token'] && empty( $t['refreshPending'] ) && ! empty( state_sync()['lastSuccessAt'] ), 'Rotation not persisted' );
} );
case_sync( 'ambiguous token rotation is never blindly replayed', function() {
 $t = invoke_sync( 'unseal', get_option( 'chidemoon_catalog_tokens' ) ); $t['expiresAt'] = time() - 1; invoke_sync( 'persist_tokens', $t );
 $GLOBALS['http'] = fn() => new WP_Error(); Chidemoon_Catalog_Sync::poll();
 check_sync( true === state_sync()['blocked'] && 'refresh_outcome_unknown' === state_sync()['error'], 'Ambiguous rotation was treated as safely repeatable' );
 Chidemoon_Catalog_Sync::poll( 'retry' ); check_sync( 1 === count( $GLOBALS['calls'] ), 'Refresh token replayed after ambiguity' );
} );
case_sync( 'credentials must be durably marked before rotating a refresh token', function() {
 $t = invoke_sync( 'unseal', get_option( 'chidemoon_catalog_tokens' ) ); $t['expiresAt'] = time() - 1; invoke_sync( 'persist_tokens', $t );
 $GLOBALS['fail_option'] = 'chidemoon_catalog_tokens'; Chidemoon_Catalog_Sync::poll();
 check_sync( array() === $GLOBALS['calls'] && 'credentials_persistence_failed' === state_sync()['error'], 'Rotation proceeded without durable recovery marker' );
} );
echo 'Passed ' . $GLOBALS['passed'] . " sync recovery scenarios.\n";
