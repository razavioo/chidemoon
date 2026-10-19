<?php
/** Local WordPress integration test; all fixture records are removed. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { exit( 1 ); }
$created = array();
$check = static function ( bool $ok, string $message ): void { if ( ! $ok ) { throw new RuntimeException( $message ); } WP_CLI::log( 'PASS: ' . $message ); };
try {
 foreach ( array( 'A', 'B' ) as $name ) { $created[] = wp_insert_post( array( 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Relation fixture ' . $name ) ); }
 [$a, $b] = $created;
 $article = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Relation fixture article' ) ); $created[] = $article;
 update_post_meta( $article, Chidemoon_Core_Content_Relations::EDITORIAL, array( $a ) );
 $check( in_array( $article, Chidemoon_Core_Content_Relations::related_content( $a ), true ), 'Explicit editorial relation is visible from the product.' );
 $check( ! in_array( $article, Chidemoon_Core_Content_Relations::related_content( $b ), true ), 'An unrelated product does not inherit content.' );
 update_post_meta( $article, Chidemoon_Core_Content_Relations::EDITORIAL, array( $b ) );
 $check( ! in_array( $article, Chidemoon_Core_Content_Relations::related_content( $a ), true ), 'Replacing a relation removes the old reverse link.' );
 wp_update_post( array( 'ID' => $article, 'post_status' => 'draft' ) );
 $check( ! in_array( $article, Chidemoon_Core_Content_Relations::related_content( $b ), true ), 'Draft content is never shown publicly.' );
 delete_post_meta( $article, Chidemoon_Core_Content_Relations::EDITORIAL );
 update_post_meta( $article, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => (string) $b, 'settings' => array( 'product_id' => $a, 'template_id' => $b ) ) ) ) ) );
 $check( array( $a ) === Chidemoon_Core_Content_Relations::references( $article ), 'Known product fields count; unrelated template IDs do not.' );
 update_post_meta( $article, '_elementor_data', wp_slash( wp_json_encode( array( array( 'widgetType' => 'chidemoon-compare-table', 'settings' => array( 'ids_text' => (string) $b, 'products' => array( array( 'product_id' => $a ) ) ) ) ) ) ) );
 $check( array( $b ) === Chidemoon_Core_Content_Relations::references( $article ), 'Comparison text override excludes inactive repeater products.' );
 delete_post_meta( $article, '_elementor_data' );
 wp_update_post( array( 'ID' => $article, 'post_status' => 'publish', 'post_content' => '[chidemoon_compare_table products="' . $a . ',' . $b . '"]' ) );
 $check( 2 === count( Chidemoon_Core_Content_Relations::references( $article ) ), 'Comparison shortcode resolves both actual product references.' );
 wp_update_post( array( 'ID' => $article, 'post_content' => '<a href="' . get_permalink( $a ) . '">Product</a>' ) );
 $check( array( $a ) === Chidemoon_Core_Content_Relations::references( $article ), 'A real product permalink creates an exact relation.' );
 $scene = wp_insert_post( array( 'post_type' => 'reycob_look', 'post_status' => 'publish', 'post_title' => 'Relation fixture scene' ) ); $created[] = $scene;
 update_post_meta( $scene, '_reycob_stl_points', array( array( 'product_id' => $a, 'x' => 30, 'y' => 50 ) ) );
 $check( in_array( $scene, Chidemoon_Core_Content_Relations::related_content( $a ), true ), 'Vendor scene hotspots create a reverse product link.' );
 delete_post_meta( $scene, '_reycob_stl_points' );
 $check( ! in_array( $scene, Chidemoon_Core_Content_Relations::related_content( $a ), true ), 'Deleted hotspots remove stale reverse links.' );
 WP_CLI::success( 'Content relations integration checks passed.' );
} finally { foreach ( array_reverse( $created ) as $id ) { wp_delete_post( $id, true ); } }
