<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_RTL {
	private static array $persian_digits = array(
		'0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
		'5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
	);

	private static array $rtl_locales = array( 'fa_IR', 'fa_AF', 'ar', 'ar_SA', 'ar_EG', 'ckb', 'ku' );

	/**
	 * Get the active WordPress locale for plugin-owned UI.
	 */
	public static function locale(): string {
		if ( function_exists( 'determine_locale' ) ) {
			$locale = determine_locale();
		} elseif ( function_exists( 'get_locale' ) ) {
			$locale = get_locale();
		} else {
			$locale = 'en_US';
		}
		return $locale ?: 'en_US';
	}

	/**
	 * Get the active locale formatted for HTML lang attributes.
	 */
	public static function language(): string {
		return str_replace( '_', '-', self::locale() );
	}

	/**
	 * Get the active text direction for plugin-owned UI.
	 *
	 * Honors the `kalahamoon_direction` setting and site locale.
	 */
	public static function direction(): string {
		$override = function_exists( 'get_option' ) ? get_option( 'kalahamoon_direction', 'auto' ) : 'auto';
		if ( 'rtl' === $override || 'ltr' === $override ) {
			return $override;
		}
		return function_exists( 'is_rtl' ) && is_rtl() ? 'rtl' : 'ltr';
	}

	/** Admin screens are English/LTR even when public content is Persian/RTL. */
	public static function admin_direction(): string {
		return 'ltr';
	}

	/** Admin screens use English independently of the site's content locale. */
	public static function admin_language(): string {
		return 'en-US';
	}

	/** Shared English-only locale payload for WordPress admin JavaScript. */
	public static function admin_script_locale_config(): array {
		return array( 'isRtl' => false, 'direction' => 'ltr', 'language' => 'en-US', 'locale' => 'en-US' );
	}

	/**
	 * Shared locale/direction data for JavaScript.
	 *
	 * @return array{isRtl:bool,direction:string,language:string,locale:string}
	 */
	public static function script_locale_config(): array {
		return array(
			'isRtl'     => 'rtl' === self::direction(),
			'direction' => self::direction(),
			'language'  => self::language(),
			'locale'    => self::locale(),
		);
	}

	/**
	 * Whether the current locale needs an RTL font (Vazirmatn).
	 */
	public static function needs_rtl_font(): bool {
		return in_array( self::locale(), self::$rtl_locales, true );
	}

	/**
	 * Format a number, optionally using Persian numerals.
	 *
	 * @param float|int|string $number
	 * @param bool|null        $use_persian Force Persian digits; null reads the option.
	 * @param int              $decimals    Number of decimal places to keep.
	 */
	public static function format_number( $number, ?bool $use_persian = null, int $decimals = 0 ): string {
		if ( null === $use_persian ) {
			$use_persian = (bool) get_option( 'kalahamoon_persian_numerals', false );
		}

		$formatted = number_format( (float) $number, max( 0, $decimals ), '.', ',' );

		if ( $use_persian ) {
			$formatted = self::to_persian_digits( $formatted, true );
		}

		return $formatted;
	}

	/**
	 * Convert ASCII digits according to the configured content presentation.
	 */
	public static function to_persian_digits( string $value, ?bool $force = null ): string {
		$use_persian = null === $force ? (bool) get_option( 'kalahamoon_persian_numerals', false ) : $force;
		return $use_persian ? strtr( $value, self::$persian_digits ) : $value;
	}

	/**
	 * Format a price with currency symbol/label.
	 *
	 * @param float|int|string $amount
	 * @param string           $currency ISO code (IRR, USD, etc.)
	 */
	public static function format_price( $amount, string $currency = '' ): string {
		if ( empty( $currency ) ) {
			$currency = get_option( 'kalahamoon_display_currency', 'IRR' );
		}

		$unit = get_option( 'kalahamoon_display_unit', 'TOMAN' );

		$value = (float) $amount;

		// Convert Rial to Toman if needed
		if ( 'IRR' === $currency && 'TOMAN' === $unit ) {
			$value = $value / 10;
			$formatted = self::format_number( $value );
			return $formatted . ' ' . __( 'تومان', 'kalahamoon' );
		}

		if ( 'IRR' === $currency ) {
			$formatted = self::format_number( $value );
			return $formatted . ' ' . __( 'ریال', 'kalahamoon' );
		}

		if ( 'USD' === $currency ) {
			return '$' . number_format( $value, 2 );
		}

		if ( 'EUR' === $currency ) {
			return '€' . number_format( $value, 2 );
		}

		return self::format_number( $value ) . ' ' . $currency;
	}

	/**
	 * Localized display label for a marketplace platform slug. Centralizes the
	 * label map that was previously duplicated across block render templates.
	 *
	 * @param string $platform Platform slug (e.g. 'digikala').
	 * @return string Human label; falls back to the slug when unknown.
	 */
	public static function platform_label( string $platform ): string {
		$platform = strtolower( trim( $platform ) );
		$labels   = array(
			'bakalahamoon' => 'باکالاهامون',
			'basalam'      => 'باسلام',
			'digikala'     => 'دیجی‌کالا',
			'torob'        => 'ترب',
			'shopify'      => 'Shopify',
			'woocommerce'  => 'WooCommerce',
		);

		/**
		 * Filter the marketplace platform label map.
		 *
		 * @param array<string,string> $labels
		 */
		$labels = (array) apply_filters( 'kalahamoon_platform_labels', $labels );

		return $labels[ $platform ] ?? ( '' !== $platform ? $platform : '' );
	}

	/**
	 * Get the display currency setting.
	 */
	public static function get_display_currency(): string {
		return get_option( 'kalahamoon_display_currency', 'IRR' );
	}

	/**
	 * Get the display unit setting for Iranian Rial prices.
	 */
	public static function get_display_unit(): string {
		return get_option( 'kalahamoon_display_unit', 'TOMAN' );
	}
}
