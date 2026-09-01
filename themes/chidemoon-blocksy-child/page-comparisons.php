<?php
/** Public product comparison page. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$core_ready = class_exists( 'Chidemoon_Core_Compare' ) && class_exists( 'Chidemoon_Core_Affiliate' );
$products = $core_ready ? Chidemoon_Core_Compare::products_from_request() : array();
$facts_by_product_id = array();
$fact_labels         = array();
foreach ( $products as $product ) {
	$facts_by_product_id[ $product->get_id() ] = Chidemoon_Core_Compare::facts( $product );
	foreach ( $facts_by_product_id[ $product->get_id() ] as $label => $value ) {
		$fact_labels[ $label ] = $label;
	}
}
$fact_labels = array_values( $fact_labels );
$catalogue = $core_ready ? Chidemoon_Core_Compare::catalogue_products() : array();
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
	<?php while ( have_posts() ) : the_post(); ?>
		<header class="chidemoon-collection-page__hero chidemoon-section-shell">
			<p class="chidemoon-eyebrow"><?php esc_html_e( 'مقایسه‌های مبتنی بر شواهد', 'chidemoon-blocksy-child' ); ?></p>
			<h1><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?><p><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
		</header>
		<?php if ( has_post_thumbnail() ) : ?><figure class="chidemoon-collection-page__image chidemoon-section-shell"><?php the_post_thumbnail( 'large', array( 'loading' => 'eager' ) ); ?></figure><?php endif; ?>
		<?php if ( '' !== trim( wp_strip_all_tags( get_the_content() ) ) ) : ?><section class="chidemoon-collection-page__content chidemoon-collection-page__content--single chidemoon-section-shell"><div class="entry-content"><?php the_content(); ?></div></section><?php endif; ?>
	<?php endwhile; ?>

	<?php if ( $core_ready ) : ?>
		<?php Chidemoon_Core_Compare::enqueue_assets(); ?>
		<section class="chidemoon-comparison-picker chidemoon-section-shell" aria-labelledby="chidemoon-picker-title">
			<div class="chidemoon-section-heading"><div><p class="chidemoon-eyebrow"><?php esc_html_e( 'انتخاب محصول', 'chidemoon-blocksy-child' ); ?></p><h2 id="chidemoon-picker-title"><?php esc_html_e( 'تا چهار محصول را انتخاب کنید', 'chidemoon-blocksy-child' ); ?></h2></div></div>
			<p><?php esc_html_e( 'از هر کارت «مقایسه» را بزنید. انتخاب‌های شما تا زمانی که خودتان حذفشان کنید در مرورگر باقی می‌مانند.', 'chidemoon-blocksy-child' ); ?></p>
			<div class="chidemoon-comparison-search" data-comparison-search>
				<label for="chidemoon-product-search"><?php esc_html_e( 'جست‌وجوی محصول برای مقایسه', 'chidemoon-blocksy-child' ); ?></label>
				<div class="chidemoon-comparison-search__field"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6"></circle><path d="m16 16 4 4"></path></svg><input id="chidemoon-product-search" type="search" placeholder="<?php esc_attr_e( 'نام محصول را بنویسید…', 'chidemoon-blocksy-child' ); ?>" autocomplete="off" data-comparison-search-input></div>
				<p class="chidemoon-comparison-search__hint"><?php esc_html_e( 'برای شروع، نام محصول موردنظر را جست‌وجو و آن را به مقایسه اضافه کنید.', 'chidemoon-blocksy-child' ); ?></p>
				<div class="chidemoon-comparison-search__results" data-comparison-search-results aria-live="polite" aria-atomic="true" hidden></div>
			</div>
			<?php if ( ! empty( $catalogue ) ) : ?>
				<div class="chidemoon-section-heading chidemoon-comparison-picker__catalogue-heading"><div><p class="chidemoon-eyebrow"><?php esc_html_e( 'محصولات پیشنهادی', 'chidemoon-blocksy-child' ); ?></p><h3><?php esc_html_e( 'از اینجا شروع کنید', 'chidemoon-blocksy-child' ); ?></h3></div></div>
				<div class="chidemoon-card-grid chidemoon-card-grid--products" data-comparison-catalogue><?php chidemoon_blocksy_render_product_cards( $catalogue ); ?></div>
			<?php endif; ?>
		</section>

		<section class="chidemoon-comparison-table-section chidemoon-section-shell" aria-labelledby="chidemoon-table-title">
			<div class="chidemoon-section-heading"><div><p class="chidemoon-eyebrow"><?php esc_html_e( 'جدول مقایسه', 'chidemoon-blocksy-child' ); ?></p><h2 id="chidemoon-table-title"><?php esc_html_e( 'تفاوت‌ها را کنار هم ببینید', 'chidemoon-blocksy-child' ); ?></h2></div></div>
			<?php if ( count( $products ) < 2 ) : ?>
				<?php chidemoon_blocksy_render_empty_state( 'حداقل دو محصول انتخاب کنید.', 'از کارت‌های بالا یا هر کارت محصول دیگر، گزینهٔ «مقایسه» را بزنید و سپس به اینجا برگردید.' ); ?>
			<?php else : ?>
				<div class="chidemoon-comparison-table-wrap" tabindex="0" aria-label="<?php esc_attr_e( 'جدول مقایسه محصولات', 'chidemoon-blocksy-child' ); ?>">
					<table class="chidemoon-comparison-table">
						<thead><tr><th scope="col"><?php esc_html_e( 'محصول', 'chidemoon-blocksy-child' ); ?></th><?php foreach ( $products as $product ) : ?><th scope="col"><a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"><?php echo wp_get_attachment_image( $product->get_image_id(), 'woocommerce_thumbnail', false, array( 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $product->get_name() ); ?></span></a><button type="button" class="chidemoon-comparison-table__remove" data-compare-product="<?php echo esc_attr( $product->get_id() ); ?>" data-compare-name="<?php echo esc_attr( $product->get_name() ); ?>" aria-label="<?php echo esc_attr( sprintf( 'حذف %s از مقایسه', $product->get_name() ) ); ?>"><?php esc_html_e( 'حذف', 'chidemoon-blocksy-child' ); ?></button></th><?php endforeach; ?></tr></thead>
						<tbody>
							<tr><th scope="row"><?php esc_html_e( 'قیمت', 'chidemoon-blocksy-child' ); ?></th><?php foreach ( $products as $product ) : ?><td><?php echo wp_kses_post( $product->get_price_html() ); ?></td><?php endforeach; ?></tr>
							<tr><th scope="row"><?php esc_html_e( 'فروشنده', 'chidemoon-blocksy-child' ); ?></th><?php foreach ( $products as $product ) : ?><td><?php echo esc_html( (string) $product->get_meta( Chidemoon_Core_Affiliate::META_MERCHANT_NAME, true ) ?: '—' ); ?></td><?php endforeach; ?></tr>
							<tr><th scope="row"><?php esc_html_e( 'دسته‌بندی', 'chidemoon-blocksy-child' ); ?></th><?php foreach ( $products as $product ) : ?><td><?php $categories = wc_get_product_category_list( $product->get_id(), '، ' ); echo esc_html( $categories ? wp_strip_all_tags( $categories ) : '—' ); ?></td><?php endforeach; ?></tr>
							<?php foreach ( $fact_labels as $label ) : ?><tr><th scope="row"><?php echo esc_html( $label ); ?></th><?php foreach ( $products as $product ) : ?><td><?php echo esc_html( $facts_by_product_id[ $product->get_id() ][ $label ] ?? '—' ); ?></td><?php endforeach; ?></tr><?php endforeach; ?>
							<tr><th scope="row"><?php esc_html_e( 'خرید', 'chidemoon-blocksy-child' ); ?></th><?php foreach ( $products as $product ) : ?><td><a class="chidemoon-button" href="<?php echo esc_url( Chidemoon_Core_Affiliate::tracking_url( $product->get_id() ) ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php esc_html_e( 'خرید از فروشگاه', 'chidemoon-blocksy-child' ); ?></a></td><?php endforeach; ?></tr>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<section class="chidemoon-section-shell chidemoon-collection-page__feed" aria-labelledby="chidemoon-comparisons-feed">
		<div class="chidemoon-section-heading"><div><p class="chidemoon-eyebrow"><?php esc_html_e( 'در مجله', 'chidemoon-blocksy-child' ); ?></p><h2 id="chidemoon-comparisons-feed"><?php esc_html_e( 'راهنماهای مقایسه', 'chidemoon-blocksy-child' ); ?></h2></div></div>
		<?php if ( $comparison_posts->have_posts() ) : ?><div class="chidemoon-card-grid chidemoon-card-grid--archive"><?php while ( $comparison_posts->have_posts() ) : $comparison_posts->the_post(); chidemoon_blocksy_render_post_card( get_the_ID(), 'compact', 3 ); endwhile; ?></div><?php wp_reset_postdata(); ?><?php else : ?><?php chidemoon_blocksy_render_empty_state( 'اولین مقایسه در حال بررسی است.', 'فقط مقایسه‌هایی که واقعیت کالاهایشان بررسی شده باشد منتشر می‌شوند.' ); ?><?php endif; ?>
	</section>
</main>
<?php wp_reset_postdata(); get_footer();
