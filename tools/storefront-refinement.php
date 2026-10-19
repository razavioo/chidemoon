<?php
/** Targeted, repeatable Elementor upgrade. wp eval-file tools/storefront-refinement.php [apply] */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
$apply = in_array( 'apply', $args ?? array(), true );
$posts = get_posts( array( 'post_type' => array( 'elementor_library', 'page', 'post' ), 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_elementor_data' ) );
foreach ( $posts as $post ) {
	$raw = get_post_meta( $post->ID, '_elementor_data', true );
	$elements = json_decode( $raw, true );
	if ( ! is_array( $elements ) ) { continue; }
	$walk = static function ( array &$nodes ) use ( &$walk ): void {
		foreach ( $nodes as &$node ) {
			$settings = &$node['settings'];
			if ( ! is_array( $settings ) ) { $settings = array(); }
			foreach ( array( 'title', 'editor', 'facts_heading' ) as $key ) {
				if ( ! isset( $settings[ $key ] ) || ! is_string( $settings[ $key ] ) ) { continue; }
				$settings[ $key ] = str_replace( array( 'مشخصات بررسی‌شده', 'دربارهٔ محصول', 'مقاله‌های مقایسهٔ چیدمون به تفاوت کاربرد، اندازه و شرایط استفاده می‌پردازند.', 'مقایسه‌های تحریریه را بخوان و تفاوت گزینه‌ها را بر اساس کاربرد، اندازه و نیاز خانه‌ات بررسی کن.' ), array( 'مشخصات محصول', 'درباره محصول', 'بین چند گزینه مرددی؟ تفاوت‌ها را ببین و مناسب‌ترین را برای خانه‌ات انتخاب کن.', '' ), $settings[ $key ] );
			}
			if ( 'chidemoon-product-offer' === ( $node['widgetType'] ?? '' ) ) {
				$settings['disclosure_text'] = ''; $settings['price_notice'] = ''; $settings['show_source'] = 'no'; $settings['facts_empty_text'] = '';
			}
			if ( ! empty( $node['elements'] ) ) { $walk( $node['elements'] ); }
		}
	};
	$walk( $elements );
	if ( 'chidemoon-category-cards' === $post->post_name ) {
		$categories = static function ( array &$nodes ) use ( &$categories ): void {
			foreach ( $nodes as &$node ) {
				if ( 'loop-grid' === ( $node['widgetType'] ?? '' ) && 'product_taxonomy' === ( $node['settings']['_skin'] ?? '' ) ) { $node['widgetType'] = 'shortcode'; $node['settings'] = array( 'shortcode' => '[chidemoon_category_tree]' ); }
				if ( ! empty( $node['elements'] ) ) { $categories( $node['elements'] ); }
			}
		};
		$categories( $elements );
	}
	if ( 'chidemoon-product-archive' === $post->post_name && ! str_contains( $raw, 'ch-catalog-layout' ) ) {
		$section = &$elements[0]['elements'][0];
		$tools = array(); $remaining = array();
		foreach ( $section['elements'] ?? array() as $node ) { if ( in_array( $node['widgetType'] ?? '', array( 'chidemoon-catalog-tools', 'loop-grid' ), true ) ) { $tools[] = $node; } else { $remaining[] = $node; } }
		if ( 2 === count( $tools ) ) {
			$box = static fn( $id, $class, $children ) => array( 'id' => $id, 'elType' => 'container', 'settings' => array( 'content_width' => 'full', 'css_classes' => $class, 'flex_direction' => 'column' ), 'elements' => $children, 'isInner' => true );
			$sidebar = $box( 'chside01', 'ch-catalog-sidebar', array( array( 'id' => 'chside03', 'elType' => 'widget', 'widgetType' => 'chidemoon-discovery', 'settings' => array( 'mode' => 'sidebar' ), 'elements' => array() ) ) );
			$sidebar['settings']['html_tag'] = 'aside';
			$remaining[] = $box( 'chlayout', 'ch-catalog-layout', array( $sidebar, $box( 'chresult', 'ch-catalog-results', $tools ) ) );
			$section['elements'] = $remaining;
		}
		unset( $section );
	}
	if ( wp_json_encode( json_decode( $raw, true ) ) === wp_json_encode( $elements ) ) { continue; }
	WP_CLI::log( ( $apply ? 'Updating ' : 'Would update ' ) . $post->ID . ': ' . $post->post_title );
	if ( $apply ) {
		add_post_meta( $post->ID, '_chidemoon_before_storefront_20261019', $raw, true );
		$doc = \Elementor\Plugin::$instance->documents->get( $post->ID, false );
		if ( ! $doc || false === $doc->save( array( 'elements' => $elements ) ) ) { WP_CLI::error( 'Unable to save ' . $post->ID ); }
	}
}
if ( $apply ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); wp_cache_flush(); }
WP_CLI::success( $apply ? 'Storefront documents updated.' : 'Dry run complete.' );
