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
		'Search modal'             => 'جستجو',
		'Search'                   => 'جستجو',
		'Close search modal'       => 'بستن جستجو',
		'Search for...'            => 'جستجو…',
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
		$data['search_live_results']        = 'نتایج جستجو';
		$data['search_live_no_results']     = 'نتیجه‌ای پیدا نشد';
		$data['search_live_results_closed'] = 'نتایج جستجو بسته شد.';
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
