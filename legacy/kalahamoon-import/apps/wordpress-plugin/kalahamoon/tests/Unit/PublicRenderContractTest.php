<?php

namespace Kalahamoon\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicRenderContractTest extends TestCase {
	private string $plugin_root;

	protected function setUp(): void {
		parent::setUp();
		$this->plugin_root = dirname( __DIR__, 2 );
	}

	public function test_product_cache_exposes_one_editor_aware_public_render_boundary(): void {
		$cache = (string) file_get_contents( $this->plugin_root . '/includes/core/class-kalahamoon-product-cache.php' );

		$this->assertStringContainsString( 'get_for_public_render', $cache );
		$this->assertStringContainsString( "current_user_can( 'edit_posts' )", $cache );
		$this->assertStringContainsString( 'Kalahamoon_Catalog_Policy::apply', $cache );
	}

	#[DataProvider( 'product_render_files' )]
	public function test_public_product_blocks_use_the_shared_render_boundary( string $relative_path ): void {
		$source = (string) file_get_contents( $this->plugin_root . '/' . $relative_path );

		$this->assertStringNotContainsString( 'get_by_kalahamoon_id', $source, $relative_path );
		$this->assertStringContainsString( 'get_for_public_render', $source, $relative_path );
	}

	/** @return array<string, array{string}> */
	public static function product_render_files(): array {
		return array(
			'shortcodes'       => array( 'includes/display/class-kalahamoon-shortcodes.php' ),
			'product box'      => array( 'blocks/product-box/render.php' ),
			'CTA'              => array( 'blocks/cta-button/render.php' ),
			'pros and cons'    => array( 'blocks/pros-cons/render.php' ),
			'price comparison' => array( 'blocks/price-comparison/render.php' ),
			'rating'           => array( 'blocks/rating-box/render.php' ),
			'shop the look'    => array( 'blocks/shop-the-look/render.php' ),
			'AI comparison'    => array( 'blocks/ai-compare/render.php' ),
			'price alert'      => array( 'blocks/price-alert/render.php' ),
		);
	}

	public function test_legacy_grid_queries_only_public_ready_products(): void {
		$shortcodes = (string) file_get_contents( $this->plugin_root . '/includes/display/class-kalahamoon-shortcodes.php' );

		$this->assertStringContainsString( "'public_ready' => true", $shortcodes );
	}

	public function test_product_image_mirroring_uses_the_bounded_image_policy(): void {
		$cache = (string) file_get_contents( $this->plugin_root . '/includes/core/class-kalahamoon-product-cache.php' );

		$this->assertStringContainsString( 'Kalahamoon_Image_Policy::download_remote', $cache );
		$this->assertStringNotContainsString( 'download_url( $source_url', $cache );
	}

	public function test_price_comparison_filters_each_listing_before_rendering(): void {
		$render = (string) file_get_contents( $this->plugin_root . '/blocks/price-comparison/render.php' );

		$this->assertStringContainsString( 'Kalahamoon_Listings::normalize_public', $render );
	}

	public function test_comparison_rejects_more_than_four_submitted_products_instead_of_truncating_them(): void {
		$render = (string) file_get_contents( $this->plugin_root . '/blocks/product-comparison/render.php' );

		$this->assertStringContainsString( '$submitted_count = count( $ids );', $render );
		$this->assertStringContainsString( '$submitted_count <= 4', $render );
	}

	public function test_ai_comparison_renders_without_a_separate_editorial_approval_gate(): void {
		$block  = (string) file_get_contents( $this->plugin_root . '/blocks/ai-compare/block.json' );
		$editor = (string) file_get_contents( $this->plugin_root . '/blocks/ai-compare/index.js' );
		$render = (string) file_get_contents( $this->plugin_root . '/blocks/ai-compare/render.php' );

		$this->assertStringNotContainsString( '"reviewStatus"', $block );
		$this->assertStringNotContainsString( 'reviewStatus', $editor );
		$this->assertStringNotContainsString( 'reviewStatus', $render );
		$this->assertStringNotContainsString( "\$snapshot['price']", $render );
	}

	public function test_classic_lead_form_shortcode_preserves_intent_and_consent_contract(): void {
		$shortcodes = (string) file_get_contents( $this->plugin_root . '/includes/display/class-kalahamoon-shortcodes.php' );
		$render     = (string) file_get_contents( $this->plugin_root . '/blocks/lead-form/render.php' );
		$script     = (string) file_get_contents( $this->plugin_root . '/public/js/kalahamoon-forms.js' );

		$this->assertStringContainsString( "'intent'", $shortcodes );
		$this->assertStringContainsString( "'showSubject'", $shortcodes );
		$this->assertStringContainsString( "'consentText'", $shortcodes );
		$this->assertStringContainsString( "'consentVersion'", $shortcodes );
		$this->assertStringContainsString( 'data-reference-label', $render );
		$this->assertStringContainsString( 'Request reference: %s', $render );
		$this->assertStringContainsString( 'r.data.requestId', $script );
		$this->assertStringContainsString( "referenceLabel.replace('%s', requestId)", $script );
	}

	public function test_catalog_always_provides_a_level_two_heading_before_product_cards(): void {
		$render = (string) file_get_contents( $this->plugin_root . '/blocks/product-catalog/render.php' );

		$this->assertStringContainsString( "__( 'Verified product catalog', 'kalahamoon' )", $render );
		$this->assertStringContainsString( '<h2>', $render );
	}

	public function test_catalog_empty_state_keeps_the_public_projection_explicit(): void {
		$cache  = (string) file_get_contents( $this->plugin_root . '/includes/core/class-kalahamoon-product-cache.php' );
		$render = (string) file_get_contents( $this->plugin_root . '/blocks/product-catalog/render.php' );

		$this->assertStringContainsString( 'function public_ready_count', $cache );
		$this->assertStringContainsString( 'Kalahamoon_Product_Cache::public_ready_count()', $render );
		$this->assertStringContainsString( '$is_prelaunch', $render );
		$this->assertStringContainsString( 'kalahamoon-catalog__prelaunch', $render );
		$this->assertStringContainsString( 'Explore buying guides', $render );
		$this->assertStringContainsString( 'Read the magazine', $render );
	}

	public function test_comparison_does_not_direct_people_to_an_unavailable_catalog(): void {
		$render = (string) file_get_contents( $this->plugin_root . '/blocks/product-comparison/render.php' );

		$this->assertStringContainsString( 'Kalahamoon_Product_Cache::public_ready_count()', $render );
		$this->assertStringContainsString( 'Comparison will be available when', $render );
		$this->assertStringContainsString( 'Explore buying guides', $render );
	}

	public function test_favorites_preserve_svg_markup_and_expose_pressed_state(): void {
		$render = (string) file_get_contents( $this->plugin_root . '/blocks/product-catalog/render.php' );
		$script = (string) file_get_contents( $this->plugin_root . '/public/js/kalahamoon-click-tracker.js' );

		$this->assertStringContainsString( 'aria-pressed="false"', $render );
		$this->assertStringContainsString( 'data-label-remove', $render );
		$this->assertStringContainsString( "setAttribute('aria-pressed'", $script );
		$this->assertStringContainsString( "classList.toggle('is-active'", $script );
		$this->assertStringNotContainsString( "btn.textContent = '♥'", $script );
		$this->assertStringNotContainsString( "btn.textContent = '♡'", $script );
	}
}
