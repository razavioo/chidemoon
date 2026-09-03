<?php
/**
 * The home page is a public, content-led magazine composition. Every card is
 * sourced from published WordPress or WooCommerce records, so an unprepared
 * launch stays honest instead of displaying made-up editorial material.
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

get_header();

$stories = new WP_Query(
array(
'post_type'           => 'post',
'post_status'         => 'publish',
'posts_per_page'      => 9,
'ignore_sticky_posts' => true,
'no_found_rows'       => true,
)
);
$story_ids      = array();
$featured_story = null;
$side_stories   = array();
$grid_stories   = array();
if ( $stories->have_posts() ) {
while ( $stories->have_posts() ) {
$stories->the_post();
$story_ids[] = get_the_ID();
}
wp_reset_postdata();

$featured_story = get_post( $story_ids[0] );
$side_stories   = array_slice( $story_ids, 1, 2 );
$grid_stories   = array_slice( $story_ids, 3 );
}
$products           = function_exists( 'wc_get_products' )
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
if ( ! is_wp_error( $product_categories ) ) {
	$product_categories = array_filter(
		$product_categories,
		static fn( $category ): bool => $category instanceof WP_Term && 'uncategorized' !== $category->slug
	);
}
$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : chidemoon_blocksy_page_url( 'shop' );
$hero_stats = chidemoon_home_hero_stats();
?>

<main id="primary" class="site-main chidemoon-home">

<section class="chidemoon-home__hero chidemoon-section-shell" aria-label="معرفی چیدمون">
	<div class="chidemoon-home__hero-copy">
		<p class="chidemoon-eyebrow"><?php echo esc_html( get_bloginfo( 'name' ) ); ?> · <?php esc_html_e( 'مجله و فروشگاه چیدمان خانه', 'chidemoon-blocksy-child' ); ?></p>
		<h1><?php esc_html_e( 'خانه را با شواهد بچین، نه با تبلیغ', 'chidemoon-blocksy-child' ); ?></h1>
		<p class="chidemoon-home__lede"><?php esc_html_e( 'چیدمون راهنمای انتخاب و مقایسه‌ی کالاهای خانه است. هر مطلب از تحریریه می‌گذرد و هر کالا پیش از انتشار، از نظر تصویر، دسته‌بندی و مقصد فروشنده بررسی می‌شود.', 'chidemoon-blocksy-child' ); ?></p>
		<div class="chidemoon-home__actions">
			<a class="chidemoon-button" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'گشت در کالاهای منتخب', 'chidemoon-blocksy-child' ); ?></a>
			<a class="chidemoon-text-link" href="<?php echo esc_url( chidemoon_blocksy_page_url( 'magazine' ) ); ?>"><?php esc_html_e( 'مطالعه‌ی مجله', 'chidemoon-blocksy-child' ); ?></a>
		</div>
		<?php if ( ! empty( $hero_stats ) ) : ?>
		<dl class="chidemoon-home__hero-meta">
			<?php foreach ( $hero_stats as $stat_label => $stat_count ) : ?>
			<div>
				<dt><?php echo esc_html( $stat_label ); ?></dt>
				<dd><?php echo esc_html( chidemoon_fa_digits( $stat_count ) ); ?></dd>
			</div>
			<?php endforeach; ?>
		</dl>
		<?php endif; ?>
	</div>
	<div class="chidemoon-home__hero-mark" aria-hidden="true">
		<span><?php echo esc_html( mb_substr( get_bloginfo( 'name' ), 0, 1 ) ); ?></span>
		<i></i>
		<b></b>
	</div>
</section>

<?php if ( $featured_story instanceof WP_Post ) : ?>
<section class="chidemoon-home__top chidemoon-section-shell" aria-label="برجسته‌ترین مطالب">
<?php
$featured_id         = (int) $featured_story->ID;
$featured_category   = chidemoon_blocksy_primary_category( $featured_id );
?>
<article class="chidemoon-featured">
<a class="chidemoon-featured__media" href="<?php echo esc_url( get_permalink( $featured_story ) ); ?>" aria-label="<?php echo esc_attr( get_the_title( $featured_story ) ); ?>">
<?php if ( has_post_thumbnail( $featured_story ) ) : ?>
<?php echo get_the_post_thumbnail( $featured_story, 'large', array( 'fetchpriority' => 'high', 'loading' => 'eager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php else : ?>
<span class="chidemoon-card__media-empty" aria-hidden="true"></span>
<?php endif; ?>
</a>
<div class="chidemoon-featured__body">
<?php if ( $featured_category instanceof WP_Term ) : ?>
<a class="chidemoon-card__badge chidemoon-card__badge--inline" href="<?php echo esc_url( get_category_link( $featured_category->term_id ) ); ?>"><?php echo esc_html( $featured_category->name ); ?></a>
<?php endif; ?>
<h1 class="chidemoon-featured__title"><a href="<?php echo esc_url( get_permalink( $featured_story ) ); ?>"><?php echo esc_html( get_the_title( $featured_story ) ); ?></a></h1>
<p class="chidemoon-featured__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt( $featured_story ), 32 ) ); ?></p>
<div class="chidemoon-featured__meta">
<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $featured_story ) ); ?>"><?php echo esc_html( get_the_date( '', $featured_story ) ); ?></time>
<a class="chidemoon-text-link" href="<?php echo esc_url( get_permalink( $featured_story ) ); ?>">خواندن راهنما</a>
</div>
</div>
</article>

<div class="chidemoon-side-stories">
<?php foreach ( $side_stories as $side_id ) : ?>
<?php
$side_post        = get_post( $side_id );
$side_category    = chidemoon_blocksy_primary_category( $side_id );
?>
<article class="chidemoon-side-story">
<a class="chidemoon-side-story__media" href="<?php echo esc_url( get_permalink( $side_post ) ); ?>" aria-label="<?php echo esc_attr( get_the_title( $side_post ) ); ?>">
<?php if ( has_post_thumbnail( $side_post ) ) : ?>
<?php echo get_the_post_thumbnail( $side_post, 'medium', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php else : ?>
<span class="chidemoon-card__media-empty" aria-hidden="true"></span>
<?php endif; ?>
</a>
<div class="chidemoon-side-story__body">
<?php if ( $side_category instanceof WP_Term ) : ?>
<span class="chidemoon-side-story__category"><?php echo esc_html( $side_category->name ); ?></span>
<?php endif; ?>
<h2 class="chidemoon-side-story__title"><a href="<?php echo esc_url( get_permalink( $side_post ) ); ?>"><?php echo esc_html( get_the_title( $side_post ) ); ?></a></h2>
<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $side_post ) ); ?>"><?php echo esc_html( get_the_date( '', $side_post ) ); ?></time>
</div>
</article>
<?php endforeach; ?>
<a class="chidemoon-side-story__all" href="<?php echo esc_url( chidemoon_blocksy_page_url( 'magazine' ) ); ?>">همه مطالب مجله</a>
</div>
</section>
<?php endif; ?>
<?php if ( ! empty( $grid_stories ) ) : ?>
<section class="chidemoon-home__section chidemoon-section-shell" aria-labelledby="chidemoon-latest-heading">
<div class="chidemoon-section-heading">
<div>
<h2 id="chidemoon-latest-heading">آخرین مطالب</h2>
</div>
<a class="chidemoon-text-link" href="<?php echo esc_url( chidemoon_blocksy_page_url( 'magazine' ) ); ?>">آرشیو مجله</a>
</div>
<div class="chidemoon-card-grid chidemoon-card-grid--stories">
<?php foreach ( $grid_stories as $grid_index => $grid_id ) : ?>
<?php chidemoon_blocksy_render_post_card( (int) $grid_id, 0 === $grid_index ? 'lead' : 'compact' ); ?>
<?php endforeach; ?>
</div>
</section>
<?php endif; ?>

<section class="chidemoon-home__section chidemoon-section-shell" aria-labelledby="chidemoon-products-heading">
<div class="chidemoon-section-heading">
<div>
<h2 id="chidemoon-products-heading">کالاهای برگزیده</h2>
</div>
<a class="chidemoon-text-link" href="<?php echo esc_url( $shop_url ); ?>">همه کالاها</a>
</div>

<?php if ( ! empty( $products ) ) : ?>
<div class="chidemoon-card-grid chidemoon-card-grid--products">
<?php chidemoon_blocksy_render_product_cards( $products ); ?>
</div>
<?php else : ?>
<?php chidemoon_blocksy_render_empty_state( 'کاتالوگ در حال آماده‌سازی است.', 'کالاها فقط پس از تأیید دسته‌بندی، تصویر و مقصد فروشنده منتشر می‌شوند.' ); ?>
<?php endif; ?>
</section>

<?php if ( ! is_wp_error( $product_categories ) && ! empty( $product_categories ) ) : ?>
<section class="chidemoon-home__section chidemoon-section-shell" aria-labelledby="chidemoon-cats-heading">
<div class="chidemoon-section-heading">
<div>
<h2 id="chidemoon-cats-heading">دسته‌بندی فروشگاه</h2>
</div>
</div>
<div class="chidemoon-category-grid">
<?php foreach ( $product_categories as $category ) : ?>
<?php $category_link = get_term_link( $category ); ?>
<?php if ( ! is_wp_error( $category_link ) ) : ?>
<a class="chidemoon-category-link" href="<?php echo esc_url( $category_link ); ?>">
<span class="chidemoon-category-link__art" aria-hidden="true"><?php echo chidemoon_category_art( $category ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
<span class="chidemoon-category-link__body">
<span class="chidemoon-category-link__title"><?php echo esc_html( $category->name ); ?></span>
<span class="chidemoon-category-link__count"><?php echo esc_html( chidemoon_fa_digits( $category->count ) ); ?> کالا</span>
</span>
<span class="chidemoon-category-link__go" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" class="chidemoon-cat-art" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path class="catart-ink" d="M14.5 5.5 8 12l6.5 6.5" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
</a>
<?php endif; ?>
<?php endforeach; ?>
</div>
</section>
<?php endif; ?>

<section class="chidemoon-home__routes chidemoon-section-shell" aria-label="فرمت‌های چیدمون">
<a class="chidemoon-route-card" href="<?php echo esc_url( chidemoon_blocksy_page_url( 'comparisons' ) ); ?>">
<span class="chidemoon-eyebrow">دو تا چهار کالا</span>
<strong>مقایسه‌های شفاف</strong>
<span>شواهد بررسی‌شده، مبادلات روشن، بدون توصیه‌ی خودکار.</span>
</a>
<a class="chidemoon-route-card chidemoon-route-card--clay" href="<?php echo esc_url( chidemoon_blocksy_page_url( 'shop-the-look' ) ); ?>">
<span class="chidemoon-eyebrow">یک اتاق را بچین</span>
<strong>از تصویر بخر</strong>
<span>ترکیب‌های ادیتوریال متصل به پیشنهاد مستقیم فروشنده.</span>
</a>
</section>
</main>

<?php
wp_reset_postdata();
get_footer();