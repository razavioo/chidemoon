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
		Chidemoon_Core_Source_Importer::register();
		Chidemoon_Core_Enrichment::register();
		add_filter( 'woocommerce_display_product_attributes', array( 'Chidemoon_Core_Detailed_Specs', 'display_attributes' ), 30, 2 );
		Chidemoon_Core_Commercial::register();
		Chidemoon_Core_Looks::register();
		Chidemoon_Core_Reconciliation::register();
		Chidemoon_Core_Discovery::register();
		Chidemoon_Core_Public_Design::register();
		Chidemoon_Core_Branding::register();
		Chidemoon_Core_Elementor_Workspace::register();
		Chidemoon_Core_Search_Facets::register();
		Chidemoon_Core_Dates::register();
		Chidemoon_Core_Content_Relations::register();
		Chidemoon_Core_Breadcrumbs::register();
		Chidemoon_Core_Structural_Links::register();
		Chidemoon_Core_Responsive_Images::register();
		Chidemoon_Core_Page_Cache::register();
		Chidemoon_Core_Critical_Styles::register();
		add_action( 'init', array( 'Chidemoon_Core_Activator', 'flush_rewrite_rules_if_pending' ), 99 );
	}
}
