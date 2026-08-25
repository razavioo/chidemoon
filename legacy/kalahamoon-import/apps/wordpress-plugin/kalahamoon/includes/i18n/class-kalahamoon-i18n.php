<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_I18n {

	public static function load(): void {
		load_plugin_textdomain( 'kalahamoon', false, dirname( KALAHAMOON_PLUGIN_BASENAME ) . '/languages' );
		add_filter( 'gettext', array( __CLASS__, 'admin_gettext' ), 10, 3 );
	}

	/** Translate legacy Persian admin copy while leaving public content untouched. */
	public static function admin_gettext( string $translated, string $text, string $domain ): string {
		if ( ! is_admin() || 'kalahamoon' !== $domain ) {
			return $translated;
		}
		$labels = array(
			'بستن' => 'Close', 'آخرین بررسی: %s' => 'Last checked: %s', 'قبل' => 'ago', 'مشاهده و خرید' => 'View and buy',
			'خرید از باکالاهامون' => 'Buy from Bakalahamoon', 'خرید از دیجی‌کالا' => 'Buy from Digikala',
			'قیمت فعلی: [kalahamoon_price id="bas-123"]' => 'Current price: [kalahamoon_price id="bas-123"]',
			'نقاط قوت و ضعف' => 'Pros and cons', 'جاروبرقی' => 'vacuum cleaner', 'سبک|ارزان|بادوام' => 'lightweight|affordable|durable',
		);
		return $labels[ $text ] ?? $translated;
	}
}
