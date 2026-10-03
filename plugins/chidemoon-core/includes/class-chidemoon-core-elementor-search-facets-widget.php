<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Elementor_Search_Facets_Widget extends \Elementor\Widget_Base {
	public function get_name(): string { return 'chidemoon-search-facets'; }
	public function get_title(): string { return 'چیدمون | دسته‌بندی نتایج جست‌وجو'; }
	public function get_icon(): string { return 'eicon-filter'; }
	public function get_categories(): array { return array( 'general' ); }

	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => 'دسته‌بندی نتایج' ) );
		foreach ( array( 'all_label' => array( 'عنوان همه', 'همه' ), 'product_label' => array( 'عنوان محصولات', 'محصولات' ), 'post_label' => array( 'عنوان مطالب', 'مطالب' ) ) as $key => $control ) {
			$this->add_control( $key, array( 'label' => $control[0], 'type' => \Elementor\Controls_Manager::TEXT, 'default' => $control[1] ) );
		}
		$this->add_control( 'hide_counts', array( 'label' => 'پنهان کردن شمارنده‌ها', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style', array( 'label' => 'ظاهر فیلترها', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'typography', 'selector' => '{{WRAPPER}} .ch-search-facets a' ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'فاصلهٔ گزینه‌ها', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}} .ch-search-facets' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'padding', array( 'label' => 'فاصلهٔ داخلی گزینه', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'selectors' => array( '{{WRAPPER}} .ch-search-facets a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		foreach ( array( 'text_color' => array( 'رنگ متن', 'a', 'color' ), 'background' => array( 'پس‌زمینه', 'a', 'background-color' ), 'border_color' => array( 'رنگ کادر', 'a', 'border-color' ), 'active_color' => array( 'متن انتخاب‌شده', 'a[aria-current]', 'color' ), 'active_background' => array( 'پس‌زمینهٔ انتخاب‌شده', 'a[aria-current]', 'background-color' ) ) as $key => $control ) {
			$this->add_control( $key, array( 'label' => $control[0], 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ch-search-facets ' . $control[1] => $control[2] . ': {{VALUE}};' ) ) );
		}
		$this->end_controls_section();
	}

	protected function render(): void {
		$preview = ! is_search() && \Elementor\Plugin::$instance->editor->is_edit_mode();
		echo Chidemoon_Core_Search_Facets::render( $this->get_settings_for_display(), $preview ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
