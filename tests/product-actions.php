<?php
namespace Elementor {
	class Widget_Base {
		public static array $settings = array();
		protected function get_settings_for_display(): array { return self::$settings; }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );

	class WC_Product {
		public function __construct( private int $id, private string $review = 'reviewed', private string $type = 'external', private string $url = 'https://seller.example/product', public string $status = 'publish' ) {}
		public function get_id(): int { return $this->id; }
		public function get_name(): string { return 'محصول "نمونه"'; }
		public function get_image_id(): int { return 4; }
		public function is_type( string $type ): bool { return $this->type === $type; }
		public function get_meta( string $key, bool $single = true ): string {
			return match ( $key ) {
				Chidemoon_Core_Affiliate::META_REVIEW_STATE => $this->review,
				Chidemoon_Core_Affiliate::META_AFFILIATE_URL => $this->url,
				default => '',
			};
		}
	}

	$GLOBALS['products'] = array(
		42 => new WC_Product( 42 ),
		43 => new WC_Product( 43, 'draft' ),
		44 => new WC_Product( 44, 'reviewed', 'simple' ),
		45 => new WC_Product( 45, 'reviewed', 'external', '' ),
		46 => new WC_Product( 46, 'reviewed', 'external', 'https://127.0.0.1/product' ),
		47 => new WC_Product( 47, 'reviewed', 'external', 'https://seller.example/product', 'draft' ),
	);

	function wc_get_product( int $id ): WC_Product|false { return $GLOBALS['products'][ $id ] ?? false; }
	function get_the_ID(): int { return 42; }
	function get_post_status( int $id ): string { return $GLOBALS['products'][ $id ]->status ?? ''; }
	function absint( mixed $value ): int { return abs( (int) $value ); }
	function get_permalink( int $id ): string { return '/products/' . $id . '/'; }
	function home_url( string $path ): string { return $path; }
	function wp_parse_url( string $url ): array|false { return parse_url( $url ); }
	function esc_html( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( string $value ): string { return esc_html( $value ); }
	function esc_url( string $value ): string { return esc_html( $value ); }
	function esc_url_raw( string $value, array $protocols = array() ): string { return $value; }
	function __( string $value, string $domain ): string { return $value; }
	function wp_get_attachment_image_url( int $id, string $size ): string { return '/product.jpg'; }
	function wp_script_is( string $handle, string $state ): bool { return false; }
	function shortcode_atts( array $defaults, array $attributes, string $name ): array { return array_merge( $defaults, array_intersect_key( $attributes, $defaults ) ); }

	require_once __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-affiliate.php';
	require_once __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-compare.php';
	require_once __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-elementor-product-actions-widget.php';

	$widget = new Chidemoon_Core_Elementor_Product_Actions_Widget();
	$render = new \ReflectionMethod( $widget, 'render' );
	function card_actions( \ReflectionMethod $render, Chidemoon_Core_Elementor_Product_Actions_Widget $widget, array $settings ): string {
		\Elementor\Widget_Base::$settings = $settings;
		ob_start();
		$render->invoke( $widget );
		return (string) ob_get_clean();
	}
	function assert_actions( bool $condition, string $message ): void {
		if ( ! $condition ) throw new \RuntimeException( $message );
	}

	$html = card_actions( $render, $widget, array( 'details_text' => 'مشاهدهٔ محصول', 'compare_text' => 'بسنج', 'compare_selected_text' => 'حذف انتخاب' ) );
	assert_actions( str_contains( $html, 'href="/products/42/">مشاهدهٔ محصول</a>' ), 'A native Loop Item must resolve its current product to the internal detail page.' );
	assert_actions( str_contains( $html, 'data-compare-product="42"' ) && str_contains( $html, 'data-compare-label="بسنج"' ) && str_contains( $html, 'data-compare-selected-label="حذف انتخاب"' ), 'Editable comparison labels and product identity are missing.' );
	assert_actions( ! str_contains( $html, '/go/' ) && ! str_contains( $html, 'کارمزد' ), 'The default card must not expose an unrequested seller link.' );

	$settings = array( 'show_purchase' => 'yes', 'purchase_text' => 'خرید <از فروشنده>', 'disclosure_text' => 'این لینک & کارمزد دارد.', 'details_new_tab' => 'yes' );
	$html = card_actions( $render, $widget, $settings );
	assert_actions( str_contains( $html, 'href="/go/42/" target="_blank" rel="nofollow sponsored noopener"' ), 'The approved purchase must retain the local redirect and seller-link attributes.' );
	assert_actions( str_contains( $html, 'خرید &lt;از فروشنده&gt;' ) && str_contains( $html, 'این لینک &amp; کارمزد دارد.' ), 'Editor copy must be escaped.' );
	assert_actions( str_contains( $html, 'href="/products/42/" target="_blank" rel="noopener"' ), 'The internal link tab control is ignored.' );
	assert_actions( strpos( $html, '/go/42/' ) < strpos( $html, 'ch-product-card-disclosure' ), 'The disclosure must follow its active purchase action.' );

	foreach ( array( 43, 44, 45, 46 ) as $id ) {
		$html = card_actions( $render, $widget, $settings + array( 'product_id' => $id, 'unavailable_text' => 'پیشنهاد فعال نیست.' ) );
		assert_actions( str_contains( $html, 'href="/products/' . $id . '/"' ), 'A published product must keep its internal detail destination.' );
		assert_actions( ! str_contains( $html, '/go/' ) && ! str_contains( $html, 'data-compare-product' ) && ! str_contains( $html, 'ch-product-card-disclosure' ), 'Ineligible product exposes a purchase, comparison action or disclosure: ' . $id );
		assert_actions( str_contains( $html, 'پیشنهاد فعال نیست.' ), 'The unavailable state must use editor copy.' );
	}

	foreach ( array( 47, 999 ) as $id ) {
		$html = card_actions( $render, $widget, $settings + array( 'product_id' => $id, 'missing_text' => 'محصول یافت نشد.' ) );
		assert_actions( str_contains( $html, 'محصول یافت نشد.' ) && ! str_contains( $html, '<a ' ) && ! str_contains( $html, '<button ' ), 'A missing or unpublished product exposes an action.' );
	}

	$html = card_actions( $render, $widget, array( 'show_details' => 'no', 'show_compare' => 'no', 'show_purchase' => 'yes', 'purchase_text' => ' ', 'disclosure_text' => '' ) );
	assert_actions( ! str_contains( $html, 'ch-product-card-details' ) && ! str_contains( $html, 'data-compare-product' ), 'Hidden card actions remain visible.' );
	assert_actions( str_contains( $html, '>خرید از فروشگاه</a>' ) && str_contains( $html, 'چیدمون ممکن است از این لینک کارمزد دریافت کند.' ), 'An active purchase must retain a readable label and disclosure.' );

	$single = Chidemoon_Core_Compare::single_control( $GLOBALS['products'][42], array( 'label' => 'مقایسه "ویژه"', 'selected_label' => 'حذف', 'hint' => '', 'selected_hint' => '' ) );
	assert_actions( str_contains( $single, 'data-compare-label="مقایسه &quot;ویژه&quot;"' ) && str_contains( $single, 'chidemoon-compare-single__hint" hidden' ), 'Single comparison labels must escape copy and support a hidden hint.' );

	echo "Native product card action and eligibility checks passed.\n";
}
