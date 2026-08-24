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
		<p class="chidemoon-eyebrow"><?php esc_html_e( 'The journal', 'chidemoon-blocksy-child' ); ?></p>
		<h1><?php esc_html_e( 'Guides for a more considered home', 'chidemoon-blocksy-child' ); ?></h1>
		<p><?php esc_html_e( 'Long-form ideas, useful comparisons, and room-by-room notes written for real decisions.', 'chidemoon-blocksy-child' ); ?></p>
	</header>

	<section class="chidemoon-section-shell">
		<?php if ( have_posts() ) : ?>
			<div class="chidemoon-card-grid chidemoon-card-grid--archive">
				<?php while ( have_posts() ) : ?>
					<?php the_post(); ?>
					<?php chidemoon_blocksy_render_post_card( get_the_ID() ); ?>
				<?php endwhile; ?>
			</div>
			<?php the_posts_pagination( array( 'class' => 'chidemoon-pagination', 'mid_size' => 1 ) ); ?>
		<?php else : ?>
			<?php chidemoon_blocksy_render_empty_state( __( 'The journal is being prepared.', 'chidemoon-blocksy-child' ), __( 'No article has passed editorial review yet. Please return when the first guide is ready.', 'chidemoon-blocksy-child' ) ); ?>
		<?php endif; ?>
	</section>
</main>

<?php get_footer(); ?>
