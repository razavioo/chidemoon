<?php
if ( ! defined( 'DB_NAME' ) || 'catalog_test' !== DB_NAME || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Isolated catalog database required' ); }
function sync_check( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$fixture = get_option( 'chidemoon_catalog_fixture' );
$id = (int) $fixture['productId'];
$product = wc_get_product( $id );
$identity = json_decode( $product->get_meta( Chidemoon_Core_Source_Importer::META_IDENTITY, true ), true );
$record = array(
 'sourceKey' => Chidemoon_Core_Source_Contract::source_key( $identity ), 'identity' => $identity, 'aliases' => array(),
 'revision' => (int) $product->get_meta( Chidemoon_Core_Source_Importer::META_REVISION, true ) + 1,
 'contentRevision' => (int) $product->get_meta( Chidemoon_Core_Source_Importer::META_CONTENT_REVISION, true ),
 'content' => json_decode( $product->get_meta( '_chidemoon_source_content', true ), true ),
 'observations' => array( 'price' => array( 'current' => '1200000', 'previous' => null, 'currency' => 'IRR', 'observedAt' => gmdate( 'c' ) ), 'availability' => array( 'status' => 'in_stock', 'observedAt' => gmdate( 'c' ) ) ), 'removals' => array(),
);
$snapshot = array( 'version' => 'v2', 'organization' => array( 'id' => 'fixture-org', 'slug' => 'chidemoon' ), 'snapshot' => array( 'id' => wp_generate_uuid4(), 'revision' => str_repeat( 'a', 64 ), 'generatedAt' => gmdate( 'c' ) ), 'items' => array( $record ), 'withdrawals' => array() );
update_option( 'chidemoon_catalog_settings', array( 'origin' => 'https://example.com', 'clientId' => 'fixture-client', 'organizationId' => 'fixture-org', 'organizationSlug' => 'chidemoon', 'enabled' => true ), false );
delete_option( 'chidemoon_catalog_outbox' ); delete_option( 'chidemoon_catalog_status' );
$method = new ReflectionMethod( Chidemoon_Catalog_Sync::class, 'store_tokens' );
$method->invoke( null, array( 'access_token' => 'isolated-fixture-access', 'refresh_token' => 'isolated-fixture-refresh', 'token_type' => 'Bearer', 'expires_in' => 3600, 'scope' => 'catalog:source:read catalog:source:ack' ) );
sync_check( ! str_contains( get_option( 'chidemoon_catalog_tokens' ), 'isolated-fixture' ), 'Tokens persisted unencrypted' );
$calls = array(); $fail_ack = true; $wrong_tenant = false;
add_filter( 'pre_http_request', static function( $response, $args, $url ) use ( &$calls, &$fail_ack, &$wrong_tenant, &$snapshot ) {
 if ( ! str_starts_with( $url, 'https://example.com/api/integrations/catalog/v2/' ) ) { throw new RuntimeException( 'Unexpected HTTP request' ); }
 sync_check( 'Bearer isolated-fixture-access' === $args['headers']['Authorization'], 'Scoped bearer credential missing' );
 sync_check( 0 === $args['redirection'] && true === $args['sslverify'], 'HTTP trust boundary weakened' );
 $ack = str_ends_with( $url, '/source-receipts' ); $calls[] = $ack ? 'ack' : 'snapshot';
 if ( $ack ) {
  $receipt = json_decode( $args['body'], true );
  sync_check( $receipt['snapshotId'] === $snapshot['snapshot']['id'] && $receipt['snapshotRevision'] === $snapshot['snapshot']['revision'], 'Receipt has wrong snapshot identity' );
  sync_check( 'imported' === $receipt['records'][0]['status'] && (string) get_option( 'chidemoon_catalog_fixture' )['productId'] === $receipt['records'][0]['localProductId'], 'Receipt does not match imported product' );
  return array( 'response' => array( 'code' => $fail_ack ? 503 : 200 ), 'body' => wp_json_encode( array( 'imported' => true, 'publicationConfirmed' => false ) ), 'headers' => array() );
 }
 $body = $snapshot; if ( $wrong_tenant ) { $body['organization']['id'] = 'other-organization'; }
 return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( $body ), 'headers' => array() );
}, 10, 3 );
$facts = Chidemoon_Core_Enrichment::read( $id ); $relationships = Chidemoon_Core_Looks::relationships( $fixture['lookIds'][0] );
Chidemoon_Catalog_Sync::poll();
sync_check( 1 === count( get_option( 'chidemoon_catalog_outbox' ) ), 'Failed acknowledgement was lost' );
sync_check( empty( get_option( 'chidemoon_catalog_status' )['lastSuccessAt'] ), 'Import claimed success before acknowledgement' );
sync_check( '120000' === wc_get_product( $id )->get_price(), 'Source commercial observation not imported' );
sync_check( $facts === Chidemoon_Core_Enrichment::read( $id ) && $relationships === Chidemoon_Core_Looks::relationships( $fixture['lookIds'][0] ), 'Live sync changed editorial data' );
$fail_ack = false; $calls = array(); Chidemoon_Catalog_Sync::poll( 'retry' );
sync_check( array() === $calls, 'Retry ignored bounded backoff' );
$status = get_option( 'chidemoon_catalog_status' ); $status['nextAttemptAt'] = time() - 1; update_option( 'chidemoon_catalog_status', $status );
Chidemoon_Catalog_Sync::poll( 'retry' );
sync_check( array( 'ack', 'snapshot', 'ack' ) === $calls, 'Pending receipts were not delivered before fetching again' );
sync_check( array() === get_option( 'chidemoon_catalog_outbox' ) && ! empty( get_option( 'chidemoon_catalog_status' )['lastSuccessAt'] ), 'Safe receipt retry did not finish' );
$revision = get_post_meta( $id, Chidemoon_Core_Source_Importer::META_REVISION, true );
$wrong_tenant = true; Chidemoon_Catalog_Sync::poll();
sync_check( true === get_option( 'chidemoon_catalog_status' )['blocked'] && 'organization_mismatch' === get_option( 'chidemoon_catalog_status' )['error'], 'Cross-tenant snapshot was accepted' );
sync_check( $revision === get_post_meta( $id, Chidemoon_Core_Source_Importer::META_REVISION, true ), 'Cross-tenant snapshot mutated data' );
update_option( 'chidemoon_catalog_settings', array(), false ); delete_option( 'chidemoon_catalog_tokens' ); delete_option( 'chidemoon_catalog_status' ); wp_clear_scheduled_hook( 'chidemoon_catalog_poll' );
echo "PASS: encrypted credentials, source ownership, durable receipts, safe retry and tenant binding\n";
