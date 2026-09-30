<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Elementor_Room_Filters_Widget extends \Elementor\Widget_Base {
	public function get_name(): string { return 'chidemoon-room-filters'; }
	public function get_title(): string { return 'چیدمون | فیلتر فضای خانه'; }
	public function get_icon(): string { return 'eicon-filter'; }
	public function get_categories(): array { return array( 'general' ); }

	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => 'فضاهای خانه' ) );
		$this->add_control( 'all_label', array( 'label' => 'عنوان همهٔ فضاها', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'همهٔ فضاها' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'style', array( 'label' => 'ظاهر', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'text_color', array( 'label' => 'رنگ متن', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ch-room-filters a' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'background', array( 'label' => 'پس‌زمینه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ch-room-filters a' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'active_color', array( 'label' => 'متن فضای انتخاب‌شده', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ch-room-filters a[aria-current]' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'active_background', array( 'label' => 'پس‌زمینهٔ فضای انتخاب‌شده', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ch-room-filters a[aria-current]' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'border_color', array( 'label' => 'رنگ کادر', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .ch-room-filters a' => 'border-color: {{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'typography', 'selector' => '{{WRAPPER}} .ch-room-filters a' ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'فاصلهٔ گزینه‌ها', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}} .ch-room-filters' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'radius', array( 'label' => 'گردی گوشه‌ها', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 32 ) ), 'selectors' => array( '{{WRAPPER}} .ch-room-filters a' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'padding', array( 'label' => 'فاصلهٔ داخلی', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'selectors' => array( '{{WRAPPER}} .ch-room-filters a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render(): void {
		echo Chidemoon_Core_Public_Design::room_filters( $this->get_settings_for_display() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
