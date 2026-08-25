<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Deactivator {

	public static function deactivate(): void {
		// Revoke OAuth tokens on deactivation
		if ( class_exists( 'Kalahamoon_Auth' ) ) {
			Kalahamoon_Auth::revoke_tokens();
		}

		wp_clear_scheduled_hook( 'kalahamoon_sync_products' );
		wp_clear_scheduled_hook( 'kalahamoon_purge_clicks' );
		wp_clear_scheduled_hook( 'kalahamoon_check_price_alerts' );
		flush_rewrite_rules();
	}
}
