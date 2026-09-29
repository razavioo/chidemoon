<?php
/** WP-CLI acceptance checks for the installed visual ownership contract. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

$check = static function ( bool $passed, string $message ): void {
	if ( ! $passed ) {
		WP_CLI::error( $message );
	}
	WP_CLI::success( $message );
};
$check( 'hello-elementor' === get_stylesheet(), 'Hello Elementor is active.' );
$check( class_exists( '\\ElementorPro\\Plugin' ), 'Elementor Pro is active.' );
$check( class_exists( IntlDateFormatter::class ), 'ICU Persian calendar is available.' );
$check( 'Asia/Tehran' === wp_timezone_string(), 'Display dates use the Tehran timezone.' );
$widgets = \Elementor\Plugin::$instance->widgets_manager;
$types = array();
$validate = static function ( array $elements, array &$ids ) use ( &$validate, &$types, $widgets, $check ): void {
	foreach ( $elements as $element ) {
		$check( ! empty( $element['id'] ) && ! isset( $ids[ $element['id'] ] ), 'Unique element ' . ( $element['id'] ?? '(missing)' ) );
		$ids[ $element['id'] ] = true;
		if ( 'widget' === $element['elType'] ) {
			$type = $element['widgetType'];
			$check( 'html' !== $type && (bool) $widgets->get_widget_types( $type ), 'Native/registered editable widget ' . $type );
			$types[ $type ] = ( $types[ $type ] ?? 0 ) + 1;
		}
		$validate( $element['elements'] ?? array(), $ids );
	}
};
$documents = array();
foreach ( array( 'home', 'guides', 'comparisons', 'shop-the-look' ) as $slug ) {
	$page = get_page_by_path( $slug );
	$check( $page instanceof WP_Post && 'publish' === $page->post_status, 'Published page ' . $slug );
	$documents[] = $page->ID;
}
foreach ( array( 'site-header' => 'header', 'site-footer' => 'footer', 'post-single' => 'single-post', 'post-archive' => 'archive', 'search-results' => 'search-results', 'not-found' => 'error-404', 'product-single' => 'product', 'product-archive' => 'product-archive' ) as $slug => $type ) {
	$template = get_page_by_path( 'chidemoon-' . $slug, OBJECT, 'elementor_library' );
	$check( $template instanceof WP_Post && 'publish' === $template->post_status, 'Published template ' . $slug );
	$check( $type === get_post_meta( $template->ID, '_elementor_template_type', true ) && (bool) get_post_meta( $template->ID, '_elementor_conditions', true ), 'Theme Builder type and conditions: ' . $slug );
	$documents[] = $template->ID;
}
foreach ( $documents as $id ) {
	$elements = json_decode( get_post_meta( $id, '_elementor_data', true ), true );
	$check( 'builder' === get_post_meta( $id, '_elementor_edit_mode', true ) && is_array( $elements ) && count( $elements ) > 0, 'Structured Elementor document #' . $id );
	$ids = array();
	$validate( $elements, $ids );
}
foreach ( wc_get_products( array( 'status' => 'publish', 'limit' => -1 ) ) as $product ) {
	$check( Chidemoon_Core_Affiliate::is_publicly_eligible( $product ), 'Reviewed external product #' . $product->get_id() );
}
WP_CLI::log( wp_json_encode( $types, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
WP_CLI::success( 'Installed Elementor acceptance checks passed.' );
