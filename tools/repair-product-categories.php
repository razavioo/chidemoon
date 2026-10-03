<?php
/** Repair only the four verified lamps still assigned to the default category. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'edit_products' ) ) { exit; }
$apply = in_array( 'apply', $args ?? array(), true );
$catalogue = json_decode( file_get_contents( __DIR__ . '/catalogue/verified-products.json' ), true, 512, JSON_THROW_ON_ERROR );
$default_id = (int) get_option( 'default_product_cat' );
foreach ( $catalogue['items'] as $item ) {
	$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 2, 'meta_key' => '_chidemoon_source_key', 'meta_value' => $item['sourceKey'] ) );
	if ( 1 !== count( $ids ) ) { WP_CLI::error( 'Expected exactly one verified product: ' . $item['sourceKey'] ); }
	$product = wc_get_product( $ids[0] );
	$current = $product->get_category_ids();
	$term = get_term_by( 'slug', $item['category']['slug'], 'product_cat' );
	if ( $term && in_array( (int) $term->term_id, $current, true ) ) { WP_CLI::log( 'Already repaired: ' . $ids[0] ); continue; }
	if ( array( $default_id ) !== array_values( array_map( 'intval', $current ) ) && ! empty( $current ) ) { WP_CLI::warning( 'Preserved editor categories: ' . $ids[0] ); continue; }
	WP_CLI::log( ( $apply ? 'Repairing ' : 'Would repair ' ) . $ids[0] . ': ' . $item['category']['slug'] );
	if ( ! $apply ) { continue; }
	if ( ! $term ) {
		$created = wp_insert_term( $item['category']['label'], 'product_cat', array( 'slug' => $item['category']['slug'] ) );
		if ( is_wp_error( $created ) ) { WP_CLI::error( $created->get_error_message() ); }
		$term = get_term( $created['term_id'], 'product_cat' );
	}
	add_post_meta( $ids[0], '_chidemoon_pre_category_repair_20261003', $current, true );
	$product->set_category_ids( array( (int) $term->term_id ) );
	$product->save();
	$stored = wp_get_object_terms( $ids[0], 'product_cat', array( 'fields' => 'ids' ) );
	if ( is_wp_error( $stored ) || array( (int) $term->term_id ) !== array_map( 'intval', $stored ) ) { WP_CLI::error( 'Category verification failed: ' . $ids[0] ); }
}
WP_CLI::success( $apply ? 'Verified lamp category repair completed.' : 'Category repair preview completed.' );
