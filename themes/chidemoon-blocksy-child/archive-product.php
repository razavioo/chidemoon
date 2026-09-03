<?php
/**
 * WooCommerce catalogue archive with a deliberately editorial frame.
 *
 * The standard WooCommerce hooks remain intact so product visibility and
 * affiliate eligibility continue to be enforced by WooCommerce and Core.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header( 'shop' );

// The template renders its own semantic archive heading and description.
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

$current_term      = is_product_taxonomy() ? get_queried_object() : null;
$term_thumbnail_id = $current_term instanceof WP_Term ? chidemoon_term_thumbnail_id( $current_term ) : 0;
$catalogue_count   = chidemoon_archive_record_count();
?>

<div class="chidemoon-shop-archive">
	<section class="chidemoon-shop-archive__intro chidemoon-section-shell<?php echo $term_thumbnail_id > 0 ? ' has-media' : ''; ?>">
		<div class="chidemoon-shop-archive__intro-copy">
			<p class="chidemoon-eyebrow"><?php esc_html_e( 'انتخاب کالا', 'chidemoon-blocksy-child' ); ?></p>
			<h1><?php woocommerce_page_title(); ?></h1>
			<?php do_action( 'woocommerce_archive_description' ); ?>
			<?php if ( $catalogue_count > 0 ) : ?>
				<p class="chidemoon-hero-facts">
					<span class="chidemoon-hero-facts__item"><strong><?php echo esc_html( chidemoon_fa_digits( $catalogue_count ) ); ?></strong><?php esc_html_e( 'کالای منتشرشده', 'chidemoon-blocksy-child' ); ?></span>
				</p>
			<?php endif; ?>
		</div>
		<?php if ( $term_thumbnail_id > 0 ) : ?>
			<figure class="chidemoon-shop-archive__intro-media">
				<?php echo wp_get_attachment_image( $term_thumbnail_id, 'large', false, array( 'loading' => 'eager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</figure>
		<?php endif; ?>
	</section>

	<?php do_action( 'woocommerce_before_main_content' ); ?>

	<section class="chidemoon-shop-archive__catalogue chidemoon-section-shell">
		<?php if ( woocommerce_product_loop() ) : ?>
			<?php do_action( 'woocommerce_before_shop_loop' ); ?>

			<?php woocommerce_product_loop_start(); ?>
				<?php while ( have_posts() ) : ?>
					<?php the_post(); ?>
					<?php wc_get_template_part( 'content', 'product' ); ?>
				<?php endwhile; ?>
			<?php woocommerce_product_loop_end(); ?>

			<?php do_action( 'woocommerce_after_shop_loop' ); ?>
		<?php else : ?>
			<?php chidemoon_blocksy_render_empty_state( 'انتخاب کالا در حال آماده‌سازی است.', 'کالاها فقط وقتی نمایش داده می‌شوند که مقصد فروشنده، دسته‌بندی، تصویر و وضعیت بررسی‌شان آماده استفاده عمومی باشد.' ); ?>
		<?php endif; ?>
	</section>

	<?php do_action( 'woocommerce_after_main_content' ); ?>
	<?php do_action( 'woocommerce_sidebar' ); ?>
</div>

<?php get_footer( 'shop' ); ?>
