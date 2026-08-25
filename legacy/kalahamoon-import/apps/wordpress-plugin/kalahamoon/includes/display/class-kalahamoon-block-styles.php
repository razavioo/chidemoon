<?php
/**
 * Register block style variants for Kalahamoon blocks.
 *
 * Variants surface as the "Styles" switcher in the block inspector. Each
 * variant attaches an `is-style-<name>` class to the wrapper via
 * get_block_wrapper_attributes(), which is handled by the block's render.php.
 *
 * Themes can register additional styles on the `kalahamoon_register_block_styles`
 * action — it fires after the plugin's own defaults.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Block_Styles {

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ), 12 );
	}

	public static function register(): void {
		if ( ! function_exists( 'register_block_style' ) ) {
			return;
		}

		// ─── Product Box — 5 variants ────────────────────────────────────────
		register_block_style( 'kalahamoon/product-box', array(
			'name'       => 'default',
			'label'      => __( 'Default', 'kalahamoon' ),
			'is_default' => true,
		) );
		register_block_style( 'kalahamoon/product-box', array(
			'name'  => 'outlined',
			'label' => __( 'Outlined', 'kalahamoon' ),
		) );
		register_block_style( 'kalahamoon/product-box', array(
			'name'  => 'minimal',
			'label' => __( 'Minimal', 'kalahamoon' ),
		) );
		register_block_style( 'kalahamoon/product-box', array(
			'name'  => 'featured',
			'label' => __( 'Featured', 'kalahamoon' ),
		) );
		register_block_style( 'kalahamoon/product-box', array(
			'name'  => 'compact',
			'label' => __( 'Compact', 'kalahamoon' ),
		) );

		// ─── CTA Button — 4 variants ────────────────────────────────────────
		register_block_style( 'kalahamoon/cta-button', array(
			'name'       => 'solid',
			'label'      => __( 'Solid', 'kalahamoon' ),
			'is_default' => true,
		) );
		register_block_style( 'kalahamoon/cta-button', array(
			'name'  => 'outline',
			'label' => __( 'Outline', 'kalahamoon' ),
		) );
		register_block_style( 'kalahamoon/cta-button', array(
			'name'  => 'ghost',
			'label' => __( 'Ghost', 'kalahamoon' ),
		) );
		register_block_style( 'kalahamoon/cta-button', array(
			'name'  => 'link',
			'label' => __( 'Link', 'kalahamoon' ),
		) );

		// ─── Comparison Table — 3 variants ──────────────────────────────────
		register_block_style( 'kalahamoon/comparison-table', array(
			'name'       => 'striped',
			'label'      => __( 'Striped', 'kalahamoon' ),
			'is_default' => true,
		) );
		register_block_style( 'kalahamoon/comparison-table', array(
			'name'  => 'bordered',
			'label' => __( 'Bordered', 'kalahamoon' ),
		) );
		register_block_style( 'kalahamoon/comparison-table', array(
			'name'  => 'minimal',
			'label' => __( 'Minimal', 'kalahamoon' ),
		) );

		// ─── FAQ — 2 variants ───────────────────────────────────────────────
		register_block_style( 'kalahamoon/faq', array(
			'name'       => 'card',
			'label'      => __( 'Card', 'kalahamoon' ),
			'is_default' => true,
		) );
		register_block_style( 'kalahamoon/faq', array(
			'name'  => 'plain',
			'label' => __( 'Plain', 'kalahamoon' ),
		) );

		// ─── Shop the Look — 2 variants ─────────────────────────────────────
		register_block_style( 'kalahamoon/shop-the-look', array(
			'name'       => 'clean',
			'label'      => __( 'Clean', 'kalahamoon' ),
			'is_default' => true,
		) );
		register_block_style( 'kalahamoon/shop-the-look', array(
			'name'  => 'elevated',
			'label' => __( 'Elevated', 'kalahamoon' ),
		) );

		// ─── Pros & Cons — 3 variants ───────────────────────────────────────
		register_block_style( 'kalahamoon/pros-cons', array(
			'name'       => 'cards',
			'label'      => __( 'Cards', 'kalahamoon' ),
			'is_default' => true,
		) );
		register_block_style( 'kalahamoon/pros-cons', array(
			'name'  => 'columns',
			'label' => __( 'Columns', 'kalahamoon' ),
		) );
		register_block_style( 'kalahamoon/pros-cons', array(
			'name'  => 'stacked',
			'label' => __( 'Stacked', 'kalahamoon' ),
		) );

		/**
		 * Fires after the plugin registers its default block styles. Themes
		 * and integrations can hook here to add their own variants via
		 * register_block_style().
		 */
		do_action( 'kalahamoon_register_block_styles' );
	}
}
