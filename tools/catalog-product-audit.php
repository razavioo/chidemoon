<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$status = get_option( 'chidemoon_catalog_status', array() );
$config = get_option( 'chidemoon_catalog_settings', array() );
$report = $status['report'] ?? array();
unset( $status['report'] );
$products = wp_count_posts( 'product' );
$drafts = get_posts( array( 'post_type' => 'product', 'post_status' => 'draft', 'posts_per_page' => -1 ) );
echo wp_json_encode( array( 'status' => $status, 'report' => array_diff_key( $report, array( 'records' => true ) ), 'received' => count( $report['records'] ?? array() ), 'organizationId' => $config['organizationId'] ?? '', 'counts' => $products, 'drafts' => array_map( static fn( $post ) => array( 'id' => $post->ID, 'title' => $post->post_title, 'source' => (bool) get_post_meta( $post->ID, Chidemoon_Core_Source_Importer::META_IDENTITY, true ), 'needs' => Chidemoon_Core_Enrichment::readiness( $post->ID ) ), $drafts ) ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . PHP_EOL;
