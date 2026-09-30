<?php
declare( strict_types=1 );

namespace Elementor {
	class Plugin {
		public static Plugin $instance;
		public object $documents;
		public object $widgets_manager;
		public function __construct() {
			$this->widgets_manager = new class {
				public function get_widget_types( string $name ): object|false { return in_array( $name, $GLOBALS['missing_widgets'], true ) ? false : (object) array( 'name' => $name ); }
			};
			$this->documents = new class {
				public function get( int $post_id, bool $edit ): object {
					return new class( $post_id ) {
						public function __construct( private int $id ) {}
						public function save( array $data ): bool {
							$GLOBALS['saves'][ $this->id ] = ( $GLOBALS['saves'][ $this->id ] ?? 0 ) + 1;
							$failure = $GLOBALS['save_failures'][ $this->id ] ?? '';
							update_post_meta( $this->id, '_elementor_data', wp_slash( wp_json_encode( $failure ? array() : $data['elements'] ) ) );
							if ( $failure ) {
								$GLOBALS['posts'][ $this->id ]->post_content = 'Bad save replaced the original body.';
								update_post_meta( $this->id, '_elementor_version', 'bad-save' );
							}
							return 'false' !== $failure;
						}
					};
				}
			};
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/../' );
	define( 'WP_CLI', true );
	define( 'OBJECT', 'OBJECT' );
	$GLOBALS['posts'] = array();
	$GLOBALS['meta'] = array();
	$GLOBALS['writes'] = 0;
	$GLOBALS['saves'] = array();
	$GLOBALS['missing_widgets'] = array();
	$GLOBALS['save_failures'] = array();
	$GLOBALS['denied_ids'] = array();
	$GLOBALS['block_fixtures'] = array();
	class WP_CLI {
		public static function log( string $message ): void {}
		public static function success( string $message ): void {}
		public static function error( string $message ): never { throw new \RuntimeException( $message ); }
	}
	class Chidemoon_Core_Shop_The_Look { public const TAXONOMY = 'room'; }
	function current_user_can( string $capability, int $post_id = 0 ): bool { return ! in_array( $post_id, $GLOBALS['denied_ids'], true ); }
	function get_post( int $id ): object|false { return $GLOBALS['posts'][ $id ] ?? false; }
	function get_post_meta( int $id, string $key, bool $single ): mixed { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
	function metadata_exists( string $type, int $id, string $key ): bool { return array_key_exists( $key, $GLOBALS['meta'][ $id ] ?? array() ); }
	function map_strings( mixed $value, callable $function ): mixed {
		if ( is_array( $value ) ) { return array_map( static fn ( $item ) => map_strings( $item, $function ), $value ); }
		return is_string( $value ) ? $function( $value ) : $value;
	}
	function wp_slash( mixed $value ): mixed { return map_strings( $value, 'addslashes' ); }
	function update_post_meta( int $id, string $key, mixed $value ): bool { ++$GLOBALS['writes']; $GLOBALS['meta'][ $id ][ $key ] = map_strings( $value, 'stripslashes' ); return true; }
	function add_post_meta( int $id, string $key, mixed $value, bool $unique ): bool {
		if ( $unique && metadata_exists( 'post', $id, $key ) ) { return false; }
		return update_post_meta( $id, $key, $value );
	}
	function delete_post_meta( int $id, string $key ): void { ++$GLOBALS['writes']; unset( $GLOBALS['meta'][ $id ][ $key ] ); }
	function wp_update_post( array $fields, bool $error = false ): int {
		++$GLOBALS['writes'];
		foreach ( $fields as $key => $value ) { if ( 'ID' !== $key ) { $GLOBALS['posts'][ $fields['ID'] ]->$key = map_strings( $value, 'stripslashes' ); } }
		return (int) $fields['ID'];
	}
	function wp_insert_post( array $fields, bool $error = false ): int {
		$id = max( array_merge( array( 1000 ), array_keys( $GLOBALS['posts'] ) ) ) + 1;
		$GLOBALS['posts'][ $id ] = (object) array_merge( $fields, array( 'ID' => $id ) );
		return $id;
	}
	function is_wp_error( mixed $value ): bool { return false; }
	function wp_json_encode( mixed $value ): string { return json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ); }
	function wp_get_attachment_url( int $id ): string { return 'https://chidemoon.test/uploads/' . $id . '.jpg'; }
	function attachment_url_to_postid( string $url ): int { return preg_match( '~/uploads/(\d+)\.jpg$~', $url, $matches ) ? (int) $matches[1] : 0; }
	function has_blocks( string $content ): bool { return isset( $GLOBALS['block_fixtures'][ $content ] ); }
	function parse_blocks( string $content ): array { return $GLOBALS['block_fixtures'][ $content ]; }
	function esc_html( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
	function get_page_by_path( string $slug, string $output, string $type ): object|false {
		foreach ( $GLOBALS['posts'] as $post ) { if ( ( $post->post_name ?? '' ) === $slug ) { return $post; } }
		return false;
	}
	function get_category_by_slug( string $slug ): object { return (object) array( 'term_id' => 7 ); }
	function set_post_thumbnail( int $id, int $image ): void { $GLOBALS['thumbnails'][ $id ] = $image; }
	function get_posts( array $query ): array {
		if ( 'attachment' === ( $query['post_type'] ?? '' ) ) { return array( 100 + array_search( $query['meta_value'], $GLOBALS['seed_files'], true ) ); }
		return array();
	}
	function wp_set_post_tags( int $id, array $tags, bool $append ): void { $GLOBALS['tags'][ $id ] = $tags; }
	function wp_set_object_terms( int $id, string $room, string $taxonomy ): void { $GLOBALS['rooms'][ $id ] = $room; }
	function check( bool $condition, string $message ): void { if ( ! $condition ) { throw new \RuntimeException( $message ); } }
	function fixture( int $id, string $body, string $type = 'post', ?array $native = null ): void {
		$GLOBALS['posts'][ $id ] = (object) array( 'ID' => $id, 'post_type' => $type, 'post_status' => 'publish', 'post_content' => $body, 'post_excerpt' => 'خلاصهٔ سردبیر', 'post_title' => 'عنوان سردبیر' );
		if ( null !== $native ) { $GLOBALS['meta'][ $id ]['_elementor_data'] = wp_json_encode( $native ); $GLOBALS['meta'][ $id ]['_elementor_edit_mode'] = 'builder'; }
	}
	function text_widget( string $id, string $html, array $settings = array() ): array {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array_merge( array( 'editor' => $html ), $settings ), 'elements' => array() );
	}
	function widgets( array $elements ): array {
		$all = array();
		foreach ( $elements as $element ) {
			if ( 'widget' === $element['elType'] ) { $all[] = $element; }
			$all = array_merge( $all, widgets( $element['elements'] ?? array() ) );
		}
		return $all;
	}
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-elementor-content.php';

	$body = '<p>مقدمه با <strong>تأکید</strong> و <a href="/guides/">لینک</a>.</p><h2 id="choice">انتخاب <em>آگاهانه</em></h2><p>متن سردبیر: C:\\draft و «نقل قول».</p><ol start="3"><li>اول</li><li>دوم<ul><li>جزئیات</li></ul></li></ol>';
	fixture( 1, $body );
	$before_writes = $GLOBALS['writes'];
	$plan = Chidemoon_Core_Elementor_Content::upgrade( 1 );
	check( 'planned' === $plan['status'] && 4 === $plan['widget_count'], 'Editorial body should become four native widgets.' );
	check( $GLOBALS['writes'] === $before_writes && empty( $GLOBALS['saves'] ), 'Dry run must not write metadata or save a document.' );
	$nodes = widgets( $plan['elements'] );
	check( 'heading' === $nodes[1]['widgetType'] && 'h2' === $nodes[1]['settings']['header_size'] && 'choice' === $nodes[1]['settings']['_element_id'], 'Heading type, level and anchor must remain editable.' );
	check( str_contains( $nodes[0]['settings']['editor'], 'href="/guides/"' ) && str_contains( $nodes[3]['settings']['editor'], 'start="3"' ), 'Links and ordered-list numbering were lost.' );
	$applied = Chidemoon_Core_Elementor_Content::upgrade( 1, true );
	check( 'updated' === $applied['status'], 'Applying supported body failed: ' . $applied['message'] );
	check( $GLOBALS['posts'][1]->post_content === $body && 'خلاصهٔ سردبیر' === $GLOBALS['posts'][1]->post_excerpt, 'Migration changed editorial source or excerpt.' );
	$backup = get_post_meta( 1, Chidemoon_Core_Elementor_Content::BACKUP_META, true );
	check( $backup['post_content'] === $body && ! $backup['meta']['_elementor_data']['exists'], 'Backup must retain exact original slashes and metadata absence.' );
	$writes = $GLOBALS['writes'];
	check( 'unchanged' === Chidemoon_Core_Elementor_Content::upgrade( 1, true )['status'] && $writes === $GLOBALS['writes'] && 1 === $GLOBALS['saves'][1], 'Repeated conversion must have no writes.' );

	$look = array( 'id' => 'editor-look', 'elType' => 'widget', 'widgetType' => 'chidemoon-shop-the-look', 'settings' => array( 'image' => array( 'id' => 99, 'url' => wp_get_attachment_url( 99 ) ), 'caption' => 'متن ویرایش‌شده', 'hotspots' => array( array( '_id' => 'one', 'product_id' => '42', 'product_source_key' => 'basalam:42', 'label' => 'چراغ', 'x' => array( 'size' => 17 ), 'y' => array( 'size' => 31 ) ) ) ), 'elements' => array() );
	$native = array( array( 'id' => 'editor-container', 'elType' => 'container', 'settings' => array( 'css_classes' => 'my-layout', 'flex_direction' => 'row' ), 'elements' => array( $look, text_widget( 'editor-body', '<h2>عنوان فعلی</h2><p>بدنهٔ ویرایش‌شده</p>' ), text_widget( 'styled-body', '<h2>تایپوگرافی سفارشی</h2><p>این ظاهر حفظ شود</p>', array( 'typography_font_family' => 'Yekan Bakh' ) ) ) ) );
	fixture( 2, '<p>متن قدیمی که نباید برگردد</p>', 'post', $native );
	$raw_before = $GLOBALS['meta'][2]['_elementor_data'];
	check( 'updated' === Chidemoon_Core_Elementor_Content::upgrade( 2, true )['status'], 'Native look body was not upgraded.' );
	$after = json_decode( $GLOBALS['meta'][2]['_elementor_data'], true, 512, JSON_THROW_ON_ERROR );
	check( $after[0]['settings'] === $native[0]['settings'] && $after[0]['elements'][0] === $look, 'Existing room layout, image or hotspot data changed.' );
	check( $after[0]['elements'][2] === $native[0]['elements'][2], 'Editor-owned native typography changed.' );
	check( ! str_contains( $GLOBALS['meta'][2]['_elementor_data'], 'متن قدیمی' ) && str_contains( $GLOBALS['meta'][2]['_elementor_data'], 'بدنهٔ ویرایش‌شده' ), 'Stale post_content replaced the native editorial edits.' );
	check( get_post_meta( 2, Chidemoon_Core_Elementor_Content::BACKUP_META, true )['meta']['_elementor_data']['value'] === $raw_before, 'Existing native JSON backup is not exact.' );
	check( 'unchanged' === Chidemoon_Core_Elementor_Content::upgrade( 2, true )['status'], 'Native look conversion is not idempotent.' );

	$GLOBALS['meta'][99]['_wp_attachment_image_alt'] = 'متن رسانهٔ مشترک';
	$images = Chidemoon_Core_Elementor_Content::convert_html( '<figure class="wp-block-image"><img class="wp-image-99" src="https://chidemoon.test/uploads/99.jpg" alt="متن مخصوص این مقاله"><figcaption>زیرنویس <em>قابل ویرایش</em></figcaption></figure>', 'image-case' );
	$image_widgets = widgets( $images );
	check( 'image' === $image_widgets[0]['widgetType'] && '' === $image_widgets[0]['settings']['image']['id'] && 'متن مخصوص این مقاله' === $image_widgets[0]['settings']['image']['alt'], 'Per-use image alt must not change to shared media alt.' );
	check( str_contains( $image_widgets[1]['settings']['editor'], 'قابل ویرایش' ) && 'متن رسانهٔ مشترک' === $GLOBALS['meta'][99]['_wp_attachment_image_alt'], 'Caption or shared attachment metadata changed.' );
	$linked_image = widgets( Chidemoon_Core_Elementor_Content::convert_html( '<p><a href="/products/lamp/" target="_blank" rel="nofollow sponsored"><img src="/uploads/lamp.jpg" alt="چراغ"></a></p>', 'linked-image' ) )[0];
	check( 'custom' === $linked_image['settings']['link_to'] && '/products/lamp/' === $linked_image['settings']['link']['url'] && true === $linked_image['settings']['link']['is_external'] && true === $linked_image['settings']['link']['nofollow'], 'Native image conversion lost its product link or target.' );

	foreach ( array( '<p>متن</p>[gallery ids="99"]', '<iframe src="https://example.test"></iframe>', '<h2 style="color:red">رنگ سفارشی</h2>', '<figure><img src="/image.jpg" alt=""><p>متن غیر از زیرنویس</p></figure>' ) as $i => $html ) {
		fixture( 10 + $i, $html );
		$writes = $GLOBALS['writes'];
		check( 'manual' === Chidemoon_Core_Elementor_Content::upgrade( 10 + $i, true )['status'] && $writes === $GLOBALS['writes'], 'Unsupported body must stay unchanged.' );
	}
	fixture( 20, '<p>بدنهٔ قدیمی</p>', 'post', array() );
	check( 'unchanged' === Chidemoon_Core_Elementor_Content::upgrade( 20, true )['status'], 'Intentionally cleared native canvas was resurrected.' );
	fixture( 21, '<p>بدنه</p>' );
	$GLOBALS['meta'][21]['_elementor_data'] = '{broken';
	check( 'manual' === Chidemoon_Core_Elementor_Content::upgrade( 21, true )['status'] && '{broken' === $GLOBALS['meta'][21]['_elementor_data'], 'Malformed native data should be preserved.' );

	$gutenberg = '<!-- wp:fixture nested lists and look -->';
	$GLOBALS['block_fixtures'][ $gutenberg ] = array(
		array( 'blockName' => 'core/list', 'attrs' => array(), 'innerHTML' => '<ul></ul>', 'innerContent' => array( '<ul>', null, '</ul>' ), 'innerBlocks' => array( array( 'blockName' => 'core/list-item', 'innerHTML' => '<li>عنصر <strong>سردبیر</strong></li>', 'innerContent' => array( '<li>عنصر <strong>سردبیر</strong></li>' ), 'innerBlocks' => array() ) ) ),
		array( 'blockName' => 'chidemoon/shop-the-look', 'attrs' => array( 'imageId' => 99, 'imageAlt' => 'تصویر فعلی', 'caption' => 'شرح فعلی', 'hotspots' => array( array( 'productId' => 42, 'productSourceKey' => 'basalam:42', 'label' => 'چراغ', 'x' => 23, 'y' => 45 ) ) ) ),
	);
	fixture( 30, $gutenberg );
	$block_plan = Chidemoon_Core_Elementor_Content::plan( 30 );
	check( 'planned' === $block_plan['status'], 'Supported Gutenberg block body was not converted.' );
	$block_widgets = widgets( $block_plan['elements'] );
	check( str_contains( $block_widgets[0]['settings']['editor'], 'عنصر <strong>سردبیر</strong>' ), 'Gutenberg inner list item content was lost.' );
	check( 'chidemoon-shop-the-look' === $block_widgets[1]['widgetType'] && '42' === $block_widgets[1]['settings']['hotspots'][0]['product_id'] && 23.0 === $block_widgets[1]['settings']['hotspots'][0]['x']['size'], 'Gutenberg look hotspot mapping failed.' );
	$GLOBALS['block_fixtures']['<!-- wp:unsupported -->'] = array( array( 'blockName' => 'core/embed', 'attrs' => array(), 'innerHTML' => '<div>Keep me</div>' ) );
	fixture( 31, '<!-- wp:unsupported -->' );
	check( 'manual' === Chidemoon_Core_Elementor_Content::upgrade( 31, true )['status'], 'Unsupported Gutenberg block should not be stripped.' );
	$GLOBALS['block_fixtures']['<!-- wp:unsupported nested -->'] = array( array( 'blockName' => 'core/list', 'innerHTML' => '<ul></ul>', 'innerContent' => array( '<ul>', null, '</ul>' ), 'innerBlocks' => array( array( 'blockName' => 'custom/dynamic', 'innerHTML' => '', 'innerBlocks' => array() ) ) ) );
	fixture( 32, '<!-- wp:unsupported nested -->' );
	check( 'manual' === Chidemoon_Core_Elementor_Content::upgrade( 32, true )['status'], 'An unsupported nested dynamic block should not be silently discarded.' );

	fixture( 40, '<h2>شرح محصول</h2><p>متن کامل محصول</p>', 'product' );
	check( 'updated' === Chidemoon_Core_Elementor_Content::upgrade( 40, true )['status'] && 'خلاصهٔ سردبیر' === $GLOBALS['posts'][40]->post_excerpt, 'Product full-description conversion changed short description.' );
	fixture( 41, '<p>قابل ویرایش</p>' );
	$GLOBALS['denied_ids'][] = 41;
	$writes = $GLOBALS['writes'];
	check( 'error' === Chidemoon_Core_Elementor_Content::upgrade( 41, true )['status'] && $writes === $GLOBALS['writes'], 'Denied post edit permission must stop all writes.' );

	foreach ( array( 'false', 'corrupt' ) as $index => $failure ) {
		$id = 50 + $index;
		fixture( $id, $body );
		$GLOBALS['save_failures'][ $id ] = $failure;
		$result = Chidemoon_Core_Elementor_Content::upgrade( $id, true );
		check( 'error' === $result['status'] && $body === $GLOBALS['posts'][ $id ]->post_content, 'Failed native save must restore exact post body.' );
		check( ! metadata_exists( 'post', $id, '_elementor_data' ) && ! metadata_exists( 'post', $id, '_elementor_edit_mode' ) && ! metadata_exists( 'post', $id, '_elementor_version' ), 'Failed save left partial Elementor metadata.' );
		check( $body === get_post_meta( $id, Chidemoon_Core_Elementor_Content::BACKUP_META, true )['post_content'], 'Failed save must retain recovery backup.' );
	}
	fixture( 52, '<h2>عنوان</h2><p>متن</p>' );
	$GLOBALS['missing_widgets'] = array( 'heading' );
	$writes = $GLOBALS['writes'];
	check( 'error' === Chidemoon_Core_Elementor_Content::upgrade( 52, true )['status'] && $writes === $GLOBALS['writes'], 'Missing native widget should fail before changing metadata.' );
	$GLOBALS['missing_widgets'] = array();

	// The seed workflow must create guides and comparison articles as native documents too.
	$GLOBALS['seed_files'] = array( 'look-reading-corner.jpg', 'look-compact-home-office.jpg', 'look-cozy-dining-corner.jpg', 'look-calm-green-bedroom.jpg', 'look-japandi-dining.jpg', 'look-work-nook-bookshelf.jpg' );
	$args = array();
	require __DIR__ . '/../tools/rebuild-editorial.php';
	$seed_ids = array_keys( array_filter( $GLOBALS['posts'], static fn ( $post ) => ! empty( $post->post_name ) ) );
	check( 6 === count( $seed_ids ), 'Initial editorial collection did not create six articles.' );
	foreach ( $seed_ids as $id ) {
		$seed_widgets = widgets( json_decode( $GLOBALS['meta'][ $id ]['_elementor_data'], true, 512, JSON_THROW_ON_ERROR ) );
		check( 'publish' === $GLOBALS['posts'][ $id ]->post_status && 'builder' === $GLOBALS['meta'][ $id ]['_elementor_edit_mode'], 'Seed was published without a saved native document.' );
		check( 3 === count( array_filter( $seed_widgets, static fn ( $widget ) => 'heading' === $widget['widgetType'] ) ), 'Seed headings are not separate native heading widgets.' );
		check( 4 === count( array_filter( $seed_widgets, static fn ( $widget ) => 'text-editor' === $widget['widgetType'] ) ), 'Seed paragraphs are not separate native text widgets.' );
	}
	echo "Native editorial content conversion fixtures passed.\n";
}
