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
$look_page = get_page_by_path( 'shop-the-look' );
$look_elements = json_decode( (string) get_post_meta( $look_page->ID, '_elementor_data', true ), true );
$find_look = static function ( array $elements ) use ( &$find_look ): array {
	foreach ( $elements as $element ) {
		if ( 'chidemoon-shop-the-look' === ( $element['widgetType'] ?? '' ) ) {
			return $element['settings'] ?? array();
		}
		$nested = $find_look( $element['elements'] ?? array() );
		if ( $nested ) {
			return $nested;
		}
	}
	return array();
};
$look_settings = $find_look( is_array( $look_elements ) ? $look_elements : array() );
$check( wp_attachment_is_image( (int) ( $look_settings['image']['id'] ?? 0 ) ), 'Shop the Look has a scene image.' );
foreach ( array( 'basalam:25688211', 'basalam:33684609' ) as $source_key ) {
	$spot_found = false;
	foreach ( $look_settings['hotspots'] ?? array() as $spot ) {
		if ( $source_key === ( $spot['product_source_key'] ?? '' ) ) {
			$spot_found = true;
			break;
		}
	}
	$products = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => Chidemoon_Core_Affiliate::META_SOURCE_KEY, 'meta_value' => $source_key ) );
	$product = $products ? wc_get_product( (int) $products[0] ) : null;
	$check( $spot_found && $product instanceof WC_Product && Chidemoon_Core_Affiliate::is_publicly_eligible( $product ), 'Shoppable product is published and reviewed: ' . $source_key );
}
foreach ( wc_get_products( array( 'status' => 'publish', 'limit' => -1 ) ) as $product ) {
	$check( Chidemoon_Core_Affiliate::is_publicly_eligible( $product ), 'Reviewed external product #' . $product->get_id() );
}
WP_CLI::log( wp_json_encode( $types, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
WP_CLI::success( 'Installed Elementor acceptance checks passed.' );
