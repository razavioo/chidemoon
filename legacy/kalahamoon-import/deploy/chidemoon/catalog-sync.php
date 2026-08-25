<?php
/**
 * Run one read-only catalog delivery cycle through WP-CLI.
 *
 * This file is mounted only by the Chidemoon scheduler. Keeping the guard in
 * the deployment layer lets the reusable plugin remain opt-in by default.
 */

if ( ! class_exists( 'Kalahamoon_Catalog_Consumer' ) || ! Kalahamoon_Catalog_Consumer::is_enabled() ) {

	fwrite( STDERR, "The read-only catalog consumer is not enabled.\n" );
	exit( 1 );
}

if ( ! class_exists( 'Kalahamoon_API_Products' ) ) {

	fwrite( STDERR, "The catalog sync entry point is unavailable.\n" );
	exit( 1 );
}

$result = ( new Kalahamoon_API_Products() )->sync_all();
if ( ! is_array( $result ) || empty( $result['complete'] ) || empty( $result['deliveryAcknowledged'] ) ) {

	$message = is_array( $result ) ? (string) ( $result['message'] ?? 'Catalog delivery was not confirmed.' ) : 'Catalog delivery returned an invalid result.';
	fwrite( STDERR, "{$message}\n" );
	exit( 1 );
}

fwrite( STDOUT, "Catalog delivery confirmed.\n" );
