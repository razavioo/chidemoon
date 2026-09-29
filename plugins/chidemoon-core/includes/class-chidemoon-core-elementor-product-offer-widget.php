<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Elementor_Product_Offer_Widget extends \Elementor\Widget_Base {
	public function get_name(): string { return 'chidemoon-product-offer'; }
	public function get_title(): string { return 'چیدمون | اطلاعات و فروشندهٔ محصول'; }
	public function get_icon(): string { return 'eicon-product-info'; }
	public function get_categories(): array { return array( 'general' ); }
	public function get_style_depends(): array { return array( 'chidemoon-core-compare' ); }
	public function get_script_depends(): array { return array( 'chidemoon-core-compare' ); }

	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => 'اطلاعات محصول' ) );
		$this->add_control( 'product_id', array( 'label' => 'محصول', 'type' => \Elementor\Controls_Manager::SELECT2, 'options' => array( '' => 'محصول صفحهٔ جاری' ) + Chidemoon_Core_Public_Design::product_options(), 'default' => '', 'label_block' => true ) );
		$this->add_control( 'button_text', array( 'label' => 'متن دکمهٔ فروشنده', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'خرید از فروشگاه' ) );
		$this->add_control( 'pending_text', array( 'label' => 'متن محصول تأییدنشده', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'اطلاعات این محصول هنوز تأیید نشده است؛ لینک خرید فعال نیست.' ) );
		$this->add_control( 'show_facts', array( 'label' => 'مشخصات بررسی‌شده', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'show_compare', array( 'label' => 'دکمهٔ مقایسه', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'style', array( 'label' => 'ظاهر', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'text_color', array( 'label' => 'رنگ متن', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ch-product-offer' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'typography', 'selector' => '{{WRAPPER}} .ch-product-offer' ) );
		$this->add_control( 'button_background', array( 'label' => 'رنگ دکمه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .chidemoon-affiliate-cta' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'button_color', array( 'label' => 'متن دکمه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .chidemoon-affiliate-cta' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'button_padding', array( 'label' => 'فاصلهٔ دکمه', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'selectors' => array( '{{WRAPPER}} .chidemoon-affiliate-cta' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$product = wc_get_product( absint( $settings['product_id'] ?: get_the_ID() ) );
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		echo '<div class="ch-product-offer">';
		if ( ! Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) {
			echo '<p>' . esc_html( $settings['pending_text'] ) . '</p></div>';
			return;
		}
		$merchant = (string) $product->get_meta( Chidemoon_Core_Affiliate::META_MERCHANT_NAME );
		if ( $merchant ) {
			echo '<p>فروشنده: ' . esc_html( $merchant ) . '</p>';
		}
		echo Chidemoon_Core_Affiliate::render_affiliate_cta( array( 'product_id' => (string) $product->get_id(), 'label' => $settings['button_text'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( 'yes' === $settings['show_compare'] ) {
			echo Chidemoon_Core_Compare::single_control( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( 'yes' === $settings['show_facts'] ) {
			$facts = json_decode( (string) $product->get_meta( Chidemoon_Core_Affiliate::META_FACTS ), true );
			if ( is_array( $facts ) ) {
				echo '<dl class="ch-product-facts">';
				foreach ( $facts as $key => $value ) {
					$label = is_array( $value ) ? ( $value['label'] ?? '' ) : $key;
					$fact = is_array( $value ) ? ( $value['value'] ?? '' ) : $value;
					if ( is_scalar( $label ) && is_scalar( $fact ) ) {
						echo '<dt>' . esc_html( (string) $label ) . '</dt><dd>' . esc_html( (string) $fact ) . '</dd>';
					}
				}
				echo '</dl>';
			}
		}
		echo '</div>';
	}
}
