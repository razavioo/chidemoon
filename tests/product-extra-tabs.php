<?php
declare( strict_types=1 );

namespace Elementor {
	class Controls_Manager {
		public const TAB_CONTENT = 'content';
		public const SWITCHER = 'switcher';
		public const TEXT = 'text';
	}
}

namespace ElementorPro\Modules\Woocommerce\Widgets {
	class Product_Data_Tabs {
		public array $controls = array();
		public array $sections = array();
		public array $rendered_tabs = array();
		public bool $throw_on_render = false;
		public function __construct( private array $settings = array() ) {}
		public function get_name() { return 'woocommerce-product-data-tabs'; }
		public function get_title() { return 'Product Data Tabs'; }
		public function get_keywords() { return array( 'woocommerce', 'product' ); }
		public function get_style_depends(): array { return array( 'widget-woocommerce-product-data-tabs' ); }
		public function get_script_depends(): array { return array( 'native-parent-script' ); }
		protected function get_html_wrapper_class() { return 'elementor-widget-' . $this->get_name(); }
		protected function register_controls() {
			$this->start_controls_section( 'native_tabs_style', array( 'tab' => 'style' ) );
			$this->add_control( 'native_panel_typography', array( 'type' => 'typography' ) );
			$this->end_controls_section();
		}
		protected function render() {
			$this->rendered_tabs = \apply_filters( 'woocommerce_product_tabs', array() );
			if ( $this->throw_on_render ) { throw new \RuntimeException( 'Native parent render failed.' ); }
		}
		public function initialize_controls(): void { $this->register_controls(); }
		public function render_for_test(): void { $this->render(); }
		public function wrapper_for_test(): string { return $this->get_html_wrapper_class(); }
		protected function start_controls_section( string $id, array $settings ): void { $this->sections[ $id ] = $settings; }
		protected function end_controls_section(): void {}
		protected function add_control( string $id, array $settings ): void { $this->controls[ $id ] = $settings; }
		protected function get_settings_for_display(): array {
			$defaults = array();
			foreach ( $this->controls as $id => $control ) { if ( array_key_exists( 'default', $control ) ) { $defaults[ $id ] = $control['default']; } }
			return array_merge( $defaults, $this->settings );
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/../' );
	$GLOBALS['tab_filters'] = array();
	function __( string $text, string $domain ): string { return $text; }
	function sanitize_text_field( string $text ): string { return trim( strip_tags( $text ) ); }
	function add_filter( string $hook, callable $callback, int $priority = 10 ): bool {
		$GLOBALS['tab_filters'][ $hook ][ $priority ][] = $callback;
		return true;
	}
	function remove_filter( string $hook, callable $callback, int $priority = 10 ): bool {
		foreach ( $GLOBALS['tab_filters'][ $hook ][ $priority ] ?? array() as $key => $registered ) {
			if ( $registered === $callback ) {
				unset( $GLOBALS['tab_filters'][ $hook ][ $priority ][ $key ] );
				if ( ! $GLOBALS['tab_filters'][ $hook ][ $priority ] ) { unset( $GLOBALS['tab_filters'][ $hook ][ $priority ] ); }
				return true;
			}
		}
		return false;
	}
	function apply_filters( string $hook, array $value ): array {
		$filters = $GLOBALS['tab_filters'][ $hook ] ?? array();
		ksort( $filters );
		foreach ( $filters as $callbacks ) { foreach ( $callbacks as $callback ) { $value = $callback( $value ); } }
		return $value;
	}
	function check( bool $condition, string $message ): void { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	function widget( array $settings = array() ): Chidemoon_Core_Elementor_Product_Extra_Tabs_Widget {
		$widget = new Chidemoon_Core_Elementor_Product_Extra_Tabs_Widget( $settings );
		$widget->initialize_controls();
		return $widget;
	}
	require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-elementor-product-extra-tabs-widget.php';

	$description_callback = static fn () => 'Full description';
	$attributes_callback = static fn () => 'Product attributes';
	$reviews_callback = static fn () => 'Product reviews';
	$plugin_callback = static fn () => 'Independent plugin content';
	$stock_tabs = array(
		'description' => array( 'title' => 'توضیحات', 'priority' => 10, 'callback' => $description_callback ),
		'additional_information' => array( 'title' => 'اطلاعات بیشتر', 'priority' => 20, 'callback' => $attributes_callback ),
		'reviews' => array( 'title' => 'دیدگاه‌ها (۳)', 'priority' => 30, 'callback' => $reviews_callback ),
	);
	add_filter( 'woocommerce_product_tabs', static fn ( array $tabs ): array => $stock_tabs );
	add_filter( 'woocommerce_product_tabs', static function ( array $tabs ) use ( $plugin_callback ): array {
		$tabs['plugin_manual'] = array( 'title' => 'راهنمای نصب', 'priority' => 40, 'callback' => $plugin_callback, 'plugin_setting' => 'keep' );
		return $tabs;
	}, 99 );
	$before = apply_filters( 'woocommerce_product_tabs', array() );
	$hooks_before = $GLOBALS['tab_filters'];

	$default = widget();
	$default->render_for_test();
	check( ! isset( $default->rendered_tabs['description'] ), 'The independently editable description was duplicated in product tabs.' );
	check( $default->rendered_tabs['additional_information'] === $before['additional_information'], 'Native attribute data, title or callback changed.' );
	check( $default->rendered_tabs['reviews'] === $before['reviews'], 'Default review title/count or callback changed.' );
	check( $default->rendered_tabs['plugin_manual'] === $before['plugin_manual'], 'An unknown plugin tab or its callback was changed.' );
	check( $GLOBALS['tab_filters'] === $hooks_before && apply_filters( 'woocommerce_product_tabs', array() ) === $before, 'Rendering leaked a filter into subsequent WooCommerce renders.' );
	check( 'yes' === $default->controls['show_attributes']['default'] && 'yes' === $default->controls['show_reviews']['default'], 'Product attributes and reviews must be shown by default.' );
	check( isset( $default->controls['info_title'], $default->controls['reviews_title'], $default->controls['native_panel_typography'] ), 'Graphical title controls or inherited native Style controls are missing.' );
	check( 'content' === $default->sections['chidemoon_extra_tabs_content']['tab'], 'Product tab controls are not exposed in the Elementor Content panel.' );
	check( array( 'widget-woocommerce-product-data-tabs' ) === $default->get_style_depends() && array( 'native-parent-script' ) === $default->get_script_depends(), 'Native styles or scripts were replaced.' );
	check( str_contains( $default->wrapper_for_test(), 'elementor-widget-chidemoon-product-extra-tabs' ) && str_contains( $default->wrapper_for_test(), 'elementor-widget-woocommerce-product-data-tabs' ), 'Native styling wrapper compatibility was lost.' );

	$custom = widget( array( 'info_title' => '  <b>مشخصات محصول</b> ', 'reviews_title' => 'تجربهٔ خریداران' ) );
	$custom->render_for_test();
	check( 'مشخصات محصول' === $custom->rendered_tabs['additional_information']['title'] && 'تجربهٔ خریداران' === $custom->rendered_tabs['reviews']['title'], 'Graphically edited product tab titles are not rendered.' );
	check( $attributes_callback === $custom->rendered_tabs['additional_information']['callback'] && $reviews_callback === $custom->rendered_tabs['reviews']['callback'], 'Changing a title replaced its native tab callback.' );
	$hidden = widget( array( 'show_attributes' => '', 'show_reviews' => '', 'info_title' => 'Hidden attributes' ) );
	$hidden->render_for_test();
	check( array( 'plugin_manual' ) === array_keys( $hidden->rendered_tabs ), 'Hidden native tabs removed an unrelated plugin tab.' );

	$no_native = static function ( array $tabs ): array { unset( $tabs['additional_information'], $tabs['reviews'] ); return $tabs; };
	add_filter( 'woocommerce_product_tabs', $no_native, 200 );
	$missing = widget( array( 'info_title' => 'عنوان', 'reviews_title' => 'عنوان' ) );
	$missing->render_for_test();
	check( array( 'plugin_manual' ) === array_keys( $missing->rendered_tabs ), 'Title controls created nonexistent native tabs for a product.' );
	remove_filter( 'woocommerce_product_tabs', $no_native, 200 );

	$failure = widget();
	$failure->throw_on_render = true;
	try { $failure->render_for_test(); throw new \RuntimeException( 'Expected parent rendering failure.' ); }
	catch ( \RuntimeException $error ) { check( 'Native parent render failed.' === $error->getMessage(), 'Parent render failure was swallowed.' ); }
	check( $GLOBALS['tab_filters'] === $hooks_before && apply_filters( 'woocommerce_product_tabs', array() ) === $before, 'A failed native render leaked the widget filter.' );
	echo "Product extra tabs preserve native and plugin content, graphical controls, and scoped filters.\n";
}
