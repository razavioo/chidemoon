<?php
/**
 * Native Elementor migration acceptance tests in a disposable WordPress site.
 * Run: wp --user=<admin> eval-file /path/to/tests/elementor-editability.php
 * Creates its own draft documents, Loop templates and empty category; removes them.
 */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }
if ( ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
	WP_CLI::error( 'These acceptance tests require a disposable local or development site.' );
}
if ( ! current_user_can( 'edit_theme_options' ) ) { WP_CLI::error( 'Run as an administrator using --user=<id>.' ); }

// Loading the real migration performs its read-only report before the isolated tests.
$args = array();
require_once __DIR__ . '/../tools/elementor-editability-upgrade.php';

final class Chidemoon_Editability_Acceptance {
	private array $documents = array();
	private array $terms = array();
	private array $menus = array();
	private int $checks = 0;
	private string $nonce;
	private const BACKUP = '_chidemoon_pre_editability_20260930';

	public function __construct() { $this->nonce = bin2hex( random_bytes( 5 ) ); }

	private function check( bool $condition, string $message ): void {
		++$this->checks;
		if ( ! $condition ) { throw new RuntimeException( $message ); }
	}

	private function invoke( object $upgrade, string $method, array $arguments = array() ): mixed {
		return ( new ReflectionMethod( $upgrade, $method ) )->invokeArgs( $upgrade, $arguments );
	}

	private function upgrade( bool $apply, array $templates ): object {
		$upgrade = new Chidemoon_Elementor_Editability_Upgrade( $apply );
		( new ReflectionProperty( $upgrade, 'templates' ) )->setValue( $upgrade, $templates );
		return $upgrade;
	}

	private function patch( object $upgrade, array &$elements, string $target ): array {
		$changes = array();
		$this->invoke( $upgrade, 'remember_ids', array( $elements ) );
		$this->invoke( $upgrade, 'patch', array( &$elements, $target, &$changes ) );
		if ( 'site-header' === $target ) { $this->invoke( $upgrade, 'move_header_menu', array( &$elements, &$changes ) ); }
		return $changes;
	}

	private function widget( string $type, array $settings, ?string $id = null ): array {
		return array( 'id' => $id ?? bin2hex( random_bytes( 4 ) ), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}

	private function box( array $children, array $settings = array(), ?string $id = null ): array {
		return array( 'id' => $id ?? bin2hex( random_bytes( 4 ) ), 'elType' => 'container', 'settings' => array_merge( array( 'content_width' => 'full', 'flex_direction' => 'column' ), $settings ), 'elements' => $children );
	}

	private function find( array $elements, string $id ): ?array {
		foreach ( $elements as $element ) {
			if ( $id === $element['id'] ) { return $element; }
			$found = $this->find( $element['elements'] ?? array(), $id );
			if ( $found ) { return $found; }
		}
		return null;
	}

	private function data( int $id ): array {
		return json_decode( (string) get_post_meta( $id, '_elementor_data', true ), true, 512, JSON_THROW_ON_ERROR );
	}

	private function fixture( string $kind, array $elements ): int {
		$id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_name' => 'qa-editability-' . $this->nonce . '-' . $kind, 'post_title' => 'آزمون مستقل ویرایش‌پذیری', 'post_content' => '<p>یادداشت سردبیر: C:\\editor\\draft و «نقل قول».</p>' ) ), true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
		$this->documents[] = (int) $id;
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
		update_post_meta( $id, '_elementor_page_settings', array( 'hide_title' => 'yes', 'background_color' => '#fffcfa' ) );
		update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
		return (int) $id;
	}

	private function template( object $upgrade, string $kind, string $type, array $elements, string $source = '' ): int {
		$id = $this->invoke( $upgrade, 'create_template', array( 'qa-' . $this->nonce . '-' . $kind, 'آزمون مستقل ' . $kind, $type, $elements, $source ) );
		$this->documents[] = $id;
		return $id;
	}

	public function run(): void {
		$builder = $this->upgrade( true, array() );
		$term = wp_insert_term( 'دستهٔ آزمون ویرایش‌پذیری ' . $this->nonce, 'product_cat', array( 'slug' => 'qa-editability-' . $this->nonce ) );
		if ( is_wp_error( $term ) ) { throw new RuntimeException( $term->get_error_message() ); }
		$term_id = (int) $term['term_id'];
		$this->terms[] = $term_id;
		$loop_id = $this->template( $builder, 'term-card', 'loop-item', $this->invoke( $builder, 'card', array( 'product_taxonomy' ) ), 'product_taxonomy' );
		$this->check( 'loop-item' === get_post_meta( $loop_id, '_elementor_template_type', true ) && 'product_taxonomy' === get_post_meta( $loop_id, '_elementor_source', true ), 'Category card must be a native Loop Item with its taxonomy source.' );
		$templates = array( 'product-category-card' => $loop_id );
		$builder = $this->upgrade( true, $templates );
		$grid = $this->invoke( $builder, 'grid', array( 'product_taxonomy', array( 'product_taxonomy_query_post_type' => 'product_cat', 'product_taxonomy_query_filter_by' => 'manual_selection', 'product_taxonomy_posts_ids' => array( $term_id ), 'product_taxonomy_hide_empty' => 'no', 'pagination_type' => '' ) ) );
		$panel_id = $this->template( $builder, 'category-panel', 'section', array( $this->box( array( $grid ) ) ) );
		$templates['category-cards'] = $panel_id;
		$panel_html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $panel_id, true );
		$this->check( str_contains( $panel_html, get_term( $term_id )->name ), 'Native category Loop must render its actual term name.' );
		$this->check( str_contains( $panel_html, esc_url( get_term_link( $term_id, 'product_cat' ) ) ), 'Native category Loop must render its actual term permalink.' );
		$this->check( str_contains( $panel_html, 'data-elementor-type="loop-item"' ) && str_contains( $panel_html, 'class="elementor elementor-' . $loop_id . ' e-loop-item' ), 'Category cards must be rendered by the native Loop document.' );

		$routes = array( 'دسته‌بندی' => '/categories/', 'محصولات' => '/shop/', 'مقایسه کنید' => '/comparisons/', 'ایده‌های چیدمان' => '/shop-the-look/', 'راهنمای خرید' => '/guides/' );
		$items = array();
		$children = array();
		$category_child_id = bin2hex( random_bytes( 4 ) );
		foreach ( $routes as $label => $path ) {
			$items[] = array( '_id' => bin2hex( random_bytes( 4 ) ), 'item_title' => $label, 'item_link' => array( 'url' => '', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' ), 'item_dropdown_content' => 'no' );
			$children[] = $this->box( array(), array( '_title' => 'عنوان شخصی پنل', 'background_background' => 'classic', 'background_color' => '#fff7ed', 'padding' => array( 'unit' => 'px', 'top' => '17', 'right' => '18', 'bottom' => '19', 'left' => '20', 'isLinked' => false ) ), 0 === count( $children ) ? $category_child_id : null );
		}
		$unknown_url = home_url( '/editor-choice/?layout=keep#custom' );
		$custom_product_url = 'https://example.test/curated?color=green#collection';
		$items[] = array( '_id' => 'custom01', 'item_title' => 'انتخاب سردبیر', 'item_link' => array( 'url' => $unknown_url, 'is_external' => 'on', 'nofollow' => 'on', 'custom_attributes' => 'aria-label|انتخاب سردبیر' ), 'item_dropdown_content' => 'yes' );
		$custom_note = $this->widget( 'text-editor', array( 'editor' => '<p>متن شخصی منوی سردبیر</p>' ) );
		$children[] = $this->box( array( $custom_note ), array( '_title' => 'پنل سردبیر', 'background_background' => 'classic', 'background_color' => '#edf7ed' ) );
		$items[] = array( '_id' => 'custom02', 'item_title' => 'محصولات', 'item_link' => array( 'url' => $custom_product_url, 'is_external' => 'on', 'nofollow' => 'on', 'custom_attributes' => '' ), 'item_dropdown_content' => 'no' );
		// Missing final placeholder reproduces a partially edited native nested menu.
		$menu = $this->widget( 'mega-menu', array( 'menu_items' => $items, '_css_classes' => 'editor-custom-nav', '_element_id' => 'editor-nav', '_position' => 'absolute', '_offset_orientation_h' => 'end', '_offset_x' => array( 'unit' => 'px', 'size' => 12 ), '_margin' => array( 'unit' => 'px', 'top' => '13', 'right' => '14', 'bottom' => '15', 'left' => '16', 'isLinked' => false ) ), 'a8423ff' );
		$menu['elements'] = $children;
		$logo = $this->widget( 'heading', array( 'title' => 'نام ویرایش‌شدهٔ سایت', 'header_size' => 'h2' ) );
		$header_note = $this->widget( 'text-editor', array( 'editor' => '<p>توضیح شخصی هدر</p>' ) );
		$detached_note = $this->widget( 'text-editor', array( 'editor' => '<p>یادداشت جداگانهٔ سردبیر</p>' ) );
		$header = $this->box( array( $logo, $header_note ), array( 'css_classes' => 'ch-header editor-header', 'background_background' => 'classic', 'background_color' => '#fffcfa' ) );
		$detached = $this->box( array( $menu, $detached_note ), array( 'css_classes' => 'editor-detached-container' ) );
		$original = array( $header, $detached );
		$fixture_id = $this->fixture( 'header', $original );
		$original_raw = get_post_meta( $fixture_id, '_elementor_data', true );
		$original_content = get_post_field( 'post_content', $fixture_id );
		$original_meta = get_post_meta( $fixture_id );

		$dry = $this->upgrade( false, $templates );
		$planned = $original;
		$changes = $this->patch( $dry, $planned, 'site-header' );
		$this->check( count( $changes ) > 0, 'Dry run must plan the missing links and detached header menu.' );
		$this->invoke( $dry, 'save', array( $fixture_id, $planned, $changes ) );
		$this->check( $original_meta === get_post_meta( $fixture_id ) && $original_content === get_post_field( 'post_content', $fixture_id ), 'Dry run must not write fixture metadata, content or backups.' );

		$apply = $this->upgrade( true, $templates );
		$patched = $original;
		$changes = $this->patch( $apply, $patched, 'site-header' );
		$this->invoke( $apply, 'save', array( $fixture_id, $patched, $changes ) );
		$stored = $this->data( $fixture_id );
		$stored_menu = $this->find( $stored, 'a8423ff' );
		$this->check( null !== $stored_menu, 'The existing native menu ID must be retained.' );
		foreach ( array_keys( $routes ) as $index => $label ) {
			$this->check( $stored_menu['settings']['menu_items'][ $index ]['item_title'] === $label && $stored_menu['settings']['menu_items'][ $index ]['item_link']['url'] === home_url( $routes[ $label ] ), 'Missing native menu link was not filled for ' . $label );
		}
		$this->check( $stored_menu['settings']['menu_items'][5]['item_link']['url'] === $unknown_url && $stored_menu['settings']['menu_items'][6]['item_link']['url'] === $custom_product_url, 'Unknown custom links and edited links on known labels must be preserved.' );
		$this->check( $stored_menu['settings']['menu_items'][5]['item_link']['is_external'] === 'on' && $stored_menu['settings']['menu_items'][5]['item_link']['nofollow'] === 'on', 'Editor link target/relationship settings must be preserved.' );
		$this->check( $stored_menu['settings']['_element_id'] === 'editor-nav' && $stored_menu['settings']['_margin']['top'] === '13' && str_contains( $stored_menu['settings']['_css_classes'], 'editor-custom-nav' ), 'Editor menu ID, margins and CSS classes must survive the repair.' );
		$this->check( 'absolute' !== ( $stored_menu['settings']['_position'] ?? '' ), 'Detached menu must return to normal header flow.' );
		$this->check( count( $stored_menu['elements'] ) === count( $items ), 'Native menu child containers must retain the repeater index correspondence.' );
		$category = $stored_menu['elements'][0];
		$this->check( $category['id'] === $category_child_id && $category['settings']['background_color'] === '#fff7ed' && $category['settings']['padding']['top'] === '17' && $category['settings']['_title'] === 'عنوان شخصی پنل', 'Adding category cards must preserve the existing empty dropdown container ID and editor styles/title.' );
		$this->check( 'yes' === $stored_menu['settings']['menu_items'][0]['item_dropdown_content'] && 'template' === $category['elements'][0]['widgetType'] && (int) $category['elements'][0]['settings']['template_id'] === $panel_id, 'Category dropdown must contain the editable native category panel.' );
		$stored_header = $this->find( $stored, $header['id'] );
		$this->check( 'a8423ff' === $stored_header['elements'][1]['id'] && $stored_header['settings']['background_color'] === '#fffcfa', 'The existing menu must move into the existing header without resetting header styles.' );
		$this->check( $this->find( $stored, $logo['id'] )['settings']['title'] === 'نام ویرایش‌شدهٔ سایت' && $this->find( $stored, $header_note['id'] )['settings']['editor'] === '<p>توضیح شخصی هدر</p>' && $this->find( $stored, $custom_note['id'] )['settings']['editor'] === '<p>متن شخصی منوی سردبیر</p>' && $this->find( $stored, $detached_note['id'] )['settings']['editor'] === '<p>یادداشت جداگانهٔ سردبیر</p>', 'Header, custom dropdown and detached-container editorial text must be retained.' );
		$header_html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $fixture_id, true );
		$dom = new DOMDocument();
		$dom->loadHTML( '<meta charset="utf-8">' . $header_html, LIBXML_NOERROR | LIBXML_NOWARNING );
		$rendered_links = array();
		foreach ( ( new DOMXPath( $dom ) )->query( '//*[@id="editor-nav"]//a[@href]' ) as $link ) { $rendered_links[] = $link->getAttribute( 'href' ); }
		$this->check( ! array_diff( array_map( 'home_url', array_values( $routes ) ), $rendered_links ), 'Each repaired native mega-menu route must render as a working HTML link.' );
		$this->check( in_array( $unknown_url, $rendered_links, true ) && in_array( $custom_product_url, $rendered_links, true ), 'Native menu rendering must retain custom link destinations.' );
		$backup = get_post_meta( $fixture_id, self::BACKUP, true );
		$this->check( $backup['content'] === $original_content && $backup['meta']['_elementor_data']['value'] === $original_raw && $backup['meta']['_elementor_page_settings']['value']['background_color'] === '#fffcfa', 'The first backup must retain exact original content, slashes and Elementor settings.' );
		$applied_raw = get_post_meta( $fixture_id, '_elementor_data', true );
		$again = $stored;
		$this->check( array() === $this->patch( $this->upgrade( true, $templates ), $again, 'site-header' ) && $again === $stored && $applied_raw === get_post_meta( $fixture_id, '_elementor_data', true ), 'Repeated migration must be a no-op after native header repair.' );

		// Editors may change the converted design; an additional repair cannot reset it.
		$stored_header['elements'][0]['settings']['title'] = 'نام تازهٔ سردبیر';
		foreach ( $stored as &$element ) { if ( $element['id'] === $stored_header['id'] ) { $element = $stored_header; } }
		unset( $element );
		\Elementor\Plugin::$instance->documents->get( $fixture_id, false )->save( array( 'elements' => $stored ) );
		$edited = $this->data( $fixture_id );
		$repaired = $edited;
		$this->check( array() === $this->patch( $this->upgrade( true, $templates ), $repaired, 'site-header' ) && $this->find( $repaired, $logo['id'] )['settings']['title'] === 'نام تازهٔ سردبیر', 'Rerunning after editor changes must preserve the new document content.' );
		$this->invoke( $apply, 'save', array( $fixture_id, $repaired, array( 'test second safe save' ) ) );
		$this->check( get_post_meta( $fixture_id, self::BACKUP, true ) === $backup && count( get_post_meta( $fixture_id, self::BACKUP, false ) ) === 1, 'Later saves must keep the unique original recovery snapshot.' );

		$loop_data = $this->data( $loop_id );
		$loop_data[0]['settings']['background_color'] = '#eef7ee';
		\Elementor\Plugin::$instance->documents->get( $loop_id, false )->save( array( 'elements' => $loop_data ) );
		$edited_loop = get_post_meta( $loop_id, '_elementor_data', true );
		$existing_loop_id = $this->invoke( $apply, 'create_template', array( 'qa-' . $this->nonce . '-term-card', 'عنوان تازهٔ پیشنهادی', 'loop-item', $this->invoke( $apply, 'card', array( 'product_taxonomy' ) ), 'product_taxonomy' ) );
		$this->check( $existing_loop_id === $loop_id && get_post_meta( $loop_id, '_elementor_data', true ) === $edited_loop && $this->data( $loop_id )[0]['settings']['background_color'] === '#eef7ee', 'Existing Loop template appearance must remain untouched on repeat creation.' );

		$feed = $this->widget( 'posts', array( '_css_classes' => 'ch-editorial-feed editor-custom-feed', 'posts_post_type' => 'post', 'posts_query_id' => 'editor_query', 'posts_include' => array( 'terms' ), 'posts_include_term_ids' => array( 17 ), 'classic_posts_per_page' => 7, 'classic_columns' => '4', 'classic_columns_tablet' => '2', 'classic_columns_mobile' => '1', '_element_id' => 'editor-feed', '_margin' => array( 'unit' => 'px', 'top' => '21', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => false ), 'pagination_type' => 'numbers_and_prev_next', 'pagination_next_label' => 'بعدیِ سردبیر' ) );
		$feed_document = array( $this->box( array( $feed ) ) );
		$feed_changes = $this->patch( $this->upgrade( true, array( 'article-card' => $loop_id ) ), $feed_document, 'guides' );
		$converted = $this->find( $feed_document, $feed['id'] );
		$this->check( $feed_changes && 'loop-grid' === $converted['widgetType'] && 'editor_query' === $converted['settings']['post_query_query_id'] && array( 17 ) === $converted['settings']['post_query_include_term_ids'] && 7 === $converted['settings']['posts_per_page'], 'Native article cards must preserve the original editorial query and item count.' );
		$this->check( '4' === $converted['settings']['columns'] && 'editor-feed' === $converted['settings']['_element_id'] && '21' === $converted['settings']['_margin']['top'] && 'بعدیِ سردبیر' === $converted['settings']['pagination_next_label'] && str_contains( $converted['settings']['_css_classes'], 'editor-custom-feed' ), 'Native feed conversion must preserve editor layout, advanced styles and pagination labels.' );
		$this->check( array() === $this->patch( $this->upgrade( true, array( 'article-card' => $loop_id ) ), $feed_document, 'guides' ), 'Converted native feeds must not be converted again.' );

		// Only managed widgets belong to the migration; editor-authored widgets
		// beside them must preserve their native settings and source query.
		$tabs_settings = array( '_css_classes' => 'ch-product-tabs-widget editor-tabs', '_element_id' => 'editor-product-tabs', '_margin' => array( 'unit' => 'px', 'top' => '23', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => false ), 'tab_text_color' => '#173b2b', 'active_tab_text_color' => '#126444', 'tab_typography_font_family' => 'Yekan Bakh', 'content_typography_font_size' => array( 'unit' => 'px', 'size' => 17 ), 'show_attributes' => 'yes', 'show_reviews' => '', 'info_title' => 'مشخصات ویرایش‌شده', 'reviews_title' => 'نظر سردبیر' );
		$tabs = $this->widget( 'woocommerce-product-data-tabs', $tabs_settings );
		$custom_tabs = $this->widget( 'woocommerce-product-data-tabs', array( '_css_classes' => 'editor-independent-tabs', 'tab_text_color' => '#7a3240' ) );
		$product_body = array( $this->box( array( $tabs, $custom_tabs ) ) );
		$tabs_changes = $this->patch( $this->upgrade( true, array() ), $product_body, 'product-single' );
		$converted_tabs = $this->find( $product_body, $tabs['id'] );
		$this->check( $tabs_changes && 'chidemoon-product-extra-tabs' === $converted_tabs['widgetType'] && $converted_tabs['settings'] === $tabs_settings, 'Managed product tabs must preserve their original element ID, native styles, editor labels and visibility settings.' );
		$this->check( $custom_tabs === $this->find( $product_body, $custom_tabs['id'] ), 'An unrelated editor-authored native product tabs widget was converted.' );
		$body_types = wp_list_pluck( $product_body[0]['elements'][0]['elements'], 'widgetType' );
		$this->check( array( 'heading', 'theme-post-content', 'chidemoon-product-extra-tabs' ) === $body_types, 'Product conversion must keep the native editable full body alongside attributes/reviews.' );
		$tabs_id = $this->fixture( 'tabs', $product_body );
		$this->invoke( $apply, 'save', array( $tabs_id, $product_body, $tabs_changes ) );
		$saved_tabs = $this->find( $this->data( $tabs_id ), $tabs['id'] );
		$saved_custom = $this->find( $this->data( $tabs_id ), $custom_tabs['id'] );
		$this->check( $saved_tabs['settings'] === $tabs_settings && $saved_custom['id'] === $custom_tabs['id'] && $saved_custom['widgetType'] === $custom_tabs['widgetType'] && $saved_custom['settings'] === $custom_tabs['settings'] && $saved_custom['elements'] === $custom_tabs['elements'], 'A native Elementor save changed product tab styles, controls or the independent widget.' );
		$this->check( array() === $this->patch( $this->upgrade( true, array() ), $product_body, 'product-single' ), 'Product tabs conversion must be idempotent.' );

		$unrelated_menu = $this->widget( 'mega-menu', array( '_css_classes' => 'editor-secondary-nav', '_element_id' => 'editor-secondary-nav', '_position' => 'absolute', 'menu_items' => array( array( '_id' => 'unrelated', 'item_title' => 'دسته‌بندی', 'item_link' => array( 'url' => '' ), 'item_dropdown_content' => 'no' ) ) ) );
		$unrelated_menu['elements'] = array( $this->box( array(), array( '_title' => 'پنل جداگانهٔ سردبیر', 'background_color' => '#fff4ef' ) ) );
		$unrelated_header = array( $this->box( array( $unrelated_menu ), array( 'css_classes' => 'ch-header' ) ) );
		$unrelated_header_before = $unrelated_header;
		$this->check( array() === $this->patch( $this->upgrade( true, $templates ), $unrelated_header, 'site-header' ) && $unrelated_header === $unrelated_header_before, 'An unsupported editor-authored native menu was changed or moved.' );
		foreach ( array( 'product-single', 'product-archive' ) as $target ) {
			$author_feed = array( $this->box( array( $this->widget( 'posts', array( '_css_classes' => 'editor-author-feed', 'posts_post_type' => 'post', 'posts_query_id' => 'editor_author_query', 'posts_include' => array( 'authors' ), 'posts_include_authors' => array( 1 ), 'classic_posts_per_page' => 3 ) ) ) ) );
			$before = $author_feed;
			$this->check( array() === $this->patch( $this->upgrade( true, $templates ), $author_feed, $target ) && $author_feed === $before, 'An unrelated author posts feed was changed in ' . $target );
		}

		// Parent/child WordPress navigation needs its native renderer as fallback.
		$menu_id = wp_create_nav_menu( 'qa-hierarchical-' . $this->nonce );
		if ( is_wp_error( $menu_id ) ) { throw new RuntimeException( $menu_id->get_error_message() ); }
		$this->menus[] = (int) $menu_id;
		$parent_id = wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'گروه والد', 'menu-item-url' => home_url( '/parent/' ), 'menu-item-type' => 'custom', 'menu-item-status' => 'publish' ) );
		if ( is_wp_error( $parent_id ) ) { throw new RuntimeException( $parent_id->get_error_message() ); }
		$child_id = wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'پیوند فرزند', 'menu-item-url' => home_url( '/child/' ), 'menu-item-parent-id' => $parent_id, 'menu-item-type' => 'custom', 'menu-item-status' => 'publish' ) );
		if ( is_wp_error( $child_id ) ) { throw new RuntimeException( $child_id->get_error_message() ); }
		$old_nav = $this->widget( 'nav-menu', array( 'menu' => (string) $menu_id, '_css_classes' => 'ch-nav editor-legacy-nav', 'layout' => 'horizontal', 'dropdown' => 'tablet' ) );
		$legacy_header = array( $this->box( array( $old_nav ), array( 'css_classes' => 'ch-header' ) ) );
		$before = $legacy_header;
		$this->check( array() === $this->patch( $this->upgrade( true, $templates ), $legacy_header, 'site-header' ) && $legacy_header === $before, 'A hierarchical WordPress menu was flattened instead of preserving its native fallback.' );
		$stored_items = wp_get_nav_menu_items( $menu_id );
		$this->check( count( $stored_items ) === 2 && (int) $stored_items[1]->menu_item_parent === (int) $parent_id, 'Menu migration changed the existing WordPress parent/child source.' );
		WP_CLI::success( 'Elementor editability acceptance: ' . $this->checks . ' checks passed.' );
	}

	public function cleanup(): void {
		foreach ( array_reverse( $this->documents ) as $id ) { wp_delete_post( $id, true ); }
		foreach ( $this->terms as $id ) { wp_delete_term( $id, 'product_cat' ); }
		foreach ( $this->menus as $id ) { wp_delete_nav_menu( $id ); }
	}
}

$acceptance = new Chidemoon_Editability_Acceptance();
$failure = null;
try { $acceptance->run(); } catch ( Throwable $error ) { $failure = $error; } finally { $acceptance->cleanup(); }
if ( $failure ) { WP_CLI::error( $failure->getMessage() ); }
