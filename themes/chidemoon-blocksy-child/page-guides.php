<?php
/**
 * Landing template for editorial buying guides.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$guide_posts = new WP_Query(
	array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 6,
		'category_name'       => 'guides',
		'post__not_in'        => array( get_queried_object_id() ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
?>

<main id="primary" class="site-main chidemoon-collection-page chidemoon-guides-page">
	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<header class="chidemoon-collection-page__hero chidemoon-section-shell<?php echo has_post_thumbnail() ? ' has-media' : ''; ?>">
			<div class="chidemoon-collection-page__hero-copy">
				<p class="chidemoon-eyebrow"><?php esc_html_e( 'راهنمای خرید، از دل تجربه', 'chidemoon-blocksy-child' ); ?></p>
				<h1><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?>
					<p><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="chidemoon-collection-page__hero-media">
					<?php the_post_thumbnail( 'large', array( 'loading' => 'eager' ) ); ?>
				</figure>
			<?php endif; ?>
		</header>

		<?php if ( '' !== trim( wp_strip_all_tags( get_the_content() ) ) ) : ?>
			<section class="chidemoon-collection-page__content chidemoon-collection-page__content--single chidemoon-section-shell">
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</section>
		<?php endif; ?>
	<?php endwhile; ?>

	<section class="chidemoon-section-shell chidemoon-collection-page__feed" aria-labelledby="chidemoon-guides-feed">
		<div class="chidemoon-section-heading">
			<div>
				<p class="chidemoon-eyebrow"><?php esc_html_e( 'در مجله', 'chidemoon-blocksy-child' ); ?></p>
				<h2 id="chidemoon-guides-feed"><?php esc_html_e( 'قبل از خرید، اصول کار را بدانید', 'chidemoon-blocksy-child' ); ?></h2>
			</div>
		</div>
		<?php if ( $guide_posts->have_posts() ) : ?>
			<div class="chidemoon-card-grid chidemoon-card-grid--archive">
				<?php while ( $guide_posts->have_posts() ) : ?>
					<?php $guide_posts->the_post(); ?>
					<?php chidemoon_blocksy_render_post_card( get_the_ID(), 'compact', 3 ); ?>
				<?php endwhile; ?>
			</div>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<?php chidemoon_blocksy_render_empty_state( 'اولین راهنما در حال آماده‌سازی است.', 'اینجا فقط راهنماهایی منتشر می‌شوند که از تجربه‌ی واقعی نوشته شده باشند.' ); ?>
		<?php endif; ?>
	</section>
</main>

<?php
wp_reset_postdata();
get_footer();
