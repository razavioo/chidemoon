<?php
/** Local data bindings for independently editable native Loop elements. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Elementor_Content_Label_Tag extends \Elementor\Core\DynamicTags\Tag {
	public function get_name(): string { return 'chidemoon-content-label'; }
	public function get_title(): string { return 'چیدمون | نوع مطلب'; }
	public function get_group(): array { return array( 'chidemoon' ); }
	public function get_categories(): array { return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ); }
	protected function register_controls(): void {
		foreach ( array( 'product' => 'محصول', 'guide' => 'راهنمای خرید', 'comparison' => 'مقایسه', 'look' => 'ایدهٔ چیدمان', 'shoppable' => 'چیدمان قابل خرید', 'concept' => 'ایدهٔ مفهومی', 'post' => 'مطلب' ) as $key => $label ) {
			$this->add_control( $key, array( 'label' => $label, 'type' => \Elementor\Controls_Manager::TEXT, 'default' => $label ) );
		}
	}
	public function render(): void {
		$id = (int) get_the_ID();
		$key = 'product' === get_post_type( $id ) ? 'product' : 'post';
		if ( 'post' === $key ) {
			$label = Chidemoon_Core_Public_Design::post_card_label( $id );
			$key = array( 'راهنما' => 'guide', 'مقایسه' => 'comparison', 'ایدهٔ چیدمان' => 'look', 'چیدمان قابل خرید' => 'shoppable', 'ایدهٔ مفهومی' => 'concept' )[ $label ] ?? 'post';
		}
		echo esc_html( (string) $this->get_settings( $key ) );
	}
}

final class Chidemoon_Core_Elementor_Product_Field_Tag extends \Elementor\Core\DynamicTags\Tag {
	public function get_name(): string { return 'chidemoon-product-field'; }
	public function get_title(): string { return 'چیدمون | دادهٔ محصول'; }
	public function get_group(): array { return array( 'chidemoon' ); }
	public function get_categories(): array { return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ); }
	protected function register_controls(): void {
		$this->add_control( 'field', array( 'label' => 'داده', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => array( 'price' => 'قیمت ثبت‌شده', 'merchant' => 'نام فروشنده', 'short_description' => 'توضیح کوتاه' ), 'default' => 'merchant' ) );
		$this->add_control( 'merchant_label', array( 'label' => 'عنوان فروشنده', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'فروشنده:', 'condition' => array( 'field' => 'merchant' ) ) );
	}
	public function render(): void {
		// Resolve the Loop item, never a global product left over from another card.
		$id = (int) get_the_ID();
		if ( 'product' !== get_post_type( $id ) || ! function_exists( 'wc_get_product' ) ) {
			return;
		}
		$product = wc_get_product( $id );
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		switch ( $this->get_settings( 'field' ) ) {
			case 'price':
				echo wp_kses_post( $product->get_price_html() );
				break;
			case 'short_description':
				echo wp_kses_post( apply_filters( 'woocommerce_short_description', $product->get_short_description() ) );
				break;
			default:
				$merchant = trim( (string) $product->get_meta( Chidemoon_Core_Affiliate::META_MERCHANT_NAME ) );
				if ( $merchant ) {
					echo esc_html( trim( (string) $this->get_settings( 'merchant_label' ) . ' ' . $merchant ) );
				}
		}
	}
}
