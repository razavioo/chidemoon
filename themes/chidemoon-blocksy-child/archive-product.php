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
$term_art          = $current_term instanceof WP_Term && is_product_category()
	? chidemoon_category_art( $current_term )
	: '';
$hero_product      = $current_term instanceof WP_Term && is_product_category()
	? chidemoon_category_hero_product( $current_term )
	: null;
?>

<div class="chidemoon-shop-archive">
	<section class="chidemoon-shop-archive__intro chidemoon-section-shell<?php echo $term_thumbnail_id > 0 || '' !== $term_art || $hero_product instanceof WC_Product ? ' has-media' : ''; ?>">
		<div class="chidemoon-shop-archive__intro-copy">
			<p class="chidemoon-eyebrow"><?php esc_html_e( 'انتخاب محصول', 'chidemoon-blocksy-child' ); ?></p>
			<h1><?php woocommerce_page_title(); ?></h1>
			<?php do_action( 'woocommerce_archive_description' ); ?>
		</div>
		<?php if ( $hero_product instanceof WC_Product ) : ?>
			<figure class="chidemoon-shop-archive__intro-media chidemoon-shop-archive__intro-media--product">
				<a class="chidemoon-shop-archive__media-frame" href="<?php echo esc_url( $hero_product->get_permalink() ); ?>" aria-label="<?php echo esc_attr( $hero_product->get_name() ); ?>">
					<?php echo wp_get_attachment_image( (int) $hero_product->get_image_id(), 'large', false, array( 'loading' => 'eager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( '' !== $term_art ) : ?>
						<span class="chidemoon-shop-archive__media-seal" aria-hidden="true"><?php echo $term_art; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php endif; ?>
				</a>
				<figcaption class="chidemoon-shop-archive__media-caption">
					<a class="chidemoon-shop-archive__media-caption__product" href="<?php echo esc_url( $hero_product->get_permalink() ); ?>"><?php echo esc_html( $hero_product->get_name() ); ?></a>
					<span class="chidemoon-shop-archive__media-caption__label"><?php esc_html_e( 'نمونه‌ای از این دسته', 'chidemoon-blocksy-child' ); ?></span>
				</figcaption>
			</figure>
		<?php elseif ( $term_thumbnail_id > 0 ) : ?>
			<figure class="chidemoon-shop-archive__intro-media">
				<?php echo wp_get_attachment_image( $term_thumbnail_id, 'large', false, array( 'loading' => 'eager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</figure>
		<?php elseif ( '' !== $term_art ) : ?>
			<figure class="chidemoon-shop-archive__intro-media chidemoon-shop-archive__intro-media--art" aria-hidden="true">
				<?php echo $term_art; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
			<?php chidemoon_blocksy_render_empty_state( 'انتخاب محصول در حال آماده‌سازی است.', 'محصولات فقط وقتی نمایش داده می‌شوند که بررسی‌شان کامل شده باشد؛ تصویر، دسته‌بندی و فروشنده‌ی مقصد همه تأیید شده باشند.' ); ?>
		<?php endif; ?>
	</section>

	<?php do_action( 'woocommerce_after_main_content' ); ?>
	<?php do_action( 'woocommerce_sidebar' ); ?>
</div>

<?php get_footer( 'shop' ); ?>
