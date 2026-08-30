<?php
/**
 * Landing template for editor-authored product comparisons.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$comparison_posts = new WP_Query(
	array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 6,
		'category_name'       => 'comparisons',
		'post__not_in'        => array( get_queried_object_id() ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
?>

<main id="primary" class="site-main chidemoon-collection-page chidemoon-comparisons-page">
	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<header class="chidemoon-collection-page__hero chidemoon-section-shell">
			<p class="chidemoon-eyebrow"><?php esc_html_e( 'مقایسه‌های مبتنی بر شواهد', 'chidemoon-blocksy-child' ); ?></p>
			<h1><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="chidemoon-collection-page__image chidemoon-section-shell">
				<?php the_post_thumbnail( 'large', array( 'loading' => 'eager' ) ); ?>
			</figure>
		<?php endif; ?>

		<?php if ( '' !== trim( wp_strip_all_tags( get_the_content() ) ) ) : ?>
			<section class="chidemoon-collection-page__content chidemoon-collection-page__content--single chidemoon-section-shell">
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</section>
		<?php endif; ?>
	<?php endwhile; ?>

	<section class="chidemoon-section-shell chidemoon-collection-page__feed" aria-labelledby="chidemoon-comparisons-feed">
		<div class="chidemoon-section-heading">
			<div>
				<p class="chidemoon-eyebrow"><?php esc_html_e( 'در مجله', 'chidemoon-blocksy-child' ); ?></p>
				<h2 id="chidemoon-comparisons-feed"><?php esc_html_e( 'مقایسه‌های منتشرشده', 'chidemoon-blocksy-child' ); ?></h2>
			</div>
		</div>
		<?php if ( $comparison_posts->have_posts() ) : ?>
			<div class="chidemoon-card-grid chidemoon-card-grid--archive">
				<?php while ( $comparison_posts->have_posts() ) : ?>
					<?php $comparison_posts->the_post(); ?>
					<?php chidemoon_blocksy_render_post_card( get_the_ID(), 'compact' ); ?>
				<?php endwhile; ?>
			</div>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<?php chidemoon_blocksy_render_empty_state( 'اولین مقایسه در حال بررسی است.', 'فقط مقایسه‌هایی که واقعیت کالاهایشان بررسی و منبع‌شان شفاف باشد اینجا منتشر می‌شوند.' ); ?>
		<?php endif; ?>
	</section>
</main>

<?php
wp_reset_postdata();
get_footer();
