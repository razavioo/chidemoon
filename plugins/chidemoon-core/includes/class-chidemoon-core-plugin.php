<?php
/**
 * Coordinates the independent Chidemoon Core subsystems.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Plugin {
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	public function register(): void {
		load_plugin_textdomain( 'chidemoon-core', false, dirname( CHIDEMOON_CORE_BASENAME ) . '/languages' );
		Chidemoon_Core_Activator::maybe_upgrade();
		Chidemoon_Core_Affiliate::register();
		Chidemoon_Core_Forms::register();
		Chidemoon_Core_Admin::register();
		Chidemoon_Core_Blocks::register();
		Chidemoon_Core_Shop_The_Look::register();
		Chidemoon_Core_Compare::register();
		Chidemoon_Core_Importer::register();
		add_action( 'init', array( 'Chidemoon_Core_Activator', 'flush_rewrite_rules_if_pending' ), 99 );
	}
}
