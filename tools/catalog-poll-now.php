<?php
if ( ! defined( 'ABSPATH' ) || ! current_user_can( 'manage_woocommerce' ) ) { exit(1); }
Chidemoon_Catalog_Sync::poll( 'manual' );
$status = get_option( 'chidemoon_catalog_status', array() );
$report = $status['report'] ?? array();
echo wp_json_encode( array( 'lastSuccessAt' => $status['lastSuccessAt'] ?? null, 'error' => $status['error'] ?? null, 'report' => array_diff_key( $report, array( 'records' => true ) ), 'received' => count( $report['records'] ?? array() ), 'issues' => array_values( array_filter( $report['records'] ?? array(), static fn( $row ) => 'imported' !== $row['status'] ) ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
