<?php
/** Safely convert existing article bodies (and optionally product descriptions) to native Elementor. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}
if ( ! current_user_can( 'edit_posts' ) ) {
	WP_CLI::error( 'Run this command as a user who can edit the selected content.' );
}
if ( ! class_exists( 'Chidemoon_Core_Elementor_Content' ) ) {
	require_once dirname( __DIR__ ) . '/plugins/chidemoon-core/includes/class-chidemoon-core-elementor-content.php';
}
$arguments = array_values( (array) ( $args ?? array() ) );
if ( array_diff( $arguments, array( 'apply', 'products' ) ) || count( $arguments ) !== count( array_unique( $arguments ) ) ) {
	WP_CLI::error( 'Usage: wp eval-file tools/editorial-elementor-upgrade.php [apply] [products]' );
}
$apply = in_array( 'apply', $arguments, true );
$types = in_array( 'products', $arguments, true ) ? array( 'post', 'product' ) : array( 'post' );
$report = array( 'planned' => array(), 'updated' => array(), 'unchanged' => array(), 'manual' => array(), 'error' => array() );
$page = 1;
do {
	$ids = get_posts( array( 'post_type' => $types, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'fields' => 'ids', 'posts_per_page' => 100, 'paged' => $page++, 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true ) );
	foreach ( $ids as $post_id ) {
		$result = Chidemoon_Core_Elementor_Content::upgrade( (int) $post_id, $apply );
		$report[ $result['status'] ][] = (int) $post_id;
		$message = strtoupper( $result['status'] ) . ' #' . $post_id . ': ' . $result['message'] . ' (source=' . $result['source'] . ', widgets=' . $result['widget_count'] . ')';
		if ( in_array( $result['status'], array( 'manual', 'error' ), true ) ) { WP_CLI::warning( $message ); }
		else { WP_CLI::log( $message ); }
		foreach ( $result['notes'] as $note ) { WP_CLI::log( '  ' . $note ); }
	}
} while ( count( $ids ) === 100 );
if ( $apply && class_exists( '\Elementor\Plugin' ) && $report['updated'] ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
$counts = array();
foreach ( $report as $status => $ids ) { $counts[] = $status . '=' . count( $ids ) . ( $ids ? ' [' . implode( ',', $ids ) . ']' : '' ); }
$summary = ( $apply ? 'Applied' : 'Dry run' ) . ': ' . implode( '; ', $counts ) . '.';
if ( $report['error'] ) { WP_CLI::error( $summary ); }
elseif ( $report['manual'] ) { WP_CLI::warning( $summary ); }
else { WP_CLI::success( $summary ); }
