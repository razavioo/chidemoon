<?php
declare( strict_types=1 );

namespace Elementor {
	class Plugin {
		public static Plugin $instance;
		public object $widgets_manager;
		public object $documents;
		public object $files_manager;

		public function __construct() {
			$this->widgets_manager = new class {
				public function get_widget_types( string $type ): object {
					return new class( $type ) {
						public function __construct( private string $type ) {}
						public function get_controls(): array {
							return 'woocommerce-archive-products' === $this->type ? array( 'allow_order' => array(), 'show_result_count' => array() ) : array();
						}
					};
				}
			};
			$this->documents = new class {
				public function get( int $id, bool $edit ): object {
					return new class( $id ) {
						public function __construct( private int $id ) {}
						public function save( array $data ): bool {
							$GLOBALS['document_saves'][ $this->id ] = ( $GLOBALS['document_saves'][ $this->id ] ?? 0 ) + 1;
							$GLOBALS['post_meta'][ $this->id ]['_elementor_data'] = json_encode( $data['elements'], JSON_UNESCAPED_UNICODE );
							return true;
						}
					};
				}
			};
			$this->files_manager = new class {
				public function clear_cache(): void {}
			};
		}
	}
}

namespace ElementorPro {
	class Plugin {}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/../' );
	define( 'WP_CLI', true );
	class WP_CLI {
		public static function log( string $message ): void {}
		public static function success( string $message ): void {}
		public static function warning( string $message ): void { throw new \RuntimeException( $message ); }
		public static function error( string $message ): void { throw new \RuntimeException( $message ); }
	}

	$GLOBALS['post_meta'] = array();
	$GLOBALS['document_saves'] = array();
	function get_page_by_path( string $path ): false { return false; }
	function get_posts( array $query ): array { return array(); }
	function get_post_meta( int $id, string $key, bool $single ): mixed { return $GLOBALS['post_meta'][ $id ][ $key ] ?? false; }
	function metadata_exists( string $type, int $id, string $key ): bool { return array_key_exists( $key, $GLOBALS['post_meta'][ $id ] ?? array() ); }
	function add_post_meta( int $id, string $key, mixed $value, bool $unique ): bool {
		if ( metadata_exists( 'post', $id, $key ) ) { return false; }
		$GLOBALS['post_meta'][ $id ][ $key ] = $value;
		return true;
	}
	function update_post_meta( int $id, string $key, mixed $value ): void { $GLOBALS['post_meta'][ $id ][ $key ] = $value; }
	function wp_slash( string $value ): string { return addslashes( $value ); }
	function home_url( string $path ): string { return 'https://chidemoon.test' . $path; }
	function esc_url( string $url ): string { return htmlspecialchars( $url, ENT_QUOTES ); }
	function esc_html( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES ); }
	function wp_parse_url( string $url, int $component ): string|false { return parse_url( $url, $component ); }

	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	$args = array( 'ui-upgrade' );
	require __DIR__ . '/../tools/elementor-rebuild.php';

	function check( bool $condition, string $message ): void {
		if ( ! $condition ) { throw new \RuntimeException( $message ); }
	}
	function widget( string $type, array $settings = array() ): array {
		return array( 'id' => bin2hex( random_bytes( 4 ) ), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}
	function box( string $class, array $children ): array {
		return array( 'id' => bin2hex( random_bytes( 4 ) ), 'elType' => 'container', 'settings' => array( 'css_classes' => $class ), 'elements' => $children );
	}
	function fixture( int $id, array $elements, bool $managed = true ): string {
		$raw = json_encode( $elements, JSON_UNESCAPED_UNICODE );
		$GLOBALS['post_meta'][ $id ]['_elementor_data'] = $raw;
		if ( $managed ) { $GLOBALS['post_meta'][ $id ]['_chidemoon_elementor_rebuild'] = '2026-09-29'; }
		return $raw;
	}
	function upgrade( int $id, string $target ): bool {
		$method = new \ReflectionMethod( Chidemoon_Elementor_Rebuild::class, 'upgrade_ui_document' );
		return $method->invoke( $GLOBALS['rebuild'], $id, $target, true );
	}

	$home = array( box( 'ch-main', array( box( 'ch-route', array(
		widget( 'button', array( 'text' => 'مشاهده', 'link' => array( 'url' => home_url( '/guides/' ) ) ) ),
		widget( 'button', array( 'text' => 'انتخاب سردبیر', 'link' => array( 'url' => home_url( '/comparisons/' ) ) ) ),
	) ), widget( 'posts', array( '_css_classes' => 'ch-editorial-feed', 'classic_masonry' => 'yes' ) ) ) ) );
	$before = fixture( 101, $home );
	check( upgrade( 101, 'home' ), 'expected first home upgrade' );
	$after = json_decode( $GLOBALS['post_meta'][101]['_elementor_data'], true );
	check( $after[0]['elements'][0]['elements'][0]['settings']['text'] === 'خواندن راهنماها', 'route label not updated' );
	check( $after[0]['elements'][0]['elements'][1]['settings']['text'] === 'انتخاب سردبیر', 'editor label changed' );
	check( $after[0]['elements'][1]['settings']['classic_masonry'] === 'no', 'home masonry still conflicts with grid' );
	check( stripslashes( $GLOBALS['post_meta'][101]['_chidemoon_pre_ui_upgrade_20260929_elementor_data'] ) === $before, 'original document not backed up' );
	check( ! upgrade( 101, 'home' ) && $GLOBALS['document_saves'][101] === 1, 'upgrade must be idempotent' );

	$footer = array(
		box( 'ch-section ch-footer', array(
			box( 'ch-footer-about', array(
				widget( 'text-editor', array( 'editor' => '<p>قیمت‌ها مربوط به زمان بررسی منبع‌اند. قیمت نهایی و شرایط ارسال را در فروشگاه ببین.</p>' ) ),
				widget( 'text-editor', array( 'editor' => '<p>یادداشت ویرایشگر</p>' ) ),
			) ),
		) ),
		box( 'ch-section ch-footer-bottom', array( widget( 'text-editor', array( 'editor' => '<p>چیدمون ممکن است از بعضی لینک‌های فروشنده کارمزد دریافت کند.</p>' ) ) ) ),
	);
	fixture( 109, $footer );
	check( upgrade( 109, 'site-footer' ), 'expected footer cleanup' );
	$after = json_decode( $GLOBALS['post_meta'][109]['_elementor_data'], true );
	check( count( $after ) === 1, 'footer bottom strip remains' );
	$about = $after[0]['elements'][0]['elements'];
	check( count( $about ) === 1 && $about[0]['settings']['editor'] === '<p>یادداشت ویرایشگر</p>', 'footer cleanup changed editor content' );
	check( ! upgrade( 109, 'site-footer' ) && $GLOBALS['document_saves'][109] === 1, 'footer cleanup repeated' );

	$search = array( box( 'ch-main', array( box( 'ch-section ch-search', array(
		widget( 'search', array( '_css_classes' => 'ch-page-search' ) ),
		widget( 'archive-posts', array( '_css_classes' => 'ch-editorial-feed', 'archive_classic_masonry' => 'yes', 'archive_classic_read_more_text' => 'مشاهده', 'nothing_found_message' => 'نتیجه‌ای پیدا نشد. عبارت دیگری را جست‌وجو کن.' ) ),
		widget( 'text-editor', array( 'editor' => '<p>یادداشت سردبیر</p>' ) ),
	) ) ) ) );
	fixture( 102, $search );
	check( upgrade( 102, 'search-results' ), 'expected search upgrade' );
	$after = json_decode( $GLOBALS['post_meta'][102]['_elementor_data'], true );
	$children = $after[0]['elements'][0]['elements'];
	check( count( $children ) === 6, 'search should gain context, facets and recovery once' );
	check( $children[1]['settings']['_css_classes'] === 'ch-search-help', 'search context missing' );
	check( $children[2]['widgetType'] === 'chidemoon-search-facets', 'search facets missing' );
	check( $children[3]['settings']['archive_classic_read_more_text'] === 'مشاهدهٔ نتیجه', 'search action missing' );
	check( $children[3]['settings']['archive_classic_masonry'] === 'no', 'search masonry still conflicts with grid' );
	check( $children[4]['settings']['css_classes'] === 'ch-search-recovery', 'empty recovery missing' );
	check( $children[5]['settings']['editor'] === '<p>یادداشت سردبیر</p>', 'editor widget changed' );
	check( ! upgrade( 102, 'search-results' ), 'search upgrade repeated' );

	$article = array( box( 'ch-main', array(
		box( 'ch-section ch-article-header', array( widget( 'theme-post-title' ) ) ),
		box( 'ch-section ch-article-section', array( widget( 'theme-post-content' ) ) ),
	) ) );
	fixture( 105, $article );
	check( upgrade( 105, 'post-single' ), 'expected article next step' );
	$after = json_decode( $GLOBALS['post_meta'][105]['_elementor_data'], true );
	$next = $after[0]['elements'][2];
	check( $next['settings']['css_classes'] === 'ch-section ch-article-next', 'article next step missing' );
	check( $next['elements'][0]['settings']['title'] === 'برای مطالعهٔ بیشتر', 'article heading is too specific' );
	check( $next['elements'][1]['settings']['posts_query_id'] === 'chidemoon_related_posts', 'related query missing' );
	check( ! upgrade( 105, 'post-single' ), 'article next step repeated' );

	$products = array( box( 'ch-main', array( box( 'ch-section ch-product-archive', array(
		widget( 'theme-archive-title' ),
		widget( 'woocommerce-archive-description' ),
		widget( 'woocommerce-archive-products', array( 'show_result_count' => 'no' ) ),
	) ) ) ) );
	fixture( 106, $products );
	check( upgrade( 106, 'product-archive' ), 'expected product archive controls' );
	$after = json_decode( $GLOBALS['post_meta'][106]['_elementor_data'], true );
	$children = $after[0]['elements'][0]['elements'];
	check( $children[0]['widgetType'] === 'woocommerce-breadcrumb', 'product archive breadcrumb missing' );
	check( $children[3]['settings']['allow_order'] === 'yes', 'native sort control missing' );
	check( $children[3]['settings']['show_result_count'] === 'no', 'explicit editor setting overwritten' );
	check( ! upgrade( 106, 'product-archive' ), 'product archive controls repeated' );

	$product = array( box( 'ch-main', array( box( 'ch-section ch-product-single', array(
		box( 'ch-product-layout', array(
			widget( 'woocommerce-product-images' ),
			box( 'ch-product-summary', array( widget( 'woocommerce-product-title' ), widget( 'chidemoon-product-offer', array( 'product_id' => '42', 'facts_gap' => array( 'size' => 12 ), 'facts_label_color' => '#123456' ) ) ) ),
		) ),
		widget( 'woocommerce-product-data-tabs', array( '_css_classes' => 'editor-custom-tabs' ) ),
		widget( 'text-editor', array( 'editor' => '<p>یادداشت ویرایشگر</p>' ) ),
	) ) ) ) );
	fixture( 107, $product );
	check( upgrade( 107, 'product-single' ), 'expected product details upgrade' );
	$after = json_decode( $GLOBALS['post_meta'][107]['_elementor_data'], true );
	$children = $after[0]['elements'][0]['elements'];
	check( count( $children ) === 4 && 'chidemoon-product-offer' === $children[1]['widgetType'], 'facts widget not inserted after product layout' );
	check( 'no' === $children[0]['elements'][1]['elements'][1]['settings']['show_facts'], 'summary still renders duplicate facts' );
	check( 'yes' === $children[1]['settings']['facts_only'] && '42' === $children[1]['settings']['product_id'], 'facts widget lost product selection' );
	check( '#123456' === $children[1]['settings']['facts_label_color'] && 12 === $children[1]['settings']['facts_gap']['size'], 'facts styling was not carried over' );
	check( 'editor-custom-tabs ch-product-tabs-widget' === $children[2]['settings']['_css_classes'], 'editor tab class was overwritten' );
	check( '<p>یادداشت ویرایشگر</p>' === $children[3]['settings']['editor'], 'editor product content changed' );
	check( ! upgrade( 107, 'product-single' ) && 1 === $GLOBALS['document_saves'][107], 'product upgrade must be idempotent' );

	$hidden_facts = array( box( 'ch-main', array( box( 'ch-section ch-product-single', array(
		box( 'ch-product-layout', array( box( 'ch-product-summary', array( widget( 'chidemoon-product-offer', array( 'show_facts' => 'no' ) ) ) ) ) ),
		widget( 'woocommerce-product-data-tabs' ),
	) ) ) ) );
	fixture( 108, $hidden_facts );
	check( upgrade( 108, 'product-single' ), 'expected tabs alignment' );
	$after = json_decode( $GLOBALS['post_meta'][108]['_elementor_data'], true );
	check( count( $after[0]['elements'][0]['elements'] ) === 2, 'explicit hidden facts preference was overridden' );
	check( ! upgrade( 108, 'product-single' ), 'hidden facts upgrade repeated' );

	$concept = array( box( '', array( widget( 'chidemoon-shop-the-look', array( 'hotspots' => array() ) ), widget( 'text-editor', array( 'editor' => '<p>متن مطلب</p>' ) ) ) ) );
	fixture( 103, $concept, false );
	check( upgrade( 103, 'conceptual-look' ), 'expected conceptual label' );
	$after = json_decode( $GLOBALS['post_meta'][103]['_elementor_data'], true );
	check( $after[0]['elements'][0]['settings']['_css_classes'] === 'ch-look-state ch-look-state-conceptual', 'conceptual label missing' );
	check( ! upgrade( 103, 'conceptual-look' ), 'conceptual label repeated' );
	$buyable = array( box( '', array( widget( 'chidemoon-shop-the-look', array( 'hotspots' => array( array( 'product_source_key' => 'real:1' ) ) ) ) ) ) );
	fixture( 104, $buyable, false );
	check( ! upgrade( 104, 'conceptual-look' ), 'buyable look mislabeled conceptual' );

	echo "Elementor UI upgrade fixtures passed\n";
}
