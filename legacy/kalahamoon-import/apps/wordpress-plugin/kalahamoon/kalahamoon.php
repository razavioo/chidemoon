<?php
/**
 * Plugin Name: Kalahamoon
 * Plugin URI: https://kalahamoon.com/wordpress
 * Description: Connect your WordPress site to Kalahamoon — display products, generate affiliate links, capture leads, and leverage AI-powered product comparison. RTL-first.
 * Version: 1.19.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Kalahamoon
 * Author URI: https://kalahamoon.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kalahamoon
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KALAHAMOON_VERSION', '1.19.0' );
define( 'KALAHAMOON_PLUGIN_FILE', __FILE__ );
define( 'KALAHAMOON_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KALAHAMOON_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KALAHAMOON_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

if ( ! defined( 'KALAHAMOON_DEFAULT_API_URL' ) ) {
	define( 'KALAHAMOON_DEFAULT_API_URL', 'https://app.kalahamoon.com' );
}

/**
 * Returns the default Kalahamoon API URL, filterable for local/self-hosted installs.
 */
function kalahamoon_default_api_url(): string {
	$default = defined( 'KALAHAMOON_DEFAULT_API_URL' ) ? KALAHAMOON_DEFAULT_API_URL : 'https://app.kalahamoon.com';
	return rtrim( (string) apply_filters( 'kalahamoon_default_api_url', $default ), '/' );
}

require_once KALAHAMOON_PLUGIN_DIR . 'includes/class-kalahamoon-plugin.php';

register_activation_hook( __FILE__, array( 'Kalahamoon_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Kalahamoon_Deactivator', 'deactivate' ) );

/**
 * Returns the main plugin instance.
 */
function kalahamoon(): Kalahamoon_Plugin {
	return Kalahamoon_Plugin::instance();
}

kalahamoon();
