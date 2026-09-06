<?php
/**
 * Chidemoon Blocksy child theme module.
 *
 * Loaded by functions.php; do not load directly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blocksy's configured footer only ships an empty copyright row, which renders
 * as a blank strip on every page. The editorial footer below carries the real
 * navigation, disclosure, and brand closure instead.
 */
function chidemoon_blocksy_render_footer(): void {
	$sections = array(
		array(
			'title' => 'بخش‌های چیدمون',
			'links' => array(
				'خانه'           => home_url( '/' ),
				'مجله'           => chidemoon_blocksy_page_url( 'magazine' ),
				'راهنمای خرید'   => chidemoon_blocksy_page_url( 'guides' ),
				'مقایسه‌ها'      => chidemoon_blocksy_page_url( 'comparisons' ),
				'ببین و بخر'     => chidemoon_blocksy_page_url( 'shop-the-look' ),
			),
		),
		array(
			'title' => 'فروشگاه',
			'links' => array(
				'همه محصولات' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : chidemoon_blocksy_page_url( 'shop' ),
			),
		),
	);
	$categories = taxonomy_exists( 'product_cat' )
		? get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 5, 'orderby' => 'count', 'order' => 'DESC' ) )
		: array();
	if ( ! is_wp_error( $categories ) ) {
		foreach ( $categories as $category ) {
			if ( $category instanceof WP_Term && 'uncategorized' !== $category->slug ) {
				$sections[1]['links'][ $category->name ] = get_term_link( $category );
			}
		}
	}
	?>
	<footer class="chidemoon-footer" itemscope itemtype="https://schema.org/WPFooter">
		<div class="chidemoon-footer__inner">
			<div class="chidemoon-footer__brand">
				<p class="chidemoon-footer__logo" itemprop="name"><?php bloginfo( 'name' ); ?></p>
				<p class="chidemoon-footer__tagline">
					<?php esc_html_e( 'مجله‌ی خرید برای خانه؛ راهنماهای امتحان‌پس‌داده، مقایسه‌ی بی‌طرفانه و پیشنهادهایی برای چیدمان هر گوشه‌ی خانه.', 'chidemoon-blocksy-child' ); ?>
				</p>
			</div>
			<?php foreach ( $sections as $section ) : ?>
				<nav class="chidemoon-footer__nav" aria-label="<?php echo esc_attr( $section['title'] ); ?>">
					<h2 class="chidemoon-footer__title"><?php echo esc_html( $section['title'] ); ?></h2>
					<ul>
						<?php foreach ( $section['links'] as $label => $url ) : ?>
							<?php if ( is_string( $url ) && '' !== $url && ! is_wp_error( $url ) ) : ?>
								<li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( (string) $label ); ?></a></li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endforeach; ?>
			<div class="chidemoon-footer__note">
				<h2 class="chidemoon-footer__title"><?php esc_html_e( 'استقلال تحریریه', 'chidemoon-blocksy-child' ); ?></h2>
				<p>
					<?php esc_html_e( 'چیدمون یک بلاگ تخصصی مستقل است؛ هیچ فروشنده‌ای در نتیجه‌ی بررسی‌ها و رتبه‌بندی‌ها نفوذ ندارد.', 'chidemoon-blocksy-child' ); ?>
				</p>
			</div>
		</div>
	</footer>
	<?php
}
add_action( 'blocksy:footer:before', 'chidemoon_blocksy_render_footer', 5 );
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
 * Persian-first presentation must never surface the English "Uncategorized"
 * term as a badge. Prefer the first real category and fall back to none.
 */
function chidemoon_blocksy_primary_category( int $post_id ): ?WP_Term {
	$categories = get_the_category( $post_id );
	foreach ( $categories as $category ) {
		if ( 'uncategorized' !== $category->slug ) {
			return $category;
		}
	}

	return null;
}

/**
 * Estimate the reading time of a post in minutes for the article meta row.
 * Persian prose is measured at roughly 180 words per minute.
 */
function chidemoon_reading_time( int $post_id ): int {
	$content = get_post_field( 'post_content', $post_id );
	$words   = preg_split( '/\s+/u', wp_strip_all_tags( $content ), -1, PREG_SPLIT_NO_EMPTY );

	return max( 1, (int) ceil( ( is_array( $words ) ? count( $words ) : 0 ) / 180 ) );
}

/**
 * Number of records in the current archive query, safe outside the loop.
 */
function chidemoon_archive_record_count(): int {
	return isset( $GLOBALS['wp_query']->found_posts ) ? (int) $GLOBALS['wp_query']->found_posts : 0;
}

/**
 * Honest launch counts for the home hero. Zero rows are omitted by the
 * caller so an unprepared site never advertises numbers it cannot back.
 *
 * @return array<string, int> label => count.
 */
function chidemoon_home_hero_stats(): array {
	$stats = array();

	$story_count = wp_count_posts( 'post' );
	if ( $story_count instanceof stdClass && (int) $story_count->publish > 0 ) {
		$stats['راهنمای منتشرشده'] = (int) $story_count->publish;
	}

	if ( taxonomy_exists( 'product_cat' ) ) {
		$category_count = wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );
		if ( is_numeric( $category_count ) && (int) $category_count > 0 ) {
			$stats['دسته‌بندی فعال'] = (int) $category_count;
		}
	}

	if ( post_type_exists( 'product' ) ) {
		$product_count = wp_count_posts( 'product' );
		if ( $product_count instanceof stdClass && (int) $product_count->publish > 0 ) {
			$stats['محصول بررسی‌شده'] = (int) $product_count->publish;
		}
	}

	return $stats;
}

/**
 * WooCommerce stores an optional category banner as the term thumbnail.
 */
function chidemoon_term_thumbnail_id( ?WP_Term $term ): int {
	if ( ! $term instanceof WP_Term || ! taxonomy_exists( $term->taxonomy ) ) {
		return 0;
	}

	$thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );

	return is_numeric( $thumbnail_id ) ? (int) $thumbnail_id : 0;
}

/**
 * The newest published product with a real image in a category, used as the
 * honest hero visual for the category archive. Returns null when the category
 * has no image-bearing product yet, so the template can fall back to curated
 * or drawn media instead of fabricating anything.
 *
 * @param WP_Term $term Product category term.
 */
function chidemoon_category_hero_product( WP_Term $term ): ?WC_Product {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return null;
	}

	$products = wc_get_products(
		array(
			'status'   => 'publish',
			'limit'    => 6,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'category' => array( $term->slug ),
		)
	);

	foreach ( $products as $product ) {
		if ( $product instanceof WC_Product && (int) $product->get_image_id() > 0 ) {
			return $product;
		}
	}

	return null;
}

/**
 * The presentation layer deliberately reads only public WordPress fields. It
 * does not infer product claims, merchant links, review state, or affiliate data.
 *
 * @param int    $post_id      Published post ID.
 * @param string $variant      Optional lead or compact visual variant.
 * @param int    $heading_level Card heading level, limited to h2 or h3.
 */
function chidemoon_blocksy_render_post_card( int $post_id, string $variant = '', int $heading_level = 3 ): void {
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
		return;
	}

	$permalink  = get_permalink( $post );
	$title      = get_the_title( $post );
	$excerpt    = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 24 );
	$category   = chidemoon_blocksy_primary_category( $post_id );
	$valid_variants = array( 'lead', 'compact' );
	$variant_class  = in_array( $variant, $valid_variants, true ) ? ' chidemoon-story-card--' . $variant : '';
	$heading_level  = in_array( $heading_level, array( 2, 3 ), true ) ? $heading_level : 3;
	$heading_tag    = 'h' . $heading_level;
	?>
	<article class="chidemoon-card chidemoon-story-card<?php echo esc_attr( $variant_class ); ?>">
		<a class="chidemoon-card__media" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
			<?php if ( has_post_thumbnail( $post ) ) : ?>
				<?php echo get_the_post_thumbnail( $post, 'large', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?>
				<span class="chidemoon-card__media-empty" aria-hidden="true"></span>
			<?php endif; ?>
			<?php if ( $category instanceof WP_Term ) : ?>
				<span class="chidemoon-card__badge"><?php echo esc_html( $category->name ); ?></span>
			<?php endif; ?>
		</a>
		<div class="chidemoon-card__body">
			<h3 class="chidemoon-card__title"><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a></h3>
			<?php if ( '' !== $excerpt ) : ?>
				<p class="chidemoon-card__excerpt"><?php echo esc_html( wp_trim_words( $excerpt, 18 ) ); ?></p>
			<?php endif; ?>
			<div class="chidemoon-card__footer">
				<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post ) ); ?>"><?php echo esc_html( get_the_date( '', $post ) ); ?></time>
				<a class="chidemoon-text-link" href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'ادامه مطلب', 'chidemoon-blocksy-child' ); ?></a>
			</div>
		</div>
	</article>
	<?php
}

/**
 * Resolves the imagery for an adjacent-post navigation card: the featured
 * image wins, then the first image attached to the post, then the first
 * media-library image used inside the content. Posts with none of these fall
 * back to the shared decorative placeholder, so the navigation never reads as
 * a bare text link.
 *
 * @param int $post_id Published post ID.
 */
function chidemoon_blocksy_navigation_image_id( int $post_id ): int {
	$thumbnail_id = (int) get_post_thumbnail_id( $post_id );
	if ( $thumbnail_id > 0 ) {
		return $thumbnail_id;
	}

	$attachments = get_attached_media( 'image', $post_id );
	if ( ! empty( $attachments ) ) {
		$attachment = reset( $attachments );
		if ( $attachment instanceof WP_Post ) {
			return (int) $attachment->ID;
		}
	}

	$post = get_post( $post_id );
	if ( $post instanceof WP_Post && 1 === preg_match( '/wp-image-(\d+)/', (string) $post->post_content, $matches ) ) {
		$attachment_id = (int) $matches[1];
		if ( $attachment_id > 0 && wp_attachment_is_image( $attachment_id ) ) {
			return $attachment_id;
		}
	}

	return 0;
}

/**
 * Renders one adjacent-post card for the article footer navigation. The card
 * leads with the sibling post's imagery so the journal feels continuous
 * instead of ending in text-only links.
 *
 * @param WP_Post|string|null $adjacent Adjacent post; WP core may also return an empty string when the journal ends here.
 * @param bool         $previous True for the previous card, false for next.
 */
function chidemoon_blocksy_render_navigation_card( $adjacent, bool $previous ): void {
	if ( ! $adjacent instanceof WP_Post || 'publish' !== get_post_status( $adjacent ) ) {
		return;
	}

	$permalink = get_permalink( $adjacent );
	$image_id  = chidemoon_blocksy_navigation_image_id( (int) $adjacent->ID );
	?>
	<div class="nav-<?php echo $previous ? 'previous' : 'next'; ?>">
		<a href="<?php echo esc_url( $permalink ); ?>" rel="<?php echo $previous ? 'prev' : 'next'; ?>">
			<span class="chidemoon-article__nav-media" aria-hidden="true">
				<?php if ( $image_id > 0 ) : ?>
					<?php echo wp_get_attachment_image( $image_id, 'medium', false, array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<span class="chidemoon-card__media-empty" aria-hidden="true"></span>
				<?php endif; ?>
			</span>
			<span class="chidemoon-article__nav-body">
				<span class="chidemoon-eyebrow"><?php echo esc_html( $previous ? __( 'مطلب قبلی', 'chidemoon-blocksy-child' ) : __( 'مطلب بعدی', 'chidemoon-blocksy-child' ) ); ?></span>
				<span class="chidemoon-article__nav-title"><?php echo esc_html( get_the_title( $adjacent ) ); ?></span>
			</span>
		</a>
	</div>
	<?php
}

/**
 * Catalogue sorting as a visible chip row inside the archive masthead. Each
 * option is a plain link WooCommerce already understands, so ordering works
 * without JavaScript and the active choice is part of the page's address.
 */
function chidemoon_blocksy_render_catalogue_sort(): void {
	$options = array(
		'menu_order' => __( 'پیشنهاد تحریریه', 'chidemoon-blocksy-child' ),
		'popularity' => __( 'محبوب‌ترین', 'chidemoon-blocksy-child' ),
		'rating'     => __( 'بهترین امتیاز', 'chidemoon-blocksy-child' ),
		'date'       => __( 'جدیدترین', 'chidemoon-blocksy-child' ),
		'price'      => __( 'ارزان‌ترین', 'chidemoon-blocksy-child' ),
		'price-desc' => __( 'گران‌ترین', 'chidemoon-blocksy-child' ),
	);

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'menu_order';
	if ( ! array_key_exists( $orderby, $options ) ) {
		$orderby = 'menu_order';
	}

	$current_term = is_product_taxonomy() ? get_queried_object() : null;
	if ( $current_term instanceof WP_Term ) {
		$base = get_term_link( $current_term );
	} else {
		$base = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
	}
	if ( ! is_string( $base ) || '' === $base || is_wp_error( $base ) ) {
		return;
	}
	?>
	<div class="chidemoon-shop-archive__sort">
		<p class="chidemoon-shop-archive__sort-label"><?php esc_html_e( 'مرتب‌سازی بر اساس', 'chidemoon-blocksy-child' ); ?></p>
		<div class="chidemoon-shop-archive__sort-options">
			<?php foreach ( $options as $key => $label ) : ?>
				<?php
				$href = 'menu_order' === $key ? remove_query_arg( 'orderby', $base ) : add_query_arg( 'orderby', $key, $base );
				printf(
					'<a href="%1$s"%2$s>%3$s</a>',
					esc_url( $href ),
					$key === $orderby ? ' class="is-active" aria-current="page"' : '',
					esc_html( $label )
				);
				?>
			<?php endforeach; ?>
		</div>
	</div>
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
		$eligible   = class_exists( 'Chidemoon_Core_Affiliate' ) && Chidemoon_Core_Affiliate::is_publicly_eligible( $product );
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
					<p class="chidemoon-product-card__pending"><?php esc_html_e( 'قیمت در حال بررسی', 'chidemoon-blocksy-child' ); ?></p>
				<?php endif; ?>
				<div class="chidemoon-product-card__actions">
					<?php if ( $eligible ) : ?>
						<a class="chidemoon-button" href="<?php echo esc_url( Chidemoon_Core_Affiliate::tracking_url( $product_id ) ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php esc_html_e( 'خرید از فروشگاه', 'chidemoon-blocksy-child' ); ?></a>
						<?php echo Chidemoon_Core_Compare::control( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php else : ?>
						<a class="chidemoon-button" href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'مشاهده محصول', 'chidemoon-blocksy-child' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</article>
		<?php
	}
}

/**
 * A deliberate empty state avoids visual placeholder content before the
 * editorial team has reviewed real items for publication.
 */
function chidemoon_blocksy_render_empty_state( string $title, string $description, string $action_url = '', string $action_label = '' ): void {
	?>
	<div class="chidemoon-empty-state">
		<span class="chidemoon-empty-state__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M12 4v16M3 9.5h18"/></svg></span>
		<div>
			<h3><?php echo esc_html( $title ); ?></h3>
			<p><?php echo esc_html( $description ); ?></p>
			<?php if ( '' !== $action_url && '' !== $action_label ) : ?>
				<a class="chidemoon-button" href="<?php echo esc_url( $action_url ); ?>"><?php echo esc_html( $action_label ); ?></a>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
