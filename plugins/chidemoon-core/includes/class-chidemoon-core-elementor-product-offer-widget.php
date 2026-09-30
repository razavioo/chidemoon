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
		$this->add_control( 'data_help', array( 'type' => \Elementor\Controls_Manager::RAW_HTML, 'raw' => 'نام، تصویر، قیمت و توضیحات از خود محصول خوانده می‌شوند. فروشنده، منبع، وضعیت بررسی و مشخصات را در «محصولات ← ویرایش محصول ← اطلاعات محصول ← چیدمون» تغییر دهید. متن‌های این ویجت و ظاهر آن در همین پنل قابل ویرایش‌اند.' ) );
		$this->add_control( 'button_text', array( 'label' => 'متن دکمهٔ فروشنده', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'خرید از فروشگاه', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'pending_text', array( 'label' => 'متن محصول تأییدنشده', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'اطلاعات این محصول هنوز تأیید نشده است؛ لینک خرید فعال نیست.', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'missing_text', array( 'label' => 'متن بدون محصول', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'محصولی برای نمایش انتخاب نشده است.', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'facts_only', array( 'label' => 'نمایش مستقل مشخصات', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'no' ) );
		$this->add_control( 'show_facts', array( 'label' => 'مشخصات بررسی‌شده', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'show_facts_heading', array( 'label' => 'عنوان بخش مشخصات', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes', 'condition' => array( 'facts_only' => 'yes' ) ) );
		$this->add_control( 'facts_heading', array( 'label' => 'متن عنوان مشخصات', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'مشخصات بررسی‌شده', 'dynamic' => array( 'active' => true ), 'condition' => array( 'facts_only' => 'yes', 'show_facts_heading' => 'yes' ) ) );
		$this->add_control( 'facts_empty_text', array( 'label' => 'متن مشخصات خالی', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'مشخصات بررسی‌شده‌ای برای این محصول ثبت نشده است.', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'show_compare', array( 'label' => 'دکمهٔ مقایسه', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		foreach ( array(
			'compare_text' => array( 'متن دکمهٔ مقایسه', 'افزودن به مقایسه' ),
			'compare_selected_text' => array( 'متن پس از انتخاب', 'در فهرست مقایسه' ),
			'compare_hint' => array( 'راهنمای مقایسه', 'با حداکثر چهار محصول بسنجید' ),
			'compare_selected_hint' => array( 'راهنمای حذف از مقایسه', 'برای حذف از فهرست کلیک کنید' ),
		) as $key => $control ) {
			$this->add_control( $key, array( 'label' => $control[0], 'type' => \Elementor\Controls_Manager::TEXT, 'default' => $control[1], 'dynamic' => array( 'active' => true ), 'condition' => array( 'show_compare' => 'yes' ) ) );
		}
		$this->add_control( 'show_source', array( 'label' => 'منبع و تاریخ بررسی', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'merchant_label', array( 'label' => 'عنوان فروشنده', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'فروشنده', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'source_label', array( 'label' => 'متن لینک منبع', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'مشاهدهٔ اطلاعات در فروشگاه', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'checked_label', array( 'label' => 'عنوان تاریخ بررسی', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'بررسی منبع', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'price_notice', array( 'label' => 'توضیح قیمت', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'قیمت و موجودی را پیش از خرید در فروشگاه بررسی کن.', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'disclosure_text', array( 'label' => 'توضیح کارمزد لینک خرید', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'چیدمون ممکن است از این لینک کارمزد دریافت کند.', 'dynamic' => array( 'active' => true ), 'description' => 'این توضیح کنار لینک خرید فعال نمایش داده می‌شود. اگر متن خالی باشد، توضیح پیش‌فرض استفاده می‌شود.' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'style', array( 'label' => 'ظاهر', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'text_color', array( 'label' => 'رنگ متن', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ch-product-offer, {{WRAPPER}} .ch-product-facts-section' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'surface_background', array( 'label' => 'پس‌زمینهٔ اطلاعات و مشخصات', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ch-product-offer, {{WRAPPER}} .ch-product-facts-section' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'surface_border', array( 'label' => 'رنگ کادر اطلاعات و مشخصات', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ch-product-offer, {{WRAPPER}} .ch-product-facts-section' => 'border-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'surface_padding', array( 'label' => 'فاصلهٔ داخلی اطلاعات و مشخصات', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'selectors' => array( '{{WRAPPER}} .ch-product-offer, {{WRAPPER}} .ch-product-facts-section' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'typography', 'selector' => '{{WRAPPER}} .ch-product-offer, {{WRAPPER}} .ch-product-facts-section' ) );
		$this->add_control( 'button_background', array( 'label' => 'رنگ دکمه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .chidemoon-affiliate-cta' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'button_color', array( 'label' => 'متن دکمه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .chidemoon-affiliate-cta' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'button_padding', array( 'label' => 'فاصلهٔ دکمه', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'selectors' => array( '{{WRAPPER}} .chidemoon-affiliate-cta' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'button_alignment', array( 'label' => 'جای دکمهٔ خرید', 'type' => \Elementor\Controls_Manager::CHOOSE, 'options' => array( 'flex-start' => array( 'title' => 'راست', 'icon' => 'eicon-text-align-right' ), 'center' => array( 'title' => 'وسط', 'icon' => 'eicon-text-align-center' ), 'flex-end' => array( 'title' => 'چپ', 'icon' => 'eicon-text-align-left' ) ), 'selectors' => array( '{{WRAPPER}} .ch-product-offer-actions' => 'justify-content: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'button_width', array( 'label' => 'عرض دکمهٔ خرید', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array( '%', 'px' ), 'selectors' => array( '{{WRAPPER}} .chidemoon-affiliate-cta' => 'width: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'button_radius', array( 'label' => 'گردی دکمهٔ خرید', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 32 ) ), 'selectors' => array( '{{WRAPPER}} .ch-product-offer .chidemoon-affiliate-cta' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'source_color', array( 'label' => 'متن منبع', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ch-product-source, {{WRAPPER}} .ch-product-checked' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'facts_label_color', array( 'label' => 'عنوان مشخصات', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ch-product-facts dt' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'facts_heading_typography', 'selector' => '{{WRAPPER}} .ch-product-facts-section h2' ) );
		$this->add_control( 'facts_heading_color', array( 'label' => 'رنگ عنوان بخش مشخصات', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ch-product-facts-section h2' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'compare_background', array( 'label' => 'پس‌زمینهٔ مقایسه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .chidemoon-compare-single:not(.is-selected)' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'compare_color', array( 'label' => 'متن مقایسه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .chidemoon-compare-single:not(.is-selected)' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'compare_selected_background', array( 'label' => 'پس‌زمینهٔ مقایسه پس از انتخاب', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .chidemoon-compare-single.is-selected' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'compare_selected_color', array( 'label' => 'متن مقایسه پس از انتخاب', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .chidemoon-compare-single.is-selected' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'facts_gap', array( 'label' => 'فاصلهٔ مشخصات', 'type' => \Elementor\Controls_Manager::SLIDER, 'selectors' => array( '{{WRAPPER}} .ch-product-facts' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$product = wc_get_product( absint( ( $settings['product_id'] ?? '' ) ?: get_the_ID() ) );
		if ( ! $product instanceof WC_Product ) {
			echo '<p class="ch-product-empty">' . esc_html( $settings['missing_text'] ?? 'محصولی برای نمایش انتخاب نشده است.' ) . '</p>';
			return;
		}
		if ( 'yes' === ( $settings['facts_only'] ?? 'no' ) ) {
			if ( Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) {
				$facts = $this->facts_markup( $product, $settings['facts_empty_text'] ?? 'مشخصات بررسی‌شده‌ای برای این محصول ثبت نشده است.' );
				if ( $facts ) {
					echo '<section class="ch-product-facts-section">';
					if ( 'yes' === ( $settings['show_facts_heading'] ?? 'yes' ) && '' !== ( $settings['facts_heading'] ?? 'مشخصات بررسی‌شده' ) ) {
						echo '<h2>' . esc_html( $settings['facts_heading'] ?? 'مشخصات بررسی‌شده' ) . '</h2>';
					}
					echo $facts . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
			return;
		}
		echo '<div class="ch-product-offer">';
		if ( ! Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) {
			echo '<p>' . esc_html( $settings['pending_text'] ?? 'اطلاعات این محصول هنوز تأیید نشده است؛ لینک خرید فعال نیست.' ) . '</p></div>';
			return;
		}
		$merchant = (string) $product->get_meta( Chidemoon_Core_Affiliate::META_MERCHANT_NAME );
		if ( $merchant ) {
			echo '<p class="ch-product-merchant"><span>' . esc_html( $settings['merchant_label'] ?? 'فروشنده' ) . '</span><strong>' . esc_html( $merchant ) . '</strong></p>';
		}
		if ( 'yes' === ( $settings['show_source'] ?? 'yes' ) ) {
			$checked = (string) $product->get_meta( Chidemoon_Core_Affiliate::META_SOURCE_CHECKED );
			$checked_at = strtotime( $checked );
			if ( $checked && false !== $checked_at ) {
				echo '<p class="ch-product-merchant ch-product-checked"><span>' . esc_html( $settings['checked_label'] ?? 'بررسی منبع' ) . '</span><strong><time datetime="' . esc_attr( gmdate( DATE_ATOM, $checked_at ) ) . '">' . esc_html( wp_date( 'j F Y، H:i', $checked_at ) ) . '</time></strong></p>';
			}
		}
		echo '<div class="ch-product-offer-actions">';
		echo Chidemoon_Core_Affiliate::render_affiliate_cta( array( 'product_id' => (string) $product->get_id(), 'label' => $this->required_text( $settings, 'button_text', 'خرید از فروشگاه' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		echo '<div class="ch-product-note"><p>' . esc_html( $this->required_text( $settings, 'disclosure_text', 'چیدمون ممکن است از این لینک کارمزد دریافت کند.' ) ) . '</p></div>';
		if ( 'yes' === ( $settings['show_source'] ?? 'yes' ) ) {
			$source = (string) $product->get_meta( Chidemoon_Core_Affiliate::META_SOURCE_URL );
			echo '<div class="ch-product-source">';
			if ( $source ) {
				echo '<p><a href="' . esc_url( $source ) . '" target="_blank" rel="nofollow noopener">' . esc_html( $settings['source_label'] ?? 'مشاهدهٔ اطلاعات در فروشگاه' ) . '</a></p>';
			}
			if ( '' !== trim( (string) ( $settings['price_notice'] ?? '' ) ) ) {
				echo '<p>' . esc_html( $settings['price_notice'] ) . '</p>';
			}
			echo '</div>';
		}
		if ( 'yes' === ( $settings['show_compare'] ?? 'yes' ) ) {
			echo Chidemoon_Core_Compare::single_control( $product, array(
				'label' => $this->required_text( $settings, 'compare_text', 'افزودن به مقایسه' ),
				'selected_label' => $this->required_text( $settings, 'compare_selected_text', 'در فهرست مقایسه' ),
				'hint' => $settings['compare_hint'] ?? 'با حداکثر چهار محصول بسنجید',
				'selected_hint' => $settings['compare_selected_hint'] ?? 'برای حذف از فهرست کلیک کنید',
			) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( 'yes' === ( $settings['show_facts'] ?? 'yes' ) ) {
			echo $this->facts_markup( $product, $settings['facts_empty_text'] ?? 'مشخصات بررسی‌شده‌ای برای این محصول ثبت نشده است.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
	}

	/** @param array<string, mixed> $settings */
	private function required_text( array $settings, string $key, string $fallback ): string {
		$text = trim( (string) ( $settings[ $key ] ?? '' ) );
		return '' !== $text ? $text : $fallback;
	}

	private function facts_markup( WC_Product $product, string $empty_text ): string {
		$facts = json_decode( (string) $product->get_meta( Chidemoon_Core_Affiliate::META_FACTS ), true );
		$rows = '';
		foreach ( is_array( $facts ) ? $facts : array() as $key => $value ) {
			$label = is_array( $value ) ? ( $value['label'] ?? '' ) : $key;
			$fact = is_array( $value ) ? ( $value['value'] ?? '' ) : $value;
			if ( is_scalar( $label ) && is_scalar( $fact ) && '' !== trim( (string) $label ) && '' !== trim( (string) $fact ) ) {
				$rows .= '<dt>' . esc_html( (string) $label ) . '</dt><dd>' . esc_html( (string) $fact ) . '</dd>';
			}
		}
		if ( $rows ) {
			return '<dl class="ch-product-facts">' . $rows . '</dl>';
		}
		return '' !== trim( $empty_text ) ? '<p class="ch-product-facts-empty">' . esc_html( $empty_text ) . '</p>' : '';
	}
}
