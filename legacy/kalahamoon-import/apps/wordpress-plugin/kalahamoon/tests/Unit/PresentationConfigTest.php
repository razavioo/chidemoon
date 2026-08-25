<?php

namespace Kalahamoon\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PresentationConfigTest extends TestCase {

	private string $pluginDir;

	protected function setUp(): void {
		parent::setUp();
		$this->pluginDir = dirname( __DIR__, 2 );
	}

	public function test_theme_json_exposes_spacing_and_typography_presets(): void {
		$path = $this->pluginDir . '/theme.json';
		$data = json_decode( (string) file_get_contents( $path ), true );

		$this->assertNotEmpty( $data['settings']['spacing']['spacingSizes'] ?? array() );
		$this->assertNotEmpty( $data['settings']['typography']['fontSizes'] ?? array() );
		$this->assertArrayHasKey( 'layout', $data['settings'] ?? array() );
	}

	public function test_patterns_are_scoped_to_posts_and_pages(): void {
		$files = glob( $this->pluginDir . '/patterns/*.php' ) ?: array();

		foreach ( $files as $file ) {
			$contents = (string) file_get_contents( $file );
			$this->assertMatchesRegularExpression(
				'/^\s*\*\s*Post Types:\s*(?:page|post,\s*page)\s*$/mi',
				$contents,
				"Pattern '$file' should declare a page-compatible Post Types header."
			);
		}
	}

	public function test_public_css_exposes_theme_native_kalahamoon_tokens(): void {
		$css = (string) file_get_contents( $this->pluginDir . '/public/css/kalahamoon-public.css' );
		foreach ( array( '--kalahamoon-primary', '--kalahamoon-on-primary', '--kalahamoon-surface', '--kalahamoon-surface-alt', '--kalahamoon-muted', '--kalahamoon-border', '--kalahamoon-radius-lg', '--kalahamoon-focus' ) as $token ) {
			$this->assertStringContainsString( $token, $css, "Public CSS should expose $token." );
		}
		$this->assertStringContainsString( '.kalahamoon-screen-reader-text', $css );
	}

	public function test_frontend_styles_do_not_use_high_specificity_button_hacks(): void {
		$css = (string) file_get_contents( $this->pluginDir . '/public/css/kalahamoon-public.css' );
		$this->assertStringNotContainsString( '.kalahamoon-cta-button.kalahamoon-cta-button', $css );
		$this->assertStringNotContainsString( '.kalahamoon-product-grid.kalahamoon-product-grid', $css );
	}

	public function test_plugin_exposes_theme_and_tracker_filters(): void {
		$plugin = (string) file_get_contents( $this->pluginDir . '/includes/class-kalahamoon-plugin.php' );
		$this->assertStringContainsString( 'kalahamoon_css_tokens', $plugin );
		$this->assertStringContainsString( 'kalahamoon_enqueue_public_styles', $plugin );
		$this->assertStringContainsString( 'kalahamoon_enqueue_click_tracker', $plugin );
	}

	public function test_admin_dashboard_exposes_public_catalog_readiness(): void {
		$admin = (string) file_get_contents( $this->pluginDir . '/includes/admin/class-kalahamoon-admin.php' );

		$this->assertStringContainsString( 'Kalahamoon_Product_Cache::public_ready_count()', $admin );
		$this->assertStringContainsString( 'Public site readiness', $admin );
		$this->assertStringContainsString( 'Review publishing readiness', $admin );
		$this->assertStringContainsString( 'Catalog publication status', $admin );
		$this->assertStringContainsString( 'Review product readiness', $admin );
		$this->assertStringContainsString( "esc_html__( 'Ready for public catalog', 'kalahamoon' )", $admin );
		$this->assertStringContainsString( "admin.php?page=kalahamoon-products", $admin );
		$this->assertStringContainsString( "current_user_can( 'manage_options' )", $admin );
	}

	public function test_product_table_explains_the_catalog_policy_without_exposing_internal_issue_codes(): void {
		$admin = (string) file_get_contents( $this->pluginDir . '/includes/admin/class-kalahamoon-admin.php' );

		$this->assertStringContainsString( 'Kalahamoon_Catalog_Policy::evaluate', $admin );
		$this->assertStringContainsString( 'public_catalog_readiness_messages', $admin );
		$this->assertStringContainsString( 'Ready for public catalog', $admin );
		$this->assertStringContainsString( 'Confirm the canonical product category.', $admin );
		$this->assertStringNotContainsString( "echo esc_html( implode( ', ', \$issues ) )", $admin );
	}

	public function test_product_table_has_a_mobile_card_layout_instead_of_a_wide_overflowing_grid(): void {
		$admin = (string) file_get_contents( $this->pluginDir . '/includes/admin/class-kalahamoon-admin.php' );
		$css   = (string) file_get_contents( $this->pluginDir . '/admin/css/kalahamoon-admin.css' );

		$this->assertStringContainsString( 'data-label="<?php esc_attr_e( \'Readiness\'', $admin );
		$this->assertStringContainsString( '.kalahamoon-product-table td::before', $css );
		$this->assertStringContainsString( 'content: attr(data-label)', $css );
		$this->assertStringContainsString( '.kalahamoon-table-scroll {', $css );
		$this->assertStringContainsString( 'min-inline-size: 1120px;', $css );
		$this->assertStringContainsString( '@media (max-width: 782px)', $css );
	}
}
