<?php
namespace Elementor {
	class Widget_Base {
		public static array $settings = array();

		protected function get_settings_for_display(): array {
			return self::$settings;
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );

	class WC_Product {
		public function __construct( private array $meta ) {}

		public function get_id(): int { return 42; }
		public function get_meta( string $key ): string { return $this->meta[ $key ] ?? ''; }
	}

	class Chidemoon_Core_Affiliate {
		public const META_MERCHANT_NAME = '_merchant';
		public const META_SOURCE_CHECKED = '_checked';
		public const META_SOURCE_URL = '_source';
		public const META_FACTS = '_facts';
		public static bool $eligible = true;

		public static function is_publicly_eligible( WC_Product $product ): bool {
			return self::$eligible;
		}

		public static function render_affiliate_cta( array $attributes ): string {
			return '<a class="chidemoon-affiliate-cta" href="/go/42/">' . esc_html( $attributes['label'] ) . '</a>';
		}
	}

	class Chidemoon_Core_Compare {
		public static array $labels = array();
		public static function single_control( WC_Product $product, array $labels = array() ): string {
			self::$labels = $labels;
			return '<button>' . esc_html( $labels['label'] ?? 'مقایسه' ) . '</button>';
		}
	}

	$product = new WC_Product( array(
		Chidemoon_Core_Affiliate::META_MERCHANT_NAME => 'فروشگاه نمونه',
		Chidemoon_Core_Affiliate::META_SOURCE_CHECKED => '2026-09-29T15:05:00Z',
		Chidemoon_Core_Affiliate::META_SOURCE_URL => 'https://example.com/product',
		Chidemoon_Core_Affiliate::META_FACTS => json_encode( array( array( 'label' => 'جنس', 'value' => 'فلز' ) ), JSON_UNESCAPED_UNICODE ),
	) );

	function wc_get_product( int $id ): WC_Product|false {
		global $product;
		return 42 === $id ? $product : false;
	}
	function get_the_ID(): int { return 42; }
	function absint( mixed $value ): int { return abs( (int) $value ); }
	function esc_html( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_html__( string $value, string $domain ): string { return esc_html( $value ); }
	function esc_attr( string $value ): string { return esc_html( $value ); }
	function esc_url( string $value ): string { return esc_html( $value ); }
	function wp_date( string $format, int $timestamp ): string { return gmdate( 'Y-m-d H:i', $timestamp ); }

	require_once __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-elementor-product-offer-widget.php';
	$widget = new Chidemoon_Core_Elementor_Product_Offer_Widget();
	$render = new \ReflectionMethod( $widget, 'render' );

	function render_offer( \ReflectionMethod $render, Chidemoon_Core_Elementor_Product_Offer_Widget $widget ): string {
		ob_start();
		$render->invoke( $widget );
		return (string) ob_get_clean();
	}

	function assert_offer( bool $condition, string $message ): void {
		if ( ! $condition ) {
			throw new \RuntimeException( $message );
		}
	}

	\Elementor\Widget_Base::$settings = array(
		'product_id' => 42,
		'button_text' => 'خرید از فروشگاه',
		'pending_text' => 'لینک خرید فعال نیست.',
		'show_source' => 'yes',
		'show_compare' => 'no',
		'show_facts' => 'no',
		'price_notice' => 'قیمت را بررسی کن.',
	);
	$html = render_offer( $render, $widget );
	$merchant_at = strpos( $html, '<strong>فروشگاه نمونه</strong>' );
	$checked_at = strpos( $html, '<time datetime="2026-09-29T15:05:00+00:00">' );
	$cta_at = strpos( $html, 'class="chidemoon-affiliate-cta"' );
	$disclosure_at = strpos( $html, 'چیدمون ممکن است از این لینک کارمزد دریافت کند.' );
	assert_offer( false !== $merchant_at && false !== $checked_at && false !== $cta_at && false !== $disclosure_at, 'Missing purchase trust details' );
	assert_offer( $merchant_at < $checked_at && $checked_at < $cta_at && $cta_at < $disclosure_at, 'Purchase trust details are out of order' );
	assert_offer( str_contains( $html, 'قیمت را بررسی کن.' ) && str_contains( $html, 'https://example.com/product' ), 'Source settings were lost' );

	\Elementor\Widget_Base::$settings['show_source'] = 'no';
	$html = render_offer( $render, $widget );
	assert_offer( ! str_contains( $html, '<time' ) && ! str_contains( $html, 'قیمت را بررسی کن.' ), 'Hidden source details were rendered' );
	assert_offer( str_contains( $html, 'چیدمون ممکن است از این لینک کارمزد دریافت کند.' ), 'Affiliate disclosure must remain next to an active CTA' );
	\Elementor\Widget_Base::$settings['facts_only'] = 'yes';
	$html = render_offer( $render, $widget );
	assert_offer( str_contains( $html, '<h2>مشخصات بررسی‌شده</h2>' ) && str_contains( $html, '<dt>جنس</dt><dd>فلز</dd>' ), 'Full-width facts are missing' );
	assert_offer( ! str_contains( $html, 'chidemoon-affiliate-cta' ) && ! str_contains( $html, 'کارمزد' ) && ! str_contains( $html, 'فروشگاه نمونه' ), 'Facts-only view duplicates offer details' );
	\Elementor\Widget_Base::$settings['facts_heading'] = 'اطلاعات <تأییدشده>';
	$html = render_offer( $render, $widget );
	assert_offer( str_contains( $html, '<h2>اطلاعات &lt;تأییدشده&gt;</h2>' ), 'The facts heading must use escaped Elementor copy.' );
	\Elementor\Widget_Base::$settings['show_facts_heading'] = 'no';
	$html = render_offer( $render, $widget );
	assert_offer( ! str_contains( $html, '<h2>' ) && str_contains( $html, '<dt>جنس</dt>' ), 'The separate facts heading toggle removes product data.' );

	Chidemoon_Core_Affiliate::$eligible = false;
	$html = render_offer( $render, $widget );
	assert_offer( '' === $html, 'Unverified product exposes standalone facts' );
	\Elementor\Widget_Base::$settings['facts_only'] = 'no';
	$html = render_offer( $render, $widget );
	assert_offer( str_contains( $html, 'لینک خرید فعال نیست.' ), 'Pending state is missing' );
	assert_offer( ! str_contains( $html, 'chidemoon-affiliate-cta' ) && ! str_contains( $html, 'کارمزد' ), 'Pending offer exposes an affiliate action or disclosure' );

	Chidemoon_Core_Affiliate::$eligible = true;
	\Elementor\Widget_Base::$settings['show_compare'] = 'yes';
	\Elementor\Widget_Base::$settings['compare_text'] = 'بسنج <اکنون>';
	\Elementor\Widget_Base::$settings['compare_selected_text'] = 'حذف از فهرست';
	\Elementor\Widget_Base::$settings['compare_hint'] = '';
	\Elementor\Widget_Base::$settings['compare_selected_hint'] = 'انتخاب را حذف کن';
	\Elementor\Widget_Base::$settings['disclosure_text'] = 'این خرید & کارمزد دارد.';
	$html = render_offer( $render, $widget );
	assert_offer( str_contains( $html, 'بسنج &lt;اکنون&gt;' ) && str_contains( $html, 'این خرید &amp; کارمزد دارد.' ), 'Editable offer and comparison copy was lost or not escaped.' );
	assert_offer( '' === Chidemoon_Core_Compare::$labels['hint'] && 'حذف از فهرست' === Chidemoon_Core_Compare::$labels['selected_label'], 'Comparison state copy must be passed to the interactive control.' );
	\Elementor\Widget_Base::$settings['disclosure_text'] = '';
	$html = render_offer( $render, $widget );
	assert_offer( str_contains( $html, 'چیدمون ممکن است از این لینک کارمزد دریافت کند.' ), 'An empty editor setting must not remove the active purchase disclosure.' );

	$product = new WC_Product( array( Chidemoon_Core_Affiliate::META_FACTS => '{invalid' ) );
	\Elementor\Widget_Base::$settings['facts_only'] = 'yes';
	\Elementor\Widget_Base::$settings['facts_empty_text'] = 'مشخصات هنوز ثبت نشده است.';
	$html = render_offer( $render, $widget );
	assert_offer( str_contains( $html, 'مشخصات هنوز ثبت نشده است.' ) && ! str_contains( $html, '<dl' ), 'Missing or malformed facts must use the editable empty state.' );
	\Elementor\Widget_Base::$settings['facts_empty_text'] = '';
	$html = render_offer( $render, $widget );
	assert_offer( '' === $html, 'An intentionally empty facts fallback must hide an empty section.' );
	\Elementor\Widget_Base::$settings['product_id'] = 999;
	\Elementor\Widget_Base::$settings['missing_text'] = 'محصول یافت نشد.';
	$html = render_offer( $render, $widget );
	assert_offer( str_contains( $html, 'محصول یافت نشد.' ), 'The missing product state must remain editable.' );

	echo "Product offer trust states passed.\n";
}
