<?php
/** Public curated collection route backed by the existing product taxonomy. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$collection = chidemoon_current_public_collection();
if ( ! $collection instanceof WP_Term ) {
	return;
}

$block = sprintf(
	'<!-- wp:kalahamoon/product-catalog {"heading":%1$s,"description":%2$s,"collection":%3$s,"perPage":12,"columns":4,"showFilters":false,"showQuickView":true,"showFavorites":true,"showCompare":true} /-->',
	wp_json_encode( $collection->name ),
	wp_json_encode( wp_strip_all_tags( (string) $collection->description ) ),
	wp_json_encode( $collection->slug )
);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'chidemoon-product-collection-page' ); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
	<?php block_template_part( 'header' ); ?>
	<main id="chidemoon-main" class="chidemoon-content-page chidemoon-product-page chidemoon-product-collection">
		<nav class="chidemoon-product-detail__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'chidemoon-theme' ); ?>"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'chidemoon-theme' ); ?></a><span aria-hidden="true">/</span><a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Products', 'chidemoon-theme' ); ?></a><span aria-hidden="true">/</span><span aria-current="page"><?php echo esc_html( $collection->name ); ?></span></nav>
		<?php echo do_blocks( $block ); ?>
	</main>
	<?php block_template_part( 'footer' ); ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
