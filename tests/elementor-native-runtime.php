<?php
/**
 * Run after the native migrations in a disposable local WordPress installation:
 * wp --user=<admin> eval-file /path/to/tests/elementor-native-runtime.php
 * Creates isolated fixtures and removes them before reporting results.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost', '::1' ), true ) ) {
	WP_CLI::error( 'These fixture checks require a disposable local WordPress site.' );
}

$failures = array();
$checks = 0;
$GLOBALS['ch_native_runtime_checks'] =& $checks;
$GLOBALS['ch_native_runtime_failures'] =& $failures;
$fixtures = array();
$comments = array();
$terms = array();
$original_globals = array();
foreach ( array( 'post', 'product', 'wp_query', 'wp_the_query', 'woocommerce_loop' ) as $key ) {
	$original_globals[ $key ] = array( 'exists' => array_key_exists( $key, $GLOBALS ), 'value' => $GLOBALS[ $key ] ?? null );
}
$original_get = $_GET;
if ( ! ( $GLOBALS['wp_query'] ?? null ) instanceof WP_Query ) {
	$GLOBALS['wp_query'] = new WP_Query();
}
$hide_stock = static fn() => 'yes';

function ch_native_check( bool $condition, string $message ): void {
	++$GLOBALS['ch_native_runtime_checks'];
	if ( ! $condition ) { $GLOBALS['ch_native_runtime_failures'][] = $message; }
}

function ch_native_widget( string $name, array $settings = array() ): \Elementor\Widget_Base {
	$widget = \Elementor\Plugin::$instance->elements_manager->create_element_instance( array(
		'id' => substr( md5( $name . wp_json_encode( $settings ) ), 0, 8 ),
		'elType' => 'widget', 'widgetType' => $name, 'settings' => $settings, 'elements' => array(),
	) );
	if ( ! $widget instanceof \Elementor\Widget_Base ) { throw new RuntimeException( 'Missing widget: ' . $name ); }
	return $widget;
}

function ch_native_render( callable $render ): string {
	ob_start();
	try { $render(); return (string) ob_get_contents(); }
	finally { ob_end_clean(); }
}

function ch_native_product_field( string $field ): string {
	$tag = new Chidemoon_Core_Elementor_Product_Field_Tag( array( 'id' => 'field-' . $field, 'settings' => array( 'field' => $field, 'merchant_label' => 'فروشنده:' ) ) );
	return ch_native_render( static fn() => $tag->render() );
}

function ch_native_context( int $id, ?WC_Product $stale_product = null ): void {
	$GLOBALS['post'] = get_post( $id );
	setup_postdata( $GLOBALS['post'] );
	// This deliberately simulates a Woo global left over from a different card.
	$GLOBALS['product'] = $stale_product;
}

function ch_native_loop_card( int $template_id ): string {
	$document = \Elementor\Plugin::$instance->documents->get( $template_id, false );
	if ( ! $document ) { throw new RuntimeException( 'Missing Loop document #' . $template_id ); }
	$GLOBALS['wp_query']->is_loop_widget = true;
	return ch_native_render( static fn() => $document->print_content() );
}

try {
	// Elementor registers its widget/tag classes lazily during the first render.
	\Elementor\Plugin::$instance->dynamic_tags->get_tags();
	$product_template = get_page_by_path( 'chidemoon-product-card', OBJECT, 'elementor_library' );
	$search_template = get_page_by_path( 'chidemoon-search-card', OBJECT, 'elementor_library' );
	if ( ! $product_template || ! $search_template ) { throw new RuntimeException( 'Run the native editability migration before this check.' ); }
	$product_document = \Elementor\Plugin::$instance->documents->get( (int) $product_template->ID, false );
	ch_native_check( $product_document instanceof \ElementorPro\Modules\LoopBuilder\Documents\Loop, 'The product card is not a native editable Loop Item document.' );
	$preview_args = $product_document->get_preview_as_query_args();
	ch_native_check( 'product' === ( $preview_args['post_type'] ?? '' ) && 'publish' === get_post_status( (int) ( $preview_args['p'] ?? 0 ) ), 'The native product card editor has no published product preview.' );
	ch_native_check( in_array( 'product', (array) get_option( 'elementor_cpt_support' ), true ), 'The product body is unavailable to the native Elementor editor.' );
	$prefix = 'ch-native-' . strtolower( wp_generate_password( 8, false, false ) );
	foreach ( array( 'same', 'other' ) as $name ) {
		$term = wp_insert_term( $prefix . '-' . $name, 'product_cat' );
		if ( is_wp_error( $term ) ) { throw new RuntimeException( $term->get_error_message() ); }
		$terms[ $name ] = (int) $term['term_id'];
	}
	$products = array();
	foreach ( array( 'first', 'second', 'hidden', 'outstock', 'outside', 'unreviewed' ) as $index => $name ) {
		$product = 'outstock' === $name ? new WC_Product_Simple() : new WC_Product_External();
		$product->set_name( $prefix . '-' . $name );
		$product->set_status( 'publish' );
		$product->set_regular_price( (string) ( 111111 + $index * 222222 ) );
		if ( $product instanceof WC_Product_External ) { $product->set_product_url( 'https://seller.example/' . $name ); }
		$product->set_category_ids( array( $terms[ 'outside' === $name ? 'other' : 'same' ] ) );
		$product->set_catalog_visibility( 'hidden' === $name ? 'hidden' : 'visible' );
		$product->set_stock_status( 'outstock' === $name ? 'outofstock' : 'instock' );
		$product->set_short_description( '<p>' . $prefix . '-description-' . $name . '</p>' );
		if ( 'first' === $name ) {
			$attribute = new WC_Product_Attribute();
			$attribute->set_name( 'رنگ آزمایشی' );
			$attribute->set_options( array( $prefix . '-green-attribute' ) );
			$attribute->set_visible( true );
			$product->set_attributes( array( $attribute ) );
			$product->set_weight( '2.4' );
			$product->set_length( '31' );
			$product->set_width( '22' );
			$product->set_height( '13' );
			$product->set_description( '<p>' . $prefix . '-full-description</p>' );
			$product->set_reviews_allowed( true );
		}
		$product->update_meta_data( Chidemoon_Core_Affiliate::META_REVIEW_STATE, 'unreviewed' === $name ? 'draft' : 'reviewed' );
		$product->update_meta_data( Chidemoon_Core_Affiliate::META_AFFILIATE_URL, 'https://seller.example/' . $name );
		$product->update_meta_data( Chidemoon_Core_Affiliate::META_MERCHANT_NAME, $prefix . '-merchant-' . $name );
		$product->save();
		$fixtures[] = $product->get_id();
		$products[ $name ] = $product;
	}
	$review_id = wp_insert_comment( array( 'comment_post_ID' => $products['first']->get_id(), 'comment_author' => 'بازبین مستقل', 'comment_author_email' => 'native-qa@example.test', 'comment_content' => $prefix . '-approved-review-body', 'comment_type' => 'review', 'comment_approved' => 1 ) );
	if ( ! $review_id ) { throw new RuntimeException( 'Could not create the isolated product review.' ); }
	$comments[] = (int) $review_id;
	update_comment_meta( $review_id, 'rating', 5 );
	WC_Comments::clear_transients( $products['first']->get_id() );
	$products['first'] = wc_get_product( $products['first']->get_id() );
	$article = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $prefix . '-article', 'post_content' => 'این مقاله یک نتیجهٔ جست‌وجو است.' ), true );
	if ( is_wp_error( $article ) ) { throw new RuntimeException( $article->get_error_message() ); }
	$fixtures[] = (int) $article;

	// Each native element must resolve the current Loop item, including mixed search cards.
	foreach ( array( 'first' => 'second', 'second' => 'first' ) as $name => $stale ) {
		$current = $products[ $name ];
		ch_native_context( $current->get_id(), $products[ $stale ] );
		ch_native_check( str_contains( ch_native_product_field( 'merchant' ), $prefix . '-merchant-' . $name ), 'Merchant data leaks between Loop items: ' . $name );
		ch_native_check( ch_native_product_field( 'price' ) === wp_kses_post( $current->get_price_html() ), 'Price data leaks between Loop items: ' . $name );
		ch_native_check( str_contains( ch_native_product_field( 'short_description' ), $prefix . '-description-' . $name ), 'Short description resolves the wrong product: ' . $name );
		$html = ch_native_loop_card( (int) $product_template->ID );
		ch_native_check( str_contains( $html, $current->get_name() ) && ! str_contains( $html, $products[ $stale ]->get_name() ), 'The native product card title resolves the wrong item: ' . $name );
		ch_native_check( str_contains( $html, 'href="' . esc_url( get_permalink( $current->get_id() ) ) . '"' ) && str_contains( $html, 'data-compare-product="' . $current->get_id() . '"' ), 'Native card action identity resolves the wrong item: ' . $name );
	}
	ch_native_context( (int) $article, $products['first'] );
	foreach ( array( 'price', 'merchant', 'short_description' ) as $field ) {
		ch_native_check( '' === ch_native_product_field( $field ), 'An article displays stale product data: ' . $field );
	}
	$html = ch_native_loop_card( (int) $search_template->ID );
	ch_native_check( str_contains( $html, $prefix . '-article' ) && ! str_contains( $html, $prefix . '-merchant-first' ) && ! str_contains( $html, 'data-compare-product=' ), 'A native article search card displays product content or actions.' );

	// Changing labels in the widget must affect its actual runtime output.
	ch_native_context( $products['first']->get_id(), $products['second'] );
	$actions = ch_native_widget( 'chidemoon-product-actions', array( 'show_purchase' => 'yes', 'details_text' => 'جزئیات ویرایش‌شده', 'purchase_text' => 'خرید ویرایش‌شده', 'compare_text' => 'مقایسه ویرایش‌شده', 'compare_selected_text' => 'حذف ویرایش‌شده' ) );
	$render = new ReflectionMethod( $actions, 'render' );
	$html = ch_native_render( static fn() => $render->invoke( $actions ) );
	ch_native_check( str_contains( $html, 'جزئیات ویرایش‌شده' ) && str_contains( $html, 'خرید ویرایش‌شده' ) && str_contains( $html, 'data-compare-selected-label="حذف ویرایش‌شده"' ), 'Runtime action labels ignore Elementor settings.' );
	ch_native_check( str_contains( $html, 'href="' . esc_url( Chidemoon_Core_Affiliate::tracking_url( $products['first']->get_id() ) ) . '" target="_blank" rel="nofollow sponsored noopener"' ), 'Runtime affiliate action lost its approved redirect.' );
	ch_native_context( $products['unreviewed']->get_id(), $products['first'] );
	$html = ch_native_render( static fn() => $render->invoke( $actions ) );
	ch_native_check( ! str_contains( $html, '/go/' ) && ! str_contains( $html, 'data-compare-product=' ) && str_contains( $html, esc_url( get_permalink( $products['unreviewed']->get_id() ) ) ), 'An unreviewed runtime card exposes an active affiliate or compare action.' );

	// Native Woo tabs must preserve the current product's attributes/reviews and
	// third-party content beside the separately editable full-description body.
	$tab_query = new WP_Query( array( 'post_type' => 'product', 'p' => $products['first']->get_id() ) );
	$GLOBALS['wp_query'] = $tab_query;
	$GLOBALS['wp_the_query'] = $tab_query;
	ch_native_context( $products['first']->get_id(), $products['second'] );
	$extra_tabs = ch_native_widget( 'chidemoon-product-extra-tabs' );
	$native_tabs = ch_native_widget( 'woocommerce-product-data-tabs' );
	$extra_controls = $extra_tabs->get_controls();
	ch_native_check( ! array_diff_key( $native_tabs->get_controls(), $extra_controls ) && isset( $extra_controls['show_attributes'], $extra_controls['show_reviews'], $extra_controls['info_title'], $extra_controls['reviews_title'] ), 'Extra tabs lose native Style controls or graphical visibility/title controls.' );
	ch_native_check( $extra_tabs->get_style_depends() === $native_tabs->get_style_depends() && $extra_tabs->get_script_depends() === $native_tabs->get_script_depends(), 'Extra tabs lose the inherited native stylesheet or scripts.' );
	$extra_render = new ReflectionMethod( $extra_tabs, 'render' );
	$plugin_tab = static function ( array $tabs ) use ( $prefix ): array {
		$tabs['ch_native_manual'] = array( 'title' => $prefix . '-plugin-title', 'priority' => 40, 'callback' => static function () use ( $prefix ): void { echo esc_html( $prefix . '-plugin-body' ); } );
		return $tabs;
	};
	add_filter( 'woocommerce_product_tabs', $plugin_tab, 99 );
	try {
		$tab_callbacks = $GLOBALS['wp_filter']['woocommerce_product_tabs']->callbacks;
		$html = ch_native_render( static fn() => $extra_render->invoke( $extra_tabs ) );
		ch_native_check( str_contains( $html, 'id="tab-additional_information"' ) && str_contains( $html, $prefix . '-green-attribute' ) && str_contains( $html, wc_format_weight( $products['first']->get_weight() ) ), 'Extra tabs lose the current product attributes or dimensions/weight.' );
		$review_title = sprintf( __( 'Reviews (%d)', 'woocommerce' ), $products['first']->get_review_count() );
		ch_native_check( 1 === $products['first']->get_review_count() && str_contains( $html, esc_html( $review_title ) ) && str_contains( $html, 'id="tab-reviews"' ) && str_contains( $html, $prefix . '-approved-review-body' ), 'Extra tabs lose the native review title/count, review panel or actual approved review.' );
		ch_native_check( str_contains( $html, $prefix . '-plugin-title' ) && str_contains( $html, $prefix . '-plugin-body' ), 'Extra tabs lose a third-party tab or its callback output.' );
		ch_native_check( ! str_contains( $html, 'id="tab-description"' ) && ! str_contains( $html, $prefix . '-full-description' ) && ! str_contains( $html, $products['second']->get_name() ), 'Extra tabs duplicate the full body or resolve the stale global product.' );
		ch_native_check( $GLOBALS['wp_filter']['woocommerce_product_tabs']->callbacks === $tab_callbacks && isset( apply_filters( 'woocommerce_product_tabs', array() )['description'] ), 'Extra-tab filters leak into another native WooCommerce render.' );
		$custom_tabs = ch_native_widget( 'chidemoon-product-extra-tabs', array( 'info_title' => 'مشخصات ویرایش‌شده', 'reviews_title' => 'تجربهٔ ویرایش‌شده' ) );
		$custom_render = new ReflectionMethod( $custom_tabs, 'render' );
		$html = ch_native_render( static fn() => $custom_render->invoke( $custom_tabs ) );
		ch_native_check( str_contains( $html, 'مشخصات ویرایش‌شده' ) && str_contains( $html, 'تجربهٔ ویرایش‌شده' ) && str_contains( $html, $prefix . '-approved-review-body' ), 'Graphical tab-title editing changes or removes native review content.' );
		$hidden_tabs = ch_native_widget( 'chidemoon-product-extra-tabs', array( 'show_attributes' => '', 'show_reviews' => '' ) );
		$hidden_render = new ReflectionMethod( $hidden_tabs, 'render' );
		$html = ch_native_render( static fn() => $hidden_render->invoke( $hidden_tabs ) );
		ch_native_check( ! str_contains( $html, 'id="tab-additional_information"' ) && ! str_contains( $html, 'id="tab-reviews"' ) && str_contains( $html, $prefix . '-plugin-body' ), 'Extra-tab visibility switches hide unrelated plugin content or fail to hide native tabs.' );
	} finally { remove_filter( 'woocommerce_product_tabs', $plugin_tab, 99 ); }

	// Product archives must preserve Woo exclusions, taxonomy and current-query pagination.
	add_filter( 'pre_option_woocommerce_hide_out_of_stock_items', $hide_stock );
	$visible_ids = array_map( static fn( WC_Product $product ): int => $product->get_id(), array_intersect_key( $products, array_flip( array( 'first', 'second', 'hidden', 'outstock', 'outside' ) ) ) );
	$catalog_args = array(
		'post_type' => 'product', 'post_status' => 'publish', 'post__in' => array_values( $visible_ids ),
		'posts_per_page' => 1, 'orderby' => 'ID', 'order' => 'ASC',
		'tax_query' => WC()->query->get_tax_query( array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => array( $terms['same'] ) ) ) ),
		'meta_query' => WC()->query->get_meta_query(),
	);
	foreach ( array( 1 => 'first', 2 => 'second' ) as $page => $name ) {
		$main = new WP_Query( $catalog_args + array( 'paged' => $page ) );
		$GLOBALS['wp_query'] = $main;
		$GLOBALS['wp_the_query'] = $main;
		$grid = ch_native_widget( 'loop-grid', array( '_skin' => 'product', 'template_id' => (string) $product_template->ID, 'product_query_post_type' => 'current_query', 'posts_per_page' => 12, 'pagination_type' => 'numbers', 'pagination_load_type' => 'page_reload' ) );
		$grid->query_posts();
		$query = $grid->get_query();
		ch_native_check( array( $products[ $name ]->get_id() ) === array_map( 'intval', wp_list_pluck( $query->posts, 'ID' ) ), 'Current Query loses product taxonomy, visibility, stock or page: ' . $page );
		ch_native_check( 2 === (int) $query->found_posts && 2 === (int) $query->max_num_pages && 1 === (int) $query->get( 'posts_per_page' ), 'Current Query loses native pagination totals: ' . $page );
	}

	// Numeric price order must survive Woo removing its main-query SQL filters.
	foreach ( array( 'price' => array( 'first', 'second' ), 'price-desc' => array( 'second', 'first' ) ) as $ordering => $names ) {
		$_GET['orderby'] = $ordering;
		$main = new WP_Query( array_merge( $catalog_args, array( 'posts_per_page' => 12, 'wc_query' => 'product_query' ) ) );
		$GLOBALS['wp_query'] = $main;
		$GLOBALS['wp_the_query'] = $main;
		WC()->query->remove_ordering_args();
		$grid = ch_native_widget( 'loop-grid', array( '_skin' => 'product', 'template_id' => (string) $product_template->ID, 'product_query_post_type' => 'current_query' ) );
		$grid->query_posts();
		$expected = array_map( static fn( $name ) => $products[ $name ]->get_id(), $names );
		ch_native_check( $expected === array_map( 'intval', wp_list_pluck( $grid->get_query()->posts, 'ID' ) ), 'Current Query loses numeric price ordering: ' . $ordering );
	}

	// Archive controls must use Woo's real counts/options and keep filters intact.
	wc_setup_loop( array( 'is_paginated' => true, 'total' => 2, 'per_page' => 1, 'current_page' => 2, 'is_search' => false ) );
	$_GET = array( 'orderby' => 'date', 'filter_feature' => 'bright', 'e-page-nativegrid' => '2' );
	$catalog_settings = array( 'count_range' => 'آزمایش {first}/{last}/{total} 50% <script>', 'count_all' => 'کل {total} محصول', 'count_single' => 'یک نتیجهٔ ویرایش‌شده', 'order_label' => 'مرتب‌سازی & آزمایشی', 'order_latest' => 'تازه & ویژه', 'order_relevance' => 'ارتباط ویرایش‌شده' );
	$catalog = ch_native_widget( 'chidemoon-catalog-tools', $catalog_settings );
	$catalog_render = new ReflectionMethod( $catalog, 'render' );
	$main_before = $GLOBALS['wp_query'];
	$request_before = $_GET;
	$html = ch_native_render( static fn() => $catalog_render->invoke( $catalog ) );
	ch_native_check( str_contains( $html, 'آزمایش 2/2/2 50% &lt;script&gt;' ) && str_contains( $html, 'role="status"' ), 'Editable count labels lose the real current-page range, escaping, percent copy or accessibility.' );
	ch_native_check( str_contains( $html, 'مرتب‌سازی &amp; آزمایشی' ) && str_contains( $html, 'value="date"  selected=\'selected\'>تازه &amp; ویژه' ), 'Editable sorting labels or the selected Woo ordering are lost.' );
	ch_native_check( str_contains( $html, 'name="filter_feature" value="bright"' ) && str_contains( $html, 'name="paged" value="1"' ) && ! str_contains( $html, 'name="e-page-nativegrid"' ), 'Sorting fails to preserve catalog filters or restart Elementor pagination.' );
	ch_native_check( $GLOBALS['wp_query'] === $main_before && $_GET === $request_before, 'Catalog controls mutate the archive query or request.' );
	ch_native_check( __( 'Sort by', 'woocommerce' ) !== $catalog_settings['order_label'] && ( apply_filters( 'woocommerce_catalog_orderby', array( 'date' => 'default' ) )['date'] ?? '' ) !== $catalog_settings['order_latest'], 'Catalog label filters leak to another component.' );
	wc_set_loop_prop( 'per_page', 12 );
	wc_set_loop_prop( 'current_page', 1 );
	$html = ch_native_render( static fn() => $catalog_render->invoke( $catalog ) );
	ch_native_check( str_contains( $html, 'کل 2 محصول' ), 'The all-results count ignores editable copy or the Woo total.' );
	wc_set_loop_prop( 'total', 1 );
	$html = ch_native_render( static fn() => $catalog_render->invoke( $catalog ) );
	ch_native_check( str_contains( $html, 'یک نتیجهٔ ویرایش‌شده' ), 'The single-result count ignores editable copy.' );
	wc_set_loop_prop( 'is_search', true );
	$_GET['orderby'] = 'relevance';
	$html = ch_native_render( static fn() => $catalog_render->invoke( $catalog ) );
	ch_native_check( str_contains( $html, 'ارتباط ویرایش‌شده' ) && ! str_contains( $html, 'value="menu_order"' ), 'Search sorting loses its relevance label or native available options.' );
	$sorting_only = ch_native_widget( 'chidemoon-catalog-tools', array( 'show_count' => '', 'show_sorting' => 'yes' ) );
	$sorting_render = new ReflectionMethod( $sorting_only, 'render' );
	$html = ch_native_render( static fn() => $sorting_render->invoke( $sorting_only ) );
	ch_native_check( ! str_contains( $html, 'woocommerce-result-count' ) && str_contains( $html, 'woocommerce-ordering' ), 'The count visibility switch does not affect runtime output.' );
	$count_only = ch_native_widget( 'chidemoon-catalog-tools', array( 'show_count' => 'yes', 'show_sorting' => '' ) );
	$count_render = new ReflectionMethod( $count_only, 'render' );
	$html = ch_native_render( static fn() => $count_render->invoke( $count_only ) );
	ch_native_check( str_contains( $html, 'woocommerce-result-count' ) && ! str_contains( $html, 'woocommerce-ordering' ), 'The sorting visibility switch does not affect runtime output.' );
	$_GET = $original_get;

	$context = new WP_Query( array( 'post_type' => 'product', 'p' => $products['first']->get_id() ) );
	$GLOBALS['wp_query'] = $context;
	$GLOBALS['wp_the_query'] = $context;
	$related = new WP_Query();
	$related->parse_query( array( 'post_type' => 'product', 'post__in' => array_values( $visible_ids ), 'posts_per_page' => 20, 'post__not_in' => array( 99999999 ) ) );
	Chidemoon_Core_Public_Design::related_products_query( $related );
	ch_native_check( in_array( 99999999, $related->get( 'post__not_in' ), true ), 'Related products erase an existing query exclusion.' );
	$related->query( $related->query_vars );
	ch_native_check( array( $products['second']->get_id() ) === array_map( 'intval', wp_list_pluck( $related->posts, 'ID' ) ), 'Related products expose the current, hidden, unavailable or unrelated product: ' . implode( ', ', wp_list_pluck( $related->posts, 'post_title' ) ) );
	$only_current = new WP_Query();
	$only_current->parse_query( array( 'post_type' => 'product', 'post__in' => array( $products['first']->get_id() ) ) );
	Chidemoon_Core_Public_Design::related_products_query( $only_current );
	$only_current->query( $only_current->query_vars );
	ch_native_check( ! $only_current->posts && array( 0 ) === $only_current->get( 'post__in' ), 'Removing the only selected related product expands the query to all products.' );

	$url = Chidemoon_Core_Compare::comparison_url( array( $products['second']->get_id(), $products['unreviewed']->get_id(), $products['first']->get_id() ) );
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $selection );
	ch_native_check( '/product-comparison/' === wp_parse_url( $url, PHP_URL_PATH ) && $selection['products'] === $products['second']->get_id() . ',' . $products['first']->get_id(), 'New comparison URLs lose eligibility filtering or selection order.' );
	// Containers can reach WordPress through its service hostname even when the
	// public development URL points at the operator's localhost port.
	$http_origin = getenv( 'CHIDEMOON_ACCEPTANCE_HTTP_ORIGIN' );
	$legacy_url = $http_origin ? rtrim( $http_origin, '/' ) . '/comparisons/' : home_url( '/comparisons/' );
	$legacy = wp_remote_get( add_query_arg( 'products', $products['second']->get_id() . ',' . $products['first']->get_id(), $legacy_url ), array( 'redirection' => 0, 'timeout' => 10 ) );
	if ( is_wp_error( $legacy ) ) { ch_native_check( false, 'Local legacy shared-link request failed: ' . $legacy->get_error_message() ); }
	else {
		$destination = wp_remote_retrieve_header( $legacy, 'location' );
		parse_str( (string) wp_parse_url( $destination, PHP_URL_QUERY ), $legacy_selection );
		ch_native_check( in_array( wp_remote_retrieve_response_code( $legacy ), array( 301, 302, 307, 308 ), true ) && '/product-comparison/' === wp_parse_url( $destination, PHP_URL_PATH ) && ( $legacy_selection['products'] ?? '' ) === $selection['products'], 'Old shared comparison links do not reach the new product tool with their selection.' );
	}
} catch ( Throwable $exception ) {
	$failures[] = $exception->getMessage();
} finally {
	$_GET = $original_get;
	remove_filter( 'pre_option_woocommerce_hide_out_of_stock_items', $hide_stock );
	foreach ( $comments as $id ) { wp_delete_comment( $id, true ); }
	foreach ( array_reverse( $fixtures ) as $id ) { wp_delete_post( $id, true ); }
	foreach ( $terms as $id ) { wp_delete_term( $id, 'product_cat' ); }
	foreach ( $original_globals as $key => $original ) {
		if ( $original['exists'] ) { $GLOBALS[ $key ] = $original['value']; }
		else { unset( $GLOBALS[ $key ] ); }
	}
}

if ( $failures ) {
	foreach ( $failures as $failure ) { WP_CLI::warning( $failure ); }
	WP_CLI::error( count( $failures ) . ' native runtime checks failed; fixtures removed.' );
}
WP_CLI::success( $checks . ' native runtime checks passed; fixtures removed.' );
