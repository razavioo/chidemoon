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
