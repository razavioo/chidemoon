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
			<p class="chidemoon-eyebrow"><?php esc_html_e( 'Evidence-led comparisons', 'chidemoon-blocksy-child' ); ?></p>
			<h1><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
		</header>

		<section class="chidemoon-collection-page__content chidemoon-section-shell">
			<div class="entry-content">
				<?php the_content(); ?>
			</div>
			<aside class="chidemoon-collection-page__note">
				<span class="chidemoon-eyebrow"><?php esc_html_e( 'How we compare', 'chidemoon-blocksy-child' ); ?></span>
				<p><?php esc_html_e( 'Comparisons make their evidence and unresolved points visible so an editor can keep each decision accountable.', 'chidemoon-blocksy-child' ); ?></p>
			</aside>
		</section>
	<?php endwhile; ?>

	<section class="chidemoon-section-shell chidemoon-collection-page__feed" aria-labelledby="chidemoon-comparisons-feed">
		<div class="chidemoon-section-heading">
			<div>
				<p class="chidemoon-eyebrow"><?php esc_html_e( 'In the journal', 'chidemoon-blocksy-child' ); ?></p>
				<h2 id="chidemoon-comparisons-feed"><?php esc_html_e( 'Published comparisons', 'chidemoon-blocksy-child' ); ?></h2>
			</div>
		</div>
		<?php if ( $comparison_posts->have_posts() ) : ?>
			<div class="chidemoon-card-grid chidemoon-card-grid--archive">
				<?php while ( $comparison_posts->have_posts() ) : ?>
					<?php $comparison_posts->the_post(); ?>
					<?php chidemoon_blocksy_render_post_card( get_the_ID() ); ?>
				<?php endwhile; ?>
			</div>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<?php chidemoon_blocksy_render_empty_state( __( 'The first comparison is under editorial review.', 'chidemoon-blocksy-child' ), __( 'Only comparisons with checked product facts and clear sources will be published here.', 'chidemoon-blocksy-child' ) ); ?>
		<?php endif; ?>
	</section>
</main>

<?php
wp_reset_postdata();
get_footer();
