<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Elementor_Search_Facets_Widget extends \Elementor\Widget_Base {
	public function get_name(): string { return 'chidemoon-search-facets'; }
	public function get_title(): string { return 'چیدمون | دسته‌بندی نتایج جست‌وجو'; }
	public function get_icon(): string { return 'eicon-filter'; }
	public function get_categories(): array { return array( 'general' ); }

	protected function render(): void {
		echo Chidemoon_Core_Search_Facets::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
