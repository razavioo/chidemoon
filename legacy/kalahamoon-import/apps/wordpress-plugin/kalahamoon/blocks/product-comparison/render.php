<?php
/**
 * Server-side comparison selected by block attributes or the public URL.
 *
 * @var array $attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$raw_ids = trim( (string) ( $attributes['productIds'] ?? '' ) );
if ( '' === $raw_ids && isset( $_GET['products'] ) ) {
	$raw_ids = (string) wp_unslash( $_GET['products'] );
}
$ids = array_values(
	array_unique(
		array_filter(
			array_map(
				static fn( string $id ): string => preg_replace( '/[^a-zA-Z0-9_-]/', '', trim( $id ) ) ?: '',
				explode( ',', $raw_ids )
			)
		)
	)
);
$submitted_count = count( $ids );
if ( $submitted_count > 4 ) {
	$ids = array();
}

$products = array();
foreach ( $ids as $id ) {
	$product = Kalahamoon_Product_Cache::get_for_public_render( $id );
	if ( $product ) {
		$products[] = $product;
	}
}

$types = array_values(
	array_unique(
		array_filter(
			array_map(
				static function ( array $product ): string {
					$type = is_array( $product['comparisonType'] ?? null ) ? $product['comparisonType'] : array();
					return trim( (string) ( $type['key'] ?? $type['slug'] ?? '' ) );
				},
				$products
			)
		)
	)
);
$ready       = $submitted_count >= 2 && $submitted_count <= 4 && count( $products ) === $submitted_count && 1 === count( $types );
$public_total = Kalahamoon_Product_Cache::public_ready_count();
$catalog_available = $public_total >= 2;
$heading     = trim( (string) ( $attributes['heading'] ?? '' ) );
$show_notice = ! isset( $attributes['showDisclosure'] ) || ! empty( $attributes['showDisclosure'] );
$wrapper     = get_block_wrapper_attributes( array( 'class' => 'kalahamoon-product-comparison' ) );
?>
<section <?php echo $wrapper; ?> data-comparison-state="<?php echo $ready ? 'ready' : 'incomplete'; ?>">
	<?php if ( '' !== $heading ) : ?><h2><?php echo esc_html( $heading ); ?></h2><?php endif; ?>

	<?php if ( ! $ready ) : ?>
		<div class="kalahamoon-product-comparison__empty" role="status">
			<?php if ( ! $catalog_available ) : ?>
				<h2><?php esc_html_e( 'Comparison will be available when verified products are ready.', 'kalahamoon' ); ?></h2>
				<p><?php esc_html_e( 'A comparison needs at least two current, verified products from the same comparison type. We do not fill this page with incomplete catalog data.', 'kalahamoon' ); ?></p>
				<div class="kalahamoon-product-comparison__actions"><a href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'Explore buying guides', 'kalahamoon' ); ?></a><a href="<?php echo esc_url( home_url( '/magazine/' ) ); ?>"><?php esc_html_e( 'Read the magazine', 'kalahamoon' ); ?></a></div>
			<?php else : ?>
				<h2><?php esc_html_e( 'Choose two to four compatible products.', 'kalahamoon' ); ?></h2>
				<p><?php esc_html_e( 'Products must be verified, recently refreshed, and part of the same comparison type.', 'kalahamoon' ); ?></p>
				<a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Choose products from the catalog', 'kalahamoon' ); ?></a>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<?php if ( $show_notice ) : ?>
			<p class="kalahamoon-product-comparison__disclosure"><?php esc_html_e( 'Some product links may earn us a commission without changing your price.', 'kalahamoon' ); ?></p>
		<?php endif; ?>
		<?php echo Kalahamoon_Shortcodes::comparison_table( array( 'ids' => implode( ',', $ids ) ) ); ?>
	<?php endif; ?>
</section>
