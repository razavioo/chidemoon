<?php
/**
 * Plugin Name: Chidemoon Catalog Sync
 * Description: Approved source-data synchronization with a connector-bound OAuth client.
 * Version: 0.1.0
 * Requires PHP: 8.2
 * Text Domain: chidemoon-catalog-sync
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/includes/class-chidemoon-catalog-sync.php';
add_action( 'init', static function(): void { load_plugin_textdomain( 'chidemoon-catalog-sync', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); }, 0 );
Chidemoon_Catalog_Sync::register();
register_activation_hook( __FILE__, array( 'Chidemoon_Catalog_Sync', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Chidemoon_Catalog_Sync', 'deactivate' ) );
