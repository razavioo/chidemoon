<?php
/**
 * Chidemoon Blocksy child theme bootstrap.
 *
 * Presentation remains here so product, affiliate, and editorial rules stay
 * portable in the Chidemoon Core plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	static function (): void {
		load_child_theme_textdomain( 'chidemoon-blocksy-child', get_stylesheet_directory() . '/languages' );
	}
);

function chidemoon_blocksy_enqueue_styles(): void {
	wp_enqueue_style(
		'chidemoon-blocksy-child',
		get_stylesheet_uri(),
		array(),
		(string) wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'chidemoon_blocksy_enqueue_styles', 20 );

function chidemoon_blocksy_setup(): void {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
}
add_action( 'after_setup_theme', 'chidemoon_blocksy_setup' );

function chidemoon_blocksy_body_classes( array $classes ): array {
	$classes[] = 'chidemoon-editorial-site';
	$classes[] = is_rtl() ? 'chidemoon-persian' : 'chidemoon-ltr';
	return $classes;
}
add_filter( 'body_class', 'chidemoon_blocksy_body_classes' );

/**
 * Return a stable public URL when a curated landing page has not been created
 * yet. Navigation can therefore be styled before editorial setup is complete.
 */
function chidemoon_blocksy_page_url( string $slug ): string {
	$page = get_page_by_path( $slug );
	if ( $page instanceof WP_Post ) {
		$permalink = get_permalink( $page );
		if ( is_string( $permalink ) && '' !== $permalink ) {
			return $permalink;
		}
	}

	return home_url( '/' . trim( $slug, '/' ) . '/' );
}

/**
 * The presentation layer deliberately reads only public WordPress fields. It
 * does not infer product claims, merchant links, review state, or affiliate data.
 */
function chidemoon_blocksy_render_post_card( int $post_id ): void {
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
		return;
	}

	$permalink  = get_permalink( $post );
	$title      = get_the_title( $post );
	$excerpt    = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 24 );
	$categories = get_the_category( $post_id );
	$category   = ! empty( $categories ) ? $categories[0] : null;
	?>
	<article class="chidemoon-card chidemoon-story-card">
		<a class="chidemoon-card__media" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
			<?php if ( has_post_thumbnail( $post ) ) : ?>
				<?php echo get_the_post_thumbnail( $post, 'large', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?>
				<span class="chidemoon-card__media-empty" aria-hidden="true"></span>
			<?php endif; ?>
		</a>
		<div class="chidemoon-card__body">
			<div class="chidemoon-card__meta">
				<?php if ( $category instanceof WP_Term ) : ?>
					<span><?php echo esc_html( $category->name ); ?></span>
				<?php endif; ?>
				<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post ) ); ?>"><?php echo esc_html( get_the_date( '', $post ) ); ?></time>
			</div>
			<h3 class="chidemoon-card__title"><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a></h3>
			<?php if ( '' !== $excerpt ) : ?>
				<p class="chidemoon-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
			<?php endif; ?>
			<a class="chidemoon-text-link" href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'Read the guide', 'chidemoon-blocksy-child' ); ?><span aria-hidden="true">↗</span></a>
		</div>
	</article>
	<?php
}

/**
 * Render product tiles from WooCommerce's public catalogue only. The Core
 * plugin remains responsible for whether a product can make an affiliate CTA.
 *
 * @param WC_Product[] $products Public WooCommerce products.
 */
function chidemoon_blocksy_render_product_cards( array $products ): void {
	foreach ( $products as $product ) {
		if ( ! $product instanceof WC_Product || 'publish' !== get_post_status( $product->get_id() ) ) {
			continue;
		}

		$product_id = $product->get_id();
		$title      = $product->get_name();
		$permalink  = get_permalink( $product_id );
		$image_id   = $product->get_image_id();
		$price_html = $product->get_price_html();
		$terms      = get_the_terms( $product_id, 'product_cat' );
		$term       = is_array( $terms ) && ! empty( $terms ) ? $terms[0] : null;
		?>
		<article class="chidemoon-card chidemoon-product-card">
			<a class="chidemoon-card__media" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
				<?php if ( $image_id > 0 ) : ?>
					<?php echo wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<span class="chidemoon-card__media-empty" aria-hidden="true"></span>
				<?php endif; ?>
			</a>
			<div class="chidemoon-card__body">
				<?php if ( $term instanceof WP_Term ) : ?>
					<p class="chidemoon-card__meta"><span><?php echo esc_html( $term->name ); ?></span></p>
				<?php endif; ?>
				<h3 class="chidemoon-card__title"><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a></h3>
				<?php if ( '' !== $price_html ) : ?>
					<p class="chidemoon-product-card__price"><?php echo wp_kses_post( $price_html ); ?></p>
				<?php else : ?>
					<p class="chidemoon-product-card__pending"><?php esc_html_e( 'Price under review', 'chidemoon-blocksy-child' ); ?></p>
				<?php endif; ?>
				<a class="chidemoon-text-link" href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'View details', 'chidemoon-blocksy-child' ); ?><span aria-hidden="true">↗</span></a>
			</div>
		</article>
		<?php
	}
}

/**
 * A deliberate empty state avoids visual placeholder content before the
 * editorial team has reviewed real items for publication.
 */
function chidemoon_blocksy_render_empty_state( string $title, string $description ): void {
	?>
	<div class="chidemoon-empty-state">
		<span class="chidemoon-empty-state__index" aria-hidden="true">01</span>
		<div>
			<h3><?php echo esc_html( $title ); ?></h3>
			<p><?php echo esc_html( $description ); ?></p>
		</div>
	</div>
	<?php
}

/**
 * The affiliate disclosure is a Core-owned value. The theme only reserves a
 * visually consistent position for it when the plugin elects to render one.
 */
function chidemoon_blocksy_render_single_product_disclosure(): void {
	if ( ! shortcode_exists( 'chidemoon_affiliate_disclosure' ) ) {
		return;
	}

	$disclosure = do_shortcode( '[chidemoon_affiliate_disclosure]' );
	if ( '' !== trim( $disclosure ) ) {
		echo '<div class="chidemoon-product-disclosure">' . $disclosure . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
add_action( 'woocommerce_single_product_summary', 'chidemoon_blocksy_render_single_product_disclosure', 38 );
