<?php
/**
 * Server-side render for kalahamoon/pros-cons block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$pros_raw   = $attributes['pros'] ?? '';
$cons_raw   = $attributes['cons'] ?? '';
$heading    = $attributes['heading'] ?? '';
$product_id = $attributes['productId'] ?? '';
$show_cta   = $attributes['showCta'] ?? true;
$cta_text   = Kalahamoon_Link_Builder::public_cta_label( $attributes['ctaText'] ?? '' );
$pros_label = $attributes['prosLabel'] ?? __( 'نقاط مثبت', 'kalahamoon' );
$cons_label = $attributes['consLabel'] ?? __( 'نقاط منفی', 'kalahamoon' );

// Support legacy pipe-separated and newline-separated storage (editor saves \n).
// Each entry may be "icon::label" (new) or just "label" (legacy).
if ( ! function_exists( 'kalahamoon_parse_pc_items' ) ) :
function kalahamoon_parse_pc_items( string $raw ): array {
	$items = array();
	foreach ( preg_split( '/[|\n]/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) continue;
		$sep = strpos( $line, '::' );
		if ( false !== $sep ) {
			$items[] = array( 'icon' => substr( $line, 0, $sep ), 'label' => substr( $line, $sep + 2 ) );
		} else {
			$items[] = array( 'icon' => '', 'label' => $line );
		}
	}
	return $items;
}
endif;

$pros = kalahamoon_parse_pc_items( $pros_raw );
$cons = kalahamoon_parse_pc_items( $cons_raw );

if ( empty( $pros ) && empty( $cons ) ) {
	return;
}

$product = $product_id ? Kalahamoon_Product_Cache::get_for_public_render( $product_id ) : null;

// Collapse to a single column when only one side has content. This lets
// the CSS serve a deliberate 1fr grid instead of leaving a half-empty row,
// and keeps screen readers from announcing an empty column.
// $pros/$cons are arrays of ['icon'=>,'label'=>] entries.
$col_mode = ( ! empty( $pros ) && ! empty( $cons ) ) ? 'dual' : 'single';

$wrapper_attrs = get_block_wrapper_attributes( array(
	'class'         => 'kalahamoon-pros-cons-card',
	'data-col-mode' => $col_mode,
) );
?>

<div <?php echo $wrapper_attrs; ?>>

	<?php if ( $product ) : ?>
		<div class="kalahamoon-pc-product-header">
			<?php if ( ! empty( $product['imageUrl'] ) ) : ?>
				<img class="kalahamoon-pc-product-thumb"
					src="<?php echo esc_url( $product['imageUrl'] ); ?>"
					alt="<?php echo esc_attr( $product['title'] ); ?>"
					loading="lazy" decoding="async"
					onerror="this.hidden=true;this.nextElementSibling.hidden=false" />
				<div class="kalahamoon-pc-product-thumb kalahamoon-pc-product-thumb--placeholder" aria-hidden="true" hidden>
					<?php echo Kalahamoon_Placeholder::image_fallback_svg(
						strtolower( (string) ( $product['platform'] ?? '' ) ),
						(string) ( $product['title'] ?? '' )
					); ?>
				</div>
			<?php else : ?>
				<div class="kalahamoon-pc-product-thumb kalahamoon-pc-product-thumb--placeholder">
					<?php echo Kalahamoon_Placeholder::image_fallback_svg(
						strtolower( (string) ( $product['platform'] ?? '' ) ),
						(string) ( $product['title'] ?? '' )
					); ?>
				</div>
			<?php endif; ?>
			<div class="kalahamoon-pc-product-title">
				<?php if ( $heading ) : ?>
					<h3 class="kalahamoon-pc-heading"><?php echo esc_html( $heading ); ?></h3>
				<?php else : ?>
					<h3 class="kalahamoon-pc-heading"><?php echo esc_html( $product['title'] ); ?></h3>
				<?php endif; ?>
				<?php if ( ! empty( $product['price'] ) && $product['price'] > 0 ) : ?>
					<div class="kalahamoon-pc-price">
						<?php echo esc_html( Kalahamoon_RTL::format_price( $product['price'], $product['currency'] ) ); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php elseif ( $heading ) : ?>
		<h3 class="kalahamoon-pc-heading"><?php echo esc_html( $heading ); ?></h3>
	<?php endif; ?>

	<div class="kalahamoon-pc-columns">

		<?php if ( ! empty( $pros ) ) : ?>
		<div class="kalahamoon-pc-col kalahamoon-pc-pros">
			<div class="kalahamoon-pc-col-label">
				<span class="kalahamoon-pc-icon" aria-hidden="true">✅</span>
				<?php echo esc_html( $pros_label ); ?>
			</div>
			<ul class="kalahamoon-pc-list">
				<?php foreach ( $pros as $entry ) :
					$icon  = $entry['icon'] ?: '✅';
					$label = $entry['label'];
				?>
					<li class="kalahamoon-pc-item">
						<span class="kalahamoon-pc-item-icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
						<?php echo esc_html( $label ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $cons ) ) : ?>
		<div class="kalahamoon-pc-col kalahamoon-pc-cons">
			<div class="kalahamoon-pc-col-label">
				<span class="kalahamoon-pc-icon" aria-hidden="true">❌</span>
				<?php echo esc_html( $cons_label ); ?>
			</div>
			<ul class="kalahamoon-pc-list">
				<?php foreach ( $cons as $entry ) :
					$icon  = $entry['icon'] ?: '❌';
					$label = $entry['label'];
				?>
					<li class="kalahamoon-pc-item">
						<span class="kalahamoon-pc-item-icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
						<?php echo esc_html( $label ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>

	</div>

	<?php if ( $product && $show_cta ) :
		$destination   = Kalahamoon_Link_Builder::resolve_product_destination( $product );
		$affiliate_url = $destination['url'];
		$link_attrs    = Kalahamoon_Link_Builder::public_link_attributes( $destination );
	?>
		<?php if ( Kalahamoon_Link_Builder::is_clickable_url( $affiliate_url ) ) : ?>
		<div class="kalahamoon-pc-footer">
			<a href="<?php echo esc_url( $affiliate_url ); ?>"
				class="kalahamoon-cta-button <?php echo esc_attr( $link_attrs['class'] ); ?>"
				target="_blank"
				rel="<?php echo esc_attr( $link_attrs['rel'] ); ?>"
				data-product-id="<?php echo esc_attr( $product['id'] ); ?>"
				data-link-id="<?php echo esc_attr( $link_attrs['linkId'] ); ?>"
				data-link-kind="<?php echo esc_attr( $link_attrs['kind'] ); ?>"
				data-block-type="pros-cons">
				<?php echo esc_html( $cta_text ); ?>
			</a>
		</div>
		<?php endif; ?>
	<?php endif; ?>

</div>
