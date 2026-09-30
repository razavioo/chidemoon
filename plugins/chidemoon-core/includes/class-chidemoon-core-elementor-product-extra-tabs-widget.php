<?php
/** Native WooCommerce attributes, reviews and plugin tabs alongside the editable product body. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Elementor_Product_Extra_Tabs_Widget extends \ElementorPro\Modules\Woocommerce\Widgets\Product_Data_Tabs {
	public function get_name(): string { return 'chidemoon-product-extra-tabs'; }
	public function get_title(): string { return __( 'چیدمون | مشخصات و دیدگاه‌های محصول', 'chidemoon-core' ); }
	public function get_keywords(): array { return array_merge( parent::get_keywords(), array( 'چیدمون', 'مشخصات', 'دیدگاه', 'نظر', 'attribute', 'review' ) ); }

	protected function get_html_wrapper_class() {
		return parent::get_html_wrapper_class() . ' elementor-widget-woocommerce-product-data-tabs';
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'chidemoon_extra_tabs_content', array( 'label' => __( 'بخش‌های محصول', 'chidemoon-core' ), 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'show_attributes', array( 'label' => __( 'نمایش مشخصات تکمیلی', 'chidemoon-core' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'info_title', array( 'label' => __( 'عنوان زبانهٔ مشخصات', 'chidemoon-core' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '', 'dynamic' => array( 'active' => true ), 'description' => __( 'عنوان خالی از متن پیش‌فرض ووکامرس استفاده می‌کند.', 'chidemoon-core' ), 'condition' => array( 'show_attributes' => 'yes' ) ) );
		$this->add_control( 'show_reviews', array( 'label' => __( 'نمایش دیدگاه‌ها', 'chidemoon-core' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'reviews_title', array( 'label' => __( 'عنوان زبانهٔ دیدگاه‌ها', 'chidemoon-core' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '', 'dynamic' => array( 'active' => true ), 'description' => __( 'عنوان خالی از متن و تعداد دیدگاه‌های ووکامرس استفاده می‌کند.', 'chidemoon-core' ), 'condition' => array( 'show_reviews' => 'yes' ) ) );
		$this->end_controls_section();
		// Retain all native tab/panel typography, colours, borders and other Style controls.
		parent::register_controls();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$filter = static function ( array $tabs ) use ( $settings ): array {
			unset( $tabs['description'] );
			foreach ( array( 'additional_information' => array( 'show_attributes', 'info_title' ), 'reviews' => array( 'show_reviews', 'reviews_title' ) ) as $tab => $controls ) {
				if ( 'yes' !== ( $settings[ $controls[0] ] ?? 'yes' ) ) { unset( $tabs[ $tab ] ); continue; }
				$title = trim( sanitize_text_field( (string) ( $settings[ $controls[1] ] ?? '' ) ) );
				if ( '' !== $title && isset( $tabs[ $tab ] ) ) { $tabs[ $tab ]['title'] = $title; }
			}
			return $tabs;
		};
		// Apply after WooCommerce creates/sorts tabs, only for this widget's render call.
		add_filter( 'woocommerce_product_tabs', $filter, PHP_INT_MAX );
		try {
			parent::render();
		} finally {
			remove_filter( 'woocommerce_product_tabs', $filter, PHP_INT_MAX );
		}
	}
}
