<?php
/**
 * Editable actions for a native Elementor product Loop Item.
 * Product data stays in WooCommerce; purchase and comparison retain review checks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Elementor_Product_Actions_Widget extends \Elementor\Widget_Base {
	public function get_name(): string { return 'chidemoon-product-actions'; }
	public function get_title(): string { return 'چیدمون | دکمه‌های کارت محصول'; }
	public function get_icon(): string { return 'eicon-button'; }
	public function get_categories(): array { return array( 'general' ); }
	public function get_keywords(): array { return array( 'product', 'loop', 'compare', 'محصول', 'مقایسه', 'چیدمون' ); }
	public function get_style_depends(): array { return array( 'chidemoon-core-compare' ); }
	public function get_script_depends(): array { return array( 'chidemoon-core-compare' ); }

	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => 'دکمه‌های محصول' ) );
		$this->add_control( 'product_id', array(
			'label' => 'محصول',
			'type' => \Elementor\Controls_Manager::SELECT2,
			'options' => array( '' => 'محصول جاری در کارت' ) + Chidemoon_Core_Public_Design::product_options(),
			'default' => '',
			'label_block' => true,
		) );
		$this->add_control( 'data_help', array(
			'type' => \Elementor\Controls_Manager::RAW_HTML,
			'raw' => 'در قالب کارت، «محصول جاری در کارت» را نگه دارید تا هر کارت به محصول خودش لینک شود. جزئیات به صفحهٔ داخلی محصول و خرید به لینک تأییدشدهٔ فروشنده از مسیر /go/ می‌رود. نام، تصویر و قیمت را از خود محصول و ظاهر کارت را از قالب Loop Item ویرایش کنید.',
		) );
		$this->add_control( 'show_details', array( 'label' => 'دکمهٔ جزئیات محصول', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'details_text', array( 'label' => 'متن دکمهٔ جزئیات', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'جزئیات محصول', 'dynamic' => array( 'active' => true ), 'condition' => array( 'show_details' => 'yes' ) ) );
		$this->add_control( 'details_new_tab', array( 'label' => 'باز کردن جزئیات در زبانهٔ تازه', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '', 'condition' => array( 'show_details' => 'yes' ) ) );
		$this->add_control( 'show_purchase', array( 'label' => 'دکمهٔ خرید از فروشنده', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '', 'description' => 'فقط برای محصول منتشرشده با پیشنهاد فروشندهٔ بررسی‌شده فعال می‌شود.' ) );
		$this->add_control( 'purchase_text', array( 'label' => 'متن دکمهٔ خرید', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'خرید از فروشگاه', 'dynamic' => array( 'active' => true ), 'condition' => array( 'show_purchase' => 'yes' ) ) );
		$this->add_control( 'disclosure_text', array( 'label' => 'توضیح کارمزد خرید', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'چیدمون ممکن است از این لینک کارمزد دریافت کند.', 'dynamic' => array( 'active' => true ), 'condition' => array( 'show_purchase' => 'yes' ), 'description' => 'کنار لینک خرید نمایش داده می‌شود؛ متن خالی از توضیح پیش‌فرض استفاده می‌کند.' ) );
		$this->add_control( 'show_compare', array( 'label' => 'دکمهٔ مقایسه', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'compare_text', array( 'label' => 'متن دکمهٔ مقایسه', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'مقایسه', 'dynamic' => array( 'active' => true ), 'condition' => array( 'show_compare' => 'yes' ) ) );
		$this->add_control( 'compare_selected_text', array( 'label' => 'متن پس از انتخاب برای مقایسه', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'حذف از مقایسه', 'dynamic' => array( 'active' => true ), 'condition' => array( 'show_compare' => 'yes' ) ) );
		$this->add_control( 'unavailable_text', array( 'label' => 'متن پیشنهاد بررسی‌نشده', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'پیشنهاد فروشندهٔ این محصول هنوز تأیید نشده است.', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'missing_text', array( 'label' => 'متن بدون محصول', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'محصولی برای این کارت در دسترس نیست.', 'dynamic' => array( 'active' => true ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style', array( 'label' => 'ظاهر دکمه‌ها', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'direction', array(
			'label' => 'چیدمان دکمه‌ها',
			'type' => \Elementor\Controls_Manager::SELECT,
			'options' => array( 'column' => 'زیر هم', 'row' => 'کنار هم' ),
			'selectors' => array( '{{WRAPPER}} .ch-product-card-actions' => 'flex-direction: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'gap', array( 'label' => 'فاصلهٔ دکمه‌ها', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}} .ch-product-card-actions' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$buttons = '{{WRAPPER}} .ch-product-card-details, {{WRAPPER}} .chidemoon-affiliate-cta, {{WRAPPER}} .chidemoon-compare-control';
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'button_typography', 'selector' => $buttons ) );
		$this->add_responsive_control( 'button_padding', array( 'label' => 'فاصلهٔ داخل دکمه', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'selectors' => array( $buttons => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'button_radius', array( 'label' => 'گردی دکمه', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 32 ) ), 'selectors' => array( $buttons => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		foreach ( array(
			'details_background' => array( 'پس‌زمینهٔ جزئیات', '.ch-product-card-details', 'background-color' ),
			'details_color' => array( 'متن جزئیات', '.ch-product-card-details', 'color' ),
			'purchase_background' => array( 'پس‌زمینهٔ خرید', '.chidemoon-affiliate-cta', 'background-color' ),
			'purchase_color' => array( 'متن خرید', '.chidemoon-affiliate-cta', 'color' ),
			'compare_background' => array( 'پس‌زمینهٔ مقایسه', '.chidemoon-compare-control:not(.is-selected)', 'background-color' ),
			'compare_color' => array( 'متن مقایسه', '.chidemoon-compare-control:not(.is-selected)', 'color' ),
			'compare_selected_background' => array( 'پس‌زمینهٔ مقایسه پس از انتخاب', '.chidemoon-compare-control.is-selected', 'background-color' ),
			'compare_selected_color' => array( 'متن مقایسه پس از انتخاب', '.chidemoon-compare-control.is-selected', 'color' ),
			'notice_color' => array( 'متن توضیحات', '.ch-product-card-empty, .ch-product-card-disclosure', 'color' ),
		) as $key => $control ) {
			$selector = implode( ', ', array_map( static fn( $part ) => '{{WRAPPER}} ' . trim( $part ), explode( ',', $control[1] ) ) );
			$this->add_control( $key, array( 'label' => $control[0], 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( $selector => $control[2] . ': {{VALUE}};' ) ) );
		}
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'notice_typography', 'selector' => '{{WRAPPER}} .ch-product-card-empty, {{WRAPPER}} .ch-product-card-disclosure' ) );
		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$product = wc_get_product( absint( ( $settings['product_id'] ?? '' ) ?: get_the_ID() ) );
		if ( ! $product instanceof WC_Product || 'publish' !== get_post_status( $product->get_id() ) ) {
			$this->empty_markup( $settings['missing_text'] ?? 'محصولی برای این کارت در دسترس نیست.' );
			return;
		}

		$eligible = Chidemoon_Core_Affiliate::is_publicly_eligible( $product );
		echo '<div class="ch-product-card-actions">';
		if ( 'yes' === ( $settings['show_details'] ?? 'yes' ) ) {
			$target = 'yes' === ( $settings['details_new_tab'] ?? '' ) ? ' target="_blank" rel="noopener"' : '';
			echo '<a class="chidemoon-button ch-product-card-details" href="' . esc_url( get_permalink( $product->get_id() ) ) . '"' . $target . '>' . esc_html( $this->required_text( $settings, 'details_text', 'جزئیات محصول' ) ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( $eligible && 'yes' === ( $settings['show_purchase'] ?? '' ) ) {
			echo '<div class="ch-product-card-purchase">';
			echo Chidemoon_Core_Affiliate::render_affiliate_cta( array( 'product_id' => (string) $product->get_id(), 'label' => $this->required_text( $settings, 'purchase_text', 'خرید از فروشگاه' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<p class="ch-product-card-disclosure">' . esc_html( $this->required_text( $settings, 'disclosure_text', 'چیدمون ممکن است از این لینک کارمزد دریافت کند.' ) ) . '</p></div>';
		}
		if ( $eligible && 'yes' === ( $settings['show_compare'] ?? 'yes' ) ) {
			echo Chidemoon_Core_Compare::control( $product, array(
				'label' => $this->required_text( $settings, 'compare_text', 'مقایسه' ),
				'selected_label' => $this->required_text( $settings, 'compare_selected_text', 'حذف از مقایسه' ),
			) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( ! $eligible && ( 'yes' === ( $settings['show_purchase'] ?? '' ) || 'yes' === ( $settings['show_compare'] ?? 'yes' ) ) ) {
			$this->empty_markup( $settings['unavailable_text'] ?? 'پیشنهاد فروشندهٔ این محصول هنوز تأیید نشده است.' );
		}
		echo '</div>';
	}

	private function empty_markup( string $text ): void {
		if ( '' !== trim( $text ) ) {
			echo '<p class="ch-product-card-empty">' . esc_html( $text ) . '</p>';
		}
	}

	/** @param array<string, mixed> $settings */
	private function required_text( array $settings, string $key, string $fallback ): string {
		$text = trim( (string) ( $settings[ $key ] ?? '' ) );
		return '' !== $text ? $text : $fallback;
	}
}
