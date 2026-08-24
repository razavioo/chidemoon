<?php
/**
 * Product detail page. Product facts, merchant links, and purchase behaviour
 * are rendered through WooCommerce and Chidemoon Core, never from this theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header( 'shop' );
?>

<div class="chidemoon-product-page">
	<?php do_action( 'woocommerce_before_main_content' ); ?>

	<section class="chidemoon-product-page__frame chidemoon-section-shell">
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<?php wc_get_template_part( 'content', 'single-product' ); ?>
		<?php endwhile; ?>
	</section>

	<?php do_action( 'woocommerce_after_main_content' ); ?>
	<?php do_action( 'woocommerce_sidebar' ); ?>
</div>

<?php get_footer( 'shop' ); ?>
