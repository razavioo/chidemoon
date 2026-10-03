<?php
/** Preserve the native search template when filtering to products. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'edit_theme_options' ) ) { exit; }
$template = get_page_by_path( 'chidemoon-product-archive', OBJECT, 'elementor_library' );
if ( ! $template ) { WP_CLI::error( 'Managed product archive is missing.' ); }
$stored = get_post_meta( $template->ID, '_elementor_conditions', true );
if ( ! is_array( $stored ) || ! in_array( 'include/woocommerce/product_archive', $stored, true ) ) { WP_CLI::error( 'Unexpected product archive conditions; review manually.' ); }
if ( in_array( 'exclude/archive/search', $stored, true ) ) { WP_CLI::success( 'Search exclusion already present.' ); return; }
if ( ! in_array( 'apply', $args ?? array(), true ) ) { WP_CLI::log( 'Would exclude searches from managed product archive #' . $template->ID ); return; }
$conditions = array();
foreach ( $stored as $path ) {
	$parts = explode( '/', $path );
	if ( count( $parts ) > 4 || count( $parts ) < 2 ) { WP_CLI::error( 'Unsupported condition path.' ); }
	$conditions[] = array_combine( array_slice( array( 'type', 'name', 'sub_name', 'sub_id' ), 0, count( $parts ) ), $parts );
}
$conditions[] = array( 'type' => 'exclude', 'name' => 'archive', 'sub_name' => 'search' );
add_post_meta( $template->ID, '_chidemoon_pre_search_repair_20261003', $stored, true );
$manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();
$manager->save_conditions( $template->ID, $conditions );
if ( ! in_array( 'exclude/archive/search', (array) get_post_meta( $template->ID, '_elementor_conditions', true ), true ) ) { WP_CLI::error( 'Search exclusion did not persist.' ); }
WP_CLI::success( 'Product searches retain the search template and facet controls.' );
