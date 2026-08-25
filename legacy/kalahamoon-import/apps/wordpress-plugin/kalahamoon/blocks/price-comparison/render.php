<?php
/**
 * Server-side render for kalahamoon/price-comparison.
 *
 * Renders every marketplace listing for a product as a buy-box, cheapest
 * first, with the lowest price highlighted. Uses the synced `listings[]` data.
 *
 * @var array $attributes
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$product_id = trim( (string) ( $attributes['productId'] ?? '' ) );
if ( '' === $product_id ) {
	echo Kalahamoon_Placeholder::editor_hint(
		__( 'محصولی انتخاب نشده', 'kalahamoon' ),
		__( 'یک محصول انتخاب کنید تا قیمت فروشندگان مختلف مقایسه شود.', 'kalahamoon' )
	);
	return;
}

$product = Kalahamoon_Product_Cache::get_for_public_render( $product_id );
if ( ! $product ) {
	echo Kalahamoon_Placeholder::product_not_found( $product_id );
	return;
}

$rows = Kalahamoon_Listings::normalize_public( $product );
if ( empty( $rows ) ) {
	echo Kalahamoon_Placeholder::empty_state(
		__( 'قیمتی برای مقایسه موجود نیست', 'kalahamoon' ),
		__( 'این محصول فقط یک فروشنده دارد یا قیمت‌ها هنوز همگام‌سازی نشده‌اند.', 'kalahamoon' ),
		'tag'
	);
	return;
}

$heading  = trim( (string) ( $attributes['heading'] ?? '' ) );
$show_stock = ! isset( $attributes['showStock'] ) || ! empty( $attributes['showStock'] );
$max_rows = max( 1, (int) ( $attributes['maxRows'] ?? 8 ) );
$rows     = array_slice( $rows, 0, $max_rows );

$wrapper = get_block_wrapper_attributes( array( 'class' => 'kalahamoon-price-comparison' ) );
?>
<div <?php echo $wrapper; ?> dir="<?php echo esc_attr( Kalahamoon_RTL::direction() ); ?>">
	<?php if ( '' !== $heading ) : ?>
		<h3 class="kalahamoon-price-comparison__heading"><?php echo esc_html( $heading ); ?></h3>
	<?php endif; ?>

	<ul class="kalahamoon-price-comparison__list" role="list">
		<?php foreach ( $rows as $row ) :
			$label       = Kalahamoon_RTL::platform_label( $row['platform'] );
			$destination = Kalahamoon_Link_Builder::resolve_direct_destination( $row['url'] );
			$link_attrs  = Kalahamoon_Link_Builder::public_link_attributes( $destination );
			$buy         = Kalahamoon_Link_Builder::is_clickable_url( $destination['url'] );
		?>
			<li class="kalahamoon-price-comparison__row<?php echo $row['cheapest'] ? ' is-cheapest' : ''; ?>">
				<span class="kalahamoon-price-comparison__seller">
					<?php if ( '' !== $label ) : ?>
						<span class="kalahamoon-marketplace-badge kalahamoon-badge-<?php echo esc_attr( $row['platform'] ); ?>"><?php echo esc_html( $label ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $row['seller'] ) : ?>
						<span class="kalahamoon-price-comparison__seller-name" dir="auto"><?php echo esc_html( $row['seller'] ); ?></span>
					<?php endif; ?>
					<?php if ( $row['cheapest'] ) : ?>
						<span class="kalahamoon-price-comparison__best" role="img" aria-label="<?php esc_attr_e( 'کمترین قیمت', 'kalahamoon' ); ?>"><?php esc_html_e( 'بهترین قیمت', 'kalahamoon' ); ?></span>
					<?php endif; ?>
					<?php if ( $show_stock ) : ?>
						<span class="kalahamoon-stock-badge <?php echo $row['inStock'] ? 'kalahamoon-stock-in' : 'kalahamoon-stock-out'; ?>">
							<?php echo esc_html( $row['inStock'] ? __( 'موجود', 'kalahamoon' ) : __( 'ناموجود', 'kalahamoon' ) ); ?>
						</span>
					<?php endif; ?>
				</span>
				<span class="kalahamoon-price-comparison__price"><?php echo esc_html( Kalahamoon_RTL::format_price( $row['price'], $row['currency'] ) ); ?></span>
				<?php if ( $buy ) : ?>
					<a class="kalahamoon-price-comparison__buy <?php echo esc_attr( $link_attrs['class'] ); ?>"
						href="<?php echo esc_url( $destination['url'] ); ?>"
						target="_blank" rel="<?php echo esc_attr( $link_attrs['rel'] ); ?>"
						data-product-id="<?php echo esc_attr( $product['id'] ); ?>"
						data-link-id="<?php echo esc_attr( $link_attrs['linkId'] ); ?>"
						data-link-kind="<?php echo esc_attr( $link_attrs['kind'] ); ?>"
						data-block-type="price-comparison">
						<?php esc_html_e( 'View product', 'kalahamoon' ); ?>
					</a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
