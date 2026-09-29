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

		$this->end_controls_section();
		$this->start_controls_section( 'section_table_style', array( 'label' => __( 'ظاهر جدول', 'chidemoon-core' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		foreach ( array( 'text_color' => array( 'رنگ متن', 'color' ), 'background_color' => array( 'پس‌زمینه', 'background-color' ), 'border_color' => array( 'خطوط جدول', 'border-color' ) ) as $key => $control ) {
			$this->add_control( $key, array( 'label' => $control[0], 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .chidemoon-comparison-table th, {{WRAPPER}} .chidemoon-comparison-table td' => $control[1] . ': {{VALUE}};' ) ) );
		}
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'table_typography', 'selector' => '{{WRAPPER}} .chidemoon-comparison-table' ) );
		$this->add_responsive_control( 'cell_padding', array( 'label' => __( 'فاصلهٔ درون سلول', 'chidemoon-core' ), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .chidemoon-comparison-table th, {{WRAPPER}} .chidemoon-comparison-table td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'action_color', array( 'label' => 'رنگ دکمه‌ها', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} button, {{WRAPPER}} .chidemoon-button' => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ) ) );
		$this->add_control( 'action_text_color', array( 'label' => 'متن دکمه‌ها', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} button, {{WRAPPER}} .chidemoon-button' => 'color: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		if ( 'yes' === ( $settings['show_picker'] ?? '' ) ) {
			echo Chidemoon_Core_Compare::render_picker_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
		if ( count( $ids ) < 2 ) {
			// Try query var for preview on generic comparison page
			if ( empty( $ids ) ) {
				echo \Chidemoon_Core_Compare::render_compare_table_shortcode( array( 'products' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				return;
			}
			echo '<div class="elementor-alert elementor-alert-warning">' . esc_html__( 'حداقل دو محصول برای جدول مقایسه لازم است.', 'chidemoon-core' ) . '</div>';
			return;
		}
		echo \Chidemoon_Core_Compare::render_compare_table_shortcode( array( 'products' => implode( ',', array_slice( $ids, 0, 4 ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

}
