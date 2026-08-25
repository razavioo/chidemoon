<?php
/**
 * PHPUnit bootstrap — loads Brain Monkey WP stubs then plugin classes.
 *
 * ABSPATH must be defined before any plugin file is required because every
 * plugin file guards against direct access with `if (!defined('ABSPATH')) exit;`.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'WPINC', 'wp-includes' );

if ( ! defined( 'KALAHAMOON_VERSION' ) ) {
	define( 'KALAHAMOON_VERSION', '1.0.0-test' );
}
if ( ! defined( 'KALAHAMOON_PLUGIN_DIR' ) ) {
	define( 'KALAHAMOON_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'KALAHAMOON_PLUGIN_URL' ) ) {
	define( 'KALAHAMOON_PLUGIN_URL', 'https://example.test/wp-content/plugins/kalahamoon/' );
}

// ── Minimal WordPress class stubs ─────────────────────────────────────────

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int    $ID           = 0;
		public string $post_title   = '';
		public string $post_content = '';
		public string $post_status  = 'publish';

		public function __construct( array $data = array() ) {
			foreach ( $data as $key => $value ) {
				$this->{$key} = $value;
			}
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		private mixed $data;

		public function __construct( string $code = '', string $message = '', mixed $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data(): mixed {
			return $this->data;
		}
	}
}

// ── Patchwork must be loaded before the autoloader for Brain Monkey function mocking ──
require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';

// ── Composer autoloader (PHPUnit + Brain Monkey) ───────────────────────────
require_once __DIR__ . '/../vendor/autoload.php';

// ── Plugin classes under test ──────────────────────────────────────────────
require_once __DIR__ . '/../includes/i18n/class-kalahamoon-rtl.php';
require_once __DIR__ . '/../includes/core/class-kalahamoon-link-builder.php';
require_once __DIR__ . '/../includes/core/class-kalahamoon-disclosure.php';
require_once __DIR__ . '/../includes/display/class-kalahamoon-shortcodes.php';
require_once __DIR__ . '/../includes/display/class-kalahamoon-patterns.php';
require_once __DIR__ . '/../includes/display/class-kalahamoon-listings.php';
