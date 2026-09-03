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
		add_theme_support( 'editor-styles' );
		add_editor_style( array( 'assets/css/typography.css', 'assets/css/editor.css' ) );
	}
);

function chidemoon_blocksy_enqueue_styles(): void {
	$version = (string) wp_get_theme()->get( 'Version' );

	// Static assets change between sealed releases while the theme header
	// version may not; bust edge/browser caches with the file's own mtime.
	$asset_version = static function ( string $relative ) use ( $version ): string {
		$mtime = @filemtime( get_stylesheet_directory() . '/' . $relative );
		return $mtime ? $version . '.' . $mtime : $version;
	};

	wp_enqueue_style(
		'chidemoon-typography',
		get_stylesheet_directory_uri() . '/assets/css/typography.css',
		array(),
		$asset_version( 'assets/css/typography.css' )
	);
	wp_enqueue_style(
		'chidemoon-blocksy-child',
		get_stylesheet_uri(),
		array( 'chidemoon-typography' ),
		$version
	);
	wp_enqueue_style(
		'chidemoon-editorial-refresh',
		get_stylesheet_directory_uri() . '/assets/css/editorial-refresh.css',
		array( 'chidemoon-blocksy-child' ),
		$asset_version( 'assets/css/editorial-refresh.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'chidemoon_blocksy_enqueue_styles', 20 );

/**
 * Curated header fallback. WordPress and Blocksy fall back to listing every
 * published page when no nav menu is assigned, which would surface cart,
 * checkout, account, and showcase pages in the public header. Only the
 * editorial entry points belong there.
 *
 * @return int[]
 */
function chidemoon_header_page_ids(): array {
	$ids = array();
	foreach ( array( 'shop', 'magazine', 'guides', 'comparisons', 'shop-the-look' ) as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
			$ids[] = (int) $page->ID;
		}
	}
	return $ids;
}

/**
 * Wrap the theme's page-list fallback so unassigned menu locations only
 * surface the curated Chidemoon entry points.
 *
 * @param array $args wp_nav_menu arguments.
 */
function chidemoon_nav_menu_fallback( array $args = array() ): void {
	$keep = chidemoon_header_page_ids();
	if ( empty( $keep ) ) {
		return;
	}

	$all_pages = get_posts(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);
	$hide      = array_values( array_diff( $all_pages, $keep ) );

	add_filter(
		'wp_list_pages_excludes',
		static function ( array $excludes ) use ( $hide ): array {
			return array_values( array_unique( array_merge( $excludes, $hide ) ) );
		}
	);

	$original = $args['chidemoon_original_fallback'] ?? '';
	if ( is_callable( $original ) ) {
		call_user_func( $original, $args );
		return;
	}

	wp_page_menu( array( 'echo' => true ) );
}

add_filter(
	'wp_nav_menu_args',
	static function ( array $args ): array {
		$has_menu = ! empty( $args['menu'] ) && (bool) wp_get_nav_menu_object( $args['menu'] );

		if ( ! $has_menu && ! empty( $args['theme_location'] ) && has_nav_menu( $args['theme_location'] ) ) {
			$has_menu = true;
		}

		if ( $has_menu ) {
			return $args;
		}

		$args['chidemoon_original_fallback'] = $args['fallback_cb'] ?? '';
		$args['fallback_cb']                 = 'chidemoon_nav_menu_fallback';

		return $args;
	}
);

/**
 * Keep keyboard users out of the Blocksy navigation chrome when they want the
 * page content. The target is shared by every public template in this theme.
 */
function chidemoon_blocksy_render_skip_link(): void {
	?>
	<a class="chidemoon-skip-link" href="#primary"><?php esc_html_e( 'رفتن به محتوای اصلی', 'chidemoon-blocksy-child' ); ?></a>
	<?php
}
add_action( 'wp_body_open', 'chidemoon_blocksy_render_skip_link', 5 );

function chidemoon_blocksy_setup(): void {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
}
add_action( 'after_setup_theme', 'chidemoon_blocksy_setup' );

/**
 * WooCommerce's gallery links contain only an image. Give the zoom destination
 * a useful accessible name without altering WooCommerce gallery behavior.
 */
function chidemoon_blocksy_label_product_gallery_links( string $html, int $attachment_id ): string {
	if ( ! is_product() || str_contains( $html, 'aria-label=' ) ) {
		return $html;
	}

	$label = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
	if ( ! is_string( $label ) || '' === trim( $label ) ) {
		$label = get_the_title();
	}

	return preg_replace(
		'/<a\s+href=/',
		'<a aria-label="' . esc_attr( sprintf( 'نمایش تصویر بزرگ %s', $label ) ) . '" href=',
		$html,
		1
	) ?? $html;
}
add_filter( 'woocommerce_single_product_image_thumbnail_html', 'chidemoon_blocksy_label_product_gallery_links', 10, 2 );

function chidemoon_blocksy_body_classes( array $classes ): array {
	$classes[] = 'chidemoon-editorial-site';
	$classes[] = is_rtl() ? 'chidemoon-persian' : 'chidemoon-ltr';
	return $classes;
}
add_filter( 'body_class', 'chidemoon_blocksy_body_classes' );

add_filter(
	'blocksy:breadcrumbs:items-array',
	static function ( array $items ): array {
		if ( isset( $items[0]['name'] ) && 'Home' === $items[0]['name'] ) {
			$items[0]['name'] = 'خانه';
		}

		return $items;
	}
);

/**
 * The presentation is Persian-first, so numerals are converted to Persian
 * digits for dates and prices without touching stored data.
 */
function chidemoon_fa_digits( $text ): string {
	return strtr(
		(string) $text,
		array(
			'0' => '۰',
			'1' => '۱',
			'2' => '۲',
			'3' => '۳',
			'4' => '۴',
			'5' => '۵',
			'6' => '۶',
			'7' => '۷',
			'8' => '۸',
			'9' => '۹',
		)
	);
}

/**
 * Convert Persian digits only in visible text nodes of generated markup so
 * href attributes (for example /page/2/) stay intact.
 */
function chidemoon_fa_digits_in_markup( string $markup ): string {
	return (string) preg_replace_callback(
		'/>([^<>]+)</',
		static fn( array $matches ): string => '>' . chidemoon_fa_digits( $matches[1] ) . '<',
		$markup
	);
}
add_filter( 'paginate_links', 'chidemoon_fa_digits_in_markup', 20 );

add_filter( 'get_the_time', 'chidemoon_fa_digits' );
add_filter( 'wc_price', 'chidemoon_fa_digits' );

/**
 * WooCommerce has no built-in symbol for the Iranian toman. Returning plain
 * text (never an HTML entity) keeps the Persian-digit price filter from
 * corrupting numeric character references such as &#36;.
 */
add_filter( 'woocommerce_currency_symbol',
	static function ( $symbol, $currency ) {
		if ( 'IRT' === $currency ) {
			return 'تومان';
		}
		return $symbol;
	},
	10,
	2
);

/**
 * Persian reading order: the amount comes first and the currency word
 * follows it with a clear gap instead of the glued "تومان۷۸۰٬۰۰۰".
 */
add_filter(
	'woocommerce_price_format',
	static fn(): string => '%2$s %1$s'
);

/**
 * Persian shopping UI expects grouped thousands (۷۸۰٬۰۰۰) regardless of the
 * stored WooCommerce options.
 */
add_filter(
	'wc_get_price_thousand_separator',
	static fn(): string => ','
);
add_filter(
	'wc_get_price_decimal_separator',
	static fn(): string => '٫'
);

/**
 * Convert a Gregorian date to the Solar Hijri calendar used in Persian copy.
 *
 * @return int[] Jalali year, month, and day.
 */
function chidemoon_gregorian_to_jalali( int $year, int $month, int $day ): array {
	$gregorian_days = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
	$year_offset    = $year > 1600 ? 979 : 0;
	$year           = $year > 1600 ? $year - 1600 : $year - 621;
	$year_two       = $month > 2 ? $year + 1 : $year;
	$days           = ( 365 * $year ) + intdiv( $year_two + 3, 4 ) - intdiv( $year_two + 99, 100 ) + intdiv( $year_two + 399, 400 ) - 80 + $day + $gregorian_days[ $month - 1 ];
	$jalali_year    = $year_offset + ( 33 * intdiv( $days, 12053 ) );
	$days          %= 12053;
	$jalali_year   += 4 * intdiv( $days, 1461 );
	$days          %= 1461;

	if ( $days > 365 ) {
		$jalali_year += intdiv( $days - 1, 365 );
		$days         = ( $days - 1 ) % 365;
	}

	if ( $days < 186 ) {
		$jalali_month = 1 + intdiv( $days, 31 );
		$jalali_day   = 1 + ( $days % 31 );
	} else {
		$jalali_month = 7 + intdiv( $days - 186, 30 );
		$jalali_day   = 1 + ( ( $days - 186 ) % 30 );
	}

	return array( $jalali_year, $jalali_month, $jalali_day );
}

/**
 * Keep machine-readable dates Gregorian while presenting Solar Hijri dates.
 */
function chidemoon_solar_hijri_post_date( string $date, string $format, WP_Post $post ): string {
	if ( DATE_W3C === $format || 'c' === $format ) {
		return $date;
	}

	$timestamp = get_post_timestamp( $post );
	if ( false === $timestamp ) {
		return chidemoon_fa_digits( $date );
	}

	list( $year, $month, $day ) = chidemoon_gregorian_to_jalali(
		(int) wp_date( 'Y', $timestamp ),
		(int) wp_date( 'n', $timestamp ),
		(int) wp_date( 'j', $timestamp )
	);
	$month_names = array( 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );

	return chidemoon_fa_digits( sprintf( '%d %s %d', $day, $month_names[ $month - 1 ], $year ) );
}
add_filter( 'get_the_date', 'chidemoon_solar_hijri_post_date', 10, 3 );

add_filter(
	'woocommerce_currency_symbol',
	static function ( string $symbol, string $currency ): string {
		return 'IRR' === $currency ? 'تومان' : $symbol;
	},
	10,
	2
);

/**
 * Copyright is intentionally omitted from the public site.
 */
add_filter(
	'blocksy:footer:copyright:default-value',
	'__return_empty_string'
);
add_filter(
	'blocksy:footer:copyright:value',
	'__return_empty_string'
);

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
				'از تصویر بخر'   => chidemoon_blocksy_page_url( 'shop-the-look' ),
			),
		),
		array(
			'title' => 'فروشگاه',
			'links' => array(
				'همه کالاها' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : chidemoon_blocksy_page_url( 'shop' ),
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
					<?php esc_html_e( 'مجله‌ی خرید برای خانه؛ راهنماهای آزموده، مقایسه‌های شفاف و کالاهای منتخب برای چیدمان هر گوشه از خانه.', 'chidemoon-blocksy-child' ); ?>
				</p>
				<p class="chidemoon-footer__disclosure">
					<?php esc_html_e( 'شفافیت: برخی لینک‌ها همکاری در فروش دارند؛ انتخاب کالا فقط بر پایه‌ی بررسی تحریریه است و قیمت و موجودی را در فروشنده ببینید.', 'chidemoon-blocksy-child' ); ?>
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
				<h2 class="chidemoon-footer__title"><?php esc_html_e( 'استناد محتوایی', 'chidemoon-blocksy-child' ); ?></h2>
				<p>
					<?php esc_html_e( 'چیدمون یک رسانه‌ی مستقل است؛ هیچ فروشنده‌ای در نتیجه‌ی بررسی‌ها و رتبه‌بندی‌ها نفوذ ندارد.', 'chidemoon-blocksy-child' ); ?>
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
				<a class="chidemoon-text-link" href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'ادامه مطلب', 'chidemoon-blocksy-child' ); ?><span aria-hidden="true">←</span></a>
			</div>
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
						<a class="chidemoon-button" href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'مشاهدهٔ جزئیات', 'chidemoon-blocksy-child' ); ?></a>
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
