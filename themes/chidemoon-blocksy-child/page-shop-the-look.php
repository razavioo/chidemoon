<?php
/**
 * Landing template for editorial room combinations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$look_posts = new WP_Query(
	array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 6,
		'tag'                 => 'shop-the-look',
		'post__not_in'        => array( get_queried_object_id() ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
?>

<main id="primary" class="site-main chidemoon-collection-page chidemoon-look-page">
	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<header class="chidemoon-collection-page__hero chidemoon-section-shell">
			<p class="chidemoon-eyebrow"><?php esc_html_e( 'A room, considered', 'chidemoon-blocksy-child' ); ?></p>
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
				<span class="chidemoon-eyebrow"><?php esc_html_e( 'Direct merchant offers', 'chidemoon-blocksy-child' ); ?></span>
				<p><?php esc_html_e( 'Every selected product keeps its own destination. Chidemoon does not take payment or run a shopping cart.', 'chidemoon-blocksy-child' ); ?></p>
			</aside>
		</section>
	<?php endwhile; ?>

	<section class="chidemoon-section-shell chidemoon-collection-page__feed" aria-labelledby="chidemoon-looks-feed">
		<div class="chidemoon-section-heading">
			<div>
				<p class="chidemoon-eyebrow"><?php esc_html_e( 'Room notes', 'chidemoon-blocksy-child' ); ?></p>
				<h2 id="chidemoon-looks-feed"><?php esc_html_e( 'Published looks', 'chidemoon-blocksy-child' ); ?></h2>
			</div>
		</div>
		<?php if ( $look_posts->have_posts() ) : ?>
			<div class="chidemoon-card-grid chidemoon-card-grid--archive">
				<?php while ( $look_posts->have_posts() ) : ?>
					<?php $look_posts->the_post(); ?>
					<?php chidemoon_blocksy_render_post_card( get_the_ID() ); ?>
				<?php endwhile; ?>
			</div>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<?php chidemoon_blocksy_render_empty_state( __( 'The first room edit is being reviewed.', 'chidemoon-blocksy-child' ), __( 'A look appears only when its images, product destinations, and editorial narrative are complete.', 'chidemoon-blocksy-child' ) ); ?>
		<?php endif; ?>
	</section>
</main>

<?php
wp_reset_postdata();
get_footer();
