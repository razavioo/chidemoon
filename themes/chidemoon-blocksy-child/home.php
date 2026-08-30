<?php
/**
 * Editorial index for WordPress posts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main chidemoon-archive chidemoon-archive--journal">
	<header class="chidemoon-archive__hero chidemoon-section-shell">
		<p class="chidemoon-eyebrow"><?php esc_html_e( 'مجله چیدمون', 'chidemoon-blocksy-child' ); ?></p>
		<h1><?php esc_html_e( 'راهنمای انتخاب و چیدمان وسایل خانه', 'chidemoon-blocksy-child' ); ?></h1>
		<p><?php esc_html_e( 'راهنمای خرید، مقایسه‌ی کالاها و پیشنهادهای کاربردی برای چیدمان هر بخش از خانه.', 'chidemoon-blocksy-child' ); ?></p>
	</header>

	<section class="chidemoon-section-shell">
		<?php if ( have_posts() ) : ?>
			<div class="chidemoon-card-grid chidemoon-card-grid--archive">
				<?php while ( have_posts() ) : ?>
					<?php the_post(); ?>
					<?php chidemoon_blocksy_render_post_card( get_the_ID(), 'compact', 2 ); ?>
				<?php endwhile; ?>
			</div>
			<?php the_posts_pagination( array( 'class' => 'chidemoon-pagination', 'mid_size' => 1 ) ); ?>
		<?php else : ?>
			<?php chidemoon_blocksy_render_empty_state( 'مجله در حال آماده‌سازی است.', 'هنوز مقاله‌ای از بررسی تحریریه گذشته است. منتظر اولین راهنما باشید.' ); ?>
		<?php endif; ?>
	</section>
</main>

<?php get_footer(); ?>
