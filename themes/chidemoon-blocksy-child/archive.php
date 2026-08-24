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
		<p class="chidemoon-eyebrow"><?php esc_html_e( 'Editorial archive', 'chidemoon-blocksy-child' ); ?></p>
		<h1><?php the_archive_title(); ?></h1>
		<?php if ( get_the_archive_description() ) : ?>
			<div class="chidemoon-archive__description"><?php the_archive_description(); ?></div>
		<?php else : ?>
			<p><?php esc_html_e( 'Reviewed stories and practical ideas from the Chidemoon journal.', 'chidemoon-blocksy-child' ); ?></p>
		<?php endif; ?>
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
			<?php chidemoon_blocksy_render_empty_state( __( 'There are no published stories in this archive.', 'chidemoon-blocksy-child' ), __( 'This section will remain quiet until a reviewed story belongs here.', 'chidemoon-blocksy-child' ) ); ?>
		<?php endif; ?>
	</section>
</main>

<?php get_footer(); ?>
