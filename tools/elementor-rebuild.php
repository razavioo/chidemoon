<?php
/**
 * Chidemoon's one-time, reviewable Elementor migration.
 * Run with: wp eval-file /tools/elementor-rebuild.php apply
 * An existing migration is left alone unless force is passed.
 * Run with: wp eval-file /tools/elementor-rebuild.php ui-upgrade apply
 * The UI upgrade patches only known elements in managed documents.
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

if ( ! class_exists( '\\Elementor\\Plugin' ) || ! class_exists( '\\ElementorPro\\Plugin' ) ) {
	WP_CLI::error( 'Elementor and Elementor Pro must be active.' );
}

$apply = in_array( 'apply', $args, true );
$force = in_array( 'force', $args, true );
$reset_demo = in_array( 'reset-demo', $args, true );
$ui_upgrade = in_array( 'ui-upgrade', $args, true );
if ( $force && ! $apply ) {
	WP_CLI::error( 'force requires apply.' );
}
if ( $ui_upgrade && ( $force || $reset_demo ) ) {
	WP_CLI::error( 'ui-upgrade cannot be combined with force or reset-demo.' );
}
if ( $apply && ( ! current_user_can( 'edit_theme_options' ) || ! current_user_can( 'edit_pages' ) ) ) {
	WP_CLI::error( 'Run as an administrator with --user=<id>.' );
}
if ( $apply && ! $ui_upgrade ) {
	update_option( 'timezone_string', 'Asia/Tehran' );
	update_option( 'date_format', 'j F Y' );
	foreach ( array( 'guides' => 'راهنمای خرید', 'comparisons' => 'مقایسه‌ها', 'room-ideas' => 'ایده‌های چیدمان' ) as $slug => $name ) {
		if ( ! get_category_by_slug( $slug ) ) {
			wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
		}
	}
}

$required_pages = array( 'home' => 'چیدمون', 'guides' => 'راهنمای خرید', 'comparisons' => 'مقایسه‌ها', 'shop-the-look' => 'ایده‌های چیدمان', 'magazine' => 'مجله', 'shop' => 'محصولات' );
foreach ( $required_pages as $slug => $title ) {
	$page = get_page_by_path( $slug );
	if ( $apply && ! $ui_upgrade && ! $page ) {
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title ), true );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}
	} elseif ( $apply && ! $ui_upgrade && $page && $page->post_title !== $title ) {
		wp_update_post( array( 'ID' => $page->ID, 'post_title' => $title ) );
	}
}

$assets = array(
	'hero'    => 'look-real-43.jpg',
	'work'    => 'look-compact-home-office.jpg',
	'reading' => 'look-reading-corner.jpg',
	'dining'  => 'look-japandi-dining.jpg',
	'bedroom' => 'look-calm-green-bedroom.jpg',
	'shoppable' => 'look-basalam-lamps.jpg',
);
foreach ( $assets as $key => $file ) {
	$matches = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'meta_key' => '_wp_attached_file', 'meta_value' => $file, 'meta_compare' => 'LIKE', 'fields' => 'ids' ) );
	if ( ! $matches && $apply && ! $ui_upgrade ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$path = __DIR__ . '/seed-images/looks/' . $file;
		if ( ! is_file( $path ) ) {
			WP_CLI::error( 'Missing editorial image: ' . $path );
		}
		$temp = wp_tempnam( $file );
		copy( $path, $temp );
		$id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $temp ), 0, 'تصویر مفهومی چیدمان خانه' );
		if ( is_wp_error( $id ) ) {
			@unlink( $temp );
			WP_CLI::error( $id->get_error_message() );
		}
		$matches = array( $id );
	}
	$assets[ $key ] = $matches ? (int) $matches[0] : 0;
	if ( $apply && ! $ui_upgrade && $assets[ $key ] && false === get_option( 'chidemoon_concept_media', false ) ) {
		update_post_meta( $assets[ $key ], '_wp_attachment_image_alt', 'چیدمان مفهومی خانه' );
	}
}
if ( $apply && ! $ui_upgrade && false === get_option( 'chidemoon_concept_media', false ) ) {
	update_option( 'chidemoon_concept_media', array_values( $assets ), false );
}

$template_names = array( 'site-header', 'site-footer', 'post-single', 'post-archive', 'search-results', 'not-found', 'product-single', 'product-archive' );
$summary = array();
foreach ( $required_pages as $slug => $title ) {
	$page = get_page_by_path( $slug );
	$summary[] = 'page ' . $slug . ( $page ? ' #' . $page->ID : ' (new)' );
}
foreach ( $template_names as $name ) {
	$summary[] = 'template ' . $name;
}
if ( ! $apply ) {
	if ( ! $ui_upgrade ) {
		WP_CLI::log( "Dry run. Pass apply to write:\n- " . implode( "\n- ", $summary ) );
		return;
	}
}

if ( $apply && ( ! current_user_can( 'edit_theme_options' ) || ! current_user_can( 'edit_pages' ) ) ) {
	WP_CLI::error( 'Run as an administrator with --user=<id>.' );
}

// Retire only the first inventory, so a later rerun cannot hide the editor's new work.
if ( ! $ui_upgrade && $reset_demo && false === get_option( 'chidemoon_demo_retirement', false ) ) {
	$previous = array();
	foreach ( get_posts( array( 'post_type' => array( 'post', 'product' ), 'post_status' => 'publish', 'posts_per_page' => -1 ) ) as $post ) {
		if ( get_post_meta( $post->ID, '_chidemoon_rebuild_editorial', true ) ) {
			continue;
		}
		$previous[ $post->ID ] = $post->post_status;
		if ( ! wp_trash_post( $post->ID ) ) {
			WP_CLI::error( 'Could not retire demo item #' . $post->ID );
		}
	}
	$test_page = get_post( 325 );
	if ( $test_page && 'page' === $test_page->post_type && ! in_array( $test_page->post_name, array_keys( $required_pages ), true ) ) {
		$previous[ $test_page->ID ] = $test_page->post_status;
		wp_trash_post( $test_page->ID );
	}
	update_option( 'chidemoon_demo_retirement', $previous, false );
	WP_CLI::success( 'Retired ' . count( $previous ) . ' demo items to trash.' );
}

class Chidemoon_Elementor_Rebuild {
	private int $next_id = 1;
	private array $assets;
	private bool $force;
	private int $menu_id;
	private \Elementor\Plugin $elementor;

	public function __construct( array $assets, bool $force, bool $ui_upgrade = false ) {
		$this->assets    = $assets;
		$this->force     = $force;
		$this->elementor = \Elementor\Plugin::$instance;
		if ( $ui_upgrade ) {
			$this->menu_id = 0;
			$this->next_id = 100000;
			return;
		}
		$menu = wp_get_nav_menu_object( 'chidemoon-primary' );
		$this->menu_id = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu( 'chidemoon-primary' );
		if ( get_option( 'chidemoon_visual_setup' ) && ! $force ) {
			return;
		}
		foreach ( array( 'shop-the-look', 'guides', 'comparisons', 'shop', 'magazine' ) as $slug ) {
			$page = get_page_by_path( $slug );
			$items = wp_get_nav_menu_items( $this->menu_id ) ?: array();
			if ( ! in_array( $page->ID, array_map( static fn( $item ) => (int) $item->object_id, $items ), true ) ) {
				wp_update_nav_menu_item( $this->menu_id, 0, array( 'menu-item-object-id' => $page->ID, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-title' => $page->post_title, 'menu-item-status' => 'publish' ) );
			}
		}
	}

	private function id(): string {
		return substr( md5( 'chidemoon-elementor-' . $this->next_id++ ), 0, 8 );
	}

	private function widget( string $type, array $settings = array() ): array {
		$widget = $this->elementor->widgets_manager->get_widget_types( $type );
		if ( ! $widget ) {
			WP_CLI::error( 'Required Elementor widget is unavailable: ' . $type );
		}
		foreach ( $widget->get_controls() as $key => $control ) {
			if ( ! isset( $settings[ $key ] ) && ! isset( $settings['__dynamic__'][ $key ] ) && ! empty( $control['dynamic']['default'] ) ) {
				$settings['__dynamic__'][ $key ] = $control['dynamic']['default'];
			}
		}
		return array( 'id' => $this->id(), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}

	private function box( array $children, string $class = '', array $settings = array() ): array {
		$defaults = array( 'content_width' => 'full', 'flex_direction' => 'column', 'flex_gap' => array( 'column' => '16', 'row' => '16', 'isLinked' => true, 'unit' => 'px', 'size' => 16 ), 'padding' => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ) );
		if ( $class ) {
			$defaults['css_classes'] = $class;
		}
		return array( 'id' => $this->id(), 'elType' => 'container', 'settings' => array_merge( $defaults, $settings ), 'elements' => $children, 'isInner' => false );
	}

	private function section( array $children, string $class = '', array $layout = array() ): array {
		return $this->box( $children, 'ch-section ' . $class, array_merge( array(
			'content_width' => 'boxed',
			'boxed_width'  => array( 'unit' => 'px', 'size' => 1280, 'sizes' => array() ),
			'padding'      => array( 'unit' => 'px', 'top' => '40', 'right' => '24', 'bottom' => '40', 'left' => '24', 'isLinked' => false ),
			'padding_mobile' => array( 'unit' => 'px', 'top' => '24', 'right' => '16', 'bottom' => '24', 'left' => '16', 'isLinked' => false ),
		), $layout ) );
	}

	private function heading( string $text, string $tag = 'h2', string $class = '' ): array {
		return $this->widget( 'heading', array( 'title' => $text, 'header_size' => $tag, '_css_classes' => $class ) );
	}

	private function text( string $html, string $class = '' ): array {
		return $this->widget( 'text-editor', array( 'editor' => $html, '_css_classes' => $class ) );
	}

	private function button( string $label, string $url, string $class = '' ): array {
		return $this->widget( 'button', array( 'text' => $label, 'link' => array( 'url' => home_url( $url ) ), 'align' => 'right', '_css_classes' => $class ) );
	}

	private function search( string $class = '' ): array {
		$settings = array( 'search_input_placeholder_text' => 'جست‌وجو در چیدمون', 'submit_trigger' => 'both', 'submit_button_text' => 'جست‌وجو', 'icon_submit' => array( 'value' => 'fas fa-search', 'library' => 'fa-solid' ), 'live_results' => '', '_css_classes' => $class );
		if ( 'ch-header-search' === $class ) {
			$settings['_element_width'] = 'initial';
			$settings['_element_custom_width'] = array( 'unit' => 'px', 'size' => 300 );
			$settings['_element_custom_width_tablet'] = array( 'unit' => '%', 'size' => 100 );
		}
		return $this->widget( 'search', $settings );
	}

	private function links( array $links ): array {
		$items = array();
		foreach ( $links as $path => $label ) {
			$items[] = array( '_id' => $this->id(), 'text' => $label, 'link' => array( 'url' => home_url( $path ) ), 'selected_icon' => array( 'value' => 'fas fa-angle-left', 'library' => 'fa-solid' ) );
		}
		return $this->widget( 'icon-list', array( 'icon_list' => $items, '_css_classes' => 'ch-footer-nav' ) );
	}

	private function product_grid_settings(): array {
		return array(
			'_css_classes' => 'ch-product-grid', 'button_text_color' => '#ffffff', 'columns_tablet' => '2', 'columns_mobile' => '1',
			'box_padding' => array( 'unit' => 'px', 'top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'isLinked' => true ),
			'box_border_radius' => array( 'unit' => 'px', 'size' => 8 ),
			'button_border_radius' => array( 'unit' => 'px', 'top' => '6', 'right' => '6', 'bottom' => '6', 'left' => '6', 'isLinked' => true ),
			'title_typography_typography' => 'custom', 'title_typography_font_size' => array( 'unit' => 'px', 'size' => 17 ), 'title_typography_line_height' => array( 'unit' => 'em', 'size' => 1.7 ),
			'__globals__' => array( 'title_color' => 'globals/colors?id=primary', 'price_color' => 'globals/colors?id=secondary', 'button_background_color' => 'globals/colors?id=primary' ),
		);
	}

	private function require_product_archive_controls(): void {
		$widget = $this->elementor->widgets_manager->get_widget_types( 'woocommerce-archive-products' );
		$controls = $widget ? $widget->get_controls() : array();
		foreach ( array( 'allow_order', 'show_result_count' ) as $key ) {
			if ( ! array_key_exists( $key, $controls ) ) {
				WP_CLI::error( 'Installed Elementor Archive Products widget lacks control: ' . $key );
			}
		}
	}

	private function product_archive_grid(): array {
		$this->require_product_archive_controls();
		return $this->widget( 'woocommerce-archive-products', array_merge( $this->product_grid_settings(), array(
			'columns' => 4, 'columns_tablet' => '2', 'columns_mobile' => '1',
			'allow_order' => 'yes', 'show_result_count' => 'yes',
		) ) );
	}

	private function image( string $key, string $class = '' ): array {
		$id = $this->assets[ $key ];
		return $this->widget( 'image', array( 'image' => array( 'id' => $id, 'url' => wp_get_attachment_url( $id ) ), 'image_size' => 'full', '_css_classes' => $class ) );
	}

	private function posts( string $category = '', int $count = 6, string $query_id = '' ): array {
		$settings = array(
			'_skin' => 'classic', 'classic_columns' => '3', 'classic_columns_tablet' => '2', 'classic_columns_mobile' => '1',
			'classic_posts_per_page' => $count, 'classic_thumbnail_size_size' => 'large', 'classic_meta_data' => array( 'date' ),
			'classic_excerpt_length' => 16, 'classic_read_more_text' => 'مشاهدهٔ مطلب', 'classic_masonry' => 'no',
			'posts_post_type' => 'post', 'posts_orderby' => 'date', 'posts_order' => 'DESC', '_css_classes' => 'ch-editorial-feed',
			'posts_query_id' => $query_id,
			'pagination_type' => $count > 6 ? 'numbers' : '',
		);
		if ( $category ) {
			$term = get_category_by_slug( $category );
			if ( $term ) {
				$settings['posts_include'] = array( 'terms' );
				$settings['posts_include_term_ids'] = array( $term->term_id );
			}
		}
		return $this->widget( 'posts', $settings );
	}

	private function route( string $image, string $title, string $description, string $path, string $action ): array {
		return $this->box( array(
			$this->image( $image, 'ch-route-media' ),
			$this->heading( $title, 'h3' ),
			$this->text( '<p>' . esc_html( $description ) . '</p>' ),
			$this->button( $action, $path, 'ch-link-button' ),
		), 'ch-route', array( 'width' => array( 'unit' => '%', 'size' => 31, 'sizes' => array() ), 'width_mobile' => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ) ) );
	}

	private function intro( string $eyebrow, string $title, string $description, string $image ): array {
		return $this->section( array(
			$this->box( array(
				$this->text( '<p>' . esc_html( $eyebrow ) . '</p>', 'ch-eyebrow' ),
				$this->heading( $title, 'h1', 'ch-display' ),
				$this->text( '<p>' . esc_html( $description ) . '</p>', 'ch-intro-copy' ),
			), 'ch-hero-copy', array( 'width' => array( 'unit' => '%', 'size' => 47, 'sizes' => array() ), 'width_mobile' => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ) ) ),
			$this->box( array( $this->image( $image, 'ch-hero-media' ) ), 'ch-hero-visual', array( 'width' => array( 'unit' => '%', 'size' => 50, 'sizes' => array() ), 'width_mobile' => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ) ) ),
		), 'ch-hero', array( 'flex_direction' => 'row', 'flex_direction_mobile' => 'column', 'flex_align_items' => 'center', 'flex_gap' => array( 'column' => '28', 'row' => '28', 'isLinked' => true, 'unit' => 'px', 'size' => 28 ) ) );
	}

	private function home(): array {
		return array(
			$this->section( array(
				$this->box( array(
					$this->text( '<p>چیدمون</p>', 'ch-eyebrow' ),
					$this->heading( 'ایده‌های چیدمان خانه', 'h1', 'ch-display' ),
					$this->text( '<p>از ایدهٔ یک گوشه تا انتخاب محصول؛ فضاها را ببین، راهنماها را بخوان و گزینه‌ها را کنار هم مقایسه کن.</p>', 'ch-intro-copy' ),
					$this->box( array( $this->button( 'کشف چیدمان‌ها', '/shop-the-look/' ), $this->button( 'راهنمای خرید', '/guides/', 'ch-link-button' ) ), 'ch-actions', array( 'flex_direction' => 'row', 'flex_wrap' => 'wrap' ) ),
				), 'ch-hero-copy', array( 'width' => array( 'unit' => '%', 'size' => 47, 'sizes' => array() ), 'width_mobile' => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ) ) ),
				$this->box( array( $this->image( 'hero', 'ch-hero-media' ) ), 'ch-hero-visual', array( 'width' => array( 'unit' => '%', 'size' => 50, 'sizes' => array() ), 'width_mobile' => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ) ) ),
			), 'ch-hero ch-home-hero', array( 'flex_direction' => 'row', 'flex_direction_mobile' => 'column', 'flex_align_items' => 'center', 'flex_gap' => array( 'column' => '28', 'row' => '28', 'isLinked' => true, 'unit' => 'px', 'size' => 28 ) ) ),
			$this->section( array(
				$this->text( '<p>از کجا شروع کنیم؟</p>', 'ch-eyebrow' ),
				$this->heading( 'مسیر خودت را پیدا کن' ),
				$this->box( array(
					$this->route( 'reading', 'ایده‌های چیدمان', 'از ترکیب رنگ، نور و وسایل برای فضای خودت ایده بگیر.', '/shop-the-look/', 'دیدن ایده‌ها' ),
					$this->route( 'work', 'راهنمای خرید', 'برای انتخاب اندازه، جنس و کاربرد درست شروع کن.', '/guides/', 'خواندن راهنماها' ),
					$this->route( 'dining', 'مقایسه‌ها', 'تفاوت گزینه‌ها را در کنار هم بخوان.', '/comparisons/', 'دیدن مقایسه‌ها' ),
				), 'ch-route-grid', array( 'flex_direction' => 'row', 'flex_direction_mobile' => 'column', 'flex_wrap' => 'wrap', 'flex_justify_content' => 'space-between' ) ),
			), 'ch-discovery' ),
			$this->section( array(
				$this->text( '<p>مجله</p>', 'ch-eyebrow' ),
				$this->heading( 'تازه‌ترین ایده‌ها و راهنماها' ),
				$this->posts( '', 6 ),
				$this->button( 'همهٔ مطالب', '/magazine/', 'ch-link-button' ),
			), 'ch-magazine-section' ),
		);
	}

	private function landing( string $kind ): array {
		if ( 'guides' === $kind ) {
			return array( $this->intro( 'راهنمای خرید', 'راهنماهای انتخاب و خرید', 'راهنماهای چیدمون به اندازه، کاربرد و جزئیات قابل بررسی می‌پردازند.', 'work' ), $this->section( array( $this->heading( 'راهنماها' ), $this->posts( 'guides', 12 ) ), 'ch-listing' ) );
		}
		if ( 'comparisons' === $kind ) {
			return array( $this->section( array( $this->text( '<p>مقایسه‌ها</p>', 'ch-eyebrow' ), $this->heading( 'دو انتخاب را کنار هم ببین', 'h1' ), $this->text( '<p>مشخصات محصولات و اطلاعات فروشنده را کنار هم ببین؛ برای انتخاب متناسب با خانهٔ خودت.</p>' ), $this->widget( 'chidemoon-compare-table', array( 'show_picker' => 'yes', 'show_status' => 'yes', 'columns' => '4', 'columns_tablet' => '2', 'columns_mobile' => '1' ) ) ), 'ch-compare-section' ), $this->section( array( $this->heading( 'مقایسه‌های منتشر شده' ), $this->posts( 'comparisons', 12 ) ), 'ch-listing' ) );
		}
		return array(
			$this->shoppable_look(),
			$this->section( array( $this->heading( 'فضاهای خانه' ), $this->text( '<p>نقطه‌های روی تصویر، محصولات قابل بررسی را نشان می‌دهند. چیدمان‌های بدون نقطه برای الهام‌اند.</p>', 'ch-looks-context' ), $this->widget( 'chidemoon-room-filters' ), $this->posts( 'room-ideas', 12, 'chidemoon_looks' ) ), 'ch-listing' ),
		);
	}

	private function shoppable_look(): array {
		$look_image_id = $this->assets['shoppable'];
		return $this->section( array(
				$this->text( '<p>ایده‌های چیدمان</p>', 'ch-eyebrow' ),
				$this->heading( 'چیدمان قابل خرید', 'h1' ),
				$this->widget( 'chidemoon-shop-the-look', array(
					'image' => array( 'id' => $look_image_id, 'url' => wp_get_attachment_url( $look_image_id ) ),
					'image_alt' => 'گوشهٔ مطالعه با چراغ بازویی مشکی در چپ و چراغ شارژی سفید در راست',
					'caption' => 'چیدمان بازسازی‌شده بر پایهٔ تصاویر محصولات',
					'hotspots' => array(
						array( 'product_source_key' => 'basalam:25688211', 'label' => 'چراغ بازویی کریم‌زاده', 'x' => array( 'size' => 46, 'unit' => '%' ), 'y' => array( 'size' => 24, 'unit' => '%' ) ),
						array( 'product_source_key' => 'basalam:33684609', 'label' => 'چراغ شارژی HG 799', 'x' => array( 'size' => 80, 'unit' => '%' ), 'y' => array( 'size' => 22, 'unit' => '%' ) ),
					),
				) ),
			), 'ch-look-feature' );
	}

	private function article_next(): array {
		$posts = $this->posts( '', 2, 'chidemoon_related_posts' );
		$posts['settings']['_css_classes'] = 'ch-editorial-feed ch-related-feed';
		$posts['settings']['classic_read_more_text'] = 'خواندن مطلب';
		return $this->section( array(
			$this->heading( 'برای مطالعهٔ بیشتر' ),
			$posts,
		), 'ch-article-next' );
	}

	private function search_help(): array {
		return $this->text( '<p>نتیجه‌ها از میان محصولات، ایده‌ها، راهنماها و مقایسه‌ها هستند.</p>', 'ch-search-help' );
	}

	private function search_facets(): array {
		return $this->widget( 'chidemoon-search-facets', array( '_css_classes' => 'ch-search-facet-widget' ) );
	}

	private function search_recovery(): array {
		$paths = array(
			'/shop/'          => 'محصولات',
			'/shop-the-look/' => 'ایده‌های چیدمان',
			'/guides/'        => 'راهنماهای خرید',
		);
		$links = array();
		foreach ( $paths as $path => $label ) {
			$links[] = '<a href="' . esc_url( home_url( $path ) ) . '">' . esc_html( $label ) . '</a>';
		}
		return $this->box( array(
			$this->heading( 'از مسیرهای دیگر ادامه بده', 'h2' ),
			$this->text( '<p>می‌توانی عبارت بالا را اصلاح کنی یا این بخش‌ها را ببینی.</p><p class="ch-search-recovery-links">' . implode( '، ', $links ) . '</p>' ),
		), 'ch-search-recovery' );
	}

	private function upgrade_shop_look_page( int $page_id, array $feature ): void {
		$elements = json_decode( (string) get_post_meta( $page_id, '_elementor_data', true ), true );
		if ( ! is_array( $elements ) || str_contains( (string) get_post_meta( $page_id, '_elementor_data', true ), 'chidemoon-shop-the-look' ) ) {
			return;
		}
		if ( ! $this->replace_shop_look_hero( $elements, $feature ) ) {
			array_unshift( $elements, $feature );
		}
		$document = $this->elementor->documents->get( $page_id, false );
		if ( ! $document || ! $document->save( array( 'elements' => $elements ) ) ) {
			WP_CLI::error( 'Could not upgrade Shop the Look page #' . $page_id );
		}
		WP_CLI::success( 'Upgraded Shop the Look page #' . $page_id );
	}

	private function replace_shop_look_hero( array &$elements, array $feature ): bool {
		foreach ( $elements as &$element ) {
			if ( 'ch-section ch-hero' === ( $element['settings']['css_classes'] ?? '' ) ) {
				$element = $feature;
				return true;
			}
			if ( ! empty( $element['elements'] ) && $this->replace_shop_look_hero( $element['elements'], $feature ) ) {
				return true;
			}
		}
		return false;
	}

	private function templates(): array {
		return array(
			'site-header' => array( 'header', array( array( 'type' => 'include', 'name' => 'general' ) ), array(
				$this->section( array(
					$this->box( array( $this->widget( 'theme-site-title', array( 'header_size' => 'div', '_css_classes' => 'ch-brand' ) ), $this->text( '<p>خانه، به سلیقهٔ تو</p>', 'ch-brand-caption' ) ), 'ch-brand-block', array( 'width' => array( 'unit' => '%', 'size' => 16 ), 'width_mobile' => array( 'unit' => '%', 'size' => 55 ), 'flex_gap' => array( 'column' => '0', 'row' => '0', 'isLinked' => true, 'unit' => 'px', 'size' => 0 ) ) ),
					$this->widget( 'nav-menu', array( 'menu' => (string) $this->menu_id, 'layout' => 'horizontal', 'dropdown' => 'tablet', '_css_classes' => 'ch-nav', '_element_width' => 'auto' ) ),
					$this->search( 'ch-header-search' ),
				), 'ch-header', array( 'flex_direction' => 'row', 'flex_direction_mobile' => 'row', 'flex_wrap' => 'wrap', 'flex_justify_content' => 'space-between', 'flex_align_items' => 'center', 'padding' => array( 'unit' => 'px', 'top' => '12', 'right' => '24', 'bottom' => '12', 'left' => '24', 'isLinked' => false ), 'padding_mobile' => array( 'unit' => 'px', 'top' => '10', 'right' => '16', 'bottom' => '10', 'left' => '16', 'isLinked' => false ) ) ),
			) ),
			'site-footer' => array( 'footer', array( array( 'type' => 'include', 'name' => 'general' ) ), array(
				$this->section( array(
					$this->box( array( $this->heading( 'چیدمون', 'h2' ), $this->text( '<p>خانه، به سلیقهٔ تو</p>', 'ch-footer-tagline' ), $this->text( '<p>از دیدن یک ایده تا انتخاب جزئیات خانه؛ چیدمان‌ها، راهنماها و محصولات را کنار هم پیدا کن.</p>' ) ), 'ch-footer-about' ),
					$this->box( array( $this->heading( 'کشف چیدمون', 'h3' ), $this->links( array( '/shop-the-look/' => 'ایده‌های چیدمان', '/guides/' => 'راهنمای خرید', '/comparisons/' => 'مقایسهٔ محصولات', '/magazine/' => 'مجلهٔ چیدمون' ) ) ), 'ch-footer-links' ),
					$this->box( array( $this->heading( 'محصولات', 'h3' ), $this->links( array( '/shop/' => 'فروشگاه', '/product-category/desk-lamps/' => 'چراغ مطالعه' ) ) ), 'ch-footer-links' ),
				), 'ch-footer', array( 'flex_direction' => 'row', 'flex_direction_mobile' => 'column', 'flex_wrap' => 'wrap', 'flex_justify_content' => 'space-between' ) ),
			) ),
			'post-single' => array( 'single-post', array( array( 'type' => 'include', 'name' => 'singular', 'sub_name' => 'post' ) ), array(
				$this->section( array( $this->widget( 'theme-post-title', array( 'header_size' => 'h1', '_css_classes' => 'ch-article-title' ) ), $this->widget( 'post-info', array( 'icon_list' => array( array( '_id' => 'chdate', 'type' => 'date', 'date_format' => 'custom', 'custom_date_format' => 'j F Y', 'link' => '', 'show_icon' => 'none' ) ), '_css_classes' => 'ch-article-meta' ) ), $this->widget( 'theme-post-featured-image', array( 'image_size' => 'full', '_css_classes' => 'ch-article-media' ) ) ), 'ch-article-header' ),
				$this->section( array( $this->widget( 'theme-post-content', array( '_css_classes' => 'ch-article-body' ) ), $this->widget( 'post-comments' ) ), 'ch-article-section' ),
				$this->article_next(),
			) ),
			'post-archive' => array( 'archive', array( array( 'type' => 'include', 'name' => 'archive' ) ), array(
				$this->section( array( $this->widget( 'theme-archive-title', array( 'header_size' => 'h1', '__dynamic__' => array( 'title' => '[elementor-tag id="" name="archive-title" settings="%7B%22include_context%22%3A%22no%22%2C%22fallback%22%3A%22مجلهٔ چیدمون%22%7D"]' ) ) ), $this->text( '<p>ایده‌ها، راهنماها و مقایسه‌ها برای فضاهای خانه.</p>' ), $this->archive_posts() ), 'ch-archive' ),
			) ),
			'search-results' => array( 'search-results', array( array( 'type' => 'include', 'name' => 'archive', 'sub_name' => 'search' ) ), array(
				$this->section( array( $this->text( '<p>در چیدمون پیدا کن</p>', 'ch-eyebrow' ), $this->widget( 'theme-archive-title', array( 'header_size' => 'h1' ) ), $this->search( 'ch-page-search' ), $this->search_help(), $this->search_facets(), $this->archive_posts( true ), $this->search_recovery() ), 'ch-search' ),
			) ),
			'not-found' => array( 'error-404', array( array( 'type' => 'include', 'name' => 'singular', 'sub_name' => 'not_found404' ) ), array(
				$this->section( array( $this->heading( 'این صفحه پیدا نشد', 'h1' ), $this->text( '<p>ممکن است نشانی تغییر کرده باشد. از جست‌وجو یا مسیرهای اصلی چیدمون ادامه بده.</p>' ), $this->search( 'ch-page-search' ), $this->button( 'بازگشت به صفحهٔ اصلی', '/' ) ), 'ch-not-found' ),
			) ),
			'product-single' => array( 'product', array( array( 'type' => 'include', 'name' => 'woocommerce', 'sub_name' => 'product' ) ), array(
				$this->section( array( $this->widget( 'woocommerce-breadcrumb' ), $this->box( array(
					$this->widget( 'woocommerce-product-images', array( '_css_classes' => 'ch-product-media' ) ),
					$this->box( array( $this->widget( 'woocommerce-product-title', array( 'header_size' => 'h1' ) ), $this->widget( 'woocommerce-product-price', array( '__globals__' => array( 'price_color' => 'globals/colors?id=secondary' ) ) ), $this->widget( 'woocommerce-product-short-description' ), $this->widget( 'chidemoon-product-offer', array( 'show_facts' => 'no' ) ) ), 'ch-product-summary' ),
				), 'ch-product-layout', array( 'flex_direction' => 'row', 'flex_wrap' => 'wrap' ) ), $this->widget( 'chidemoon-product-offer', array( 'facts_only' => 'yes', '_css_classes' => 'ch-product-facts-widget' ) ), $this->widget( 'woocommerce-product-data-tabs', array( '_css_classes' => 'ch-product-tabs-widget' ) ), $this->widget( 'woocommerce-product-related', $this->product_grid_settings() ) ), 'ch-product-single' ),
			) ),
			'product-archive' => array( 'product-archive', array( array( 'type' => 'include', 'name' => 'woocommerce', 'sub_name' => 'product_archive' ) ), array(
				$this->section( array( $this->widget( 'woocommerce-breadcrumb', array( '_css_classes' => 'ch-product-archive-breadcrumb' ) ), $this->widget( 'theme-archive-title', array( 'header_size' => 'h1', '__dynamic__' => array( 'title' => '[elementor-tag id="" name="archive-title" settings="%7B%22include_context%22%3A%22no%22%7D"]' ) ) ), $this->text( '<p>محصولات این فهرست برای بررسی و مقایسه‌اند. پیشنهاد خرید فقط برای محصولات تأییدشده فعال می‌شود.</p>' ), $this->widget( 'woocommerce-archive-description' ), $this->product_archive_grid() ), 'ch-product-archive' ),
			) ),
		);
	}

	private function archive_posts( bool $search = false ): array {
		return $this->widget( 'archive-posts', array( '_skin' => 'archive_classic', 'archive_classic_columns' => '3', 'archive_classic_columns_tablet' => '2', 'archive_classic_columns_mobile' => '1', 'archive_classic_title_tag' => 'h2', 'archive_classic_masonry' => 'no', 'archive_classic_meta_data' => $search ? array() : array( 'date' ), 'archive_classic_read_more_text' => $search ? 'مشاهدهٔ نتیجه' : 'مشاهدهٔ مطلب', 'nothing_found_message' => $search ? 'نتیجه‌ای پیدا نشد.' : 'مطلبی با این مشخصات پیدا نشد. عبارت دیگری را جست‌وجو کن.', 'pagination_type' => 'numbers', '_css_classes' => $search ? 'ch-editorial-feed ch-search-results-feed' : 'ch-editorial-feed' ) );
	}

	private function has_class( array $element, string $class ): bool {
		$classes = trim( (string) ( $element['settings']['css_classes'] ?? $element['settings']['_css_classes'] ?? '' ) );
		return in_array( $class, preg_split( '/\s+/', $classes ), true );
	}

	private function child_has_class( array $children, string $class ): bool {
		foreach ( $children as $child ) {
			if ( $this->has_class( $child, $class ) ) {
				return true;
			}
		}
		return false;
	}

	private function patch_ui_elements( array &$elements, string $target, array &$changes ): void {
		if ( 'site-footer' === $target ) {
			foreach ( $elements as $index => $element ) {
				if ( $this->has_class( $element, 'ch-footer-bottom' ) ) {
					unset( $elements[ $index ] );
					$changes[] = 'footer bottom strip removed';
				} elseif ( 'text-editor' === ( $element['widgetType'] ?? '' ) && str_contains( (string) ( $element['settings']['editor'] ?? '' ), 'قیمت‌ها مربوط به زمان بررسی منبع‌اند' ) ) {
					unset( $elements[ $index ] );
					$changes[] = 'footer price notice removed';
				}
			}
			$elements = array_values( $elements );
		}
		foreach ( $elements as &$element ) {
			if ( in_array( $element['widgetType'] ?? '', array( 'posts', 'archive-posts' ), true ) && $this->has_class( $element, 'ch-editorial-feed' ) ) {
				$key = 'posts' === $element['widgetType'] ? 'classic_masonry' : 'archive_classic_masonry';
				if ( 'no' !== ( $element['settings'][ $key ] ?? '' ) ) {
					$element['settings'][ $key ] = 'no';
					$changes[] = 'editorial grid without masonry';
				}
			}
			$children = &$element['elements'];
			if ( ! is_array( $children ) ) {
				continue;
			}
			if ( 'home' === $target && $this->has_class( $element, 'ch-route' ) ) {
				$labels = array( '/shop-the-look/' => 'دیدن ایده‌ها', '/guides/' => 'خواندن راهنماها', '/comparisons/' => 'دیدن مقایسه‌ها' );
				foreach ( $children as &$child ) {
					$path = (string) wp_parse_url( (string) ( $child['settings']['link']['url'] ?? '' ), PHP_URL_PATH );
					if ( 'button' === ( $child['widgetType'] ?? '' ) && 'مشاهده' === ( $child['settings']['text'] ?? '' ) && isset( $labels[ $path ] ) ) {
						$child['settings']['text'] = $labels[ $path ];
						$changes[] = 'route action ' . $path;
					}
				}
				unset( $child );
			}
			if ( 'guides' === $target && $this->has_class( $element, 'ch-hero-copy' ) ) {
				foreach ( $children as &$child ) {
					if ( 'heading' === ( $child['widgetType'] ?? '' ) && 'قبل از خرید، بهتر انتخاب کن' === ( $child['settings']['title'] ?? '' ) ) {
						$child['settings']['title'] = 'راهنماهای انتخاب و خرید';
						$changes[] = 'guide headline';
					}
				}
				unset( $child );
			}
			if ( 'shop-the-look' === $target && $this->has_class( $element, 'ch-look-feature' ) ) {
				foreach ( $children as &$child ) {
					if ( 'heading' === ( $child['widgetType'] ?? '' ) && 'ببین و بخر' === ( $child['settings']['title'] ?? '' ) ) {
						$child['settings']['title'] = 'چیدمان قابل خرید';
						$changes[] = 'buyable feature title';
					}
				}
				unset( $child );
			}
			if ( 'shop-the-look' === $target && $this->has_class( $element, 'ch-listing' ) && ! $this->child_has_class( $children, 'ch-looks-context' ) ) {
				foreach ( $children as $index => $child ) {
					if ( 'chidemoon-room-filters' === ( $child['widgetType'] ?? '' ) ) {
						array_splice( $children, $index, 0, array( $this->text( '<p>نقطه‌های روی تصویر، محصولات قابل بررسی را نشان می‌دهند. چیدمان‌های بدون نقطه برای الهام‌اند.</p>', 'ch-looks-context' ) ) );
						$changes[] = 'look listing context';
						break;
					}
				}
			}
			if ( 'search-results' === $target && $this->has_class( $element, 'ch-search' ) ) {
				foreach ( $children as $index => &$child ) {
					if ( 'search' === ( $child['widgetType'] ?? '' ) && $this->has_class( $child, 'ch-page-search' ) && ! $this->child_has_class( $children, 'ch-search-help' ) ) {
						array_splice( $children, $index + 1, 0, array( $this->search_help() ) );
						$changes[] = 'search context';
						break;
					}
				}
				unset( $child );
				if ( ! $this->child_has_class( $children, 'ch-search-facet-widget' ) ) {
					foreach ( $children as $index => $child ) {
						if ( $this->has_class( $child, 'ch-search-help' ) ) {
							array_splice( $children, $index + 1, 0, array( $this->search_facets() ) );
							$changes[] = 'search result facets';
							break;
						}
					}
				}
				foreach ( $children as &$child ) {
					if ( 'archive-posts' !== ( $child['widgetType'] ?? '' ) ) {
						continue;
					}
					if ( 'مشاهده' === ( $child['settings']['archive_classic_read_more_text'] ?? '' ) ) {
						$child['settings']['archive_classic_read_more_text'] = 'مشاهدهٔ نتیجه';
						$changes[] = 'search result action';
					}
					if ( 'نتیجه‌ای پیدا نشد. عبارت دیگری را جست‌وجو کن.' === ( $child['settings']['nothing_found_message'] ?? '' ) ) {
						$child['settings']['nothing_found_message'] = 'نتیجه‌ای پیدا نشد.';
						$changes[] = 'search empty copy';
					}
					if ( 'ch-editorial-feed' === ( $child['settings']['_css_classes'] ?? '' ) ) {
						$child['settings']['_css_classes'] = 'ch-editorial-feed ch-search-results-feed';
						$changes[] = 'search result class';
					}
				}
				unset( $child );
				if ( ! $this->child_has_class( $children, 'ch-search-recovery' ) ) {
					foreach ( $children as $index => $child ) {
						if ( 'archive-posts' === ( $child['widgetType'] ?? '' ) ) {
							array_splice( $children, $index + 1, 0, array( $this->search_recovery() ) );
							$changes[] = 'empty search recovery';
							break;
						}
					}
				}
			}
			if ( 'post-single' === $target && $this->has_class( $element, 'ch-main' ) && $this->child_has_class( $children, 'ch-article-section' ) && ! $this->child_has_class( $children, 'ch-article-next' ) ) {
				$children[] = $this->article_next();
				$changes[] = 'related articles';
			}
			if ( 'post-single' === $target && $this->has_class( $element, 'ch-article-next' ) ) {
				foreach ( $children as &$child ) {
					if ( 'heading' === ( $child['widgetType'] ?? '' ) && 'در همین موضوع بخوان' === ( $child['settings']['title'] ?? '' ) ) {
						$child['settings']['title'] = 'برای مطالعهٔ بیشتر';
						$changes[] = 'related articles heading';
					}
				}
				unset( $child );
			}
			if ( 'product-single' === $target && $this->has_class( $element, 'ch-product-single' ) ) {
				$facts_settings = null;
				$layout_index = null;
				foreach ( $children as $index => &$child ) {
					if ( $this->has_class( $child, 'ch-product-layout' ) ) {
						$layout_index = $index;
						foreach ( $child['elements'] as &$column ) {
							if ( ! $this->has_class( $column, 'ch-product-summary' ) ) {
								continue;
							}
							foreach ( $column['elements'] as &$offer ) {
								if ( 'chidemoon-product-offer' !== ( $offer['widgetType'] ?? '' ) || 'yes' === ( $offer['settings']['facts_only'] ?? 'no' ) || 'no' === ( $offer['settings']['show_facts'] ?? 'yes' ) ) {
									continue;
								}
								$facts_settings = array( 'facts_only' => 'yes', '_css_classes' => 'ch-product-facts-widget' );
								foreach ( $offer['settings'] as $key => $value ) {
									if ( in_array( $key, array( 'product_id', 'text_color', 'facts_label_color' ), true ) || str_starts_with( $key, 'facts_gap' ) || str_starts_with( $key, 'typography_' ) ) {
										$facts_settings[ $key ] = $value;
									}
								}
								$offer['settings']['show_facts'] = 'no';
								$changes[] = 'product summary facts moved';
								break;
							}
							unset( $offer );
						}
						unset( $column );
					}
					if ( 'woocommerce-product-data-tabs' === ( $child['widgetType'] ?? '' ) && ! $this->has_class( $child, 'ch-product-tabs-widget' ) ) {
						$child['settings']['_css_classes'] = trim( (string) ( $child['settings']['_css_classes'] ?? '' ) . ' ch-product-tabs-widget' );
						$changes[] = 'product tabs aligned';
					}
				}
				unset( $child );
				if ( null !== $facts_settings && null !== $layout_index && ! $this->child_has_class( $children, 'ch-product-facts-widget' ) ) {
					array_splice( $children, $layout_index + 1, 0, array( $this->widget( 'chidemoon-product-offer', $facts_settings ) ) );
					$changes[] = 'product full-width facts';
				}
			}
			if ( 'product-archive' === $target && $this->has_class( $element, 'ch-product-archive' ) ) {
				$this->require_product_archive_controls();
				if ( ! $this->child_has_class( $children, 'ch-product-archive-breadcrumb' ) ) {
					array_unshift( $children, $this->widget( 'woocommerce-breadcrumb', array( '_css_classes' => 'ch-product-archive-breadcrumb' ) ) );
					$changes[] = 'product archive breadcrumb';
				}
				foreach ( $children as &$child ) {
					if ( 'woocommerce-archive-products' !== ( $child['widgetType'] ?? '' ) ) {
						continue;
					}
					foreach ( array( 'allow_order' => 'sorting', 'show_result_count' => 'result count' ) as $key => $label ) {
						if ( ! array_key_exists( $key, $child['settings'] ) ) {
							$child['settings'][ $key ] = 'yes';
							$changes[] = 'product archive ' . $label;
						}
					}
				}
				unset( $child );
			}
			if ( 'conceptual-look' === $target && ! $this->child_has_class( $children, 'ch-look-state' ) ) {
				foreach ( $children as $index => $child ) {
					if ( 'chidemoon-shop-the-look' === ( $child['widgetType'] ?? '' ) && empty( $child['settings']['hotspots'] ) ) {
						array_splice( $children, $index, 0, array( $this->text( '<p><strong>ایدهٔ مفهومی</strong> · محصولات این تصویر برای خرید معرفی نشده‌اند.</p>', 'ch-look-state ch-look-state-conceptual' ) ) );
						$changes[] = 'conceptual look label';
						break;
					}
				}
			}
			$this->patch_ui_elements( $children, $target, $changes );
		}
		unset( $element, $children );
	}

	private function upgrade_ui_document( int $id, string $target, bool $apply ): bool {
		if ( 'conceptual-look' !== $target && ! get_post_meta( $id, '_chidemoon_elementor_rebuild', true ) ) {
			WP_CLI::log( 'Skipped unmanaged Elementor document #' . $id );
			return false;
		}
		$raw = (string) get_post_meta( $id, '_elementor_data', true );
		$elements = json_decode( $raw, true );
		if ( ! is_array( $elements ) ) {
			WP_CLI::warning( 'Skipped invalid Elementor data in document #' . $id );
			return false;
		}
		$changes = array();
		$this->patch_ui_elements( $elements, $target, $changes );
		if ( ! $changes ) {
			WP_CLI::log( 'No matching UI changes for #' . $id . ' (' . $target . ')' );
			return false;
		}
		WP_CLI::log( ( $apply ? 'Applying' : 'Would apply' ) . ' #' . $id . ' (' . $target . '): ' . implode( ', ', $changes ) );
		if ( ! $apply ) {
			return true;
		}
		$backup_key = '_chidemoon_pre_ui_upgrade_20260929_elementor_data';
		if ( ! metadata_exists( 'post', $id, $backup_key ) && ! add_post_meta( $id, $backup_key, wp_slash( $raw ), true ) ) {
			WP_CLI::error( 'Could not back up Elementor document #' . $id );
		}
		$document = $this->elementor->documents->get( $id, false );
		if ( ! $document || ! $document->save( array( 'elements' => $elements ) ) ) {
			WP_CLI::error( 'Could not save UI upgrade for document #' . $id );
		}
		update_post_meta( $id, '_chidemoon_ui_upgrade', '2026-09-29' );
		return true;
	}

	public function upgrade_ui( bool $apply ): void {
		$this->require_product_archive_controls();
		$count = 0;
		foreach ( array( 'home', 'guides', 'comparisons', 'shop-the-look' ) as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page && $this->upgrade_ui_document( (int) $page->ID, $slug, $apply ) ) {
				++$count;
			}
		}
		foreach ( array( 'site-footer', 'search-results', 'post-single', 'post-archive', 'product-single', 'product-archive' ) as $slug ) {
			$templates = get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'name' => 'chidemoon-' . $slug, 'posts_per_page' => 1 ) );
			if ( $templates && $this->upgrade_ui_document( (int) $templates[0]->ID, $slug, $apply ) ) {
				++$count;
			}
		}
		$looks = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_key' => '_chidemoon_native_look', 'meta_value' => '1' ) );
		foreach ( $looks as $look ) {
			if ( $this->upgrade_ui_document( (int) $look->ID, 'conceptual-look', $apply ) ) {
				++$count;
			}
		}
		if ( $apply && $count ) {
			$this->elementor->files_manager->clear_cache();
		}
		WP_CLI::success( ( $apply ? 'Updated ' : 'Would update ' ) . $count . ' Elementor documents.' );
	}

	private function save( int $id, array $elements ): void {
		if ( get_post_meta( $id, '_chidemoon_elementor_rebuild', true ) && ! $this->force ) {
			WP_CLI::log( 'Skipped existing migration #' . $id );
			return;
		}
		if ( ! metadata_exists( 'post', $id, '_chidemoon_pre_rebuild_elementor_data' ) ) {
			add_post_meta( $id, '_chidemoon_pre_rebuild_elementor_data', (string) get_post_meta( $id, '_elementor_data', true ), true );
			add_post_meta( $id, '_chidemoon_pre_rebuild_content', (string) get_post_field( 'post_content', $id ), true );
		}
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		if ( 'page' === get_post_type( $id ) ) {
			if ( ! metadata_exists( 'post', $id, '_chidemoon_pre_rebuild_page_template' ) ) {
				add_post_meta( $id, '_chidemoon_pre_rebuild_page_template', (string) get_post_meta( $id, '_wp_page_template', true ), true );
			}
			update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
		}
		if ( ! in_array( get_post_meta( $id, '_elementor_template_type', true ), array( 'header', 'footer' ), true ) ) {
			$elements = array( $this->box( $elements, 'ch-main', array( 'html_tag' => 'main', '_element_id' => 'chidemoon-content' ) ) );
		}
		$document = $this->elementor->documents->get( $id, false );
		if ( ! $document || ! $document->save( array( 'elements' => $elements ) ) ) {
			WP_CLI::error( 'Elementor could not save document #' . $id );
		}
		update_post_meta( $id, '_chidemoon_elementor_rebuild', '2026-09-29' );
		WP_CLI::success( 'Saved Elementor document #' . $id );
	}

	public function run(): void {
		$kit = $this->elementor->kits_manager->get_active_kit();
		$kit_id = $kit->get_main_id();
		if ( ! get_post_meta( $kit_id, '_chidemoon_elementor_rebuild', true ) || $this->force ) {
			if ( ! metadata_exists( 'post', $kit_id, '_chidemoon_pre_rebuild_kit' ) ) {
				add_post_meta( $kit_id, '_chidemoon_pre_rebuild_kit', get_post_meta( $kit_id, '_elementor_page_settings', true ), true );
			}
			$kit->save( array( 'settings' => array(
				'system_colors' => array(
					array( '_id' => 'primary', 'title' => 'اصلی', 'color' => '#123f34' ),
					array( '_id' => 'secondary', 'title' => 'مکمل', 'color' => '#b95d3e' ),
					array( '_id' => 'text', 'title' => 'متن', 'color' => '#17241f' ),
					array( '_id' => 'accent', 'title' => 'تأکید', 'color' => '#607069' ),
				),
				'system_typography' => array(
					array( '_id' => 'primary', 'title' => 'عنوان اصلی', 'typography_typography' => 'custom', 'typography_font_family' => 'Estedad', 'typography_font_weight' => '800' ),
					array( '_id' => 'secondary', 'title' => 'عنوان فرعی', 'typography_typography' => 'custom', 'typography_font_family' => 'Estedad', 'typography_font_weight' => '700' ),
					array( '_id' => 'text', 'title' => 'متن', 'typography_typography' => 'custom', 'typography_font_family' => 'Vazirmatn', 'typography_font_weight' => '400' ),
					array( '_id' => 'accent', 'title' => 'تأکید', 'typography_typography' => 'custom', 'typography_font_family' => 'Vazirmatn', 'typography_font_weight' => '600' ),
				),
				'container_width' => array( 'unit' => 'px', 'size' => 1280, 'sizes' => array() ),
				'body_color' => '#17241f', 'body_typography_font_family' => 'Vazirmatn',
				'h1_typography_font_family' => 'Estedad', 'h2_typography_font_family' => 'Estedad', 'h3_typography_font_family' => 'Estedad',
			) ) );
			update_post_meta( $kit_id, '_chidemoon_elementor_rebuild', '2026-09-29' );
		}
		$this->save( (int) get_page_by_path( 'home' )->ID, $this->home() );
		foreach ( array( 'guides', 'comparisons', 'shop-the-look' ) as $slug ) {
			$page_id = (int) get_page_by_path( $slug )->ID;
			$elements = $this->landing( $slug );
			$this->save( $page_id, $elements );
			if ( 'shop-the-look' === $slug && ! $this->force ) {
				$this->upgrade_shop_look_page( $page_id, $elements[0] );
			}
		}
		foreach ( $this->templates() as $slug => $definition ) {
			list( $type, $conditions, $elements ) = $definition;
			$title = array( 'site-header' => 'سربرگ', 'site-footer' => 'پاورقی', 'post-single' => 'قالب نوشته', 'post-archive' => 'مجله و آرشیو', 'search-results' => 'نتایج جست‌وجو', 'not-found' => 'صفحهٔ ۴۰۴', 'product-single' => 'جزئیات محصول', 'product-archive' => 'فهرست محصولات' )[ $slug ];
			$existing = get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'any', 'name' => 'chidemoon-' . $slug, 'posts_per_page' => 1 ) );
			if ( $existing ) {
				$id = (int) $existing[0]->ID;
				if ( get_post_meta( $id, '_chidemoon_elementor_rebuild', true ) && ! $this->force ) {
					WP_CLI::log( 'Preserved existing template #' . $id );
					continue;
				}
				wp_update_post( array( 'ID' => $id, 'post_title' => 'چیدمون | ' . $title ) );
			} else {
				$document = $this->elementor->documents->create( $type, array( 'post_status' => 'publish', 'post_name' => 'chidemoon-' . $slug, 'post_title' => 'چیدمون | ' . $title ) );
				$id = is_wp_error( $document ) ? $document : $document->get_main_id();
			}
			if ( is_wp_error( $id ) || ! $id ) {
				WP_CLI::error( 'Could not create template ' . $slug );
			}
			update_post_meta( $id, '_elementor_template_type', $type );
			wp_set_object_terms( $id, $type, 'elementor_library_type' );
			$this->save( $id, $elements );
			$manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();
			if ( ! $manager->save_conditions( $id, $conditions ) && ! get_post_meta( $id, '_elementor_conditions', true ) ) {
				WP_CLI::error( 'Could not assign conditions to ' . $slug );
			}
		}
		if ( ! get_option( 'chidemoon_visual_setup' ) || $this->force ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', (int) get_page_by_path( 'home' )->ID );
			update_option( 'page_for_posts', (int) get_page_by_path( 'magazine' )->ID );
			update_option( 'woocommerce_shop_page_id', (int) get_page_by_path( 'shop' )->ID );
			update_option( 'blogname', 'چیدمون' );
			update_option( 'chidemoon_visual_setup', '2026-09-29', false );
		}
		$this->elementor->files_manager->clear_cache();
	}
}

$rebuild = new Chidemoon_Elementor_Rebuild( $assets, $force, $ui_upgrade );
if ( $ui_upgrade ) {
	$rebuild->upgrade_ui( $apply );
} else {
	$rebuild->run();
	WP_CLI::success( 'Migration finished. Inspect every template and route before activating Hello on production.' );
}
