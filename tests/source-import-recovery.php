<?php
require __DIR__ . '/source-import-contract.php';
function source_case( string $name, callable $run ): void {
 global $fixture, $snapshot;
 $GLOBALS['options'] = array(); $GLOBALS['matches'] = array(); $GLOBALS['source_map'] = array( $fixture['sourceKey'] => 123 );
 $p = new WC_Product_External();
 $p->meta[ Chidemoon_Core_Source_Importer::META_IDENTITY ] = json_encode( $fixture['identity'] );
 $p->meta[ Chidemoon_Core_Affiliate::META_SOURCE_KEY ] = $fixture['sourceKey'];
 $GLOBALS['products'] = array( 123 => $p ); $GLOBALS['product'] = $p;
 $snapshot['items'] = array( $fixture ); unset( $snapshot['items'][0]['content']['images'] ); $snapshot['withdrawals'] = array();
 $run(); echo "PASS: $name\n"; $GLOBALS['source_passed'] = ( $GLOBALS['source_passed'] ?? 0 ) + 1;
}
source_case( 'verified import mapping survives cloned source metadata without changing the copy', function() {
 global $fixture, $snapshot;
 $copy = clone $GLOBALS['product']; $copy->id = 721; $GLOBALS['products'][721] = $copy;
 $GLOBALS['matches'][ $fixture['sourceKey'] ] = array( 123, 721 );
 $before = serialize( $copy );
 check( 123 === Chidemoon_Core_Source_Importer::resolve( $fixture['sourceKey'], $fixture['aliases'] ), 'Copy displaced canonical destination' );
 $result = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
 check( 'imported' === $result['records'][0]['status'] && '123' === $result['records'][0]['localProductId'], 'Canonical source stopped syncing' );
 check( $before === serialize( $copy ), 'Sync changed the copied product' );
} );
source_case( 'unmapped ambiguous identities remain blocked rather than selecting an arbitrary product', function() {
 global $fixture, $snapshot;
 $GLOBALS['source_map'] = array(); $GLOBALS['matches'][ $fixture['sourceKey'] ] = array( 123, 721 );
 $result = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
 check( 'identity_collision' === $result['records'][0]['issues'][0], 'Ambiguous identity silently resolved' );
} );
foreach ( array( 'different_identity', 'missing_identity', 'missing_source_key', 'missing_product' ) as $kind ) {
 source_case( "inconsistent canonical mapping is never trusted: $kind", function() use ( $kind ) {
  global $fixture, $snapshot;
  if ( 'different_identity' === $kind ) { $i = $fixture['identity']; $i['product'] = 'other'; $GLOBALS['product']->meta[ Chidemoon_Core_Source_Importer::META_IDENTITY ] = json_encode( $i ); }
  elseif ( 'missing_identity' === $kind ) { unset( $GLOBALS['product']->meta[ Chidemoon_Core_Source_Importer::META_IDENTITY ] ); }
  elseif ( 'missing_source_key' === $kind ) { unset( $GLOBALS['product']->meta[ Chidemoon_Core_Affiliate::META_SOURCE_KEY ] ); }
  else { $GLOBALS['products'] = array(); }
  $result = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
  check( 'identity_collision' === $result['records'][0]['issues'][0], 'Unverified destination mapping accepted' );
 } );
}
source_case( 'JSON key ordering and whitespace cannot invent an identity collision', function() {
 global $fixture, $snapshot;
 $GLOBALS['product']->meta[ Chidemoon_Core_Source_Importer::META_IDENTITY ] = json_encode( array_reverse( $fixture['identity'], true ), JSON_PRETTY_PRINT );
 $result = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
 check( 'imported' === $result['records'][0]['status'], 'Equivalent identity rejected' );
} );
source_case( 'product duplication excludes source ownership and rollback metadata but preserves editorial content', function() {
 $keys = array( '_chidemoon_source_key', '_chidemoon_source_identity', '_chidemoon_source_aliases', '_chidemoon_source_revision', '_chidemoon_source_fingerprint', '_chidemoon_source_observations', '_chidemoon_identity_migration_backup_v2', '_chidemoon_product_facts', '_custom_editorial' );
 $excluded = Chidemoon_Core_Source_Importer::duplicate_excluded_meta( array( '_existing_exclusion' ), $keys );
 foreach ( array_slice( $keys, 0, 7 ) as $key ) { check( in_array( $key, $excluded, true ), "Copied source metadata: $key" ); }
 check( in_array( '_existing_exclusion', $excluded, true ) && ! in_array( '_chidemoon_product_facts', $excluded, true ) && ! in_array( '_custom_editorial', $excluded, true ), 'Unrelated duplicate behavior changed' );
} );
source_case( 'a colliding record does not prevent a healthy record from importing', function() {
 global $fixture, $snapshot;
 $bad = $snapshot['items'][0]; $bad['identity']['product'] = 'ambiguous'; $bad['sourceKey'] = Chidemoon_Core_Source_Contract::source_key( $bad['identity'] );
 $GLOBALS['matches'][ $bad['sourceKey'] ] = array( 999, 888 );
 array_unshift( $snapshot['items'], $bad );
 $result = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
 check( 1 === $result['failed'] && 'failed' === $result['records'][0]['status'] && 'imported' === $result['records'][1]['status'], 'One collision stopped the batch' );
} );
source_case( 'successful replay is idempotent and keeps destination ID, revision and editorial state', function() {
 global $snapshot;
 $first = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
 $before = serialize( $GLOBALS['product'] );
 $again = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
 check( $first['records'] === $again['records'] && $before === serialize( $GLOBALS['product'] ), 'Replay rewrote content or identity' );
} );
source_case( 'withdrawal uses canonical identity despite copied metadata and preserves editorial approval', function() {
 global $fixture, $snapshot;
 $GLOBALS['matches'][ $fixture['sourceKey'] ] = array( 123, 721 );
 $snapshot['items'] = array(); $snapshot['withdrawals'] = array( array( 'sourceKey' => $fixture['sourceKey'], 'revision' => 2 ) );
 $result = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
 check( 'imported' === $result['records'][0]['status'] && '1' === $GLOBALS['product']->meta[ Chidemoon_Core_Source_Importer::META_WITHDRAWN ], 'Withdrawal blocked by copied identity' );
 check( 'approved facts' === $GLOBALS['product']->meta['_chidemoon_product_facts'], 'Withdrawal destroyed editorial data' );
} );
source_case( 'stale revisions never overwrite a newer destination', function() {
 global $snapshot;
 $GLOBALS['product']->meta[ Chidemoon_Core_Source_Importer::META_REVISION ] = 10;
 $before = serialize( $GLOBALS['product'] );
 $result = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
 check( 'stale_revision' === $result['records'][0]['issues'][0] && $before === serialize( $GLOBALS['product'] ), 'Stale revision mutated destination' );
} );
echo 'Passed ' . $GLOBALS['source_passed'] . " source identity recovery scenarios.\n";
