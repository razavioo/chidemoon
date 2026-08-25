<?php
/**
 * Server-side render for the public catalog block.
 *
 * Query-string filters keep the first response complete and indexable while
 * the view script adds only local favorites/comparison state.
 *
 * @var array $attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$param = static function ( string $name, string $kind = 'text' ): string {
	$value = isset( $_GET[ $name ] ) ? (string) wp_unslash( $_GET[ $name ] ) : '';
	if ( 'key' === $kind ) {
		return sanitize_key( $value );
	}
	if ( 'slug' === $kind ) {
		return sanitize_title( $value );
	}
	return sanitize_text_field( $value );
};

$page       = max( 1, absint( $param( 'kc_page' ) ) );
$per_page   = max( 4, min( 24, absint( $attributes['perPage'] ?? 12 ) ) );
$columns    = max( 2, min( 4, absint( $attributes['columns'] ?? 3 ) ) );
$search     = $param( 'kc_search' );
if ( '' === $search && is_search() ) {
	$search = sanitize_text_field( get_search_query( false ) );
}
$collection = sanitize_title( (string) ( $attributes['collection'] ?? '' ) );
$category   = $param( 'kc_category', 'slug' );
$brand      = $param( 'kc_brand', 'slug' );
$platform   = $param( 'kc_platform', 'key' );
$sort       = $param( 'kc_sort', 'key' ) ?: 'newest';
$min_price  = $param( 'kc_min_price' );
$max_price  = $param( 'kc_max_price' );
$allowed_sort = array( 'newest', 'price_asc', 'price_desc', 'title_asc' );
$sort       = in_array( $sort, $allowed_sort, true ) ? $sort : 'newest';

$catalog = Kalahamoon_Product_Cache::get_all(
	array(
		'page'         => $page,
		'limit'        => $per_page,
		'search'       => $search,
		'collection'   => $collection,
		'category'     => $category,
		'brand'        => $brand,
		'platform'     => $platform,
		'min_price'    => is_numeric( $min_price ) ? (float) $min_price : null,
		'max_price'    => is_numeric( $max_price ) ? (float) $max_price : null,
		'sort'         => $sort,
		'public_ready' => true,
	)
);

$products    = is_array( $catalog['items'] ?? null ) ? $catalog['items'] : array();
$total       = (int) ( $catalog['total'] ?? 0 );
$total_pages = max( 0, (int) ( $catalog['totalPages'] ?? 0 ) );
$public_total = Kalahamoon_Product_Cache::public_ready_count();
$has_active_filters = '' !== $search
	|| '' !== $collection
	|| '' !== $category
	|| '' !== $brand
	|| '' !== $platform
	|| '' !== $min_price
	|| '' !== $max_price
	|| 'newest' !== $sort
	|| $page > 1;
$is_prelaunch = 0 === $public_total;
$is_no_results = ! $is_prelaunch && empty( $products );
$recovery_url = is_search()
	? home_url( '/' )
	: remove_query_arg( array( 'kc_page', 'kc_search', 'kc_category', 'kc_brand', 'kc_platform', 'kc_min_price', 'kc_max_price', 'kc_sort' ), get_permalink() ?: home_url( '/' ) );
$heading     = trim( (string) ( $attributes['heading'] ?? '' ) );
$description = trim( (string) ( $attributes['description'] ?? '' ) );
if ( '' === $heading ) {
	$heading = __( 'Verified product catalog', 'kalahamoon' );
}
$show_filters   = ! isset( $attributes['showFilters'] ) || ! empty( $attributes['showFilters'] );
$show_quick     = ! isset( $attributes['showQuickView'] ) || ! empty( $attributes['showQuickView'] );
$show_favorites = ! isset( $attributes['showFavorites'] ) || ! empty( $attributes['showFavorites'] );
$show_compare   = ! isset( $attributes['showCompare'] ) || ! empty( $attributes['showCompare'] );

$categories = get_terms( array( 'taxonomy' => 'kalahamoon_category', 'hide_empty' => true, 'number' => 100 ) );
$brands     = get_terms( array( 'taxonomy' => 'kalahamoon_brand', 'hide_empty' => true, 'number' => 100 ) );
$categories = is_wp_error( $categories ) ? array() : $categories;
$brands     = is_wp_error( $brands ) ? array() : $brands;
$platforms  = array( 'basalam', 'digikala', 'woocommerce', 'shopify', 'wordpress' );

$wrapper = get_block_wrapper_attributes(
	array(
		'class'                   => 'kalahamoon-catalog' . ( $is_prelaunch ? ' kalahamoon-catalog--prelaunch' : '' ),
		'data-kalahamoon-catalog' => '1',
		'data-compare-url'        => home_url( '/compare/' ),
		'data-compare-selected'   => __( '{count} selected', 'kalahamoon' ),
		'data-compare-mixed'      => __( 'Choose products from the same comparison type.', 'kalahamoon' ),
		'data-compare-maximum'    => __( 'You can compare up to four products.', 'kalahamoon' ),
		'style'                   => '--kalahamoon-catalog-columns:' . $columns,
	)
);
?>
<section <?php echo $wrapper; ?>>
	<?php if ( '' !== $heading || '' !== $description ) : ?>
		<header class="kalahamoon-catalog__header">
			<h2><?php echo esc_html( $heading ); ?></h2>
			<?php if ( '' !== $description ) : ?><p><?php echo esc_html( $description ); ?></p><?php endif; ?>
		</header>
	<?php endif; ?>

	<?php if ( $show_filters && ! $is_prelaunch ) : ?>
		<form class="kalahamoon-catalog__filters" method="get" action="<?php echo esc_url( get_permalink() ?: home_url( '/' ) ); ?>">
			<label class="kalahamoon-catalog__search">
				<span><?php esc_html_e( 'Search products', 'kalahamoon' ); ?></span>
				<input type="search" name="kc_search" value="<?php echo esc_attr( $search ); ?>" />
			</label>
			<label>
				<span><?php esc_html_e( 'Category', 'kalahamoon' ); ?></span>
				<select name="kc_category">
					<option value=""><?php esc_html_e( 'All categories', 'kalahamoon' ); ?></option>
					<?php foreach ( $categories as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $category, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Brand', 'kalahamoon' ); ?></span>
				<select name="kc_brand">
					<option value=""><?php esc_html_e( 'All brands', 'kalahamoon' ); ?></option>
					<?php foreach ( $brands as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $brand, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Store', 'kalahamoon' ); ?></span>
				<select name="kc_platform">
					<option value=""><?php esc_html_e( 'All stores', 'kalahamoon' ); ?></option>
					<?php foreach ( $platforms as $platform_option ) : ?>
						<option value="<?php echo esc_attr( $platform_option ); ?>" <?php selected( $platform, $platform_option ); ?>><?php echo esc_html( ucfirst( $platform_option ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Minimum price', 'kalahamoon' ); ?></span>
				<input type="number" min="0" step="1" name="kc_min_price" value="<?php echo esc_attr( $min_price ); ?>" inputmode="numeric" />
			</label>
			<label>
				<span><?php esc_html_e( 'Maximum price', 'kalahamoon' ); ?></span>
				<input type="number" min="0" step="1" name="kc_max_price" value="<?php echo esc_attr( $max_price ); ?>" inputmode="numeric" />
			</label>
			<label>
				<span><?php esc_html_e( 'Sort', 'kalahamoon' ); ?></span>
				<select name="kc_sort">
					<option value="newest" <?php selected( $sort, 'newest' ); ?>><?php esc_html_e( 'Newest', 'kalahamoon' ); ?></option>
					<option value="price_asc" <?php selected( $sort, 'price_asc' ); ?>><?php esc_html_e( 'Price: low to high', 'kalahamoon' ); ?></option>
					<option value="price_desc" <?php selected( $sort, 'price_desc' ); ?>><?php esc_html_e( 'Price: high to low', 'kalahamoon' ); ?></option>
					<option value="title_asc" <?php selected( $sort, 'title_asc' ); ?>><?php esc_html_e( 'Title', 'kalahamoon' ); ?></option>
				</select>
			</label>
			<button type="submit"><?php esc_html_e( 'Apply filters', 'kalahamoon' ); ?></button>
			<a href="<?php echo esc_url( get_permalink() ?: home_url( '/' ) ); ?>"><?php esc_html_e( 'Clear', 'kalahamoon' ); ?></a>
		</form>
	<?php endif; ?>

	<?php if ( ! $is_prelaunch ) : ?>
		<div class="kalahamoon-catalog__summary" aria-live="polite">
			<?php echo esc_html( sprintf( _n( '%s product', '%s products', $total, 'kalahamoon' ), number_format_i18n( $total ) ) ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $is_prelaunch ) : ?>
		<div class="kalahamoon-catalog__prelaunch" role="status">
			<h3><?php esc_html_e( 'Products are being reviewed before publication.', 'kalahamoon' ); ?></h3>
			<p><?php esc_html_e( 'We only show verified products with current details. While this catalog is being prepared, use a buying guide or the magazine to make your next decision.', 'kalahamoon' ); ?></p>
			<div class="kalahamoon-catalog__recovery-actions">
				<a href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'Explore buying guides', 'kalahamoon' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/magazine/' ) ); ?>"><?php esc_html_e( 'Read the magazine', 'kalahamoon' ); ?></a>
			</div>
		</div>
	<?php elseif ( $is_no_results ) : ?>
		<div class="kalahamoon-catalog__empty">
			<h3><?php echo esc_html( $has_active_filters ? __( 'No verified products match your current filters.', 'kalahamoon' ) : __( 'No launch-ready products are available right now.', 'kalahamoon' ) ); ?></h3>
			<p><?php esc_html_e( 'Try a broader search, clear the filters, or return after the catalog has been refreshed.', 'kalahamoon' ); ?></p>
			<div class="kalahamoon-catalog__recovery-actions">
				<?php if ( $has_active_filters ) : ?><a href="<?php echo esc_url( $recovery_url ); ?>"><?php esc_html_e( 'Clear search and filters', 'kalahamoon' ); ?></a><?php endif; ?>
				<a href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'Explore buying guides', 'kalahamoon' ); ?></a>
			</div>
		</div>
	<?php else : ?>
		<div class="kalahamoon-catalog__grid">
			<?php foreach ( $products as $product ) :
				$destination    = Kalahamoon_Link_Builder::resolve_product_destination( $product );
				$affiliate_url  = $destination['url'];
				$link_attrs     = Kalahamoon_Link_Builder::public_link_attributes( $destination );
				$detail_url     = esc_url_raw( (string) apply_filters( 'kalahamoon_product_public_url', '', $product ) );
				$comparison     = is_array( $product['comparisonType'] ?? null ) ? (string) ( $product['comparisonType']['key'] ?? $product['comparisonType']['slug'] ?? '' ) : '';
				$description_text = wp_trim_words( wp_strip_all_tags( (string) ( $product['description'] ?? '' ) ), 28 );
			?>
				<article class="kalahamoon-catalog-card kalahamoon-product-card" data-product-id="<?php echo esc_attr( $product['id'] ); ?>" data-comparison-type="<?php echo esc_attr( $comparison ); ?>" data-track-recent="1">
					<div class="kalahamoon-catalog-card__media">
						<?php if ( '' !== $detail_url ) : ?><a class="kalahamoon-catalog-card__detail-link" href="<?php echo esc_url( $detail_url ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View details for %s', 'kalahamoon' ), $product['title'] ) ); ?>"><?php endif; ?>
						<img src="<?php echo esc_url( $product['imageUrl'] ); ?>" alt="<?php echo esc_attr( $product['title'] ); ?>" loading="lazy" decoding="async" />
						<?php if ( '' !== $detail_url ) : ?></a><?php endif; ?>
						<?php if ( $show_favorites ) : ?>
							<button class="kalahamoon-favorite-btn" type="button" data-product-id="<?php echo esc_attr( $product['id'] ); ?>" data-label-save="<?php esc_attr_e( 'Save to favorites', 'kalahamoon' ); ?>" data-label-remove="<?php esc_attr_e( 'Remove from favorites', 'kalahamoon' ); ?>" aria-label="<?php esc_attr_e( 'Save to favorites', 'kalahamoon' ); ?>" aria-pressed="false">
								<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z" /></svg>
							</button>
						<?php endif; ?>
					</div>
					<div class="kalahamoon-catalog-card__body">
						<p class="kalahamoon-catalog-card__meta"><?php echo esc_html( $product['brand'] ?: $product['category'] ?: $product['platform'] ); ?></p>
						<h3><?php if ( '' !== $detail_url ) : ?><a href="<?php echo esc_url( $detail_url ); ?>"><?php endif; ?><?php echo esc_html( $product['title'] ); ?><?php if ( '' !== $detail_url ) : ?></a><?php endif; ?></h3>
						<?php if ( ! empty( $product['priceVisible'] ) && (float) $product['price'] > 0 ) : ?>
							<p class="kalahamoon-catalog-card__price"><?php echo esc_html( Kalahamoon_RTL::format_price( (float) $product['price'], (string) $product['currency'] ) ); ?></p>
							<?php if ( 'stale' === ( $product['priceFreshness'] ?? '' ) ) : ?><p class="kalahamoon-catalog-card__freshness"><?php esc_html_e( 'Price checked more than 12 hours ago.', 'kalahamoon' ); ?></p><?php endif; ?>
						<?php else : ?>
							<p class="kalahamoon-catalog-card__freshness"><?php esc_html_e( 'Price temporarily hidden until the next verified refresh.', 'kalahamoon' ); ?></p>
						<?php endif; ?>

						<?php if ( $show_quick && '' !== $description_text ) : ?>
							<details class="kalahamoon-catalog-card__quick">
								<summary><?php esc_html_e( 'Quick view', 'kalahamoon' ); ?></summary>
								<p><?php echo esc_html( $description_text ); ?></p>
							</details>
						<?php endif; ?>

						<div class="kalahamoon-catalog-card__actions">
							<?php if ( '' !== $detail_url ) : ?><a class="kalahamoon-catalog-card__details" href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'Details', 'kalahamoon' ); ?></a><?php endif; ?>
							<?php if ( '' !== $affiliate_url ) : ?><a class="kalahamoon-catalog-card__buy <?php echo esc_attr( $link_attrs['class'] ); ?>" href="<?php echo esc_url( $affiliate_url ); ?>" target="_blank" rel="<?php echo esc_attr( $link_attrs['rel'] ); ?>" data-product-id="<?php echo esc_attr( $product['id'] ); ?>" data-link-id="<?php echo esc_attr( $link_attrs['linkId'] ); ?>" data-link-kind="<?php echo esc_attr( $link_attrs['kind'] ); ?>" data-block-type="product-catalog"><?php esc_html_e( 'View product', 'kalahamoon' ); ?></a><?php endif; ?>
							<?php if ( $show_compare && '' !== $comparison ) : ?><button class="kalahamoon-catalog-card__compare" type="button" data-product-id="<?php echo esc_attr( $product['id'] ); ?>" data-comparison-type="<?php echo esc_attr( $comparison ); ?>" aria-pressed="false"><?php esc_html_e( 'Compare', 'kalahamoon' ); ?></button><?php endif; ?>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $show_compare && ! $is_prelaunch ) : ?>
		<div class="kalahamoon-catalog__compare-tray" data-compare-tray hidden>
			<span data-compare-status role="status" aria-live="polite"></span>
			<a href="<?php echo esc_url( home_url( '/compare/' ) ); ?>" data-compare-link><?php esc_html_e( 'Open comparison', 'kalahamoon' ); ?></a>
			<button type="button" data-compare-clear><?php esc_html_e( 'Clear', 'kalahamoon' ); ?></button>
		</div>
	<?php endif; ?>

	<?php if ( $total_pages > 1 ) : ?>
		<nav class="kalahamoon-catalog__pagination" aria-label="<?php esc_attr_e( 'Product catalog pages', 'kalahamoon' ); ?>">
			<?php if ( $page > 1 ) : ?><a rel="prev" href="<?php echo esc_url( add_query_arg( 'kc_page', $page - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'kalahamoon' ); ?></a><?php endif; ?>
			<span><?php echo esc_html( sprintf( __( 'Page %1$s of %2$s', 'kalahamoon' ), number_format_i18n( $page ), number_format_i18n( $total_pages ) ) ); ?></span>
			<?php if ( $page < $total_pages ) : ?><a rel="next" href="<?php echo esc_url( add_query_arg( 'kc_page', $page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'kalahamoon' ); ?></a><?php endif; ?>
		</nav>
	<?php endif; ?>
</section>
