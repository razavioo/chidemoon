<?php
/** Server render for the Chidemoon Shop the Look block. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$image_id = absint( $attributes['imageId'] ?? 0 );
if ( $image_id <= 0 ) {
	return;
}

$image_alt = sanitize_text_field( (string) ( $attributes['imageAlt'] ?? get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) );
$caption   = sanitize_text_field( (string) ( $attributes['caption'] ?? '' ) );
$raw       = $attributes['hotspots'] ?? array();
$hotspots  = is_array( $raw ) ? array_values( array_filter( $raw, 'is_array' ) ) : array();
$instance  = wp_unique_id( 'chidemoon-look-' );
$products  = array();

foreach ( $hotspots as $index => $spot ) {
	$product_id = absint( $spot['productId'] ?? 0 );
	$x          = max( 0, min( 100, (float) ( $spot['x'] ?? 0 ) ) );
	$y          = max( 0, min( 100, (float) ( $spot['y'] ?? 0 ) ) );
	$hotspots[ $index ]['x'] = $x;
	$hotspots[ $index ]['y'] = $y;
	if ( $product_id <= 0 || isset( $products[ $product_id ] ) ) {
		continue;
	}
	$product = wc_get_product( $product_id );
	if ( ! $product instanceof WC_Product || ! Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) {
		continue;
	}
	$products[ $product_id ] = array(
		'id'       => $product_id,
		'title'    => $product->get_name(),
		'price'    => $product->get_price_html(),
		'image_id' => $product->get_image_id(),
		'url'      => Chidemoon_Core_Affiliate::tracking_url( $product_id ),
		'product'  => $product,
	);
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'chidemoon-shop-the-look', 'data-look-instance' => $instance ) );
?>
<figure <?php echo $wrapper; ?>>
	<div class="chidemoon-shop-the-look__canvas">
		<?php echo wp_get_attachment_image( $image_id, 'full', false, array( 'class' => 'chidemoon-shop-the-look__image', 'alt' => $image_alt, 'loading' => 'lazy', 'decoding' => 'async' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php foreach ( $hotspots as $index => $spot ) :
			$product_id = absint( $spot['productId'] ?? 0 );
			$product    = $products[ $product_id ] ?? null;
			if ( ! $product ) {
				continue;
			}
			$tooltip_id = $instance . '-product-' . $index;
			$label      = sanitize_text_field( (string) ( $spot['label'] ?? $product['title'] ) );
		?>
			<button type="button" class="chidemoon-shop-the-look__hotspot" style="left:<?php echo esc_attr( $spot['x'] ); ?>%;top:<?php echo esc_attr( $spot['y'] ); ?>%" aria-expanded="false" aria-controls="<?php echo esc_attr( $tooltip_id ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" data-tooltip="<?php echo esc_attr( $tooltip_id ); ?>">
				<span aria-hidden="true"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
			</button>
			<div id="<?php echo esc_attr( $tooltip_id ); ?>" class="chidemoon-shop-the-look__tooltip" hidden>
				<button type="button" class="chidemoon-shop-the-look__close" aria-label="<?php esc_attr_e( 'بستن', 'chidemoon-core' ); ?>">×</button>
				<?php if ( $product['image_id'] > 0 ) : ?>
					<?php echo wp_get_attachment_image( $product['image_id'], 'woocommerce_thumbnail', false, array( 'class' => 'chidemoon-shop-the-look__product-image', 'alt' => $product['title'], 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
				<div class="chidemoon-shop-the-look__tooltip-body">
					<h3><?php echo esc_html( $product['title'] ); ?></h3>
					<div class="chidemoon-shop-the-look__price"><?php echo wp_kses_post( $product['price'] ); ?></div>
					<a class="chidemoon-button" href="<?php echo esc_url( $product['url'] ); ?>" target="_blank" rel="nofollow sponsored noopener" data-product-id="<?php echo esc_attr( $product_id ); ?>"><?php esc_html_e( 'خرید از فروشگاه', 'chidemoon-core' ); ?></a>
					<?php echo Chidemoon_Core_Compare::control( $product['product'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php if ( $caption ) : ?><figcaption><?php echo esc_html( $caption ); ?></figcaption><?php endif; ?>
	<?php if ( ! empty( $products ) ) : ?>
		<ol class="chidemoon-shop-the-look__fallback" aria-label="<?php esc_attr_e( 'محصولات این تصویر', 'chidemoon-core' ); ?>">
		<?php foreach ( $products as $product ) : ?><li><span><?php echo esc_html( $product['title'] ); ?></span><span><?php echo wp_kses_post( $product['price'] ); ?></span><a href="<?php echo esc_url( $product['url'] ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php esc_html_e( 'خرید از فروشگاه', 'chidemoon-core' ); ?></a></li><?php endforeach; ?>
		</ol>
	<?php endif; ?>
</figure>
