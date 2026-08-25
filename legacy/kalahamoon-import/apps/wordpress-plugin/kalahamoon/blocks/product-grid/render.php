<?php
/**
 * Server-side render for kalahamoon/product-grid block.
 *
 * Wrapped with get_block_wrapper_attributes() so block supports (color,
 * spacing, blockGap) land on the outer element. Inner rendering is still
 * delegated to the [kalahamoon_products] shortcode so block and shortcode stay
 * pixel-identical. Empty results render a graceful empty-state panel
 * instead of producing a silent empty div.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$ids_str  = (string) ( $attributes['productIds'] ?? '' );
$category = (string) ( $attributes['category']   ?? '' );
$columns  = max( 2, min( 4, (int) ( $attributes['columns'] ?? 3 ) ) );
$limit    = max( 1, min( 12, (int) ( $attributes['limit'] ?? 6 ) ) );
$ranked   = ! empty( $attributes['ranked'] );
$layout   = sanitize_key( (string) ( $attributes['layout'] ?? 'grid' ) );
if ( ! in_array( $layout, array( 'grid', 'masonry', 'list', 'carousel' ), true ) ) {
	$layout = 'grid';
}

$order_by = sanitize_key( (string) ( $attributes['orderBy'] ?? 'newest' ) );
if ( ! in_array( $order_by, array( 'newest', 'oldest', 'price_asc', 'price_desc', 'title', 'random' ), true ) ) {
	$order_by = 'newest';
}

// Quick pre-flight: if no IDs and no category, signal to editors instead of
// rendering an empty grid.
if ( '' === trim( $ids_str ) && '' === trim( $category ) ) {
	echo Kalahamoon_Placeholder::editor_hint(
		__( 'شبکه محصول خالی است', 'kalahamoon' ),
		__( 'از سایدبار بلاک، محصول یا دسته‌بندی را انتخاب کنید.', 'kalahamoon' )
	);
	return;
}

$wrapper_attrs = get_block_wrapper_attributes( array(
	'class'       => 'kalahamoon-product-grid-wrap',
	'data-layout' => $layout,
	'data-order'  => $order_by,
) );

$inner = do_shortcode( sprintf(
	'[kalahamoon_products ids="%s" category="%s" columns="%d" limit="%d" ranked="%s" orderby="%s"]',
	esc_attr( $ids_str ),
	esc_attr( $category ),
	$columns,
	$limit,
	$ranked ? '1' : '0',
	esc_attr( $order_by )
) );

// The shortcode already emits its own empty-state when nothing matched, so we
// just forward the result. Still, wrap it so block-level supports (color,
// spacing, border, is-style-*) apply consistently.
echo '<div ' . $wrapper_attrs . '>' . $inner . '</div>';
