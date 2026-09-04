<?php
/**
 * Plugin runtime bootstrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_Plugin {
	private static ?Chidemoon_AI_Plugin $instance = null;

	public static function instance(): Chidemoon_AI_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'boot' ), 20 );
	}

	public function boot(): void {
		load_plugin_textdomain( 'chidemoon-ai', false, dirname( plugin_basename( CHIDEMOON_AI_FILE ) ) . '/languages' );
		Chidemoon_AI_Activator::maybe_upgrade();
		Chidemoon_AI_Settings::register();
		Chidemoon_AI_Runner::register();
		Chidemoon_AI_Assistant_Widget::register();
		add_action( 'rest_api_init', array( 'Chidemoon_AI_REST_Controller', 'register' ) );

		if ( is_admin() ) {
			Chidemoon_AI_Admin::register();
		}
	}
}
