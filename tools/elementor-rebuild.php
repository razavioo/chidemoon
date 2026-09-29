<?php
/**
 * Chidemoon's one-time, reviewable Elementor migration.
 * Run with: wp eval-file /tools/elementor-rebuild.php apply
 * An existing migration is left alone unless force is passed.
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
if ( $force && ! $apply ) {
	WP_CLI::error( 'force requires apply.' );
}
if ( $apply && ( ! current_user_can( 'edit_theme_options' ) || ! current_user_can( 'edit_pages' ) ) ) {
	WP_CLI::error( 'Run as an administrator with --user=<id>.' );
}
if ( $apply ) {
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
	if ( $apply && ! $page ) {
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title ), true );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}
	} elseif ( $apply && $page && $page->post_title !== $title ) {
		wp_update_post( array( 'ID' => $page->ID, 'post_title' => $title ) );
	}
}

$assets = array(
	'hero'    => 'look-real-43.jpg',
	'work'    => 'look-compact-home-office.jpg',
	'reading' => 'look-reading-corner.jpg',
	'dining'  => 'look-japandi-dining.jpg',
	'bedroom' => 'look-calm-green-bedroom.jpg',
);
foreach ( $assets as $key => $file ) {
	$matches = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'meta_key' => '_wp_attached_file', 'meta_value' => $file, 'meta_compare' => 'LIKE', 'fields' => 'ids' ) );
	if ( ! $matches && $apply ) {
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
	if ( $apply && $assets[ $key ] && false === get_option( 'chidemoon_concept_media', false ) ) {
		update_post_meta( $assets[ $key ], '_wp_attachment_image_alt', 'چیدمان مفهومی خانه' );
	}
}
if ( $apply && false === get_option( 'chidemoon_concept_media', false ) ) {
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
	WP_CLI::log( "Dry run. Pass apply to write:\n- " . implode( "\n- ", $summary ) );
	return;
}

if ( ! current_user_can( 'edit_theme_options' ) || ! current_user_can( 'edit_pages' ) ) {
	WP_CLI::error( 'Run as an administrator with --user=<id>.' );
}

// Retire only the first inventory, so a later rerun cannot hide the editor's new work.
if ( $reset_demo && false === get_option( 'chidemoon_demo_retirement', false ) ) {
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

	public function __construct( array $assets, bool $force ) {
		$this->assets    = $assets;
		$this->force     = $force;
		$this->elementor = \Elementor\Plugin::$instance;
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

	private function image( string $key, string $class = '' ): array {
		$id = $this->assets[ $key ];
		return $this->widget( 'image', array( 'image' => array( 'id' => $id, 'url' => wp_get_attachment_url( $id ) ), 'image_size' => 'full', '_css_classes' => $class ) );
	}

	private function posts( string $category = '', int $count = 6, string $query_id = '' ): array {
		$settings = array(
			'_skin' => 'classic', 'classic_columns' => '3', 'classic_columns_tablet' => '2', 'classic_columns_mobile' => '1',
			'classic_posts_per_page' => $count, 'classic_thumbnail_size_size' => 'large', 'classic_meta_data' => array( 'date' ),
			'classic_excerpt_length' => 16, 'classic_read_more_text' => 'مشاهدهٔ مطلب', 'classic_masonry' => 'yes',
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

	private function route( string $image, string $title, string $description, string $path ): array {
		return $this->box( array(
			$this->image( $image, 'ch-route-media' ),
			$this->heading( $title, 'h3' ),
			$this->text( '<p>' . esc_html( $description ) . '</p>' ),
			$this->button( 'مشاهده', $path, 'ch-link-button' ),
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
					$this->route( 'reading', 'ایده‌های چیدمان', 'از ترکیب رنگ، نور و وسایل برای فضای خودت ایده بگیر.', '/shop-the-look/' ),
					$this->route( 'work', 'راهنمای خرید', 'برای انتخاب اندازه، جنس و کاربرد درست شروع کن.', '/guides/' ),
					$this->route( 'dining', 'مقایسه‌ها', 'تفاوت گزینه‌ها را در کنار هم بخوان.', '/comparisons/' ),
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
			return array( $this->intro( 'راهنمای خرید', 'قبل از خرید، بهتر انتخاب کن', 'راهنماهای چیدمون به اندازه، کاربرد و جزئیات قابل بررسی می‌پردازند.', 'work' ), $this->section( array( $this->heading( 'راهنماها' ), $this->posts( 'guides', 12 ) ), 'ch-listing' ) );
		}
		if ( 'comparisons' === $kind ) {
			return array( $this->section( array( $this->text( '<p>مقایسه‌ها</p>', 'ch-eyebrow' ), $this->heading( 'دو انتخاب را کنار هم ببین', 'h1' ), $this->text( '<p>مشخصات محصولات و اطلاعات فروشنده را کنار هم ببین؛ برای انتخاب متناسب با خانهٔ خودت.</p>' ), $this->widget( 'chidemoon-compare-table', array( 'show_picker' => 'yes', 'show_status' => 'yes', 'columns' => '4', 'columns_tablet' => '2', 'columns_mobile' => '1' ) ) ), 'ch-compare-section' ), $this->section( array( $this->heading( 'مقایسه‌های منتشر شده' ), $this->posts( 'comparisons', 12 ) ), 'ch-listing' ) );
		}
		return array( $this->intro( 'ایده‌های چیدمان', 'چیدمان را از نزدیک ببین', 'برای هر فضا ایده بگیر و جزئیات قابل خرید را همان‌جا ببین.', 'reading' ), $this->section( array( $this->heading( 'فضاهای خانه' ), $this->widget( 'chidemoon-room-filters' ), $this->posts( 'room-ideas', 12, 'chidemoon_looks' ) ), 'ch-listing' ) );
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
				$this->section( array(
					$this->text( '<p>چیدمون ممکن است از بعضی لینک‌های فروشنده کارمزد دریافت کند.</p>', 'ch-disclosure' ),
					$this->text( '<p>با دقت انتخاب کن، با سلیقه بچین.</p>', 'ch-footer-signoff' ),
				), 'ch-footer-bottom', array( 'flex_direction' => 'row', 'flex_direction_mobile' => 'column', 'flex_justify_content' => 'space-between', 'padding' => array( 'unit' => 'px', 'top' => '20', 'right' => '24', 'bottom' => '20', 'left' => '24', 'isLinked' => false ) ) ),
			) ),
			'post-single' => array( 'single-post', array( array( 'type' => 'include', 'name' => 'singular', 'sub_name' => 'post' ) ), array(
				$this->section( array( $this->widget( 'theme-post-title', array( 'header_size' => 'h1', '_css_classes' => 'ch-article-title' ) ), $this->widget( 'post-info', array( 'icon_list' => array( array( '_id' => 'chdate', 'type' => 'date', 'date_format' => 'custom', 'custom_date_format' => 'j F Y', 'link' => '', 'show_icon' => 'none' ) ), '_css_classes' => 'ch-article-meta' ) ), $this->widget( 'theme-post-featured-image', array( 'image_size' => 'full', '_css_classes' => 'ch-article-media' ) ) ), 'ch-article-header' ),
				$this->section( array( $this->widget( 'theme-post-content', array( '_css_classes' => 'ch-article-body' ) ), $this->widget( 'post-comments' ) ), 'ch-article-section' ),
			) ),
			'post-archive' => array( 'archive', array( array( 'type' => 'include', 'name' => 'archive' ) ), array(
				$this->section( array( $this->widget( 'theme-archive-title', array( 'header_size' => 'h1', '__dynamic__' => array( 'title' => '[elementor-tag id="" name="archive-title" settings="%7B%22include_context%22%3A%22no%22%2C%22fallback%22%3A%22مجلهٔ چیدمون%22%7D"]' ) ) ), $this->text( '<p>ایده‌ها، راهنماها و مقایسه‌ها برای فضاهای خانه.</p>' ), $this->archive_posts() ), 'ch-archive' ),
			) ),
			'search-results' => array( 'search-results', array( array( 'type' => 'include', 'name' => 'archive', 'sub_name' => 'search' ) ), array(
				$this->section( array( $this->text( '<p>در چیدمون پیدا کن</p>', 'ch-eyebrow' ), $this->widget( 'theme-archive-title', array( 'header_size' => 'h1' ) ), $this->search( 'ch-page-search' ), $this->archive_posts( true ) ), 'ch-search' ),
			) ),
			'not-found' => array( 'error-404', array( array( 'type' => 'include', 'name' => 'singular', 'sub_name' => 'not_found404' ) ), array(
				$this->section( array( $this->heading( 'این صفحه پیدا نشد', 'h1' ), $this->text( '<p>ممکن است نشانی تغییر کرده باشد. از جست‌وجو یا مسیرهای اصلی چیدمون ادامه بده.</p>' ), $this->search( 'ch-page-search' ), $this->button( 'بازگشت به صفحهٔ اصلی', '/' ) ), 'ch-not-found' ),
			) ),
			'product-single' => array( 'product', array( array( 'type' => 'include', 'name' => 'woocommerce', 'sub_name' => 'product' ) ), array(
				$this->section( array( $this->widget( 'woocommerce-breadcrumb' ), $this->box( array(
					$this->widget( 'woocommerce-product-images', array( '_css_classes' => 'ch-product-media' ) ),
					$this->box( array( $this->widget( 'woocommerce-product-title', array( 'header_size' => 'h1' ) ), $this->widget( 'woocommerce-product-price', array( '__globals__' => array( 'price_color' => 'globals/colors?id=secondary' ) ) ), $this->widget( 'woocommerce-product-short-description' ), $this->widget( 'chidemoon-product-offer' ) ), 'ch-product-summary' ),
				), 'ch-product-layout', array( 'flex_direction' => 'row', 'flex_wrap' => 'wrap' ) ), $this->widget( 'woocommerce-product-data-tabs' ), $this->widget( 'woocommerce-product-related', $this->product_grid_settings() ) ), 'ch-product-single' ),
			) ),
			'product-archive' => array( 'product-archive', array( array( 'type' => 'include', 'name' => 'woocommerce', 'sub_name' => 'product_archive' ) ), array(
				$this->section( array( $this->widget( 'theme-archive-title', array( 'header_size' => 'h1', '__dynamic__' => array( 'title' => '[elementor-tag id="" name="archive-title" settings="%7B%22include_context%22%3A%22no%22%7D"]' ) ) ), $this->text( '<p>محصولات این فهرست برای بررسی و مقایسه‌اند. پیشنهاد خرید فقط برای محصولات تأییدشده فعال می‌شود.</p>' ), $this->widget( 'woocommerce-archive-description' ), $this->widget( 'woocommerce-archive-products', array_merge( $this->product_grid_settings(), array( 'columns' => 4, 'columns_tablet' => '2', 'columns_mobile' => '1' ) ) ) ), 'ch-product-archive' ),
			) ),
		);
	}

	private function archive_posts( bool $search = false ): array {
		return $this->widget( 'archive-posts', array( '_skin' => 'archive_classic', 'archive_classic_columns' => '3', 'archive_classic_columns_tablet' => '2', 'archive_classic_columns_mobile' => '1', 'archive_classic_masonry' => 'yes', 'archive_classic_meta_data' => $search ? array() : array( 'date' ), 'archive_classic_read_more_text' => $search ? 'مشاهده' : 'مشاهدهٔ مطلب', 'nothing_found_message' => $search ? 'نتیجه‌ای پیدا نشد. عبارت دیگری را جست‌وجو کن.' : 'مطلبی با این مشخصات پیدا نشد. عبارت دیگری را جست‌وجو کن.', 'pagination_type' => 'numbers', '_css_classes' => 'ch-editorial-feed' ) );
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
			$this->save( (int) get_page_by_path( $slug )->ID, $this->landing( $slug ) );
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

( new Chidemoon_Elementor_Rebuild( $assets, $force ) )->run();
WP_CLI::success( 'Migration finished. Inspect every template and route before activating Hello on production.' );
