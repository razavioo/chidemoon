<?php
/**
 * Server-side render for kalahamoon/shop-the-look block.
 *
 * Display styles (attribute `displayStyle`):
 *   - hotspots : dots overlaid on the image, product cards in tap/hover tooltips (view.js).
 *   - strip    : image with dots, plus a horizontal product strip/carousel below.
 *   - side     : image on one side, a vertical product list on the other.
 *   - list     : image, then a numbered product list underneath.
 *
 * For strip/side/list the product cards are SSR'd and always visible (no JS needed);
 * hotspots keeps the interactive tooltip behavior.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$image_url = $attributes['imageUrl'] ?? '';
$image_alt = $attributes['imageAlt'] ?? '';
$caption   = $attributes['caption'] ?? '';
$style     = (string) ( $attributes['displayStyle'] ?? 'hotspots' );
if ( ! in_array( $style, array( 'hotspots', 'strip', 'side', 'list' ), true ) ) {
	$style = 'hotspots';
}

if ( empty( $image_url ) ) {
	return;
}

$decoded_hotspots = json_decode( $attributes['hotspots'] ?? '[]', true );
$hotspots         = is_array( $decoded_hotspots )
	? array_values( array_filter( $decoded_hotspots, 'is_array' ) )
	: array();
$instance_id      = wp_unique_id( 'kalahamoon-stl-' );

// Preload product data for all hotspots with a product ID.
$products = array();
foreach ( $hotspots as $hs ) {
	$pid = $hs['productId'] ?? '';
	if ( $pid && ! isset( $products[ $pid ] ) ) {
		$p = Kalahamoon_Product_Cache::get_for_public_render( $pid );
		if ( $p ) {
			$products[ $pid ] = $p;
		}
	}
}

$show_dots  = in_array( $style, array( 'hotspots', 'strip', 'side' ), true );
$has_panel  = in_array( $style, array( 'strip', 'side', 'list' ), true );

/**
 * Render a compact, always-visible product card for the strip/side/list panels.
 */
if ( ! function_exists( 'kalahamoon_stl_render_card' ) ) :
function kalahamoon_stl_render_card( int $number, array $product, string $style ): string {
	$destination   = Kalahamoon_Link_Builder::resolve_product_destination( $product );
	$affiliate_url = $destination['url'];
	$link_attrs    = Kalahamoon_Link_Builder::public_link_attributes( $destination );
	$has_link      = Kalahamoon_Link_Builder::is_clickable_url( $affiliate_url );

	ob_start();
	?>
	<div class="kalahamoon-stl-card">
		<span class="kalahamoon-stl-card-num" aria-hidden="true"><?php echo esc_html( Kalahamoon_RTL::to_persian_digits( (string) $number ) ); ?></span>
		<?php if ( ! empty( $product['imageUrl'] ) ) : ?>
			<img class="kalahamoon-stl-card-thumb" src="<?php echo esc_url( $product['imageUrl'] ); ?>" alt="<?php echo esc_attr( $product['title'] ); ?>" loading="lazy" decoding="async" onerror="this.hidden=true;this.nextElementSibling.hidden=false" />
			<div class="kalahamoon-stl-card-thumb kalahamoon-stl-card-thumb--placeholder" aria-hidden="true" hidden>
				<?php echo Kalahamoon_Placeholder::image_fallback_svg( strtolower( (string) ( $product['platform'] ?? '' ) ), (string) ( $product['title'] ?? '' ) ); ?>
			</div>
		<?php else : ?>
			<div class="kalahamoon-stl-card-thumb kalahamoon-stl-card-thumb--placeholder" aria-hidden="true">
				<?php echo Kalahamoon_Placeholder::image_fallback_svg( strtolower( (string) ( $product['platform'] ?? '' ) ), (string) ( $product['title'] ?? '' ) ); ?>
			</div>
		<?php endif; ?>
		<div class="kalahamoon-stl-card-body">
			<p class="kalahamoon-stl-card-title"><?php echo esc_html( $product['title'] ); ?></p>
			<?php if ( ! empty( $product['price'] ) && $product['price'] > 0 ) : ?>
				<div class="kalahamoon-stl-card-price"><?php echo esc_html( Kalahamoon_RTL::format_price( $product['price'], $product['currency'] ) ); ?></div>
			<?php endif; ?>
			<?php if ( $has_link ) : ?>
				<a href="<?php echo esc_url( $affiliate_url ); ?>" class="kalahamoon-cta-button <?php echo esc_attr( $link_attrs['class'] ); ?> kalahamoon-stl-card-cta" target="_blank" rel="<?php echo esc_attr( $link_attrs['rel'] ); ?>" data-product-id="<?php echo esc_attr( $product['id'] ); ?>" data-link-id="<?php echo esc_attr( $link_attrs['linkId'] ); ?>" data-link-kind="<?php echo esc_attr( $link_attrs['kind'] ); ?>" data-block-type="shop-the-look" data-kalahamoon-event="destination_click">
						<?php esc_html_e( 'View product', 'kalahamoon' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}
endif;

$wrapper_attrs = get_block_wrapper_attributes( array(
	'class'              => 'kalahamoon-shop-the-look kalahamoon-stl-style-' . sanitize_html_class( $style ),
	'data-display-style' => $style,
) );
?>

<figure <?php echo $wrapper_attrs; ?> data-hotspots="<?php echo esc_attr( wp_json_encode( $hotspots ) ); ?>">

	<div class="kalahamoon-stl-layout">

		<div class="kalahamoon-stl-canvas">
			<img class="kalahamoon-stl-image"
				src="<?php echo esc_url( $image_url ); ?>"
				alt="<?php echo esc_attr( $image_alt ); ?>"
				loading="lazy" decoding="async"
				onerror="this.hidden=true;this.nextElementSibling.hidden=false" />
			<div class="kalahamoon-stl-image kalahamoon-stl-image--fallback" aria-hidden="true" hidden>
				<?php echo Kalahamoon_Placeholder::image_fallback_svg( '', (string) $image_alt ); ?>
			</div>

			<?php if ( $show_dots ) : ?>
				<?php foreach ( $hotspots as $idx => $hs ) :
					$dot_style_raw = (string) ( $hs['style'] ?? 'dot' );
					$dot_style = in_array( $dot_style_raw, array( 'dot', 'number', 'plus' ), true ) ? $dot_style_raw : 'dot';
					$pid       = $hs['productId'] ?? '';
					$x         = max( 0.0, min( 100.0, isset( $hs['x'] ) ? (float) $hs['x'] : 0.0 ) );
					$y         = max( 0.0, min( 100.0, isset( $hs['y'] ) ? (float) $hs['y'] : 0.0 ) );
					$product   = $pid ? ( $products[ $pid ] ?? null ) : null;
					$is_interactive = 'hotspots' === $style && $product;
					$dot_label = ( 'number' === $dot_style ) ? ( $idx + 1 ) : ( 'plus' === $dot_style ? '+' : '' );
					$tooltip_id = $instance_id . '-tooltip-' . $idx;
				?>
					<<?php echo $is_interactive ? 'button' : 'span'; ?>
						<?php if ( $is_interactive ) : ?>type="button"<?php endif; ?>
						class="kalahamoon-stl-dot kalahamoon-stl-dot--<?php echo esc_attr( $dot_style ); ?><?php echo $product ? '' : ' kalahamoon-stl-dot--no-product'; ?>"
						style="inset-inline-start:<?php echo esc_attr( $x ); ?>%;inset-block-start:<?php echo esc_attr( $y ); ?>%"
						<?php if ( $is_interactive ) : ?>aria-expanded="false" aria-controls="<?php echo esc_attr( $tooltip_id ); ?>"<?php else : ?>aria-hidden="true"<?php endif; ?>
						data-idx="<?php echo esc_attr( $idx ); ?>"
					<?php if ( $is_interactive ) : ?>data-kalahamoon-event="hotspot_open"<?php endif; ?>
						<?php if ( $is_interactive ) : ?>aria-label="<?php echo esc_attr( sprintf( __( 'Product: %s', 'kalahamoon' ), $product['title'] ) ); ?>"<?php endif; ?>
					><?php echo esc_html( $dot_label ); ?></<?php echo $is_interactive ? 'button' : 'span'; ?>>

					<?php
					// Interactive tooltip cards only for the pure hotspots style.
					if ( 'hotspots' === $style && $product ) :
						$destination   = Kalahamoon_Link_Builder::resolve_product_destination( $product );
						$affiliate_url = $destination['url'];
						$link_attrs    = Kalahamoon_Link_Builder::public_link_attributes( $destination );
						$has_image     = ! empty( $product['imageUrl'] );
					?>
					<div id="<?php echo esc_attr( $tooltip_id ); ?>"
						class="kalahamoon-stl-tooltip"
						role="group"
						aria-label="<?php echo esc_attr( $product['title'] ); ?>"
						data-idx="<?php echo esc_attr( $idx ); ?>"
						hidden>

						<button type="button" class="kalahamoon-stl-tp-close" aria-label="<?php esc_attr_e( 'بستن', 'kalahamoon' ); ?>">×</button>

						<?php if ( $has_image ) : ?>
						<div class="kalahamoon-stl-tp-image-wrap">
							<img class="kalahamoon-stl-tp-thumb"
								src="<?php echo esc_url( $product['imageUrl'] ); ?>"
								alt="<?php echo esc_attr( $product['title'] ); ?>"
								loading="lazy" decoding="async"
								onerror="this.hidden=true;this.nextElementSibling.hidden=false" />
							<div class="kalahamoon-stl-tp-thumb kalahamoon-stl-tp-thumb--placeholder" aria-hidden="true" hidden>
								<?php echo Kalahamoon_Placeholder::image_fallback_svg( strtolower( (string) ( $product['platform'] ?? '' ) ), (string) ( $product['title'] ?? '' ) ); ?>
							</div>
						</div>
						<?php endif; ?>

						<div class="kalahamoon-stl-tp-body">
							<p class="kalahamoon-stl-tp-title"><?php echo esc_html( $product['title'] ); ?></p>
							<?php if ( ! empty( $product['price'] ) && $product['price'] > 0 ) : ?>
								<div class="kalahamoon-stl-tp-price">
									<?php echo esc_html( Kalahamoon_RTL::format_price( $product['price'], $product['currency'] ) ); ?>
								</div>
							<?php endif; ?>
							<?php if ( Kalahamoon_Link_Builder::is_clickable_url( $affiliate_url ) ) : ?>
							<a href="<?php echo esc_url( $affiliate_url ); ?>"
								class="kalahamoon-stl-tp-cta <?php echo esc_attr( $link_attrs['class'] ); ?>"
								target="_blank"
								rel="<?php echo esc_attr( $link_attrs['rel'] ); ?>"
								data-product-id="<?php echo esc_attr( $product['id'] ); ?>"
								data-link-id="<?php echo esc_attr( $link_attrs['linkId'] ); ?>"
								data-link-kind="<?php echo esc_attr( $link_attrs['kind'] ); ?>"
								data-block-type="shop-the-look"
							data-kalahamoon-event="destination_click">
									<?php esc_html_e( 'View product', 'kalahamoon' ); ?>
								<svg viewBox="0 0 20 20" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 10h10M10 5l5 5-5 5"/></svg>
							</a>
							<?php endif; ?>
						</div>
					</div>
					<?php endif; ?>

				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<?php if ( $has_panel && ! empty( $hotspots ) ) : ?>
			<div class="kalahamoon-stl-products">
				<?php
				$number = 0;
				foreach ( $hotspots as $hs ) :
					$pid     = $hs['productId'] ?? '';
					$product = $pid ? ( $products[ $pid ] ?? null ) : null;
					if ( ! $product ) {
						continue;
					}
					$number++;
					echo kalahamoon_stl_render_card( $number, $product, $style );
				endforeach;
				?>
			</div>
		<?php endif; ?>

	</div>

	<?php if ( $caption ) : ?>
		<figcaption class="kalahamoon-stl-caption"><?php echo esc_html( $caption ); ?></figcaption>
	<?php endif; ?>

</figure>
