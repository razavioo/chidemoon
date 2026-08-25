<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$pid = trim( (string) ( $attributes['productId'] ?? '' ) );
if ( '' === $pid ) {
	echo Kalahamoon_Placeholder::editor_hint(
		__( 'محصولی برای این دکمه انتخاب نشده', 'kalahamoon' ),
		__( 'از سایدبار بلاک، محصول مقصد را انتخاب کنید.', 'kalahamoon' )
	);
	return;
}

$product = Kalahamoon_Product_Cache::get_for_public_render( $pid );
if ( ! $product ) {
	echo Kalahamoon_Placeholder::product_not_found( $pid );
	return;
}

$custom_url  = trim( (string) ( $attributes['customUrl'] ?? '' ) );
$destination = ( '' !== $custom_url )
	? Kalahamoon_Link_Builder::resolve_direct_destination( $custom_url )
	: Kalahamoon_Link_Builder::resolve_product_destination( $product );
$url         = $destination['url'];
$link_attrs  = Kalahamoon_Link_Builder::public_link_attributes( $destination );

// No clickable destination (e.g. marketplace product synced without a public
// URL) → render nothing rather than a dead button that reloads the page.
if ( ! Kalahamoon_Link_Builder::is_clickable_url( $url ) ) {
	echo Kalahamoon_Placeholder::editor_hint(
		__( 'لینک مقصد در دسترس نیست', 'kalahamoon' ),
		__( 'برای این محصول هنوز لینک خرید/همکاری ثبت نشده است.', 'kalahamoon' )
	);
	return;
}

$text        = Kalahamoon_Link_Builder::public_cta_label( $attributes['text'] ?? '' );
$size        = (string) ( $attributes['size']         ?? 'medium' );
$show_price  = ! isset( $attributes['showPrice'] )    || ! empty( $attributes['showPrice'] );
$icon        = (string) ( $attributes['icon']         ?? 'none' );
$icon_pos    = (string) ( $attributes['iconPosition'] ?? 'start' );
$full_width  = ! empty( $attributes['fullWidth'] );

$price       = ( $show_price && $product['price'] > 0 )
	? Kalahamoon_RTL::format_price( $product['price'], $product['currency'] )
	: '';

// ── Tiny inline-SVG icon set. Stored as a local function table so there is no
// extra HTTP request and the icons inherit currentColor from the button text.
$icons = array(
	'cart'  => '<path d="M3 3h3l2.6 12.6a2 2 0 002 1.4h8.4a2 2 0 002-1.5L22 7H6"/><circle cx="10" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/>',
	'bolt'  => '<path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z"/>',
	'tag'   => '<path d="M20 13.5 13.5 20a1.5 1.5 0 01-2.1 0L3 11.6V4a1 1 0 011-1h7.6L20 11.4a1.5 1.5 0 010 2.1z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
	'arrow' => '<path d="M5 12h14M13 5l7 7-7 7"/>',
	'heart' => '<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 10-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>',
);

$icon_svg = '';
if ( isset( $icons[ $icon ] ) ) {
	$icon_svg = '<svg class="kalahamoon-cta-icon" viewBox="0 0 24 24" width="18" height="18" '
		. 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
		. 'aria-hidden="true">' . $icons[ $icon ] . '</svg>';
}

$classes = array(
	'kalahamoon-cta-button',
	'kalahamoon-cta-' . sanitize_html_class( $size ),
);
if ( $full_width ) {
	$classes[] = 'kalahamoon-cta-fullwidth';
}
if ( '' !== $icon_svg ) {
	$classes[] = 'kalahamoon-cta-has-icon';
	$classes[] = 'kalahamoon-cta-icon-' . sanitize_html_class( $icon_pos );
}

$wrapper_class = $full_width ? 'kalahamoon-cta-wrap kalahamoon-cta-wrap--full' : 'kalahamoon-cta-wrap';
?>

<div <?php echo get_block_wrapper_attributes( array( 'class' => $wrapper_class ) ); ?>>
	<a href="<?php echo esc_url( $url ); ?>"
		class="<?php echo esc_attr( trim( implode( ' ', $classes ) . ' ' . $link_attrs['class'] ) ); ?>"
		target="_blank"
		rel="<?php echo esc_attr( $link_attrs['rel'] ); ?>"
		data-product-id="<?php echo esc_attr( $pid ); ?>"
		data-link-id="<?php echo esc_attr( $link_attrs['linkId'] ); ?>"
		data-link-kind="<?php echo esc_attr( $link_attrs['kind'] ); ?>"
		data-block-type="cta-button">
		<?php if ( $icon_svg && 'start' === $icon_pos ) echo $icon_svg; // phpcs:ignore Kalahamoon.Escape -- local SVG whitelist above. ?>
		<span class="kalahamoon-cta-label"><?php echo esc_html( $text ); ?></span>
		<?php if ( $price ) : ?>
			<span class="kalahamoon-cta-price"><?php echo esc_html( $price ); ?></span>
		<?php endif; ?>
		<?php if ( $icon_svg && 'end' === $icon_pos ) echo $icon_svg; // phpcs:ignore Kalahamoon.Escape -- local SVG whitelist above. ?>
	</a>
</div>
