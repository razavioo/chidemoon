<?php
/**
 * The home page is a public, content-led composition. Every card is sourced
 * from published WordPress or WooCommerce records, so an unprepared launch
 * stays honest instead of displaying made-up editorial material.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$front_page_id    = (int) get_option( 'page_on_front' );
$front_page        = $front_page_id > 0 ? get_post( $front_page_id ) : null;
$hero_title        = $front_page instanceof WP_Post ? get_the_title( $front_page ) : get_bloginfo( 'name' );
$hero_description  = $front_page instanceof WP_Post && has_excerpt( $front_page )
	? get_the_excerpt( $front_page )
	: get_bloginfo( 'description' );
$shop_url          = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : chidemoon_blocksy_page_url( 'shop' );
$stories           = new WP_Query(
	array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 3,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
$products          = function_exists( 'wc_get_products' )
	? wc_get_products(
		array(
			'status'  => 'publish',
			'limit'   => 4,
			'orderby' => 'date',
			'order'   => 'DESC',
		)
	)
	: array();
$product_categories = taxonomy_exists( 'product_cat' )
	? get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'number'     => 6,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	)
	: array();
?>

<main id="primary" class="site-main chidemoon-home">
	<section class="chidemoon-home__hero chidemoon-section-shell">
		<div class="chidemoon-home__hero-copy">
			<p class="chidemoon-eyebrow"><?php esc_html_e( 'Home, design, and considered living', 'chidemoon-blocksy-child' ); ?></p>
			<h1><?php echo esc_html( '' !== $hero_title ? $hero_title : get_bloginfo( 'name' ) ); ?></h1>
			<?php if ( '' !== $hero_description ) : ?>
				<p class="chidemoon-home__lede"><?php echo esc_html( $hero_description ); ?></p>
			<?php endif; ?>
			<div class="chidemoon-home__actions">
				<a class="chidemoon-button" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Explore selections', 'chidemoon-blocksy-child' ); ?><span aria-hidden="true">↗</span></a>
				<a class="chidemoon-button chidemoon-button--quiet" href="<?php echo esc_url( chidemoon_blocksy_page_url( 'guides' ) ); ?>"><?php esc_html_e( 'Read the journal', 'chidemoon-blocksy-child' ); ?></a>
			</div>
		</div>
		<div class="chidemoon-home__hero-mark" aria-hidden="true">
			<span>CM</span>
			<i></i>
			<b></b>
		</div>
	</section>

	<?php if ( $front_page instanceof WP_Post && '' !== trim( $front_page->post_content ) ) : ?>
		<section class="chidemoon-home__intro chidemoon-section-shell">
			<div class="entry-content">
				<?php echo apply_filters( 'the_content', $front_page->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="chidemoon-home__section chidemoon-section-shell" aria-labelledby="chidemoon-products-heading">
		<div class="chidemoon-section-heading">
			<div>
				<p class="chidemoon-eyebrow"><?php esc_html_e( 'A useful edit', 'chidemoon-blocksy-child' ); ?></p>
				<h2 id="chidemoon-products-heading"><?php esc_html_e( 'Products worth considering', 'chidemoon-blocksy-child' ); ?></h2>
			</div>
			<a class="chidemoon-text-link" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Browse all products', 'chidemoon-blocksy-child' ); ?><span aria-hidden="true">↗</span></a>
		</div>

		<?php if ( ! empty( $products ) ) : ?>
			<div class="chidemoon-card-grid chidemoon-card-grid--products">
				<?php chidemoon_blocksy_render_product_cards( $products ); ?>
			</div>
		<?php else : ?>
			<?php chidemoon_blocksy_render_empty_state( __( 'The first product edit is being reviewed.', 'chidemoon-blocksy-child' ), __( 'Only products with checked merchant details, imagery, and editorial review will appear here.', 'chidemoon-blocksy-child' ) ); ?>
		<?php endif; ?>
	</section>

	<section class="chidemoon-home__section chidemoon-section-shell" aria-labelledby="chidemoon-categories-heading">
		<div class="chidemoon-section-heading">
			<div>
				<p class="chidemoon-eyebrow"><?php esc_html_e( 'Start with a room', 'chidemoon-blocksy-child' ); ?></p>
				<h2 id="chidemoon-categories-heading"><?php esc_html_e( 'Browse the home', 'chidemoon-blocksy-child' ); ?></h2>
			</div>
		</div>

		<?php if ( ! is_wp_error( $product_categories ) && ! empty( $product_categories ) ) : ?>
			<div class="chidemoon-category-grid">
				<?php foreach ( $product_categories as $index => $category ) : ?>
					<?php $category_link = get_term_link( $category ); ?>
					<?php if ( ! is_wp_error( $category_link ) ) : ?>
						<a class="chidemoon-category-link" href="<?php echo esc_url( $category_link ); ?>">
							<span class="chidemoon-category-link__index">0<?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
							<span class="chidemoon-category-link__title"><?php echo esc_html( $category->name ); ?></span>
							<span class="chidemoon-category-link__count"><?php echo esc_html( (string) $category->count ); ?></span>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<?php chidemoon_blocksy_render_empty_state( __( 'Collections will appear after catalogue review.', 'chidemoon-blocksy-child' ), __( 'The public catalogue intentionally starts empty until categories and affiliate products are approved.', 'chidemoon-blocksy-child' ) ); ?>
		<?php endif; ?>
	</section>

	<section class="chidemoon-home__journal chidemoon-section-shell" aria-labelledby="chidemoon-journal-heading">
		<div class="chidemoon-section-heading chidemoon-section-heading--inverse">
			<div>
				<p class="chidemoon-eyebrow"><?php esc_html_e( 'The journal', 'chidemoon-blocksy-child' ); ?></p>
				<h2 id="chidemoon-journal-heading"><?php esc_html_e( 'Ideas with room to live', 'chidemoon-blocksy-child' ); ?></h2>
			</div>
			<a class="chidemoon-text-link" href="<?php echo esc_url( chidemoon_blocksy_page_url( 'guides' ) ); ?>"><?php esc_html_e( 'Visit all guides', 'chidemoon-blocksy-child' ); ?><span aria-hidden="true">↗</span></a>
		</div>

		<?php if ( $stories->have_posts() ) : ?>
			<div class="chidemoon-card-grid chidemoon-card-grid--stories">
				<?php while ( $stories->have_posts() ) : ?>
					<?php $stories->the_post(); ?>
					<?php chidemoon_blocksy_render_post_card( get_the_ID() ); ?>
				<?php endwhile; ?>
			</div>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<?php chidemoon_blocksy_render_empty_state( __( 'The journal is waiting for its first reviewed story.', 'chidemoon-blocksy-child' ), __( 'Guides, comparisons, and room ideas will be published here after editorial review.', 'chidemoon-blocksy-child' ) ); ?>
		<?php endif; ?>
	</section>

	<section class="chidemoon-home__routes chidemoon-section-shell" aria-label="<?php esc_attr_e( 'Explore Chidemoon formats', 'chidemoon-blocksy-child' ); ?>">
		<a class="chidemoon-route-card" href="<?php echo esc_url( chidemoon_blocksy_page_url( 'comparisons' ) ); ?>">
			<span class="chidemoon-eyebrow"><?php esc_html_e( 'Two to four products', 'chidemoon-blocksy-child' ); ?></span>
			<strong><?php esc_html_e( 'Clear comparisons', 'chidemoon-blocksy-child' ); ?></strong>
			<span><?php esc_html_e( 'Reviewed facts, visible trade-offs, no automatic recommendations.', 'chidemoon-blocksy-child' ); ?></span>
			<i aria-hidden="true">↗</i>
		</a>
		<a class="chidemoon-route-card chidemoon-route-card--clay" href="<?php echo esc_url( chidemoon_blocksy_page_url( 'shop-the-look' ) ); ?>">
			<span class="chidemoon-eyebrow"><?php esc_html_e( 'Build a room', 'chidemoon-blocksy-child' ); ?></span>
			<strong><?php esc_html_e( 'Shop the look', 'chidemoon-blocksy-child' ); ?></strong>
			<span><?php esc_html_e( 'Editorial combinations connected to individual, direct merchant offers.', 'chidemoon-blocksy-child' ); ?></span>
			<i aria-hidden="true">↗</i>
		</a>
	</section>
</main>

<?php
wp_reset_postdata();
get_footer();
