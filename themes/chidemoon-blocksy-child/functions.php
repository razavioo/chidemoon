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

require get_stylesheet_directory() . '/includes/category-art.php';

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
 * Search forms render their submit control disabled until the visitor types
 * a phrase; this tiny companion script keeps the control in sync with the
 * field and blocks empty submissions (including Enter) as a hard stop.
 */
function chidemoon_blocksy_enqueue_scripts(): void {
	$relative = 'assets/js/search-form.js';
	$mtime    = @filemtime( get_stylesheet_directory() . '/' . $relative );

	wp_enqueue_script(
		'chidemoon-search-form',
		get_stylesheet_directory_uri() . '/' . $relative,
		array(),
		(string) wp_get_theme()->get( 'Version' ) . ( $mtime ? '.' . $mtime : '' ),
		array( 'strategy' => 'defer' )
	);
}
add_action( 'wp_enqueue_scripts', 'chidemoon_blocksy_enqueue_scripts', 20 );

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
 * Section shortcuts shown under the search field in the header modal and on
 * empty search results, so a stalled query always has a next step.
 */
function chidemoon_search_quick_links(): void {
	$links = array(
		'فروشگاه'       => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : chidemoon_blocksy_page_url( 'shop' ),
		'مجله'          => chidemoon_blocksy_page_url( 'magazine' ),
		'راهنمای خرید'  => chidemoon_blocksy_page_url( 'guides' ),
		'مقایسه‌ها'     => chidemoon_blocksy_page_url( 'comparisons' ),
	);

	echo '<nav class="chidemoon-search-form__links" aria-label="' . esc_attr__( 'بخش‌های پیشنهادی', 'chidemoon-blocksy-child' ) . '">';
	echo '<span class="chidemoon-search-form__links-label">' . esc_html__( 'می‌توانید از اینجا شروع کنید:', 'chidemoon-blocksy-child' ) . '</span>';
	foreach ( $links as $label => $url ) {
		if ( is_string( $url ) && '' !== $url ) {
			printf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
		}
	}
	echo '</nav>';
}

/**
 * The header builder keeps the search element on the desktop row, so mobile
 * visitors get their search entry at the top of the off-canvas menu.
 */
add_action(
	'blocksy:header:offcanvas:mobile:top',
	static function (): void {
		echo '<div class="chidemoon-offcanvas-search">';
		get_search_form();
		echo '</div>';
	}
);

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
 * Shared pagination language: Persian labels and a compact window so the
 * journal, archive, and Shop-the-Look surfaces stay visually identical.
 *
 * @return array<string, mixed>
 */
function chidemoon_pagination_args( array $args = array() ): array {
	return array_merge(
		array(
			'mid_size'  => 1,
			'prev_text' => esc_html__( 'قبلی', 'chidemoon-blocksy-child' ),
			'next_text' => esc_html__( 'بعدی', 'chidemoon-blocksy-child' ),
		),
		$args
	);
}

/**
 * Render posts pagination with the same Persian-digit pass used by the
 * generated paginate_links output, so public page numbers never fall back
 * to Latin digits.
 */
function chidemoon_the_posts_pagination( array $args = array() ): void {
	$markup = get_the_posts_pagination( chidemoon_pagination_args( $args ) );

	if ( '' === $markup ) {
		return;
	}

	echo chidemoon_fa_digits_in_markup( $markup ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-generated markup plus trusted theme labels; only text nodes are digit-mapped.
}

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
	static fn(): string => '٬'
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
 * Reviews stay out of sight for now. The reviews tab carries the whole
 * discussion surface, so dropping the tab hides the list and the form in one
 * move; the priority must outrun WooCommerce's default tabs (10) and its
 * tab sorter (99). Remove this filter to bring the discussion back.
 */
add_filter(
	'woocommerce_product_tabs',
	static function ( array $tabs ): array {
		unset( $tabs['reviews'] );

		return $tabs;
	},
	100
);

/**
 * Product review form: drop the "required fields" boilerplate line, rebuild
 * the empty-state heading in proper Persian (the outer quotes WooCommerce
 * adds collide with «» already used inside product titles), and translate
 * the Name/Email labels WooCommerce leaves in English on fa_IR sites.
 */
add_filter(
	'woocommerce_product_review_comment_form_args',
	static function ( array $args ): array {
		$args['comment_notes_before'] = '';

		$is_first_review = str_contains( $args['title_reply'], '&ldquo;' )
			|| str_contains( $args['title_reply'], '“' );

		if ( $is_first_review ) {
			$product_title = get_the_title();
			$quoted        = str_contains( $product_title, '«' )
				? $product_title
				: '«' . $product_title . '»';

			$args['title_reply'] = 'اولین کسی باشید که دیدگاهی دربارهٔ ' . $quoted . ' ثبت می‌کند';
		}

		foreach ( array( 'author', 'email' ) as $field_key ) {
			if ( ! empty( $args['fields'][ $field_key ] ) ) {
				$args['fields'][ $field_key ] = str_replace(
					array( '>Name', '>Email' ),
					array( '>نام', '>ایمیل' ),
					$args['fields'][ $field_key ]
				);
			}
		}

		return $args;
	}
);

/**
 * The loop CTA carries a screen-reader label that WooCommerce builds as
 * `بیشتر بخوانید درباره "…"`. Persian product titles already quote their
 * name with «», so the outer pair collapses into «…"…"» noise. Re-quote
 * the inner title with «» only when it does not quote itself.
 */
add_filter(
	'woocommerce_loop_add_to_cart_args',
	static function ( array $args ): array {
		$label = $args['attributes']['aria-label'] ?? ( $args['aria-label'] ?? '' );
		if ( ! is_string( $label ) || '' === $label ) {
			return $args;
		}

		$label = str_replace( array( '&ldquo;', '&rdquo;' ), array( '“', '”' ), $label );

		if ( preg_match( '/^(.*?)“(.*?)”(.*)$/s', $label, $matches ) ) {
			$title  = $matches[2];
			$label  = $matches[1] . ( str_contains( $title, '«' ) ? $title : '«' . $title . '»' ) . $matches[3];
		}

		if ( isset( $args['attributes'] ) && is_array( $args['attributes'] ) && array_key_exists( 'aria-label', $args['attributes'] ) ) {
			$args['attributes']['aria-label'] = $label;
		} else {
			$args['aria-label'] = $label;
		}

		return $args;
	}
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
 * Blocksy ships untranslated English interface chrome (search modal, live
 * search status, off-canvas helpers) and the site runs without a Persian
 * Blocksy language pack. Override the few strings a visitor can meet, then
 * let every other string pass through untouched.
 */
function chidemoon_blocksy_english_overrides(): array {
	static $map = null;
	if ( is_array( $map ) ) {
		return $map;
	}

	$map = array(
		'Skip to content'          => 'رفتن به محتوای اصلی',
		'Search modal'             => 'جست‌وجو',
		'Search'                   => 'جست‌وجو',
		'Close search modal'       => 'بستن جست‌وجو',
		'Search for...'            => 'جست‌وجو…',
		'Show more'                => 'نمایش بیشتر',
		'More'                     => 'بیشتر',
		'In stock'                 => 'موجود',
		'Out of stock'             => 'ناموجود',
		'Expand dropdown menu'     => 'باز کردن زیرمنو',
		'Collapse dropdown menu'   => 'بستن زیرمنو',
		'Previous slide'           => 'اسلاید قبلی',
		'Next slide'               => 'اسلاید بعدی',
		'Load more'                => 'بارگذاری بیشتر',
		'read more'                => 'ادامه مطلب',
		'Quantity'                 => 'تعداد',
		'View shopping cart'       => 'مشاهده سبد خرید',
		'No products in the cart.' => 'سبد خرید خالی است.',
		'Cart'                     => 'سبد خرید',
	);

	return $map;
}

/**
 * WooCommerce's Persian pack renders the non-purchasable product CTA as
 * "اطلاعات بیشتر". The editorial CTA for such products is "مشاهده" — the
 * same word the product cards use — so the shipped translation is remapped.
 */
function chidemoon_woocommerce_english_overrides(): array {
	static $map = null;
	if ( is_array( $map ) ) {
		return $map;
	}

	$map = array(
		'Read more' => 'مشاهده',
	);

	return $map;
}

add_filter(
	'gettext',
	static function ( string $translation, string $text, string $domain ): string {
		if ( 'blocksy' === $domain ) {
			$map = chidemoon_blocksy_english_overrides();
			return $map[ $text ] ?? $translation;
		}

		if ( 'woocommerce' === $domain ) {
			$map = chidemoon_woocommerce_english_overrides();
			return $map[ $text ] ?? $translation;
		}

		return $translation;
	},
	999,
	3
);

/**
 * Blocksy's search modal placeholder travels through the dynamic-translation
 * helper (not gettext) and no multilingual plugin is installed to intercept
 * it, so the default English string is replaced here.
 */
add_filter(
	'wpml_translate_single_string',
	static function ( string $translation, string $text, string $domain ): string {
		if ( 'Blocksy' !== $domain || 'Start typing to search' !== $text ) {
			return $translation;
		}

		return 'مثلاً: چراغ مطالعه';
	},
	10,
	3
);

/**
 * Persian live-search and helper strings for Blocksy's front-end scripts.
 */
add_filter(
	'blocksy:general:ct-scripts-localizations',
	static function ( array $data ): array {
		$data['show_more_text']             = 'نمایش بیشتر';
		$data['more_text']                  = 'بیشتر';
		$data['search_live_results']        = 'نتایج جست‌وجو';
		$data['search_live_no_results']     = 'نتیجه‌ای پیدا نشد';
		$data['search_live_results_closed'] = 'نتایج جست‌وجو بسته شد.';
		$data['search_live_no_result']      = 'نتیجه‌ای پیدا نشد';
		$data['search_live_one_result']     = 'یک نتیجه پیدا شد. برای انتخاب، کلید Tab را بزنید.';
		$data['search_live_many_results']   = '%s نتیجه پیدا شد. برای انتخاب، کلید Tab را بزنید.';
		$data['search_live_stock_status_texts'] = array(
			'instock'    => 'موجود',
			'outofstock' => 'ناموجود',
		);
		$data['clipboard_copied']           = 'کپی شد!';
		$data['clipboard_failed']           = 'کپی نشد';
		$data['expand_submenu']             = 'باز کردن زیرمنو';
		$data['collapse_submenu']           = 'بستن زیرمنو';

		return $data;
	}
);

/**
 * Blocksy also prints its own English skip link at wp_body_open priority 50.
 * The theme already renders a Persian one at priority 5 pointing at #primary,
 * so the duplicate is removed from the tab order and the accessibility tree.
 */
add_filter(
	'blocksy:head:skip-to-content:href',
	static fn(): string => '#primary'
);

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
