<?php
/**
 * Server-side render for kalahamoon/product-box block.
 *
 * Handles edge cases centrally:
 *  - missing product → editor-visible hint, zero output on the front end
 *  - missing image   → branded SVG placeholder
 *  - price 0/missing → "contact for details" chip
 *  - all visual presets (aspect ratio, hover, heading level, variant badge)
 *    are driven by block attributes so authors can reshape the card without
 *    touching CSS.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (empty for dynamic blocks).
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$product_id = trim( (string) ( $attributes['productId'] ?? '' ) );

if ( '' === $product_id ) {
	echo Kalahamoon_Placeholder::editor_hint(
		__( 'محصولی انتخاب نشده', 'kalahamoon' ),
		__( 'از سایدبار بلاک یک محصول را انتخاب کنید.', 'kalahamoon' )
	);
	return;
}

$product = Kalahamoon_Product_Cache::get_for_public_render( $product_id );
if ( ! $product ) {
	echo Kalahamoon_Placeholder::product_not_found( $product_id );
	return;
}

$layout         = (string) ( $attributes['layout'] ?? 'vertical' );
$aspect_ratio   = (string) ( $attributes['imageAspectRatio'] ?? '1/1' );
$hover_effect   = (string) ( $attributes['hoverEffect']      ?? 'lift' );
$heading_level  = (int)    ( $attributes['headingLevel']     ?? 3 );
$variant        = (string) ( $attributes['variant']          ?? '' );
$show_title     = ! isset( $attributes['showTitle'] )           || ! empty( $attributes['showTitle'] );
$show_price     = ! isset( $attributes['showPrice'] )           || ! empty( $attributes['showPrice'] );
$show_old       = ! isset( $attributes['showOldPrice'] )        || ! empty( $attributes['showOldPrice'] );
$show_badge     = ! isset( $attributes['showMarketplaceBadge'] ) || ! empty( $attributes['showMarketplaceBadge'] );
$show_cta       = ! isset( $attributes['showCta'] )             || ! empty( $attributes['showCta'] );
$show_stock     = ! empty( $attributes['showStock'] );
$cta_text       = Kalahamoon_Link_Builder::public_cta_label( $attributes['ctaText'] ?? '' );
$track_recent   = ! isset( $attributes['trackRecent'] )          || ! empty( $attributes['trackRecent'] );

// Clamp heading level to h2…h6 range.
$heading_level = max( 2, min( 6, $heading_level ) );
$heading_tag   = 'h' . $heading_level;

$destination   = Kalahamoon_Link_Builder::resolve_product_destination( $product );
$affiliate_url = $destination['url'];
$link_attrs    = Kalahamoon_Link_Builder::public_link_attributes( $destination );
$platform      = strtolower( (string) ( $product['platform'] ?? 'bakalahamoon' ) );

$variant_labels = array(
	'bestseller'  => __( 'پرفروش', 'kalahamoon' ),
	'on-sale'     => __( 'تخفیف', 'kalahamoon' ),
	'new-arrival' => __( 'جدید', 'kalahamoon' ),
);

// Normalize aspect ratio for CSS. Allow 1/1, 4/3, 3/4, 16/9, auto.
$aspect_map = array(
	'1/1'   => '1 / 1',
	'4/3'   => '4 / 3',
	'3/4'   => '3 / 4',
	'16/9'  => '16 / 9',
	'auto'  => 'auto',
);
$aspect_css = $aspect_map[ $aspect_ratio ] ?? '1 / 1';

$wrapper_classes = array(
	'kalahamoon-product-card',
	'kalahamoon-layout-' . sanitize_html_class( $layout ),
	'kalahamoon-hover-' . sanitize_html_class( $hover_effect ),
);

$wrapper_attrs = get_block_wrapper_attributes( array(
	'class'              => implode( ' ', $wrapper_classes ),
	'data-product-id'    => $product['id'],
	'data-track-recent'  => $track_recent ? '1' : '0',
	'data-variant'       => $variant,
	'data-variant-label' => isset( $variant_labels[ $variant ] ) ? $variant_labels[ $variant ] : '',
	'style'              => '--kalahamoon-image-aspect:' . $aspect_css . ';',
) );
?>

<div <?php echo $wrapper_attrs; ?>>

	<?php echo Kalahamoon_Placeholder::image( $product ); ?>

	<div class="kalahamoon-product-info">

		<?php if ( $show_title && ! empty( $product['title'] ) ) : ?>
			<<?php echo esc_html( $heading_tag ); ?> class="kalahamoon-product-title" title="<?php echo esc_attr( $product['title'] ); ?>">
				<?php echo esc_html( $product['title'] ); ?>
			</<?php echo esc_html( $heading_tag ); ?>>
		<?php endif; ?>

		<?php if ( $show_price ) {
			echo Kalahamoon_Placeholder::price( $product, $show_old );
		} ?>

		<?php if ( $show_badge && '' !== $platform ) : ?>
			<span class="kalahamoon-marketplace-badge kalahamoon-badge-<?php echo esc_attr( $platform ); ?>">
				<?php echo esc_html( Kalahamoon_RTL::platform_label( $platform ) ); ?>
			</span>
		<?php endif; ?>

		<?php if ( $show_stock ) :
			$in_stock = (int) ( $product['inventory'] ?? 0 ) > 0;
		?>
			<span class="kalahamoon-stock-badge <?php echo $in_stock ? 'kalahamoon-stock-in' : 'kalahamoon-stock-out'; ?>"
				role="status">
				<?php echo esc_html( $in_stock ? __( 'موجود', 'kalahamoon' ) : __( 'ناموجود', 'kalahamoon' ) ); ?>
			</span>
		<?php endif; ?>

		<?php if ( $show_cta && '' !== $affiliate_url && '#' !== $affiliate_url ) : ?>
			<a href="<?php echo esc_url( $affiliate_url ); ?>"
				class="kalahamoon-cta-button <?php echo esc_attr( $link_attrs['class'] ); ?>"
				target="_blank"
				rel="<?php echo esc_attr( $link_attrs['rel'] ); ?>"
				data-product-id="<?php echo esc_attr( $product['id'] ); ?>"
				data-link-id="<?php echo esc_attr( $link_attrs['linkId'] ); ?>"
				data-link-kind="<?php echo esc_attr( $link_attrs['kind'] ); ?>"
				data-block-type="product-box">
				<?php echo esc_html( $cta_text ); ?>
			</a>
		<?php endif; ?>

	</div>
</div>
