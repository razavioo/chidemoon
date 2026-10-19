<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Chidemoon_Core_Elementor_Product_Context_Widget extends \Elementor\Widget_Base {
 public function get_name(): string { return 'chidemoon-product-context'; }
 public function get_title(): string { return 'محتوای مرتبط با محصول'; }
 public function get_icon(): string { return 'eicon-posts-grid'; }
 public function get_categories(): array { return array( 'general' ); }
 protected function register_controls(): void {
  $this->start_controls_section( 'content', array( 'label' => 'محتوا' ) );
  $this->add_control( 'heading', array( 'label' => 'عنوان', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'این محصول در چیدمون' ) );
  $this->end_controls_section();
 }
 protected function render(): void { echo Chidemoon_Core_Content_Relations::render_context( $this->get_settings_for_display() ); }
}
