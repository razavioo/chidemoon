<?php
/**
 * Elementor widget for product comparison table.
 * Reuses the same rendering as the shortcode and Gutenberg block, so the design stays consistent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_Core_Elementor_Compare_Widget extends \Elementor\Widget_Base {

	public function get_name(): string { return 'chidemoon-compare-table'; }
	public function get_title(): string { return __( 'Chidemoon جدول مقایسه', 'chidemoon-core' ); }
	public function get_icon(): string { return 'eicon-table'; }
	public function get_categories(): array { return array( 'chidemoon-commerce', 'general' ); }
	public function get_keywords(): array { return array( 'compare', 'product', 'chidemoon', 'مقایسه' ); }
	public function get_style_depends(): array { return array( 'chidemoon-core-compare' ); }
	public function get_script_depends(): array { return array( 'chidemoon-core-compare' ); }

	protected function register_controls(): void {
		$this->start_controls_section( 'section_content', array(
			'label' => __( 'محصولات', 'chidemoon-core' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'products_help', array(
			'type' => \Elementor\Controls_Manager::RAW_HTML,
			'raw'  => esc_html__( 'تا چهار محصول را انتخاب کنید. انتخاب خالی از نشانی صفحه خوانده می‌شود. فقط محصولات بررسی‌شده در خروجی عمومی نمایش داده می‌شوند.', 'chidemoon-core' ),
		) );

		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'product_id', array(
			'label' => __( 'محصول', 'chidemoon-core' ),
			'type'  => \Elementor\Controls_Manager::SELECT2,
			'options' => Chidemoon_Core_Public_Design::product_options(),
			'label_block' => true,
		) );
		$this->add_control( 'products', array(
			'label'       => __( 'محصولات برای مقایسه', 'chidemoon-core' ),
			'type'        => \Elementor\Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'title_field' => 'محصول #{{ product_id }}',
		) );

		$this->add_control( 'ids_text', array(
			'label'       => __( 'یا شناسه‌ها با کاما', 'chidemoon-core' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'placeholder' => '12,34,56',
			'description' => __( 'اگر پر شود، بر لیست بالا اولویت دارد.', 'chidemoon-core' ),
		) );
		$this->add_control( 'show_picker', array( 'label' => 'انتخاب‌گر محصولات', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '' ) );
		$this->add_control( 'show_status', array( 'label' => 'وضعیت انتخاب‌ها', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '' ) );
		foreach ( array( 'search_label' => array( 'عنوان انتخاب‌گر', 'محصولات را انتخاب کن' ), 'search_placeholder' => array( 'متن جست‌وجو', 'نام محصول یا مدل را جست‌وجو کن' ), 'selection_hint' => array( 'راهنمای انتخاب', '۲ تا ۴ محصول برای مقایسه' ), 'empty_title' => array( 'عنوان فهرست خالی', 'محصولی برای مقایسه موجود نیست' ), 'empty_text' => array( 'توضیح فهرست خالی', 'محصولات پس از بررسی مشخصات فروشنده به این فهرست اضافه می‌شوند.' ), 'table_empty_text' => array( 'متن جدول خالی', 'دو محصول انتخاب کن تا ویژگی‌ها را کنار هم ببینی.' ) ) as $key => $control ) {
			$this->add_control( $key, array( 'label' => $control[0], 'type' => \Elementor\Controls_Manager::TEXT, 'default' => $control[1], 'label_block' => true ) );
		}

		$this->end_controls_section();
		$this->start_controls_section( 'section_picker_style', array( 'label' => 'انتخاب‌گر و کارت‌ها', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'columns', array( 'label' => 'ستون‌های محصولات', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => array( '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴' ), 'selectors' => array( '{{WRAPPER}} .chidemoon-core-product-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ) ) );
		$this->add_responsive_control( 'card_gap', array( 'label' => 'فاصلهٔ کارت‌ها', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 64 ) ), 'selectors' => array( '{{WRAPPER}} .chidemoon-core-product-grid' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'search_width', array( 'label' => 'عرض جست‌وجو', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array( '%', 'px' ), 'range' => array( '%' => array( 'min' => 20, 'max' => 100 ), 'px' => array( 'min' => 200, 'max' => 1280 ) ), 'selectors' => array( '{{WRAPPER}} .chidemoon-comparison-search' => 'max-inline-size: {{SIZE}}{{UNIT}};' ) ) );
		foreach ( array( 'card_color' => array( 'پس‌زمینهٔ کارت', '.chidemoon-core-product-card', 'background-color' ), 'picker_text_color' => array( 'متن و عنوان‌ها', '.chidemoon-comparison-picker, .chidemoon-core-product-card__body h3, .chidemoon-comparison-search label', 'color' ), 'price_color' => array( 'قیمت', '.chidemoon-core-product-card__price', 'color' ), 'search_background' => array( 'پس‌زمینهٔ جست‌وجو', '.chidemoon-comparison-search__field', 'background-color' ) ) as $key => $control ) {
			$selector = implode( ', ', array_map( static fn( $part ) => '{{WRAPPER}} ' . trim( $part ), explode( ',', $control[1] ) ) );
			$this->add_control( $key, array( 'label' => $control[0], 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( $selector => $control[2] . ': {{VALUE}};' ) ) );
		}
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'picker_typography', 'selector' => '{{WRAPPER}} .chidemoon-comparison-picker' ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'card_title_typography', 'selector' => '{{WRAPPER}} .chidemoon-core-product-card h3' ) );
		$this->add_responsive_control( 'card_padding', array( 'label' => 'فاصلهٔ داخل کارت', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'selectors' => array( '{{WRAPPER}} .chidemoon-core-product-card__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_table_style', array( 'label' => __( 'ظاهر جدول', 'chidemoon-core' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		foreach ( array( 'text_color' => array( 'رنگ متن', 'color' ), 'background_color' => array( 'پس‌زمینه', 'background-color' ), 'border_color' => array( 'خطوط جدول', 'border-color' ) ) as $key => $control ) {
			$this->add_control( $key, array( 'label' => $control[0], 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .chidemoon-comparison-table th, {{WRAPPER}} .chidemoon-comparison-table td' => $control[1] . ': {{VALUE}};' ) ) );
		}
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'table_typography', 'selector' => '{{WRAPPER}} .chidemoon-comparison-table' ) );
		$this->add_responsive_control( 'cell_padding', array( 'label' => __( 'فاصلهٔ درون سلول', 'chidemoon-core' ), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .chidemoon-comparison-table th, {{WRAPPER}} .chidemoon-comparison-table td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'action_color', array( 'label' => 'رنگ دکمه‌ها', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} button, {{WRAPPER}} .chidemoon-button' => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ) ) );
		$this->add_control( 'action_text_color', array( 'label' => 'متن دکمه‌ها', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} button, {{WRAPPER}} .chidemoon-button' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'button_typography', 'selector' => '{{WRAPPER}} .chidemoon-compare-control, {{WRAPPER}} .chidemoon-button' ) );
		$this->add_responsive_control( 'button_padding', array( 'label' => 'فاصلهٔ درون دکمه', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'selectors' => array( '{{WRAPPER}} .chidemoon-compare-control, {{WRAPPER}} .chidemoon-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'button_radius', array( 'label' => 'گردی دکمه', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 32 ) ), 'selectors' => array( '{{WRAPPER}} .chidemoon-compare-control, {{WRAPPER}} .chidemoon-button' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		if ( 'yes' === ( $settings['show_picker'] ?? '' ) ) {
			echo Chidemoon_Core_Compare::render_picker_shortcode( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( 'yes' === ( $settings['show_status'] ?? '' ) ) {
			echo Chidemoon_Core_Compare::render_status_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		$ids = array();
		if ( ! empty( $settings['ids_text'] ) ) {
			$ids = array_map( 'absint', explode( ',', (string) $settings['ids_text'] ) );
		} elseif ( ! empty( $settings['products'] ) && is_array( $settings['products'] ) ) {
			foreach ( $settings['products'] as $row ) {
				$pid = absint( $row['product_id'] ?? 0 );
				if ( $pid ) $ids[] = $pid;
			}
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		echo Chidemoon_Core_Compare::render_compare_table_shortcode( array( 'products' => implode( ',', array_slice( $ids, 0, 4 ) ), 'show_empty' => 'yes' !== ( $settings['show_picker'] ?? '' ), 'empty_text' => $settings['table_empty_text'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

}
