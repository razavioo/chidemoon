<?php
/**
 * Plugin Name: Chidemoon AI
 * Plugin URI: https://chidemoon.com/
 * Description: Independent, review-gated editorial AI for Chidemoon.
 * Version: 0.2.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Chidemoon
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: chidemoon-ai
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHIDEMOON_AI_VERSION', '0.2.0' );
define( 'CHIDEMOON_AI_FILE', __FILE__ );
define( 'CHIDEMOON_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'CHIDEMOON_AI_URL', plugin_dir_url( __FILE__ ) );

require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-capabilities.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-settings.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-state-machine.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-activator.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-usage.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-repository.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-evidence.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-web.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-look.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-enrich.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-provider.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-openai-compatible-provider.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-moderation.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-media.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-runner.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-assistant.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-assistant-widget.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-rest-controller.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-admin.php';
require_once CHIDEMOON_AI_DIR . 'includes/class-chidemoon-ai-plugin.php';

register_activation_hook( __FILE__, array( 'Chidemoon_AI_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Chidemoon_AI_Activator', 'deactivate' ) );

/**
 * Returns the independent Chidemoon AI runtime.
 */
function chidemoon_ai(): Chidemoon_AI_Plugin {
	return Chidemoon_AI_Plugin::instance();
}

chidemoon_ai();
