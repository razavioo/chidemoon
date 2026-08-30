<?php
/**
 * Archive presentation for public editorial taxonomies and dates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main chidemoon-archive">
	<header class="chidemoon-archive__hero chidemoon-section-shell">
		<p class="chidemoon-eyebrow"><?php esc_html_e( 'آرشیو مجله', 'chidemoon-blocksy-child' ); ?></p>
		<h1><?php the_archive_title(); ?></h1>
		<?php if ( get_the_archive_description() ) : ?>
			<div class="chidemoon-archive__description"><?php the_archive_description(); ?></div>
		<?php else : ?>
			<p><?php esc_html_e( 'مطالب بررسی‌شده و ایده‌های کاربردی از مجله چیدمون.', 'chidemoon-blocksy-child' ); ?></p>
		<?php endif; ?>
	</header>

	<section class="chidemoon-section-shell">
		<?php if ( have_posts() ) : ?>
			<div class="chidemoon-card-grid chidemoon-card-grid--archive">
				<?php while ( have_posts() ) : ?>
					<?php the_post(); ?>
					<?php chidemoon_blocksy_render_post_card( get_the_ID(), 'compact' ); ?>
				<?php endwhile; ?>
			</div>
			<?php the_posts_pagination( array( 'class' => 'chidemoon-pagination', 'mid_size' => 1 ) ); ?>
		<?php else : ?>
			<?php chidemoon_blocksy_render_empty_state( 'در این آرشیو مطلب منتشرشده‌ای نیست.', 'این بخش تا زمانی که مطلب بررسی‌شده‌ای اینجا منتشر شود ساکت می‌ماند.' ); ?>
		<?php endif; ?>
	</section>
</main>

<?php get_footer(); ?>
