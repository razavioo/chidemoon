<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Chidemoon_Core_Elementor_Discovery_Widget extends \Elementor\Widget_Base {
 public function get_name(): string { return 'chidemoon-discovery'; }
 public function get_title(): string { return __( 'Product filters and guided chooser', 'chidemoon-core' ); }
 public function get_icon(): string { return 'eicon-filter'; }
 public function get_categories(): array { return array( 'chidemoon-commerce' ); }
 protected function register_controls(): void {
  $this->start_controls_section( 'content', array( 'label' => __( 'Discovery', 'chidemoon-core' ) ) );
  $this->add_control( 'mode', array( 'label' => __( 'Mode', 'chidemoon-core' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'filters', 'options' => array( 'filters' => __( 'Catalog filters', 'chidemoon-core' ), 'chooser' => __( 'Guided chooser', 'chidemoon-core' ) ) ) );
  $this->end_controls_section();
 }
 protected function render(): void { $settings = $this->get_settings_for_display(); echo 'chooser' === ( $settings['mode'] ?? '' ) ? Chidemoon_Core_Discovery::chooser() : Chidemoon_Core_Discovery::filters(); }
}
