<?php
/**
 * Upgrade managed documents to native Loop templates without resetting content.
 * wp --user=<admin> eval-file /tools/elementor-editability-upgrade.php [apply]
 */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

final class Chidemoon_Elementor_Editability_Upgrade {
	private bool $apply;
	private array $templates = array();
	private int $sequence = 0;
	private array $used_ids = array();
	private int $changed = 0;
	private const BACKUP = '_chidemoon_pre_editability_20260930';

	public function __construct( bool $apply ) { $this->apply = $apply; }

	private function id(): string {
		do {
			$id = substr( md5( 'chidemoon-native-loop-20260930-' . ++$this->sequence ), 0, 8 );
		} while ( isset( $this->used_ids[ $id ] ) );
		$this->used_ids[ $id ] = true;
		return $id;
	}

	private function remember_ids( array $elements ): void {
		foreach ( $elements as $element ) {
			if ( ! empty( $element['id'] ) ) { $this->used_ids[ $element['id'] ] = true; }
			$this->remember_ids( $element['elements'] ?? array() );
		}
	}

	private function widget( string $type, array $settings = array() ): array {
		if ( ! \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $type ) ) {
			WP_CLI::error( 'Missing editable widget: ' . $type );
		}
		return array( 'id' => $this->id(), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}

	private function box( array $children, string $class = '', array $settings = array() ): array {
		return array( 'id' => $this->id(), 'elType' => 'container', 'isInner' => false, 'settings' => array_merge( array( 'content_width' => 'full', 'flex_direction' => 'column', 'css_classes' => $class, 'flex_gap' => array( 'column' => '12', 'row' => '12', 'unit' => 'px', 'size' => 12, 'isLinked' => true ) ), $settings ), 'elements' => $children );
	}

	private function tag( string $name, array $settings = array() ): string {
		return '[elementor-tag id="' . $this->id() . '" name="' . $name . '" settings="' . rawurlencode( wp_json_encode( $settings, JSON_UNESCAPED_UNICODE ) ) . '"]';
	}

	private function heading( string $title, string $tag = 'h2' ): array {
		return $this->widget( 'heading', array( 'title' => $title, 'header_size' => $tag ) );
	}

	private function text( string $html ): array { return $this->widget( 'text-editor', array( 'editor' => $html ) ); }

	private function button( string $text, string $path ): array {
		return $this->widget( 'button', array( 'text' => $text, 'link' => array( 'url' => home_url( $path ) ), 'align' => 'right' ) );
	}

	private function card( string $kind ): array {
		$term = str_contains( $kind, 'taxonomy' );
		$url = $this->tag( $term ? 'archive-url' : 'post-url' );
		$media = $this->widget( 'image', array( 'image_size' => 'large', 'link_to' => 'custom', '_css_classes' => 'ch-native-card-media', '__dynamic__' => array( 'image' => $this->tag( 'product_taxonomy' === $kind ? 'woocommerce-category-image-tag' : 'post-featured-image' ), 'link' => $url ) ) );
		$title = $this->widget( 'heading', array( 'header_size' => 'h2', 'typography_typography' => 'custom', 'typography_font_size' => array( 'unit' => 'px', 'size' => $term ? 16 : 20 ), 'typography_line_height' => array( 'unit' => 'em', 'size' => 1.6 ), 'typography_font_weight' => '700', '_css_classes' => 'ch-native-card-title', '__dynamic__' => array( 'title' => $this->tag( $term ? 'archive-title' : 'post-title', $term ? array( 'include_context' => 'no' ) : array() ), 'link' => $url ) ) );
		$body = array();
		if ( ! $term ) {
			$body[] = $this->widget( 'heading', array( 'header_size' => 'div', 'typography_typography' => 'custom', 'typography_font_size' => array( 'unit' => 'px', 'size' => 12 ), 'typography_font_weight' => '600', '_css_classes' => 'ch-native-card-kind', '__dynamic__' => array( 'title' => $this->tag( 'chidemoon-content-label' ) ) ) );
		}
		$body[] = $title;
		if ( 'product' === $kind || 'search' === $kind ) {
			$body[] = $this->widget( 'heading', array( 'header_size' => 'div', '_css_classes' => 'ch-native-card-price', '__dynamic__' => array( 'title' => $this->tag( 'chidemoon-product-field', array( 'field' => 'price' ) ) ) ) );
			$body[] = $this->widget( 'text-editor', array( '_css_classes' => 'ch-native-card-merchant', '__dynamic__' => array( 'editor' => $this->tag( 'chidemoon-product-field', array( 'field' => 'merchant', 'merchant_label' => 'فروشنده:' ) ) ) ) );
		}
		if ( ! $term && 'product' !== $kind ) {
			$body[] = $this->widget( 'text-editor', array( '_css_classes' => 'ch-native-card-excerpt', '__dynamic__' => array( 'editor' => $this->tag( 'post-excerpt', array( 'max_length' => 24, 'apply_to_post_content' => 'yes' ) ) ) ) );
		}
		if ( 'product' === $kind ) {
			$body[] = $this->widget( 'chidemoon-product-actions', array( 'show_purchase' => 'no', 'show_compare' => 'yes', 'direction' => 'column' ) );
		} else {
			$body[] = $this->widget( 'button', array( 'text' => $term ? 'دیدن دسته‌بندی' : ( 'search' === $kind ? 'مشاهدهٔ نتیجه' : 'خواندن مطلب' ), 'align' => 'right', '_css_classes' => 'ch-native-card-action', '__dynamic__' => array( 'link' => $url ) ) );
		}
		$children = 'post_taxonomy' === $kind ? array() : array( $media );
		$children[] = $this->box( $body, 'ch-native-card-body', array( 'padding' => array( 'unit' => 'px', 'top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'isLinked' => true ) ) );
		return array( $this->box( $children, 'ch-native-card ch-native-card--' . $kind, array( 'html_tag' => 'article', 'background_background' => 'classic', 'background_color' => '#ffffff', 'padding' => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ), 'border_border' => 'solid', 'border_width' => array( 'unit' => 'px', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ), 'border_color' => '#dce4dd', 'border_radius' => array( 'unit' => 'px', 'top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'isLinked' => true ), 'flex_gap' => array( 'unit' => 'px', 'column' => '0', 'row' => '0', 'size' => 0, 'isLinked' => true ) ) ) );
	}

	private function create_template( string $slug, string $title, string $type, array $elements, string $source = '' ): int {
		$existing = get_page_by_path( 'chidemoon-' . $slug, OBJECT, 'elementor_library' );
		if ( $existing ) {
			try {
				$stored = $this->read_elements( (int) $existing->ID );
				$this->validate_elements( $stored );
				if ( 'publish' !== $existing->post_status || 'builder' !== get_post_meta( $existing->ID, '_elementor_edit_mode', true ) || $type !== get_post_meta( $existing->ID, '_elementor_template_type', true ) ) {
					throw new RuntimeException( 'Existing template is unpublished or has incompatible Elementor metadata.' );
				}
				if ( $source ) {
					$settings = (array) get_post_meta( $existing->ID, '_elementor_page_settings', true );
					$document = \Elementor\Plugin::$instance->documents->get( $existing->ID, false );
					$actual = $document && method_exists( $document, 'get_settings' ) ? $document->get_settings( 'source' ) : ( $settings['source'] ?? '' );
					if ( $source !== ( $actual ?: 'post' ) ) { throw new RuntimeException( 'Existing Loop template uses a different data source.' ); }
				}
			} catch ( Throwable $exception ) {
				WP_CLI::error( 'Preserved existing template #' . $existing->ID . ': ' . $exception->getMessage() . ' Review it in Elementor before retrying.' );
			}
			$this->templates[ $slug ] = (int) $existing->ID;
			return (int) $existing->ID;
		}
		WP_CLI::log( ( $this->apply ? 'Creating ' : 'Would create ' ) . $title );
		if ( ! $this->apply ) {
			$this->templates[ $slug ] = 0;
			return 0;
		}
		$document = \Elementor\Plugin::$instance->documents->create( $type, array( 'post_status' => 'draft', 'post_name' => 'chidemoon-' . $slug, 'post_title' => 'چیدمون | ' . $title ) );
		if ( is_wp_error( $document ) || ! $document ) { WP_CLI::error( 'Could not create ' . $slug ); }
		$id = (int) $document->get_main_id();
		if ( ! $id || ! get_post( $id ) ) { WP_CLI::error( 'Could not load newly created template ' . $slug ); }
		$snapshot = $this->snapshot( $id );
		try {
			update_post_meta( $id, '_elementor_template_type', $type );
			$terms = wp_set_object_terms( $id, $type, 'elementor_library_type' );
			if ( is_wp_error( $terms ) ) { throw new RuntimeException( $terms->get_error_message() ); }
			$this->save( $id, $elements, array( 'native template' ), $source ? array( 'source' => $source ) : array(), $document, true );
			$this->publish_document( $id );
		} catch ( Throwable $exception ) {
			try { $this->restore_snapshot( $id, $snapshot ); }
			catch ( Throwable $rollback ) { $exception = new RuntimeException( $exception->getMessage() . ' Rollback failed: ' . $rollback->getMessage() ); }
			WP_CLI::error( 'Could not create ' . $slug . ': ' . $exception->getMessage() . '; original draft retained.' );
		}
		$this->templates[ $slug ] = $id;
		return $id;
	}

	private function grid( string $kind, array $settings = array() ): array {
		$slug = array( 'post' => 'article-card', 'product' => 'product-card', 'search' => 'search-card', 'post_taxonomy' => 'post-category-card', 'product_taxonomy' => 'product-category-card' )[ $kind ];
		$skin = 'search' === $kind ? 'post' : $kind;
		return $this->widget( 'loop-grid', array_merge( array( '_skin' => $skin, 'template_id' => (string) ( $this->templates[ $slug ] ?? 0 ), 'columns' => '3', 'columns_tablet' => '2', 'columns_mobile' => '1', 'posts_per_page' => 12, 'masonry' => '', 'equal_height' => 'yes', 'pagination_type' => 'numbers', 'pagination_load_type' => 'page_reload', 'enable_nothing_found_message' => 'yes', 'nothing_found_message_text' => 'هنوز موردی در این بخش منتشر نشده است.', '_css_classes' => 'ch-native-feed' ), $settings ) );
	}

	private function setup_templates(): void {
		foreach ( array( 'article-card' => array( 'کارت مقاله و ایدهٔ چیدمان', 'post' ), 'product-card' => array( 'کارت محصول', 'product' ), 'search-card' => array( 'کارت نتیجهٔ جست‌وجو', 'search' ), 'product-category-card' => array( 'کارت دستهٔ محصول', 'product_taxonomy' ), 'post-category-card' => array( 'کارت دستهٔ مطلب', 'post_taxonomy' ) ) as $slug => $item ) {
			$this->create_template( $slug, $item[0], 'loop-item', $this->card( $item[1] ), 'search' === $item[1] ? 'post' : $item[1] );
		}
		$category_elements = array( $this->box( array(
			$this->heading( 'دسته‌های محصولات' ),
			$this->grid( 'product_taxonomy', array( 'product_taxonomy_query_post_type' => 'product_cat', 'product_taxonomy_query_filter_by' => 'show_all', 'product_taxonomy_hide_empty' => 'yes', 'product_taxonomy_orderby' => 'name', 'product_taxonomy_order' => 'ASC', 'pagination_type' => '', 'columns' => '3', 'posts_per_page' => 100 ) ),
			$this->heading( 'مطالب و ایده‌ها' ),
			$this->grid( 'post_taxonomy', array( 'post_taxonomy_query_post_type' => 'category', 'post_taxonomy_query_filter_by' => 'show_all', 'post_taxonomy_hide_empty' => 'yes', 'post_taxonomy_orderby' => 'name', 'post_taxonomy_order' => 'ASC', 'pagination_type' => '', 'columns' => '3', 'posts_per_page' => 100 ) ),
		), 'ch-category-panel' ) );
		$this->create_template( 'category-cards', 'کارت‌های دسته‌بندی', 'section', $category_elements );
	}

	private function template_widget(): array {
		return $this->widget( 'template', array( 'template_id' => (string) ( $this->templates['category-cards'] ?? 0 ) ) );
	}

	private function page( string $slug, string $title, array $elements ): void {
		$existing = get_page_by_path( $slug );
		if ( $existing && 'builder' === get_post_meta( $existing->ID, '_elementor_edit_mode', true ) ) {
			if ( 'product-comparison' === $slug ) {
				$contains_compare = static function ( array $tree ) use ( &$contains_compare ): bool {
					foreach ( $tree as $element ) {
						if ( 'chidemoon-compare-table' === ( $element['widgetType'] ?? '' ) || $contains_compare( $element['elements'] ?? array() ) ) { return true; }
					}
					return false;
				};
				if ( ! $contains_compare( $this->read_elements( (int) $existing->ID ) ) ) { WP_CLI::error( 'Preserved existing /product-comparison/ page: add the Chidemoon comparison widget in Elementor before retrying.' ); }
			}
			return;
		}
		$raw = $existing ? get_post_meta( $existing->ID, '_elementor_data', true ) : '';
		if ( $existing && ( trim( $existing->post_content ) || ( '' !== $raw && false !== $raw && null !== $raw ) ) ) {
			WP_CLI::warning( 'Preserved existing page content: ' . $slug );
			return;
		}
		WP_CLI::log( ( $this->apply ? 'Creating page ' : 'Would create page ' ) . $slug );
		if ( ! $this->apply ) { return; }
		$id = $existing ? (int) $existing->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_name' => $slug, 'post_title' => $title ), true );
		if ( is_wp_error( $id ) || ! $id ) { WP_CLI::error( is_wp_error( $id ) ? $id->get_error_message() : 'Could not create page ' . $slug ); }
		$snapshot = $this->snapshot( (int) $id );
		try {
			$this->save( (int) $id, $elements, array( 'native page' ), array(), null, true );
			update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
			if ( 'elementor_header_footer' !== get_post_meta( $id, '_wp_page_template', true ) ) { throw new RuntimeException( 'Could not save the Elementor page layout.' ); }
			if ( ! $existing ) { $this->publish_document( (int) $id ); }
		} catch ( Throwable $exception ) {
			try { $this->restore_snapshot( (int) $id, $snapshot ); }
			catch ( Throwable $rollback ) { $exception = new RuntimeException( $exception->getMessage() . ' Rollback failed: ' . $rollback->getMessage() ); }
			WP_CLI::error( 'Could not prepare page ' . $slug . ': ' . $exception->getMessage() . '; original state retained.' );
		}
	}

	private function has_class( array $element, string $class ): bool {
		return in_array( $class, preg_split( '/\s+/', (string) ( $element['settings']['css_classes'] ?? $element['settings']['_css_classes'] ?? '' ) ), true );
	}

	private function convert_feed( array $element, string $target ): array {
		$type = $element['widgetType'];
		$old = $element['settings'] ?? array();
		$product = in_array( $type, array( 'woocommerce-archive-products', 'woocommerce-product-related' ), true );
		$kind = $product ? 'product' : ( 'search-results' === $target ? 'search' : 'post' );
		// Current Query retains the native search/category/archive filters and pagination.
		$settings = array( 'post_query_post_type' => 'post', 'nothing_found_message_text' => $old['nothing_found_message'] ?? 'هنوز مطلبی در این بخش منتشر نشده است.' );
		if ( in_array( $type, array( 'archive-posts', 'woocommerce-archive-products' ), true ) ) {
			$settings[ $product ? 'product_query_post_type' : 'post_query_post_type' ] = 'current_query';
		}
		if ( 'woocommerce-product-related' === $type ) {
			$settings['_skin'] = 'post';
			$settings['post_query_post_type'] = 'product';
			$settings['post_query_query_id'] = 'chidemoon_related_products';
			$settings['posts_per_page'] = $old['posts_per_page'] ?? 4;
		}
		if ( 'posts' === $type ) {
			foreach ( $old as $key => $value ) {
				if ( str_starts_with( $key, 'posts_' ) && ! in_array( $key, array( 'posts_per_page' ), true ) ) {
					$settings[ 'post_query_' . substr( $key, 6 ) ] = $value;
				}
			}
			$settings['posts_per_page'] = $old['classic_posts_per_page'] ?? 6;
		}
		$prefix = 'archive-posts' === $type ? 'archive_classic_' : 'classic_';
		foreach ( array( '', '_tablet', '_mobile' ) as $suffix ) {
			$key = 'columns' . $suffix;
			$settings[ $key ] = $old[ $product ? $key : $prefix . $key ] ?? ( '' === $suffix ? '3' : ( '_tablet' === $suffix ? '2' : '1' ) );
		}
		foreach ( array( 'pagination_type', 'pagination_prev_label', 'pagination_next_label' ) as $key ) {
			if ( array_key_exists( $key, $old ) ) { $settings[ $key ] = $old[ $key ]; }
		}
		foreach ( $old as $key => $value ) {
			if ( str_starts_with( $key, '_' ) && ! in_array( $key, array( '_skin', '_css_classes' ), true ) ) { $settings[ $key ] = $value; }
		}
		$settings['_css_classes'] = trim( (string) ( $old['_css_classes'] ?? '' ) . ' ch-native-feed' );
		$new = $this->grid( $kind, $settings );
		$new['id'] = $element['id'];
		return $new;
	}

	private function menu_items( int $menu_id ): array {
		$items = array();
		$items[] = array( '_id' => $this->id(), 'item_title' => 'دسته‌بندی', 'item_link' => array( 'url' => home_url( '/categories/' ) ), 'item_dropdown_content' => 'yes' );
		foreach ( wp_get_nav_menu_items( $menu_id ) ?: array() as $item ) {
			if ( ! empty( $item->menu_item_parent ) ) {
				WP_CLI::warning( 'Preserved hierarchical WordPress menu. Edit its links under Appearance > Menus and its layout in Elementor.' );
				return array();
			}
			$items[] = array( '_id' => $this->id(), 'item_title' => $item->title, 'item_link' => array( 'url' => $item->url, 'is_external' => '_blank' === ( $item->target ?? '' ) ? 'on' : '', 'nofollow' => in_array( 'nofollow', preg_split( '/\s+/', (string) ( $item->xfn ?? '' ) ), true ) ? 'on' : '', 'custom_attributes' => ! empty( $item->xfn ) ? 'rel|' . sanitize_text_field( $item->xfn ) : '' ), 'item_dropdown_content' => 'no' );
		}
		return $items;
	}

	private function patch( array &$elements, string $target, array &$changes ): void {
		// These visible controls live in the document, so the editor sees the same UI.
		if ( in_array( $target, array( 'search-results', 'not-found' ), true ) ) {
			$has_caption = false;
			foreach ( $elements as $child ) { $has_caption = $has_caption || $this->has_class( $child, 'ch-page-search-caption' ); }
			if ( ! $has_caption ) {
				foreach ( $elements as $index => $child ) {
					if ( 'search' === ( $child['widgetType'] ?? '' ) && $this->has_class( $child, 'ch-page-search' ) ) {
						$caption = $this->heading( 'جست‌وجو در محصولات و مطالب', 'h2' );
						$caption['settings']['_css_classes'] = 'ch-page-search-caption';
						array_splice( $elements, $index, 0, array( $caption ) );
						$changes[] = 'editable search caption';
						break;
					}
				}
			}
		}
		if ( 'product-archive' === $target ) {
			$has_tools = false;
			foreach ( $elements as $child ) { $has_tools = $has_tools || 'chidemoon-catalog-tools' === ( $child['widgetType'] ?? '' ); }
			if ( ! $has_tools ) {
				foreach ( $elements as $index => $child ) {
					if ( 'woocommerce-archive-products' === ( $child['widgetType'] ?? '' ) || ( 'loop-grid' === ( $child['widgetType'] ?? '' ) && $this->has_class( $child, 'ch-product-grid' ) ) ) {
						array_splice( $elements, $index, 0, array( $this->widget( 'chidemoon-catalog-tools' ) ) );
						$changes[] = 'editable catalog ordering and result count';
						break;
					}
				}
			}
		}
		foreach ( $elements as &$element ) {
			$type = $element['widgetType'] ?? '';
			$settings = &$element['settings'];
			if ( 'shop-the-look' === $target && $this->has_class( $element, 'ch-look-feature' ) ) {
				$children = &$element['elements'];
				$has_filters = false;
				foreach ( $children as $child ) { $has_filters = $has_filters || 'chidemoon-room-filters' === ( $child['widgetType'] ?? '' ); }
				if ( ! $has_filters ) {
					foreach ( $children as $index => $child ) {
						if ( 'heading' === ( $child['widgetType'] ?? '' ) && 'h1' === ( $child['settings']['header_size'] ?? '' ) ) {
							array_splice( $children, $index + 1, 0, array( $this->widget( 'chidemoon-room-filters', array( '_css_classes' => 'ch-room-filters-quick-widget', 'nav_id' => 'ch-room-filters-quick' ) ) ) );
							$changes[] = 'native quick room filter';
							break;
						}
					}
				}
				unset( $children );
			}
			if ( in_array( $type, array( 'posts', 'archive-posts', 'woocommerce-archive-products', 'woocommerce-product-related' ), true ) && ( $this->has_class( $element, 'ch-editorial-feed' ) || $this->has_class( $element, 'ch-product-grid' ) ) ) {
				$element = $this->convert_feed( $element, $target );
				$changes[] = 'editable Loop grid from ' . $type;
				$settings = &$element['settings'];
			}
			if ( 'comparisons' === $target && 'chidemoon-compare-table' === $type ) {
				$element = $this->text( '<p>مقایسه‌های تحریریه را بخوان و تفاوت گزینه‌ها را بر اساس کاربرد، اندازه و نیاز خانه‌ات بررسی کن.</p>' );
				$changes[] = 'comparison articles replace product picker';
			}
			if ( 'site-footer' === $target && 'icon-list' === $type ) {
				foreach ( $settings['icon_list'] ?? array() as $index => $item ) {
					if ( 'مقایسهٔ محصولات' === ( $item['text'] ?? '' ) && home_url( '/comparisons/' ) === ( $item['link']['url'] ?? '' ) ) {
						$settings['icon_list'][ $index ]['text'] = 'مقالات مقایسه';
						$changes[] = 'editorial comparison footer label';
					}
				}
			}
			if ( 'comparisons' === $target && 'heading' === $type && 'دو انتخاب را کنار هم ببین' === ( $settings['title'] ?? '' ) ) {
				$settings['title'] = 'مقایسه‌ها برای انتخاب بهتر';
				$changes[] = 'editorial comparison heading';
			}
			if ( 'comparisons' === $target && 'text-editor' === $type && '<p>مشخصات محصولات و اطلاعات فروشنده را کنار هم ببین؛ برای انتخاب متناسب با خانهٔ خودت.</p>' === ( $settings['editor'] ?? '' ) ) {
				$settings['editor'] = '<p>مقاله‌های مقایسهٔ چیدمون به تفاوت کاربرد، اندازه و شرایط استفاده می‌پردازند.</p>';
				$changes[] = 'editorial comparison intro';
			}
			if ( 'product-single' === $target && 'woocommerce-product-short-description' === $type ) {
				$short = $this->widget( 'text-editor', array_merge( $settings, array( '_css_classes' => trim( ( $settings['_css_classes'] ?? '' ) . ' ch-product-short-description' ), '__dynamic__' => array( 'editor' => $this->tag( 'chidemoon-product-field', array( 'field' => 'short_description' ) ) ) ) ) );
				$short['id'] = $element['id'];
				$element = $short;
				$changes[] = 'native short description with local data binding';
			}
			if ( 'product-single' === $target && 'woocommerce-product-data-tabs' === $type && $this->has_class( $element, 'ch-product-tabs-widget' ) ) {
				$extra_tabs = $this->widget( 'chidemoon-product-extra-tabs', $settings );
				$extra_tabs['id'] = $element['id'];
				$element = $this->box( array( $this->heading( 'دربارهٔ محصول' ), $this->widget( 'theme-post-content' ), $extra_tabs ), 'ch-product-description' );
				$changes[] = 'native product body with editable attributes and review tabs';
			}
			if ( 'site-header' === $target && 'nav-menu' === $type && $this->has_class( $element, 'ch-nav' ) ) {
				$items = $this->menu_items( (int) ( $settings['menu'] ?? 0 ) );
				if ( ! $items ) { continue; }
				$children = array();
				foreach ( $items as $index => $item ) { $children[] = $this->box( 0 === $index ? array( $this->template_widget() ) : array(), '', array( '_title' => $item['item_title'] ) ); }
				$element = $this->widget( 'mega-menu', array( 'menu_items' => $items, 'breakpoint_selector' => 'tablet', 'item_layout' => 'horizontal', '_css_classes' => 'ch-nav ch-mega-nav', '_element_width' => 'auto' ) );
				$element['elements'] = $children;
				$changes[] = 'native menu and editable category panel';
				$type = 'mega-menu';
				$settings = &$element['settings'];
			}
			if ( 'site-header' === $target && 'mega-menu' === $type && ( $this->has_class( $element, 'ch-nav' ) || 'a8423ff' === ( $element['id'] ?? '' ) ) ) {
				$routes = array( 'دسته‌بندی' => '/categories/', 'دسته بندی' => '/categories/', 'محصولات' => '/shop/', 'مقایسه کنید' => '/comparisons/', 'مقایسه‌ها' => '/comparisons/', 'مقایسه' => '/comparisons/', 'ایده‌های چیدمان' => '/shop-the-look/', 'راهنمای خرید' => '/guides/' );
				foreach ( $settings['menu_items'] ?? array() as $index => $item ) {
					$label = trim( (string) ( $item['item_title'] ?? '' ) );
					if ( ! isset( $routes[ $label ] ) ) { continue; }
					if ( empty( $item['item_link']['url'] ) ) {
						$settings['menu_items'][ $index ]['item_link']['url'] = home_url( $routes[ $label ] );
						$changes[] = 'menu link ' . $label;
					}
					if ( '/categories/' === $routes[ $label ] && empty( $element['elements'][ $index ]['elements'] ) ) {
						$settings['menu_items'][ $index ]['item_dropdown_content'] = 'yes';
						if ( isset( $element['elements'][ $index ] ) ) {
							$element['elements'][ $index ]['elements'] = array( $this->template_widget() );
						} else {
							$element['elements'][ $index ] = $this->box( array( $this->template_widget() ), '', array( '_title' => $label ) );
						}
						$changes[] = 'editable category cards in header';
					}
				}
				foreach ( $settings['menu_items'] ?? array() as $index => $item ) {
					if ( ! isset( $element['elements'][ $index ] ) ) { $element['elements'][ $index ] = $this->box( array(), '', array( '_title' => $item['item_title'] ?? '' ) ); }
				}
				if ( ! $this->has_class( $element, 'ch-mega-nav' ) ) {
					$settings['_css_classes'] = trim( ( $settings['_css_classes'] ?? '' ) . ' ch-nav ch-mega-nav' );
					unset( $settings['_position'], $settings['_offset_orientation_h'], $settings['_offset_x'], $settings['_offset_y'] );
					$settings['_element_width'] = 'auto';
					$changes[] = 'menu in normal header flow';
				}
			}
			$this->patch( $element['elements'], $target, $changes );
		}
		unset( $element, $settings );
	}

	private function move_header_menu( array &$elements, array &$changes ): void {
		$has_header = false;
		foreach ( $elements as $element ) { $has_header = $has_header || $this->has_class( $element, 'ch-header' ); }
		if ( ! $has_header ) { return; }
		$menu = null;
		foreach ( $elements as $index => &$element ) {
			if ( $this->has_class( $element, 'ch-header' ) ) { continue; }
			foreach ( $element['elements'] ?? array() as $child_index => $child ) {
				if ( 'mega-menu' === ( $child['widgetType'] ?? '' ) && $this->has_class( $child, 'ch-mega-nav' ) ) {
					$menu = $child;
					array_splice( $element['elements'], $child_index, 1 );
					if ( ! $element['elements'] ) { unset( $elements[ $index ] ); }
					break 2;
				}
			}
		}
		unset( $element );
		if ( $menu ) {
			foreach ( $elements as &$element ) {
				if ( $this->has_class( $element, 'ch-header' ) ) {
					array_splice( $element['elements'], 1, 0, array( $menu ) );
					$changes[] = 'move detached menu into header';
					break;
				}
			}
			unset( $element );
		}
		$elements = array_values( $elements );
	}

	private function save( int $id, array $elements, array $changes, array $settings = array(), $document = null, bool $throw_on_error = false, ?string $expected_data = null ): void {
		WP_CLI::log( ( $this->apply ? 'Updating #' : 'Would update #' ) . $id . ': ' . implode( ', ', $changes ) );
		if ( ! $this->apply ) { return; }
		$snapshot = null;
		$mutated = false;
		try {
			$this->validate_elements( $elements );
			if ( ! $elements ) { throw new RuntimeException( 'Refused to replace the document with an empty canvas.' ); }
			if ( ! current_user_can( 'edit_post', $id ) ) { throw new RuntimeException( 'You cannot edit this document.' ); }
			if ( null !== $expected_data && $expected_data !== (string) get_post_meta( $id, '_elementor_data', true ) ) { throw new RuntimeException( 'Native content changed after planning; run the upgrade again.' ); }
			$document = $document ?: \Elementor\Plugin::$instance->documents->get( $id, false );
			if ( ! $document ) { throw new RuntimeException( 'Missing Elementor document #' . $id ); }
			$snapshot = $this->snapshot( $id );
			if ( ! metadata_exists( 'post', $id, self::BACKUP ) && ! add_post_meta( $id, self::BACKUP, wp_slash( $snapshot ), true ) ) { throw new RuntimeException( 'Backup failed for #' . $id ); }
			$mutated = true;
			update_post_meta( $id, '_elementor_edit_mode', 'builder' );
			// Loop::save() has no return value; verify the complete planned tree instead.
			$input = array( 'elements' => $elements );
			if ( $settings ) { $input['settings'] = $settings; }
			$result = $document->save( $input );
			if ( false === $result ) { throw new RuntimeException( 'Elementor rejected the document.' ); }
			$this->verify_elements( $elements, $this->read_elements( $id ) );
			if ( $settings ) { $this->verify_settings( $settings, (array) get_post_meta( $id, '_elementor_page_settings', true ), 'document settings' ); }
			update_post_meta( $id, '_chidemoon_native_editability', '2026-09-30' );
			++$this->changed;
		} catch ( Throwable $exception ) {
			if ( $snapshot && $mutated ) {
				try { $this->restore_snapshot( $id, $snapshot ); }
				catch ( Throwable $rollback ) { $exception = new RuntimeException( $exception->getMessage() . ' Rollback failed: ' . $rollback->getMessage() . '; recovery backup retained.' ); }
			}
			if ( $throw_on_error ) { throw $exception; }
			WP_CLI::error( 'Could not save #' . $id . ': ' . $exception->getMessage() . '; original state restored when possible.' );
		}
	}

	private function read_elements( int $id ): array {
		$raw = get_post_meta( $id, '_elementor_data', true );
		$elements = is_array( $raw ) ? $raw : json_decode( (string) $raw, true, 512, JSON_THROW_ON_ERROR );
		if ( ! is_array( $elements ) || ! $elements || array_keys( $elements ) !== array_keys( array_values( $elements ) ) ) { throw new RuntimeException( 'Native document data is empty or invalid.' ); }
		return $elements;
	}

	private function validate_elements( array $elements, ?array &$seen = null ): void {
		if ( null === $seen ) { $seen = array(); }
		if ( array_keys( $elements ) !== array_keys( array_values( $elements ) ) ) { throw new RuntimeException( 'Native elements are not an ordered list.' ); }
		foreach ( $elements as $element ) {
			$id = $element['id'] ?? null;
			$type = $element['elType'] ?? null;
			if ( ! is_string( $id ) || '' === $id || isset( $seen[ $id ] ) || ! in_array( $type, array( 'container', 'section', 'column', 'widget' ), true ) || ! is_array( $element['settings'] ?? array() ) || ! is_array( $element['elements'] ?? array() ) ) { throw new RuntimeException( 'Native document has an invalid or duplicate element.' ); }
			$seen[ $id ] = true;
			if ( 'widget' === $type && ( empty( $element['widgetType'] ) || ! \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $element['widgetType'] ) ) ) { throw new RuntimeException( 'Native document requires an unavailable widget.' ); }
			$this->validate_elements( $element['elements'] ?? array(), $seen );
		}
	}

	private function verify_elements( array $expected, array $stored ): void {
		if ( count( $expected ) !== count( $stored ) ) { throw new RuntimeException( 'Elementor did not preserve the planned element count.' ); }
		foreach ( $expected as $index => $element ) {
			$actual = $stored[ $index ] ?? array();
			if ( $element['id'] !== ( $actual['id'] ?? '' ) || $element['elType'] !== ( $actual['elType'] ?? '' ) || ( $element['widgetType'] ?? '' ) !== ( $actual['widgetType'] ?? '' ) || ( ! empty( $element['isInner'] ) && empty( $actual['isInner'] ) ) ) { throw new RuntimeException( 'Elementor did not preserve the planned element structure.' ); }
			$this->verify_settings( $element['settings'] ?? array(), $actual['settings'] ?? array(), 'element ' . $element['id'] );
			$this->verify_elements( $element['elements'] ?? array(), $actual['elements'] ?? array() );
		}
	}

	private function verify_settings( array $expected, array $stored, string $context ): void {
		foreach ( $expected as $key => $value ) {
			if ( ! array_key_exists( $key, $stored ) ) { throw new RuntimeException( 'Elementor dropped ' . $context . ' setting ' . $key ); }
			if ( is_array( $value ) ) {
				if ( ! is_array( $stored[ $key ] ) ) { throw new RuntimeException( 'Elementor changed ' . $context . ' setting ' . $key ); }
				if ( $value && array_keys( $value ) === array_keys( array_values( $value ) ) && count( $value ) !== count( $stored[ $key ] ) ) { throw new RuntimeException( 'Elementor changed a repeater in ' . $context ); }
				$this->verify_settings( $value, $stored[ $key ], $context . '.' . $key );
			} elseif ( $value !== $stored[ $key ] && ! ( is_numeric( $value ) && is_numeric( $stored[ $key ] ) && (string) ( 0 + $value ) === (string) ( 0 + $stored[ $key ] ) ) ) {
				throw new RuntimeException( 'Elementor changed ' . $context . ' setting ' . $key );
			}
		}
	}

	private function snapshot( int $id ): array {
		$post = get_post( $id );
		if ( ! $post ) { throw new RuntimeException( 'Missing post #' . $id ); }
		$keys = array( '_elementor_data', '_elementor_edit_mode', '_elementor_page_settings', '_elementor_template_type', '_elementor_version', '_elementor_pro_version', '_elementor_css', '_elementor_element_cache', '_elementor_controls_usage', '_elementor_page_assets', '_wp_page_template', '_chidemoon_native_editability' );
		foreach ( array_keys( (array) get_post_meta( $id ) ) as $key ) { if ( str_starts_with( $key, '_elementor_' ) ) { $keys[] = $key; } }
		$meta = array();
		foreach ( array_unique( $keys ) as $key ) { $meta[ $key ] = array( 'exists' => metadata_exists( 'post', $id, $key ), 'value' => get_post_meta( $id, $key, true ) ); }
		$fields = array();
		foreach ( array( 'post_content', 'post_excerpt', 'post_title', 'post_status' ) as $key ) { $fields[ $key ] = (string) $post->$key; }
		return array( 'content' => $fields['post_content'], 'fields' => $fields, 'meta' => $meta );
	}

	private function restore_snapshot( int $id, array $snapshot ): void {
		$errors = array();
		$restore_meta = static function () use ( $id, $snapshot, &$errors ): void {
			$keys = array_keys( $snapshot['meta'] );
			foreach ( array_keys( (array) get_post_meta( $id ) ) as $key ) { if ( str_starts_with( $key, '_elementor_' ) ) { $keys[] = $key; } }
			foreach ( array_unique( $keys ) as $key ) {
				try {
					$prior = $snapshot['meta'][ $key ] ?? array( 'exists' => false, 'value' => '' );
					if ( ! $prior['exists'] ) { if ( metadata_exists( 'post', $id, $key ) ) { delete_post_meta( $id, $key ); } }
					elseif ( ! metadata_exists( 'post', $id, $key ) || get_post_meta( $id, $key, true ) !== $prior['value'] ) { update_post_meta( $id, $key, wp_slash( $prior['value'] ) ); }
					if ( metadata_exists( 'post', $id, $key ) !== $prior['exists'] || ( $prior['exists'] && get_post_meta( $id, $key, true ) !== $prior['value'] ) ) { throw new RuntimeException( 'Stored value does not match the recovery snapshot.' ); }
				} catch ( Throwable $error ) { $errors[] = 'Metadata ' . $key . ': ' . $error->getMessage(); }
			}
		};
		// Clear partial Elementor state before WordPress hooks inspect the post.
		$restore_meta();
		try {
			$post = get_post( $id );
			if ( ! $post ) { throw new RuntimeException( 'Missing post #' . $id ); }
			$fields = array( 'ID' => $id );
			foreach ( $snapshot['fields'] as $key => $value ) { if ( $post->$key !== $value ) { $fields[ $key ] = $value; } }
			if ( count( $fields ) > 1 ) {
				// wp_update_post merges the current page template into its update. Use a
				// valid value here; restore the exact dynamic/obsolete template below.
				$fields['page_template'] = 'default';
				$result = wp_update_post( wp_slash( $fields ), true );
				if ( is_wp_error( $result ) || ! $result ) { throw new RuntimeException( is_wp_error( $result ) ? $result->get_error_message() : 'WordPress rejected the post update.' ); }
			}
		} catch ( Throwable $error ) { $errors[] = 'Post fields: ' . $error->getMessage(); }
		// Post-save hooks can change caches/settings or fail after writing fields.
		// Always attempt every metadata restore again before reporting failures.
		$restore_meta();
		$restored = get_post( $id );
		foreach ( $snapshot['fields'] as $key => $value ) { if ( ! $restored || $restored->$key !== $value ) { $errors[] = 'Post field ' . $key . ' does not match the recovery snapshot.'; } }
		if ( $errors ) { throw new RuntimeException( implode( ' ', array_unique( $errors ) ) ); }
	}

	private function publish_document( int $id ): void {
		$result = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
		if ( is_wp_error( $result ) || ! $result || 'publish' !== get_post_status( $id ) ) { throw new RuntimeException( 'Could not publish the verified native document.' ); }
	}

	public function run(): void {
		if ( ! class_exists( '\\ElementorPro\\Plugin' ) ) { WP_CLI::error( 'Elementor Pro must be active.' ); }
		if ( $this->apply && ! current_user_can( 'edit_theme_options' ) ) { WP_CLI::error( 'Run with an administrator using --user=<id>.' ); }
		$this->setup_templates();
		$this->page( 'categories', 'دسته‌بندی‌ها', array( $this->box( array( $this->heading( 'دسته‌بندی‌های چیدمون', 'h1' ), $this->template_widget() ), 'ch-section ch-main', array( 'html_tag' => 'main', '_element_id' => 'chidemoon-content' ) ) ) );
		$this->page( 'product-comparison', 'مقایسهٔ محصولات', array( $this->box( array( $this->heading( 'مقایسهٔ مشخصات محصولات', 'h1' ), $this->text( '<p>دو تا چهار محصول انتخاب کن و اطلاعات ثبت‌شدهٔ آن‌ها را کنار هم ببین.</p>' ), $this->widget( 'chidemoon-compare-table', array( 'show_picker' => 'yes', 'show_status' => 'yes' ) ) ), 'ch-section ch-main', array( 'html_tag' => 'main', '_element_id' => 'chidemoon-content' ) ) ) );
		$documents = array();
		foreach ( array( 'home', 'guides', 'comparisons', 'shop-the-look' ) as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page ) { $documents[ $page->ID ] = $slug; }
		}
		foreach ( array( 'site-header', 'site-footer', 'post-single', 'post-archive', 'search-results', 'not-found', 'product-single', 'product-archive' ) as $slug ) {
			$template = get_page_by_path( 'chidemoon-' . $slug, OBJECT, 'elementor_library' );
			if ( $template ) { $documents[ $template->ID ] = $slug; }
		}
		foreach ( $documents as $id => $target ) {
			if ( ! get_post_meta( $id, '_chidemoon_elementor_rebuild', true ) ) {
				WP_CLI::warning( 'Skipped unmanaged document #' . $id );
				continue;
			}
			$raw = (string) get_post_meta( $id, '_elementor_data', true );
			$elements = json_decode( $raw, true );
			if ( ! is_array( $elements ) ) { WP_CLI::error( 'Invalid Elementor document #' . $id ); }
			$this->remember_ids( $elements );
			$changes = array();
			$this->patch( $elements, $target, $changes );
			if ( 'site-header' === $target ) { $this->move_header_menu( $elements, $changes ); }
			if ( $changes ) { $this->save( (int) $id, $elements, $changes, expected_data: $raw ); }
		}
		if ( $this->apply ) {
			$types = (array) get_option( 'elementor_cpt_support', array( 'page', 'post' ) );
			update_option( 'elementor_cpt_support', array_values( array_unique( array_merge( $types, array( 'page', 'post', 'product' ) ) ) ) );
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
		WP_CLI::success( $this->apply ? 'Updated ' . $this->changed . ' native documents. Editorial bodies: run editorial-elementor-upgrade.php apply products.' : 'Dry run complete. No content or settings saved.' );
	}
}

$unknown = array_diff( $args ?? array(), array( 'apply' ) );
if ( $unknown ) { WP_CLI::error( 'Unknown argument(s): ' . implode( ', ', $unknown ) ); }
( new Chidemoon_Elementor_Editability_Upgrade( in_array( 'apply', $args ?? array(), true ) ) )->run();
