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
			<p class="chidemoon-eyebrow"><?php esc_html_e( 'یک اتاق، با دقت', 'chidemoon-blocksy-child' ); ?></p>
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

	<section class="chidemoon-section-shell chidemoon-collection-page__feed" aria-labelledby="chidemoon-looks-feed">
		<div class="chidemoon-section-heading">
			<div>
				<p class="chidemoon-eyebrow"><?php esc_html_e( 'نکته‌های اتاق', 'chidemoon-blocksy-child' ); ?></p>
				<h2 id="chidemoon-looks-feed"><?php esc_html_e( 'ترکیب‌های منتشرشده', 'chidemoon-blocksy-child' ); ?></h2>
			</div>
		</div>
		<?php if ( $look_posts->have_posts() ) : ?>
			<div class="chidemoon-card-grid chidemoon-card-grid--archive">
				<?php while ( $look_posts->have_posts() ) : ?>
					<?php $look_posts->the_post(); ?>
					<?php chidemoon_blocksy_render_post_card( get_the_ID(), 'compact', 3 ); ?>
				<?php endwhile; ?>
			</div>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<?php chidemoon_blocksy_render_empty_state( 'اولین ترکیب در حال بررسی است.', 'یک ترکیب فقط وقتی منتشر می‌شود که تصاویرش، مقصد کالاهایش و روایت تحریریه‌اش کامل باشد.' ); ?>
		<?php endif; ?>
	</section>
</main>

<?php
wp_reset_postdata();
get_footer();
