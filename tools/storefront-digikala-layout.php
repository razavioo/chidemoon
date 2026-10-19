<?php
/** Repeatable native product layout revision; wp eval-file ... [apply]. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
$apply = in_array( 'apply', $args ?? array(), true );
$post = get_page_by_path( 'chidemoon-product-single', OBJECT, 'elementor_library' );
if ( ! $post ) { WP_CLI::error( 'Product template missing.' ); }
$raw = get_post_meta( $post->ID, '_elementor_data', true );
$elements = json_decode( $raw, true );
if ( ! is_array( $elements ) ) { WP_CLI::error( 'Invalid product template.' ); }
$walk = static function ( array &$nodes ) use ( &$walk ): void {
	foreach ( $nodes as &$node ) {
		$class = $node['settings']['css_classes'] ?? $node['settings']['_css_classes'] ?? '';
		if ( 'ch-product-layout' === $class && ! str_contains( wp_json_encode( $node ), 'ch-product-purchase' ) ) {
			$purchase = array();
			foreach ( $node['elements'] as &$child ) {
				if ( 'ch-product-summary' !== ( $child['settings']['css_classes'] ?? '' ) ) { continue; }
				$summary = array();
				foreach ( $child['elements'] as $widget ) {
					if ( in_array( $widget['widgetType'] ?? '', array( 'woocommerce-product-price', 'chidemoon-product-offer' ), true ) ) { $purchase[] = $widget; } else { $summary[] = $widget; }
				}
				$summary[] = array( 'id' => 'chdkfeat', 'elType' => 'widget', 'widgetType' => 'chidemoon-product-offer', 'settings' => array( 'facts_only' => 'yes', 'facts_limit' => 6, 'facts_heading' => 'ویژگی‌ها', '_css_classes' => 'ch-product-highlights-widget' ), 'elements' => array() );
				$child['elements'] = $summary;
			}
			unset( $child );
			if ( $purchase ) { $node['elements'][] = array( 'id' => 'chdkbuy', 'elType' => 'container', 'settings' => array( 'content_width' => 'full', 'flex_direction' => 'column', 'css_classes' => 'ch-product-purchase', 'html_tag' => 'aside' ), 'elements' => $purchase, 'isInner' => true ); }
		}
		if ( ! empty( $node['elements'] ) ) { $walk( $node['elements'] ); }
		if ( str_contains( $class, 'ch-product-single' ) ) {
			$facts = null; $description = null; $reviews = null; $rest = array(); $position = null;
			foreach ( $node['elements'] as $child ) {
				$child_class = $child['settings']['css_classes'] ?? $child['settings']['_css_classes'] ?? '';
				if ( 'ch-product-facts-widget' === $child_class ) { $facts = $child; $position ??= count( $rest ); }
				elseif ( 'ch-product-description' === $child_class ) {
					$description = $child; $position ??= count( $rest ); $body = array();
					foreach ( $child['elements'] as $widget ) { if ( 'chidemoon-product-extra-tabs' === ( $widget['widgetType'] ?? '' ) ) { $reviews = $widget; } else { $body[] = $widget; } }
					$description['elements'] = $body;
				} elseif ( 'chidemoon-product-extra-tabs' === ( $child['widgetType'] ?? '' ) ) { $reviews = $child; }
				else { $rest[] = $child; }
			}
			if ( $facts && $description ) { array_splice( $rest, $position, 0, array_values( array_filter( array( $description, $facts, $reviews ) ) ) ); $node['elements'] = $rest; }
		}
	}
};
$walk( $elements );
if ( wp_json_encode( $elements ) === wp_json_encode( json_decode( $raw, true ) ) ) { WP_CLI::success( 'Layout already updated.' ); return; }
if ( $apply ) {
	add_post_meta( $post->ID, '_chidemoon_before_digikala_layout_20261019', $raw, true );
	$doc = \Elementor\Plugin::$instance->documents->get( $post->ID, false );
	if ( false === $doc->save( array( 'elements' => $elements ) ) ) { WP_CLI::error( 'Save failed.' ); }
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
WP_CLI::success( $apply ? 'Native product layout updated.' : 'Would update native product layout.' );
