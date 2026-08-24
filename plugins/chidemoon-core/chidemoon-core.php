<?php
/**
 * Plugin Name: Chidemoon Core
 * Plugin URI: https://chidemoon.com
 * Description: Independent affiliate, editorial, readiness, and local audience foundations for Chidemoon.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * Author: Chidemoon
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: chidemoon-core
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHIDEMOON_CORE_VERSION', '0.1.0' );
define( 'CHIDEMOON_CORE_FILE', __FILE__ );
define( 'CHIDEMOON_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CHIDEMOON_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'CHIDEMOON_CORE_BASENAME', plugin_basename( __FILE__ ) );

require_once CHIDEMOON_CORE_DIR . 'includes/class-chidemoon-core-activator.php';
require_once CHIDEMOON_CORE_DIR . 'includes/class-chidemoon-core-affiliate.php';
require_once CHIDEMOON_CORE_DIR . 'includes/class-chidemoon-core-forms.php';
require_once CHIDEMOON_CORE_DIR . 'includes/class-chidemoon-core-admin.php';
require_once CHIDEMOON_CORE_DIR . 'includes/class-chidemoon-core-blocks.php';
require_once CHIDEMOON_CORE_DIR . 'includes/class-chidemoon-core-importer.php';
require_once CHIDEMOON_CORE_DIR . 'includes/class-chidemoon-core-plugin.php';

register_activation_hook( __FILE__, array( 'Chidemoon_Core_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Chidemoon_Core_Activator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! Chidemoon_Core_Activator::woocommerce_is_active() ) {
			add_action( 'admin_notices', 'chidemoon_core_render_woocommerce_notice' );
			return;
		}

		Chidemoon_Core_Plugin::instance()->register();
	}
);

/**
 * Keeps the activation dependency visible instead of allowing partial local
 * metadata or public forms to look operational without WooCommerce.
 */
function chidemoon_core_render_woocommerce_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>' . esc_html__( 'Chidemoon Core requires WooCommerce to be active.', 'chidemoon-core' ) . '</p></div>';
}
