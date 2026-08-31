<?php
/**
 * Editorial article template for the Chidemoon journal. Renders a reviewed
 * single post with its imagery, metadata, and the Core affiliate disclosure.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$categories = get_the_category();

?>
<main id="primary" class="site-main chidemoon-article">
	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<header class="chidemoon-article__hero chidemoon-section-shell">
			<?php if ( ! empty( $categories ) ) : ?>
				<a class="chidemoon-eyebrow chidemoon-article__badge" href="<?php echo esc_url( get_category_link( $categories[0]->term_id ) ); ?>"><?php echo esc_html( $categories[0]->name ); ?></a>
			<?php else : ?>
				<p class="chidemoon-eyebrow"><?php esc_html_e( 'مجله چیدمون', 'chidemoon-blocksy-child' ); ?></p>
			<?php endif; ?>
			<h1><?php the_title(); ?></h1>
			<div class="chidemoon-article__meta">
				<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
				<span aria-hidden="true">·</span>
				<span><?php the_author(); ?></span>
			</div>
			<?php if ( has_excerpt() ) : ?>
				<p class="chidemoon-article__lede"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<?php
			$thumbnail_id = (int) get_post_thumbnail_id();
			$caption      = wp_get_attachment_caption( $thumbnail_id );
			?>
			<figure class="chidemoon-article__image chidemoon-section-shell">
				<?php the_post_thumbnail( 'full', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?>
				<?php if ( ! empty( $caption ) ) : ?>
					<figcaption class="chidemoon-article__image-caption"><?php echo esc_html( $caption ); ?></figcaption>
				<?php endif; ?>
			</figure>
		<?php endif; ?>

		<div class="chidemoon-article__layout chidemoon-section-shell">
			<article class="chidemoon-article__body entry-content">
				<?php the_content(); ?>
			</article>

			<aside class="chidemoon-article__aside">
				<?php $tags = get_the_tags(); ?>
				<?php if ( is_array( $tags ) && ! empty( $tags ) ) : ?>
					<div class="chidemoon-article__tags">
						<?php foreach ( $tags as $tag ) : ?>
							<a class="chidemoon-article__tag" href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>"><?php echo esc_html( $tag->name ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</aside>
		</div>

		<nav class="chidemoon-article__navigation chidemoon-section-shell" aria-label="<?php esc_attr_e( 'ادامه مجله', 'chidemoon-blocksy-child' ); ?>">
			<?php the_post_navigation(
				array(
					'prev_text' => '<span class="chidemoon-eyebrow">' . esc_html__( 'مطلب قبلی', 'chidemoon-blocksy-child' ) . '</span><span class="chidemoon-article__nav-title">%title</span>',
					'next_text' => '<span class="chidemoon-eyebrow">' . esc_html__( 'مطلب بعدی', 'chidemoon-blocksy-child' ) . '</span><span class="chidemoon-article__nav-title">%title</span>',
				)
			); ?>
		</nav>
	<?php endwhile; ?>
</main>

<?php get_footer(); ?>
