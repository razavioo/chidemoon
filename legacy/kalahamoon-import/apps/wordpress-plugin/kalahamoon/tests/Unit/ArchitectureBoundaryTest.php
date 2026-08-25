<?php
use PHPUnit\Framework\TestCase;

final class ArchitectureBoundaryTest extends TestCase {
	private function repo( string $path ): string {
		$root = dirname( __DIR__, 5 );
		$content = file_get_contents( $root . '/' . $path );
		$this->assertNotFalse( $content, 'Unable to read ' . $path );
		return (string) $content;
	}

	public function test_kalahamoon_catalog_client_uses_only_generic_integration_routes(): void {
		$client = $this->repo( 'apps/wordpress-plugin/kalahamoon/includes/api/class-kalahamoon-api-client.php' );

		foreach ( array( '/api/integrations/catalog/v1/capabilities', '/api/integrations/catalog/v1/snapshot', '/api/integrations/catalog/v1/delivery-receipts' ) as $endpoint ) {
			$this->assertStringContainsString( $endpoint, $client );
		}
		$this->assertStringNotContainsString( '/api/public/editorial', $client );
	}

	public function test_kalahamoon_exposes_reusable_content_primitives(): void {
		$shortcodes = $this->repo( 'apps/wordpress-plugin/kalahamoon/includes/display/class-kalahamoon-shortcodes.php' );
		$shop_look = $this->repo( 'apps/wordpress-plugin/kalahamoon/blocks/shop-the-look/render.php' );

		foreach ( array( 'kalahamoon_products', 'kalahamoon_compare', 'kalahamoon_look', 'kalahamoon_lead_form' ) as $shortcode ) {
			$this->assertStringContainsString( "'$shortcode'", $shortcodes );
		}
		$this->assertStringContainsString( 'data-kalahamoon-event', $shop_look );
	}

	public function test_kalahamoon_rest_surface_has_no_site_specific_editorial_mutation(): void {
		$rest = $this->repo( 'apps/wordpress-plugin/kalahamoon/includes/rest/class-kalahamoon-rest-controller.php' );
		$cache = $this->repo( 'apps/wordpress-plugin/kalahamoon/includes/core/class-kalahamoon-product-cache.php' );

		$this->assertStringNotContainsString( '/editorial', $rest );
		$this->assertStringNotContainsString( 'update_editorial_cache', $cache );
	}

	public function test_catalog_consumer_cannot_render_the_legacy_product_workflow(): void {
		$admin = $this->repo( 'apps/wordpress-plugin/kalahamoon/includes/admin/class-kalahamoon-admin.php' );

		$this->assertMatchesRegularExpression(
			'/public static function render_products_page\(\): void \{\s+if \( self::is_catalog_consumer\(\) \) \{\s+self::render_catalog_consumer_page\(\);\s+return;\s+\}/',
			$admin
		);
		$this->assertStringContainsString(
			"if ( ! \$consumer ) {\n\t\t\tadd_action( 'admin_init', array( __CLASS__, 'handle_csv_export' ) );\n\t\t\tadd_action( 'admin_post_kalahamoon_save_product'",
			$admin
		);
	}

	public function test_prebuilt_whatsapp_bridge_reuses_the_offline_runtime_image(): void {
		$compose = $this->repo( 'compose.prebuilt.deploy.yml' );

		$this->assertStringContainsString( 'WA_BRIDGE_RUNTIME_IMAGE: ${WA_BRIDGE_RUNTIME_IMAGE:-kalahamoon-wa-bridge:latest}', $compose );
	}
}
