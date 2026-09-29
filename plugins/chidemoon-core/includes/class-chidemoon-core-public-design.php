<?php
/**
 * The small public baseline shared by Elementor templates and Core widgets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Public_Design {
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 30 );
		add_action( 'elementor/query/chidemoon_looks', array( __CLASS__, 'looks_query' ) );
		add_action( 'elementor/query/chidemoon_related_posts', array( __CLASS__, 'related_posts_query' ) );
		add_shortcode( 'chidemoon_room_filters', array( __CLASS__, 'room_filters' ) );
		add_filter( 'hello_elementor_skip_link_url', static fn(): string => '#chidemoon-content' );
		add_filter( 'hello_elementor_skip_link_text', static fn(): string => 'رفتن به محتوا' );
		add_filter( 'elementor/fonts/additional_fonts', array( __CLASS__, 'fonts' ) );
		add_filter( 'elementor/utils/get_the_archive_title', static function ( string $title ): string {
			return is_home() ? get_the_title( (int) get_option( 'page_for_posts' ) ) : $title;
		} );
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'featured_image' ), 10, 2 );
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'content_card_details' ), 20, 2 );
		add_action( 'woocommerce_after_shop_loop_item_title', array( __CLASS__, 'loop_merchant' ), 11 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( __CLASS__, 'loop_offer_text' ), 110, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_args', array( __CLASS__, 'loop_offer_label' ), 110, 2 );
		add_filter( 'wc_price', array( __CLASS__, 'public_price_digits' ), 100 );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widgets' ) );
		add_action( 'pre_get_posts', static function ( WP_Query $query ): void {
			if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
				$query->set( 'post_type', array( 'post', 'product' ) );
			}
		} );
		add_filter( 'gettext', static function ( string $translation, string $text, string $domain ): string {
			return 'hello-elementor' === $domain && 'Skip to content' === $text && is_rtl() ? 'رفتن به محتوا' : $translation;
		}, 10, 3 );
	}

	public static function widgets( $manager ): void {
		require_once CHIDEMOON_CORE_DIR . 'includes/class-chidemoon-core-elementor-product-offer-widget.php';
		require_once CHIDEMOON_CORE_DIR . 'includes/class-chidemoon-core-elementor-room-filters-widget.php';
		$manager->register( new Chidemoon_Core_Elementor_Product_Offer_Widget() );
		$manager->register( new Chidemoon_Core_Elementor_Room_Filters_Widget() );
	}

	public static function featured_image( string $content, $widget ): string {
		if ( 'theme-post-featured-image' === $widget->get_name() && get_post_meta( get_the_ID(), '_chidemoon_native_look', true ) ) {
			return '';
		}
		return $content;
	}

	public static function related_posts_query( WP_Query $query ): void {
		$post_id = (int) get_queried_object_id();
		if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
			return;
		}
		$query->set( 'post__not_in', array( $post_id ) );
		$query->set( 'posts_per_page', 2 );
		$query->set( 'ignore_sticky_posts', true );
		$categories = wp_get_post_categories( $post_id );
		if ( $categories && get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'category__in' => $categories, 'post__not_in' => array( $post_id ) ) ) ) {
			$query->set( 'category__in', $categories );
		}
	}

	public static function loop_merchant(): void {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$merchant = trim( (string) $product->get_meta( Chidemoon_Core_Affiliate::META_MERCHANT_NAME ) );
		if ( $merchant ) {
			echo '<p class="ch-product-card-merchant">' . esc_html( 'فروشنده: ' . $merchant ) . '</p>';
		}
	}

	public static function loop_offer_text( string $text, WC_Product $product ): string {
		if ( $product->is_type( 'external' ) && ! Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) {
			return 'دیدن جزئیات محصول';
		}
		return $text;
	}

	public static function loop_offer_label( array $args, WC_Product $product ): array {
		if ( $product->is_type( 'external' ) ) {
			$args['attributes'] = isset( $args['attributes'] ) && is_array( $args['attributes'] ) ? $args['attributes'] : array();
			$args['attributes']['aria-label'] = sprintf( Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ? 'دیدن پیشنهاد فروشنده برای %s' : 'دیدن جزئیات محصول %s', $product->get_name() );
		}
		return $args;
	}

	public static function public_price_digits( string $html ): string {
		return is_admin() ? $html : self::persian_digits( $html );
	}

	public static function content_card_details( string $content, $widget ): string {
		if ( ! in_array( $widget->get_name(), array( 'posts', 'archive-posts' ), true ) || ! method_exists( $widget, 'get_settings_for_display' ) || ! str_contains( $content, 'elementor-post__text' ) || ! class_exists( 'DOMDocument' ) ) {
			return $content;
		}
		$classes = $widget->get_settings_for_display( '_css_classes' );
		if ( ! is_string( $classes ) || ! preg_match( '/(?:^|\s)ch-editorial-feed(?:\s|$)/', $classes ) ) {
			return $content;
		}
		$document = new DOMDocument( '1.0', 'UTF-8' );
		$previous_errors = libxml_use_internal_errors( true );
		$loaded = $document->loadHTML( '<?xml encoding="UTF-8"><div id="chidemoon-card-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_errors );
		if ( ! $loaded ) {
			return $content;
		}
		$xpath = new DOMXPath( $document );
		foreach ( $xpath->query( '//article[contains(concat(" ", normalize-space(@class), " "), " elementor-post ")]' ) as $article ) {
			$classes = (string) $article->getAttribute( 'class' );
			if ( ! preg_match( '/(?:^|\s)post-(\d+)(?:\s|$)/', $classes, $match ) ) {
				continue;
			}
			$post_id = (int) $match[1];
			$text = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " elementor-post__text ")]', $article )->item( 0 );
			if ( ! $text ) {
				continue;
			}
			$type = 'product' === get_post_type( $post_id ) ? 'product' : 'post';
			$label = 'product' === $type ? 'محصول' : self::post_card_label( $post_id );
			$badge = $document->createElement( 'span' );
			$badge->setAttribute( 'class', 'ch-content-type ch-content-type--' . $type );
			$badge->appendChild( $document->createTextNode( $label ) );
			$text->insertBefore( $badge, $text->firstChild );
			if ( 'product' === $type ) {
				$product = wc_get_product( $post_id );
				if ( $product instanceof WC_Product ) {
					$meta = $document->createElement( 'p' );
					$meta->setAttribute( 'class', 'ch-search-product-meta' );
					$price = trim( html_entity_decode( wp_strip_all_tags( $product->get_price_html() ), ENT_QUOTES, 'UTF-8' ) );
					if ( $price ) {
						$amount = $document->createElement( 'span' );
						$amount->setAttribute( 'class', 'ch-search-product-price' );
						$amount->appendChild( $document->createTextNode( self::persian_digits( $price ) ) );
						$meta->appendChild( $amount );
					}
					$merchant = trim( (string) $product->get_meta( Chidemoon_Core_Affiliate::META_MERCHANT_NAME ) );
					if ( $merchant ) {
						$seller = $document->createElement( 'span' );
						$seller->setAttribute( 'class', 'ch-search-product-merchant' );
						$seller->appendChild( $document->createTextNode( 'فروشنده: ' . $merchant ) );
						$meta->appendChild( $seller );
					}
					if ( $meta->hasChildNodes() ) {
						$title = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " elementor-post__title ")]', $text )->item( 0 );
						if ( $title && $title->nextSibling ) {
							$text->insertBefore( $meta, $title->nextSibling );
						} else {
							$text->appendChild( $meta );
						}
					}
				}
			}
			$action = $xpath->query( './/a[contains(concat(" ", normalize-space(@class), " "), " elementor-post__read-more ")]', $text )->item( 0 );
			if ( $action ) {
				$action->textContent = 'product' === $type ? 'دیدن محصول' : 'خواندن مطلب';
				$action->setAttribute( 'aria-label', $action->textContent . ': ' . get_the_title( $post_id ) );
			}
		}
		$root = $document->getElementById( 'chidemoon-card-root' );
		if ( ! $root ) {
			return $content;
		}
		$result = '';
		foreach ( $root->childNodes as $child ) {
			$result .= $document->saveHTML( $child );
		}
		return $result;
	}

	private static function post_card_label( int $post_id ): string {
		if ( get_post_meta( $post_id, '_chidemoon_native_look', true ) ) {
			$elements = json_decode( (string) get_post_meta( $post_id, '_elementor_data', true ), true );
			$stack = is_array( $elements ) ? $elements : array();
			while ( $stack ) {
				$element = array_pop( $stack );
				if ( 'chidemoon-shop-the-look' === ( $element['widgetType'] ?? '' ) && self::has_eligible_look_hotspot( $element['settings']['hotspots'] ?? array() ) ) {
					return 'چیدمان قابل خرید';
				}
				foreach ( (array) ( $element['elements'] ?? array() ) as $child ) {
					$stack[] = $child;
				}
			}
			return 'ایدهٔ مفهومی';
		}
		$categories = wp_get_post_categories( $post_id, array( 'fields' => 'slugs' ) );
		if ( in_array( 'guides', $categories, true ) ) {
			return 'راهنما';
		}
		if ( in_array( 'comparisons', $categories, true ) ) {
			return 'مقایسه';
		}
		if ( in_array( 'room-ideas', $categories, true ) ) {
			return 'ایدهٔ چیدمان';
		}
		return 'مطلب';
	}

	private static function has_eligible_look_hotspot( $hotspots ): bool {
		if ( ! is_array( $hotspots ) || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}
		foreach ( $hotspots as $spot ) {
			if ( ! is_array( $spot ) ) {
				continue;
			}
			$product_id = absint( $spot['product_id'] ?? $spot['productId'] ?? 0 );
			if ( ! $product_id ) {
				$source_key = sanitize_text_field( (string) ( $spot['product_source_key'] ?? $spot['productSourceKey'] ?? '' ) );
				if ( preg_match( '/^[a-z][a-z0-9_-]*:[a-zA-Z0-9_-]+$/', $source_key ) ) {
					$matches = get_posts( array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'meta_key'       => Chidemoon_Core_Affiliate::META_SOURCE_KEY,
						'meta_value'     => $source_key,
					) );
					$product_id = $matches ? (int) $matches[0] : 0;
				}
			}
			$product = $product_id ? wc_get_product( $product_id ) : null;
			if ( $product instanceof WC_Product && Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) {
				return true;
			}
		}
		return false;
	}

	private static function persian_digits( string $text ): string {
		return strtr( $text, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}

	public static function fonts( array $fonts ): array {
		$fonts['Vazirmatn'] = \Elementor\Fonts::LOCAL;
		$fonts['Estedad'] = \Elementor\Fonts::LOCAL;
		return $fonts;
	}

	public static function looks_query( WP_Query $query ): void {
		$query->set( 'post_type', 'post' );
		$query->set( 'post_status', 'publish' );
		$query->set( 'tag', 'shop-the-look' );
		$room = isset( $_GET['room'] ) ? sanitize_title( wp_unslash( (string) $_GET['room'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $room && term_exists( $room, Chidemoon_Core_Shop_The_Look::TAXONOMY ) ) {
			$query->set( 'tax_query', array( array( 'taxonomy' => Chidemoon_Core_Shop_The_Look::TAXONOMY, 'field' => 'slug', 'terms' => $room ) ) );
		}
	}

	public static function room_filters( array $attributes = array() ): string {
		$terms = get_terms( array( 'taxonomy' => Chidemoon_Core_Shop_The_Look::TAXONOMY, 'hide_empty' => true ) );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return '';
		}
		$page = get_page_by_path( 'shop-the-look' );
		$url = $page ? get_permalink( $page ) : home_url( '/shop-the-look/' );
		$selected = isset( $_GET['room'] ) ? sanitize_title( wp_unslash( (string) $_GET['room'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$fragment = '#ch-room-filters';
		$html = '<nav id="ch-room-filters" class="ch-room-filters" aria-label="فضای خانه"><a href="' . esc_url( $url . $fragment ) . '"' . ( '' === $selected ? ' aria-current="page"' : '' ) . '>' . esc_html( $attributes['all_label'] ?? 'همهٔ فضاها' ) . '</a>';
		foreach ( $terms as $term ) {
			$html .= '<a href="' . esc_url( add_query_arg( 'room', $term->slug, $url ) . $fragment ) . '"' . ( $selected === $term->slug ? ' aria-current="page"' : '' ) . '>' . esc_html( $term->name ) . '</a>';
		}
		return $html . '</nav>';
	}

	/** Searchable Elementor options include drafts so editors can prepare a page before review. */
	public static function product_options(): array {
		$options = array();
		foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $post ) {
			$options[ $post->ID ] = $post->post_title . ' (#' . $post->ID . ')';
		}
		return $options;
	}

	public static function enqueue(): void {
		$path = CHIDEMOON_CORE_DIR . 'assets/css/public-design.css';
		wp_enqueue_style(
			'chidemoon-public-design',
			CHIDEMOON_CORE_URL . 'assets/css/public-design.css',
			array(),
			file_exists( $path ) ? (string) filemtime( $path ) : CHIDEMOON_CORE_VERSION
		);
		$script = CHIDEMOON_CORE_DIR . 'assets/js/public-design.js';
		wp_enqueue_script(
			'chidemoon-public-design',
			CHIDEMOON_CORE_URL . 'assets/js/public-design.js',
			array(),
			file_exists( $script ) ? (string) filemtime( $script ) : CHIDEMOON_CORE_VERSION,
			true
		);
		wp_add_inline_script( 'chidemoon-public-design', 'window.chidemoonPublicDesign = ' . wp_json_encode( array( 'searchQuery' => is_search() ? get_search_query( false ) : '' ) ) . ';', 'before' );
	}
}
